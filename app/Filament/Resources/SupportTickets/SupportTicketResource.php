<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Models\NotificationPreference;
use App\Models\SupportTicket;
use App\Notifications\EngagementNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationLabel = 'Support tickets';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?int $navigationSort = 12;

    public static function getNavigationBadge(): ?string
    {
        $open = SupportTicket::where('status', 'open')->count();

        return $open ? (string) $open : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('user')->withCount('replies'))->columns([
            TextColumn::make('updated_at')->label('Last activity')->since()->sortable(),
            TextColumn::make('subject')->searchable()->weight('bold')->description(fn (SupportTicket $r) => $r->user?->name.' · '.$r->user?->email),
            TextColumn::make('replies_count')->label('Replies'),
            TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'open' ? 'warning' : 'gray'),
        ])->filters([
            SelectFilter::make('status')->options(['open' => 'Open', 'closed' => 'Closed'])->default('open'),
        ])->recordActions([
            Action::make('reply')->label('Open & reply')->icon('heroicon-o-chat-bubble-left-right')
                ->modalHeading(fn (SupportTicket $r) => $r->subject)
                ->modalContent(fn (SupportTicket $r) => view('filament.support-ticket-thread', ['ticket' => $r->load(['user', 'replies.user'])]))
                ->schema([Textarea::make('body')->label('Your reply')->required()->maxLength(10000)->rows(4)])
                ->modalSubmitActionLabel('Send reply')
                ->visible(fn (SupportTicket $r) => $r->status === 'open')
                ->action(function (SupportTicket $r, array $data): void {
                    DB::transaction(function () use ($r, $data): void {
                        $locked = SupportTicket::whereKey($r->id)->lockForUpdate()->firstOrFail();
                        $locked->replies()->create(['body' => $data['body'], 'user_id' => auth()->id()]);
                        $locked->touch();
                    });
                    if (NotificationPreference::where('user_id', $r->user_id)->value('support') !== false) {
                        $r->user?->notify(new EngagementNotification(['title' => 'Support response: '.$r->subject, 'body' => $data['body'], 'url' => route('support.show', $r)]));
                    }
                }),
            Action::make('close')->label('Close')->color('gray')->requiresConfirmation()->visible(fn (SupportTicket $r) => $r->status === 'open')->action(fn (SupportTicket $r) => $r->update(['status' => 'closed'])),
            Action::make('reopen')->label('Reopen')->visible(fn (SupportTicket $r) => $r->status === 'closed')->action(fn (SupportTicket $r) => $r->update(['status' => 'open'])),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportTickets::route('/')];
    }
}
