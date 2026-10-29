<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\NotificationPreference;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('reviews.index')) {
            Route::middleware('web')->group(base_path('routes/engagement.php'));
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_review_requires_completed_published_lesson_and_moderation(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $url = '/courses/'.$course->slug.'/reviews';
        $this->actingAs($user)->postJson($url, ['rating' => 5, 'body' => 'Useful course'])->assertForbidden();
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'published' => true]);
        LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->post($url, ['rating' => 5, 'body' => 'Useful course'])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('No approved reviews')->assertDontSee('Useful course');
        $review = CourseReview::first();
        $this->post($url, ['rating' => 1, 'body' => 'Duplicate review'])->assertSessionHasErrors();
        $this->post('/academy-admin/reviews/'.$review->id, ['status' => 'approved'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/academy-admin/reviews/'.$review->id, ['status' => 'approved'])->assertRedirect();
        $this->get($url)->assertSee('Useful course')->assertSee('5.0');
    }

    public function test_announcement_delivery_is_scoped_and_preferences_are_respected(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $student = User::factory()->create();
        $expired = User::factory()->create();
        $muted = User::factory()->create();
        $stranger = User::factory()->create();
        foreach ([$student, $expired, $muted] as $learner) {
            Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id, 'expires_at' => $learner->is($expired) ? now()->subDay() : null]);
        }
        NotificationPreference::create(['user_id' => $muted->id, 'announcements' => false, 'support' => true]);
        $url = '/instructor/courses/'.$course->slug.'/announcements';
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->actingAs($student)->get($url)->assertForbidden();
        $this->actingAs($stranger)->post($url, ['title' => 'Hello', 'body' => 'Class update'])->assertForbidden();
        $this->actingAs($owner)->post($url, ['title' => 'Hello', 'body' => 'Class update'])->assertRedirect();
        $this->assertCount(1, $student->notifications);
        $this->assertCount(0, $expired->notifications);
        $this->assertCount(0, $muted->notifications);
        $this->assertCount(0, $stranger->notifications);
        $this->actingAs($stranger)->post('/notifications/'.$student->notifications->first()->id.'/read')->assertNotFound();
        $this->actingAs($student)->get('/notifications')->assertSee('Class update');
    }

    public function test_support_tickets_are_private_with_admin_reply_close_and_owner_reopen(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($owner)->post('/support', ['subject' => 'Private issue', 'body' => 'Private question'])->assertRedirect();
        $ticket = SupportTicket::first();
        $this->actingAs($other)->get('/support/'.$ticket->id)->assertNotFound();
        $this->post('/support/'.$ticket->id.'/replies', ['body' => 'Intrusion'])->assertNotFound();
        $this->get('/support')->assertDontSee('Private issue');
        $this->actingAs($admin)->post('/support/'.$ticket->id.'/replies', ['body' => 'Staff response'])->assertRedirect();
        $this->post('/support/'.$ticket->id.'/status', ['status' => 'closed'])->assertRedirect();
        $this->actingAs($owner)->postJson('/support/'.$ticket->id.'/replies', ['body' => 'Closed reply'])->assertUnprocessable();
        $this->post('/support/'.$ticket->id.'/status', ['status' => 'open'])->assertRedirect();
        $this->get('/support/'.$ticket->id)->assertSee('Staff response');
        $this->get('/notifications')->assertOk();
        $this->post('/notification-preferences', ['announcements' => 0, 'support' => 0])->assertRedirect();
    }

    public function test_expired_and_unverified_accounts_cannot_review_and_unpublished_completion_is_insufficient(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'published' => false]);
        LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $url = '/courses/'.$course->slug.'/reviews';
        $data = ['rating' => 4, 'body' => 'Helpful course'];
        $this->actingAs($user)->post($url, $data)->assertForbidden();
        $lesson->update(['published' => true]);
        $enrollment->update(['expires_at' => now()->subMinute()]);
        $this->post($url, $data)->assertForbidden();
        $enrollment->update(['expires_at' => null]);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->post($url, $data)->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_admin_policy_controls_future_reviews_without_publishing_pending_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'published' => true]);
        LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->actingAs($student)->get('/academy-admin/reviews')->assertForbidden();
        $this->post('/academy-admin/reviews/settings', ['moderation_required' => 0])->assertForbidden();
        $this->actingAs($admin)->get('/academy-admin/reviews')->assertOk();
        $this->post('/academy-admin/reviews/settings', ['moderation_required' => 0])->assertRedirect();
        $this->actingAs($student)->post('/courses/'.$course->slug.'/reviews', ['rating' => 4, 'body' => 'Helpful course'])->assertRedirect();
        $this->assertDatabaseHas('course_reviews', ['status' => 'approved', 'rating' => 4]);
    }

    public function test_support_and_notifications_admin_privacy_and_disabled_accounts(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $stranger = User::factory()->create();
        $this->actingAs($owner)->post('/support', ['subject' => 'Private thread', 'body' => 'Only our staff should see this']);
        $ticket = SupportTicket::first();
        $this->actingAs($stranger)->get('/academy-admin/support')->assertForbidden();
        $this->post('/support/'.$ticket->id.'/status', ['status' => 'closed'])->assertNotFound();
        $this->actingAs($owner)->post('/notification-preferences', ['announcements' => 1, 'support' => 0])->assertRedirect();
        $this->actingAs($admin)->get('/academy-admin/support')->assertOk()->assertSee('Private thread');
        $this->get('/support/'.$ticket->id)->assertOk();
        $this->post('/support/'.$ticket->id.'/replies', ['body' => 'A private response'])->assertRedirect();
        $this->assertCount(0, $owner->notifications);
        $this->assertCount(0, $stranger->notifications);
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($owner)->get('/support/'.$ticket->id)->assertForbidden();
        $this->get('/notifications')->assertRedirect();
    }
}
