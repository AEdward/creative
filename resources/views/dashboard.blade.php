<x-layout :title="__('Dashboard')">
@php
    $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $tabs = [
        ['dashboard', 'Overview', 'heroicon-o-user'],
        ['learning.index', 'My Courses', 'heroicon-o-book-open'],
        ['certificates.index', 'Certificates', 'heroicon-o-trophy'],
        ['wishlist', 'Wishlist', 'heroicon-o-heart'],
        ['payments', 'Orders & Payments', 'heroicon-o-credit-card'],
        ['profile', 'Settings', 'heroicon-o-cog-6-tooth'],
    ];
@endphp
@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif

<section class="dash-hero">
    <div class="dash-hero-band" aria-hidden="true"></div>
    <div class="dash-hero-body">
        <div class="dash-avatar">
            @if($user->avatar_key)<img src="{{ route('profile.avatar') }}" alt="">@else<span>{{ $initials }}</span>@endif
            <a href="{{ route('profile') }}#profile-picture" class="dash-avatar-edit" aria-label="{{ __('Change profile picture') }}"><x-heroicon-o-camera aria-hidden="true" /></a>
        </div>
        <div class="dash-who">
            <div class="dash-name"><h1>{{ $user->name }}</h1><a href="{{ route('profile') }}" class="dash-edit"><x-heroicon-o-pencil-square aria-hidden="true" />{{ __('Edit Profile') }}</a></div>
            <p class="dash-contact"><span>{{ $user->email }}</span>@if($user->phone)<span><x-heroicon-o-phone aria-hidden="true" />{{ $user->phone }}</span>@endif</p>
            <p class="dash-bio">{{ $user->bio ?: __('Add a short bio from Edit Profile so instructors know what you want to create.') }}</p>
        </div>
        <div class="dash-facts">
            <span class="dash-status"><x-heroicon-s-check-circle aria-hidden="true" />{{ __($user->role === 'student' ? 'Active Student' : ucfirst($user->role)) }}</span>
            <dl>
                <div><dt><x-heroicon-o-calendar aria-hidden="true" />{{ __('Member Since') }}</dt><dd>{{ $user->created_at->translatedFormat('M j, Y') }}</dd></div>
                <div><dt><x-heroicon-o-academic-cap aria-hidden="true" />{{ __('Enrolled Courses') }}</dt><dd>{{ $stats['enrolled'] }}</dd></div>
                <div><dt><x-heroicon-o-trophy aria-hidden="true" />{{ __('Certificates') }}</dt><dd>{{ $certificateCount }}</dd></div>
            </dl>
        </div>
    </div>
</section>

<nav class="dash-tabs" aria-label="{{ __('Your account sections') }}">
    @foreach($tabs as [$route, $label, $icon])
        <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" />{{ __($label) }}</a>
    @endforeach
</nav>

<div class="dash-stats">
    <div class="dash-stat tone-violet"><span class="dash-stat-icon"><x-heroicon-o-book-open aria-hidden="true" /></span><div><p>{{ __('Enrolled Courses') }}</p><strong>{{ $stats['enrolled'] }}</strong><a href="{{ route('learning.index') }}">{{ __('View all courses') }} →</a></div></div>
    <div class="dash-stat tone-blue"><span class="dash-stat-icon"><x-heroicon-s-play aria-hidden="true" /></span><div><p>{{ __('In Progress') }}</p><strong>{{ $stats['inProgress'] }}</strong><a href="{{ route('learning.index') }}">{{ __('Continue learning') }} →</a></div></div>
    <div class="dash-stat tone-green"><span class="dash-stat-icon"><x-heroicon-s-check-circle aria-hidden="true" /></span><div><p>{{ __('Completed') }}</p><strong>{{ $stats['completed'] }}</strong><a href="{{ route('certificates.index') }}">{{ __('View certificates') }} →</a></div></div>
    <div class="dash-stat tone-orange"><span class="dash-stat-icon"><x-heroicon-o-clock aria-hidden="true" /></span><div><p>{{ __('Learning Hours') }}</p><strong>{{ rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.') }}</strong><small>{{ __('Total time spent') }}</small></div></div>
</div>

<div class="dash-grid">
    <section class="dash-card">
        <header><h2><x-heroicon-o-book-open aria-hidden="true" />{{ __('Enrolled Courses') }}</h2><a href="{{ route('learning.index') }}">{{ __('View All') }}</a></header>
        <ul class="dash-courses">
            @forelse($enrollments->take(5) as $enrollment)
                @php $course = $enrollment->course; $thumb = 'images/categories/'.$course->category?->slug.'.webp'; $p = $enrollment->percent; @endphp
                <li>
                    <a class="dash-thumb" href="{{ route('courses.show', $course->slug) }}" tabindex="-1" aria-hidden="true">@if($course->thumbnail_key)<img src="{{ route('courses.thumbnail', $course->slug) }}" alt="" loading="lazy">@elseif(file_exists(public_path($thumb)))<img src="{{ asset($thumb) }}" alt="" loading="lazy">@endif</a>
                    <div class="dash-course-info">
                        <h3><a href="{{ route('courses.show', $course->slug) }}">{{ $course->title }}</a></h3>
                        <span class="dash-teacher"><span class="dash-ini" aria-hidden="true">{{ mb_substr($course->instructor_display_name ?? '?', 0, 1) }}</span>{{ $course->instructor_display_name }}</span>
                        <div class="dash-progress"><span class="dash-bar" role="progressbar" aria-valuenow="{{ $p }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress in :course', ['course' => $course->title]) }}"><i style="width:{{ $p }}%"></i></span><b>{{ $p }}%</b></div>
                    </div>
                    <a class="dash-go {{ $p === 0 ? 'soft' : ($p === 100 ? 'soft' : '') }}" href="{{ route('courses.show', $course->slug) }}">{{ __($p === 0 ? 'Start' : ($p === 100 ? 'Review' : 'Continue')) }}</a>
                </li>
            @empty
                <li class="dash-empty">{{ __('You haven’t enrolled in a course yet.') }} <a href="{{ route('courses.index') }}">{{ __('Explore courses') }} →</a></li>
            @endforelse
        </ul>
    </section>

    <div class="dash-side">
        <section class="dash-card">
            <header><h2><x-heroicon-o-trophy aria-hidden="true" />{{ __('Latest Certificate') }}</h2><a href="{{ route('certificates.index') }}">{{ __('View All') }}</a></header>
            @if($latestCertificate)
                <div class="dash-cert">
                    <a class="dash-cert-mini" href="{{ route('certificates.print', $latestCertificate) }}" aria-hidden="true" tabindex="-1"><x-certificate-sheet :template="\App\Support\CertificateTemplate::get()" :learner="$latestCertificate->learner_name" :course="$latestCertificate->course_title" :issued="$latestCertificate->issued_at->toFormattedDateString()" :reference="$latestCertificate->reference" /></a>
                    <div><h3>{{ $latestCertificate->course_title }}</h3><p>{{ __('Completed on') }}<br><x-heroicon-o-calendar aria-hidden="true" />{{ $latestCertificate->issued_at->translatedFormat('M j, Y') }}</p>
                        <a class="dash-outline" href="{{ route('certificates.print', $latestCertificate) }}"><x-heroicon-o-arrow-down-tray aria-hidden="true" />{{ __('Download Certificate') }}</a></div>
                </div>
            @else
                <p class="dash-empty">{{ __('Finish a course to earn your first certificate.') }}</p>
            @endif
        </section>
        <section class="dash-card">
            <header><h2><x-heroicon-o-bolt aria-hidden="true" />{{ __('Recent Activity') }}</h2></header>
            <ul class="dash-activity">
                @forelse($activity as $item)
                    <li><span class="dash-act tone-{{ $item['tone'] }}"><x-dynamic-component :component="$item['icon']" aria-hidden="true" /></span><span><b>@if(! empty($item['url']))<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif</b>@if($item['text'])<small>{{ $item['text'] }}</small>@endif</span><time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->diffForHumans() }}</time></li>
                @empty
                    <li class="dash-empty">{{ __('Your learning activity will appear here.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
</x-layout>
