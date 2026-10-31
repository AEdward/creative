<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\AccountAdministration;
use Filament\Actions\Action;
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
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('role')->badge(), TextColumn::make('status')->badge(), TextColumn::make('created_at')->dateTime()->sortable()])->filters([SelectFilter::make('role')->options(['student' => 'Student', 'instructor' => 'Instructor', 'admin' => 'Administrator'])])->recordActions([
            Action::make('approveInstructor')->label('Approve instructor')->requiresConfirmation()->visible(fn (User $record) => $record->role === 'student' && $record->status === 'active')->action(fn (User $record) => app(AccountAdministration::class)->approveInstructor(auth()->user(), $record)),
            Action::make('suspend')->label('Suspend account')->color('danger')->requiresConfirmation()->visible(fn (User $record) => $record->role !== 'admin' && $record->status === 'active')->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'suspended')),
            Action::make('reactivate')->label('Reactivate account')->requiresConfirmation()->visible(fn (User $record) => $record->role !== 'admin' && $record->status === 'suspended')->action(fn (User $record) => app(AccountAdministration::class)->setStatus(auth()->user(), $record, 'active')),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
