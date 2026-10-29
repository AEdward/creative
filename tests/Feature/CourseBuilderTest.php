<?php

namespace Tests\Feature;

use App\Livewire\Instructor\CourseEditor;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_instructors_can_open_course_editor(): void
    {
        $this->get('/instructor/courses/create')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/instructor/courses/create')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/instructor/courses/create')->assertOk();
    }

    public function test_instructor_can_save_draft_and_price_uses_integer_minor_units(): void
    {
        $user = User::factory()->create(['role' => 'instructor']);
        $category = Category::factory()->create();
        Livewire::actingAs($user)->test(CourseEditor::class)
            ->set('title', 'Editing foundations')->set('slug', 'editing-foundations')->set('categoryId', $category->id)->set('summary', 'A practical introduction.')->set('description', 'Learn the basics.')->set('isFree', false)->set('price', '1200.50')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('courses', ['slug' => 'editing-foundations', 'instructor_id' => $user->id, 'status' => 'draft', 'price_minor' => 120050]);
    }

    public function test_an_instructor_cannot_edit_another_instructors_course(): void
    {
        $course = Course::factory()->create();
        $other = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($other)->get('/instructor/courses/'.$course->slug.'/edit')->assertForbidden();
    }

    public function test_instructors_cannot_publish_courses(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id, 'status' => 'draft']);
        Livewire::actingAs($owner)->test(CourseEditor::class, ['course' => $course])->call('publish')->assertForbidden();
    }

    public function test_lesson_cannot_be_added_to_a_different_courses_module(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $other = Course::factory()->create()->modules()->create(['title' => 'Unrelated module']);
        Livewire::actingAs($owner)->test(CourseEditor::class, ['course' => $course])->set('lessonModuleId', $other->id)->set('lessonTitle', 'Intrusion')->set('lessonBody', 'Not allowed')->call('addLesson')->assertNotFound();
    }

    public function test_admin_can_publish_a_course_with_a_complete_text_lesson(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'draft', 'published_at' => null]);
        $module = $course->modules()->create(['title' => 'Introduction']);
        $module->lessons()->create(['title' => 'Start here', 'type' => 'text', 'body' => 'Welcome to the course.', 'published' => true]);
        Livewire::actingAs($admin)->test(CourseEditor::class, ['course' => $course])->call('publish')->assertHasNoErrors();
        $this->assertSame('published', $course->fresh()->status);
    }

    public function test_updating_a_published_course_as_instructor_returns_it_to_review(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        Livewire::actingAs($owner)->test(CourseEditor::class, ['course' => $course])->set('title', 'Revised course')->call('save')->assertHasNoErrors();
        $this->assertSame('draft', $course->fresh()->status);
    }

    public function test_owned_text_lessons_can_be_edited_and_removed_from_public_curriculum(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $module = $course->modules()->create(['title' => 'Module']);
        $lesson = $module->lessons()->create(['title' => 'Old title', 'body' => 'Old body', 'type' => 'text', 'published' => true]);
        Livewire::actingAs($owner)->test(CourseEditor::class, ['course' => $course])->call('editLesson', $lesson->id)->set('lessonTitle', 'Updated title')->set('lessonBody', 'Updated body')->call('addLesson')->assertHasNoErrors()->call('unpublishLesson', $lesson->id)->assertHasNoErrors();
        $this->assertSame('Updated body', $lesson->fresh()->body);
        $this->assertFalse($lesson->fresh()->published);
    }

    public function test_administrative_publication_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['status' => 'draft']);
        $course->modules()->create(['title' => 'Module'])->lessons()->create(['title' => 'Lesson', 'type' => 'text', 'body' => 'Content', 'published' => true]);
        Livewire::actingAs($admin)->test(CourseEditor::class, ['course' => $course])->call('publish')->assertHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'subject_id' => $course->id, 'action' => 'course.published']);
    }

    public function test_unattached_video_lessons_prevent_publication(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);
        $module = $course->modules()->create(['title' => 'Module']);
        $module->lessons()->create(['title' => 'Text', 'type' => 'text', 'body' => 'Content', 'published' => true]);
        $module->lessons()->create(['title' => 'Unattached video', 'type' => 'video', 'published' => true]);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CourseEditor::class, ['course' => $course])->call('publish')->assertHasErrors('publication');
        $this->assertSame('draft', $course->fresh()->status);
    }

    public function test_hiding_the_last_lesson_as_admin_returns_the_course_to_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['is_free' => true, 'price_minor' => 0]);
        $lesson = $course->modules()->create(['title' => 'Module'])->lessons()->create(['title' => 'Only lesson', 'body' => 'Complete content', 'type' => 'text', 'published' => true]);
        Livewire::actingAs($admin)->test(CourseEditor::class, ['course' => $course])->call('unpublishLesson', $lesson->id)->assertHasNoErrors();
        $this->assertSame('draft', $course->fresh()->status);
        $this->actingAs(User::factory()->create())->post('/courses/'.$course->slug.'/enroll')->assertNotFound();
    }

    public function test_long_unicode_course_and_lesson_content_can_be_saved(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $category = Category::factory()->create();
        $content = str_repeat('ሀ', 25000);
        Livewire::actingAs($owner)->test(CourseEditor::class)->set('title', 'Unicode foundations')->set('slug', 'unicode-foundations')->set('categoryId', $category->id)->set('summary', 'A multilingual course.')->set('description', $content)->call('save')->assertHasNoErrors();
        $course = Course::where('slug', 'unicode-foundations')->firstOrFail();
        $module = $course->modules()->create(['title' => 'Module']);
        Livewire::actingAs($owner)->test(CourseEditor::class, ['course' => $course])->set('lessonModuleId', $module->id)->set('lessonTitle', 'A Unicode lesson')->set('lessonBody', $content)->call('addLesson')->assertHasNoErrors();
        $this->assertSame($content, $course->fresh()->description);
        $this->assertSame($content, $module->lessons()->firstOrFail()->body);
    }
}
