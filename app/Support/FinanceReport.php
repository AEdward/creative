<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PaymentSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money figures for a date range (Africa/Nairobi days). "Received" = payments verified in the range;
 * "Refunded" = refunds approved in the range. All amounts are minor units (cents) in ETB.
 */
class FinanceReport
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    public static function forDays(string $from, string $to): self
    {
        return new self(CarbonImmutable::parse($from, 'Africa/Nairobi')->startOfDay()->utc(), CarbonImmutable::parse($to, 'Africa/Nairobi')->endOfDay()->utc());
    }

    /** Orders whose payment was verified in the range (they may have been refunded later). */
    private function paidOrders()
    {
        return Order::query()->whereIn('status', ['paid', 'refunded'])
            ->whereHas('payment', fn ($q) => $q->whereIn('status', ['verified_success', 'refunded'])->whereBetween('verified_at', [$this->start, $this->end]));
    }

    public function totals(): array
    {
        $paid = (clone $this->paidOrders())->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_minor),0) as received, COALESCE(SUM(discount_minor),0) as discounts, COALESCE(SUM(vat_minor),0) as vat')->first();
        $refunds = Order::where('refund_status', 'refunded')->whereBetween('refund_reviewed_at', [$this->start, $this->end])->selectRaw('COUNT(*) as n, COALESCE(SUM(total_minor),0) as amount')->first();
        $pending = Order::where('status', 'awaiting_payment')->whereHas('submissions', fn ($q) => $q->where('status', 'pending'))->selectRaw('COUNT(*) as n, COALESCE(SUM(total_minor),0) as amount')->first();

        return [
            'orders' => (int) $paid->orders,
            'received' => (int) $paid->received,
            'refunded' => (int) $refunds->amount,
            'refund_count' => (int) $refunds->n,
            'net' => (int) $paid->received - (int) $refunds->amount,
            'discounts' => (int) $paid->discounts,
            'vat' => (int) $paid->vat,
            'pending' => (int) $pending->amount,
            'pending_count' => (int) $pending->n,
        ];
    }

    /** @return Collection<string, array{received:int, orders:int}> keyed by YYYY-MM (Nairobi) */
    public function byMonth(): Collection
    {
        return (clone $this->paidOrders())->with('payment:id,order_id,verified_at')->get(['id', 'total_minor'])
            ->groupBy(fn (Order $o) => $o->payment->verified_at->timezone('Africa/Nairobi')->format('Y-m'))
            ->map(fn ($orders) => ['received' => (int) $orders->sum('total_minor'), 'orders' => $orders->count()])
            ->sortKeys();
    }

    public function byCourse(): Collection
    {
        return DB::table('order_items')->joinSub((clone $this->paidOrders())->select('id', 'total_minor', 'status'), 'paid', 'paid.id', '=', 'order_items.order_id')
            ->selectRaw('order_items.title_snapshot as course, COUNT(*) as orders, SUM(paid.total_minor) as received, SUM(CASE WHEN paid.status = \'refunded\' THEN 1 ELSE 0 END) as refunds')
            ->groupBy('order_items.title_snapshot')->orderByDesc('received')->get();
    }

    public function byBank(): Collection
    {
        return PaymentSubmission::query()->where('payment_submissions.status', 'approved')
            ->joinSub((clone $this->paidOrders())->select('id', 'total_minor'), 'paid', 'paid.id', '=', 'payment_submissions.order_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_submissions.payment_method_id')
            ->selectRaw('payment_methods.name as bank, COUNT(*) as orders, SUM(paid.total_minor) as received')
            ->groupBy('payment_methods.name')->orderByDesc('received')->get();
    }

    public function coupons(): Collection
    {
        return DB::table('coupon_redemptions')->where('coupon_redemptions.status', 'redeemed')
            ->joinSub((clone $this->paidOrders())->select('id'), 'paid', 'paid.id', '=', 'coupon_redemptions.order_id')
            ->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')
            ->selectRaw('coupons.code as code, COUNT(*) as uses, SUM(coupon_redemptions.discount_minor) as discount')
            ->groupBy('coupons.code')->orderByDesc('uses')->get();
    }

    /** One CSV row per order placed in the range, for the accountant. */
    public function csvRows(): \Generator
    {
        yield ['Date (Nairobi)', 'Order reference', 'Learner', 'Email', 'Course', 'Paid via', 'Transaction ID', 'Price', 'Discount', 'Total', 'VAT included', 'Status', 'Refund', 'Currency'];
        $orders = Order::with(['user:id,name,email', 'items:id,order_id,title_snapshot', 'payment:id,order_id,provider', 'submissions.method:id,name'])
            ->whereBetween('created_at', [$this->start, $this->end])->orderBy('id');
        foreach ($orders->lazy(200) as $order) {
            $submission = $order->submissions->sortByDesc('id')->first();
            yield array_map([self::class, 'safe'], [
                $order->created_at->timezone('Africa/Nairobi')->format('Y-m-d H:i'), $order->reference, $order->user?->name, $order->user?->email,
                $order->items->pluck('title_snapshot')->join('; '), $submission?->method?->name ?? str_replace('_', ' ', (string) $order->payment?->provider),
                $submission?->transaction_reference, self::money($order->subtotal_minor), self::money($order->discount_minor), self::money($order->total_minor),
                self::money($order->vat_minor), $order->status, $order->refund_status, $order->currency,
            ]);
        }
    }

    public static function money(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    /** Stop spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    private static function safe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}
