@props(['title' => 'Creative skills. Real possibilities.', 'description' => 'Creative Lab Academy — learn video editing, graphic design, motion design, 3D and digital marketing.', 'dark' => false])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Creative Lab Academy</title>
    <meta name="description" content="{{ $description }}"><meta name="theme-color" content="#0B0F1A">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ url()->current() }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $dark ? 'home-page' : '' }}">
@if($banner = (\App\Models\SiteSetting::content()['banner'] ?? null))<div class="notice" style="margin:0;border-radius:0;text-align:center" role="status">{{ $banner }}</div>@endif
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header {{ $dark ? 'header-dark' : '' }}">
    <div class="container header-inner">
        <a class="wordmark" href="{{ route('home') }}" aria-label="Creative Lab Academy home">Creative Lab<span>Academy</span></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('academy.home') }}</a>
            <a href="{{ route('courses.index') }}" @if(request()->routeIs('courses.*', 'categories.*')) aria-current="page" @endif>{{ __('academy.courses') }}</a>
            <a href="{{ route('instructors.index') }}">Instructors</a><a href="{{ route('about') }}">{{ __('academy.about') }}</a><a href="{{ route('faq') }}">{{ __('academy.faq') }}</a>
        </nav>
        <div class="header-actions"><form method="POST" action="{{ route('locale') }}" class="locale-form">@csrf<label class="sr-only" for="locale">{{ __('academy.language') }}</label><select name="locale" id="locale" aria-label="{{ __('academy.language') }}"><option value="en" @selected(app()->getLocale() === 'en')>EN</option><option value="am" @selected(app()->getLocale() === 'am')>አማ</option></select><button type="submit" class="text-link">{{ __('academy.language') }}</button></form>
            @auth <a class="text-link" href="{{ url('/dashboard') }}">My dashboard</a>
            @else <a class="text-link sign-in-link" href="{{ url('/login') }}">Log in</a> @endauth
            <a class="button button-small" href="{{ route('courses.index') }}">Explore courses <span aria-hidden="true"><x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></span></a>
        </div>
        <details class="mobile-menu"><summary>Menu</summary><nav aria-label="Mobile navigation"><a href="{{ route('home') }}">{{ __('academy.home') }}</a><a href="{{ route('courses.index') }}">{{ __('academy.courses') }}</a><a href="{{ route('instructors.index') }}">Instructors</a><a href="{{ route('about') }}">{{ __('academy.about') }}</a><a href="{{ route('faq') }}">{{ __('academy.faq') }}</a><a href="{{ url('/login') }}">Log in</a></nav></details>
    </div>
</header>
@auth @if(auth()->user()->hasVerifiedEmail())<nav class="account-nav" aria-label="Your workspace"><div class="container"><a href="{{ route('dashboard') }}">{{ __('academy.dashboard') }}</a><a href="{{ route('learning.index') }}">{{ __('academy.learning') }}</a><a href="{{ route('certificates.index') }}">{{ __('academy.certificates') }}</a><a href="{{ route('notifications.index') }}">{{ __('academy.notifications') }}</a><a href="{{ route('support.index') }}">{{ __('academy.support') }}</a>@can('instructor')<a href="{{ route('instructor.dashboard') }}">Teaching studio</a><a href="{{ route('instructor.profile.edit') }}">Instructor profile</a>@endcan @can('administer-academy')<a href="{{ route('academy-admin') }}">Operations</a>@endcan</div></nav>@endif @endauth
<main id="main">{{ $slot }}</main>
<footer class="site-footer"><div class="container footer-grid">
    <div><a class="wordmark" href="{{ route('home') }}">Creative Lab<span>Academy</span></a><p>Learn. Create. Grow.</p><p class="small">A place to turn creative curiosity into practical skills.</p></div>
    <div><h2>Explore</h2><a href="{{ route('courses.index') }}">All courses</a><a href="{{ route('instructors.index') }}">Instructors</a><a href="{{ route('about') }}">About the academy</a><a href="{{ route('contact') }}">Contact</a></div>
    <div><h2>Good to know</h2><a href="{{ route('faq') }}">Questions & answers</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('refund-policy') }}">Refund policy</a></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} Creative Lab Academy</span><span>Made for creative minds.</span></div></footer>
@livewireScripts
</body></html>
