<x-filament-panels::page>
<div class="cla-dash">
    <section class="cla-card cla-switch-card">
        <div>
            <h2><x-filament::icon icon="heroicon-o-academic-cap" />{{ __('Instructor workspace') }}</h2>
            <p class="cla-muted">{{ $portalOpen
                ? __('Open: instructor accounts can sign in to their workspace and edit their own courses (changes wait for an admin to publish). Learners can message their course teacher, and the public Instructors page is visible.')
                : __('Closed: only admins create and edit courses. Instructors see a “not open yet” page, learners message the support team, and the public Instructors page is hidden.') }}</p>
        </div>
        <label class="cla-toggle">
            <input type="checkbox" wire:model.live="portalOpen" role="switch" aria-checked="{{ $portalOpen ? 'true' : 'false' }}">
            <span class="cla-toggle-track" aria-hidden="true"><span></span></span>
            <span class="cla-toggle-label">{{ $portalOpen ? __('On') : __('Off') }}</span>
        </label>
    </section>
    {{ $this->table }}
</div>
</x-filament-panels::page>
