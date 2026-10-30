<x-layout :title="$profile->user->name">
<section class="feature-page"><div class="container">
<a href="{{ route('instructors.index') }}">{{ __('All instructors') }}</a>
<header class="editor-panel">
@if($profile->show_photo && $profile->user->avatar_key)<img src="{{ route('instructors.photo', $profile) }}" alt="" width="120" height="120" style="border-radius:50%;object-fit:cover">@endif
<h1>{{ $profile->user->name }}</h1><p>{{ $profile->professional_title }}</p>
@if($profile->biography)<h2>{{ __('About') }}</h2><p style="white-space:pre-line">{{ $profile->biography }}</p>@endif
@if($profile->specialties)<h2>{{ __('Specialties') }}</h2><p style="white-space:pre-line">{{ $profile->specialties }}</p>@endif
</header>
<h2>{{ __('Published courses') }}</h2><div class="course-grid">@forelse($courses as $course)<x-course-card :course="$course" />@empty<p>{{ __('No published courses to explore yet.') }}</p>@endforelse</div>{{ $courses->links() }}
</div></section>
</x-layout>
