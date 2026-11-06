<?php

namespace App\Http\Controllers;

use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    private function staff(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 404);
    }

    /** First sign-in: scan the QR code, then confirm one code from the app. */
    public function setup(Request $request, TwoFactor $twoFactor): View|RedirectResponse
    {
        $this->staff($request);
        $user = $request->user();
        if ($user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.challenge');
        }
        $secret = $request->session()->get('two_factor_setup_secret') ?: $twoFactor->newSecret();
        $request->session()->put('two_factor_setup_secret', $secret);

        return view('auth.two-factor-setup', ['qr' => $twoFactor->qrCode($user, $secret), 'secret' => trim(chunk_split($secret, 4, ' '))]);
    }

    public function confirm(Request $request, TwoFactor $twoFactor): View|RedirectResponse
    {
        $this->staff($request);
        $user = $request->user();
        abort_if((bool) $user->two_factor_confirmed_at, 409);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $secret = (string) $request->session()->get('two_factor_setup_secret');
        if ($secret === '' || ! $twoFactor->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code didn’t match. Check the time on your phone and try the newest code.']);
        }
        $codes = $twoFactor->enable($user, $secret);
        $request->session()->forget('two_factor_setup_secret');
        $request->session()->put(TwoFactor::SESSION_KEY, $user->id);

        return view('auth.two-factor-recovery', ['codes' => $codes, 'next' => $request->session()->pull('url.intended', url('/admin'))]);
    }

    /** Every later sign-in: enter the current code (or a recovery code). */
    public function challenge(Request $request): View|RedirectResponse
    {
        $this->staff($request);
        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $this->staff($request);
        $user = $request->user();
        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many tries. Wait a minute and try again.']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        if (! $twoFactor->check($user, $data['code'])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'That code didn’t work. Use the newest code from your app, or a recovery code.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(TwoFactor::SESSION_KEY, $user->id);

        return redirect()->intended(url('/admin'));
    }
}
