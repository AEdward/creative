@props(['modal' => false])
@php $id = $modal ? 'modal-' : ''; $bag = $modal ? 'login' : 'default'; @endphp
<div class="auth-form-area">
    <h1 @if($modal) id="login-dialog-title" @endif>{{ __('Welcome Back') }}</h1>
    <p>{{ __('Sign in to continue your learning journey at Creative Lab Academy.') }}</p>
    @if(! $modal && session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
    <form method="POST" action="{{ route('login.store') }}">@csrf
        @if($modal)<input type="hidden" name="auth_modal" value="1">@endif
        <div class="form-field"><label for="{{ $id }}email">{{ __('Email address') }}</label>
            <div class="input-icon"><x-heroicon-o-envelope aria-hidden="true" /><input type="email" id="{{ $id }}email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="email" placeholder="you@example.com" required maxlength="255" @error('email', $bag) aria-invalid="true" aria-describedby="{{ $id }}email-error" @enderror></div>
            @error('email', $bag)<p id="{{ $id }}email-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
        <div class="form-field"><label for="{{ $id }}password">{{ __('Password') }}</label>
            <div class="input-icon"><x-heroicon-o-lock-closed aria-hidden="true" /><input type="password" id="{{ $id }}password" name="password" autocomplete="current-password" placeholder="{{ __('Enter your password') }}" required maxlength="128" @error('password', $bag) aria-invalid="true" aria-describedby="{{ $id }}password-error" @enderror>
                <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id }}password" aria-pressed="false" aria-label="{{ __('Show password') }}" data-label-show="{{ __('Show password') }}" data-label-hide="{{ __('Hide password') }}"><x-heroicon-o-eye aria-hidden="true" /></button></div>
            @error('password', $bag)<p id="{{ $id }}password-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
        <div class="form-extra"><label class="checkbox-label"><input type="checkbox" name="remember" value="1">{{ __('Remember me') }}</label><a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a></div>
        <button type="submit" class="button form-wide-button">{{ __('Sign In') }} <x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></button>
    </form>
    <div class="auth-switch">{{ __('Don’t have an account?') }} <a href="{{ route('register') }}" data-auth-switch="register">{{ __('Create account') }}</a></div>
</div>
