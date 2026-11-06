@props(['title' => 'Creative skills. Real possibilities.', 'description' => 'Creative Lab Academy — learn video editing, graphic design, motion design, 3D and digital marketing online, in English and Amharic.', 'dark' => false, 'image' => null, 'type' => 'website', 'jsonLd' => [], 'noindex' => false])
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
@php
    $portalOpen = (bool) \App\Support\AcademySettings::get('instructor_portal_enabled');
    // Pages behind the auth middleware use the signed-in sidebar layout; public pages keep the marketing header.
    $useShell = auth()->check() && auth()->user()->hasVerifiedEmail() && ! request()->routeIs('two-factor.*')
        && (request()->routeIs('learning.show', 'courses.show', 'categories.show')
            || collect(request()->route()?->gatherMiddleware() ?? [])->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:'))));
@endphp
@if($useShell)
<x-app-shell :title="$title">{{ $slot }}<div hidden id="media-localization" data-messages="{{ json_encode($mediaLabels, JSON_UNESCAPED_UNICODE) }}"></div></x-app-shell>
@else
@php $searchCategories = \App\Models\Category::where('active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug']); @endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Creative Lab Academy</title>
    <meta name="theme-color" content="#0B1025">
    <x-seo-head :title="$title" :description="$description" :image="$image" :type="$type" :noindex="$noindex || request()->routeIs('login', 'register', 'password.*', 'verification.*', 'two-factor.*', 'checkout.*')"
        :json-ld="request()->routeIs('home') ? array_merge([\App\Support\Seo::organization(), \App\Support\Seo::website()], $jsonLd) : $jsonLd" />
    @if(file_exists(public_path('images/brand/favicon-32.png')))<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}"><link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/brand/favicon-192.png') }}"><link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">@endif
    @if(request()->routeIs('home'))<link rel="preload" as="image" href="{{ asset('images/home/hero.webp') }}" imagesrcset="{{ asset('images/home/hero-1000.webp') }} 1000w, {{ asset('images/home/hero.webp') }} 1862w" imagesizes="100vw" fetchpriority="high">@endif
    {{-- Public pages use no Livewire components, so its scripts aren't loaded here (signed-in pages load them). --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $dark ? 'home-page' : '' }}">
@if($banner = (\App\Models\SiteSetting::content()['banner'] ?? null))<div class="notice site-banner" role="status">{{ $banner }}</div>@endif
@if(\App\Support\AcademySettings::get('maintenance_enabled'))<div class="maintenance-staff-bar" role="status">{{ __('Maintenance mode is on: visitors see the “back soon” page. Turn it off in Site settings.') }}</div>@endif
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<header class="site-header {{ $dark ? 'header-dark' : '' }}">
    <div class="container header-inner">
        <x-brand :on-dark="$dark" />
        <nav class="desktop-nav" aria-label="{{ __('Main navigation') }}">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('academy.home') }}</a>
            <a href="{{ route('courses.index') }}" @if(request()->routeIs('courses.*', 'categories.*')) aria-current="page" @endif>{{ __('academy.courses') }}</a>
            @if($portalOpen)<a href="{{ route('instructors.index') }}" @if(request()->routeIs('instructors.*')) aria-current="page" @endif>{{ __('Instructors') }}</a>@endif<a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>{{ __('academy.about') }}</a><a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>{{ __('Contact') }}</a>
        </nav>
        <div class="header-actions"><form method="POST" action="{{ route('locale') }}" class="locale-form">@csrf<div class="locale-switch" role="group" aria-label="{{ __('academy.language') }}"><button type="submit" name="locale" value="en" lang="en" aria-label="English" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">EN</button><button type="submit" name="locale" value="am" lang="am" aria-label="አማርኛ" aria-pressed="{{ app()->getLocale() === 'am' ? 'true' : 'false' }}">አማ</button></div></form>
            <details class="nav-search" data-nav-search>
                <summary class="icon-link" aria-label="{{ __('Search courses') }}"><x-heroicon-o-magnifying-glass aria-hidden="true" /></summary>
                <div class="nav-search-panel">
                    <x-search-form id="nav-search-input" />
                    @if($searchCategories->isNotEmpty())<p class="nav-search-label">{{ __('Browse by path') }}</p><div class="nav-search-chips">@foreach($searchCategories as $category)<a href="{{ route('categories.show', $category) }}">{{ __($category->name) }}</a>@endforeach</div>@endif
                </div>
            </details>
            @auth <a class="button button-ghost" href="{{ auth()->user()->homeUrl() }}">{{ __('My dashboard') }}</a>
            @else <a class="button button-ghost sign-in-link" href="{{ url('/login') }}" data-login-open>{{ __('Log In') }}</a>
            <a class="button button-pill" href="{{ url('/register') }}" data-login-open="register">{{ __('Get Started') }}</a> @endauth
        </div>
        <details class="mobile-menu"><summary>{{ __('Menu') }}</summary><nav aria-label="{{ __('Mobile navigation') }}"><x-search-form id="mobile-search-input" /><a href="{{ route('home') }}">{{ __('academy.home') }}</a><a href="{{ route('courses.index') }}">{{ __('academy.courses') }}</a>@if($portalOpen)<a href="{{ route('instructors.index') }}">{{ __('Instructors') }}</a>@endif<a href="{{ route('about') }}">{{ __('academy.about') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a><a href="{{ route('faq') }}">{{ __('academy.faq') }}</a>@auth<a href="{{ auth()->user()->homeUrl() }}">{{ __('My dashboard') }}</a>@else<a href="{{ url('/login') }}" data-login-open>{{ __('Log In') }}</a><a href="{{ url('/register') }}" data-login-open="register">{{ __('Get Started') }}</a>@endauth<form method="POST" action="{{ route('locale') }}" class="mobile-locale-form">@csrf<label for="mobile-locale">{{ __('academy.language') }}</label><select name="locale" id="mobile-locale"><option value="en" @selected(app()->getLocale() === 'en')>English</option><option value="am" @selected(app()->getLocale() === 'am')>አማርኛ</option></select><button type="submit" class="button button-small">{{ __('academy.language') }}</button></form></nav></details>
    </div>
</header>
{{-- Signed-in links live in each role's sidebar; "My dashboard" in the header goes there. --}}
<main id="main" tabindex="-1">{{ $slot }}</main>
<footer class="site-footer"><div class="container footer-grid">
    <div><x-brand /><p class="small">{{ __('A place to turn creative curiosity into practical skills.') }}</p>@php $contact = \App\Support\AcademySettings::all(); @endphp @if(filled($contact['contact_email']))<a href="mailto:{{ $contact['contact_email'] }}">{{ $contact['contact_email'] }}</a>@endif @if(filled($contact['contact_phone']))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['contact_phone']) }}">{{ $contact['contact_phone'] }}</a>@endif @if(filled($contact['contact_address']))<p class="small">{{ $contact['contact_address'] }}</p>@endif</div>
    <div><h2>{{ __('Explore') }}</h2><a href="{{ route('courses.index') }}">{{ __('All courses') }}</a>@if($portalOpen)<a href="{{ route('instructors.index') }}">{{ __('Instructors') }}</a>@endif<a href="{{ route('about') }}">{{ __('About the academy') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a></div>
    <div><h2>{{ __('Good to know') }}</h2><a href="{{ route('faq') }}">{{ __('Questions & answers') }}</a><a href="{{ route('privacy') }}">{{ __('Privacy') }}</a><a href="{{ route('terms') }}">{{ __('Terms') }}</a><a href="{{ route('refund-policy') }}">{{ __('Refund policy') }}</a><a href="{{ route('cookies') }}">{{ __('Cookie policy') }}</a></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} Creative Lab Academy</span><span>{{ __('Developed by') }} <a href="https://paraibatech.com/" target="_blank" rel="noopener">Paraiba Technology PLC</a></span></div></footer>

@guest @unless(request()->routeIs('login', 'register'))
@php $authPanel = $errors->getBag('register')->any() ? 'register' : ($errors->getBag('login')->any() ? 'login' : null); @endphp
<dialog class="login-dialog" id="login-dialog" aria-label="{{ __('Sign in or create an account') }}" @if($authPanel) data-open-on-load="{{ $authPanel }}" @endif>
    <div class="auth-shell">
        <x-auth-aside />
        <div class="auth-forms">
            <div class="auth-tabs" role="tablist" aria-label="{{ __('Sign in or create an account') }}">
                <button type="button" role="tab" id="auth-tab-login" aria-controls="auth-panel-login" data-auth-tab="login" aria-selected="{{ $authPanel === 'register' ? 'false' : 'true' }}" tabindex="{{ $authPanel === 'register' ? '-1' : '0' }}">{{ __('Sign In') }}</button>
                <button type="button" role="tab" id="auth-tab-register" aria-controls="auth-panel-register" data-auth-tab="register" aria-selected="{{ $authPanel === 'register' ? 'true' : 'false' }}" tabindex="{{ $authPanel === 'register' ? '0' : '-1' }}">{{ __('Create Account') }}</button>
            </div>
            <div role="tabpanel" id="auth-panel-login" aria-labelledby="auth-tab-login" data-auth-panel="login" @if($authPanel === 'register') hidden @endif><x-login-form modal /></div>
            <div role="tabpanel" id="auth-panel-register" aria-labelledby="auth-tab-register" data-auth-panel="register" @if($authPanel !== 'register') hidden @endif><x-register-form modal /></div>
        </div>
    </div>
    <form method="dialog"><button class="dialog-close" aria-label="{{ __('Close') }}"><x-heroicon-o-x-mark aria-hidden="true" /></button></form>
</dialog>
@endunless @endguest
<div hidden id="media-localization" data-messages="{{ json_encode($mediaLabels, JSON_UNESCAPED_UNICODE) }}"></div>
<x-chat-widget />
</body></html>
@endif
