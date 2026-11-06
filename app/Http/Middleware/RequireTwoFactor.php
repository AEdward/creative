<?php

namespace App\Http\Middleware;

use App\Services\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must pass two-step sign-in before anything else, however they signed in (site form or panel).
 * Runs on the website and on the admin panel.
 */
class RequireTwoFactor
{
    private const OPEN_ROUTES = ['two-factor.*', 'logout', 'filament.admin.auth.logout', 'verification.*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('academy.require_two_factor') || ! $user || $user->role !== 'admin' || $request->routeIs(self::OPEN_ROUTES)) {
            return $next($request);
        }
        if ($request->session()->get(TwoFactor::SESSION_KEY) === $user->id) {
            return $next($request);
        }
        $target = $user->two_factor_confirmed_at ? route('two-factor.challenge') : route('two-factor.setup');
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => 'Two-step sign-in required.', 'redirect' => $target], 403);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->to($target);
    }
}
