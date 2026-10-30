@props(['title' => 'Creative skills. Real possibilities.', 'description' => 'Creative Lab Academy — learn video editing, graphic design, motion design, 3D and digital marketing.', 'dark' => false])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Creative Lab Academy</title>
    <meta name="description" content="{{ $description }}"><meta name="theme-color" content="#0B1025">
    @if(file_exists(public_path('images/brand/favicon-32.png')))<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}"><link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/brand/favicon-192.png') }}"><link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ url()->current() }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $dark ? 'home-page' : '' }}">
@if($banner = (\App\Models\SiteSetting::content()['banner'] ?? null))<div class="notice" style="margin:0;border-radius:0;text-align:center" role="status">{{ $banner }}</div>@endif
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="site-header {{ $dark ? 'header-dark' : '' }}">
    <div class="container header-inner">
        <x-brand :on-dark="$dark" />
        <nav class="desktop-nav" aria-label="{{ __('Main navigation') }}">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('academy.home') }}</a>
            <a href="{{ route('courses.index') }}" @if(request()->routeIs('courses.*', 'categories.*')) aria-current="page" @endif>{{ __('academy.courses') }}</a>
            <a href="{{ route('instructors.index') }}" @if(request()->routeIs('instructors.*')) aria-current="page" @endif>{{ __('Instructors') }}</a><a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>{{ __('academy.about') }}</a><a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>{{ __('Contact') }}</a>
        </nav>
        <div class="header-actions"><form method="POST" action="{{ route('locale') }}" class="locale-form">@csrf<label class="sr-only" for="locale">{{ __('academy.language') }}</label><select name="locale" id="locale" aria-label="{{ __('academy.language') }}" onchange="this.form.submit()"><option value="en" @selected(app()->getLocale() === 'en')>EN</option><option value="am" @selected(app()->getLocale() === 'am')>አማ</option></select><noscript><button type="submit" class="text-link">{{ __('academy.language') }}</button></noscript></form>
            <a class="icon-link" href="{{ route('courses.index') }}#q" aria-label="{{ __('Search courses') }}"><x-heroicon-o-magnifying-glass aria-hidden="true" /></a>
            @auth <a class="button button-ghost" href="{{ url('/dashboard') }}">{{ __('My dashboard') }}</a>
            @else <a class="button button-ghost sign-in-link" href="{{ url('/login') }}">{{ __('Log In') }}</a>
            <a class="button button-pill" href="{{ url('/register') }}">{{ __('Get Started') }}</a> @endauth
        </div>
        <details class="mobile-menu"><summary>{{ __('Menu') }}</summary><nav aria-label="{{ __('Mobile navigation') }}"><a href="{{ route('home') }}">{{ __('academy.home') }}</a><a href="{{ route('courses.index') }}">{{ __('academy.courses') }}</a><a href="{{ route('instructors.index') }}">{{ __('Instructors') }}</a><a href="{{ route('about') }}">{{ __('academy.about') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a><a href="{{ route('faq') }}">{{ __('academy.faq') }}</a>@auth<a href="{{ url('/dashboard') }}">{{ __('My dashboard') }}</a>@else<a href="{{ url('/login') }}">{{ __('Log In') }}</a><a href="{{ url('/register') }}">{{ __('Get Started') }}</a>@endauth<form method="POST" action="{{ route('locale') }}" class="mobile-locale-form">@csrf<label for="mobile-locale">{{ __('academy.language') }}</label><select name="locale" id="mobile-locale"><option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="am" @selected(app()->getLocale() === 'am')>አማርኛ</option></select><button type="submit" class="button button-small">{{ __('academy.language') }}</button></form></nav></details>
    </div>
</header>
@auth @if(auth()->user()->hasVerifiedEmail())<nav class="account-nav" aria-label="{{ __('Your workspace') }}"><div class="container"><a href="{{ route('dashboard') }}">{{ __('academy.dashboard') }}</a><a href="{{ route('learning.index') }}">{{ __('academy.learning') }}</a><a href="{{ route('certificates.index') }}">{{ __('academy.certificates') }}</a><a href="{{ route('notifications.index') }}">{{ __('academy.notifications') }}</a><a href="{{ route('support.index') }}">{{ __('academy.support') }}</a>@can('instructor')<a href="{{ route('instructor.dashboard') }}">{{ __('Teaching studio') }}</a><a href="{{ route('instructor.profile.edit') }}">{{ __('Instructor profile') }}</a>@endcan @can('administer-academy')<a href="{{ route('academy-admin') }}">{{ __('Operations') }}</a>@endcan</div></nav>@endif @endauth
<main id="main" tabindex="-1">{{ $slot }}</main>
<footer class="site-footer"><div class="container footer-grid">
    <div><x-brand /><p class="small">{{ __('A place to turn creative curiosity into practical skills.') }}</p></div>
    <div><h2>{{ __('Explore') }}</h2><a href="{{ route('courses.index') }}">{{ __('All courses') }}</a><a href="{{ route('instructors.index') }}">{{ __('Instructors') }}</a><a href="{{ route('about') }}">{{ __('About the academy') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a></div>
    <div><h2>{{ __('Good to know') }}</h2><a href="{{ route('faq') }}">{{ __('Questions & answers') }}</a><a href="{{ route('privacy') }}">{{ __('Privacy') }}</a><a href="{{ route('terms') }}">{{ __('Terms') }}</a><a href="{{ route('refund-policy') }}">{{ __('Refund policy') }}</a></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} Creative Lab Academy</span><span>{{ __('Made for creative minds.') }}</span></div></footer>
@php
$mediaLabels = collect([
    'Please retry later.',
    'Ready to watch.',
    'Playback is unavailable. Refresh playback to retry.',
    'Playback stopped or the link expired. Refresh playback to continue from your place.',
    'Your place could not be saved. Check your connection.',
    'Choose an MP4 video up to 1 GB.',
    'Uploading directly to private storage…',
    'Upload failed. Please retry.',
    'Upload connection failed. Please retry.',
    'Upload cancelled. You can start a new upload.',
    'Verifying video…',
    'Uploading:'
])->mapWithKeys(fn ($label) => [$label => __($label)])->all();
@endphp
<div hidden id="media-localization" data-messages="{{ json_encode($mediaLabels, JSON_UNESCAPED_UNICODE) }}"></div>
@livewireScripts
</body></html>
