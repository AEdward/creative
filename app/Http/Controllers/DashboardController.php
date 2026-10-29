<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\RecentlyViewed;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $recent = RecentlyViewed::where('user_id', $request->user()->id)->whereHas('lesson', fn ($q) => $q->where('published', true)->whereHas('module.course', fn ($c) => $c->published()->whereHas('enrollments', fn ($e) => $e->active()->where('user_id', $request->user()->id))))->with('lesson.module.course')->latest('viewed_at')->limit(6)->get();
        $enrolledCount = Enrollment::active()->where('user_id', $request->user()->id)->whereHas('course', fn ($c) => $c->published())->count();

        return view('dashboard', compact('recent', 'enrolledCount'));
    }
}
