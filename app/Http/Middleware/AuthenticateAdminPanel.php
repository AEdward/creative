<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's panel authentication, except that signed-in learners and instructors who open /admin
 * go back to their own dashboard instead of seeing a bare 403.
 */
class AuthenticateAdminPanel extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();
        if ($user && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            throw new HttpResponseException(redirect()->route('dashboard')->with('status', 'The admin panel is only available to administrator accounts.'));
        }

        parent::authenticate($request, $guards);
    }
}
