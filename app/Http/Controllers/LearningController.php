<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\NotificationPreference;
use App\Models\RecentlyViewed;
use App\Notifications\EngagementNotification;
use App\Services\CourseProgress;
use App\Services\Media\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LearningController extends Controller
{
    private function publishedLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->module->course_id === $course->id && $lesson->published && Course::published()->whereKey($course->id)->exists(), 404);
    }

    private function enrollment(Course $course): ?Enrollment
    {
        return auth()->check() ? Enrollment::active()->where('course_id', $course->id)->where('user_id', auth()->id())->first() : null;
    }

    public function enroll(Course $course): RedirectResponse
    {
        abort_unless(Course::published()->whereKey($course->id)->exists(), 404);
        abort_unless($course->is_free, 403, 'Paid enrollment requires a verified payment.');
        $createdEnrollment = DB::transaction(function () use ($course) {
            return Enrollment::firstOrCreate(['user_id' => auth()->id(), 'course_id' => $course->id], ['status' => 'active', 'source' => 'free', 'enrolled_at' => now()]);
        });
        abort_unless($this->enrollment($course), 403, 'This enrollment cannot be reactivated automatically.');
        if ($createdEnrollment->wasRecentlyCreated && NotificationPreference::where('user_id', auth()->id())->value('learning') !== false) {
            auth()->user()->notify(new EngagementNotification(['title' => 'Your course is ready', 'body' => $course->title, 'url' => route('learning.index')]));
        }

        return redirect()->route('learning.index')->with('status', 'You’re enrolled. Your next creative step is ready.');
    }

    public function index(): View
    {
        $enrollments = Enrollment::active()->where('user_id', auth()->id())->whereHas('course', fn ($query) => $query->published())->with(['course' => fn ($query) => $query->withCount(['lessons' => fn ($lessons) => $lessons->where('published', true)])->with('category')])->withCount(['progress as completed_lessons_count' => fn ($query) => $query->whereNotNull('completed_at')->whereHas('lesson', fn ($lesson) => $lesson->where('published', true))])->latest('enrolled_at')->paginate(12);

        return view('learning.index', compact('enrollments'));
    }

    public function show(Request $request, Course $course, Lesson $lesson, CourseProgress $steps): View|RedirectResponse
    {
        $this->publishedLesson($course, $lesson);
        if (! $lesson->is_preview && ! auth()->check()) {
            return redirect()->guest(route('login'));
        }
        if (! $lesson->is_preview && ! auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }
        $enrollment = $this->enrollment($course);
        abort_unless($lesson->is_preview || $enrollment, 403, 'Enroll in this course to access this lesson.');
        // Enrolled learners go step by step: a locked step sends them to the one they need to finish first.
        $unlockedIds = $enrollment ? $steps->unlockedIds($course, $enrollment) : [];
        if ($enrollment && ! in_array($lesson->id, $unlockedIds, true)) {
            $current = $steps->current($course, $enrollment);

            return redirect()->route('learning.show', [$course->slug, $current?->id ?? $lesson->id])->with('status', 'Finish this step first to unlock the next one.');
        }
        if ($enrollment) {
            RecentlyViewed::updateOrCreate(['user_id' => auth()->id(), 'lesson_id' => $lesson->id], ['viewed_at' => now()]);
        }

        // Text steps: pages are read in order, one at a time.
        $pages = $lesson->type === 'text' ? $lesson->textPages() : [];
        $page = 1;
        if ($pages) {
            $page = max(1, min(count($pages), (int) $request->query('page', 1)));
            if ($enrollment) {
                $record = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
                if ($page > $record->pages_viewed + 1) {
                    return redirect()->route('learning.show', [$course->slug, $lesson->id, 'page' => $record->pages_viewed + 1]);
                }
                // Reaching a page is recorded; the step finishes with "Finish & continue" on the last page.
                LessonProgress::whereKey($record->id)->where('pages_viewed', '<', $page)->update(['pages_viewed' => $page]);
            }
        }
        $course->load(['modules.lessons' => fn ($query) => $query->where('published', true)]);
        $lessons = $course->modules->flatMap->lessons;
        $position = $lessons->search(fn ($item) => $item->id === $lesson->id);
        $previous = $position > 0 ? $lessons[$position - 1] : null;
        $next = $position < $lessons->count() - 1 ? $lessons[$position + 1] : null;
        $progress = $enrollment?->progress()->where('lesson_id', $lesson->id)->first();
        $quiz = $lesson->type === 'quiz' ? $lesson->assessment : null;
        $attempts = $quiz && $enrollment ? AssessmentAttempt::where('assessment_id', $quiz->id)->where('enrollment_id', $enrollment->id)->latest('id')->get() : collect();
        $assignment = $lesson->type === 'assignment' ? $lesson->assessment : null;
        $submission = $assignment && $enrollment ? AssignmentSubmission::where('assessment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->latest('id')->first() : null;
        $completedIds = $enrollment?->progress()->whereNotNull('completed_at')->pluck('lesson_id')->all() ?? [];
        $note = $enrollment ? LessonNote::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first() : null;

        // Load the lesson's files once and decide playability once, instead of re-querying from the view.
        $files = $lesson->resources()->get()->groupBy('kind');
        $captions = $files->get('caption', collect());
        $downloads = $files->get('resource', collect());
        $videoPlayable = $lesson->videoAsset?->status === 'ready' && app(MediaStorage::class)->available();

        $stepDone = in_array($lesson->id, $completedIds, true);

        return view('learning.show', compact('course', 'lesson', 'enrollment', 'progress', 'previous', 'next', 'completedIds', 'note', 'captions', 'downloads', 'videoPlayable', 'unlockedIds', 'pages', 'page', 'quiz', 'attempts', 'assignment', 'submission', 'stepDone'));
    }

    /** Steps finish themselves (last text page, end of video, passed quiz, submitted work); this only re-checks. */
    public function complete(Course $course, Lesson $lesson, CourseProgress $steps): RedirectResponse
    {
        $this->publishedLesson($course, $lesson);
        $enrollment = $this->enrollment($course);
        abort_unless($enrollment && $steps->isUnlocked($course, $enrollment, $lesson), 403);
        $progress = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        $finished = match ($lesson->type) {
            'text' => $progress->pages_viewed >= count($lesson->textPages()),
            'video' => $lesson->duration_seconds > 0 && $progress->max_position_seconds >= $lesson->duration_seconds - CourseProgress::VIDEO_END_SLACK,
            default => (bool) $progress->completed_at,
        };
        if (! $finished) {
            return back()->withErrors(['lesson' => $lesson->type === 'video' ? 'Watch the whole video to unlock the next step.' : 'Finish this step to unlock the next one.']);
        }
        $steps->complete($enrollment, $lesson);

        return back()->with('status', 'Step complete. The next one is unlocked.');
    }

    /**
     * Video heartbeat. The furthest point watched can only grow at about real-time speed (no skipping ahead),
     * and reaching the end completes the step.
     */
    public function progress(Request $request, Course $course, Lesson $lesson, CourseProgress $steps): JsonResponse
    {
        $this->publishedLesson($course, $lesson);
        $enrollment = $this->enrollment($course);
        abort_unless($enrollment && $steps->isUnlocked($course, $enrollment, $lesson), 403);
        $data = $request->validate(['position_seconds' => ['required', 'integer', 'min:0', 'max:'.$lesson->duration_seconds]]);
        $completed = DB::transaction(function () use ($enrollment, $lesson, $data, $steps) {
            $created = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->wasRecentlyCreated;
            $progress = LessonProgress::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->lockForUpdate()->firstOrFail();
            $elapsed = $created ? 0 : max(0, (int) $progress->updated_at?->diffInSeconds(now(), true));
            // Real playback plus a little slack for network delay; seeking back never lowers the furthest point.
            $reachable = $progress->max_position_seconds + (int) ceil($elapsed * 1.25) + 15;
            $position = min($data['position_seconds'], $reachable);
            $furthest = max($progress->max_position_seconds, $position);
            LessonProgress::whereKey($progress->id)->update(['position_seconds' => $position, 'max_position_seconds' => $furthest, 'updated_at' => now()]);
            if ($lesson->type === 'video' && $lesson->duration_seconds > 0 && $furthest >= $lesson->duration_seconds - CourseProgress::VIDEO_END_SLACK) {
                $steps->complete($enrollment, $lesson);

                return true;
            }

            return (bool) $progress->completed_at;
        });

        return response()->json(['saved' => true, 'completed' => $completed]);
    }

    public function notes(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $this->publishedLesson($course, $lesson);
        $enrollment = $this->enrollment($course);
        abort_unless($enrollment, 403);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:10000'], 'bookmarked' => ['sometimes', 'boolean']]);
        LessonNote::updateOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], ['body' => $data['body'] ?? '', 'bookmarked' => $request->boolean('bookmarked')]);

        return back()->with('status', 'Your private notes have been saved.');
    }
}
