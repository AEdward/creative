<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['user_id', 'reference', 'checkout_key', 'currency', 'subtotal_minor', 'discount_minor', 'total_minor', 'vat_minor', 'vat_percent', 'status', 'refund_status', 'refund_reason', 'refund_note', 'refund_requested_at', 'refund_reviewed_at', 'refund_reviewer_id'];

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'vat_minor' => 'integer', 'vat_percent' => 'float', 'refund_requested_at' => 'datetime', 'refund_reviewed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function totalLabel(): string
    {
        return $this->money($this->total_minor);
    }

    public function money(int $minor): string
    {
        return $this->currency.' '.number_format($minor / 100, 2);
    }

    /** A paid order can have one refund request open at a time; the admin decides each case. */
    public function canRequestRefund(): bool
    {
        return $this->status === 'paid' && $this->refund_status === null;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
