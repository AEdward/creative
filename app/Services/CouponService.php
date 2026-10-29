<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function reserve(Order $order, string $code): void
    {
        $coupon = Coupon::where('code', Str::upper(trim($code)))->lockForUpdate()->first();
        $uses = $coupon ? CouponRedemption::where('coupon_id', $coupon->id)->whereIn('status', ['reserved', 'redeemed'])->lockForUpdate()->get() : collect();
        if (! $coupon || ! $coupon->active || $coupon->starts_at?->isFuture() || $coupon->expires_at?->isPast() || ($coupon->course_id && ! $order->items()->where('course_id', $coupon->course_id)->exists()) || $coupon->percent < 1 || $coupon->percent > 99 || $uses->count() >= $coupon->max_uses || $uses->where('user_id', $order->user_id)->count() >= $coupon->per_user_limit) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is unavailable or its usage limit has been reached.']);
        }
        $discount = intdiv($order->subtotal_minor * $coupon->percent, 100);
        $order->update(['discount_minor' => $discount, 'total_minor' => $order->subtotal_minor - $discount]);
        CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id, 'discount_minor' => $discount, 'status' => 'reserved']);
    }
}
