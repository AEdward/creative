<x-layout dark>
<section class="hero hero-photo">
    <img class="hero-image" src="{{ asset('images/home/hero.webp') }}" srcset="{{ asset('images/home/hero-1000.webp') }} 1000w, {{ asset('images/home/hero.webp') }} 1862w" sizes="100vw" width="1862" height="845" alt="" fetchpriority="high">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">{{ __('Online courses for creative minds') }}</p>
            <h1>{{ __('Learn Creative Skills') }}<br><span>{{ __('Build a Brighter Future') }}</span></h1>
            <p class="hero-description">{{ __('Practical online courses in video editing, graphics design, motion design, 3D and digital marketing.') }}</p>
            <div class="button-row"><a class="button button-pill" href="{{ route('courses.index') }}">{{ __('Start Learning') }} <x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></a></div>
            @if($stats['students'] > 0)
            <p class="hero-proof"><span class="proof-avatars" aria-hidden="true"><span></span><span></span><span></span><span></span></span><span><strong>{{ number_format($stats['students']) }}</strong> {{ trans_choice('{1} student is already learning|[2,*] students are already learning', $stats['students']) }}</span></p>
            @endif
        </div>
    </div>
    <p class="hero-script" aria-hidden="true">Create<br>Learn<br>Grow</p>
</section>
@php
$pathStyles = [
    'video-editing' => ['video', 'Learn professional editing for YouTube, social media and commercial projects.'],
    'graphic-design' => ['design', 'Master design tools and create stunning visuals for any purpose.'],
    'motion-design' => ['motion', 'Bring ideas to life with animation and motion graphics.'],
    '3d-blender' => ['three-d', 'Learn 3D modeling, animation and rendering with Blender.'],
    'digital-marketing' => ['marketing', 'Grow brands with modern digital marketing strategies and tools.'],
];
@endphp
<section class="paths section" id="creative-paths"><div class="container">
    <div class="paths-heading">
        <p class="eyebrow">{{ __('Explore our courses') }}</p>
        <h2>{{ __('Choose Your') }} <span class="gradient-text">{{ __('Creative Path') }}</span></h2>
        <p>{{ __('Practical, project-based courses designed for real world skills.') }}</p>
        <a class="inline-link" href="{{ route('courses.index') }}">{{ __('View All Courses') }} <x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></a>
    </div>
    <div class="path-grid">
    @foreach($categories as $category)
        @php [$style, $copy] = $pathStyles[$category->slug] ?? ['video', $category->description]; $thumb = 'images/categories/'.$category->slug.'.webp'; @endphp
        <a class="path-card {{ $style }}" href="{{ route('categories.show', $category) }}">
            <div class="path-thumb">@if(file_exists(public_path($thumb)))<img src="{{ asset($thumb) }}" alt="" loading="lazy" width="640" height="235">@else<x-heroicon-o-sparkles class="path-thumb-icon" aria-hidden="true" />@endif</div>
            <h3>{{ __($category->name) }}</h3>
            <p>{{ filled($category->description) ? $category->description : __($copy) }}</p>
            <div class="path-foot"><span class="path-count">{{ trans_choice('{0} Courses coming soon|{1} :count course|[2,*] :count courses', $category->courses_count, ['count' => $category->courses_count]) }}</span><span class="path-arrow" aria-hidden="true"><x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></span></div>
        </a>
    @endforeach
    </div>
</div></section>
<section class="section courses-section"><div class="container"><div class="section-heading"><div><p class="eyebrow">{{ __('Make room for your next idea') }}</p><h2>{{ __('Learning starts here.') }}</h2></div><a class="inline-link" href="{{ route('courses.index') }}">{{ __('View all courses') }} <span aria-hidden="true"><x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></span></a></div><div class="course-grid">
@forelse($courses as $course)<x-course-card :course="$course" />@empty<div class="empty-state"><span class="empty-symbol" aria-hidden="true"><x-heroicon-o-sparkles class="empty-icon" aria-hidden="true" /></span><h3>{{ __('No courses published yet') }}</h3><p>{{ __('We’re preparing the academy’s course collection. Explore the creative paths above and check back for published courses.') }}</p><a href="{{ route('about') }}" class="inline-link">{{ __('Get to know the academy') }} <x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></a></div>@endforelse
</div></div></section>
<section class="section community-section"><div class="container community-grid">
    <div>
        <p class="eyebrow">{{ __('Why Creative Lab Academy') }}</p>
        <h2>{{ __('More Than Courses.') }}<br>{{ __('A') }} <span class="gradient-text">{{ __('Creative Community.') }}</span></h2>
        <p>{{ __('We help you build real skills, create amazing projects and grow your career in the creative industry.') }}</p>
        <a class="button button-pill" href="{{ auth()->check() ? route('courses.index') : url('/register') }}" @guest data-login-open="register" @endguest>{{ __('Get Started Today') }} <x-heroicon-o-arrow-right class="direction-icon" aria-hidden="true" /></a>
    </div>
    <div class="community-panel">
        <div class="benefit-cards">
            <article><x-heroicon-s-academic-cap class="benefit-icon" aria-hidden="true" /><h3>{{ __('Expert Instructors') }}</h3><p>{{ __('Learn from industry professionals') }}</p></article>
            <article><x-heroicon-s-play class="benefit-icon" aria-hidden="true" /><h3>{{ __('Practical Projects') }}</h3><p>{{ __('Build a portfolio with real work') }}</p></article>
            <article><x-heroicon-s-user-group class="benefit-icon" aria-hidden="true" /><h3>{{ __('Learn Anywhere') }}</h3><p>{{ __('Access on any device anytime') }}</p></article>
            <article><x-heroicon-s-star class="benefit-icon" aria-hidden="true" /><h3>{{ __('Career Growth') }}</h3><p>{{ __('Get the skills for real opportunities') }}</p></article>
        </div>
        @php $statItems = array_filter([
            $stats['students'] ? ['users', number_format($stats['students']), __('Active Students')] : null,
            $stats['lessons'] ? ['play', number_format($stats['lessons']), __('Published Lessons')] : null,
            $stats['courses'] ? ['trophy', number_format($stats['courses']), __('Published Courses')] : null,
            $stats['rating'] ? ['star', $stats['rating'].'/5', __('Student Rating')] : null,
        ]); @endphp
        @if($statItems)
        <dl class="stat-strip">
            @foreach($statItems as [$icon, $value, $label])<div><x-dynamic-component :component="'heroicon-s-'.$icon" class="stat-icon {{ $icon }}" aria-hidden="true" /><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
        </dl>
        @endif
    </div>
    <div class="ribbons" aria-hidden="true"><span></span><span></span><span></span><span class="dot"></span></div>
</div></section>
<section class="section"><div class="container faq-grid"><div><p class="eyebrow">{{ __('A little clarity') }}</p><h2>{{ __('Before you dive in.') }}</h2><a class="inline-link" href="{{ route('faq') }}">{{ __('More questions? Start here') }} <x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></a></div><div><details class="faq-item"><summary>{{ __('What can I learn at Creative Lab?') }}</summary><p>{{ __('Our creative paths cover video editing, graphic design, motion design, 3D and Blender, and digital marketing. Published course pages list their exact curriculum and requirements.') }}</p></details><details class="faq-item"><summary>{{ __('Do I need experience to get started?') }}</summary><p>{{ __('Look for a beginner-level course and check its requirements. Each course page explains who it is for and which tools you’ll need.') }}</p></details><details class="faq-item"><summary>{{ __('Are courses available now?') }}</summary><p>{{ __('Only published courses appear in the catalog. If a creative path is empty, its courses are still being prepared.') }}</p></details></div></div></section>
</x-layout>
