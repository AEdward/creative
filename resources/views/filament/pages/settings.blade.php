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
        <header><h2><x-filament::icon icon="heroicon-o-magnifying-glass" />{{ __('Search engines') }}</h2></header>
        <div class="cla-form">
            <p class="cla-muted">{{ __('To prove you own the site in Google Search Console: add the property https://creativelabet.com, choose “HTML tag”, and paste only the code inside content="…" here. Then submit the sitemap: :url', ['url' => route('sitemap')]) }}</p>
            <label for="st-google">{{ __('Google verification code') }}</label>
            <input id="st-google" type="text" wire:model="settings.google_site_verification" maxlength="120" placeholder="e.g. AbC123xyz…">
            @error('settings.google_site_verification')<p class="cla-error">{{ $message }}</p>@enderror
            <label for="st-bing">{{ __('Bing verification code (optional)') }}</label>
            <input id="st-bing" type="text" wire:model="settings.bing_site_verification" maxlength="120">
            @error('settings.bing_site_verification')<p class="cla-error">{{ $message }}</p>@enderror
        </div>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-megaphone" />{{ __('Website content') }}</h2></header>
        <div class="cla-form">
            <label for="st-banner">{{ __('Announcement banner (shown at the top of every public page; leave empty to hide)') }}</label>
            <input id="st-banner" type="text" wire:model="content.banner" maxlength="300">
            @error('content.banner')<p class="cla-error">{{ $message }}</p>@enderror
            <label for="st-about">{{ __('Extra text on the About page') }}</label>
            <textarea id="st-about" wire:model="content.about" rows="4" maxlength="10000"></textarea>
            <label for="st-faq">{{ __('Extra text on the FAQ page') }}</label>
            <textarea id="st-faq" wire:model="content.faq" rows="4" maxlength="10000"></textarea>
        </div>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-wrench-screwdriver" />{{ __('Maintenance mode') }}</h2></header>
        <div class="cla-form">
            <label class="cla-check"><input type="checkbox" wire:model="settings.maintenance_enabled"> {{ __('Put the website in maintenance mode') }}</label>
            <p class="cla-muted">{{ __('Visitors and learners see a “We’ll be back soon” page. Staff can still sign in, use the admin panel and browse the site (with a reminder bar). Sign-in and password pages stay open so staff can get in.') }}</p>
            <label for="st-maint">{{ __('Message for visitors (optional)') }}</label>
            <textarea id="st-maint" wire:model="settings.maintenance_message" rows="3" maxlength="500" placeholder="{{ __('We’re making improvements and will be back shortly.') }}"></textarea>
            @error('settings.maintenance_message')<p class="cla-error">{{ $message }}</p>@enderror
        </div>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-scale" />{{ __('Legal pages') }}</h2></header>
        <div class="cla-form">
            <p class="cla-muted">{{ __('Paste the approved text. Blank lines start new paragraphs. While a page is empty, the built-in policy text is shown. Have these reviewed by a lawyer before launch.') }}</p>
            @foreach(['privacy' => 'Privacy policy', 'terms' => 'Terms of service', 'refund-policy' => 'Refund policy', 'cookies' => 'Cookie policy'] as $field => $label)
                <label for="st-{{ $field }}">{{ __($label) }}</label>
                <textarea id="st-{{ $field }}" wire:model="settings.{{ $field }}" rows="8" maxlength="30000"></textarea>
                @error('settings.'.$field)<p class="cla-error">{{ $message }}</p>@enderror
            @endforeach
        </div>
    </section>
    <div class="cla-actions"><button type="submit" class="cla-primary">{{ __('Save settings') }}</button></div>
</form>
</x-filament-panels::page>
