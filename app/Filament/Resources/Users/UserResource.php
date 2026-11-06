<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\AccountAdministration;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Students & users';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false; // accounts are added with the "New user" action, which emails a set-password link
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'super');
    }

    public static function newUserAction(): Action
    {
        return Action::make('newUser')->label('New user')->icon('heroicon-o-user-plus')
            ->modalDescription('They get an email with a link to choose their own password. You never see or set it.')
            ->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->maxLength(255)->unique('users', 'email'),
                Select::make('role')->options(['student' => 'Student', 'instructor' => 'Instructor'])->default('student')->required()
                    ->helperText('Staff (admins) are added on the Staff page.'),
            ])
            ->action(function (array $data): void {
                app(AccountAdministration::class)->createAccount(auth()->user(), $data['name'], $data['email'], $data['role']);
                Notification::make()->title('Account created — a set-password email is on its way')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->visibleToStaff(auth()->user()))->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('role')->badge(), TextColumn::make('status')->badge(), TextColumn::make('created_at')->dateTime()->sortable()])->filters([SelectFilter::make('role')->options(['student' => 'Student', 'instructor' => 'Instructor', 'admin' => 'Administrator'])])->recordActions([
            Action::make('approveInstructor')->label('Approve instructor')->requiresConfirmation()->visible(fn (User $record) => $record->role === 'student' && $record->status === 'active')->action(fn (User $record) => app(AccountAdministration::class)->approveInstructor(auth()->user(), $record)),
            Action::make('changeRole')->label('Change role')->icon('heroicon-o-arrows-right-left')
                ->schema([Select::make('role')->options(['student' => 'Student', 'instructor' => 'Instructor'])->required()])
                ->fillForm(fn (User $record) => ['role' => $record->role])
                ->visible(fn (User $record) => $record->id !== auth()->id() && $record->role !== 'admin')
                ->action(fn (User $record, array $data) => app(AccountAdministration::class)->setRole(auth()->user(), $record, $data['role'])),
            Action::make('suspend')->label('Suspend account')->color('danger')->requiresConfirmation()->visible(fn (User $record) => $record->role !== 'admin' && $record->status === 'active')->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'suspended')),
            Action::make('reactivate')->label('Reactivate account')->requiresConfirmation()->visible(fn (User $record) => $record->role !== 'admin' && $record->status === 'suspended')->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'active')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
