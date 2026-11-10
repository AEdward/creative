<x-filament-panels::page>
<div class="cla-dash">
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-shield-check" />{{ __('What each role can open') }}</h2></header>
        <ul class="cla-role-list">
            @foreach(\App\Models\User::STAFF_ROLES as $key => $label)
                <li><b>{{ __($label) }}</b><span>{{ __(\App\Filament\Pages\Staff::ROLE_HELP[$key]) }}</span></li>
            @endforeach
        </ul>
        <p class="cla-muted">{{ __('Everyone on staff can see the dashboard and analytics. There is always at least one super admin.') }}</p>
    </section>
    {{ $this->table }}
</div>
</x-filament-panels::page>
