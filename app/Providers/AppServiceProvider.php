<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\R2Storage;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\UnavailableGateway;
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
        Gate::define('instructor', fn (User $user) => $user->status === 'active' && in_array($user->role, ['instructor', 'admin']));
    }
}
