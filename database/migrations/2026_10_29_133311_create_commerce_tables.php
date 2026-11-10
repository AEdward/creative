<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->uuid('reference')->unique();
            $t->char('checkout_key', 64)->unique();
            $t->char('currency', 3);
            $t->unsignedBigInteger('subtotal_minor');
            $t->unsignedBigInteger('discount_minor')->default(0);
            $t->unsignedBigInteger('total_minor');
            $t->enum('status', ['pending', 'awaiting_payment', 'paid', 'failed', 'cancelled', 'expired'])->default('pending');
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->string('title_snapshot');
            $t->unsignedBigInteger('unit_price_minor');
            $t->timestamps();
            $t->unique(['order_id', 'course_id']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('provider', 50);
            $referenceColumn = $t->string('provider_reference')->nullable();
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $referenceColumn->collation('utf8mb4_bin');
            }$t->uuid('idempotency_key')->unique();
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency', 3);
            $t->enum('status', ['initiated', 'pending', 'verified_success', 'verified_failure'])->default('initiated');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'provider_reference']);
        });
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 50);
            $eventColumn = $t->string('external_event_id', 128);
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $eventColumn->collation('utf8mb4_bin');
            }$t->foreignId('payment_id')->constrained()->restrictOnDelete();
            $t->string('event_type');
            $t->char('fingerprint', 64);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'external_event_id']);
        });
        Schema::table('enrollments', fn (Blueprint $t) => $t->foreignId('source_order_item_id')->nullable()->constrained('order_items')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $t) => $t->dropConstrainedForeignId('source_order_item_id'));
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
