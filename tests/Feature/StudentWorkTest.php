<?php

namespace Tests\Feature;

use App\Filament\Resources\StudentWork\Pages\ManageStudentWork;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Media\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudentWorkTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAssignment(): array
    {
        $course = Course::factory()->create();
        $work = Assessment::create(['course_id' => $course->id, 'type' => 'assignment', 'title' => 'Showreel', 'instructions' => 'Upload your reel', 'pass_percent' => 70, 'attempt_limit' => 0, 'advance_on' => 'grade']);
        $step = $course->modules()->create(['title' => 'C'])->lessons()->create(['title' => 'Showreel', 'type' => 'assignment', 'assessment_id' => $work->id, 'published' => true]);
        $learner = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id]);

        return [$course, $work, $step, $learner];
    }

    public function test_large_work_uploads_straight_to_r2_and_is_verified(): void
    {
        [$course, $work, $step, $learner] = $this->setUpAssignment();
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('available')->andReturn(true);
        $storage->shouldReceive('uploadUrl')->andReturn(['url' => 'https://r2.example.test/put', 'headers' => ['Content-Type' => ['video/mp4']]]);
        $this->app->instance(MediaStorage::class, $storage);
        $this->actingAs($learner);

        $this->postJson(route('assignments.upload-url', [$course, $work]), ['filename' => 'virus.exe', 'bytes' => 100])->assertUnprocessable();
        $this->postJson(route('assignments.upload-url', [$course, $work]), ['filename' => 'reel.mp4', 'bytes' => 600 * 1024 * 1024])->assertUnprocessable();
        $start = $this->postJson(route('assignments.upload-url', [$course, $work]), ['filename' => 'reel.mp4', 'bytes' => 200 * 1024 * 1024])->assertOk()->json();
        $this->assertSame('https://r2.example.test/put', $start['upload']['url']);

        // Upload never arrived (wrong size) → refused; arrived → recorded.
        $storage->shouldReceive('objectSize')->once()->andReturn(null);
        $this->post(route('assignments.submit', [$course, $work]), ['upload_token' => $start['token']])->assertSessionHasErrors('file');
        $storage->shouldReceive('objectSize')->once()->andReturn(200 * 1024 * 1024);
        $this->post(route('assignments.submit', [$course, $work]), ['upload_token' => $start['token']])->assertSessionHasNoErrors();
        $submission = AssignmentSubmission::firstOrFail();
        $this->assertSame(['r2', 'reel.mp4', 'video/mp4', 'pending'], [$submission->disk, $submission->original_name, $submission->mime, $submission->status]);
        $this->assertStringStartsWith('submissions/'.$course->id.'/', $submission->path);

        // A token can't be reused for someone else's assignment.
        $other = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $other->id, 'course_id' => $course->id]);
        $this->actingAs($other)->post(route('assignments.submit', [$course, $work]), ['upload_token' => $start['token']])->assertSessionHasErrors('file');
        $this->post(route('assignments.submit', [$course, $work]), ['upload_token' => 'garbage'])->assertSessionHasErrors('file');

        // Downloads are short-lived private links.
        $storage->shouldReceive('downloadUrl')->andReturn('https://r2.example.test/get?sig=1');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('assignments.download', $submission))->assertRedirect('https://r2.example.test/get?sig=1');
    }

    public function test_admin_reviews_and_frees_space_but_keeps_grades(): void
    {
        Storage::fake('local');
        [$course, $work, $step, $learner] = $this->setUpAssignment();
        $this->actingAs($learner)->post(route('assignments.submit', [$course, $work]), ['file' => UploadedFile::fake()->create('reel.pdf', 300, 'application/pdf')])->assertSessionHasNoErrors();
        $submission = AssignmentSubmission::firstOrFail();
        $this->assertSame('local', $submission->disk);
        Storage::disk('local')->assertExists($submission->path);

        $content = User::factory()->create(['role' => 'admin', 'staff_role' => 'content']);
        $this->actingAs($content)->get('/admin/student-work')->assertOk()->assertSee('Showreel')->assertSee('Files stored');
        Livewire::test(ManageStudentWork::class)->callTableAction('review', $submission, ['status' => 'accepted', 'feedback' => 'Lovely work']);
        $this->assertSame('accepted', $submission->fresh()->status);
        $this->assertTrue($learner->notifications()->get()->contains(fn ($n) => $n->data['title'] === 'Your work was accepted'));
        $this->assertNotNull(LessonProgress::where('lesson_id', $step->id)->value('completed_at'));

        // Too recent for a 30-day cleanup; old enough afterwards.
        Livewire::test(ManageStudentWork::class)->callAction('cleanup', ['days' => 30]);
        Storage::disk('local')->assertExists($submission->path);
        AssignmentSubmission::whereKey($submission->id)->update(['updated_at' => now()->subDays(40)]);
        Livewire::test(ManageStudentWork::class)->callAction('cleanup', ['days' => 30]);
        Storage::disk('local')->assertMissing($submission->path);
        $fresh = $submission->fresh();
        $this->assertNotNull($fresh->file_deleted_at);
        $this->assertSame(['accepted', 'Lovely work'], [$fresh->status, $fresh->feedback]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_work.cleanup']);

        $this->actingAs($learner)->get(route('assignments.download', $submission))->assertStatus(410);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'staff_role' => 'finance']))->get('/admin/student-work')->assertForbidden();
    }
}
