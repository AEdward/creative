<div class="cla-thread">
    <article class="cla-thread-msg">
        <header><b>{{ $ticket->user?->name }}</b><small>{{ $ticket->created_at->format('M j, Y H:i') }}</small></header>
        <p class="preserve-lines">{{ $ticket->body }}</p>
    </article>
    @foreach($ticket->replies as $reply)
        <article @class(['cla-thread-msg', 'staff' => $reply->user_id !== $ticket->user_id])>
            <header><b>{{ $reply->user?->name }}</b><small>{{ $reply->created_at->format('M j, Y H:i') }}</small></header>
            <p class="preserve-lines">{{ $reply->body }}</p>
        </article>
    @endforeach
</div>
