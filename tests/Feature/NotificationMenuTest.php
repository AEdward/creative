<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EngagementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_lists_latest_notifications_and_opening_one_marks_it_read(): void
    {
        $user = User::factory()->create();
        $user->notifyNow(new EngagementNotification(['title' => 'New announcement', 'body' => 'Lesson 3 is live', 'url' => '/my-learning']));
        $note = $user->notifications()->first();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('app-bell-panel', false)->assertSee('New announcement')->assertSee('View all notifications');
        $this->get(route('notifications.open', $note->id))->assertRedirect('/my-learning');
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_external_links_are_not_followed_and_mark_all_read_works(): void
    {
        $user = User::factory()->create();
        $user->notifyNow(new EngagementNotification(['title' => 'A', 'body' => 'x', 'url' => 'https://evil.example/phish']));
        $user->notifyNow(new EngagementNotification(['title' => 'B', 'body' => 'y', 'url' => '//evil.example']));
        foreach ($user->notifications as $note) {
            $this->actingAs($user)->get(route('notifications.open', $note->id))->assertRedirect(route('notifications.index'));
        }
        $user->notifyNow(new EngagementNotification(['title' => 'C', 'body' => 'z', 'url' => '/']));
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notifyNow(new EngagementNotification(['title' => 'Private', 'body' => 'x', 'url' => '/']));
        $this->actingAs(User::factory()->create())->get(route('notifications.open', $owner->notifications()->first()->id))->assertNotFound();
    }
}
