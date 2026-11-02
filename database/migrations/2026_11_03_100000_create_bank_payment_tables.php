<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('logo_path')->nullable();
            $table->string('account_name', 150);
            $table->string('account_number', 80);
            $table->text('instructions')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('transaction_reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            // The same bank transaction can't be claimed twice.
            $table->unique(['payment_method_id', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('payment_methods');
    }
};
