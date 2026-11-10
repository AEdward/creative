@php view()->share('errors', $errors ?? new \Illuminate\Support\ViewErrorBag); @endphp
<x-layout :title="__('Page not found')" :noindex="true">
<section class="section error-page">
    <div class="container error-inner">
        <p class="error-code" aria-hidden="true">404</p>
        <h1>{{ __('This page wandered off.') }}</h1>
        <p class="error-lead">{{ __('The link may be old, mistyped, or the course is no longer available. Try a search, or pick up from one of these.') }}</p>
        <form class="error-search" method="GET" action="{{ route('courses.index') }}" role="search">
            <label class="sr-only" for="error-q">{{ __('Search courses') }}</label>
            <input id="error-q" type="search" name="q" maxlength="120" placeholder="{{ __('Search for courses, skills or tools…') }}">
            <button class="button" type="submit">{{ __('Search') }}</button>
        </form>
        <div class="error-links">
            <a class="button" href="{{ route('home') }}">{{ __('Go to the homepage') }}</a>
            <a class="button button-outline" href="{{ route('courses.index') }}">{{ __('Browse all courses') }}</a>
            @auth<a class="inline-link" href="{{ route('dashboard') }}">{{ __('My learning') }} →</a>@endauth
            <a class="inline-link" href="{{ route('contact') }}">{{ __('Contact us') }} →</a>
        </div>
    </div>
</section>
</x-layout>
