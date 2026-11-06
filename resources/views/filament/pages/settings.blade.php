<x-filament-panels::page>
<form wire:submit="save" class="cla-dash">
    <div class="cla-row cla-row-cert">
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-phone" />{{ __('Contact details') }}</h2></header>
            <div class="cla-form">
                <p class="cla-muted">{{ __('Shown on the Contact page and in the footer. Leave empty to hide.') }}</p>
                @foreach(['contact_email' => 'Email', 'contact_phone' => 'Phone', 'contact_address' => 'Address'] as $field => $label)
                    <label for="st-{{ $field }}">{{ __($label) }}</label>
                    <input id="st-{{ $field }}" type="text" wire:model="settings.{{ $field }}" maxlength="255">
                    @error('settings.'.$field)<p class="cla-error">{{ $message }}</p>@enderror
                @endforeach
            </div>
        </section>
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-banknotes" />{{ __('Payments & VAT') }}</h2></header>
            <div class="cla-form">
                <label class="cla-check"><input type="checkbox" wire:model="settings.bank_checkout_enabled"> {{ __('Accept bank-transfer payments') }}</label>
                <p class="cla-muted">{{ __('When off, paid courses show “Payments are paused” instead of a Buy button.') }}</p>
                <label class="cla-check"><input type="checkbox" wire:model="settings.vat_enabled"> {{ __('Show VAT on checkout and receipts') }}</label>
                <label for="st-vat">{{ __('VAT rate (%)') }}</label>
                <input id="st-vat" type="text" inputmode="decimal" wire:model="settings.vat_percent">
                @error('settings.vat_percent')<p class="cla-error">{{ $message }}</p>@enderror
                <p class="cla-muted">{{ __('Course prices are treated as VAT-inclusive: the price learners pay does not change; checkout shows how much of it is VAT. Confirm the rate with your accountant before turning this on.') }}</p>
            </div>
        </section>
    </div>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-academic-cap" />{{ __('Instructors') }}</h2></header>
        <div class="cla-form">
            <label class="cla-check"><input type="checkbox" wire:model="settings.instructor_portal_enabled"> {{ __('Open the instructor workspace') }}</label>
            <p class="cla-muted">{{ __('Off: only admins create and edit courses (you still pick the instructor shown on each course). On: instructor accounts can also edit their own courses; their changes wait for an admin to publish.') }}</p>
        </div>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-circle-stack" />{{ __('Video storage') }}</h2></header>
        @php $percent = min(100, (int) round($usedBytes / $limitBytes * 100)); @endphp
        <p class="cla-muted">{{ __(':used of :limit used (:percent%)', ['used' => \Illuminate\Support\Number::fileSize($usedBytes, 1), 'limit' => \Illuminate\Support\Number::fileSize($limitBytes), 'percent' => $percent]) }}</p>
        <div class="cla-meter" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $percent }}%" class="{{ $percent >= (int) $settings['storage_alert_percent'] ? 'bad' : '' }}"></span></div>
        <div class="cla-form cla-two">
            <div><label for="st-limit">{{ __('Storage budget (GB)') }}</label><input id="st-limit" type="text" inputmode="numeric" wire:model="settings.storage_limit_gb">@error('settings.storage_limit_gb')<p class="cla-error">{{ $message }}</p>@enderror</div>
            <div><label for="st-alert">{{ __('Alert admins at (% of budget)') }}</label><input id="st-alert" type="text" inputmode="numeric" wire:model="settings.storage_alert_percent">@error('settings.storage_alert_percent')<p class="cla-error">{{ $message }}</p>@enderror</div>
        </div>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-scale" />{{ __('Legal pages') }}</h2></header>
        <div class="cla-form">
            <p class="cla-muted">{{ __('Paste the approved text. Blank lines start new paragraphs. While a page is empty it shows a “Pending legal review” notice. Have these reviewed by a lawyer before launch.') }}</p>
            @foreach(['privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'refund-policy' => 'Refund policy'] as $field => $label)
                <label for="st-{{ $field }}">{{ __($label) }}</label>
                <textarea id="st-{{ $field }}" wire:model="settings.{{ $field }}" rows="8" maxlength="30000"></textarea>
                @error('settings.'.$field)<p class="cla-error">{{ $message }}</p>@enderror
            @endforeach
        </div>
    </section>
    <div class="cla-actions"><button type="submit" class="cla-primary">{{ __('Save settings') }}</button></div>
</form>
</x-filament-panels::page>
