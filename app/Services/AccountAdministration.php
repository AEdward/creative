<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountAdministration
{
    private function authorize(User $actor): void
    {
        $fresh = $actor->fresh();
        if (! $fresh || $fresh->role !== 'admin' || $fresh->status !== 'active' || ! $fresh->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }
    }

    public function approveInstructor(User $actor, User $subject): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor, $subject) {
            $user = User::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === 'student' && $user->status === 'active', 409, 'Only active students can be approved as instructors.');
            $user->forceFill(['role' => 'instructor'])->save();
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'instructor.approved', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => ['role' => ['from' => 'student', 'to' => 'instructor']]]);
        });
    }

    public function setStatus(User $actor, User $subject, string $status): void
    {
        $this->authorize($actor);
        abort_unless(in_array($status, ['active', 'suspended']), 422);
        DB::transaction(function () use ($actor, $subject, $status) {
            $user = User::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            abort_if($user->role === 'admin' || $user->id === $actor->id, 409, 'Administrator status must be managed through a trusted operational process.');
            $previous = $user->status;
            $changes = ['status' => $status];
            if ($status === 'suspended') {
                $changes['remember_token'] = Str::random(60);
            }
            $user->forceFill($changes)->save();
            if ($status === 'suspended') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'account.status_changed', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => ['status' => ['from' => $previous, 'to' => $status]]]);
        });
    }
}
