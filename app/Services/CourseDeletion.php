<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes a course nobody has enrolled in or ordered (drafts, test courses).
 * Courses with learners or orders can only be unpublished, so receipts and certificates stay intact.
 */
class CourseDeletion
{
    /** Why this course can't be deleted, or null when it can. */
    public function blocker(Course $course): ?string
    {
        $enrolled = Enrollment::where('course_id', $course->id)->count();
        if ($enrolled > 0) {
            return trans_choice(':count learner is enrolled, so this course can’t be deleted. Unpublish it instead to hide it.|:count learners are enrolled, so this course can’t be deleted. Unpublish it instead to hide it.', $enrolled, ['count' => $enrolled]);
        }
        if (OrderItem::where('course_id', $course->id)->exists()) {
            return __('This course has orders, so it can’t be deleted. Unpublish it instead to hide it.');
        }

        return null;
    }

    public function delete(Course $course, User $admin, string $confirmation): void
    {
        abort_unless($admin->can('administer-academy'), 403);
        if (trim($confirmation) !== $course->title) {
            throw ValidationException::withMessages(['confirmation' => __('Type the course title exactly to confirm.')]);
        }
        $files = [];
        $videoKeys = [];

        DB::transaction(function () use ($course, $admin, &$files, &$videoKeys): void {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($reason = $this->blocker($course)) {
                throw ValidationException::withMessages(['course' => $reason]);
            }
            $lessonIds = Lesson::whereIn('module_id', $course->modules()->pluck('id'))->pluck('id');

            // Lesson files and captions (stored on the server).
            $resources = LessonResource::whereIn('lesson_id', $lessonIds)->get(['id', 'object_key']);
            $files = $resources->pluck('object_key')->filter()->all();
            LessonResource::whereIn('id', $resources->pluck('id'))->delete();

            // Videos: detach from lessons, then remove the records (objects are removed from R2 after commit).
            Lesson::whereIn('id', $lessonIds)->update(['video_asset_id' => null]);
            $assets = VideoAsset::where('course_id', $course->id)->get(['id', 'object_key', 'upload_key']);
            $videoKeys = $assets->flatMap(fn ($a) => [$a->object_key, $a->upload_key])->filter()->all();
            VideoAsset::whereIn('id', $assets->pluck('id'))->delete();

            Coupon::where('course_id', $course->id)->delete();
            if ($course->thumbnail_key && str_starts_with($course->thumbnail_key, 'thumbnails/')) {
                $files[] = $course->thumbnail_key;
            }
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'course.deleted', 'subject_type' => 'course', 'subject_id' => $course->id, 'changes' => ['title' => $course->title, 'slug' => $course->slug, 'lessons' => $lessonIds->count(), 'videos' => $assets->count()]]);
            // Chapters, steps, quizzes, reviews, announcements and wishlist entries go with the course (cascade).
            $course->delete();
        });

        Storage::disk('local')->delete($files);
        $storage = app(MediaStorage::class);
        if ($videoKeys && $storage->available()) {
            foreach ($videoKeys as $key) {
                try {
                    $storage->delete($key);
                } catch (\Throwable $e) {
                    report($e); // an orphaned object costs storage, not correctness; the hourly cleanup can't see it, so log it
                }
            }
        }
    }

    /** Hide a course from the website without losing anything (works for courses people bought). */
    public function unpublish(Course $course, User $admin): void
    {
        abort_unless($admin->can('administer-academy'), 403);
        $course->update(['status' => 'draft', 'published_at' => null]);
        AuditLog::create(['actor_id' => $admin->id, 'action' => 'course.unpublished', 'subject_type' => 'course', 'subject_id' => $course->id, 'changes' => ['title' => $course->title]]);
    }
}
