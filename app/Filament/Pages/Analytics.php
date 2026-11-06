<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Learning analytics for a date range (Africa/Nairobi days, inclusive). */
class Analytics extends Page
{
    protected string $view = 'filament.pages.analytics';

    protected static ?string $title = 'Analytics';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->from = now('Africa/Nairobi')->subDays(29)->toDateString();
        $this->to = now('Africa/Nairobi')->toDateString();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds of the chosen local days */
    private function range(): array
    {
        // Validate without throwing: a half-typed date shows an error and the report keeps the last 30 days.
        $check = Validator::make(['from' => $this->from, 'to' => $this->to], ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $this->resetErrorBag();
        if ($check->fails()) {
            foreach ($check->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            [$from, $to] = [now('Africa/Nairobi')->subDays(29)->toDateString(), now('Africa/Nairobi')->toDateString()];
        } else {
            [$from, $to] = [$this->from, $this->to];
        }
        $start = CarbonImmutable::parse($from, 'Africa/Nairobi')->startOfDay();
        $end = CarbonImmutable::parse($to, 'Africa/Nairobi')->endOfDay();
        if ($start->diffInDays($end) > 366) {
            $start = $end->subDays(366)->startOfDay();
        }

        return [$start->utc(), $end->utc()];
    }

    public function preset(int $days): void
    {
        $this->from = now('Africa/Nairobi')->subDays($days - 1)->toDateString();
        $this->to = now('Africa/Nairobi')->toDateString();
    }

    protected function getViewData(): array
    {
        [$start, $end] = $this->range();
        $between = [$start, $end];

        $completions = LessonProgress::whereBetween('completed_at', $between);
        $metrics = [
            ['New students', User::where('role', 'student')->whereBetween('created_at', $between)->count(), 'heroicon-o-user-plus'],
            ['New enrollments', Enrollment::whereBetween('enrolled_at', $between)->count(), 'heroicon-o-academic-cap'],
            ['Active learners', LessonProgress::whereBetween('lesson_progress.updated_at', $between)->join('enrollments', 'enrollments.id', '=', 'lesson_progress.enrollment_id')->distinct()->count('enrollments.user_id'), 'heroicon-o-bolt'],
            ['Steps completed', (clone $completions)->count(), 'heroicon-o-check-badge'],
            ['Courses finished', DB::table('certificates')->whereBetween('issued_at', $between)->count(), 'heroicon-o-trophy'],
            ['Verified revenue', 'ETB '.number_format(Payment::where('currency', 'ETB')->where('status', 'verified_success')->whereBetween('verified_at', $between)->sum('amount_minor') / 100, 2), 'heroicon-o-banknotes'],
        ];

        // One point per local day: enrollments and learners who made progress.
        $days = [];
        for ($day = $start->setTimezone('Africa/Nairobi')->startOfDay(); $day <= $end->setTimezone('Africa/Nairobi'); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['enrollments' => 0, 'active' => 0];
        }
        foreach (Enrollment::whereBetween('enrolled_at', $between)->pluck('enrolled_at') as $at) {
            $days[$at->timezone('Africa/Nairobi')->toDateString()]['enrollments']++;
        }
        $active = LessonProgress::whereBetween('lesson_progress.updated_at', $between)->join('enrollments', 'enrollments.id', '=', 'lesson_progress.enrollment_id')
            ->get(['lesson_progress.updated_at', 'enrollments.user_id']);
        $seen = [];
        foreach ($active as $row) {
            $key = CarbonImmutable::parse($row->updated_at)->timezone('Africa/Nairobi')->toDateString();
            if (isset($days[$key]) && ! isset($seen[$key][$row->user_id])) {
                $seen[$key][$row->user_id] = true;
                $days[$key]['active']++;
            }
        }

        // Courses: enrollments in range, learners overall, and how many finished every step.
        $courses = Course::query()->withCount(['enrollments as period_enrollments' => fn ($q) => $q->whereBetween('enrolled_at', $between), 'enrollments as learners' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('period_enrollments')->orderByDesc('learners')->limit(10)->get(['id', 'title']);
        $finished = DB::table('certificates')->join('enrollments', 'enrollments.id', '=', 'certificates.enrollment_id')
            ->whereIn('enrollments.course_id', $courses->pluck('id'))->whereNull('certificates.revoked_at')
            ->selectRaw('enrollments.course_id as course_id, COUNT(*) as total')->groupBy('enrollments.course_id')->pluck('total', 'course_id');

        return ['metrics' => $metrics, 'days' => $days, 'courses' => $courses, 'finished' => $finished];
    }
}
