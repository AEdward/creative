<?php

namespace App\Models;

use App\Support\AcademySettings;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'bio', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'student', 'status' => 'active'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** Each role has its own workspace; this is where it starts (and where crossing attempts are sent back). */
    public function homeUrl(): string
    {
        return match (true) {
            $this->role === 'admin' => url('/admin'),
            $this->role === 'instructor' && (bool) AcademySettings::get('instructor_portal_enabled') => route('instructor.dashboard'),
            $this->role === 'instructor' => route('instructor.closed'),
            default => route('dashboard'),
        };
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->role === 'admin' && $this->status === 'active' && $this->hasVerifiedEmail();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
