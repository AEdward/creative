<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Support\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Refunds are decided case by case: a learner asks, an admin approves (access removed, order refunded)
 * or declines with a note. The money itself is returned outside the site, by bank transfer.
 */
class RefundService
{
    public function request(User $user, Order $order, string $reason): void
    {
        DB::transaction(function () use ($user, $order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === $user->id, 404);
            if (! $order->canRequestRefund()) {
                throw ValidationException::withMessages(['reason' => 'A refund can’t be requested for this order.']);
            }
            $order->update(['refund_status' => 'requested', 'refund_reason' => $reason, 'refund_requested_at' => now()]);
            AuditLog::create(['actor_id' => $user->id, 'action' => 'order.refund_requested', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['reason' => $reason]]);
        });

        $admins = User::staffFor('finance');
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new EngagementNotification(['title' => __('Refund requested'), 'body' => $user->name.' · '.$order->items()->value('title_snapshot'), 'url' => Admin::url('orders')]));
        }
    }

    public function approve(Order $order, User $admin, ?string $note): void
    {
        DB::transaction(function () use ($order, $admin, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($order->status !== 'paid' || ! in_array($order->refund_status, [null, 'requested'], true)) {
                throw ValidationException::withMessages(['order' => 'This order can’t be refunded.']);
            }
            Enrollment::where('user_id', $order->user_id)->whereIn('source_order_item_id', $order->items->pluck('id'))->update(['status' => 'refunded']);
            Payment::where('order_id', $order->id)->update(['status' => 'refunded']);
            $order->update(['status' => 'refunded', 'refund_status' => 'refunded', 'refund_note' => $note, 'refund_reviewed_at' => now(), 'refund_reviewer_id' => $admin->id]);
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'order.refunded', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['amount_minor' => $order->total_minor, 'note' => $note]]);
        }, 3);
        $order->user?->notify(new EngagementNotification(['title' => __('Refund approved'), 'body' => __('Your refund for :course was approved. Course access has been removed.', ['course' => $order->items()->value('title_snapshot')]).($note ? ' '.$note : ''), 'url' => '/payments']));
    }

    public function decline(Order $order, User $admin, string $note): void
    {
        DB::transaction(function () use ($order, $admin, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->refund_status !== 'requested') {
                throw ValidationException::withMessages(['order' => 'There is no open refund request on this order.']);
            }
            $order->update(['refund_status' => 'declined', 'refund_note' => $note, 'refund_reviewed_at' => now(), 'refund_reviewer_id' => $admin->id]);
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'order.refund_declined', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['note' => $note]]);
        }, 3);
        $order->user?->notify(new EngagementNotification(['title' => __('Refund declined'), 'body' => $note, 'url' => '/payments']));
    }
}
