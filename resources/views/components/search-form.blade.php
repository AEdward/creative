@props(['id'])
<form class="search-box" method="GET" action="{{ route('courses.index') }}" role="search">
    <label class="sr-only" for="{{ $id }}">{{ __('Search courses') }}</label>
    <div class="input-icon"><x-heroicon-o-magnifying-glass aria-hidden="true" /><input type="search" id="{{ $id }}" name="q" maxlength="120" placeholder="{{ __('Search courses, skills or tools…') }}" autocomplete="off"></div>
    <button type="submit" class="button button-pill">{{ __('Search') }}</button>
</form>
