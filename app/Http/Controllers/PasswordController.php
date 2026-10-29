<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function email(Request $request): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower($request->input('email')) : $request->input('email')]);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        Password::sendResetLink($data);

        return back()->with('status', 'If an account matches this address, a password reset link has been sent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(12), 'max:128']]);
        $status = Password::reset($data, function ($user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
