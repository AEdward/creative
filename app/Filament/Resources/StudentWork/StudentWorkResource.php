<?php

namespace App\Filament\Resources\StudentWork;

use App\Filament\Resources\StudentWork\Pages\ManageStudentWork;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Services\StudentWork;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/** Learners' submitted work: review, download, and delete files to free space (grades are kept). */
class StudentWorkResource extends Resource
{
    protected static ?string $model = AssignmentSubmission::class;

    protected static ?string $navigationLabel = 'Student work';

    protected static ?string $slug = 'student-work';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $pending = AssignmentSubmission::where('status', 'pending')->count();

        return $pending ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'content');
    }

    public static function cleanupAction(): Action
    {
        return Action::make('cleanup')->label('Free up space')->icon('heroicon-o-trash')->color('gray')
            ->modalDescription('Deletes the files of reviewed work (accepted or sent back) that is older than the age you choose. Grades and feedback stay. Work waiting for review is never deleted.')
            ->schema([
                Select::make('days')->label('Reviewed more than')->options([30 => '30 days ago', 90 => '90 days ago', 180 => '6 months ago', 365 => '1 year ago'])->default(90)->required()->live(),
            ])
            ->modalSubmitActionLabel('Delete these files')
            ->action(function (array $data): void {
                $work = app(StudentWork::class);
                $candidates = $work->cleanupCandidates((int) $data['days'])->get();
                $bytes = (int) $candidates->sum('bytes');
                foreach ($candidates as $submission) {
                    $work->deleteFile($submission, auth()->user());
                }
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'student_work.cleanup', 'subject_type' => 'assignment_submission', 'subject_id' => 0, 'changes' => ['files' => $candidates->count(), 'bytes' => $bytes, 'older_than_days' => (int) $data['days']]]);
                Notification::make()->title(trans_choice(':count file deleted|:count files deleted', $candidates->count(), ['count' => $candidates->count()]))
                    ->body(__(':size freed. Grades and feedback are kept.', ['size' => Number::fileSize($bytes, 1)]))->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['assessment.course', 'enrollment.user']))->columns([
            TextColumn::make('updated_at')->label('Submitted')->since()->sortable(),
            TextColumn::make('enrollment.user.name')->label('Learner')->description(fn (AssignmentSubmission $r) => $r->enrollment?->user?->email)->searchable(),
            TextColumn::make('assessment.title')->label('Assignment')->description(fn (AssignmentSubmission $r) => $r->assessment?->course?->title)->limit(40),
            TextColumn::make('original_name')->label('File')->limit(28)
                ->description(fn (AssignmentSubmission $r) => $r->file_deleted_at ? __('Deleted to save space') : ($r->bytes ? Number::fileSize($r->bytes, 1).' · '.($r->disk === 'r2' ? 'R2' : __('server')) : null)),
            TextColumn::make('status')->badge()->color(fn (string $state) => ['accepted' => 'success', 'pending' => 'warning'][$state] ?? 'danger')
                ->formatStateUsing(fn (string $state) => ['pending' => 'Waiting for review', 'accepted' => 'Accepted', 'revision-needed' => 'Changes requested'][$state] ?? $state),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Waiting for review', 'accepted' => 'Accepted', 'revision-needed' => 'Changes requested']),
            TernaryFilter::make('file_deleted_at')->label('File')->nullable()->trueLabel('Deleted')->falseLabel('Still stored'),
        ])->recordActions([
            Action::make('download')->label('Download')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn (AssignmentSubmission $r) => ! $r->file_deleted_at)
                ->url(fn (AssignmentSubmission $r) => route('assignments.download', $r), true),
            Action::make('review')->label('Review')->icon('heroicon-o-check-badge')
                ->schema([
                    Radio::make('status')->label('Decision')->options(['accepted' => 'Accept', 'revision-needed' => 'Ask for changes'])->required(),
                    Textarea::make('feedback')->label('Feedback to the learner')->required()->maxLength(10000)->rows(4),
                ])
                ->fillForm(fn (AssignmentSubmission $r) => ['status' => $r->status === 'pending' ? null : $r->status, 'feedback' => $r->feedback])
                ->action(fn (AssignmentSubmission $r, array $data) => app(StudentWork::class)->review($r, auth()->user(), $data['status'], $data['feedback'])),
            Action::make('deleteFile')->label('Delete file')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                ->modalDescription('Removes the uploaded file to free space. The grade and feedback stay.')
                ->visible(fn (AssignmentSubmission $r) => ! $r->file_deleted_at && $r->status !== 'pending')
                ->action(fn (AssignmentSubmission $r) => app(StudentWork::class)->deleteFile($r, auth()->user())),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageStudentWork::route('/')];
    }
}
