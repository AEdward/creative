<?php

namespace App\Http\Middleware;

use App\Support\AcademySettings;
use App\Support\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site-settings maintenance switch. Visitors get a 503 "back soon" page; staff keep full access, and
 * the admin panel, sign-in, password reset, two-step, health check and payment webhooks stay reachable.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AcademySettings::get('maintenance_enabled') || $this->alwaysOpen($request) || $request->user()?->role === 'admin') {
            return $next($request);
        }

        return response()->view('errors.maintenance', ['message' => (string) AcademySettings::get('maintenance_message')], 503)->header('Retry-After', '3600');
    }

    private function alwaysOpen(Request $request): bool
    {
        return Admin::isAdminHost($request->getHost())
            || $request->routeIs('login', 'login.store', 'logout', 'password.*', 'two-factor.*', 'verification.*', 'filament.*', 'livewire.*', '*-livewire.*', 'payments.webhook')
            || $request->is('up', 'robots.txt', 'favicon.ico', Admin::path() === '' ? '__none__' : Admin::path().'*', 'livewire*', 'build/*', 'images/*', 'css/*');
    }
}
