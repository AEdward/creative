<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function home(): View
    {
        $published = fn (Builder $course) => $course->published();
        $reviews = CourseReview::where('status', 'approved')->whereHas('course', $published);

        return view('home', [
            'courses' => Course::published()->with(['instructor', 'category'])->latest('published_at')->limit(3)->get(),
            'categories' => Category::where('active', true)->withCount(['courses' => $published])->orderBy('sort_order')->orderBy('id')->get(),
            'stats' => [
                'students' => User::where('role', 'student')->where('status', 'active')->count(),
                'lessons' => Lesson::where('published', true)->whereHas('module.course', $published)->count(),
                'courses' => Course::published()->count(),
                'rating' => $reviews->exists() ? round((float) $reviews->avg('rating'), 1) : null,
            ],
        ]);
    }

    public function index(Request $request, ?Category $category = null): View
    {
        abort_if($category && ! $category->active, 404);
        $validator = Validator::make($request->all(), ['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'alpha_dash', 'max:100'], 'level' => ['nullable', 'in:beginner,intermediate,advanced'], 'language' => ['nullable', 'in:en,am'], 'access' => ['nullable', 'in:free,paid'], 'sort' => ['nullable', 'in:newest,title,price-asc,price-desc'], 'page' => ['nullable', 'integer', 'min:1']]);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('courses.index'));
        }
        $filters = $validator->validated();
        if ($category) {
            $filters['category'] = $category->slug;
        }
        $query = Course::published()->with(['instructor', 'category']);
        if (! empty($filters['q'])) {
            $query->where(fn (Builder $search) => $search->where('title', 'like', '%'.$filters['q'].'%')->orWhere('summary', 'like', '%'.$filters['q'].'%'));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $filters['category']));
        }
        foreach (['level', 'language'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if (! empty($filters['access'])) {
            $query->where('is_free', $filters['access'] === 'free');
        }
        match ($filters['sort'] ?? 'newest') {
            'title' => $query->orderBy('title'), 'price-asc' => $query->orderBy('price_minor'), 'price-desc' => $query->orderByDesc('price_minor'), default => $query->orderByDesc('published_at')
        };

        return view('courses.index', ['courses' => $query->orderBy('id')->paginate(12)->withQueryString(), 'categories' => Category::where('active', true)->orderBy('sort_order')->get(), 'filters' => $filters, 'category' => $category]);
    }

    public function show(string $slug): View
    {
        $course = Course::published()->where('slug', $slug)->with(['instructor', 'category', 'modules.lessons' => fn (HasMany $query) => $query->where('published', true)])->firstOrFail();

        $enrollment = auth()->check() ? Enrollment::active()->where('course_id', $course->id)->where('user_id', auth()->id())->first() : null;

        return view('courses.show', compact('course', 'enrollment'));
    }
}
