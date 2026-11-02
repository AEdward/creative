<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A learner's proof of a manual bank transfer, waiting for admin approval. */
class PaymentSubmission extends Model
{
    protected $fillable = ['order_id', 'user_id', 'payment_method_id', 'transaction_reference', 'proof_path', 'status', 'reviewer_id', 'reviewed_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
