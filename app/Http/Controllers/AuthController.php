<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower($request->input('email')) : $request->input('email')]);
        // Popup sign-ups use their own error bag so other forms on the returning page stay clean.
        $bag = $request->boolean('auth_modal') ? 'register' : 'default';
        $data = $request->validateWithBag($bag, ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'string', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(12), 'max:128'], 'auth_modal' => ['sometimes', 'boolean']]);
        unset($data['auth_modal']);
        $data['email'] = Str::lower($data['email']);
        $user = User::create($data);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function login(Request $request): RedirectResponse
    {
        // Popup sign-ins use their own error bag so other forms on the returning page stay clean.
        $bag = $request->boolean('auth_modal') ? 'login' : 'default';
        $data = $request->validateWithBag($bag, ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:128'], 'remember' => ['sometimes', 'boolean'], 'auth_modal' => ['sometimes', 'boolean']]);
        $email = Str::lower($data['email']);
        $key = 'login:'.hash('sha256', $email.'|'.$request->ip());
        abort_if(RateLimiter::tooManyAttempts($key, 6), 429, 'Too many sign-in attempts. Please try again later.');
        if (! Auth::attempt(['email' => $email, 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.'])->errorBag($bag);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
