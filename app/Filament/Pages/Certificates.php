<?php

namespace App\Filament\Pages;

use App\Models\Certificate;
use App\Support\CertificateTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Certificates extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.certificates';

    protected static ?string $title = 'Certificates';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 9;

    public array $template = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->template = CertificateTemplate::get();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate([
            'template.heading' => ['required', 'string', 'max:80'],
            'template.presented' => ['required', 'string', 'max:120'],
            'template.body' => ['required', 'string', 'max:160'],
            'template.signer_name' => ['required', 'string', 'max:80'],
            'template.signer_title' => ['required', 'string', 'max:80'],
            'template.footer' => ['required', 'string', 'max:200'],
            'template.accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ])['template'];
        CertificateTemplate::save($data);
        \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'certificate.template_updated', 'subject_type' => 'site_setting', 'subject_id' => 0, 'changes' => $data]);
        Notification::make()->title('Certificate design saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        $this->template = CertificateTemplate::DEFAULTS;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    protected function getViewData(): array
    {
        return ['issued' => Certificate::latest('issued_at')->paginate(10)];
    }
}
