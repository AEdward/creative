<?php

namespace Tests\Feature;

use App\Livewire\Instructor\CourseEditor;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LockedStepsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Course, 1: User, 2: array<string, Lesson>} */
    private function course(string $advanceOn = 'submit'): array
    {
        $course = Course::factory()->create(['is_free' => true, 'price_minor' => 0]);
        $one = $course->modules()->create(['title' => 'Chapter 1', 'sort_order' => 1]);
        $two = $course->modules()->create(['title' => 'Chapter 2', 'sort_order' => 2]);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready']);
        $quiz = Assessment::create(['course_id' => $course->id, 'type' => 'quiz', 'title' => 'Check', 'questions' => [['prompt' => '2+2?', 'options' => ['3', '4'], 'correct' => 1], ['prompt' => 'Sky?', 'options' => ['Blue', 'Green'], 'correct' => 0]], 'pass_percent' => 100, 'attempt_limit' => 0]);
        $work = Assessment::create(['course_id' => $course->id, 'type' => 'assignment', 'title' => 'Poster', 'instructions' => 'Make a poster', 'pass_percent' => 70, 'attempt_limit' => 0, 'advance_on' => $advanceOn]);
        $steps = [
            'text' => $one->lessons()->create(['title' => 'Read me', 'type' => 'text', 'body' => 'Page A', 'pages' => ['Page A', 'Page B'], 'published' => true, 'sort_order' => 1]),
            'video' => $one->lessons()->create(['title' => 'Watch me', 'type' => 'video', 'video_asset_id' => $asset->id, 'duration_seconds' => 60, 'published' => true, 'sort_order' => 2]),
            'quiz' => $two->lessons()->create(['title' => 'Quiz', 'type' => 'quiz', 'assessment_id' => $quiz->id, 'published' => true, 'sort_order' => 1]),
            'work' => $two->lessons()->create(['title' => 'Assignment', 'type' => 'assignment', 'assessment_id' => $work->id, 'published' => true, 'sort_order' => 2]),
            'end' => $two->lessons()->create(['title' => 'The end', 'type' => 'text', 'body' => 'Done', 'published' => true, 'sort_order' => 3]),
        ];
        $learner = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id]);

        return [$course, $learner, $steps];
    }

    private function url(Course $course, Lesson $lesson, array $query = []): string
    {
        return route('learning.show', [$course->slug, $lesson->id] + $query);
    }

    public function test_each_step_unlocks_only_after_the_previous_one_is_finished(): void
    {
        [$course, $learner, $s] = $this->course();
        $this->actingAs($learner);

        // Jumping ahead lands back on the first unfinished step; later steps show as locked.
        $this->get($this->url($course, $s['quiz']))->assertRedirect($this->url($course, $s['text']));
        // Text: pages in order (no jumping to page 2 before reading page 1), then "Finish & continue".
        $this->get($this->url($course, $s['text'], ['page' => 2]))->assertRedirect($this->url($course, $s['text'], ['page' => 1]));
        $this->get($this->url($course, $s['text']))->assertOk()->assertSee('Page 1 of 2')->assertSee('Locked:')->assertDontSee($this->url($course, $s['video']), false);
        $this->post(route('learning.complete', [$course->slug, $s['text']->id]))->assertSessionHasErrors('lesson');
        $this->get($this->url($course, $s['text'], ['page' => 2]))->assertOk()->assertSee('Page B')->assertSee('Finish &amp; continue', false);
        $this->post(route('learning.complete', [$course->slug, $s['text']->id]))->assertSessionHasNoErrors();
        $this->get($this->url($course, $s['video']))->assertOk();

        // Video: can't jump to the end; watching in real time to the end completes it.
        $progress = route('learning.progress', [$course->slug, $s['video']->id]);
        $this->postJson($progress, ['position_seconds' => 0])->assertJson(['completed' => false]);
        $this->travel(10)->seconds();
        $this->postJson($progress, ['position_seconds' => 60])->assertJson(['completed' => false]);
        $this->assertLessThan(60, LessonProgress::where('lesson_id', $s['video']->id)->value('max_position_seconds'));
        $this->travel(60)->seconds();
        $this->postJson($progress, ['position_seconds' => 60])->assertJson(['completed' => true]);

        // Quiz: failing keeps the next step locked (unlimited retries); passing unlocks it at once.
        $attempt = route('assessments.attempt', [$course, $s['quiz']->assessment_id]);
        $this->get($this->url($course, $s['work']))->assertRedirect($this->url($course, $s['quiz']));
        foreach (range(1, 4) as $try) {
            $this->post($attempt, ['answers' => [0, 0]])->assertSessionHasNoErrors();
        }
        $this->get($this->url($course, $s['work']))->assertRedirect($this->url($course, $s['quiz']));
        $this->post($attempt, ['answers' => [1, 0]])->assertSessionHas('status', 'You passed! The next step is unlocked.');

        // Assignment ("submitting is enough"): submitting unlocks the last step.
        Storage::fake('local');
        $this->post(route('assignments.submit', [$course, $s['work']->assessment_id]), ['file' => UploadedFile::fake()->create('poster.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $this->get($this->url($course, $s['end']))->assertOk()->assertSee('Done');
    }

    public function test_graded_assignment_waits_for_acceptance_and_locked_quizzes_and_videos_are_refused(): void
    {
        [$course, $learner, $s] = $this->course('grade');
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('available')->andReturn(true);
        $storage->shouldReceive('playback')->andReturn('https://storage.example.test/get');
        $this->app->instance(MediaStorage::class, $storage);
        $this->actingAs($learner);

        $this->getJson(route('media.playback', [$course->slug, $s['video']->id]))->assertForbidden();
        $this->post(route('assessments.attempt', [$course, $s['quiz']->assessment_id]), ['answers' => [1, 0]])->assertForbidden();
        $this->postJson(route('learning.progress', [$course->slug, $s['video']->id]), ['position_seconds' => 5])->assertForbidden();

        $enrollment = Enrollment::firstOrFail();
        foreach (['text', 'video', 'quiz'] as $key) {
            LessonProgress::create(['enrollment_id' => $enrollment->id, 'lesson_id' => $s[$key]->id, 'completed_at' => now()]);
        }
        Storage::fake('local');
        $this->post(route('assignments.submit', [$course, $s['work']->assessment_id]), ['file' => UploadedFile::fake()->create('poster.pdf', 50, 'application/pdf')])->assertSessionHas('status', 'Assignment submitted for review.');
        $this->get($this->url($course, $s['end']))->assertRedirect($this->url($course, $s['work']));
        $this->get($this->url($course, $s['work']))->assertSee('The next step unlocks once your work is reviewed and accepted.');

        $submission = AssignmentSubmission::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('assignments.review', $submission), ['status' => 'accepted', 'feedback' => 'Great poster'])->assertSessionHasNoErrors();
        $this->actingAs($learner)->get($this->url($course, $s['end']))->assertOk();
    }

    public function test_admin_builds_steps_of_every_type_and_reorders_them(): void
    {
        $course = Course::factory()->create();
        $chapter = $course->modules()->create(['title' => 'Chapter 1']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $editor = Livewire::test(CourseEditor::class, ['course' => $course]);

        $editor->set('stepType', 'text')->set('lessonModuleId', $chapter->id)->set('lessonTitle', 'Welcome')->set('lessonBody', "First page\n---\nSecond page")->call('addLesson')->assertHasNoErrors();
        $editor->set('stepType', 'quiz')->assertCount('quizQuestions', 1)->set('lessonModuleId', $chapter->id)->set('lessonTitle', 'Quick check')->set('quizPass', 80)
            ->set('quizQuestions.0.prompt', 'Best colour?')->set('quizQuestions.0.options', ['Red', '', 'Blue', ''])->set('quizQuestions.0.correct', 2)->call('addLesson')->assertHasNoErrors();
        $editor->set('stepType', 'assignment')->set('lessonModuleId', $chapter->id)->set('lessonTitle', 'Your poster')->set('lessonBody', 'Design a poster')->set('assignmentAdvance', 'submit')->call('addLesson')->assertHasNoErrors();
        $editor->set('stepType', 'video')->set('lessonModuleId', $chapter->id)->set('lessonTitle', 'Demo video')->call('addLesson')->assertHasNoErrors();

        $steps = $chapter->lessons()->orderBy('sort_order')->get();
        $this->assertSame(['text', 'quiz', 'assignment', 'video'], $steps->pluck('type')->all());
        $this->assertSame(['First page', 'Second page'], $steps[0]->pages);
        $this->assertEquals([['prompt' => 'Best colour?', 'options' => ['Red', 'Blue'], 'correct' => 1]], $steps[1]->assessment->questions);
        $this->assertSame([80, 0], [$steps[1]->assessment->pass_percent, $steps[1]->assessment->attempt_limit]);
        $this->assertSame('submit', $steps[2]->assessment->advance_on);
        $this->assertFalse($steps[3]->published); // waits for its video

        $editor->call('moveStep', $steps[1]->id, 'up');
        $this->assertSame(['Quick check', 'Welcome'], $chapter->lessons()->orderBy('sort_order')->take(2)->pluck('title')->all());
        $editor->set('stepType', 'quiz')->set('lessonModuleId', $chapter->id)->set('lessonTitle', 'Bad')->set('quizQuestions', [['prompt' => 'Q', 'options' => ['Only one', '', '', ''], 'correct' => 0]])->call('addLesson')->assertHasErrors();
    }
}
