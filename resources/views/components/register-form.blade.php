@props(['modal' => false])
@php $id = $modal ? 'modal-register-' : ''; $bag = $modal ? 'register' : 'default'; @endphp
<div class="auth-form-area">
    <h1 @if($modal) id="register-dialog-title" @endif>{{ __('Create Your Account') }}</h1>
    <p>{{ __('Join Creative Lab Academy and start building real creative skills.') }}</p>
    <form method="POST" action="{{ route('register.store') }}">@csrf
        @if($modal)<input type="hidden" name="auth_modal" value="1">@endif
        <div class="form-field"><label for="{{ $id }}name">{{ __('Full name') }}</label>
            <div class="input-icon"><x-heroicon-o-user aria-hidden="true" /><input id="{{ $id }}name" name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" autocomplete="name" placeholder="{{ __('Your name') }}" required maxlength="100" @error('name', $bag) aria-invalid="true" aria-describedby="{{ $id }}name-error" @enderror></div>
            @error('name', $bag)<p id="{{ $id }}name-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
        <div class="form-field"><label for="{{ $id }}email">{{ __('Email address') }}</label>
            <div class="input-icon"><x-heroicon-o-envelope aria-hidden="true" /><input type="email" id="{{ $id }}email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="email" placeholder="you@example.com" required maxlength="255" @error('email', $bag) aria-invalid="true" aria-describedby="{{ $id }}email-error" @enderror></div>
            @error('email', $bag)<p id="{{ $id }}email-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
        <div class="form-field"><label for="{{ $id }}password">{{ __('Password') }}</label>
            <div class="input-icon"><x-heroicon-o-lock-closed aria-hidden="true" /><input type="password" id="{{ $id }}password" name="password" autocomplete="new-password" placeholder="{{ __('At least 12 characters') }}" required minlength="12" maxlength="128" @error('password', $bag) aria-invalid="true" aria-describedby="{{ $id }}password-error" @enderror>
                <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id }}password" aria-pressed="false" aria-label="{{ __('Show password') }}" data-label-show="{{ __('Show password') }}" data-label-hide="{{ __('Hide password') }}"><x-heroicon-o-eye aria-hidden="true" /></button></div>
            @error('password', $bag)<p id="{{ $id }}password-error" class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
        <div class="form-field"><label for="{{ $id }}password_confirmation">{{ __('Confirm password') }}</label>
            <div class="input-icon"><x-heroicon-o-lock-closed aria-hidden="true" /><input type="password" id="{{ $id }}password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="{{ __('Repeat your password') }}" required minlength="12" maxlength="128">
                <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id }}password_confirmation" aria-pressed="false" aria-label="{{ __('Show password') }}" data-label-show="{{ __('Show password') }}" data-label-hide="{{ __('Hide password') }}"><x-heroicon-o-eye aria-hidden="true" /></button></div></div>
        <p class="auth-legal">{{ __('By creating an account you agree to the') }} <a href="{{ route('terms') }}">{{ __('terms') }}</a> {{ __('and') }} <a href="{{ route('privacy') }}">{{ __('privacy notice') }}</a>.</p>
        <button type="submit" class="button form-wide-button">{{ __('Create Account') }} <x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></button>
    </form>
    <div class="auth-switch">{{ __('Already have an account?') }} <a href="{{ route('login') }}" data-auth-switch="login">{{ __('Sign in') }}</a></div>
</div>
