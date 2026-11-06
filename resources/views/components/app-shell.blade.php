@props(['title' => 'Creative Lab Academy'])
@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
    $latestNotifications = $user->notifications()->latest()->limit(5)->get();
    $unreadMessages = \App\Models\Conversation::visibleTo($user)->unreadFor($user)->count();
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ')->limit(18);
    $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $hasAvatar = is_string($user->avatar_key) && $user->avatar_key !== '';
    // Each role has its own workspace and sidebar; nobody is shown another role's pages.
    // Entries: [url, label, icon, route pattern(s) that mark it current].
    $portalOpen = (bool) \App\Support\AcademySettings::get('instructor_portal_enabled');
    if ($user->role === 'admin') {
        $can = fn (string $area) => $user->staffCan($area);
        $nav = array_values(array_filter([
            [\App\Support\Admin::url(), 'Admin dashboard', 'heroicon-o-home', []],
            $can('content') ? [\App\Support\Admin::url('courses'), 'Courses', 'heroicon-o-book-open', ['instructor.courses.edit', 'instructor.materials', 'instructor.assessments', 'instructor.announcements', 'instructor.learners']] : null,
            $can('content') ? [\App\Filament\Pages\CourseStudio::getUrl(), 'New course', 'heroicon-o-plus-circle', []] : null,
            $can('content') ? [\App\Support\Admin::url('media-library'), 'Media Library', 'heroicon-o-photo', []] : null,
            $can('super') ? [\App\Support\Admin::url('users'), 'Students & users', 'heroicon-o-users', []] : null,
            $can('finance') ? [\App\Support\Admin::url('payment-submissions'), 'Payment approvals', 'heroicon-o-banknotes', []] : null,
            $can('enrollments') ? [\App\Support\Admin::url('enrollments'), 'Enrollments', 'heroicon-o-user-plus', []] : null,
            [\App\Support\Admin::url('analytics'), 'Analytics', 'heroicon-o-chart-bar', []],
            $can('support') ? [route('messages.index'), 'Support messages', 'heroicon-o-envelope', ['messages.*']] : null,
            $can('support') ? [\App\Support\Admin::url('support-tickets'), 'Support tickets', 'heroicon-o-lifebuoy', ['support.show']] : null,
        ]));
        $account = [
            [route('profile'), 'Profile', 'heroicon-o-user', ['profile']],
            [route('home'), 'Website', 'heroicon-o-globe-alt', []],
        ];
    } elseif ($user->role === 'instructor') {
        $nav = $portalOpen ? [
            [route('instructor.dashboard'), 'Instructor dashboard', 'heroicon-o-home', ['instructor.dashboard', 'instructor.courses.*', 'instructor.materials', 'instructor.assessments', 'instructor.announcements', 'instructor.learners']],
            [route('messages.index'), 'Messages', 'heroicon-o-envelope', ['messages.*']],
        ] : [
            [route('instructor.closed'), 'Instructor workspace', 'heroicon-o-home', ['instructor.closed']],
        ];
        $account = array_values(array_filter([
            [route('profile'), 'Profile', 'heroicon-o-user', ['profile']],
            $portalOpen ? [route('instructor.profile.edit'), 'Public instructor profile', 'heroicon-o-identification', ['instructor.profile.*']] : null,
        ]));
    } else {
        $nav = [
            [route('dashboard'), 'academy.dashboard', 'heroicon-o-home', ['dashboard']],
            [route('courses.index'), 'academy.courses', 'heroicon-o-squares-2x2', ['courses.*']],
            [route('learning.index'), 'academy.learning', 'heroicon-o-book-open', ['learning.*']],
            [route('certificates.index'), 'Certificates', 'heroicon-o-trophy', ['certificates.*']],
            [route('wishlist'), 'Wishlist', 'heroicon-o-heart', ['wishlist']],
            [route('messages.index'), 'Messages', 'heroicon-o-envelope', ['messages.*']],
        ];
        $account = [
            [route('profile'), 'Profile', 'heroicon-o-user', ['profile']],
            [route('payments'), 'Orders & payments', 'heroicon-o-credit-card', ['payments']],
            [route('messages.index', ['to' => 'support']), 'Help & Support', 'heroicon-o-question-mark-circle', ['support.*']],
        ];
    }
    $messagesUrl = route('messages.index');
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
            @foreach($nav as [$url, $label, $icon, $active])
                <a href="{{ $url }}" @if($active && request()->routeIs($active)) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" /><span>{{ __($label) }}</span>@if($url === $messagesUrl && $unreadMessages)<b class="app-nav-badge" aria-label="{{ trans_choice(':count unread conversation|:count unread conversations', $unreadMessages, ['count' => $unreadMessages]) }}">{{ $unreadMessages }}</b>@endif</a>
            @endforeach
        </nav>
        <p class="app-nav-heading">{{ __('Account') }}</p>
        <nav class="app-nav" aria-label="{{ __('Account') }}">
            @foreach($account as [$url, $label, $icon, $active])
                <a href="{{ $url }}" @if($active && request()->routeIs($active)) aria-current="page" @endif><x-dynamic-component :component="$icon" aria-hidden="true" /><span>{{ __($label) }}</span></a>
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
                <details class="app-bell-menu" data-nav-search>
                    <summary class="app-bell" aria-label="{{ $unread ? trans_choice(':count unread notification|:count unread notifications', $unread, ['count' => $unread]) : __('Notifications') }}"><x-heroicon-o-bell aria-hidden="true" />@if($unread)<span class="app-bell-count" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>@endif</summary>
                    <div class="app-bell-panel">
                        <div class="app-bell-head"><strong>{{ __('Notifications') }}</strong>@if($unread)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit">{{ __('Mark all as read') }}</button></form>@endif</div>
                        <ul>
                            @forelse($latestNotifications as $note)
                                <li @class(['unread' => ! $note->read_at])><a href="{{ route('notifications.open', $note->id) }}"><b>{{ $note->data['title'] ?? __('Notification') }}</b><span>{{ \Illuminate\Support\Str::limit($note->data['body'] ?? '', 90) }}</span><small>{{ $note->created_at->diffForHumans() }}</small></a></li>
                            @empty
                                <li class="app-bell-empty">{{ __('You’re all caught up.') }}</li>
                            @endforelse
                        </ul>
                        <a class="app-bell-all" href="{{ route('notifications.index') }}">{{ __('View all notifications') }}</a>
                    </div>
                </details>
                <details class="app-user">
                    <summary><span class="app-avatar">@if($hasAvatar)<img src="{{ route('profile.avatar') }}" alt="">@else{{ $initials }}@endif</span><span class="app-user-name">{{ __('Hi, :name', ['name' => $firstName]) }}</span><x-heroicon-o-chevron-down class="app-user-chevron" aria-hidden="true" /></summary>
                    <div class="app-user-menu"><a href="{{ route('profile') }}">{{ __('Profile') }}</a><a href="{{ route('home') }}">{{ __('Public site') }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('Logout') }}</button></form></div>
                </details>
            </div>
        </header>
        <main id="main" tabindex="-1" class="app-content">{{ $slot }}</main>
    </div>
</div>
<x-chat-widget />
@livewireScripts
</body>
</html>
