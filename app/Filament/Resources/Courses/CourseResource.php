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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable()->sortable(), TextColumn::make('instructor.name'), TextColumn::make('category.name'), TextColumn::make('status')->badge(), TextColumn::make('published_at')->dateTime()])->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])])->recordActions([Action::make('review')->label('Review course')->url(fn (Course $record) => route('instructor.courses.edit', $record->slug))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourses::route('/')];
    }
}
