<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_enrollment_sends_one_database_notification_and_respects_preferences(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_free' => true]);
        $this->actingAs($user)->post('/courses/'.$course->slug.'/enroll')->assertRedirect();
        $this->post('/courses/'.$course->slug.'/enroll')->assertRedirect();
        $this->assertSame(1, $user->notifications()->count());
        $muted = User::factory()->create();
        NotificationPreference::create(['user_id' => $muted->id, 'learning' => false]);
        $this->actingAs($muted)->post('/courses/'.$course->slug.'/enroll')->assertRedirect();
        $this->assertSame(0, $muted->notifications()->count());
    }
}
