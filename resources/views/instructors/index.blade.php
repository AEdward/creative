<x-layout :title="__('Meet our instructors')">
<section class="feature-page"><div class="container">
<p class="eyebrow">{{ __('Learn from creative practitioners') }}</p><h1>{{ __('Meet our instructors.') }}</h1>
<p>{{ __('Explore the people who choose to share their creative practice with you.') }}</p>
<form action="{{ route('instructors.index') }}" method="GET" class="editor-panel">
<div class="form-field"><label for="instructor-search">{{ __('Search by name, title or specialty') }}</label><input id="instructor-search" name="q" maxlength="120" value="{{ $search }}" type="search"></div><button class="filter-apply">{{ __('Search instructors') }}</button>
</form>
<div class="course-grid">
@forelse($profiles as $profile)
<article class="editor-panel">
@if($profile->show_photo && $profile->user->avatar_key)<img src="{{ route('instructors.photo', $profile) }}" alt="" width="88" height="88" style="border-radius:50%;object-fit:cover">@endif
<h2><a href="{{ route('instructors.show', $profile) }}">{{ $profile->user->name }}</a></h2><p>{{ $profile->professional_title }}</p><p style="white-space:pre-line">{{ $profile->specialties }}</p>
</article>
@empty<p>{{ __('No instructors match your search. Try another name or specialty.') }}</p>@endforelse
</div>{{ $profiles->links() }}
</div></section>
</x-layout>
