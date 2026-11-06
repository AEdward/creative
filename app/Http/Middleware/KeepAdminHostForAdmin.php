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
 * The Media Library's background upload calls (media.store/confirm/attach) are also allowed here.
 */
class KeepAdminHostForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Admin::isAdminHost($request->getHost()) || $request->routeIs('livewire.*', '*-livewire.*', 'filament.*', 'media.store', 'media.confirm', 'media.attach') || $request->is('up', 'livewire/*', 'livewire-*/*')) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri());
    }
}
