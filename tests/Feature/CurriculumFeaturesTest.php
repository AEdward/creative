<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::factory()->create(['instructor_id' => User::factory()->create(['role' => 'instructor'])->id]);
    }

    public function test_owner_can_rename_and_reorder_curriculum_with_stable_positions(): void
    {
        $course = $this->course();
        $a = $course->modules()->create(['title' => 'First', 'sort_order' => 1]);
        $b = $course->modules()->create(['title' => 'Second', 'sort_order' => 2]);
        $base = '/instructor/courses/'.$course->slug;
        $this->actingAs($course->instructor)->put($base.'/modules/'.$b->id, ['title' => 'New second title'])->assertRedirect();
        $this->post($base.'/modules/'.$b->id.'/move', ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$b->id, $a->id], $course->modules()->pluck('id')->all());
        $one = $a->lessons()->create(['title' => 'One', 'type' => 'text', 'body' => 'Read', 'sort_order' => 1]);
        $two = $a->lessons()->create(['title' => 'Two', 'type' => 'text', 'body' => 'Read', 'sort_order' => 2]);
        $this->post($base.'/lessons/'.$two->id.'/move', ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$two->id, $one->id], $a->lessons()->pluck('id')->all());
        $this->assertSame('draft', $course->fresh()->status);
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->post($base.'/modules/'.$a->id.'/move', ['direction' => 'up'])->assertForbidden();
    }

    public function test_private_resource_upload_download_and_cross_course_denial(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Read', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        $path = '/instructor/courses/'.$course->slug.'/lessons/'.$lesson->id.'/resources';
        $this->actingAs($course->instructor)->post($path, ['title' => 'Exercise', 'kind' => 'resource', 'file' => UploadedFile::fake()->createWithContent('exercise.txt', 'A practical exercise')])->assertRedirect();
        $file = LessonResource::firstOrFail();
        $this->assertStringNotContainsString('exercise.txt', $file->object_key);
        $course->refresh()->update(['status' => 'published', 'published_at' => now()]);
        $this->actingAs(User::factory()->create())->get('/lesson-resources/'.$file->id)->assertForbidden();
        $student = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $this->actingAs($student)->get('/lesson-resources/'.$file->id)->assertOk()->assertDownload('exercise.txt');
        $other = $this->course();
        $this->actingAs($other->instructor)->post('/instructor/courses/'.$other->slug.'/lessons/'.$lesson->id.'/resources', ['title' => 'Bad', 'kind' => 'resource', 'file' => UploadedFile::fake()->createWithContent('test.txt', 'x')])->assertNotFound();
    }

    public function test_caption_requires_webvtt_and_unsafe_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Watch', 'type' => 'text', 'body' => 'Read', 'published' => true]);
        $path = '/instructor/courses/'.$course->slug.'/lessons/'.$lesson->id.'/resources';
        $this->actingAs($course->instructor)->post($path, ['title' => 'English', 'kind' => 'caption', 'language' => 'en', 'file' => UploadedFile::fake()->createWithContent('captions.vtt', 'not captions')])->assertSessionHasErrors('file');
        $this->post($path, ['title' => 'English', 'kind' => 'caption', 'language' => 'en', 'file' => UploadedFile::fake()->createWithContent('captions.vtt', "WEBVTT\n\n00:00.000 --> 00:02.000\nHello\n")])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($path, ['title' => 'Unsafe', 'kind' => 'resource', 'file' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('lesson_resources', 1);
    }

    public function test_avatar_is_private_and_validated_and_thumbnail_follows_course_visibility(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/avatar', ['image' => UploadedFile::fake()->createWithContent('avatar.png', $png)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->avatar_key);
        $this->get('/profile/avatar')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/profile/avatar', ['image' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg></svg>')])->assertSessionHasErrors('image');
        $course = $this->course();
        $this->actingAs($course->instructor)->post('/instructor/courses/'.$course->slug.'/thumbnail', ['image' => UploadedFile::fake()->createWithContent('cover.png', $png)])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->get('/courses/'.$course->slug.'/thumbnail')->assertNotFound();
        $course->refresh()->update(['status' => 'published', 'published_at' => now()]);
        $this->get('/courses/'.$course->slug.'/thumbnail')->assertOk();
    }
}
