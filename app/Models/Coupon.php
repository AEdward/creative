<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'percent', 'course_id', 'active', 'max_uses', 'per_user_limit', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'percent' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
