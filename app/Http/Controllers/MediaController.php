<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MediaController extends Controller
{
    public function __construct(private MediaStorage $storage) {}

    public function store(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);
        $data = $request->validate(['filename' => ['required', 'string', 'max:255'], 'bytes' => ['required', 'integer', 'min:16', 'max:1073741824'], 'mime' => ['required', 'in:video/mp4']]);
        abort_unless($this->storage->available(), 503, 'Private video storage is not configured.');
        $asset = VideoAsset::create(['course_id' => $course->id, 'uploaded_by' => $request->user()->id, 'object_key' => 'videos/'.$course->id.'/'.Str::uuid().'.mp4', 'upload_key' => 'uploads/'.$course->id.'/'.Str::uuid().'.mp4', 'filename' => $data['filename'], 'expected_bytes' => $data['bytes'], 'mime' => $data['mime'], 'status' => 'pending', 'upload_expires_at' => now()->addMinutes(15)]);
        try {
            $upload = $this->storage->upload($asset);
        } catch (Throwable) {
            $asset->update(['status' => 'failed']);
            abort(503, 'Unable to prepare an upload. Please retry later.');
        }

        return response()->json(['id' => $asset->id, 'upload' => $upload, 'expires_at' => $asset->upload_expires_at->toIso8601String()], 201)->header('Cache-Control', 'no-store, private');
    }

    private function owned(Course $course, VideoAsset $asset): void
    {
        Gate::authorize('update', $course);
        abort_unless($asset->course_id === $course->id, 404);
    }

    public function confirm(Course $course, VideoAsset $asset): JsonResponse
    {
        $this->owned($course, $asset);
        abort_unless($this->storage->available(), 503, 'Private video storage is not configured.');
        DB::transaction(function () use ($asset): void {
            $locked = VideoAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'ready') {
                return;
            }
            abort_unless($locked->status === 'pending' && $locked->upload_expires_at->copy()->addHours(2)->isFuture(), 422, 'This upload has expired. Start a new upload.');
            try {
                $this->storage->finalize($locked);
            } catch (Throwable) {
                throw ValidationException::withMessages(['video' => 'The uploaded object could not be verified. Check its MP4 format and size, then retry.']);
            }
            $locked->update(['status' => 'ready']);
        });

        return response()->json(['status' => 'ready']);
    }

    public function attach(Request $request, Course $course, VideoAsset $asset): JsonResponse
    {
        $this->owned($course, $asset);
        $data = $request->validate(['lesson_id' => ['required', 'integer'], 'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400']]);
        DB::transaction(function () use ($course, $asset, $data): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $asset = VideoAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($asset->status === 'ready', 422, 'Verify the upload before attaching it.');
            $lesson = $locked->lessons()->findOrFail($data['lesson_id']);
            $lesson->update(['video_asset_id' => $asset->id, 'type' => 'video', 'duration_seconds' => $data['duration_seconds']]);
            $locked->update(['status' => 'draft', 'published_at' => null]);
        });

        return response()->json(['attached' => true]);
    }

    public function playback(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->module->course_id === $course->id && $lesson->published && Course::published()->whereKey($course->id)->exists(), 404);
        if (! $lesson->is_preview) {
            $user = $request->user();
            abort_unless($user && $user->status === 'active' && $user->hasVerifiedEmail() && Enrollment::active()->where('user_id', $user->id)->where('course_id', $course->id)->exists(), 403);
        }
        $asset = $lesson->videoAsset;
        abort_unless($lesson->type === 'video' && $asset && $asset->course_id === $course->id && $asset->status === 'ready', 404);
        abort_unless($this->storage->available(), 503, 'Private video storage is not configured.');
        try {
            $url = $this->storage->playback($asset);
        } catch (Throwable) {
            abort(503, 'Playback is temporarily unavailable. Please retry later.');
        }

        return response()->json(['url' => $url, 'expires_in' => 300])->header('Cache-Control', 'no-store, private');
    }
}
