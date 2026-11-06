<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "refunded" (access removed by an approved refund; the learner may buy again) is kept apart from
    // "revoked" (access removed by an admin; buying again stays blocked until an admin reviews it).
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->enum('status', ['active', 'revoked', 'refunded'])->default('active')->change();
        });
        DB::table('enrollments')->where('status', 'revoked')
            ->whereIn('source_order_item_id', DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', 'refunded')->select('order_items.id'))
            ->update(['status' => 'refunded']);
    }

    public function down(): void
    {
        DB::table('enrollments')->where('status', 'refunded')->update(['status' => 'revoked']);
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->enum('status', ['active', 'revoked'])->default('active')->change();
        });
    }
};
