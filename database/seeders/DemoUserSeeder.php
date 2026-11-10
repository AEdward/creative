<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo accounts, one per role. Run with: php artisan db:seed --class=DemoUserSeeder
 * Password comes from DEMO_PASSWORD (default "Demo-password123!"). Refuses to run in production.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo users must not be created in production.');
        }
        $password = env('DEMO_PASSWORD', 'Demo-password123!');
        foreach ([
            ['admin', 'Demo Admin', 'admin@demo.test'],
            ['instructor', 'Demo Instructor', 'instructor@demo.test'],
            ['student', 'Demo Student', 'student@demo.test'],
        ] as [$role, $name, $email]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill(['name' => $name, 'password' => $password, 'role' => $role, 'status' => 'active', 'email_verified_at' => now()])->save();
            $this->command?->info("{$role}: {$email}");
        }
    }
}
