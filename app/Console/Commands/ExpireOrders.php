<?php

namespace App\Console\Commands;

use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOrders extends Command
{
    protected $signature = 'academy:expire-orders';

    protected $description = 'Expire unpaid checkout attempts older than 24 hours without granting access';

    public function handle(): int
    {
        $count = 0;
        Order::whereIn('status', ['pending', 'awaiting_payment'])->where('created_at', '<', now()->subDay())->chunkById(100, function ($orders) use (&$count): void {
            foreach ($orders as $candidate) {
                DB::transaction(function () use ($candidate, &$count): void {
                    User::whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
                    $order = Order::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($order->status, ['pending', 'awaiting_payment']) || ! $order->created_at->lt(now()->subDay())) {
                        return;
                    }
                    $order->update(['status' => 'expired']);
                    CouponRedemption::where('order_id', $order->id)->where('status', 'reserved')->update(['status' => 'released']);
                    $count++;
                });
            }
        });
        $this->info('Expired unpaid orders: '.$count.'. Late provider evidence requires operator reconciliation.');

        return self::SUCCESS;
    }
}
