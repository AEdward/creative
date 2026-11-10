<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Support\SiteHealth as Checks;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

/** Everything that keeps the site running, on one page. */
class SiteHealth extends Page
{
    protected string $view = 'filament.pages.site-health';

    protected static ?string $title = 'Site health';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 21;

    /** Result of the last "Check video storage now" click. */
    public ?array $mediaPing = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'super');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function getNavigationBadge(): ?string
    {
        $failing = collect(app(Checks::class)->checks())->where('status', 'fail')->count();

        return $failing ? (string) $failing : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function checkMedia(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->mediaPing = app(Checks::class)->pingMedia();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')->label('Send test email')->icon('heroicon-o-envelope')->color('gray')
                ->schema([TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()->email)])
                ->action(function (array $data): void {
                    try {
                        Mail::raw('This is a test email from Creative Lab Academy. If you can read it, sending works.', fn ($m) => $m->to($data['to'])->subject('Creative Lab test email'));
                        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'health.test_email', 'subject_type' => 'site', 'subject_id' => 0, 'changes' => ['to' => $data['to']]]);
                        Notification::make()->title('Test email sent')->body('Check the inbox (and spam) of '.$data['to'].'.')->success()->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Sending failed')->body(class_basename($e).': check the MAIL_ settings in .env.')->danger()->send();
                    }
                }),
            Action::make('refresh')->label('Check again')->icon('heroicon-o-arrow-path')->color('gray')->action(fn () => null),
        ];
    }

    protected function getViewData(): array
    {
        $checks = app(Checks::class)->checks();
        if ($this->mediaPing) {
            $checks = array_map(fn ($c) => $c['key'] === 'media' ? $this->mediaPing : $c, $checks);
        }

        return [
            'checks' => $checks,
            'release' => is_file(base_path('RELEASE')) ? trim((string) file_get_contents(base_path('RELEASE'))) : 'development copy',
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'upUrl' => url('/up'),
        ];
    }
}
