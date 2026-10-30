@php
$titles = ['login'=>'Welcome back.', 'register'=>'Your next chapter starts here.', 'forgot'=>'Let’s get you back in.', 'reset'=>'A fresh start.'];
$intros = ['login'=>'Sign in to continue your creative journey.', 'register'=>'Create an account and make space for your creativity.', 'forgot'=>'Enter your email and we’ll send you a reset link if an account exists.', 'reset'=>'Choose a new password for your account.'];
$actions = ['login'=>'login.store','register'=>'register.store','forgot'=>'password.email','reset'=>'password.update'];
@endphp
<x-layout :title="__($titles[$mode])">
<section class="auth-section" @if($mode === 'login') data-login-page @endif><div class="container"><div class="auth-shell"><x-auth-aside />@if($mode === 'login')<x-login-form />@else<div class="auth-form-area"><h1>{{ __($titles[$mode]) }}</h1><p>{{ __($intros[$mode]) }}</p>
@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
<form method="POST" action="{{ route($actions[$mode]) }}">@csrf
@if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
@if($mode === 'register')<div class="form-field"><label for="name">{{ __('Your name') }}</label><input id="name" name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" autocomplete="name" required maxlength="100" autofocus>@error('name')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>@endif
<div class="form-field"><label for="email">{{ __('Email address') }}</label><input type="email" id="email" name="email" value="{{ is_string(old('email', $email ?? '')) ? old('email', $email ?? '') : '' }}" autocomplete="email" placeholder="you@example.com" required maxlength="255" @if($mode !== 'register') autofocus @endif @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<p id="email-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
@if(in_array($mode,['login','register','reset']))<div class="form-field"><label for="password">{{ __('Password') }}</label><input type="password" id="password" name="password" autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" required maxlength="128" @if($mode !== 'login') minlength="12" placeholder="{{ __('At least 12 characters') }}" @endif @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<p id="password-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>@endif
@if(in_array($mode,['register','reset']))<div class="form-field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required></div>@endif
@if($mode === 'login')<div class="form-extra"><label class="checkbox-label"><input type="checkbox" name="remember" value="1">{{ __('Remember me') }}</label><a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a></div>@endif
<button type="submit" class="button form-wide-button">{{ __(['login'=>'Sign in','register'=>'Create account','forgot'=>'Send reset link','reset'=>'Reset password'][$mode]) }} <span aria-hidden="true"><x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></span></button></form>
<div class="auth-switch">@if($mode === 'login'){{ __('New to Creative Lab?') }} <a href="{{ route('register') }}">{{ __('Create account') }}</a>@else {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>@endif</div>
@if($mode === 'register')<p class="small" style="margin-top:20px">{{ __('This academy is in development. Please review the') }} <a class="inline-link" href="{{ route('terms') }}">{{ __('terms') }}</a> {{ __('and') }} <a class="inline-link" href="{{ route('privacy') }}">{{ __('privacy notice') }}</a> {{ __('before continuing.') }}</p>@endif
</div>@endif</div></div></section>
</x-layout>
