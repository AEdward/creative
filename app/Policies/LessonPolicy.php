<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function view(?User $user, Lesson $lesson): bool
    {
        $course = $lesson->module->course;
        if (! $lesson->published || ! Course::published()->whereKey($course->id)->exists()) {
            return false;
        }

        return $lesson->is_preview || ($user && $user->status === 'active' && $user->hasVerifiedEmail() && Enrollment::active()->where('course_id', $course->id)->where('user_id', $user->id)->exists());
    }
}
