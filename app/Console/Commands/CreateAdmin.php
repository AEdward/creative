<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('academy:admin {email} {--name=}')]
#[Description('Create a verified administrator from a trusted console without exposing the password in arguments')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $name = $this->option('name') ?: 'Academy administrator';
        if (User::where('email', $email)->exists()) {
            $this->error('An account already exists. This command never promotes existing accounts.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive trusted console so the password can be entered securely.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password], ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:128']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();
        $this->info('Administrator created. Keep console access restricted.');

        return self::SUCCESS;
    }
}
