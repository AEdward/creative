<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_reading_records_recent_learning_without_completion(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'My recent lesson', 'type' => 'text', 'body' => 'Create something', 'published' => true]);
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
        $this->actingAs($user)->get('/learn/'.$course->slug.'/'.$lesson->id)->assertOk();
        $this->assertDatabaseHas('recently_vieweds', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->get('/dashboard')->assertOk()->assertSee('My recent lesson');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('My recent lesson');
    }
}
