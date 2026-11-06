<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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

    /**
     * Admin-created account (student, instructor or admin). The admin never sets or sees the password:
     * the account gets an unusable random one and the person receives a "set your password" email.
     * The email is marked verified because an admin vouched for it; the reset link proves the inbox.
     */
    public function createAccount(User $actor, string $name, string $email, string $role, ?string $staffRole = null): User
    {
        $this->authorize($actor);
        abort_unless(in_array($role, ['student', 'instructor', 'admin'], true), 422);
        // Only super admins create admins and students; content managers may add instructors.
        abort_unless($actor->staffCan('super') || ($role === 'instructor' && $actor->staffCan('content')), 403);
        abort_if($role === 'admin' && ! array_key_exists($staffRole ?? 'super', User::STAFF_ROLES), 422);
        $email = Str::lower(trim($email));
        $user = DB::transaction(function () use ($actor, $name, $email, $role, $staffRole) {
            $user = new User(['name' => trim($name), 'email' => $email, 'password' => Str::password(64)]);
            $user->forceFill(['role' => $role, 'staff_role' => $role === 'admin' ? ($staffRole ?? 'super') : null, 'status' => 'active', 'email_verified_at' => now()])->save();
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'account.created_by_admin', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => ['email' => $email, 'role' => $role, 'staff_role' => $role === 'admin' ? ($staffRole ?? 'super') : null]]);

            return $user;
        });
        $user->notify(new AccountInvitation(Password::createToken($user), $role));

        return $user;
    }

    /** Change someone's role. Admins can't change their own role, so the panel always keeps an admin. */
    public function setRole(User $actor, User $subject, string $role, ?string $staffRole = null): void
    {
        $this->authorize($actor);
        abort_unless($actor->staffCan('super'), 403);
        abort_unless(in_array($role, ['student', 'instructor', 'admin'], true), 422);
        DB::transaction(function () use ($actor, $subject, $role, $staffRole) {
            $user = User::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            abort_if($user->protectedFrom($actor), 403, 'This account is protected.');
            abort_if($user->id === $actor->id, 409, 'You can’t change your own role.');
            $previous = [$user->role, $user->staff_role];
            $newStaff = $role === 'admin' ? ($staffRole ?? 'super') : null;
            abort_if($newStaff && ! array_key_exists($newStaff, User::STAFF_ROLES), 422);
            // Never leave the academy without a super admin.
            if ($user->staffRole() === 'super' && $newStaff !== 'super') {
                $supers = User::where('role', 'admin')->where('status', 'active')->get()->filter(fn (User $u) => $u->staffRole() === 'super')->count();
                abort_if($supers <= 1, 409, 'This is the only super admin. Make someone else a super admin first.');
            }
            $user->forceFill(['role' => $role, 'staff_role' => $newStaff])->save();
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'account.role_changed', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => ['role' => ['from' => $previous[0], 'to' => $role], 'staff_role' => ['from' => $previous[1], 'to' => $newStaff]]]);
        });
    }

    public function setStatus(User $actor, User $subject, string $status): void
    {
        $this->authorize($actor);
        abort_unless(in_array($status, ['active', 'suspended']), 422);
        DB::transaction(function () use ($actor, $subject, $status) {
            $user = User::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            abort_if($user->protectedFrom($actor), 403, 'This account is protected.');
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
