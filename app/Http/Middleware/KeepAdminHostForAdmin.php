<?php

namespace App\Http\Middleware;

use App\Support\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin subdomain only serves the admin panel. Website pages opened on it (they share the same
 * server folder) are sent to the main site; the panel's own requests (including Livewire's
 * 'livewire-<hash>/update' calls) are let through — redirecting those broke admin sign-in.
 * Only page visits (GET/HEAD) are redirected: form posts from admin screens (course studio thumbnail,
 * files, chapters, uploads) must reach their handler, not be turned into a GET on the main site (404).
 */
class KeepAdminHostForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Admin::isAdminHost($request->getHost()) || ! $request->isMethodSafe() || $request->routeIs('livewire.*', '*-livewire.*', 'filament.*', 'media.store', 'media.confirm', 'media.attach') || $request->is('up', 'livewire/*', 'livewire-*/*')) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri());
    }
}
