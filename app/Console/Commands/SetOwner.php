<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Marks the business owner's account: always a super admin, hidden from other staff, and protected
 * from role changes, suspension and two-step resets by anyone else. Only runnable from the server.
 */
class SetOwner extends Command
{
    protected $signature = 'academy:owner {email} {--remove : Turn owner protection off for this account}';

    protected $description = 'Protect (or unprotect) the owner account';

    public function handle(): int
    {
        $user = User::where('email', Str::lower((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }
        if ($this->option('remove')) {
            $user->forceFill(['is_owner' => false])->save();
            AuditLog::create(['actor_id' => null, 'action' => 'owner.removed', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => []]);
            $this->info("Owner protection removed from {$user->email}.");

            return self::SUCCESS;
        }
        if ($user->role !== 'admin' || ! $user->hasVerifiedEmail()) {
            $this->error('The owner must be a verified admin account. Create it first with: php artisan academy:admin EMAIL');

            return self::FAILURE;
        }
        $user->forceFill(['is_owner' => true, 'staff_role' => 'super', 'status' => 'active'])->save();
        AuditLog::create(['actor_id' => null, 'action' => 'owner.set', 'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => []]);
        $this->info("{$user->email} is now the protected owner account.");

        return self::SUCCESS;
    }
}
