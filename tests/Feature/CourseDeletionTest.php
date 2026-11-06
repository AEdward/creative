<?php

namespace Tests\Feature;

use App\Filament\Resources\Courses\Pages\ManageCourses;
use App\Livewire\Instructor\CourseEditor;
use App\Models\Assessment;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonResource;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\CourseDeletion;
use App\Services\Media\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CourseDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function fullCourse(): Course
    {
        Storage::fake('local');
        $course = Course::factory()->create(['title' => 'Test Course']);
        $chapter = $course->modules()->create(['title' => 'Chapter']);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready', 'object_key' => 'videos/x.mp4', 'upload_key' => 'uploads/x.mp4']);
        $text = $chapter->lessons()->create(['title' => 'Text', 'type' => 'text', 'body' => 'Hi', 'published' => true]);
        $chapter->lessons()->create(['title' => 'Video', 'type' => 'video', 'video_asset_id' => $asset->id, 'published' => true]);
        $quiz = Assessment::create(['course_id' => $course->id, 'type' => 'quiz', 'title' => 'Q', 'questions' => [['prompt' => 'A?', 'options' => ['a', 'b'], 'correct' => 0]], 'pass_percent' => 70, 'attempt_limit' => 0]);
        $chapter->lessons()->create(['title' => 'Quiz', 'type' => 'quiz', 'assessment_id' => $quiz->id, 'published' => true]);
        Storage::disk('local')->put('resources/handout.pdf', 'pdf');
        LessonResource::create(['lesson_id' => $text->id, 'title' => 'Handout', 'kind' => 'resource', 'object_key' => 'resources/handout.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'bytes' => 3]);
        Coupon::create(['code' => 'ONLYTHIS', 'percent' => 10, 'course_id' => $course->id, 'active' => true, 'max_uses' => 5, 'per_user_limit' => 1]);

        return $course;
    }

    public function test_admin_deletes_an_unused_course_with_everything_in_it(): void
    {
        $course = $this->fullCourse();
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('available')->andReturn(true);
        $storage->shouldReceive('delete')->with('videos/x.mp4')->once();
        $storage->shouldReceive('delete')->with('uploads/x.mp4')->once();
        $this->app->instance(MediaStorage::class, $storage);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageCourses::class)->callTableAction('delete', $course, ['confirmation' => 'Wrong title'])->assertHasTableActionErrors(['confirmation']);
        $this->assertModelExists($course);
        Livewire::test(ManageCourses::class)->callTableAction('delete', $course, ['confirmation' => 'Test Course'])->assertHasNoTableActionErrors();

        $this->assertModelMissing($course);
        $this->assertDatabaseCount('lessons', 0);
        $this->assertDatabaseCount('video_assets', 0);
        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseCount('coupons', 0);
        Storage::disk('local')->assertMissing('resources/handout.pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.deleted']);
    }

    public function test_courses_with_learners_can_only_be_unpublished(): void
    {
        $course = $this->fullCourse();
        $course->update(['status' => 'published', 'published_at' => now()]);
        Enrollment::factory()->create(['course_id' => $course->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertStringStartsWith('1 learner is enrolled, so this course can’t be deleted.', app(CourseDeletion::class)->blocker($course));
        $this->assertThrows(fn () => app(CourseDeletion::class)->delete($course, auth()->user(), 'Test Course'), ValidationException::class);
        $this->assertModelExists($course);

        Livewire::test(ManageCourses::class)->callTableAction('unpublish', $course);
        $this->assertSame('draft', $course->fresh()->status);
        $this->get(route('courses.show', $course->slug))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.unpublished']);
    }

    public function test_studio_can_unpublish_and_delete_and_quiz_courses_can_be_published(): void
    {
        $course = Course::factory()->create(['title' => 'Quiz Only', 'status' => 'draft', 'published_at' => null]);
        $chapter = $course->modules()->create(['title' => 'Chapter']);
        $quiz = Assessment::create(['course_id' => $course->id, 'type' => 'quiz', 'title' => 'Q', 'questions' => [['prompt' => 'A?', 'options' => ['a', 'b'], 'correct' => 0]], 'pass_percent' => 70, 'attempt_limit' => 0]);
        $chapter->lessons()->create(['title' => 'Intro', 'type' => 'text', 'body' => 'Hello', 'pages' => ['Hello', 'World'], 'published' => true]);
        $chapter->lessons()->create(['title' => 'Quiz', 'type' => 'quiz', 'assessment_id' => $quiz->id, 'published' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $editor = Livewire::test(CourseEditor::class, ['course' => $course, 'embedded' => true])->call('publish')->assertHasNoErrors();
        $this->assertSame('published', $course->fresh()->status);
        $editor->call('unpublishCourse');
        $this->assertSame('draft', $course->fresh()->status);
        $editor->set('deleteConfirmation', 'nope')->call('deleteCourse')->assertHasErrors('confirmation');
        $editor->set('deleteConfirmation', 'Quiz Only')->call('deleteCourse')->assertRedirect(url('/admin/courses'));
        $this->assertModelMissing($course);
    }
}
