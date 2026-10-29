<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(Course $course, array $attributes = []): Lesson
    {
        return $course->modules()->create(['title' => 'Introduction', 'sort_order' => 1])->lessons()->create(array_merge(['title' => 'Start creating', 'type' => 'text', 'body' => 'Your first creative exercise.', 'published' => true], $attributes));
    }

    public function test_free_enrollment_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_free' => true, 'price_minor' => 0]);
        $this->actingAs($user)->post('/courses/'.$course->slug.'/enroll')->assertRedirect('/my-learning');
        $this->post('/courses/'.$course->slug.'/enroll')->assertRedirect('/my-learning');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_paid_and_unpublished_courses_cannot_be_enrolled_through_free_endpoint(): void
    {
        $this->actingAs(User::factory()->create());
        $paid = Course::factory()->create(['is_free' => false]);
        $draft = Course::factory()->create(['is_free' => true, 'status' => 'draft']);
        $this->post('/courses/'.$paid->slug.'/enroll')->assertForbidden();
        $this->post('/courses/'.$draft->slug.'/enroll')->assertNotFound();
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_unenrolled_student_cannot_access_protected_lesson(): void
    {
        $course = Course::factory()->create();
        $lesson = $this->lesson($course);
        $this->actingAs(User::factory()->create())->get('/learn/'.$course->slug.'/'.$lesson->id)->assertForbidden();
    }

    public function test_published_preview_is_public_but_draft_preview_is_not(): void
    {
        $course = Course::factory()->create();
        $lesson = $this->lesson($course, ['is_preview' => true]);
        $this->get('/learn/'.$course->slug.'/'.$lesson->id)->assertOk()->assertSee('Your first creative exercise.');
        $course->update(['status' => 'draft']);
        $this->get('/learn/'.$course->slug.'/'.$lesson->id)->assertNotFound();
    }

    public function test_opening_a_lesson_does_not_mark_it_completed(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = $this->lesson($course);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $this->actingAs($user)->get('/learn/'.$course->slug.'/'.$lesson->id)->assertOk();
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->post('/learn/'.$course->slug.'/'.$lesson->id.'/complete')->assertRedirect();
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id]);
        $this->assertNotNull(LessonProgress::first()->completed_at);
    }

    public function test_expired_and_revoked_memberships_do_not_grant_access_or_reactivate(): void
    {
        $course = Course::factory()->create(['is_free' => true]);
        $lesson = $this->lesson($course);
        foreach ([['status' => 'revoked'], ['status' => 'active', 'expires_at' => now()->subDay()]] as $state) {
            $user = User::factory()->create();
            Enrollment::factory()->create(array_merge(['user_id' => $user->id, 'course_id' => $course->id], $state));
            $this->actingAs($user)->get('/learn/'.$course->slug.'/'.$lesson->id)->assertForbidden();
            $this->post('/courses/'.$course->slug.'/enroll')->assertForbidden();
        }
    }

    public function test_lesson_must_belong_to_requested_course_and_be_published(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        $lesson = $this->lesson($other, ['is_preview' => true]);
        $this->get('/learn/'.$course->slug.'/'.$lesson->id)->assertNotFound();
        $lesson->update(['published' => false]);
        $this->get('/learn/'.$other->slug.'/'.$lesson->id)->assertNotFound();
    }

    public function test_progress_is_owned_and_cannot_exceed_duration(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = $this->lesson($course, ['duration_seconds' => 120]);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $this->actingAs($other)->postJson('/learn/'.$course->slug.'/'.$lesson->id.'/progress', ['position_seconds' => 30])->assertForbidden();
        $this->actingAs($user)->postJson('/learn/'.$course->slug.'/'.$lesson->id.'/progress', ['position_seconds' => 121])->assertUnprocessable();
        $this->postJson('/learn/'.$course->slug.'/'.$lesson->id.'/progress', ['position_seconds' => 30])->assertOk();
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'position_seconds' => 30]);
        $this->assertNull(LessonProgress::first()->completed_at);
    }

    public function test_my_learning_only_shows_current_users_enrollments(): void
    {
        $user = User::factory()->create();
        $mine = Course::factory()->create(['title' => 'My creative course']);
        $other = Course::factory()->create(['title' => 'Somebody elses course']);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $mine->id]);
        Enrollment::factory()->create(['course_id' => $other->id]);
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('My creative course')->assertDontSee('Somebody elses course');
    }
}
