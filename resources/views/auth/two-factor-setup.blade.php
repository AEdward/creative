<x-layout :title="__('Set up two-step sign-in')">
<section class="section"><div class="container two-factor">
    <div class="two-factor-card">
        <p class="eyebrow">{{ __('Staff security') }}</p>
        <h1>{{ __('Set up two-step sign-in') }}</h1>
        <p>{{ __('Staff accounts need a code from your phone as well as your password. This takes about a minute.') }}</p>
        <ol class="two-factor-steps">
            <li>{{ __('Install Google Authenticator or Microsoft Authenticator on your phone.') }}</li>
            <li>{{ __('In the app, add an account and scan this code:') }}
                <img class="two-factor-qr" src="{{ $qr }}" alt="{{ __('QR code for your authenticator app') }}" width="220" height="220">
                <details><summary>{{ __('Can’t scan? Type this key instead') }}</summary><code class="two-factor-key">{{ $secret }}</code></details>
            </li>
            <li>{{ __('Type the 6-digit code the app shows:') }}
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="two-factor-form">@csrf
                    <label class="sr-only" for="tf-code">{{ __('6-digit code') }}</label>
                    <input id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus placeholder="123 456">
                    @error('code')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror
                    <button class="button">{{ __('Turn on two-step sign-in') }}</button>
                </form>
            </li>
        </ol>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-link">{{ __('Sign out') }}</button></form>
    </div>
</div></section>
</x-layout>
