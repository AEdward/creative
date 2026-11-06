<?php

namespace Tests\Feature;

use App\Console\Commands\DemoCourse;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCourseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_course_can_be_added_tried_and_removed(): void
    {
        $this->seed(CategorySeeder::class);
        User::factory()->create(['role' => 'admin']);

        $this->artisan('academy:demo-course')->assertSuccessful();
        $this->artisan('academy:demo-course')->expectsOutputToContain('already exists')->assertSuccessful();
        $course = Course::where('slug', DemoCourse::SLUG)->firstOrFail();
        $this->assertSame(5000, $course->price_minor);
        $this->assertSame(4, $course->modules()->withCount('lessons')->get()->sum('lessons_count'));
        $this->get(route('courses.show', $course->slug))->assertOk()->assertSee('Demo: Creative Lab test course')->assertSee('Welcome');

        // A learner who joined it during testing is removed with it.
        Enrollment::factory()->create(['course_id' => $course->id]);
        $this->artisan('academy:demo-course --remove')->assertSuccessful();
        $this->assertModelMissing($course);
        $this->assertDatabaseCount('enrollments', 0);
    }
}
