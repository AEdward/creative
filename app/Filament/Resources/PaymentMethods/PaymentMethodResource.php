<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationLabel = 'Bank accounts';

    protected static ?string $modelLabel = 'bank account';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Bank or wallet name')->required()->maxLength(100)->placeholder('Commercial Bank of Ethiopia'),
            FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('payment-methods')->visibility('public')->maxSize(1024)->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
            TextInput::make('account_name')->label('Account holder name')->required()->maxLength(150),
            TextInput::make('account_number')->label('Account / phone number')->required()->maxLength(80),
            Textarea::make('instructions')->label('Extra instructions for learners')->maxLength(2000)->rows(3),
            TextInput::make('sort_order')->integer()->minValue(0)->default(0)->required(),
            Toggle::make('active')->label('Show at checkout')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('logo_path')->label('Logo')->disk('public')->height(36),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('account_name')->label('Account holder'),
            TextColumn::make('account_number')->label('Account number')->copyable(),
            ToggleColumn::make('active')->label('Active'),
            TextColumn::make('sort_order')->sortable(),
        ])->recordActions([
            EditAction::make(),
            // Methods already used by learners are deactivated instead of deleted.
            DeleteAction::make()->visible(fn (PaymentMethod $record) => ! \App\Models\PaymentSubmission::where('payment_method_id', $record->id)->exists()),
        ])->defaultSort('sort_order');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentMethods::route('/')];
    }
}
