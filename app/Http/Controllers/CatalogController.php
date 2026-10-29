<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function home(): View
    {
        return view('home', ['courses' => Course::published()->with(['instructor', 'category'])->latest('published_at')->limit(3)->get()]);
    }

    public function index(Request $request, ?Category $category = null): View
    {
        abort_if($category && ! $category->active, 404);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'alpha_dash', 'max:100'], 'level' => ['nullable', 'in:beginner,intermediate,advanced'], 'language' => ['nullable', 'in:en,am'], 'access' => ['nullable', 'in:free,paid'], 'sort' => ['nullable', 'in:newest,title,price-asc,price-desc'], 'page' => ['nullable', 'integer', 'min:1']]);
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

        return view('courses.show', compact('course'));
    }
}
