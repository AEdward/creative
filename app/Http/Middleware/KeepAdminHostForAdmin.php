<?php

namespace App\Http\Middleware;

use App\Support\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin subdomain only serves the admin panel. Website pages opened on it (they share the same
 * server folder) are sent to the main site; the panel's own requests never pass through here.
 */
class KeepAdminHostForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Admin::isAdminHost($request->getHost()) || $request->routeIs('livewire.*', 'filament.*') || $request->is('up', 'livewire/*')) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri());
    }
}
