<?php

namespace App\Http\Middleware;

use App\Support\AcademySettings;
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
        // Admins build courses with the course builder, but their "home" is the admin panel.
        if ($user->role === 'admin' && $request->routeIs('instructor.dashboard')) {
            return $this->home($request, url('/admin/courses'));
        }

        return $next($request);
    }

    private function home(Request $request, string $url): Response
    {
        return $request->expectsJson() ? response()->json(['message' => 'This page belongs to another workspace.'], 403) : redirect()->to($url);
    }
}
