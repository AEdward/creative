<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LocalFileController extends Controller
{
    public function store(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $course);
        abort_unless($lesson->module->course_id === $course->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'kind' => ['required', 'in:resource,caption'], 'language' => ['nullable', 'in:en,am'], 'file' => ['required', 'file', 'max:10240']]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        if ($data['kind'] === 'caption') {
            if ($extension !== 'vtt' || $file->getSize() > 1048576 || ! str_starts_with(ltrim($file->getContent(), "\xEF\xBB\xBF"), 'WEBVTT')) {
                throw ValidationException::withMessages(['file' => 'Choose a WEBVTT caption file up to 1 MB.']);
            }
            $mime = 'text/vtt';
        } else {
            $allowed = ['txt' => ['text/plain'], 'pdf' => ['application/pdf'], 'zip' => ['application/zip', 'application/x-zip-compressed'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
            if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
                throw ValidationException::withMessages(['file' => 'Choose a PDF, ZIP, text, PNG or JPEG file up to 10 MB.']);
            }
        }
        $key = $file->storeAs('lesson-files', Str::uuid().'.'.$extension, 'local');
        try {
            DB::transaction(function () use ($course, $lesson, $data, $key, $mime, $extension, $file): void {
                Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
                LessonResource::create(['lesson_id' => $lesson->id, 'title' => $data['title'], 'kind' => $data['kind'], 'language' => $data['language'] ?? 'en', 'object_key' => $key, 'mime' => $mime, 'extension' => $extension, 'bytes' => $file->getSize()]);
                $course->update(['status' => 'draft', 'published_at' => null]);
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($key);
            throw $error;
        }

        return back()->with('status', 'Lesson file saved. The course is ready for review.');
    }

    public function download(LessonResource $resource): BinaryFileResponse
    {
        Gate::authorize('view', $resource->lesson);
        abort_unless(Storage::disk('local')->exists($resource->object_key), 404);
        $headers = ['Content-Type' => $resource->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private'];
        if ($resource->kind === 'caption') {
            return response()->file(Storage::disk('local')->path($resource->object_key), $headers)->setPrivate();
        }
        $name = (Str::slug($resource->title) ?: 'lesson-resource').'.'.$resource->extension;

        return response()->download(Storage::disk('local')->path($resource->object_key), $name, $headers)->setPrivate();
    }

    public function destroy(Course $course, LessonResource $resource): RedirectResponse
    {
        Gate::authorize('update', $course);
        abort_unless($resource->lesson->module->course_id === $course->id, 404);
        $key = $resource->object_key;
        DB::transaction(function () use ($course, $resource): void {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $resource->delete();
            $course->update(['status' => 'draft', 'published_at' => null]);
        });
        Storage::disk('local')->delete($key);

        return back()->with('status', 'Lesson file removed.');
    }

    public function avatar(Request $request): RedirectResponse
    {
        $key = $this->image($request, 'avatars');
        $old = $request->user()->avatar_key;
        $request->user()->forceFill(['avatar_key' => $key])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('status', 'Profile picture updated.');
    }

    public function avatarImage(Request $request): BinaryFileResponse
    {
        return $this->display($request->user()->avatar_key);
    }

    public function thumbnail(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);
        $key = $this->image($request, 'thumbnails');
        $old = $course->thumbnail_key;
        $course->update(['thumbnail_key' => $key, 'status' => 'draft', 'published_at' => null]);
        if ($old && str_starts_with($old, 'thumbnails/')) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('status', 'Course image updated.');
    }

    public function thumbnailImage(Course $course): BinaryFileResponse
    {
        abort_unless(Course::published()->whereKey($course->id)->exists() || (auth()->user()?->hasVerifiedEmail() && Gate::allows('update', $course)), 404);

        return $this->display($course->thumbnail_key);
    }

    private function image(Request $request, string $folder): string
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000']]);

        return $request->file('image')->store($folder, 'local');
    }

    private function display(?string $key): BinaryFileResponse
    {
        abort_unless($key && Storage::disk('local')->exists($key), 404);

        return response()->file(Storage::disk('local')->path($key), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private'])->setPrivate();
    }
}
