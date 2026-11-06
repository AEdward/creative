<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['student_id', 'type', 'course_id', 'instructor_id', 'subject', 'status', 'closed_at', 'closed_by', 'last_message_at', 'student_read_at', 'staff_read_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'student_read_at' => 'datetime', 'staff_read_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /** Conversations this user may read: their own, ones about their courses, and support threads for admins. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('student_id', $user->id)->orWhere(fn ($i) => $i->where('type', 'instructor')->where('instructor_id', $user->id));
            if ($user->can('staff', 'support')) {
                $q->orWhere('type', 'support');
            }
        });
    }

    public function isStudent(User $user): bool
    {
        return $this->student_id === $user->id;
    }

    public function canBeSeenBy(User $user): bool
    {
        return $this->isStudent($user)
            || ($this->type === 'instructor' && $this->instructor_id === $user->id)
            || ($this->type === 'support' && $user->can('staff', 'support'));
    }

    /** SQL version of isUnreadFor(), so the sidebar badge is one COUNT query. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->whereNotNull('last_message_at')->where(function (Builder $q) use ($user) {
            $q->where(fn ($s) => $s->where('student_id', $user->id)->where(fn ($r) => $r->whereNull('student_read_at')->orWhereColumn('student_read_at', '<', 'last_message_at')))
                ->orWhere(fn ($s) => $s->where('student_id', '!=', $user->id)->where(fn ($r) => $r->whereNull('staff_read_at')->orWhereColumn('staff_read_at', '<', 'last_message_at')));
        });
    }

    public function isUnreadFor(User $user): bool
    {
        $seen = $this->isStudent($user) ? $this->student_read_at : $this->staff_read_at;

        return $this->last_message_at && (! $seen || $seen->lt($this->last_message_at));
    }

    /** Name of the other side, as seen by $user. */
    public function counterpartFor(User $user): string
    {
        if (! $this->isStudent($user)) {
            return $this->student?->name ?? __('Learner');
        }

        return $this->type === 'support' ? __('Support team') : ($this->instructor?->name ?? __('Instructor'));
    }
}
