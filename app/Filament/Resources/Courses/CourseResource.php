<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages\ManageCourses;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseResource extends Resource
{
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
            Action::make('edit')->label('Edit & lessons')->icon('heroicon-o-pencil-square')->url(fn (Course $record) => route('instructor.courses.edit', $record->slug)),
            Action::make('materials')->label('Files')->icon('heroicon-o-paper-clip')->url(fn (Course $record) => route('instructor.materials', $record->slug)),
            Action::make('assessments')->label('Quizzes')->icon('heroicon-o-academic-cap')->url(fn (Course $record) => route('instructor.assessments', $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourses::route('/')];
    }
}
