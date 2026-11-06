<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\CouponRedemption;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentProcessor
{
    public function handle(PaymentGateway $gateway, array $evidence): Order
    {
        $receipt = $gateway->verify($evidence);

        $ownerId = Order::where('reference', $receipt->orderReference)->value('user_id');

        return DB::transaction(function () use ($receipt, $ownerId) {
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            $order = Order::where('reference', $receipt->orderReference)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('order_id', $order->id)->where('provider', $receipt->provider)->lockForUpdate()->firstOrFail();
            if ($receipt->providerReference !== $payment->provider_reference || $receipt->amountMinor !== $payment->amount_minor || $receipt->amountMinor !== $order->total_minor || $receipt->currency !== $payment->currency || $receipt->currency !== $order->currency) {
                throw ValidationException::withMessages(['payment' => 'Verified payment details do not match this order.']);
            }
            $event = PaymentEvent::firstOrCreate(['provider' => $receipt->provider, 'external_event_id' => $receipt->eventId], ['payment_id' => $payment->id, 'event_type' => $receipt->success ? 'verified_success' : 'verified_failure', 'fingerprint' => $receipt->fingerprint()]);
            if ($event->payment_id !== $payment->id || $event->fingerprint !== $receipt->fingerprint()) {
                throw ValidationException::withMessages(['payment' => 'Payment event identity has already been used with different details.']);
            }
            if ($event->processed_at) {
                return $order;
            }
            if ($order->status === 'paid' || (! $receipt->success && $order->status === 'failed')) {
                $event->update(['processed_at' => now()]);

                return $order;
            }
            // A verified success that arrives after the 24h expiry still grants access: the customer was charged.
            $late = $receipt->success && $order->status === 'expired';
            if ((! in_array($order->status, ['pending', 'awaiting_payment']) && ! $late) || in_array($payment->status, ['verified_success', 'verified_failure'])) {
                throw ValidationException::withMessages(['payment' => 'A terminal payment conflict requires operator reconciliation.']);
            }
            if ($receipt->success) {
                foreach ($order->items as $item) {
                    $existing = Enrollment::where('user_id', $order->user_id)->where('course_id', $item->course_id)->first();
                    if ($existing && ($existing->status !== 'active' || $existing->expires_at?->isPast())) {
                        throw ValidationException::withMessages(['payment' => 'Existing enrollment requires operator review before paid access can be granted.']);
                    }
                    Enrollment::firstOrCreate(['user_id' => $order->user_id, 'course_id' => $item->course_id], ['source_order_item_id' => $item->id, 'source' => 'verified_payment', 'status' => 'active', 'enrolled_at' => now()]);
                }
            }
            $payment->update(['status' => $receipt->success ? 'verified_success' : 'verified_failure', 'verified_at' => now()]);
            $order->update(['status' => $receipt->success ? 'paid' : 'failed']);
            CouponRedemption::where('order_id', $order->id)->where('status', 'reserved')->update(['status' => $receipt->success ? 'redeemed' : 'released']);
            $event->update(['processed_at' => now()]);
            AuditLog::create(['actor_id' => null, 'action' => $receipt->success ? 'payment.verified_success' : 'payment.verified_failure', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['provider' => $receipt->provider, 'status' => $order->status, 'late' => $late]]);

            return $order;
        }, 3);
    }
}
