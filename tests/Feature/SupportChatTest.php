<?php

namespace Tests\Feature;

use App\Filament\Pages\SupportChat as SupportChatPage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\SupportChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_search_help_and_are_asked_to_sign_in_to_chat(): void
    {
        $this->getJson(route('chat.help', ['q' => 'payment confirmation']))->assertOk()->assertJsonPath('articles.0.q', 'How long does payment confirmation take?');
        $this->getJson(route('chat.state'))->assertOk()->assertJson(['signedIn' => false, 'online' => false]);
        $this->postJson(route('chat.send'), ['body' => 'Hi'])->assertUnauthorized();
        $this->get('/')->assertOk()->assertSee('data-chat', false)->assertSee('Sign in to chat with us');
    }

    public function test_live_chat_between_a_learner_and_support_staff(): void
    {
        $learner = User::factory()->create(['name' => 'Selam']);
        $support = User::factory()->create(['role' => 'admin', 'staff_role' => 'support']);
        $finance = User::factory()->create(['role' => 'admin', 'staff_role' => 'finance']);

        // Nobody online: the first message emails support staff only.
        $this->actingAs($learner)->postJson(route('chat.send'), ['body' => 'My video will not play'])->assertCreated()->assertJson(['online' => false]);
        $chat = Conversation::firstOrFail();
        $this->assertSame(['support', 'open', 'My video will not play'], [$chat->type, $chat->status, $chat->subject]);
        $this->assertSame(1, $support->notifications()->count());
        $this->assertSame(0, $finance->notifications()->count());

        // The learner leaves for a few minutes; staff open the chat page, show as online, and reply.
        $this->travel(3)->minutes();
        $this->actingAs($support)->get('/admin/support-chat')->assertOk()->assertSee('Selam');
        Livewire::test(SupportChatPage::class)->call('select', $chat->id)->assertSee('My video will not play')
            ->set('reply', 'Try “Refresh playback” under the video.')->call('send')->assertHasNoErrors();
        $this->assertTrue(app(SupportChat::class)->staffOnline());
        $this->assertSame(1, $learner->notifications()->count()); // learner had left the chat → emailed

        $state = $this->actingAs($learner)->getJson(route('chat.state'))->assertOk()->json();
        $this->assertTrue($state['online']);
        $this->assertSame(['My video will not play', 'Try “Refresh playback” under the video.'], array_column($state['messages'], 'body'));
        $this->assertSame([true, false], array_column($state['messages'], 'mine'));
        $after = $state['messages'][0]['id'];
        $this->assertCount(1, $this->getJson(route('chat.state', ['after' => $after]))->json('messages'));

        // Learner is in the chat now, so the next reply isn't emailed.
        $this->actingAs($support);
        Livewire::test(SupportChatPage::class)->call('select', $chat->id)->set('reply', 'Fixed?')->call('send');
        $this->assertSame(1, $learner->notifications()->count());

        // Closing: the next message starts a new chat; the old one can't be replied to.
        Livewire::test(SupportChatPage::class)->call('select', $chat->id)->call('closeChat');
        $this->assertSame('closed', $chat->fresh()->status);
        $this->actingAs($learner)->post(route('messages.reply', $chat), ['body' => 'Still broken'])->assertSessionHasErrors('body');
        $this->postJson(route('chat.send'), ['body' => 'New question'])->assertCreated();
        $this->assertSame(2, Conversation::where('student_id', $learner->id)->count());

        // Only support staff (and super admins) can use the staff chat.
        $this->actingAs($finance)->get('/admin/support-chat')->assertForbidden();
        $this->actingAs($support)->getJson(route('chat.state'))->assertForbidden();
    }
}
