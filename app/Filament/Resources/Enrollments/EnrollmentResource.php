<?php

namespace App\Filament\Resources\Enrollments;

use App\Filament\Resources\Enrollments\Pages\ManageEnrollments;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationLabel = 'Enrollments';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    /** Give a learner access to a course without a payment (scholarship, partner, manual sale). */
    public static function grantAction(): Action
    {
        return Action::make('grant')->label('Enroll a learner')->icon('heroicon-o-plus')
            ->schema([
                Select::make('user_id')->label('Learner')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('role', 'student')->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))->limit(20)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email]))
                    ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? $u->name.' · '.$u->email : null),
                Select::make('course_id')->label('Course')->required()->searchable()->options(fn () => Course::orderBy('title')->pluck('title', 'id')),
            ])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    $existing = Enrollment::where('user_id', $data['user_id'])->where('course_id', $data['course_id'])->lockForUpdate()->first();
                    $enrollment = Enrollment::updateOrCreate(['user_id' => $data['user_id'], 'course_id' => $data['course_id']], ['status' => 'active', 'source' => $existing->source ?? 'admin', 'enrolled_at' => $existing->enrolled_at ?? now(), 'expires_at' => null]);
                    AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.granted', 'subject_type' => 'enrollment', 'subject_id' => $enrollment->id, 'changes' => ['user' => $data['user_id'], 'course' => $data['course_id']]]);
                });
                Notification::make()->title('Learner enrolled')->success()->send();
            });
    }

    /** CSV of enrollments in a date range (the existing streamed export). */
    public static function exportAction(): Action
    {
        return Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->schema([
                DatePicker::make('from')->required()->default(now('Africa/Nairobi')->startOfMonth()),
                DatePicker::make('to')->required()->default(now('Africa/Nairobi'))->afterOrEqual('from'),
            ])
            ->action(fn (array $data) => redirect()->route('academy-admin.export', ['from' => substr($data['from'], 0, 10), 'to' => substr($data['to'], 0, 10)]));
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['user', 'course']))->columns([
            TextColumn::make('enrolled_at')->label('Enrolled')->dateTime('M j, Y')->sortable(),
            TextColumn::make('user.name')->label('Learner')->description(fn (Enrollment $record) => $record->user?->email)->searchable(),
            TextColumn::make('course.title')->label('Course')->limit(40)->searchable(),
            TextColumn::make('source')->badge()->color('gray')->formatStateUsing(fn (?string $state) => str_replace('_', ' ', (string) $state)),
            TextColumn::make('status')->badge()->color(fn (string $state) => ['active' => 'success', 'refunded' => 'gray'][$state] ?? 'danger'),
        ])->filters([
            SelectFilter::make('status')->options(['active' => 'Active', 'revoked' => 'Revoked', 'refunded' => 'Refunded']),
            SelectFilter::make('course_id')->label('Course')->options(fn () => Course::orderBy('title')->pluck('title', 'id')),
        ])->recordActions([
            Action::make('revoke')->label('Revoke access')->color('danger')->requiresConfirmation()
                ->modalDescription('The learner loses access to this course. Their progress and payment history are kept.')
                ->visible(fn (Enrollment $record) => $record->status === 'active')
                ->action(function (Enrollment $record): void {
                    DB::transaction(function () use ($record): void {
                        $locked = Enrollment::whereKey($record->id)->lockForUpdate()->firstOrFail();
                        $locked->update(['status' => 'revoked']);
                        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.revoked', 'subject_type' => 'enrollment', 'subject_id' => $locked->id, 'changes' => ['status' => 'revoked']]);
                    });
                }),
            Action::make('restore')->label('Restore access')->requiresConfirmation()
                ->visible(fn (Enrollment $record) => $record->status !== 'active')
                ->action(function (Enrollment $record): void {
                    $record->update(['status' => 'active']);
                    AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.restored', 'subject_type' => 'enrollment', 'subject_id' => $record->id, 'changes' => ['status' => 'active']]);
                }),
        ])->defaultSort('enrolled_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageEnrollments::route('/')];
    }
}
