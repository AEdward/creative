<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /** Longest coupon code accepted anywhere (creation, checkout field, quote). */
    public const MAX_CODE_LENGTH = 40;

    public static function normalize(?string $code): ?string
    {
        return filled($code) ? Str::upper(trim($code)) : null;
    }

    /**
     * The single set of coupon rules. Returns the reason a coupon can't be used for this learner and course,
     * or null when it can. Pass $lock = true inside a transaction to lock the coupon and its uses.
     */
    public function rejection(?Coupon $coupon, int $userId, int $courseId, bool $lock = false): ?string
    {
        if (! $coupon || ! $coupon->active || $coupon->percent < 1 || $coupon->percent > 99) {
            return 'This coupon is not valid.';
        }
        $uses = CouponRedemption::where('coupon_id', $coupon->id)->whereIn('status', ['reserved', 'redeemed']);
        $uses = ($lock ? $uses->lockForUpdate() : $uses)->get(['user_id']);

        return match (true) {
            $coupon->starts_at?->isFuture() => 'This coupon is not active yet.',
            $coupon->expires_at?->isPast() => 'This coupon has expired.',
            $coupon->course_id && $coupon->course_id !== $courseId => 'This coupon does not apply to this course.',
            $uses->count() >= $coupon->max_uses => 'This coupon has reached its usage limit.',
            $uses->where('user_id', $userId)->count() >= $coupon->per_user_limit => 'You have already used this coupon.',
            default => null,
        };
    }

    public static function discount(Coupon $coupon, int $subtotalMinor): int
    {
        return intdiv($subtotalMinor * $coupon->percent, 100);
    }

    public function reserve(Order $order, string $code): void
    {
        $coupon = Coupon::where('code', self::normalize($code))->lockForUpdate()->first();
        $courseId = (int) $order->items()->value('course_id');
        if ($reason = $this->rejection($coupon, $order->user_id, $courseId, lock: true)) {
            throw ValidationException::withMessages(['coupon' => $reason]);
        }
        $discount = self::discount($coupon, $order->subtotal_minor);
        $order->update(['discount_minor' => $discount, 'total_minor' => $order->subtotal_minor - $discount]);
        CouponRedemption::create(['coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id, 'discount_minor' => $discount, 'status' => 'reserved']);
    }
}
