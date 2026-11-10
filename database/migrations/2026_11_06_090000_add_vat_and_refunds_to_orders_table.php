<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // VAT included in total_minor at the rate in force when the order was placed (0 when VAT is off).
            $table->unsignedBigInteger('vat_minor')->default(0)->after('total_minor');
            $table->decimal('vat_percent', 5, 2)->default(0)->after('vat_minor');
            $table->string('refund_status')->nullable()->index()->after('status'); // requested | refunded | declined
            $table->string('refund_reason', 1000)->nullable()->after('refund_status');
            $table->string('refund_note', 1000)->nullable()->after('refund_reason');
            $table->timestamp('refund_requested_at')->nullable()->after('refund_note');
            $table->timestamp('refund_reviewed_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refund_reviewer_id')->nullable()->after('refund_reviewed_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'awaiting_payment', 'paid', 'failed', 'cancelled', 'expired', 'refunded'])->default('pending')->change();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->enum('status', ['initiated', 'pending', 'verified_success', 'verified_failure', 'refunded'])->default('initiated')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->enum('status', ['initiated', 'pending', 'verified_success', 'verified_failure'])->default('initiated')->change();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'awaiting_payment', 'paid', 'failed', 'cancelled', 'expired'])->default('pending')->change();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refund_reviewer_id');
            $table->dropColumn(['vat_minor', 'vat_percent', 'refund_status', 'refund_reason', 'refund_note', 'refund_requested_at', 'refund_reviewed_at']);
        });
    }
};
