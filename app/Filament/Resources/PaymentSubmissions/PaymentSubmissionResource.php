<?php

namespace App\Filament\Resources\PaymentSubmissions;

use App\Filament\Resources\PaymentSubmissions\Pages\ManagePaymentSubmissions;
use App\Models\PaymentSubmission;
use App\Services\Payments\BankTransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentSubmissionResource extends Resource
{
    protected static ?string $model = PaymentSubmission::class;

    protected static ?string $navigationLabel = 'Payment approvals';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $pending = PaymentSubmission::where('status', 'pending')->count();

        return $pending ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'finance');
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['user', 'method', 'order.items']))->columns([
            TextColumn::make('created_at')->label('Submitted')->since()->sortable(),
            TextColumn::make('user.name')->label('Learner')->description(fn (PaymentSubmission $r) => $r->user?->email)->searchable(),
            TextColumn::make('order.items.title_snapshot')->label('Course')->limit(40),
            TextColumn::make('order.total_minor')->label('Amount')->formatStateUsing(fn ($state, PaymentSubmission $r) => $r->order->currency.' '.number_format($state / 100, 2)),
            TextColumn::make('method.name')->label('Bank'),
            TextColumn::make('transaction_reference')->label('Transaction ID')->copyable()->placeholder('—')->searchable(),
            TextColumn::make('proof_path')->label('Screenshot')->formatStateUsing(fn () => 'Open')->url(fn (PaymentSubmission $r) => $r->proof_path ? route('payment-proofs.show', $r) : null, true)->placeholder('—'),
            TextColumn::make('status')->badge()->color(fn (string $state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray')
                ->description(fn (PaymentSubmission $r) => $r->rejection_reason),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('pending'),
        ])->recordActions([
            Action::make('approve')->label('Approve')->color('success')->icon('heroicon-o-check')->requiresConfirmation()
                ->modalDescription('Confirm you have seen this money arrive in the bank account. The learner gets access immediately.')
                ->visible(fn (PaymentSubmission $r) => $r->status === 'pending')
                ->action(fn (PaymentSubmission $r) => app(BankTransferService::class)->approve($r, auth()->user())),
            Action::make('reject')->label('Reject')->color('danger')->icon('heroicon-o-x-mark')
                ->schema([Textarea::make('reason')->label('Reason shown to the learner')->required()->maxLength(500)])
                ->visible(fn (PaymentSubmission $r) => $r->status === 'pending')
                ->action(fn (PaymentSubmission $r, array $data) => app(BankTransferService::class)->reject($r, auth()->user(), $data['reason'])),
            Action::make('reopen')->label('Reopen')->color('gray')->icon('heroicon-o-arrow-path')->requiresConfirmation()
                ->modalDescription('Use this if the payment was rejected by mistake. It goes back to the pending list so you can approve it.')
                ->visible(fn (PaymentSubmission $r) => $r->status === 'rejected')
                ->action(fn (PaymentSubmission $r) => app(BankTransferService::class)->reopen($r, auth()->user())),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentSubmissions::route('/')];
    }
}
