<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Support\AcademySettings;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active' && ($user->staffCan('content') || ($user->role === 'instructor' && (bool) AcademySettings::get('instructor_portal_enabled')));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->viewAny($user) && ($user->staffCan('content') || $course->instructor_id === $user->id);
    }

    public function view(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->staffCan('content');
    }
}
