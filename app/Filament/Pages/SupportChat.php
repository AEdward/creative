<?php

namespace App\Filament\Pages;

use App\Models\Conversation;
use App\Services\SupportChat as Chat;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** Live support chat for support staff: open chats on the left, the conversation on the right. */
class SupportChat extends Page
{
    protected string $view = 'filament.pages.support-chat';

    protected static ?string $title = 'Support chat';

    protected static ?string $slug = 'support-chat';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 10;

    #[Url]
    public ?int $chat = null;

    #[Url]
    public string $show = 'open';

    public string $reply = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'support');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = Conversation::where('type', 'support')->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('staff_read_at')->orWhereColumn('staff_read_at', '<', 'last_message_at'))->count();

        return $waiting ? (string) $waiting : null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        app(Chat::class)->markStaffActive(auth()->user());
    }

    /** Called by wire:poll — keeps the "online" dot green for learners while staff have this page open. */
    public function heartbeat(): void
    {
        app(Chat::class)->markStaffActive(auth()->user());
    }

    public function select(int $id): void
    {
        $this->chat = $id;
        $this->reply = '';
    }

    public function send(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['reply' => ['required', 'string', 'max:2000']]);
        app(Chat::class)->staffReplies(auth()->user(), $this->active(), trim($this->reply));
        $this->reply = '';
    }

    public function closeChat(): void
    {
        app(Chat::class)->close(auth()->user(), $this->active());
    }

    public function reopenChat(): void
    {
        app(Chat::class)->reopen(auth()->user(), $this->active());
    }

    private function active(): Conversation
    {
        return Conversation::where('type', 'support')->findOrFail($this->chat);
    }

    protected function getViewData(): array
    {
        $list = Conversation::where('type', 'support')->where('status', $this->show === 'closed' ? 'closed' : 'open')
            ->with('student:id,name,email')->latest('last_message_at')->limit(50)->get();
        $active = $this->chat ? Conversation::where('type', 'support')->with('student:id,name,email')->find($this->chat) : null;
        if ($active) {
            $active->forceFill(['staff_read_at' => now()])->saveQuietly();
        }

        return [
            'list' => $list,
            'active' => $active,
            'thread' => $active ? $active->messages()->with('sender:id,name,role')->oldest('id')->limit(300)->get() : collect(),
            'learnerHere' => $active?->student ? app(Chat::class)->learnerInChat($active->student) : false,
        ];
    }
}
