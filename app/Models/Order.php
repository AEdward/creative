<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['user_id', 'reference', 'checkout_key', 'currency', 'subtotal_minor', 'discount_minor', 'total_minor', 'status'];

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer'];
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
        return $this->currency.' '.intdiv($this->total_minor, 100).'.'.str_pad((string) ($this->total_minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
