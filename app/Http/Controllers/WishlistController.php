<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(): View
    {
        return view('wishlist', ['items' => Wishlist::where('user_id', auth()->id())->whereHas('course', fn ($q) => $q->published())->with('course.category', 'course.instructor')->latest()->paginate(12)]);
    }

    public function store(Course $course): RedirectResponse
    {
        abort_unless(Course::published()->whereKey($course->id)->exists(), 404);
        Wishlist::firstOrCreate(['user_id' => auth()->id(), 'course_id' => $course->id]);

        return redirect()->route('wishlist')->with('status', 'Course saved to your wishlist.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        Wishlist::where('user_id', auth()->id())->where('course_id', $course->id)->delete();

        return redirect()->route('wishlist')->with('status', 'Course removed from your wishlist.');
    }
}
