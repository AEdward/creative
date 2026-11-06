<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Services\Payments\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = 'Orders & Refunds';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 8;

    public static function getNavigationBadge(): ?string
    {
        $open = Order::where('refund_status', 'requested')->count();

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
        return $table->modifyQueryUsing(fn ($query) => $query->with(['user', 'items', 'payment']))->columns([
            TextColumn::make('created_at')->label('Date')->dateTime('M j, Y H:i')->sortable()->description(fn (Order $record) => $record->reference),
            TextColumn::make('user.name')->label('Learner')->description(fn (Order $record) => $record->user?->email)->searchable(),
            TextColumn::make('items.title_snapshot')->label('Course')->limit(30)->tooltip(fn (Order $record) => $record->items->pluck('title_snapshot')->join(', ')),
            TextColumn::make('total_minor')->label('Total')->formatStateUsing(fn (Order $record) => $record->totalLabel())
                ->description(fn (Order $record) => $record->vat_minor ? 'VAT '.$record->money($record->vat_minor) : null),
            TextColumn::make('status')->badge()->color(fn (string $state) => ['paid' => 'success', 'awaiting_payment' => 'warning', 'refunded' => 'gray'][$state] ?? 'danger')
                ->description(fn (Order $record) => $record->payment?->provider ? str_replace('_', ' ', $record->payment->provider) : null),
            TextColumn::make('refund_status')->label('Refund')->badge()->placeholder('—')
                ->color(fn (?string $state) => ['requested' => 'warning', 'refunded' => 'gray', 'declined' => 'danger'][$state] ?? 'gray')
                ->description(fn (Order $record) => $record->refund_status === 'requested' ? $record->refund_reason : $record->refund_note)->wrap(),
            TextColumn::make('reference')->searchable()->hidden(),
        ])->filters([
            SelectFilter::make('status')->options(['awaiting_payment' => 'Awaiting payment', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled', 'expired' => 'Expired']),
            SelectFilter::make('refund_status')->label('Refund')->options(['requested' => 'Requested', 'refunded' => 'Refunded', 'declined' => 'Declined']),
        ])->recordActions([
            Action::make('refund')->label('Refund')->color('danger')->icon('heroicon-o-arrow-uturn-left')
                ->modalDescription('Removes the learner’s access to this course and marks the order refunded. Send the money back by bank transfer yourself.')
                ->schema([Textarea::make('note')->label('Note to the learner (optional)')->maxLength(1000)])
                ->visible(fn (Order $record) => $record->status === 'paid' && in_array($record->refund_status, [null, 'requested'], true))
                ->action(fn (Order $record, array $data) => app(RefundService::class)->approve($record, auth()->user(), $data['note'] ?? null)),
            Action::make('decline')->label('Decline refund')->color('gray')->icon('heroicon-o-x-mark')
                ->schema([Textarea::make('note')->label('Reason shown to the learner')->required()->maxLength(1000)])
                ->visible(fn (Order $record) => $record->refund_status === 'requested')
                ->action(fn (Order $record, array $data) => app(RefundService::class)->decline($record, auth()->user(), $data['note'])),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrders::route('/')];
    }
}
