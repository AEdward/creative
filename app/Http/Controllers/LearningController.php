<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\NotificationPreference;
use App\Models\RecentlyViewed;
use App\Notifications\EngagementNotification;
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

    public function show(Course $course, Lesson $lesson): View|RedirectResponse
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
        if ($enrollment) {
            RecentlyViewed::updateOrCreate(['user_id' => auth()->id(), 'lesson_id' => $lesson->id], ['viewed_at' => now()]);
        }
        $course->load(['modules.lessons' => fn ($query) => $query->where('published', true)]);
        $lessons = $course->modules->flatMap->lessons;
        $position = $lessons->search(fn ($item) => $item->id === $lesson->id);
        $previous = $position > 0 ? $lessons[$position - 1] : null;
        $next = $position < $lessons->count() - 1 ? $lessons[$position + 1] : null;
        $progress = $enrollment?->progress()->where('lesson_id', $lesson->id)->first();
        $completedIds = $enrollment?->progress()->whereNotNull('completed_at')->pluck('lesson_id')->all() ?? [];
        $note = $enrollment ? LessonNote::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first() : null;

        return view('learning.show', compact('course', 'lesson', 'enrollment', 'progress', 'previous', 'next', 'completedIds', 'note'));
    }

    public function complete(Course $course, Lesson $lesson): RedirectResponse
    {
        $this->publishedLesson($course, $lesson);
        $enrollment = $this->enrollment($course);
        abort_unless($enrollment, 403);
        abort_unless($lesson->type === 'text' || ($lesson->type === 'video' && $lesson->videoAsset?->status === 'ready' && $lesson->videoAsset->course_id === $course->id && app(MediaStorage::class)->available()), 422, 'Video completion requires an attached playable asset.');
        $progress = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        // Videos count as complete only once most of them has been watched.
        if ($lesson->type === 'video' && $lesson->duration_seconds > 0 && ! $progress->completed_at && $progress->position_seconds < (int) floor($lesson->duration_seconds * 0.9)) {
            return back()->withErrors(['lesson' => 'Watch the video to the end before marking it complete.']);
        }
        LessonProgress::whereKey($progress->id)->whereNull('completed_at')->update(['completed_at' => now()]);

        return back()->with('status', 'Lesson completed. Keep creating.');
    }

    public function progress(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->publishedLesson($course, $lesson);
        $enrollment = $this->enrollment($course);
        abort_unless($enrollment, 403);
        $data = $request->validate(['position_seconds' => ['required', 'integer', 'min:0', 'max:'.$lesson->duration_seconds]]);
        DB::transaction(function () use ($enrollment, $lesson, $data) {
            $created = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->wasRecentlyCreated;
            $progress = LessonProgress::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->lockForUpdate()->firstOrFail();
            // Watched time can only grow about as fast as real time (2x playback plus slack); seeking back is always allowed.
            $elapsed = $created ? 0 : max(0, (int) $progress->updated_at?->diffInSeconds(now(), true));
            $position = min($data['position_seconds'], $progress->position_seconds + $elapsed * 2 + 20);
            LessonProgress::whereKey($progress->id)->update(['position_seconds' => $position, 'updated_at' => now()]);
        });

        return response()->json(['saved' => true]);
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
