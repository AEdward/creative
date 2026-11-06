<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'professional_title', 'biography', 'specialties', 'publicly_listed', 'show_photo'];

    protected function casts(): array
    {
        return ['publicly_listed' => 'boolean', 'show_photo' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('publicly_listed', true)->whereHas('user', fn (Builder $users) => $users->where('role', 'instructor')->where('status', 'active')->whereNotNull('email_verified_at'));
    }
}
