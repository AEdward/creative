<?php

namespace App\Models;

use App\Support\AcademySettings;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'bio', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
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
    /** Admin staff roles. An admin without one (older accounts) is a super admin. */
    public const STAFF_ROLES = ['super' => 'Super admin', 'content' => 'Content manager', 'support' => 'Support', 'finance' => 'Finance'];

    public function staffRole(): ?string
    {
        if ($this->role !== 'admin') {
            return null;
        }

        return $this->is_owner ? 'super' : ($this->staff_role ?: 'super');
    }

    /** Owner accounts are invisible to other staff (lists, pickers, activity log names). */
    public function scopeVisibleToStaff(Builder $query, ?User $viewer): Builder
    {
        return $viewer?->is_owner ? $query : $query->where('is_owner', false);
    }

    /** Only the owner may change the owner account. */
    public function protectedFrom(?User $actor): bool
    {
        return $this->is_owner && $actor?->id !== $this->id;
    }

    /**
     * Can this staff member use an area of the admin panel? Areas: content, support, finance, super
     * (staff, settings, logs, site health) and enrollments (shared by support and finance).
     */
    public function staffCan(string $area): bool
    {
        $role = $this->staffRole();
        if (! $role || $this->status !== 'active' || ! $this->hasVerifiedEmail()) {
            return false;
        }

        return $role === 'super' || $role === $area || ($area === 'enrollments' && in_array($role, ['support', 'finance'], true));
    }

    /** Active staff who handle an area (super admins included), e.g. who to notify about a payment. */
    public static function staffFor(string $area): Collection
    {
        return static::where('role', 'admin')->where('status', 'active')->whereNotNull('email_verified_at')->get()->filter(fn (User $u) => $u->staffCan($area))->values();
    }

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

    /** Courses this account is the assigned instructor of. */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
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
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_owner' => 'boolean',
        ];
    }
}
