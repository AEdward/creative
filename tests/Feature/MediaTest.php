<?php

namespace Tests\Feature;

use App\Livewire\Instructor\CourseEditor;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use App\Services\Media\R2Storage;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::factory()->create(['instructor_id' => User::factory()->create(['role' => 'instructor'])->id]);
    }

    private function storage(): void
    {
        $fake = \Mockery::mock(MediaStorage::class);
        $fake->shouldReceive('available')->andReturn(true);
        $fake->shouldReceive('upload')->andReturn(['url' => 'https://storage.example.test/temporary-put', 'headers' => ['Content-Type' => 'video/mp4']]);
        $fake->shouldReceive('finalize')->andReturnUsing(function (VideoAsset $asset): void {
            if ($asset->expected_bytes !== 32) {
                throw new \RuntimeException('Invalid object');
            }
        });
        $fake->shouldReceive('playback')->andReturn('https://storage.example.test/temporary-get');
        $this->app->instance(MediaStorage::class, $fake);
    }

    public function test_storage_is_disabled_without_configuration(): void
    {
        config(['filesystems.disks.r2.key' => '']);
        $course = $this->course();
        $this->actingAs($course->instructor)->postJson('/instructor/courses/'.$course->slug.'/videos', ['filename' => 'lesson.mp4', 'bytes' => 32, 'mime' => 'video/mp4'])->assertStatus(503);
        $this->assertDatabaseCount('video_assets', 0);
    }

    public function test_owner_upload_confirmation_and_protected_playback(): void
    {
        $this->storage();
        $course = $this->course();
        $base = '/instructor/courses/'.$course->slug.'/videos';
        $response = $this->actingAs($course->instructor)->postJson($base, ['filename' => 'lesson.mp4', 'bytes' => 32, 'mime' => 'video/mp4'])->assertCreated();
        $asset = VideoAsset::firstOrFail();
        $this->assertSame('pending', $asset->status);
        $this->assertStringNotContainsString('lesson.mp4', $asset->object_key);
        $this->postJson($base.'/'.$asset->id.'/confirm')->assertOk();
        $this->assertSame('ready', $asset->fresh()->status);
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Watch', 'type' => 'text', 'body' => 'Introduction', 'published' => true]);
        $this->postJson($base.'/'.$asset->id.'/attach', ['lesson_id' => $lesson->id, 'duration_seconds' => 120])->assertOk();
        $this->assertSame('draft', $course->fresh()->status);
        $course->refresh()->update(['status' => 'published', 'published_at' => now()]);
        $student = User::factory()->create();
        $path = '/learn/'.$course->slug.'/'.$lesson->id.'/playback';
        $this->actingAs($student)->getJson($path)->assertForbidden();
        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $this->getJson($path)->assertOk()->assertJsonPath('expires_in', 300)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString('https://', $asset->fresh()->object_key);
        $lesson->update(['published' => false]);
        $this->getJson($path)->assertNotFound();
    }

    public function test_other_instructor_cannot_upload_or_confirm_and_bad_objects_stay_pending(): void
    {
        $this->storage();
        $course = $this->course();
        $base = '/instructor/courses/'.$course->slug.'/videos';
        $data = ['filename' => 'lesson.mp4', 'bytes' => 33, 'mime' => 'video/mp4'];
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->postJson($base, $data)->assertForbidden();
        $this->actingAs($course->instructor)->postJson($base, $data)->assertCreated();
        $asset = VideoAsset::firstOrFail();
        $this->postJson($base.'/'.$asset->id.'/confirm')->assertUnprocessable()->assertDontSee('RuntimeException');
        $this->assertSame('pending', $asset->fresh()->status);
        $other = $this->course();
        $this->actingAs($other->instructor)->postJson('/instructor/courses/'.$other->slug.'/videos/'.$asset->id.'/confirm')->assertNotFound();
    }

    public function test_public_preview_and_cross_course_attachment_rules(): void
    {
        $this->storage();
        $course = $this->course();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'uploaded_by' => $course->instructor_id, 'status' => 'ready']);
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Watch', 'type' => 'video', 'video_asset_id' => $asset->id, 'published' => true, 'is_preview' => true]);
        $this->getJson('/learn/'.$course->slug.'/'.$lesson->id.'/playback')->assertOk();
        $other = $this->course();
        $this->actingAs($other->instructor)->postJson('/instructor/courses/'.$other->slug.'/videos/'.$asset->id.'/attach', ['lesson_id' => $lesson->id, 'duration_seconds' => 60])->assertNotFound();
        $asset->update(['status' => 'pending']);
        $this->getJson('/learn/'.$course->slug.'/'.$lesson->id.'/playback')->assertNotFound();
    }

    public function test_verified_video_can_be_published_and_explicitly_completed(): void
    {
        $this->storage();
        $course = $this->course();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'uploaded_by' => $course->instructor_id, 'status' => 'ready']);
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Watch', 'type' => 'video', 'video_asset_id' => $asset->id, 'duration_seconds' => 60, 'published' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(CourseEditor::class, ['course' => $course])->call('publish')->assertHasNoErrors();
        $student = User::factory()->create();
        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $this->actingAs($student)->get('/learn/'.$course->slug.'/'.$lesson->id)->assertOk()->assertSee('data-private-video', false)->assertSee('controlslist="nodownload', false)->assertSee($student->email);
        $this->assertDatabaseCount('lesson_progress', 0);
        $this->post('/learn/'.$course->slug.'/'.$lesson->id.'/complete')->assertSessionHasErrors('lesson');
        $this->assertNull(LessonProgress::first()->completed_at);
        $this->postJson('/learn/'.$course->slug.'/'.$lesson->id.'/progress', ['position_seconds' => 60])->assertOk();
        $this->assertSame(20, LessonProgress::first()->position_seconds);
        LessonProgress::first()->update(['position_seconds' => 55]);
        $this->post('/learn/'.$course->slug.'/'.$lesson->id.'/complete')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull(LessonProgress::first()->completed_at);
    }

    public function test_r2_adapter_rejects_metadata_or_header_mismatches_before_copying(): void
    {
        foreach (['size', 'mime', 'header', 'valid'] as $case) {
            $client = \Mockery::mock(S3Client::class);
            $client->shouldReceive('headObject')->andReturn(new Result(['ContentLength' => $case === 'size' ? 33 : 32, 'ContentType' => $case === 'mime' ? 'text/html' : 'video/mp4']));
            $client->shouldReceive('getObject')->andReturn(new Result(['Body' => $case === 'header' ? 'not an mp4 header' : '0000ftypisom0000']));
            $disk = \Mockery::mock(AwsS3V3Adapter::class);
            $disk->shouldReceive('getClient')->andReturn($client);
            if ($case === 'valid') {
                $disk->shouldReceive('copy')->once()->andReturn(true);
            } else {
                $disk->shouldNotReceive('copy');
            }
            Storage::swap(\Mockery::mock(FilesystemManager::class));
            Storage::shouldReceive('disk')->with('r2')->andReturn($disk);
            $asset = new VideoAsset(['expected_bytes' => 32, 'upload_key' => 'uploads/test.mp4', 'object_key' => 'videos/test.mp4']);
            try {
                app(R2Storage::class)->finalize($asset);
                $this->assertSame('valid', $case);
            } catch (\RuntimeException $exception) {
                $this->assertNotSame('valid', $case);
            }
        }
    }

    public function test_real_sdk_builds_short_lived_signatures_without_network_requests(): void
    {
        config(['filesystems.disks.r2.key' => 'test-only-access-key', 'filesystems.disks.r2.secret' => 'test-only-secret', 'filesystems.disks.r2.bucket' => 'test-bucket', 'filesystems.disks.r2.endpoint' => 'https://storage.example.test']);
        $storage = app(R2Storage::class);
        $asset = new VideoAsset(['expected_bytes' => 32, 'object_key' => 'videos/private.mp4', 'upload_key' => 'uploads/random.mp4', 'upload_expires_at' => now()->addMinutes(15)]);
        $this->assertTrue($storage->available());
        $url = $storage->playback($asset);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('300', (string) $query['X-Amz-Expires']);
        $this->assertNotEmpty($query['X-Amz-Signature']);
        $this->assertStringContainsString('/test-bucket/videos/private.mp4', $url);
        $this->assertStringNotContainsString('test-only-secret', $url);
        $upload = $storage->upload($asset);
        $this->assertSame(['video/mp4'], $upload['headers']['Content-Type']);
        $this->assertStringContainsString('/test-bucket/uploads/random.mp4', $upload['url']);
    }

    public function test_video_lesson_can_be_renamed_and_restored_without_losing_its_asset(): void
    {
        $this->storage();
        $course = $this->course();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready']);
        $lesson = $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Watch', 'type' => 'video', 'video_asset_id' => $asset->id, 'published' => false]);
        Livewire::actingAs($course->instructor)->test(CourseEditor::class, ['course' => $course])->call('editLesson', $lesson->id)->set('lessonTitle', 'A clearer title')->set('lessonPreview', true)->call('addLesson')->assertHasNoErrors();
        $this->assertSame('video', $lesson->refresh()->type);
        $this->assertSame($asset->id, $lesson->video_asset_id);
        $this->assertTrue($lesson->published);
        $this->assertTrue($lesson->is_preview);
    }

    public function test_an_upload_started_in_time_can_be_confirmed_after_the_put_url_expires(): void
    {
        $this->storage();
        $course = $this->course();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'uploaded_by' => $course->instructor_id, 'expected_bytes' => 32, 'upload_expires_at' => now()->subMinutes(20)]);
        $this->actingAs($course->instructor)->postJson('/instructor/courses/'.$course->slug.'/videos/'.$asset->id.'/confirm')->assertOk();
    }

    public function test_cleanup_preserves_attached_videos_and_removes_expired_orphans(): void
    {
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('available')->andReturn(true);
        $course = $this->course();
        $attached = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready', 'upload_expires_at' => now()->subHours(3), 'created_at' => now()->subDays(2)]);
        $orphan = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready', 'upload_expires_at' => now()->subHours(3), 'created_at' => now()->subDays(2)]);
        $pending = VideoAsset::factory()->create(['course_id' => $course->id, 'upload_expires_at' => now()->subHours(3)]);
        $recent = VideoAsset::factory()->create(['course_id' => $course->id, 'upload_expires_at' => now()->subMinutes(30)]);
        $course->modules()->create(['title' => 'Start'])->lessons()->create(['title' => 'Video', 'type' => 'video', 'video_asset_id' => $attached->id]);
        foreach ([$attached, $orphan, $pending] as $asset) {
            $storage->shouldReceive('delete')->with($asset->upload_key)->once();
        }
        foreach ([$orphan, $pending] as $asset) {
            $storage->shouldReceive('delete')->with($asset->object_key)->once();
        }
        $storage->shouldNotReceive('delete')->with($attached->object_key);
        $storage->shouldNotReceive('delete')->with($recent->upload_key);
        $this->app->instance(MediaStorage::class, $storage);
        $this->artisan('academy:prune-video-uploads')->assertSuccessful();
        $this->assertSame('ready', $attached->fresh()->status);
        $this->assertSame('pruned', $orphan->fresh()->status);
        $this->assertSame('pruned', $pending->fresh()->status);
        $this->assertSame('pending', $recent->fresh()->status);
    }
}
