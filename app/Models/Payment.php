<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'provider', 'provider_reference', 'idempotency_key', 'amount_minor', 'currency', 'status', 'verified_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'verified_at' => 'datetime'];
    }
}
