<x-layout :title="__('Enter your sign-in code')">
<section class="section"><div class="container two-factor">
    <div class="two-factor-card">
        <p class="eyebrow">{{ __('Staff security') }}</p>
        <h1>{{ __('Enter your sign-in code') }}</h1>
        <p>{{ __('Open your authenticator app and type the 6-digit code for Creative Lab Academy.') }}</p>
        <form method="POST" action="{{ route('two-factor.verify') }}" class="two-factor-form">@csrf
            <label class="sr-only" for="tf-code">{{ __('Code') }}</label>
            <input id="tf-code" name="code" autocomplete="one-time-code" maxlength="20" required autofocus placeholder="123 456">
            @error('code')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror
            <button class="button">{{ __('Continue') }}</button>
        </form>
        <p class="small">{{ __('Lost your phone? Type one of your recovery codes instead, or ask a super admin to reset your two-step sign-in.') }}</p>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-link">{{ __('Sign out') }}</button></form>
    </div>
</div></section>
</x-layout>
