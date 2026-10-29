<?php

namespace App\Services\Payments;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private PaymentGateway $gateway) {}

    public function create(User $user, Course $course, string $key, ?string $coupon = null): Order
    {
        $actor = $user->fresh();
        abort_unless($actor && $actor->status === 'active' && $actor->hasVerifiedEmail(), 403);
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid checkout key is required.']);
        }
        $checkoutKey = hash('sha256', $actor->id.'|'.$key);
        $existing = Order::where('checkout_key', $checkoutKey)->where('user_id', $actor->id)->with('items', 'payment')->first();
        if ($existing) {
            if ((int) $existing->items->first()?->course_id !== $course->id) {
                throw ValidationException::withMessages(['course' => 'This checkout key belongs to a different course.']);
            }
            if ($existing->payment?->provider_reference || $existing->status !== 'pending') {
                return $existing;
            }
        }
        abort_unless(config('academy.payments_enabled') && $this->gateway->available(), 503, 'Checkout is disabled until a payment provider is verified.');
        $course = $course->fresh();
        abort_unless($course && Course::published()->whereKey($course->id)->exists(), 404);
        if ($course->is_free || $course->price_minor < 1) {
            throw ValidationException::withMessages(['course' => 'Use free enrollment for this course.']);
        }
        if (Enrollment::where('user_id', $actor->id)->where('course_id', $course->id)->exists()) {
            throw ValidationException::withMessages(['course' => 'An enrollment already exists. Existing access must be reviewed before another purchase.']);
        }
        $order = DB::transaction(function () use ($actor, $course, $checkoutKey, $coupon) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (Enrollment::where('user_id', $actor->id)->where('course_id', $course->id)->exists()) {
                throw ValidationException::withMessages(['course' => 'An enrollment already exists.']);
            }
            $pending = Order::where('user_id', $actor->id)->whereIn('status', ['pending', 'awaiting_payment'])->whereHas('items', fn ($items) => $items->where('course_id', $course->id))->first();
            if ($pending) {
                if ($pending->checkout_key !== $checkoutKey) {
                    throw ValidationException::withMessages(['idempotency_key' => 'A checkout is already pending. Resume it using its original checkout key.']);
                }

                return $pending->load('items', 'payment');
            }
            $order = Order::firstOrCreate(['checkout_key' => $checkoutKey], ['user_id' => $actor->id, 'reference' => (string) Str::uuid(), 'currency' => $course->currency, 'subtotal_minor' => $course->price_minor, 'discount_minor' => 0, 'total_minor' => $course->price_minor, 'status' => 'pending']);
            if ($order->wasRecentlyCreated) {
                $order->items()->create(['course_id' => $course->id, 'title_snapshot' => $course->title, 'unit_price_minor' => $course->price_minor]);
                if (filled($coupon)) {
                    app(CouponService::class)->reserve($order, $coupon);
                }
                Payment::create(['order_id' => $order->id, 'provider' => $this->gateway->name(), 'idempotency_key' => (string) Str::uuid(), 'amount_minor' => $order->total_minor, 'currency' => $order->currency, 'status' => 'initiated']);
            }

            return $order->load('items', 'payment');
        }, 3);
        if ((int) $order->items->first()?->course_id !== $course->id) {
            throw ValidationException::withMessages(['course' => 'Checkout key conflict.']);
        }
        if ($order->payment->provider_reference) {
            return $order;
        }
        $session = $this->gateway->initiate($order);
        DB::transaction(function () use ($order, $session) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                return;
            }
            Payment::where('order_id', $order->id)->update(['provider_reference' => $session->providerReference, 'status' => 'pending']);
            $locked->update(['status' => 'awaiting_payment']);
        }, 3);

        return $order->fresh()->load('items', 'payment');
    }
}
