<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private function enrolled(): array
    {
        $teacher = User::factory()->create(['role' => 'instructor', 'name' => 'Teacher Tsion']);
        $course = Course::factory()->create(['instructor_id' => $teacher->id, 'title' => 'Motion basics']);
        $student = User::factory()->create(['name' => 'Learner Abel']);
        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        return [$teacher, $course, $student];
    }

    public function test_learner_can_message_their_teacher_and_the_teacher_replies(): void
    {
        [$teacher, $course, $student] = $this->enrolled();
        $this->actingAs($student)->post(route('messages.store'), ['type' => 'instructor', 'course_id' => $course->id, 'subject' => 'Export settings?', 'body' => 'Which codec should I use?'])->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->assertSame($teacher->id, $conversation->instructor_id);
        $this->assertSame(1, $teacher->unreadNotifications()->count());

        $this->actingAs($teacher)->get('/messages')->assertOk()->assertSee('Learner Abel')->assertSee('Export settings?');
        $this->get(route('messages.show', $conversation))->assertOk()->assertSee('Which codec should I use?');
        $this->post(route('messages.reply', $conversation), ['body' => 'Use H.264.'])->assertRedirect();
        $this->assertSame(1, $student->unreadNotifications()->count());
        $this->actingAs($student)->get(route('messages.show', $conversation))->assertOk()->assertSee('Use H.264.')->assertSee('Teacher Tsion');
    }

    public function test_learner_cannot_message_teachers_of_courses_they_are_not_enrolled_in(): void
    {
        [, , $student] = $this->enrolled();
        $other = Course::factory()->create();
        $this->actingAs($student)->post(route('messages.store'), ['type' => 'instructor', 'course_id' => $other->id, 'subject' => 'Hi', 'body' => 'x'])->assertStatus(422);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_support_threads_reach_admins_and_stay_private_from_others(): void
    {
        [$teacher, , $student] = $this->enrolled();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($student)->post(route('messages.store'), ['type' => 'support', 'subject' => 'Payment question', 'body' => 'I paid by CBE'])->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->assertSame(1, $admin->unreadNotifications()->count());

        $this->actingAs($admin)->get(route('messages.show', $conversation))->assertOk()->assertSee('I paid by CBE');
        $this->actingAs($teacher)->get(route('messages.show', $conversation))->assertNotFound();
        $this->post(route('messages.reply', $conversation), ['body' => 'sneaky'])->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('messages.show', $conversation))->assertNotFound();
        $this->get('/messages')->assertOk()->assertDontSee('Payment question');
    }

    public function test_sidebar_shows_unread_count_and_reading_clears_it(): void
    {
        [$teacher, $course, $student] = $this->enrolled();
        $this->actingAs($student)->post(route('messages.store'), ['type' => 'instructor', 'course_id' => $course->id, 'subject' => 'Q', 'body' => 'x']);
        $this->actingAs($teacher)->get('/dashboard')->assertSee('app-nav-badge', false);
        $this->get(route('messages.show', Conversation::first()));
        $this->get('/dashboard')->assertDontSee('app-nav-badge', false);
    }
}
