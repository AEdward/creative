<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\EngagementNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Live support chat on top of support conversations. Shared hosting has no websockets, so both sides
 * poll every few seconds while the chat is open; "online" means a support staff member was active
 * in the last 3 minutes.
 */
class SupportChat
{
    private const STAFF_SEEN = 'chat:staff-seen';

    private const LEARNER_SEEN = 'chat:learner-seen:';

    public function markStaffActive(User $staff): void
    {
        $seen = Cache::get(self::STAFF_SEEN, []);
        $seen[$staff->id] = now()->timestamp;
        Cache::put(self::STAFF_SEEN, array_filter($seen, fn ($t) => $t > now()->subHour()->timestamp), now()->addHour());
    }

    public function staffOnline(): bool
    {
        return collect(Cache::get(self::STAFF_SEEN, []))->contains(fn ($t) => $t > now()->subMinutes(3)->timestamp);
    }

    public function markLearnerActive(User $learner): void
    {
        Cache::put(self::LEARNER_SEEN.$learner->id, now()->timestamp, now()->addMinutes(10));
    }

    public function learnerInChat(User $learner): bool
    {
        return (int) Cache::get(self::LEARNER_SEEN.$learner->id, 0) > now()->subMinutes(2)->timestamp;
    }

    /** The learner's open support chat, if any. */
    public function openChat(User $learner): ?Conversation
    {
        return Conversation::where('student_id', $learner->id)->where('type', 'support')->where('status', 'open')->latest('id')->first();
    }

    public function messages(Conversation $chat, int $afterId = 0)
    {
        return $chat->messages()->with('sender:id,name,role')->where('id', '>', $afterId)->orderBy('id')->limit(100)->get()
            ->map(fn (ConversationMessage $m) => ['id' => $m->id, 'body' => $m->body, 'mine' => null, 'staff' => $m->sender?->role === 'admin', 'name' => $m->sender?->role === 'admin' ? __('Support team') : $m->sender?->name, 'at' => $m->created_at->timezone('Africa/Nairobi')->format('H:i')]);
    }

    /** Learner sends a message: continues the open chat or starts a new one. */
    public function learnerSends(User $learner, string $body): Conversation
    {
        $chat = DB::transaction(function () use ($learner, $body) {
            User::whereKey($learner->id)->lockForUpdate()->first();
            $chat = $this->openChat($learner) ?? Conversation::create([
                'student_id' => $learner->id, 'type' => 'support', 'status' => 'open', 'subject' => Str::limit(trim($body), 70),
            ]);
            $chat->messages()->create(['sender_id' => $learner->id, 'body' => $body]);
            $chat->forceFill(['last_message_at' => now(), 'student_read_at' => now()])->save();

            return $chat;
        });
        $this->markLearnerActive($learner);
        // Staff watching the chat page see it live; the alert email is for when nobody is.
        if (! $this->staffOnline()) {
            $staff = User::staffFor('support');
            if ($staff->isNotEmpty()) {
                Notification::send($staff, new EngagementNotification(['title' => __('New support chat message from :name', ['name' => $learner->name]), 'body' => Str::limit($body, 200), 'url' => '/admin/support-chat?chat='.$chat->id]));
            }
        }

        return $chat;
    }

    public function staffReplies(User $staff, Conversation $chat, string $body): void
    {
        abort_unless($chat->type === 'support' && $staff->staffCan('support'), 403);
        abort_if($chat->status !== 'open', 422, 'This chat is closed.');
        DB::transaction(function () use ($staff, $chat, $body) {
            $chat->messages()->create(['sender_id' => $staff->id, 'body' => $body]);
            $chat->forceFill(['last_message_at' => now(), 'staff_read_at' => now()])->save();
        });
        $this->markStaffActive($staff);
        $learner = $chat->student;
        // Only email when the learner has left the chat; otherwise they see the reply live.
        if ($learner && ! $this->learnerInChat($learner)) {
            $learner->notify(new EngagementNotification(['title' => __('Support replied to your chat'), 'body' => Str::limit($body, 200), 'url' => route('messages.show', $chat, false)]));
        }
    }

    public function close(User $staff, Conversation $chat): void
    {
        abort_unless($chat->type === 'support' && $staff->staffCan('support'), 403);
        $chat->forceFill(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $staff->id])->save();
    }

    public function reopen(User $staff, Conversation $chat): void
    {
        abort_unless($chat->type === 'support' && $staff->staffCan('support'), 403);
        $chat->forceFill(['status' => 'open', 'closed_at' => null, 'closed_by' => null])->save();
    }
}
