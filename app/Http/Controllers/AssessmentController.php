<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentController extends Controller
{
    private function owner(Course $course): void
    {
        abort_unless(auth()->user()->role === 'admin' || (auth()->user()->role === 'instructor' && $course->instructor_id === auth()->id()), 403);
    }

    private function enrollment(Course $course): Enrollment
    {
        abort_unless(Course::published()->whereKey($course->id)->exists(), 404);

        return Enrollment::active()->where('course_id', $course->id)->where('user_id', auth()->id())->firstOrFail();
    }

    private function enrolled(Course $course): Enrollment
    {
        abort_unless(Enrollment::active()->where('course_id', $course->id)->where('user_id', auth()->id())->exists(), 403);

        return $this->enrollment($course);
    }

    private function matching(Course $course, Assessment $assessment, string $type): void
    {
        abort_unless($assessment->course_id === $course->id && $assessment->type === $type, 404);
    }

    public function index(Course $course): View
    {
        $enrollment = $this->enrolled($course);
        $assessments = Assessment::where('course_id', $course->id)->get();
        $attempts = AssessmentAttempt::where('enrollment_id', $enrollment->id)->get();
        $submissions = AssignmentSubmission::where('enrollment_id', $enrollment->id)->latest()->get();

        return view('assessments.index', compact('course', 'assessments', 'attempts', 'submissions'));
    }

    public function manage(Course $course): View
    {
        $this->owner($course);
        $assessments = Assessment::where('course_id', $course->id)->get();
        $submissions = AssignmentSubmission::whereIn('assessment_id', $assessments->pluck('id'))->latest()->paginate(20);
        $enabled = CertificateSetting::where('course_id', $course->id)->value('enabled');

        return view('assessments.manage', compact('course', 'assessments', 'submissions', 'enabled'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->owner($course);
        $data = $request->validate(['title' => 'required|string|max:200', 'type' => 'required|in:quiz,assignment', 'instructions' => 'nullable|string|max:10000', 'required' => 'sometimes|boolean', 'pass_percent' => 'required_if:type,quiz|integer|min:1|max:100', 'attempt_limit' => 'required_if:type,quiz|integer|min:1|max:20', 'questions' => 'exclude_unless:type,quiz|required|array|min:1|max:50', 'questions.*.prompt' => 'required|string|max:1000', 'questions.*.options' => 'required|array|min:2|max:6', 'questions.*.options.*' => 'required|string|max:500', 'questions.*.correct' => 'required|integer|min:0|max:5']);
        if ($data['type'] === 'quiz') {
            foreach ($data['questions'] as $question) {
                abort_unless(array_is_list($question['options']) && array_key_exists($question['correct'], $question['options']), 422);
            }
            $data['questions'] = array_values($data['questions']);
        }
        Assessment::create(array_merge($data, ['course_id' => $course->id, 'required' => $request->boolean('required')]));

        return back()->with('status', 'Assessment created. Existing assessment requirements remain frozen.');
    }

    public function attempt(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        $enrollment = $this->enrolled($course);
        $this->matching($course, $assessment, 'quiz');
        $data = $request->validate(['answers' => 'required|array', 'answers.*' => 'required|integer|min:0|max:5']);
        DB::transaction(function () use ($enrollment, $assessment, $data) {
            Enrollment::active()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $assessment = Assessment::whereKey($assessment->id)->firstOrFail();
            abort_if(AssessmentAttempt::where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->count() >= $assessment->attempt_limit, 422, 'Attempt limit reached.');
            $questions = $assessment->questions;
            abort_unless(count($data['answers']) === count($questions), 422);
            $correct = 0;
            foreach ($questions as $index => $question) {
                abort_unless(isset($data['answers'][$index]) && array_key_exists($data['answers'][$index], $question['options']), 422);
                $correct += (int) ((int) $data['answers'][$index] === (int) $question['correct']);
            }
            $score = (int) floor(100 * $correct / count($questions));
            AssessmentAttempt::create(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'snapshot' => ['title' => $assessment->title, 'questions' => $questions, 'pass_percent' => $assessment->pass_percent], 'answers' => $data['answers'], 'score' => $score, 'passed' => $score >= $assessment->pass_percent]);
        });

        return back()->with('status', 'Attempt scored. Your result is below.');
    }

    public function submit(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        $enrollment = $this->enrolled($course);
        $this->matching($course, $assessment, 'assignment');
        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,zip|max:10240']);
        $file = $request->file('file');
        $path = $file->store('assignments', 'local');
        abort_unless(is_string($path) && $path !== '', 500, 'Unable to save this submission. Please try again.');
        try {
            AssignmentSubmission::create(['assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id, 'path' => $path, 'original_name' => basename($file->getClientOriginalName())]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Assignment submitted for review.');
    }

    public function download(AssignmentSubmission $submission): StreamedResponse
    {
        $enrollment = Enrollment::findOrFail($submission->enrollment_id);
        $course = Course::findOrFail($enrollment->course_id);
        if ($enrollment->user_id !== auth()->id()) {
            $this->owner($course);
        } else {
            $this->enrolled($course);
        }

        return Storage::disk('local')->download($submission->path, $submission->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $assessment = Assessment::findOrFail($submission->assessment_id);
        $this->owner(Course::findOrFail($assessment->course_id));
        $data = $request->validate(['status' => 'required|in:accepted,revision-needed', 'feedback' => 'required|string|max:10000']);
        $submission->update($data + ['reviewer_id' => auth()->id()]);

        return back()->with('status', 'Review saved.');
    }

    public function settings(Request $request, Course $course): RedirectResponse
    {
        $this->owner($course);
        $request->validate(['enabled' => 'sometimes|boolean']);
        CertificateSetting::updateOrCreate(['course_id' => $course->id], ['enabled' => $request->boolean('enabled')]);

        return back()->with('status', 'Certificate settings saved.');
    }

    public function issue(Course $course): RedirectResponse
    {
        $enrollment = $this->enrolled($course);
        $certificate = DB::transaction(function () use ($course, $enrollment) {
            Enrollment::active()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $existing = Certificate::where('enrollment_id', $enrollment->id)->first();
            if ($existing) {
                return $existing;
            }
            abort_unless(CertificateSetting::where('course_id', $course->id)->where('enabled', true)->exists(), 422, 'Certificates are disabled for this course.');
            $lessonIds = $course->lessons()->where('published', true)->pluck('lessons.id');
            abort_unless($lessonIds->isNotEmpty() && $enrollment->progress()->whereIn('lesson_id', $lessonIds)->whereNotNull('completed_at')->count() === $lessonIds->count(), 422, 'Complete every current published lesson first.');
            foreach (Assessment::where('course_id', $course->id)->where('required', true)->get() as $assessment) {
                $passed = $assessment->type === 'quiz' ? AssessmentAttempt::where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->where('passed', true)->exists() : AssignmentSubmission::where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->where('status', 'accepted')->exists();
                abort_unless($passed, 422, 'Pass or receive acceptance for all required assessments first.');
            }

            return Certificate::create(['enrollment_id' => $enrollment->id, 'reference' => (string) Str::uuid(), 'learner_name' => auth()->user()->name, 'course_title' => $course->title, 'issued_at' => now()]);
        });

        return redirect()->route('certificates.print', $certificate);
    }

    public function certificates(): View
    {
        $certificates = Certificate::query()->when(auth()->user()->role !== 'admin', fn ($query) => $query->whereIn('enrollment_id', Enrollment::where('user_id', auth()->id())->select('id')))->latest()->paginate(20);

        return view('assessments.certificates', compact('certificates'));
    }

    public function print(Certificate $certificate): View
    {
        abort_unless(Enrollment::whereKey($certificate->enrollment_id)->where('user_id', auth()->id())->exists() || auth()->user()->role === 'admin', 403);

        return view('assessments.certificate', compact('certificate'));
    }

    public function verify(string $reference): View
    {
        $certificate = Certificate::where('reference', $reference)->firstOrFail();

        return view('assessments.verify', compact('certificate'));
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($certificate, $data) {
            $certificate = Certificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            if (! $certificate->revoked_at) {
                $certificate->update(['revoked_at' => now(), 'revocation_reason' => $data['reason']]);
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'certificate.revoked', 'subject_type' => Certificate::class, 'subject_id' => $certificate->id, 'changes' => ['reason' => $data['reason']]]);
            }
        });

        return back()->with('status', 'Certificate revoked.');
    }
}
