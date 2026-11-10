<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes a course. Content staff can delete courses nobody has enrolled in or ordered
 * (drafts, test courses); otherwise they can only unpublish. Super admins can delete any course:
 * its enrollments, progress, submitted work and certificates go with it, while orders and payments
 * stay in the books under the course title they were bought with.
 */
class CourseDeletion
{
    /** Why this course can't be deleted, or null when it can. */
    public function blocker(Course $course, ?User $user = null): ?string
    {
        if (($user ?? auth()->user())?->can('staff', 'super')) {
            return null;
        }
        $enrolled = Enrollment::where('course_id', $course->id)->count();
        if ($enrolled > 0) {
            return trans_choice(':count learner is enrolled, so this course can’t be deleted. Unpublish it instead to hide it.|:count learners are enrolled, so this course can’t be deleted. Unpublish it instead to hide it.', $enrolled, ['count' => $enrolled]);
        }
        if (OrderItem::where('course_id', $course->id)->exists()) {
            return __('This course has orders, so it can’t be deleted. Unpublish it instead to hide it.');
        }

        return null;
    }

    /** What a super admin's delete will also remove, shown before they confirm (null when nothing extra). */
    public function impact(Course $course): ?string
    {
        $enrolled = Enrollment::where('course_id', $course->id)->count();
        $orders = OrderItem::where('course_id', $course->id)->count();
        if (! $enrolled && ! $orders) {
            return null;
        }

        return __(':learners learner(s) will lose access, and their progress, submitted work and certificates for this course will be deleted. :orders order(s) stay in Finance under the course title. This can’t be undone.', ['learners' => $enrolled, 'orders' => $orders]);
    }

    public function delete(Course $course, User $admin, string $confirmation): void
    {
        abort_unless($admin->can('staff', 'content'), 403);
        if (trim($confirmation) !== $course->title) {
            throw ValidationException::withMessages(['confirmation' => __('Type the course title exactly to confirm.')]);
        }
        $files = [];
        $videoKeys = [];
        $submittedFiles = [];

        DB::transaction(function () use ($course, $admin, &$files, &$videoKeys, &$submittedFiles): void {
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($reason = $this->blocker($course, $admin)) {
                throw ValidationException::withMessages(['course' => $reason]);
            }
            $lessonIds = Lesson::whereIn('module_id', $course->modules()->pluck('id'))->pluck('id');

            // Super admin deleting a course people joined: remove their access and records for it.
            $enrollmentIds = Enrollment::where('course_id', $course->id)->pluck('id');
            $work = AssignmentSubmission::whereIn('enrollment_id', $enrollmentIds)->whereNull('file_deleted_at')->get(['disk', 'path']);
            $submittedFiles = $work->filter(fn ($w) => $w->path)->map(fn ($w) => [$w->disk ?: 'local', $w->path])->values()->all();
            Certificate::whereIn('enrollment_id', $enrollmentIds)->delete();
            Enrollment::whereIn('id', $enrollmentIds)->delete(); // progress, notes, attempts and submissions cascade
            // Unpaid orders for it can never be completed now; close them and any pending bank-transfer checks.
            $openOrders = Order::whereIn('id', OrderItem::where('course_id', $course->id)->pluck('order_id'))->whereIn('status', ['pending', 'awaiting_payment'])->pluck('id');
            Order::whereIn('id', $openOrders)->update(['status' => 'cancelled']);
            PaymentSubmission::whereIn('order_id', $openOrders)->where('status', 'pending')->update(['status' => 'rejected', 'rejection_reason' => 'The course was removed from the academy.']);

            // Lesson files and captions (stored on the server).
            $resources = LessonResource::whereIn('lesson_id', $lessonIds)->get(['id', 'object_key']);
            $files = $resources->pluck('object_key')->filter()->all();
            LessonResource::whereIn('id', $resources->pluck('id'))->delete();

            // Videos: detach from lessons, then remove the records (objects are removed from R2 after commit).
            Lesson::whereIn('id', $lessonIds)->update(['video_asset_id' => null]);
            $assets = VideoAsset::where('course_id', $course->id)->get(['id', 'object_key', 'upload_key']);
            $videoKeys = $assets->flatMap(fn ($a) => [$a->object_key, $a->upload_key])->filter()->all();
            VideoAsset::whereIn('id', $assets->pluck('id'))->delete();

            // Used coupons stay for the books, switched off (they'd otherwise become site-wide when the course goes).
            $used = CouponRedemption::whereIn('coupon_id', Coupon::where('course_id', $course->id)->pluck('id'))->pluck('coupon_id');
            Coupon::where('course_id', $course->id)->whereIn('id', $used)->update(['active' => false]);
            Coupon::where('course_id', $course->id)->whereNotIn('id', $used)->delete();
            if ($course->thumbnail_key && str_starts_with($course->thumbnail_key, 'thumbnails/')) {
                $files[] = $course->thumbnail_key;
            }
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'course.deleted', 'subject_type' => 'course', 'subject_id' => $course->id, 'changes' => ['title' => $course->title, 'slug' => $course->slug, 'lessons' => $lessonIds->count(), 'videos' => $assets->count(), 'enrollments_removed' => $enrollmentIds->count()]]);
            // Chapters, steps, quizzes, reviews, announcements and wishlist entries go with the course (cascade).
            $course->delete();
        });

        Storage::disk('local')->delete($files);
        foreach ($submittedFiles ?? [] as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
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
        abort_unless($admin->can('staff', 'content'), 403);
        $course->update(['status' => 'draft', 'published_at' => null]);
        AuditLog::create(['actor_id' => $admin->id, 'action' => 'course.unpublished', 'subject_type' => 'course', 'subject_id' => $course->id, 'changes' => ['title' => $course->title]]);
    }
}
