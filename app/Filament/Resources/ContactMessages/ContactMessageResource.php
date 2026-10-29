<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('name'), TextEntry::make('email'), TextEntry::make('subject'), TextEntry::make('message')->columnSpanFull(), TextEntry::make('created_at')->dateTime()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('subject')->limit(50), TextColumn::make('status')->badge(), TextColumn::make('created_at')->dateTime()->sortable()])->recordActions([ViewAction::make(), Action::make('read')->label('Mark read')->visible(fn (ContactMessage $record) => $record->status === 'new')->action(fn (ContactMessage $record) => $record->update(['status' => 'read']))])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactMessages::route('/')];
    }
}
