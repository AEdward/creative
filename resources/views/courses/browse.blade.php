<x-app-shell :title="$category?->name ?? __('Explore our courses')">
<section class="browse-hero">
    <div class="browse-hero-text">
        <p class="browse-eyebrow">{{ __('Gain real-world skills') }}</p>
        <h1>{{ $category?->name ?? __('Explore Our Courses') }}</h1>
        <p>{{ __('Learn from industry experts and turn your creativity into real opportunities.') }}</p>
        <form method="GET" action="{{ route('courses.index') }}" class="browse-search" role="search">
            <label class="sr-only" for="browse-q">{{ __('Search courses') }}</label>
            <x-heroicon-o-magnifying-glass aria-hidden="true" />
            <input type="search" id="browse-q" name="q" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search courses…') }}">
            @if(! empty($filters['category']))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
            @if(! empty($filters['sort']))<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
        </form>
    </div>
</section>

<div class="browse-toolbar">
    <nav class="browse-chips" aria-label="{{ __('Creative paths') }}">
        @php $keep = array_filter(['q' => $filters['q'] ?? null, 'sort' => $filters['sort'] ?? null]); @endphp
        <a href="{{ route('courses.index', $keep) }}" @if(empty($filters['category'])) aria-current="page" @endif><x-heroicon-o-squares-2x2 aria-hidden="true" />{{ __('All Courses') }}</a>
        @foreach($categories as $item)
            <a href="{{ route('courses.index', $keep + ['category' => $item->slug]) }}" @if(($filters['category'] ?? null) === $item->slug) aria-current="page" @endif>
                @if(file_exists(public_path('images/categories/'.$item->slug.'.webp')))<img src="{{ asset('images/categories/'.$item->slug.'.webp') }}" alt="" width="22" height="22">@endif{{ $item->name }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('courses.index') }}" class="browse-sort">
        @foreach(array_filter(['q' => $filters['q'] ?? null, 'category' => $filters['category'] ?? null]) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
        <label class="sr-only" for="browse-sort">{{ __('Sort by') }}</label>
        <select id="browse-sort" name="sort" onchange="this.form.requestSubmit()">
            @foreach(['newest' => 'Newest first', 'popular' => 'Most popular', 'title' => 'Title A–Z', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'newest') === $key)>{{ __($label) }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="button button-small">{{ __('Sort') }}</button></noscript>
    </form>
</div>

@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
<p class="sr-only" role="status">{{ trans_choice(':count course|:count courses', $courses->total(), ['count' => $courses->total()]) }}</p>

<div class="browse-grid">
@forelse($courses as $course)
    @php
        $enrolled = in_array($course->id, $enrolledIds, true);
        $saved = in_array($course->id, $wishlistIds, true);
        $minutes = intdiv((int) $course->duration_seconds, 60);
        $duration = $minutes >= 60 ? intdiv($minutes, 60).'h '.($minutes % 60 ? ($minutes % 60).'m' : '') : ($minutes ? $minutes.'m' : null);
        $fallback = 'images/categories/'.$course->category->slug.'.webp';
        $teacher = $course->instructor->name;
    @endphp
    <article class="browse-card">
        <a class="browse-cover" href="{{ route('courses.show', $course->slug) }}" tabindex="-1" aria-hidden="true">
            @if($course->thumbnail_key)<img src="{{ route('courses.thumbnail', $course->slug) }}" alt="" loading="lazy">
            @elseif(file_exists(public_path($fallback)))<img src="{{ asset($fallback) }}" alt="" loading="lazy">@endif
            @if($duration)<span class="browse-duration"><x-heroicon-s-play aria-hidden="true" />{{ trim($duration) }}</span>@endif
        </a>
        <div class="browse-body">
            <h2><a href="{{ route('courses.show', $course->slug) }}">{{ $course->title }}</a></h2>
            <div class="browse-byline">
                <span class="browse-teacher"><span class="browse-initials" aria-hidden="true">{{ mb_substr($teacher, 0, 1) }}</span>{{ $teacher }}</span>
                @if($course->rating_count)<span class="browse-rating"><x-heroicon-s-star aria-hidden="true" />{{ number_format((float) $course->rating, 1) }} <span>({{ $course->rating_count >= 1000 ? round($course->rating_count / 1000, 1).'K' : $course->rating_count }})</span><span class="sr-only">{{ __('average rating') }}</span></span>@endif
            </div>
            <div class="browse-meta">
                <span><x-heroicon-o-chart-bar aria-hidden="true" />{{ __(ucfirst($course->level)) }}</span>
                <span><x-heroicon-o-rectangle-stack aria-hidden="true" />{{ trans_choice(':count Lesson|:count Lessons', $course->lessons_count, ['count' => $course->lessons_count]) }}</span>
                @unless($enrolled)<span class="browse-price">{{ $course->priceLabel() }}</span>@endunless
            </div>
            <div class="browse-actions">
                @if($enrolled)
                    <a class="browse-primary" href="{{ route('learning.index') }}">{{ __('Start Learning') }}</a>
                @else
                    <a class="browse-outline" href="{{ route('courses.show', $course->slug) }}">{{ __('Enroll Now') }}</a>
                @endif
                <form method="POST" action="{{ $saved ? route('wishlist.destroy', $course->slug) : route('wishlist.store', $course->slug) }}">@csrf @if($saved) @method('DELETE') @endif
                    <button type="submit" class="browse-save" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? __('Remove :course from wishlist', ['course' => $course->title]) : __('Save :course to wishlist', ['course' => $course->title]) }}">@if($saved)<x-heroicon-s-bookmark aria-hidden="true" />@else<x-heroicon-o-bookmark aria-hidden="true" />@endif</button>
                </form>
            </div>
        </div>
    </article>
@empty
    <div class="empty-state browse-empty"><span class="empty-symbol" aria-hidden="true"><x-heroicon-o-sparkles class="empty-icon" aria-hidden="true" /></span>
        <h2>{{ array_filter($filters) ? __('No courses match your search') : __('No courses published yet') }}</h2>
        <p>{{ array_filter($filters) ? __('Try a different keyword or clear your filters to explore all published courses.') : __('Our course collection is being prepared. Check back to discover what you can create next.') }}</p>
        @if(array_filter($filters))<a class="button" href="{{ route('courses.index') }}">{{ __('See all courses') }}</a>@endif
    </div>
@endforelse
</div>
<div class="pagination">{{ $courses->links('pagination::tailwind') }}</div>
</x-app-shell>
