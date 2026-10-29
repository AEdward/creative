@props(['title' => 'Creative skills. Real possibilities.', 'description' => 'Creative Lab Academy — learn video editing, graphic design, motion design, 3D and digital marketing.', 'dark' => false])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Creative Lab Academy</title>
    <meta name="description" content="{{ $description }}"><meta name="theme-color" content="#0B0F1A">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $dark ? 'home-page' : '' }}">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header {{ $dark ? 'header-dark' : '' }}">
    <div class="container header-inner">
        <a class="wordmark" href="{{ route('home') }}" aria-label="Creative Lab Academy home">Creative Lab<span>Academy</span></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a href="{{ route('courses.index') }}" @if(request()->routeIs('courses.*', 'categories.*')) aria-current="page" @endif>Courses</a>
            <a href="{{ route('about') }}">About</a><a href="{{ route('faq') }}">FAQ</a>
        </nav>
        <div class="header-actions">
            @auth <a class="text-link" href="{{ url('/dashboard') }}">My dashboard</a>
            @else <a class="text-link sign-in-link" href="{{ url('/login') }}">Log in</a> @endauth
            <a class="button button-small" href="{{ route('courses.index') }}">Explore courses <span aria-hidden="true">↗︎</span></a>
        </div>
        <details class="mobile-menu"><summary>Menu</summary><nav aria-label="Mobile navigation"><a href="{{ route('home') }}">Home</a><a href="{{ route('courses.index') }}">Courses</a><a href="{{ route('about') }}">About</a><a href="{{ route('faq') }}">FAQ</a><a href="{{ url('/login') }}">Log in</a></nav></details>
    </div>
</header>
<main id="main">{{ $slot }}</main>
<footer class="site-footer"><div class="container footer-grid">
    <div><a class="wordmark" href="{{ route('home') }}">Creative Lab<span>Academy</span></a><p>Learn. Create. Grow.</p><p class="small">A place to turn creative curiosity into practical skills.</p></div>
    <div><h2>Explore</h2><a href="{{ route('courses.index') }}">All courses</a><a href="{{ route('about') }}">About the academy</a><a href="{{ route('contact') }}">Contact</a></div>
    <div><h2>Good to know</h2><a href="{{ route('faq') }}">Questions & answers</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('refund-policy') }}">Refund policy</a></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} Creative Lab Academy</span><span>Made for creative minds.</span></div></footer>
</body></html>
