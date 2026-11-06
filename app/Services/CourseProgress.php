<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;

/**
 * Locked, step-by-step courses. Steps are the published lessons in chapter order then step order;
 * a step is open when every step before it is complete. "Complete" is always lesson_progress.completed_at,
 * which each step type sets by its own rule (last text page reached, video watched to the end,
 * quiz passed, assignment submitted or accepted).
 */
class CourseProgress
{
    /** Seconds from the end that still count as "watched the full video" (credits, player rounding). */
    public const VIDEO_END_SLACK = 3;

    /** @return Collection<int, Lesson> */
    public function steps(Course $course): Collection
    {
        return Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('modules.course_id', $course->id)->where('lessons.published', true)
            ->orderBy('modules.sort_order')->orderBy('modules.id')->orderBy('lessons.sort_order')->orderBy('lessons.id')
            ->select('lessons.*')->get();
    }

    /** @return list<int> ids of completed steps */
    public function completedIds(Enrollment $enrollment): array
    {
        return LessonProgress::where('enrollment_id', $enrollment->id)->whereNotNull('completed_at')->pluck('lesson_id')->all();
    }

    /** The first step the learner hasn't finished (null when the whole course is done). */
    public function current(Course $course, Enrollment $enrollment): ?Lesson
    {
        $done = array_flip($this->completedIds($enrollment));

        return $this->steps($course)->first(fn (Lesson $step) => ! isset($done[$step->id]));
    }

    /** Ids of steps the learner may open: everything completed plus the first unfinished one. */
    public function unlockedIds(Course $course, Enrollment $enrollment): array
    {
        $done = array_flip($this->completedIds($enrollment));
        $open = [];
        foreach ($this->steps($course) as $step) {
            $open[] = $step->id;
            if (! isset($done[$step->id])) {
                break;
            }
        }

        return $open;
    }

    public function isUnlocked(Course $course, Enrollment $enrollment, Lesson $lesson): bool
    {
        return in_array($lesson->id, $this->unlockedIds($course, $enrollment), true);
    }

    /** Mark a step finished (idempotent: the first completion time is kept). */
    public function complete(Enrollment $enrollment, Lesson $lesson): void
    {
        $progress = LessonProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        LessonProgress::whereKey($progress->id)->whereNull('completed_at')->update(['completed_at' => now()]);
    }
}
