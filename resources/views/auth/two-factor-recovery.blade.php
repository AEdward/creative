<x-layout :title="__('Save your recovery codes')">
<section class="section"><div class="container two-factor">
    <div class="two-factor-card">
        <p class="eyebrow">{{ __('Two-step sign-in is on') }}</p>
        <h1>{{ __('Save your recovery codes') }}</h1>
        <p>{{ __('If you lose your phone, each of these codes lets you sign in once. Store them somewhere safe (a password manager or printed). They are shown only now.') }}</p>
        <ul class="two-factor-codes">@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        <a class="button" href="{{ $next }}">{{ __('I’ve saved them — continue') }}</a>
    </div>
</div></section>
</x-layout>
