<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Services\CouponService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    public static function newCouponAction(): Action
    {
        return Action::make('newCoupon')->label('New coupon')->icon('heroicon-o-plus')
            ->schema([
                TextInput::make('code')->required()->maxLength(CouponService::MAX_CODE_LENGTH)->regex('/^[A-Za-z0-9_-]{3,'.CouponService::MAX_CODE_LENGTH.'}$/')
                    ->helperText('Letters, numbers, - and _. Learners can type it in any case.')->dehydrateStateUsing(fn ($state) => Str::upper(trim($state)))
                    ->unique('coupons', 'code'),
                TextInput::make('percent')->label('Discount %')->numeric()->required()->minValue(1)->maxValue(99),
                Select::make('course_id')->label('Only for this course (optional)')->options(fn () => Course::orderBy('title')->pluck('title', 'id'))->searchable(),
                TextInput::make('max_uses')->label('Total uses allowed')->numeric()->required()->minValue(1)->default(100),
                TextInput::make('per_user_limit')->label('Uses per learner')->numeric()->required()->minValue(1)->default(1),
                DateTimePicker::make('starts_at')->label('Starts (optional)'),
                DateTimePicker::make('expires_at')->label('Expires (optional)')->after('starts_at'),
            ])
            ->action(function (array $data): void {
                $coupon = Coupon::create($data + ['active' => true]);
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'coupon.created', 'subject_type' => 'coupon', 'subject_id' => $coupon->id, 'changes' => ['code' => $coupon->code, 'percent' => $coupon->percent]]);
            });
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withCount(['redemptions as used_count' => fn ($q) => $q->where('status', 'redeemed')]))->columns([
            TextColumn::make('code')->weight('bold')->copyable()->searchable(),
            TextColumn::make('percent')->label('Discount')->suffix('%'),
            TextColumn::make('course.title')->label('Course')->placeholder('Any course')->limit(30),
            TextColumn::make('used_count')->label('Used')->formatStateUsing(fn ($state, Coupon $record) => $state.' / '.$record->max_uses),
            TextColumn::make('expires_at')->label('Expires')->dateTime('M j, Y')->placeholder('Never'),
            TextColumn::make('active')->badge()->formatStateUsing(fn ($state) => $state ? 'Active' : 'Off')->color(fn ($state) => $state ? 'success' : 'gray'),
        ])->recordActions([
            Action::make('toggle')->label(fn (Coupon $record) => $record->active ? 'Switch off' : 'Switch on')
                ->color(fn (Coupon $record) => $record->active ? 'danger' : 'success')
                ->action(function (Coupon $record): void {
                    $record->update(['active' => ! $record->active]);
                    AuditLog::create(['actor_id' => auth()->id(), 'action' => 'coupon.toggled', 'subject_type' => 'coupon', 'subject_id' => $record->id, 'changes' => ['active' => $record->active]]);
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
