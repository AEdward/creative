@props(['title' => 'Creative Lab Academy'])
@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ')->limit(18);
    $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $hasAvatar = is_string($user->avatar_key) && $user->avatar_key !== '';
    $nav = [
        ['dashboard', 'academy.dashboard', 'heroicon-o-home', 'dashboard'],
        ['courses.index', 'academy.courses', 'heroicon-o-squares-2x2', 'courses.*'],
        ['learning.index', 'academy.learning', 'heroicon-o-book-open', 'learning.*'],
        ['certificates.index', 'Certificates', 'heroicon-o-trophy', 'certificates.*'],
        ['wishlist', 'Wishlist', 'heroicon-o-heart', 'wishlist'],
        ['notifications.index', 'Messages', 'heroicon-o-envelope', 'notifications.*'],
    ];
    $account = [
        ['profile', 'Profile', 'heroicon-o-user', 'profile'],
        ['payments', 'Orders & payments', 'heroicon-o-credit-card', 'payments'],
        ['support.index', 'Help & Support', 'heroicon-o-question-mark-circle', 'support.*'],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Creative Lab Academy</title>
    <meta name="theme-color" content="#0B1025"><meta name="robots" content="noindex">
    @if(file_exists(public_path('images/brand/favicon-32.png')))<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}"><link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">@endif
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<div class="app-shell">
    <input type="checkbox" id="app-nav-toggle" class="app-nav-toggle" aria-hidden="true" tabindex="-1">
    <aside class="app-sidebar" aria-label="{{ __('Your workspace') }}">
        <div class="app-sidebar-brand"><x-brand :on-dark="true" /></div>
        <nav class="app-nav">
            @foreach($nav as [$route, $label, $icon, $active])
                <a href="{{ route($route) }}" @if(request()->routeIs($active)) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" /><span>{{ __($label) }}</span></a>
            @endforeach
            @can('instructor')<a href="{{ route('instructor.dashboard') }}" @if(request()->routeIs('instructor.*')) aria-current="page" @endif><x-heroicon-o-presentation-chart-line aria-hidden="true" /><span>{{ __('Instructor workspace') }}</span></a>@endcan
            @can('administer-academy')<a href="{{ route('academy-admin') }}"><x-heroicon-o-shield-check aria-hidden="true" /><span>{{ __('Administration') }}</span></a>@endcan
        </nav>
        <p class="app-nav-heading">{{ __('Account') }}</p>
        <nav class="app-nav" aria-label="{{ __('Account') }}">
            @foreach($account as [$route, $label, $icon, $active])
                <a href="{{ route($route) }}" @if(request()->routeIs($active)) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" /><span>{{ __($label) }}</span></a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="app-logout">@csrf<button type="submit"><x-heroicon-o-arrow-right-on-rectangle aria-hidden="true" /><span>{{ __('Logout') }}</span></button></form>
    </aside>
    <label for="app-nav-toggle" class="app-scrim" aria-hidden="true"></label>
    <div class="app-main">
        <header class="app-topbar">
            <label for="app-nav-toggle" class="app-menu-button" role="button" tabindex="0" aria-label="{{ __('Menu') }}"><x-heroicon-o-bars-3 aria-hidden="true" /></label>
            <form class="app-search" method="GET" action="{{ route('courses.index') }}" role="search"><label class="sr-only" for="app-search">{{ __('Search courses') }}</label><x-heroicon-o-magnifying-glass aria-hidden="true" /><input type="search" id="app-search" name="q" maxlength="120" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Search for courses, skills or tools…') }}" autocomplete="off"></form>
            <div class="app-topbar-actions">
                <a class="app-bell" href="{{ route('notifications.index') }}" aria-label="{{ $unread ? trans_choice(':count unread notification|:count unread notifications', $unread, ['count' => $unread]) : __('Notifications') }}"><x-heroicon-o-bell aria-hidden="true" />@if($unread)<span class="app-bell-dot" aria-hidden="true"></span>@endif</a>
                <details class="app-user">
                    <summary><span class="app-avatar">@if($hasAvatar)<img src="{{ route('profile.avatar') }}" alt="">@else{{ $initials }}@endif</span><span class="app-user-name">{{ __('Hi, :name', ['name' => $firstName]) }}</span><x-heroicon-o-chevron-down class="app-user-chevron" aria-hidden="true" /></summary>
                    <div class="app-user-menu"><a href="{{ route('profile') }}">{{ __('Profile') }}</a><a href="{{ route('home') }}">{{ __('Public site') }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('Logout') }}</button></form></div>
                </details>
            </div>
        </header>
        <main id="main" tabindex="-1" class="app-content">{{ $slot }}</main>
    </div>
</div>
@livewireScripts
</body>
</html>
