<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorLearnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_sees_only_owned_course_learners_and_current_published_progress(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $student = User::factory()->create(['name' => 'My learner']);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $module = $course->modules()->create(['title' => 'Start']);
        $first = $module->lessons()->create(['title' => 'First', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        $module->lessons()->create(['title' => 'Second', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        $hidden = $module->lessons()->create(['title' => 'Hidden', 'type' => 'text', 'body' => 'Read', 'published' => false]);
        foreach ([$first, $hidden] as $lesson) {
            LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
        }
        Enrollment::factory()->create(['user_id' => User::factory()->create(['name' => 'Unrelated learner'])->id]);
        $this->actingAs($owner)->get('/instructor/courses/'.$course->slug.'/learners')->assertOk()->assertSee('My learner')->assertSee('50%')->assertDontSee('Unrelated learner')->assertDontSee($student->email);
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/instructor/courses/'.$course->slug.'/learners')->assertForbidden();
        $this->actingAs($student)->get('/instructor/courses/'.$course->slug.'/learners')->assertForbidden();
    }
}
