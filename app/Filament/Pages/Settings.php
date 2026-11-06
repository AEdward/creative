<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\VideoAsset;
use App\Support\AcademySettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static ?string $title = 'Site settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    public array $settings = [];

    /** Announcement banner and extra About / FAQ text shown on the public site. */
    public array $content = ['banner' => '', 'about' => '', 'faq' => ''];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->settings = AcademySettings::all();
        $this->content = array_merge($this->content, array_intersect_key(SiteSetting::content(), $this->content));
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate([
            'settings.contact_email' => ['nullable', 'email', 'max:255'],
            'settings.contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ()-]{0,40}$/'],
            'settings.contact_address' => ['nullable', 'string', 'max:255'],
            'settings.bank_checkout_enabled' => ['boolean'],
            'settings.vat_enabled' => ['boolean'],
            'settings.vat_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'settings.google_site_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
            'settings.bing_site_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
            'settings.storage_limit_gb' => ['required', 'integer', 'min:1', 'max:100000'],
            'settings.storage_alert_percent' => ['required', 'integer', 'min:10', 'max:100'],
            'settings.privacy' => ['nullable', 'string', 'max:30000'],
            'settings.terms' => ['nullable', 'string', 'max:30000'],
            'settings.refund-policy' => ['nullable', 'string', 'max:30000'],
        ])['settings'];
        $content = $this->validate([
            'content.banner' => ['nullable', 'string', 'max:300'],
            'content.about' => ['nullable', 'string', 'max:10000'],
            'content.faq' => ['nullable', 'string', 'max:10000'],
        ])['content'];
        SiteSetting::updateOrCreate(['key' => 'site_content'], ['value' => array_merge(SiteSetting::content(), array_map(fn ($v) => (string) $v, $content))]);
        $data['vat_percent'] = (float) $data['vat_percent'];
        $data['storage_limit_gb'] = (int) $data['storage_limit_gb'];
        $data['storage_alert_percent'] = (int) $data['storage_alert_percent'];
        AcademySettings::save($data);
        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'settings.updated', 'subject_type' => 'site_setting', 'subject_id' => 0, 'changes' => array_diff_key($data, array_flip(AcademySettings::LEGAL_PAGES))]);
        Notification::make()->title('Settings saved')->success()->send();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'super');
    }

    protected function getViewData(): array
    {
        $used = (int) VideoAsset::where('status', 'ready')->sum('expected_bytes');

        return ['usedBytes' => $used, 'limitBytes' => max(1, (int) $this->settings['storage_limit_gb']) * 1024 ** 3];
    }
}
