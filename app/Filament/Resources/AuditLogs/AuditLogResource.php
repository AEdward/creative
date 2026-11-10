<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Read-only record of admin actions (approvals, refunds, role changes, settings). */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationLabel = 'Activity log';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 19;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'super');
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('actor'))->columns([
            TextColumn::make('created_at')->label('When')->dateTime('M j, Y H:i')->sortable(),
            TextColumn::make('actor.name')->label('Who')->placeholder('System')
                ->formatStateUsing(fn ($state, AuditLog $r) => $r->actor?->is_owner && ! auth()->user()?->is_owner ? 'Owner' : $state),
            TextColumn::make('action')->badge()->color('gray')->searchable(),
            TextColumn::make('subject_type')->label('Item')->formatStateUsing(fn ($state, AuditLog $r) => $state.' #'.$r->subject_id),
            TextColumn::make('changes')->label('Details')->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $state)->limit(80)->wrap(),
        ])->filters([
            SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
