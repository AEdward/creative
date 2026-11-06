<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\ManageReviews;
use App\Models\CourseReview;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ReviewResource extends Resource
{
    protected static ?string $model = CourseReview::class;

    protected static ?string $navigationLabel = 'Reviews';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 11;

    public static function getNavigationBadge(): ?string
    {
        $pending = CourseReview::where('status', 'pending')->count();

        return $pending ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'support');
    }

    public static function moderationRequired(): bool
    {
        return (bool) (DB::table('review_settings')->value('moderation_required') ?? true);
    }

    public static function policyAction(): Action
    {
        return Action::make('policy')
            ->label(fn () => static::moderationRequired() ? 'Reviews need approval: On' : 'Reviews need approval: Off')
            ->color(fn () => static::moderationRequired() ? 'success' : 'gray')->icon('heroicon-o-shield-check')
            ->requiresConfirmation()
            ->modalDescription(fn () => static::moderationRequired() ? 'New reviews will appear on course pages straight away.' : 'New reviews will wait for an admin to approve them.')
            ->action(fn () => DB::table('review_settings')->updateOrInsert(['id' => 1], ['moderation_required' => ! static::moderationRequired(), 'updated_at' => now(), 'created_at' => now()]));
    }

    public static function table(Table $table): Table
    {
        $set = fn (string $status) => fn (CourseReview $record) => $record->update(['status' => $status]);

        return $table->modifyQueryUsing(fn ($query) => $query->with(['user', 'course']))->columns([
            TextColumn::make('created_at')->label('Date')->date('M j, Y')->sortable(),
            TextColumn::make('course.title')->label('Course')->limit(30)->searchable(),
            TextColumn::make('user.name')->label('Learner'),
            TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state)),
            TextColumn::make('body')->label('Review')->limit(80)->wrap(),
            TextColumn::make('status')->badge()->color(fn (string $state) => ['approved' => 'success', 'pending' => 'warning'][$state] ?? 'danger'),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
        ])->recordActions([
            Action::make('approve')->label('Approve')->color('success')->icon('heroicon-o-check')->visible(fn (CourseReview $r) => $r->status !== 'approved')->action($set('approved')),
            Action::make('reject')->label('Reject')->color('danger')->icon('heroicon-o-x-mark')->visible(fn (CourseReview $r) => $r->status !== 'rejected')->action($set('rejected')),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageReviews::route('/')];
    }
}
