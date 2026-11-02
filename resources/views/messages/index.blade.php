<x-layout :title="__('Messages')">
@php $me = auth()->user(); $composing = ! $active; @endphp
<div class="msg-head">
    <div><h1>{{ __('Messages') }}</h1><p>{{ __('Ask your teachers about a course, or get help from the support team.') }}</p></div>
    @unless($composing)<a class="msg-new-btn" href="{{ route('messages.index') }}"><x-heroicon-o-pencil-square aria-hidden="true" />{{ __('New message') }}</a>@endunless
</div>
@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif

<div class="msg-layout">
    <aside class="msg-list" aria-label="{{ __('Conversations') }}">
        <ul>
            @forelse($conversations as $item)
                @php $unread = $item->isUnreadFor($me); @endphp
                <li><a href="{{ route('messages.show', $item) }}" @if($active?->is($item)) aria-current="page" @endif @class(['unread' => $unread])>
                    <span class="msg-avatar {{ $item->type === 'support' && $item->isStudent($me) ? 'support' : '' }}" aria-hidden="true">@if($item->type === 'support' && $item->isStudent($me))<x-heroicon-o-lifebuoy />@else{{ mb_substr($item->counterpartFor($me), 0, 1) }}@endif</span>
                    <span class="msg-meta"><b>{{ $item->counterpartFor($me) }}</b><span>{{ $item->subject }}</span><small>{{ $item->type === 'support' ? __('Support') : $item->course?->title }}</small></span>
                    <time datetime="{{ $item->last_message_at?->toIso8601String() }}">{{ $item->last_message_at?->diffForHumans(short: true) }}</time>
                    @if($unread)<span class="sr-only">{{ __('Unread') }}</span>@endif
                </a></li>
            @empty
                <li class="msg-none">{{ __('No conversations yet.') }}</li>
            @endforelse
        </ul>
        {{ $conversations->links('pagination::simple-tailwind') }}
    </aside>

    <section class="msg-pane">
        @if($active)
            <header class="msg-thread-head">
                <div><h2>{{ $active->subject }}</h2><p>{{ $active->counterpartFor($me) }} · {{ $active->type === 'support' ? __('Support team') : $active->course?->title }}</p></div>
            </header>
            <ol class="msg-thread">
                @foreach($thread as $message)
                    @php $mine = $message->sender_id === $me->id; @endphp
                    <li @class(['mine' => $mine]) @if($loop->last) id="latest" @endif>
                        <div class="msg-bubble"><p class="preserve-lines">{{ $message->body }}</p></div>
                        <small>{{ $mine ? __('You') : $message->sender?->name }} · <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('M j, g:i A') }}</time></small>
                    </li>
                @endforeach
            </ol>
            <form method="POST" action="{{ route('messages.reply', $active) }}" class="msg-reply">@csrf
                <label class="sr-only" for="reply-body">{{ __('Your reply') }}</label>
                <textarea id="reply-body" name="body" rows="2" maxlength="5000" required placeholder="{{ __('Write a reply…') }}">{{ is_string(old('body')) ? old('body') : '' }}</textarea>
                <button type="submit" class="msg-send"><x-heroicon-o-paper-airplane aria-hidden="true" /><span>{{ __('Send') }}</span></button>
                @error('body')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror
            </form>
        @else
            <form method="POST" action="{{ route('messages.store') }}" class="msg-compose">@csrf
                <h2>{{ __('Start a conversation') }}</h2>
                <fieldset><legend>{{ __('Who do you want to talk to?') }}</legend>
                    <label class="msg-choice"><input type="radio" name="type" value="instructor" @checked(old('type', $startType) === 'instructor' || (! old('type') && ! $startType && $courses->isNotEmpty())) @disabled($courses->isEmpty())><span><b>{{ __('A teacher') }}</b><small>{{ $courses->isEmpty() ? __('Enroll in a course to message its teacher.') : __('Ask about one of your courses.') }}</small></span></label>
                    <label class="msg-choice"><input type="radio" name="type" value="support" @checked(old('type', $startType) === 'support' || $courses->isEmpty())><span><b>{{ __('Support team') }}</b><small>{{ __('Account, payment or technical help.') }}</small></span></label>
                </fieldset>
                @if($courses->isNotEmpty())
                    <div class="form-field"><label for="msg-course">{{ __('Course') }}</label>
                        <select id="msg-course" name="course_id">@foreach($courses as $course)<option value="{{ $course->id }}" @selected((int) old('course_id', $startCourse) === $course->id)>{{ $course->title }} — {{ $course->instructor?->name }}</option>@endforeach</select>
                        @error('course_id')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                @endif
                <div class="form-field"><label for="msg-subject">{{ __('Subject') }}</label><input id="msg-subject" name="subject" maxlength="160" required value="{{ is_string(old('subject')) ? old('subject') : '' }}">@error('subject')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                <div class="form-field"><label for="msg-body">{{ __('Message') }}</label><textarea id="msg-body" name="body" rows="6" maxlength="5000" required>{{ is_string(old('body')) ? old('body') : '' }}</textarea>@error('body')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                <button type="submit" class="msg-send"><x-heroicon-o-paper-airplane aria-hidden="true" /><span>{{ __('Send message') }}</span></button>
            </form>
        @endif
    </section>
</div>
</x-layout>
