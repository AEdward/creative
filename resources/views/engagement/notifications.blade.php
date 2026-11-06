<x-layout :title="__('Notifications')">
<section class="section">
<div class="container feature-page notes-page">
    <div class="notes-head">
        <h1>{{ __('Notifications') }}</h1>
        @if($unreadCount)
            <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="button button-secondary">{{ __('Mark all as read') }}</button></form>
        @endif
    </div>
    @include('engagement.status')

    <nav class="notes-tabs" aria-label="{{ __('Filter notifications') }}">
        <a href="{{ route('notifications.index') }}" @if(! $unreadOnly) aria-current="page" @endif>{{ __('All') }}</a>
        <a href="{{ route('notifications.index', ['show' => 'unread']) }}" @if($unreadOnly) aria-current="page" @endif>{{ __('Unread') }}@if($unreadCount) <span>{{ $unreadCount }}</span>@endif</a>
    </nav>

    <ul class="notes-list">
        @forelse($notifications as $note)
            <li @class(['unread' => ! $note->read_at])>
                <a href="{{ route('notifications.open', $note->id) }}">
                    <b>{{ $note->data['title'] ?? __('Notification') }}</b>
                    <span>{{ $note->data['body'] ?? '' }}</span>
                    <small>{{ $note->created_at->diffForHumans() }} · {{ $note->created_at->format('M j, Y g:i A') }}</small>
                </a>
                @unless($note->read_at)
                    <form method="post" action="{{ route('notifications.read', $note->id) }}">@csrf<button aria-label="{{ __('Mark read') }}" title="{{ __('Mark read') }}">{{ __('Mark read') }}</button></form>
                @endunless
            </li>
        @empty
            <li class="notes-empty">{{ $unreadOnly ? __('No unread notifications. You’re all caught up.') : __('No notifications yet.') }}</li>
        @endforelse
    </ul>
    {{ $notifications->links() }}

    <details class="notes-settings">
        <summary>{{ __('Notification settings') }}</summary>
        <form method="post" action="{{ route('notifications.preferences') }}">@csrf
            @foreach(['announcements' => 'Course announcements', 'support' => 'Support responses', 'learning' => 'Enrollment and certificates', 'email' => 'Send these by email too'] as $key => $label)
                <input type="hidden" name="{{ $key }}" value="0"><label><input type="checkbox" name="{{ $key }}" value="1" @checked($preferences->$key)> {{ __($label) }}</label>
            @endforeach
            <p>{{ __('Preferences control future notifications. Sign-in, verification and password emails are always sent.') }}</p>
            <button class="button">{{ __('Save preferences') }}</button>
        </form>
    </details>
</div>
</section>
</x-layout>
