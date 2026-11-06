<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Pages\CourseStudio;
use App\Filament\Resources\Courses\Pages\ManageCourses;
use App\Models\Course;
use App\Services\CourseDeletion;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseResource extends Resource
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'content');
    }

    protected static ?string $model = Course::class;

    protected static ?string $navigationLabel = 'Courses';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable()->sortable(), TextColumn::make('instructor.name'), TextColumn::make('category.name'), TextColumn::make('status')->badge(), TextColumn::make('published_at')->dateTime()])->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])])->recordActions([
            Action::make('edit')->label('Open studio')->icon('heroicon-o-pencil-square')->url(fn (Course $record) => CourseStudio::getUrl(['course' => $record->slug])),
            Action::make('learners')->label('Learners')->icon('heroicon-o-users')->url(fn (Course $record) => CourseStudio::getUrl(['course' => $record->slug, 'tab' => 'learners'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                ->modalDescription('The course disappears from the website. Nothing is deleted: learners who bought it keep their receipts and certificates, and you can publish it again any time.')
                ->visible(fn (Course $record) => $record->status === 'published')
                ->action(fn (Course $record) => app(CourseDeletion::class)->unpublish($record, auth()->user())),
            Action::make('delete')->label('Delete')->icon('heroicon-o-trash')->color('danger')
                ->modalHeading(fn (Course $record) => 'Delete “'.$record->title.'”?')
                ->modalDescription(fn (Course $record) => app(CourseDeletion::class)->blocker($record)
                    ?? 'This permanently removes the course with its chapters, steps, quizzes, files and videos. It can’t be undone.')
                ->schema(fn (Course $record) => app(CourseDeletion::class)->blocker($record) ? [] : [
                    TextInput::make('confirmation')->label('Type the course title to confirm')->required()->placeholder($record->title)
                        ->in([$record->title])->validationMessages(['in' => 'That doesn’t match the course title.']),
                ])
                ->modalSubmitAction(fn ($action, Course $record) => app(CourseDeletion::class)->blocker($record) ? false : $action->label('Delete permanently'))
                ->action(function (Course $record, array $data): void {
                    app(CourseDeletion::class)->delete($record, auth()->user(), (string) ($data['confirmation'] ?? ''));
                    Notification::make()->title('Course deleted')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourses::route('/')];
    }
}
