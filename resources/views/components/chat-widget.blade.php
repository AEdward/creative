{{-- Live support chat bubble: help search first, then chat with the support team. Not shown to staff. --}}
@unless(auth()->user()?->role === 'admin')
<div class="chat-widget" data-chat data-help-url="{{ route('chat.help') }}" data-state-url="{{ route('chat.state') }}" data-send-url="{{ route('chat.send') }}" data-signed-in="{{ auth()->check() && auth()->user()->hasVerifiedEmail() ? '1' : '0' }}">
    <button type="button" class="chat-bubble" data-chat-toggle aria-expanded="false" aria-controls="chat-panel">
        <x-heroicon-s-chat-bubble-left-right aria-hidden="true" />
        <span class="sr-only">{{ __('Help and support chat') }}</span>
        <span class="chat-bubble-badge" data-chat-badge hidden></span>
    </button>
    <section class="chat-panel" id="chat-panel" role="dialog" aria-label="{{ __('Help and support') }}" hidden>
        <header class="chat-head">
            <div><strong>{{ __('Help & support') }}</strong><span class="chat-status" data-chat-status><i></i>{{ __('Checking…') }}</span></div>
            <button type="button" class="chat-close" data-chat-toggle aria-label="{{ __('Close') }}"><x-heroicon-o-x-mark aria-hidden="true" /></button>
        </header>
        <div class="chat-body" data-chat-body>
            <div class="chat-help" data-chat-help>
                <label class="sr-only" for="chat-search">{{ __('Search help') }}</label>
                <input id="chat-search" type="search" placeholder="{{ __('Search help, e.g. payment, certificate…') }}" data-chat-search autocomplete="off">
                <ul class="chat-articles" data-chat-articles aria-live="polite"></ul>
            </div>
            <ol class="chat-thread" data-chat-thread aria-live="polite"></ol>
        </div>
        <footer class="chat-foot">
            @if(auth()->check() && auth()->user()->hasVerifiedEmail())
                <form class="chat-form" data-chat-form>
                    <label class="sr-only" for="chat-input">{{ __('Message') }}</label>
                    <textarea id="chat-input" rows="1" maxlength="2000" placeholder="{{ __('Didn’t find it? Write to us…') }}" data-chat-input required></textarea>
                    <button type="submit" class="chat-send" aria-label="{{ __('Send') }}"><x-heroicon-s-paper-airplane aria-hidden="true" /></button>
                </form>
                <p class="chat-hint" data-chat-hint></p>
            @else
                <p class="chat-signin">{{ __('Didn’t find an answer?') }} <a href="{{ url('/login') }}" data-login-open>{{ __('Sign in to chat with us') }}</a></p>
            @endif
        </footer>
    </section>
</div>
<div hidden id="chat-labels" data-labels="{{ json_encode([
    'online' => __('Support is online'), 'offline' => __('We’ll reply soon'), 'you' => __('You'),
    'offlineHint' => __('Our team is away right now. Leave a message — we’ll reply here and email you.'),
    'closedHint' => __('Your last chat was closed. Sending a message starts a new one.'),
    'noResults' => __('No matching help. Write to us below.'), 'failed' => __('Message not sent. Check your connection and try again.'),
], JSON_UNESCAPED_UNICODE) }}"></div>
@endunless
