<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\R2Storage;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\UnavailableGateway;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaStorage::class, R2Storage::class);
        $this->app->bind(PaymentGateway::class, UnavailableGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('administer-academy', fn (User $user) => $user->role === 'admin' && $user->status === 'active' && $user->hasVerifiedEmail());
        // /up fails (HTTP 500) when the database or writable storage is unavailable, so uptime monitors catch it.
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');
            if (! is_writable(storage_path('framework')) || ! is_writable(storage_path('logs'))) {
                throw new \RuntimeException('Storage is not writable.');
            }
        });
        Gate::define('instructor', fn (User $user) => $user->status === 'active' && in_array($user->role, ['instructor', 'admin']));
    }
}
