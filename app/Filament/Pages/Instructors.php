<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountAdministration;
use App\Support\AcademySettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Instructor accounts plus the switch that opens or closes the instructor workspace. */
class Instructors extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.instructors';

    protected static ?string $title = 'Instructors';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 4;

    public bool $portalOpen = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'content');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->portalOpen = (bool) AcademySettings::get('instructor_portal_enabled');
    }

    /** The switch saves immediately. */
    public function updatedPortalOpen(bool $value): void
    {
        abort_unless(static::canAccess(), 403);
        AcademySettings::save(['instructor_portal_enabled' => $value]);
        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'settings.instructor_portal', 'subject_type' => 'site_setting', 'subject_id' => 0, 'changes' => ['instructor_portal_enabled' => $value]]);
        Notification::make()->title($value ? 'Instructor workspace is open' : 'Instructor workspace is closed')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newInstructor')->label('New instructor')->icon('heroicon-o-user-plus')
                ->modalDescription('They get an email with a link to choose their own password.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique('users', 'email'),
                ])
                ->action(function (array $data): void {
                    app(AccountAdministration::class)->createAccount(auth()->user(), $data['name'], $data['email'], 'instructor');
                    Notification::make()->title('Instructor added — a set-password email is on its way')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('role', 'instructor')->withCount('courses'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (User $record) => $record->email),
                TextColumn::make('courses_count')->label('Courses')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->label('Added')->date('M j, Y')->sortable(),
            ])
            ->recordActions([
                Action::make('suspend')->label('Suspend')->color('danger')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'active')
                    ->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'suspended')),
                Action::make('reactivate')->label('Reactivate')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'suspended')
                    ->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'active')),
            ])
            ->emptyStateHeading('No instructors yet')
            ->emptyStateDescription('Add instructor accounts here, or just type an instructor’s name when you create a course.')
            ->defaultSort('name');
    }
}
