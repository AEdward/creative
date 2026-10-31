<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Order;
use App\Models\User;
use App\Models\VideoAsset;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getHeading(): string
    {
        return '';
    }

    protected function getViewData(): array
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $students = User::where('role', 'student');
        $paid = Order::where('status', 'paid');

        $stats = [
            ['Total Students', number_format((clone $students)->count()), $this->change(clone $students, $thisMonth, $lastMonth), 'heroicon-o-user-group', 'violet'],
            ['Total Courses', number_format(Course::count()), $this->change(Course::query(), $thisMonth, $lastMonth), 'heroicon-o-academic-cap', 'blue'],
            ['Total Lessons', number_format(Lesson::count()), $this->change(Lesson::query(), $thisMonth, $lastMonth), 'heroicon-o-play', 'green'],
            ['Total Revenue', 'ETB '.number_format(intdiv((int) (clone $paid)->sum('total_minor'), 100)), $this->change(clone $paid, $thisMonth, $lastMonth, 'total_minor'), 'heroicon-o-banknotes', 'orange'],
        ];

        // Students registered up to the end of each of the last six months.
        $growth = collect(range(5, 0))->map(function (int $ago) {
            $end = now()->subMonthsNoOverflow($ago)->endOfMonth();

            return ['label' => $end->translatedFormat('M'), 'value' => User::where('role', 'student')->where('created_at', '<=', $end)->count()];
        });

        $byCategory = Category::where('active', true)->orderBy('sort_order')->get()->map(fn (Category $category) => [
            'label' => $category->name,
            'value' => Enrollment::where('enrolled_at', '>=', now()->subDays(30))->whereHas('course', fn ($q) => $q->where('category_id', $category->id))->count(),
        ]);

        $videoBytes = (int) VideoAsset::where('status', 'ready')->sum('expected_bytes');
        $fileBytes = (int) LessonResource::sum('bytes');
        $quota = (float) config('academy.storage_quota_gb', 10) * 1024 ** 3;

        return [
            'stats' => $stats,
            'growth' => $growth,
            'byCategory' => $byCategory,
            'storage' => ['videos' => $videoBytes, 'files' => $fileBytes, 'used' => $videoBytes + $fileBytes, 'quota' => $quota],
            'recentCourses' => Course::with(['instructor', 'category'])->withCount(['lessons', 'enrollments'])->latest()->limit(5)->get(),
            'recentStudents' => User::where('role', 'student')->latest()->limit(4)->get(),
            'activity' => $this->activity(),
        ];
    }

    /** Month-over-month change in records created (or summed column), or null when last month had nothing to compare. */
    private function change($query, Carbon $thisMonth, Carbon $lastMonth, ?string $sum = null): ?array
    {
        $current = (clone $query)->where('created_at', '>=', $thisMonth);
        $previous = (clone $query)->whereBetween('created_at', [$lastMonth, $thisMonth]);
        $now = $sum ? (int) $current->sum($sum) : $current->count();
        $before = $sum ? (int) $previous->sum($sum) : $previous->count();
        if ($before === 0) {
            return $now > 0 ? ['text' => 'New this month', 'up' => true] : null;
        }
        $percent = (int) round(($now - $before) / $before * 100);

        return ['text' => abs($percent).'% from last month', 'up' => $percent >= 0];
    }

    private function activity(): Collection
    {
        $enrollments = Enrollment::with(['user', 'course'])->latest('enrolled_at')->limit(4)->get()
            ->map(fn ($e) => ['icon' => 'heroicon-o-user-plus', 'tone' => 'violet', 'title' => 'New enrollment', 'text' => ($e->user?->name ?? 'A learner').' enrolled in '.($e->course?->title ?? 'a course'), 'at' => $e->enrolled_at ?? $e->created_at]);
        $students = User::where('role', 'student')->latest()->limit(4)->get()
            ->map(fn ($u) => ['icon' => 'heroicon-o-user', 'tone' => 'blue', 'title' => 'New student registered', 'text' => $u->name.' created an account', 'at' => $u->created_at]);
        $published = Course::whereNotNull('published_at')->latest('published_at')->limit(4)->get()
            ->map(fn ($c) => ['icon' => 'heroicon-o-rocket-launch', 'tone' => 'green', 'title' => 'Course published', 'text' => $c->title, 'at' => $c->published_at]);

        return $enrollments->concat($students)->concat($published)->filter(fn ($item) => $item['at'])->sortByDesc('at')->take(4)->values();
    }
}
