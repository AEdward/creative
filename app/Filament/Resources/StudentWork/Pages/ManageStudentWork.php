<?php

namespace App\Filament\Resources\StudentWork\Pages;

use App\Filament\Resources\StudentWork\StudentWorkResource;
use App\Models\AssignmentSubmission;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Number;

class ManageStudentWork extends ManageRecords
{
    protected static string $resource = StudentWorkResource::class;

    public function getSubheading(): ?string
    {
        $stored = AssignmentSubmission::whereNull('file_deleted_at')->sum('bytes');

        return __('Files stored: :size', ['size' => Number::fileSize((int) $stored, 1)]);
    }

    protected function getHeaderActions(): array
    {
        return [StudentWorkResource::cleanupAction()];
    }
}
