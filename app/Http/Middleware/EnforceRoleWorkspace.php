<?php

namespace App\Http\Middleware;

use App\Filament\Pages\CourseStudio;
use App\Support\AcademySettings;
use App\Support\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins, instructors and students each have their own workspace and don't cross into each other's:
 * student-only pages send other roles back to their own home, the instructor workspace home is for
 * instructors, and the public instructor directory is closed while the instructor workspace is off.
 */
class EnforceRoleWorkspace
{
    /** Pages that only make sense for a learner (their purchases, progress, wishlist, support tickets). */
    private const STUDENT_ONLY = [
        'dashboard', 'learning.index', 'certificates.index', 'wishlist', 'wishlist.*', 'payments', 'payments.refund',
        // support.show / reply stay open: admins answer tickets from Admin -> Support queue.
        'support.index', 'support.store', 'checkout.*', 'assessments.index', 'announcements.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('instructors.*') && ! AcademySettings::get('instructor_portal_enabled')) {
            abort(404);
        }

        $user = $request->user();
        if (! $user || ! $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($user->role !== 'student' && $request->routeIs(self::STUDENT_ONLY)) {
            return $this->home($request, $user->homeUrl());
        }
        // Admins build courses in the admin panel's Course studio, never on the instructor screens.
        if ($user->role === 'admin' && $request->isMethod('GET') && ($url = $this->studioUrl($request))) {
            return $this->home($request, $url);
        }

        return $next($request);
    }

    private function studioUrl(Request $request): ?string
    {
        if ($request->routeIs('instructor.dashboard')) {
            return Admin::url('courses');
        }
        if ($request->routeIs('instructor.courses.create')) {
            return CourseStudio::getUrl();
        }
        $tab = ['instructor.courses.edit' => 'build', 'instructor.materials' => 'files', 'instructor.assessments' => 'quizzes', 'instructor.announcements' => 'announcements', 'instructor.learners' => 'learners'];
        foreach ($tab as $route => $name) {
            if ($request->routeIs($route)) {
                $course = $request->route('course');
                $slug = is_object($course) ? $course->slug : (string) $course;

                return CourseStudio::getUrl(['course' => $slug, 'tab' => $name]);
            }
        }

        return null;
    }

    private function home(Request $request, string $url): Response
    {
        return $request->expectsJson() ? response()->json(['message' => 'This page belongs to another workspace.'], 403) : redirect()->to($url);
    }
}
