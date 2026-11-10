<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\InstructorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstructorProfileController extends Controller
{
    public function index(Request $request): View
    {
        $search = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 120) : '';
        $rawPage = $request->query('page');
        $page = is_string($rawPage) && ctype_digit($rawPage) ? min(max((int) $rawPage, 1), 10000) : 1;
        $profiles = InstructorProfile::publiclyVisible()->with('user:id,name,avatar_key')->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $matching) use ($search): void {
                $matching->where('professional_title', 'like', '%'.$search.'%')->orWhere('specialties', 'like', '%'.$search.'%')->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$search.'%'));
            });
        })->orderBy('id')->paginate(12, ['*'], 'page', $page)->appends(['q' => $search]);

        return view('instructors.index', compact('profiles', 'search'));
    }

    public function show(InstructorProfile $instructorProfile): View
    {
        $this->ensureVisible($instructorProfile);
        $courses = Course::published()->where('instructor_id', $instructorProfile->user_id)->with(['category', 'instructor'])->orderByDesc('published_at')->paginate(12);

        return view('instructors.show', ['profile' => $instructorProfile->load('user:id,name,avatar_key'), 'courses' => $courses]);
    }

    public function photo(InstructorProfile $instructorProfile): BinaryFileResponse
    {
        $this->ensureVisible($instructorProfile);
        $key = $instructorProfile->user->avatar_key;
        abort_unless($instructorProfile->show_photo && is_string($key) && preg_match('~\Aavatars/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z~', $key) && Storage::disk('local')->exists($key), 404);

        return response()->file(Storage::disk('local')->path($key), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private'])->setPrivate();
    }

    public function edit(Request $request): View
    {
        return view('instructors.edit', ['profile' => InstructorProfile::where('user_id', $request->user()->id)->first() ?? new InstructorProfile]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['professional_title' => ['nullable', 'string', 'max:120'], 'biography' => ['nullable', 'string', 'max:5000'], 'specialties' => ['nullable', 'string', 'max:1000'], 'publicly_listed' => ['sometimes', 'boolean'], 'show_photo' => ['sometimes', 'boolean']]);
        $data['publicly_listed'] = $request->boolean('publicly_listed');
        $data['show_photo'] = $data['publicly_listed'] && $request->boolean('show_photo');
        InstructorProfile::updateOrCreate(['user_id' => $request->user()->id], $data);

        return redirect()->route('instructor.profile.edit')->with('status', 'Your instructor profile has been saved.');
    }

    private function ensureVisible(InstructorProfile $profile): void
    {
        abort_unless(InstructorProfile::publiclyVisible()->whereKey($profile->id)->exists(), 404);
    }
}
