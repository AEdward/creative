<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AccountAdministration;
use App\Services\TwoFactor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Super admins manage who runs the site and what each person can open. */
class Staff extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.staff';

    protected static ?string $title = 'Staff';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 18;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'super');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** What each role can open, shown on the page so the choice is clear. */
    public const ROLE_HELP = [
        'super' => 'Everything, including staff, site settings, activity log and site health.',
        'content' => 'Courses, course studio, media library, categories, certificates and instructors.',
        'support' => 'Support chat and tickets, contact form, reviews and enrollments.',
        'finance' => 'Payment approvals, orders & refunds, bank accounts, coupons, finance and enrollments.',
    ];

    private static function roleOptions(): array
    {
        return collect(User::STAFF_ROLES)->mapWithKeys(fn ($label, $key) => [$key => $label.' — '.self::ROLE_HELP[$key]])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newStaff')->label('New staff member')->icon('heroicon-o-user-plus')
                ->modalDescription('They get an email with a link to choose their own password, then must set up two-step sign-in.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique('users', 'email'),
                    Select::make('staff_role')->label('Role')->options(self::roleOptions())->required()->default('support'),
                ])
                ->action(function (array $data): void {
                    app(AccountAdministration::class)->createAccount(auth()->user(), $data['name'], $data['email'], 'admin', $data['staff_role']);
                    Notification::make()->title('Staff member added — a set-password email is on its way')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('role', 'admin')->visibleToStaff(auth()->user()))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $record) => $record->email),
                TextColumn::make('staff_role')->label('Role')->badge()
                    ->state(fn (User $record) => User::STAFF_ROLES[$record->staffRole()] ?? '—')
                    ->color(fn (User $record) => ['super' => 'danger', 'content' => 'info', 'support' => 'warning', 'finance' => 'success'][$record->staffRole()] ?? 'gray'),
                TextColumn::make('two_factor_confirmed_at')->label('Two-step sign-in')->badge()
                    ->state(fn (User $record) => $record->two_factor_confirmed_at ? 'On' : 'Not set up')
                    ->color(fn (User $record) => $record->two_factor_confirmed_at ? 'success' : 'warning'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('changeRole')->label('Change role')->icon('heroicon-o-arrows-right-left')
                    ->schema([Select::make('staff_role')->label('Role')->options(self::roleOptions())->required()])
                    ->fillForm(fn (User $record) => ['staff_role' => $record->staffRole()])
                    ->visible(fn (User $record) => $record->id !== auth()->id())
                    ->action(fn (User $record, array $data) => app(AccountAdministration::class)->setRole(auth()->user(), $record, 'admin', $data['staff_role'])),
                Action::make('resetTwoFactor')->label('Reset two-step sign-in')->color('gray')->requiresConfirmation()
                    ->modalDescription('Use this if they lost their phone. They set two-step sign-in up again at their next sign-in.')
                    ->visible(fn (User $record) => (bool) $record->two_factor_confirmed_at && $record->id !== auth()->id())
                    ->action(fn (User $record) => app(TwoFactor::class)->reset(auth()->user(), $record)),
                Action::make('removeStaff')->label('Remove staff access')->color('danger')->requiresConfirmation()
                    ->modalDescription('They keep their account as a regular learner but can no longer open the admin panel.')
                    ->visible(fn (User $record) => $record->id !== auth()->id())
                    ->action(fn (User $record) => app(AccountAdministration::class)->setRole(auth()->user(), $record, 'student')),
            ])
            ->defaultSort('name');
    }
}
