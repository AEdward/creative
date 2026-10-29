<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('reference')->searchable(), TextColumn::make('user.name')->label('Learner'), TextColumn::make('total_minor')->label('Total')->formatStateUsing(fn (Order $record) => $record->totalLabel()), TextColumn::make('status')->badge(), TextColumn::make('payment.provider')->label('Provider'), TextColumn::make('payment.status')->label('Verification')->badge(), TextColumn::make('created_at')->dateTime()->sortable()])->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'awaiting_payment' => 'Awaiting payment', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'expired' => 'Expired'])])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrders::route('/')];
    }
}
