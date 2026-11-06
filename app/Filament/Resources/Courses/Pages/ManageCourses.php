<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Pages\CourseStudio;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageCourses extends ManageRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCourse')->label('New course')->icon('heroicon-o-plus')->url(CourseStudio::getUrl()),
        ];
    }
}
