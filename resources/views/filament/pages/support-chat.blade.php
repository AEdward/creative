<x-filament-panels::page>
<div class="cla-chat" wire:poll.4s="heartbeat">
    <aside class="cla-card cla-chat-list">
        <nav class="cla-chat-tabs">
            <button type="button" wire:click="$set('show', 'open')" @class(['on' => $show !== 'closed'])>{{ __('Open') }}</button>
            <button type="button" wire:click="$set('show', 'closed')" @class(['on' => $show === 'closed'])>{{ __('Closed') }}</button>
        </nav>
        <ul>
            @forelse($list as $item)
                @php $unread = $item->last_message_at && (! $item->staff_read_at || $item->staff_read_at->lt($item->last_message_at)); @endphp
                <li><button type="button" wire:click="select({{ $item->id }})" @class(['active' => $active?->id === $item->id, 'unread' => $unread])>
                    <b>{{ $item->student?->name }}</b><span>{{ $item->subject }}</span><small>{{ $item->last_message_at?->diffForHumans() }}</small>
                </button></li>
            @empty
                <li class="cla-empty">{{ $show === 'closed' ? __('No closed chats.') : __('No open chats. New ones appear here automatically.') }}</li>
            @endforelse
        </ul>
    </aside>
    <section class="cla-card cla-chat-pane">
        @if($active)
            <header class="cla-chat-head">
                <div><b>{{ $active->student?->name }}</b><small>{{ $active->student?->email }} · {{ $learnerHere ? __('in the chat now') : __('away — your reply is emailed') }}</small></div>
                @if($active->status === 'open')
                    <button type="button" class="cla-outline" wire:click="closeChat" wire:confirm="{{ __('Close this chat? The learner’s next message starts a new one.') }}">{{ __('Close chat') }}</button>
                @else
                    <button type="button" class="cla-outline" wire:click="reopenChat">{{ __('Reopen') }}</button>
                @endif
            </header>
            <ol class="cla-chat-thread">
                @foreach($thread as $message)
                    <li @class(['staff' => $message->sender?->role === 'admin'])><span>{{ $message->sender?->is_owner && ! auth()->user()->is_owner ? __('Support team') : $message->sender?->name }} · {{ $message->created_at->timezone('Africa/Nairobi')->format('M j, H:i') }}</span><p>{{ $message->body }}</p></li>
                @endforeach
            </ol>
            @if($active->status === 'open')
                <form wire:submit="send" class="cla-chat-form">
                    <label class="sr-only" for="chat-reply">{{ __('Reply') }}</label>
                    <textarea id="chat-reply" wire:model="reply" rows="2" maxlength="2000" placeholder="{{ __('Type your reply…') }}" x-on:keydown.enter.prevent="if (! $event.shiftKey) $wire.send()"></textarea>
                    <button class="cla-primary" type="submit">{{ __('Send') }}</button>
                </form>
                @error('reply')<p class="cla-error">{{ $message }}</p>@enderror
            @else
                <p class="cla-muted">{{ __('This chat is closed.') }}</p>
            @endif
        @else
            <div class="cla-empty">{{ __('Choose a chat on the left. While this page is open, learners see that support is online.') }}</div>
        @endif
    </section>
</div>
</x-filament-panels::page>
