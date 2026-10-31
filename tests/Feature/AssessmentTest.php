<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('assessments.index')) {
            Route::middleware('web')->group(base_path('routes/assessments.php'));
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_assessments_require_current_enrollment(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->create())->get('/courses/'.$course->slug.'/assessments')->assertForbidden();
    }

    public function test_instructor_cannot_manage_another_course(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/instructor/courses/'.$course->slug.'/assessments')->assertForbidden();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);

        return [$owner, $student, $course, $enrollment];
    }

    private function quiz(Course $course, array $attributes = []): Assessment
    {
        return Assessment::create(array_merge(['course_id' => $course->id, 'type' => 'quiz', 'title' => 'Color practice', 'questions' => [['prompt' => 'Choose the blue value', 'options' => ['Blue', 'Red'], 'correct' => 0]], 'pass_percent' => 100, 'attempt_limit' => 2, 'required' => true], $attributes));
    }

    public function test_quiz_scoring_is_server_owned_with_frozen_snapshots_and_retry_limit(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $quiz = $this->quiz($course);
        $this->actingAs($student)->get('/courses/'.$course->slug.'/assessments')->assertOk()->assertDontSee('correct')->assertSee('Choose the blue value');
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [1], 'score' => 100, 'passed' => true])->assertRedirect();
        $this->assertDatabaseHas('assessment_attempts', ['score' => 0, 'passed' => false]);
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [99]])->assertUnprocessable();
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [0]])->assertRedirect();
        $this->assertDatabaseHas('assessment_attempts', ['score' => 100, 'passed' => true]);
        $snapshot = AssessmentAttempt::first()->snapshot;
        $quiz->update(['questions' => [['prompt' => 'Changed', 'options' => ['A', 'B'], 'correct' => 1]]]);
        $this->assertSame($snapshot, AssessmentAttempt::first()->snapshot);
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [0]])->assertUnprocessable();
        $this->assertDatabaseCount('assessment_attempts', 2);
        $this->assertArrayNotHasKey('questions', $quiz->toArray());
        $this->assertArrayNotHasKey('snapshot', AssessmentAttempt::first()->toArray());
    }

    public function test_instructor_can_create_assignment_without_quiz_fields_and_mismatched_quizzes_are_blocked(): void
    {
        [$owner,$student,$course] = $this->fixture();
        $this->actingAs($owner)->postJson('/instructor/courses/'.$course->slug.'/assessments', ['type' => 'assignment', 'title' => 'Poster', 'questions' => [['prompt' => null, 'options' => [null, null], 'correct' => 0]]])->assertRedirect();
        $this->assertDatabaseHas('assessments', ['title' => 'Poster', 'type' => 'assignment']);
        $other = Course::factory()->create();
        $quiz = $this->quiz($other);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [0]])->assertNotFound();
    }

    public function test_private_assignment_upload_and_review_are_owned(): void
    {
        Storage::fake('local');
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $assignment = Assessment::create(['course_id' => $course->id, 'type' => 'assignment', 'title' => 'Poster']);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/assessments/'.$assignment->id.'/submit', ['file' => UploadedFile::fake()->create('poster.pdf', 20, 'application/pdf')])->assertRedirect();
        $submission = AssignmentSubmission::firstOrFail();
        Storage::disk('local')->assertExists($submission->path);
        $this->actingAs(User::factory()->create())->get('/assignments/'.$submission->id.'/download')->assertForbidden();
        $this->postJson('/assignments/'.$submission->id.'/review', ['status' => 'accepted', 'feedback' => 'Fine'])->assertForbidden();
        $this->actingAs($owner)->get('/assignments/'.$submission->id.'/download')->assertOk();
        $this->postJson('/assignments/'.$submission->id.'/review', ['status' => 'accepted', 'feedback' => 'Good work'])->assertRedirect();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'accepted', 'reviewer_id' => $owner->id]);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/assessments/'.$assignment->id.'/submit', ['file' => UploadedFile::fake()->create('program.exe', 10)])->assertUnprocessable();
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$assignment->id.'/submit', ['file' => UploadedFile::fake()->create('again.pdf', 20, 'application/pdf')])->assertJsonValidationErrors(['file' => 'This assignment has already been accepted.']);
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_resubmitting_replaces_the_pending_file_instead_of_piling_up_uploads(): void
    {
        Storage::fake('local');
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $assignment = Assessment::create(['course_id' => $course->id, 'type' => 'assignment', 'title' => 'Poster']);
        $url = '/courses/'.$course->slug.'/assessments/'.$assignment->id.'/submit';
        $this->actingAs($student)->postJson($url, ['file' => UploadedFile::fake()->create('one.pdf', 20, 'application/pdf')])->assertRedirect();
        $first = AssignmentSubmission::firstOrFail()->path;
        $this->postJson($url, ['file' => UploadedFile::fake()->create('two.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->assertDatabaseCount('assignment_submissions', 1);
        $submission = AssignmentSubmission::firstOrFail();
        $this->assertSame('two.pdf', $submission->original_name);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($submission->path);
    }

    public function test_certificates_require_opt_in_current_lessons_and_assessments_and_are_private_revocable_and_idempotent(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $lesson = $course->modules()->create(['title' => 'Start', 'sort_order' => 1])->lessons()->create(['title' => 'Lesson', 'type' => 'text', 'published' => true]);
        $quiz = $this->quiz($course);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/certificate')->assertUnprocessable();
        $this->actingAs($owner)->postJson('/instructor/courses/'.$course->slug.'/certificate-settings', ['enabled' => true])->assertRedirect();
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/certificate')->assertUnprocessable();
        $enrollment->progress()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertUnprocessable();
        $this->postJson('/courses/'.$course->slug.'/assessments/'.$quiz->id.'/attempt', ['answers' => [0]])->assertRedirect();
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertRedirect();
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertRedirect();
        $this->assertDatabaseCount('certificates', 1);
        $certificate = Certificate::firstOrFail();
        $this->actingAs(User::factory()->create())->get('/certificates/'.$certificate->id.'/print')->assertForbidden();
        $this->postJson('/certificates/'.$certificate->id.'/revoke', ['reason' => 'Invalid'])->assertForbidden();
        $this->get('/certificates/verify/'.$certificate->reference)->assertOk()->assertSee('Valid certificate')->assertDontSee($student->email)->assertDontSee($student->name);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/certificates/'.$certificate->id.'/revoke', ['reason' => 'Issued in error'])->assertRedirect();
        $this->get('/certificates/verify/'.$certificate->reference)->assertOk()->assertSee('Revoked certificate');
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoked', 'subject_id' => $certificate->id]);
    }

    public function test_management_pages_render_and_administrator_can_find_issued_certificates(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $this->quiz($course);
        $this->actingAs($owner)->get('/instructor/courses/'.$course->slug.'/assessments')->assertOk()->assertSee('Create assessment');
        $certificate = Certificate::factory()->create(['enrollment_id' => $enrollment->id]);
        $this->actingAs(User::factory()->create())->get('/certificates')->assertOk()->assertDontSee($certificate->course_title);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/certificates')->assertOk()->assertSee($certificate->course_title);
        $this->get('/certificates/'.$certificate->id.'/print')->assertOk()->assertSee('Revoke certificate');
    }

    public function test_required_assignment_must_be_accepted_and_expired_students_cannot_submit_or_issue(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $lesson = $course->modules()->create(['title' => 'Start', 'sort_order' => 1])->lessons()->create(['title' => 'Lesson', 'type' => 'text', 'published' => true]);
        $enrollment->progress()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);
        CertificateSetting::factory()->create(['course_id' => $course->id, 'enabled' => true]);
        $assignment = Assessment::factory()->create(['course_id' => $course->id, 'type' => 'assignment', 'questions' => null, 'required' => true]);
        $submission = AssignmentSubmission::factory()->create(['assessment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/certificate')->assertUnprocessable();
        $submission->update(['status' => 'revision-needed']);
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertUnprocessable();
        $submission->update(['status' => 'accepted']);
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertRedirect();
        $enrollment->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/courses/'.$course->slug.'/certificate')->assertForbidden();
        $this->get('/courses/'.$course->slug.'/assessments')->assertForbidden();
    }

    public function test_oversized_upload_is_rejected_and_attempt_models_cannot_be_changed(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $assignment = Assessment::factory()->create(['course_id' => $course->id, 'type' => 'assignment', 'questions' => null]);
        $this->actingAs($student)->postJson('/courses/'.$course->slug.'/assessments/'.$assignment->id.'/submit', ['file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')])->assertUnprocessable();
        $attempt = AssessmentAttempt::factory()->create(['enrollment_id' => $enrollment->id]);
        $this->expectException(\LogicException::class);
        $attempt->update(['score' => 0]);
    }

    public function test_curriculum_change_during_issuance_cannot_issue_on_a_draft_course(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        CertificateSetting::create(['course_id' => $course->id, 'enabled' => true]);
        $module = $course->modules()->create(['title' => 'Start']);
        $complete = $module->lessons()->create(['title' => 'Done', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        $incomplete = $module->lessons()->create(['title' => 'Not done', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $complete->id, 'completed_at' => now()]);
        $changed = false;
        DB::listen(function ($query) use (&$changed, $course, $incomplete): void {
            if (! $changed && str_contains($query->sql, 'exists') && str_contains($query->sql, 'courses') && ! str_contains($query->sql, 'enrollments')) {
                $changed = true;
                $incomplete->update(['published' => false]);
                $course->update(['status' => 'draft', 'published_at' => null]);
            }
        });
        $this->actingAs($student)->post('/courses/'.$course->slug.'/certificate')->assertNotFound();
        $this->assertDatabaseCount('certificates', 0);
    }
}
