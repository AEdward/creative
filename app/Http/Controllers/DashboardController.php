<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\RecentlyViewed;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $enrollments = Enrollment::active()->where('user_id', $user->id)->whereHas('course', fn ($c) => $c->published())
            ->with(['course' => fn ($q) => $q->with(['instructor', 'category'])->withCount(['lessons' => fn ($l) => $l->where('lessons.published', true)])])
            ->withCount(['progress as completed_lessons_count' => fn ($q) => $q->whereNotNull('completed_at')->whereHas('lesson', fn ($l) => $l->where('published', true))])
            ->latest('enrolled_at')->get()
            ->each(function (Enrollment $enrollment) {
                $total = (int) $enrollment->course->lessons_count;
                $enrollment->percent = $total ? (int) min(100, round($enrollment->completed_lessons_count / $total * 100)) : 0;
            });
        $ids = $enrollments->pluck('id');

        // Learning time: full length of completed lessons plus the watched part of unfinished videos.
        $seconds = (int) LessonProgress::whereIn('enrollment_id', $ids)->with('lesson:id,duration_seconds')->get()
            ->sum(fn (LessonProgress $p) => $p->completed_at ? (int) $p->lesson?->duration_seconds : min((int) $p->position_seconds, (int) $p->lesson?->duration_seconds));

        $certificates = Certificate::whereIn('enrollment_id', $ids)->whereNull('revoked_at');

        return view('dashboard', [
            'user' => $user,
            'enrollments' => $enrollments,
            'stats' => [
                'enrolled' => $enrollments->count(),
                'inProgress' => $enrollments->filter(fn ($e) => $e->percent > 0 && $e->percent < 100)->count(),
                'completed' => $enrollments->where('percent', 100)->count(),
                'hours' => round($seconds / 3600, 1),
            ],
            'certificateCount' => (clone $certificates)->count(),
            'latestCertificate' => (clone $certificates)->latest('issued_at')->first(),
            'activity' => $this->activity($ids, $user->id),
        ]);
    }

    private function activity(Collection $enrollmentIds, int $userId): Collection
    {
        // Lessons opened recently (still enrolled, still published), so learners can jump back in.
        $viewed = RecentlyViewed::where('user_id', $userId)->whereHas('lesson', fn ($q) => $q->where('published', true)->whereHas('module.course', fn ($c) => $c->published()->whereHas('enrollments', fn ($e) => $e->active()->where('user_id', $userId))))
            ->with('lesson.module.course')->latest('viewed_at')->limit(5)->get()
            ->map(fn ($v) => ['icon' => 'heroicon-o-eye', 'tone' => 'blue', 'title' => __('Opened lesson: :lesson', ['lesson' => $v->lesson->title]), 'text' => $v->lesson->module->course->title, 'at' => $v->viewed_at, 'url' => route('learning.show', [$v->lesson->module->course->slug, $v->lesson_id])]);
        $lessons = LessonProgress::whereIn('enrollment_id', $enrollmentIds)->whereNotNull('completed_at')->with('lesson.module.course')->latest('completed_at')->limit(5)->get()
            ->map(fn ($p) => ['icon' => 'heroicon-o-play', 'tone' => 'violet', 'title' => __('Completed lesson: :lesson', ['lesson' => $p->lesson?->title]), 'text' => $p->lesson?->module?->course?->title, 'at' => $p->completed_at]);
        $quizzes = AssessmentAttempt::whereIn('enrollment_id', $enrollmentIds)->with('assessment', 'enrollment.course')->latest()->limit(5)->get()
            ->map(fn ($a) => ['icon' => 'heroicon-o-check-circle', 'tone' => $a->passed ? 'green' : 'orange', 'title' => __($a->passed ? 'Passed quiz: :quiz' : 'Attempted quiz: :quiz', ['quiz' => $a->assessment?->title]), 'text' => $a->enrollment?->course?->title, 'at' => $a->created_at]);
        $joined = Enrollment::whereIn('id', $enrollmentIds)->with('course')->latest('enrolled_at')->limit(5)->get()
            ->map(fn ($e) => ['icon' => 'heroicon-o-book-open', 'tone' => 'blue', 'title' => __('Enrolled in :course', ['course' => $e->course?->title]), 'text' => null, 'at' => $e->enrolled_at ?? $e->created_at]);
        $earned = Certificate::whereIn('enrollment_id', $enrollmentIds)->latest('issued_at')->limit(5)->get()
            ->map(fn ($c) => ['icon' => 'heroicon-o-trophy', 'tone' => 'orange', 'title' => __('Earned certificate: :course', ['course' => $c->course_title]), 'text' => null, 'at' => $c->issued_at]);

        return $viewed->concat($lessons)->concat($quizzes)->concat($joined)->concat($earned)->filter(fn ($i) => $i['at'])->sortByDesc('at')->take(5)->values();
    }
}
