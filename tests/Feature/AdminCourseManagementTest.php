<?php

namespace Tests\Feature;

use App\Filament\Pages\CourseStudio;
use App\Filament\Pages\Instructors;
use App\Filament\Pages\MediaLibrary;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Livewire\Instructor\CourseEditor;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\VideoAsset;
use App\Notifications\AccountInvitation;
use App\Services\Media\MediaStorage;
use App\Support\AcademySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_instructor_workspace_is_closed_by_default_on_a_live_site_but_admins_keep_it(): void
    {
        config(['academy.instructor_portal_default' => false]);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $this->assertFalse(AcademySettings::get('instructor_portal_enabled'));

        $this->actingAs($instructor)->get('/instructor')->assertForbidden();
        $this->get('/dashboard')->assertRedirect(route('instructor.closed'));
        $this->get(route('instructor.closed'))->assertOk()->assertSee('isn’t open yet');
        $this->get(route('instructor.courses.edit', $course->slug))->assertForbidden();
        $this->get(route('instructor.assessments', $course))->assertForbidden();
        $this->actingAs($this->admin())->get('/instructor')->assertRedirect(url('/admin/courses'));
        $this->get(route('instructor.courses.create'))->assertRedirect(CourseStudio::getUrl());
        $this->get(CourseStudio::getUrl())->assertOk()->assertSee('The essentials');

        AcademySettings::save(['instructor_portal_enabled' => true]);
        $this->actingAs($instructor)->get(route('instructor.courses.edit', $course->slug))->assertOk();
    }

    public function test_admin_creates_a_course_and_assigns_its_instructor(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['role' => 'instructor', 'name' => 'Selam Teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $category = Category::factory()->create(['active' => true]);

        $this->actingAs($admin);
        $editor = Livewire::test(CourseEditor::class)->assertSee('Selam Teacher')
            ->set('title', 'Colour Grading')->set('summary', 'Grade footage.')->set('description', 'Full course.')
            ->set('categoryId', $category->id);
        $editor->set('instructorId', $student->id)->call('save')->assertHasErrors('instructorId');
        $editor->set('instructorId', $teacher->id)->call('save')->assertHasNoErrors();
        $course = Course::where('slug', 'colour-grading')->firstOrFail();
        $this->assertSame($teacher->id, $course->instructor_id);

        // An instructor editing (portal on) can't reassign the course to someone else.
        $this->actingAs($teacher);
        Livewire::test(CourseEditor::class, ['course' => $course])->set('instructorId', $admin->id)->call('save');
        $this->assertSame($teacher->id, $course->fresh()->instructor_id);
        $this->actingAs($admin)->get('/admin/courses')->assertOk()->assertSee('New course')->assertSee(e(CourseStudio::getUrl(['course' => $course->slug])), false);
    }

    public function test_course_studio_opens_every_tab_inside_the_admin_panel(): void
    {
        $course = Course::factory()->create(['title' => 'Studio Course']);
        $course->modules()->create(['title' => 'Chapter One'])->lessons()->create(['title' => 'Intro', 'type' => 'text', 'body' => 'Hi', 'published' => true]);
        $this->actingAs($this->admin());
        foreach (['build' => 'Chapter One', 'files' => 'Intro', 'quizzes' => 'Studio Course', 'announcements' => 'Studio Course', 'learners' => 'Studio Course'] as $tab => $text) {
            $this->get(CourseStudio::getUrl(['course' => $course->slug, 'tab' => $tab]))
                ->assertOk()->assertSee('class="fi', false)->assertSee('Files &amp; captions', false)->assertSee($text)->assertDontSee('site-footer');
        }
        // Old builder addresses land on the matching tab.
        $this->get(route('instructor.materials', $course->slug))->assertRedirect(CourseStudio::getUrl(['course' => $course->slug, 'tab' => 'files']));

        // A course created in the studio reopens with its tabs.
        $category = Category::factory()->create(['active' => true]);
        Livewire::test(CourseEditor::class, ['embedded' => true])->set('title', 'Fresh Course')->set('summary', 'S')->set('description', 'D')
            ->set('categoryId', $category->id)->set('instructorName', 'Guest Teacher')->set('instructorId', null)->call('save')
            ->assertRedirect(CourseStudio::getUrl(['course' => 'fresh-course']));
        $this->actingAs(User::factory()->create())->get(CourseStudio::getUrl())->assertRedirect(route('dashboard'));
    }

    public function test_instructors_page_lists_instructors_and_switches_the_workspace(): void
    {
        config(['academy.instructor_portal_default' => false]);
        Notification::fake();
        $teacher = User::factory()->create(['role' => 'instructor', 'name' => 'Hana Teacher']);
        Course::factory()->count(2)->create(['instructor_id' => $teacher->id]);
        $this->actingAs($this->admin())->get('/admin/instructors')->assertOk()->assertSee('Hana Teacher')->assertSee('Instructor workspace');

        Livewire::test(Instructors::class)->assertSet('portalOpen', false)->set('portalOpen', true);
        $this->assertTrue(AcademySettings::get('instructor_portal_enabled'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.instructor_portal']);
        Livewire::test(Instructors::class)->assertSet('portalOpen', true)->set('portalOpen', false);
        $this->assertFalse(AcademySettings::get('instructor_portal_enabled'));

        Livewire::test(Instructors::class)->callAction('newInstructor', ['name' => 'New Teacher', 'email' => 'new.teacher@academy.test'])->assertHasNoActionErrors();
        $this->assertSame('instructor', User::where('email', 'new.teacher@academy.test')->value('role'));
        $this->actingAs(User::factory()->create())->get('/admin/instructors')->assertRedirect(route('dashboard'));
    }

    public function test_roles_stay_in_their_own_workspace(): void
    {
        config(['academy.instructor_portal_default' => false]);
        $admin = $this->admin();
        foreach (['/dashboard', '/my-learning', '/certificates', '/wishlist', '/payments', '/support'] as $page) {
            $this->actingAs($admin)->get($page)->assertRedirect(url('/admin'));
        }
        // Admin pages outside Filament show the admin sidebar, never the learner one.
        $this->get(route('messages.index'))->assertOk()->assertSee('Admin dashboard')->assertSee('Media Library')->assertDontSee('My learning');
        // The course builder lives in the admin panel's Course studio for admins.
        $this->get(route('instructor.courses.create'))->assertRedirect(CourseStudio::getUrl());

        $student = User::factory()->create();
        $this->actingAs($student)->get('/dashboard')->assertOk()->assertDontSee('Instructor workspace')->assertDontSee('Admin dashboard');
        $this->get('/admin')->assertRedirect(route('dashboard'));
        $this->get('/instructor')->assertForbidden();

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(url('/admin'));
    }

    public function test_instructor_directory_and_teacher_messages_are_hidden_while_the_workspace_is_off(): void
    {
        config(['academy.instructor_portal_default' => false]);
        $this->get('/')->assertOk()->assertDontSee(route('instructors.index'), false);
        $this->get('/instructors')->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('messages.index'))->assertOk()->assertDontSee('value="instructor"', false);
        $this->post(route('messages.store'), ['type' => 'instructor', 'course_id' => 1, 'subject' => 'Hi', 'body' => 'Question'])->assertSessionHasErrors('type');

        AcademySettings::save(['instructor_portal_enabled' => true]);
        $this->get('/instructors')->assertOk();
    }

    public function test_admin_can_type_an_instructor_name_when_there_is_no_account(): void
    {
        $category = Category::factory()->create(['active' => true]);
        $this->actingAs($this->admin());
        $editor = Livewire::test(CourseEditor::class)->set('title', 'Sound Design')->set('summary', 'Sound.')->set('description', 'All about sound.')
            ->set('categoryId', $category->id)->set('instructorId', null);
        $editor->call('save')->assertHasErrors('instructorName');
        $editor->set('instructorName', 'Abebe Kebede')->call('save')->assertHasNoErrors();
        $course = Course::where('slug', 'sound-design')->firstOrFail();
        $this->assertSame('Abebe Kebede', $course->instructor_display_name);
        $course->update(['status' => 'published', 'published_at' => now()]);
        $this->get(route('courses.show', $course->slug))->assertSee('By Abebe Kebede');

        $teacher = User::factory()->create(['role' => 'instructor', 'name' => 'Real Account']);
        Livewire::test(CourseEditor::class, ['course' => $course])->set('instructorId', $teacher->id)->call('save')->assertHasNoErrors();
        $this->assertNull($course->fresh()->instructor_name);
        $this->assertSame('Real Account', $course->fresh()->instructor_display_name);
    }

    public function test_admin_creates_users_of_any_role_who_set_their_own_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->actingAs($admin);
        Livewire::test(ManageUsers::class)->callAction('newUser', ['name' => 'Second Admin', 'email' => 'Boss@Academy.test', 'role' => 'admin'])->assertHasNoActionErrors();
        $created = User::where('email', 'boss@academy.test')->firstOrFail();
        $this->assertSame(['admin', 'active'], [$created->role, $created->status]);
        $this->assertTrue($created->hasVerifiedEmail());
        Notification::assertSentTo($created, AccountInvitation::class, fn ($note) => str_contains($note->toMail($created)->actionUrl, '/reset-password/'));
        Livewire::test(ManageUsers::class)->callAction('newUser', ['name' => 'Dup', 'email' => 'boss@academy.test', 'role' => 'student'])->assertHasActionErrors(['email']);

        Livewire::test(ManageUsers::class)->callTableAction('changeRole', $created, ['role' => 'instructor']);
        $this->assertSame('instructor', $created->fresh()->role);
        Livewire::test(ManageUsers::class)->assertTableActionHidden('changeRole', $admin);
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/admin/users')->assertRedirect(route('instructor.dashboard'));
    }

    public function test_media_page_picks_course_chapter_and_lesson_and_admin_upload_keeps_the_course_live(): void
    {
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('available')->andReturn(true);
        $storage->shouldReceive('upload')->andReturn(['url' => 'https://storage.example.test/put', 'headers' => ['Content-Type' => 'video/mp4']]);
        $storage->shouldReceive('finalize');
        $this->app->instance(MediaStorage::class, $storage);
        $course = Course::factory()->create(['title' => 'Motion Basics']);
        $this->actingAs($this->admin());

        $page = Livewire::test(MediaLibrary::class)->set('courseId', $course->id)
            ->set('newChapter', 'Getting started')->call('addChapter')
            ->set('newLesson', 'Keyframes')->call('addLesson');
        $lesson = $course->lessons()->where('lessons.title', 'Keyframes')->firstOrFail();
        $this->assertFalse((bool) $lesson->published);
        $page->assertSet('lessonId', $lesson->id)->assertSee('data-video-upload="'.route('media.store', $course->slug).'"', false);

        $base = '/instructor/courses/'.$course->slug.'/videos';
        $this->postJson($base, ['filename' => 'keyframes.mp4', 'bytes' => 32, 'mime' => 'video/mp4'])->assertCreated();
        $asset = VideoAsset::firstOrFail();
        $this->postJson($base.'/'.$asset->id.'/confirm')->assertOk();
        $this->postJson($base.'/'.$asset->id.'/attach', ['lesson_id' => $lesson->id, 'duration_seconds' => 95])->assertOk();
        $this->assertSame('published', $course->fresh()->status);
        $this->assertTrue((bool) $lesson->fresh()->published);
        $this->assertSame(95, $lesson->fresh()->duration_seconds);
    }
}
