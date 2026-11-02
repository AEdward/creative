<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_real_progress_counts_hours_and_latest_certificate(): void
    {
        $user = User::factory()->create(['name' => 'Sara Alemu', 'phone' => '+251 912 345 678', 'bio' => 'I love motion design.']);
        $started = Course::factory()->create(['title' => 'Half done course']);
        $lessons = $started->modules()->create(['title' => 'M'])->lessons();
        $one = $lessons->create(['title' => 'One', 'type' => 'text', 'body' => 'x', 'duration_seconds' => 3600, 'published' => true]);
        $lessons->create(['title' => 'Two', 'type' => 'text', 'body' => 'x', 'duration_seconds' => 3600, 'published' => true]);
        $finished = Course::factory()->create(['title' => 'Finished course']);
        $only = $finished->modules()->create(['title' => 'M'])->lessons()->create(['title' => 'Only', 'type' => 'text', 'body' => 'x', 'duration_seconds' => 1800, 'published' => true]);
        $e1 = Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $started->id]);
        $e2 = Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $finished->id]);
        $e1->progress()->create(['lesson_id' => $one->id, 'completed_at' => now()]);
        $e2->progress()->create(['lesson_id' => $only->id, 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $e2->id, 'reference' => (string) Str::uuid(), 'learner_name' => 'Sara Alemu', 'course_title' => 'Finished course', 'issued_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $response->assertViewHas('stats', ['enrolled' => 2, 'inProgress' => 1, 'completed' => 1, 'hours' => 1.5]);
        $response->assertSee('Sara Alemu')->assertSee('+251 912 345 678')->assertSee('I love motion design.')
            ->assertSee('50%')->assertSee('Continue')->assertSee('Review')->assertSee('Download Certificate')->assertSee('Completed lesson: One');
    }

    public function test_other_learners_data_never_appears_and_empty_state_is_friendly(): void
    {
        $other = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $other->id, 'course_id' => Course::factory()->create(['title' => 'Someone else course'])->id]);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertDontSee('Someone else course')->assertSee('You haven’t enrolled in a course yet.')->assertViewHas('stats', fn ($s) => $s['enrolled'] === 0);
    }

    public function test_profile_saves_phone_and_bio_and_rejects_bad_phone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => 'call me', 'bio' => 'x'])->assertSessionHasErrors('phone');
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '+251 911 000 000', 'bio' => 'Designer'])->assertSessionHasNoErrors();
        $this->assertSame('+251 911 000 000', $user->fresh()->phone);
        $this->assertSame('Designer', $user->fresh()->bio);
    }
}
