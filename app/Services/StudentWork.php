<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Learners' submitted work. Large files go straight from the browser to Cloudflare R2 (never through
 * the Yegara server); without R2 a small upload to the server still works. Files can be deleted later
 * to free space — the grade and feedback stay.
 */
class StudentWork
{
    public const MAX_R2_BYTES = 500 * 1024 * 1024;

    public const MAX_LOCAL_KB = 10240;

    /** Types learners may upload, by extension => MIME. */
    public const TYPES = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'zip' => 'application/zip', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
    ];

    public function __construct(private MediaStorage $storage) {}

    public function directUploads(): bool
    {
        return $this->storage->available();
    }

    /**
     * Step 1 of a direct upload: a 15-minute upload link plus a signed token describing the file,
     * which the learner sends back after uploading.
     */
    public function startUpload(Assessment $assessment, Enrollment $enrollment, string $filename, int $bytes): array
    {
        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! isset(self::TYPES[$extension])) {
            throw ValidationException::withMessages(['file' => 'Upload a PDF, image (JPG, PNG, WebP), ZIP or video (MP4, MOV, WebM).']);
        }
        if ($bytes < 1 || $bytes > self::MAX_R2_BYTES) {
            throw ValidationException::withMessages(['file' => 'Files can be up to 500 MB.']);
        }
        $key = 'submissions/'.$assessment->course_id.'/'.$enrollment->id.'/'.Str::uuid().'.'.$extension;
        $mime = self::TYPES[$extension];
        $expires = now()->addMinutes(15);
        $upload = $this->storage->uploadUrl($key, $mime, $expires);
        $token = Crypt::encryptString(json_encode(['key' => $key, 'bytes' => $bytes, 'mime' => $mime, 'name' => basename($filename), 'assessment' => $assessment->id, 'enrollment' => $enrollment->id, 'expires' => $expires->timestamp]));

        return ['upload' => $upload, 'token' => $token];
    }

    /** Step 2: check the object really arrived with the promised size, then record it. */
    public function finishUpload(Assessment $assessment, Enrollment $enrollment, string $token): AssignmentSubmission
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The upload could not be confirmed. Please upload the file again.']);
        }
        if (($data['assessment'] ?? null) !== $assessment->id || ($data['enrollment'] ?? null) !== $enrollment->id || ($data['expires'] ?? 0) < now()->subHour()->timestamp) {
            throw ValidationException::withMessages(['file' => 'The upload could not be confirmed. Please upload the file again.']);
        }
        if ($this->storage->objectSize($data['key']) !== (int) $data['bytes']) {
            throw ValidationException::withMessages(['file' => 'The file didn’t finish uploading. Please try again.']);
        }

        return $this->record($assessment, $enrollment, 'r2', $data['key'], $data['name'], (int) $data['bytes'], $data['mime']);
    }

    /**
     * One live submission per learner and assignment: a new upload replaces a pending one (and its
     * file is deleted); nothing replaces accepted work.
     */
    public function record(Assessment $assessment, Enrollment $enrollment, string $disk, string $path, string $name, ?int $bytes, ?string $mime): AssignmentSubmission
    {
        try {
            [$submission, $old] = DB::transaction(function () use ($assessment, $enrollment, $disk, $path, $name, $bytes, $mime) {
                Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
                $existing = AssignmentSubmission::where('assessment_id', $assessment->id)->where('enrollment_id', $enrollment->id)->latest('id')->first();
                if ($existing?->status === 'accepted') {
                    throw ValidationException::withMessages(['file' => 'This assignment has already been accepted.']);
                }
                $fields = ['path' => $path, 'disk' => $disk, 'original_name' => $name, 'bytes' => $bytes, 'mime' => $mime, 'status' => 'pending', 'feedback' => null, 'reviewer_id' => null, 'file_deleted_at' => null];
                if ($existing) {
                    $old = $existing->file_deleted_at ? null : [$existing->disk, $existing->path];
                    $existing->update($fields);

                    return [$existing, $old];
                }

                return [AssignmentSubmission::create($fields + ['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id]), null];
            });
        } catch (\Throwable $e) {
            $this->removeFile($disk, $path);
            throw $e;
        }
        if ($old) {
            $this->removeFile(...$old);
        }

        return $submission;
    }

    /**
     * Accept the work or ask for changes. Accepting a "wait for the grade" assignment step unlocks
     * the learner's next step; the learner is told either way.
     */
    public function review(AssignmentSubmission $submission, User $reviewer, string $status, string $feedback): void
    {
        abort_unless(in_array($status, ['accepted', 'revision-needed'], true), 422);
        $assessment = Assessment::findOrFail($submission->assessment_id);
        DB::transaction(function () use ($assessment, $submission, $reviewer, $status, $feedback): void {
            Course::whereKey($assessment->course_id)->lockForUpdate()->firstOrFail();
            $submission->update(['status' => $status, 'feedback' => $feedback, 'reviewer_id' => $reviewer->id]);
        });
        $enrollment = Enrollment::with('user')->find($submission->enrollment_id);
        $step = Lesson::where('assessment_id', $assessment->id)->where('published', true)->first();
        if ($status === 'accepted' && $step && $enrollment) {
            app(CourseProgress::class)->complete($enrollment, $step);
        }
        $enrollment?->user?->notify(new EngagementNotification([
            'title' => $status === 'accepted' ? __('Your work was accepted') : __('Changes requested on your work'),
            'body' => $assessment->title.': '.$feedback,
            'url' => $step ? route('learning.show', [$step->module->course->slug, $step->id]) : route('learning.index'),
        ]));
    }

    /** Remove the file but keep the submission, its status and feedback. */
    public function deleteFile(AssignmentSubmission $submission, User $actor): void
    {
        if ($submission->file_deleted_at) {
            return;
        }
        $this->removeFile($submission->disk, $submission->path);
        $submission->update(['file_deleted_at' => now()]);
        AuditLog::create(['actor_id' => $actor->id, 'action' => 'student_work.file_deleted', 'subject_type' => 'assignment_submission', 'subject_id' => $submission->id, 'changes' => ['bytes' => $submission->bytes]]);
    }

    /** Files of reviewed work older than $days days: [count, bytes]. */
    public function cleanupCandidates(int $days)
    {
        return AssignmentSubmission::whereNull('file_deleted_at')->whereIn('status', ['accepted', 'revision-needed'])->where('updated_at', '<', now()->subDays($days));
    }

    public function removeFile(string $disk, string $path): void
    {
        if ($path === '') {
            return;
        }
        try {
            $disk === 'r2' ? $this->storage->delete($path) : Storage::disk('local')->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Private download: a 5-minute link for R2, a direct stream for server files. */
    public function download(AssignmentSubmission $submission)
    {
        abort_if((bool) $submission->file_deleted_at, 410, 'This file was deleted to save space. The grade and feedback are kept.');
        if ($submission->disk === 'r2') {
            return redirect()->away($this->storage->downloadUrl($submission->path, $submission->original_name, $submission->mime ?: 'application/octet-stream'));
        }

        return Storage::disk('local')->download($submission->path, $submission->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
