<x-filament-panels::page>
@php
    $icons = ['ok' => 'heroicon-o-check-circle', 'warn' => 'heroicon-o-exclamation-triangle', 'fail' => 'heroicon-o-x-circle'];
    $words = ['ok' => __('OK'), 'warn' => __('Check'), 'fail' => __('Problem')];
    $worst = collect($checks)->contains('status', 'fail') ? 'fail' : (collect($checks)->contains('status', 'warn') ? 'warn' : 'ok');
@endphp
<div class="cla-dash">
    <section class="cla-card cla-health-summary cla-health-{{ $worst }}">
        <x-filament::icon :icon="$icons[$worst]" class="cla-health-big" />
        <div>
            <h2>{{ ['ok' => __('Everything is running normally'), 'warn' => __('Running, with things to check'), 'fail' => __('Something needs attention')][$worst] }}</h2>
            <p class="cla-muted">{{ __('Release :release · PHP :php · Laravel :laravel', ['release' => $release, 'php' => $php, 'laravel' => $laravel]) }}</p>
        </div>
    </section>

    <section class="cla-card">
        <ul class="cla-health-list">
            @foreach($checks as $check)
                <li class="cla-health-{{ $check['status'] }}">
                    <x-filament::icon :icon="$icons[$check['status']]" class="cla-health-icon" />
                    <div><b>{{ __($check['label']) }}</b><span>{{ $check['detail'] }}</span></div>
                    <span class="cla-badge {{ ['ok' => 'ok', 'warn' => 'muted', 'fail' => 'bad'][$check['status']] }}">{{ $words[$check['status']] }}</span>
                    @if($check['key'] === 'media')<button type="button" class="cla-outline cla-health-btn" wire:click="checkMedia">{{ __('Check now') }}</button>@endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-bell-alert" />{{ __('Alerts') }}</h2></header>
        <p class="cla-muted">{{ __('Every 15 minutes the site runs these checks and emails the super admins when something newly goes wrong (and again when it is fixed).') }}</p>
        <p class="cla-muted">{{ __('If the whole server is down it cannot email anyone, so also add a free outside monitor:') }}</p>
        <ol class="cla-steps-list">
            <li>{{ __('Create a free account at uptimerobot.com.') }}</li>
            <li>{{ __('Add a new HTTP(s) monitor for this address, checked every 5 minutes:') }} <code>{{ $upUrl }}</code></li>
            <li>{{ __('Add your email (and phone, for SMS) as an alert contact.') }}</li>
        </ol>
    </section>
</div>
</x-filament-panels::page>
