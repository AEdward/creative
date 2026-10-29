<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower($request->input('email')) : $request->input('email')]);
        $user = $request->user();
        $changedEmail = $request->input('email') !== $user->email;
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)], 'current_password' => $changedEmail ? ['bail', 'required', 'string', 'current_password'] : ['nullable']]);
        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($changedEmail) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($changedEmail) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route('profile')->with('status', 'Your profile has been updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['bail', 'required', 'string', 'current_password'], 'password' => ['required', 'string', 'confirmed', Password::min(12), 'max:128']]);
        DB::transaction(function () use ($request, $data) {
            $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        });
        $request->session()->regenerate(true);

        return redirect()->route('profile')->with('status', 'Your password has been updated. Other devices have been signed out.');
    }
}
