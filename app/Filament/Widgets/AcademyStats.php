<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AcademyStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [Stat::make('Student accounts', User::where('role', 'student')->count())->description('Registered student accounts'), Stat::make('Published courses', Course::published()->count())->description('Currently visible in the catalog'), Stat::make('Active enrollments', Enrollment::active()->count())->description('Active, unexpired course enrollments')];
    }
}
