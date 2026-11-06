<?php

namespace App\Livewire\Instructor;

use App\Filament\Pages\CourseStudio;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CourseEditor extends Component
{
    #[Locked]
    public ?int $courseId = null;

    /** Shown inside the admin panel's Course studio (no site banner; tabs live in the studio header). */
    #[Locked]
    public bool $embedded = false;

    public string $title = '';

    public string $slug = '';

    public string $summary = '';

    public string $description = '';

    public ?int $categoryId = null;

    /** Admin-only: who is shown as the course's instructor (an instructor account or an admin). */
    public ?int $instructorId = null;

    /** Admin-only: name to show when the instructor has no account (then $instructorId is left empty). */
    public string $instructorName = '';

    public string $level = 'beginner';

    public string $language = 'en';

    public bool $isFree = true;

    public string $price = '0.00';

    public string $outcomesText = '';

    public string $requirementsText = '';

    public string $moduleTitle = '';

    public ?int $lessonModuleId = null;

    #[Locked]
    public ?int $editingLessonId = null;

    public string $lessonTitle = '';

    public string $lessonBody = '';

    public bool $lessonPreview = false;

    /** Step being added/edited: text (pages split by a line with ---), video, quiz or assignment. */
    public string $stepType = 'text';

    /** Quiz step: [['prompt' => '', 'options' => ['', '', '', ''], 'correct' => 0], ...] */
    public array $quizQuestions = [];

    public int $quizPass = 70;

    /** Assignment step: "submit" = submitting unlocks the next step; "grade" = waits for an accepted grade. */
    public string $assignmentAdvance = 'grade';

    public function mount(?Course $course = null): void
    {
        $this->courseId = $course?->exists ? $course->id : null;
        $course = $this->editableCourse();
        $this->instructorId = $course?->instructor_name ? null : ($course?->instructor_id ?? auth()->id());
        $this->instructorName = (string) ($course?->instructor_name ?? '');
        if ($course) {
            $this->fill(['title' => $course->title, 'slug' => $course->slug, 'summary' => $course->summary ?? '', 'description' => $course->description ?? '', 'categoryId' => $course->category_id, 'level' => $course->level, 'language' => $course->language, 'isFree' => $course->is_free, 'price' => intdiv($course->price_minor, 100).'.'.str_pad((string) ($course->price_minor % 100), 2, '0', STR_PAD_LEFT), 'outcomesText' => implode("\n", $course->outcomes ?? []), 'requirementsText' => implode("\n", $course->requirements ?? [])]);
        }
    }

    private function editableCourse(): ?Course
    {
        abort_unless(auth()->user()?->hasVerifiedEmail(), 403);
        $course = $this->courseId ? Course::findOrFail($this->courseId) : null;
        $this->authorize($course ? 'update' : 'create', $course ?? Course::class);

        return $course;
    }

    public function save(): void
    {
        $course = $this->editableCourse();
        if ($this->slug === '') {
            $this->slug = Str::slug($this->title);
        }
        $this->validate(['title' => ['required', 'string', 'max:180'], 'slug' => ['required', 'alpha_dash', 'max:180', Rule::unique('courses', 'slug')->ignore($this->courseId)], 'summary' => ['required', 'string', 'max:500'], 'description' => ['required', 'string', 'max:30000'], 'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('active', true)], 'level' => ['in:beginner,intermediate,advanced'], 'language' => ['in:en,am'], 'isFree' => ['boolean'], 'price' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'outcomesText' => ['string', 'max:5000'], 'requirementsText' => ['string', 'max:5000']]);
        $parts = explode('.', $this->price);
        $minor = $this->isFree ? 0 : ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if (! $this->isFree && $minor === 0) {
            throw ValidationException::withMessages(['price' => 'Paid courses require a price greater than zero.']);
        }
        $lines = fn (string $text): array => array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn ($line) => $line !== ''));
        $isAdmin = auth()->user()->can('administer-academy');
        if ($isAdmin) {
            $this->instructorName = trim($this->instructorName);
            $this->validate([
                'instructorId' => ['nullable', 'required_without:instructorName', 'integer', Rule::exists('users', 'id')->where('status', 'active')->whereIn('role', ['instructor', 'admin'])],
                'instructorName' => ['nullable', 'required_without:instructorId', 'string', 'max:120'],
            ], ['instructorId.exists' => 'Choose an active instructor or admin account.', 'instructorName.required_without' => 'Type the instructor’s name, or choose an account.', 'instructorId.required_without' => 'Type the instructor’s name, or choose an account.']);
        }
        $data = ['title' => $this->title, 'slug' => $this->slug, 'summary' => $this->summary, 'description' => $this->description, 'category_id' => $this->categoryId, 'level' => $this->level, 'language' => $this->language, 'is_free' => $this->isFree, 'price_minor' => $minor, 'outcomes' => $lines($this->outcomesText), 'requirements' => $lines($this->requirementsText)];
        if ($isAdmin) {
            // No account chosen: the admin owns the course record and learners see the typed name.
            $data['instructor_id'] = $this->instructorId ?: auth()->id();
            $data['instructor_name'] = $this->instructorId ? null : $this->instructorName;
        }
        $createdSlug = null;
        DB::transaction(function () use ($course, $data, &$createdSlug) {
            if ($course) {
                $course->update($data);
                $this->returnToReview($course);
            } else {
                $created = Course::create($data + ['instructor_id' => auth()->id(), 'currency' => 'ETB', 'status' => 'draft']); // $data wins for admins
                $this->courseId = $created->id;
                $createdSlug = $created->slug;
            }
        });
        session()->flash('editor-status', 'Course saved. Instructor changes remain in draft until an administrator publishes them.');
        // A brand-new course in the studio: reload with the course in the address so the other tabs work.
        if ($this->embedded && $createdSlug) {
            $this->redirect(CourseStudio::getUrl(['course' => $createdSlug]));
        }
    }

    private function returnToReview(Course $course): void
    {
        if (auth()->user()->role !== 'admin' || ! $course->hasReadyCurriculum()) {
            Course::whereKey($course->id)->update(['status' => 'draft', 'published_at' => null]);
        }
    }

    public function addModule(): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        $this->validate(['moduleTitle' => ['required', 'string', 'max:180']]);
        DB::transaction(function () use ($course) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $course->modules()->create(['title' => $this->moduleTitle, 'sort_order' => (int) $course->modules()->max('sort_order') + 1]);
            $this->returnToReview($course);
        });
        $this->reset('moduleTitle');
    }

    public function editLesson(int $lessonId): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        $lesson = $course->lessons()->with('assessment')->findOrFail($lessonId);
        $this->editingLessonId = $lesson->id;
        $this->lessonModuleId = $lesson->module_id;
        $this->lessonTitle = $lesson->title;
        $this->lessonPreview = $lesson->is_preview;
        $this->stepType = $lesson->type;
        $this->lessonBody = match ($lesson->type) {
            'text' => implode("\n---\n", $lesson->textPages()),
            'quiz', 'assignment' => (string) ($lesson->assessment?->instructions ?? ''),
            default => (string) ($lesson->body ?? ''),
        };
        $this->quizQuestions = $lesson->type === 'quiz' ? array_map(fn ($q) => ['prompt' => $q['prompt'] ?? '', 'options' => array_pad($q['options'] ?? [], 4, ''), 'correct' => (int) ($q['correct'] ?? 0)], $lesson->assessment?->questions ?? []) : [];
        $this->quizPass = (int) ($lesson->assessment?->pass_percent ?: 70);
        $this->assignmentAdvance = $lesson->assessment?->advance_on ?? 'grade';
    }

    public function cancelLessonEdit(): void
    {
        $this->editableCourse();
        $this->resetStepForm();
    }

    private function resetStepForm(): void
    {
        $this->reset('editingLessonId', 'lessonTitle', 'lessonBody', 'lessonPreview', 'stepType', 'quizQuestions', 'quizPass', 'assignmentAdvance');
    }

    public function updatedStepType(): void
    {
        if ($this->stepType === 'quiz' && $this->quizQuestions === []) {
            $this->addQuestion();
        }
    }

    public function addQuestion(): void
    {
        $this->quizQuestions[] = ['prompt' => '', 'options' => ['', '', '', ''], 'correct' => 0];
    }

    public function removeQuestion(int $index): void
    {
        unset($this->quizQuestions[$index]);
        $this->quizQuestions = array_values($this->quizQuestions);
    }

    /** Add or save a step of any type. */
    public function addLesson(): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        $editing = $this->editingLessonId ? $course->lessons()->with('assessment')->findOrFail($this->editingLessonId) : null;
        $type = $editing?->type ?? $this->stepType;
        $rules = ['lessonModuleId' => ['required', 'integer'], 'lessonTitle' => ['required', 'string', 'max:180'], 'lessonPreview' => ['boolean'], 'stepType' => ['in:text,video,quiz,assignment']];
        $rules['lessonBody'] = [in_array($type, ['text', 'assignment'], true) ? 'required' : 'nullable', 'string', 'max:30000'];
        if ($type === 'quiz') {
            $rules += [
                'quizQuestions' => ['required', 'array', 'min:1', 'max:50'],
                'quizQuestions.*.prompt' => ['required', 'string', 'max:500'],
                'quizQuestions.*.options' => ['required', 'array', 'min:2', 'max:6'],
                'quizQuestions.*.options.*' => ['nullable', 'string', 'max:200'],
                'quizQuestions.*.correct' => ['required', 'integer', 'min:0', 'max:5'],
                'quizPass' => ['required', 'integer', 'min:1', 'max:100'],
            ];
        }
        if ($type === 'assignment') {
            $rules['assignmentAdvance'] = ['in:submit,grade'];
        }
        $this->validate($rules, ['quizQuestions.required' => 'Add at least one question.', 'quizQuestions.*.prompt.required' => 'Every question needs its text.']);
        $questions = $type === 'quiz' ? $this->cleanQuestions() : [];
        $module = $course->modules()->findOrFail($this->lessonModuleId);

        DB::transaction(function () use ($course, $module, $editing, $type, $questions) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $data = ['title' => $this->lessonTitle, 'type' => $type, 'is_preview' => $this->lessonPreview];
            if ($type === 'text') {
                // A line containing only --- starts a new page.
                $pages = array_values(array_filter(array_map('trim', preg_split('/^\s*-{3,}\s*$/m', $this->lessonBody) ?: []), fn ($page) => $page !== ''));
                $data += ['pages' => $pages, 'body' => $pages[0] ?? ''];
            } elseif ($type === 'video') {
                $data['body'] = $this->lessonBody;
            } else {
                $assessmentData = ['course_id' => $course->id, 'type' => $type, 'title' => $this->lessonTitle, 'instructions' => $this->lessonBody, 'required' => false];
                $assessmentData += $type === 'quiz'
                    ? ['questions' => $questions, 'pass_percent' => $this->quizPass, 'attempt_limit' => 0]
                    : ['questions' => null, 'pass_percent' => 70, 'attempt_limit' => 0, 'advance_on' => $this->assignmentAdvance];
                $assessment = $editing?->assessment;
                $assessment ? $assessment->update($assessmentData) : $assessment = Assessment::create($assessmentData);
                $data['assessment_id'] = $assessment->id;
            }

            if ($editing) {
                $sourceModuleId = $editing->module_id;
                if ($sourceModuleId !== $module->id) {
                    $data['sort_order'] = (int) $module->lessons()->max('sort_order') + 1;
                }
                // Saving a step shows it again, unless it's a video step still waiting for its video.
                $editing->update($data + ['module_id' => $module->id, 'published' => $type !== 'video' || (bool) $editing->video_asset_id]);
                if ($sourceModuleId !== $module->id) {
                    foreach ([$sourceModuleId, $module->id] as $moduleId) {
                        foreach ($course->modules()->findOrFail($moduleId)->lessons()->get() as $index => $orderedLesson) {
                            $orderedLesson->update(['sort_order' => $index + 1]);
                        }
                    }
                }
            } else {
                // Video steps stay hidden until their video is attached; other steps are visible straight away.
                $module->lessons()->create($data + ['published' => $type !== 'video', 'sort_order' => (int) $module->lessons()->max('sort_order') + 1]);
            }
            $this->returnToReview($course);
        });
        session()->flash('editor-status', $type === 'video' && ! $editing ? 'Video step added. Upload its video below or in Admin → Media Library; it appears to learners once the video is attached.' : 'Step saved.');
        $this->resetStepForm();
    }

    /** @return list<array{prompt: string, options: list<string>, correct: int}> */
    private function cleanQuestions(): array
    {
        $clean = [];
        foreach ($this->quizQuestions as $index => $question) {
            $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $question['options'] ?? []), fn ($o) => $o !== ''));
            $correctText = trim((string) ($question['options'][(int) $question['correct']] ?? ''));
            if (count($options) < 2 || $correctText === '') {
                throw ValidationException::withMessages(["quizQuestions.$index.options" => 'Each question needs at least two answers, and the correct one can’t be empty.']);
            }
            $clean[] = ['prompt' => trim($question['prompt']), 'options' => $options, 'correct' => array_search($correctText, $options, true)];
        }

        return $clean;
    }

    /** Move a step up or down within its chapter. */
    public function moveStep(int $lessonId, string $direction): void
    {
        $course = $this->editableCourse();
        abort_unless($course && in_array($direction, ['up', 'down'], true), 422);
        $lesson = $course->lessons()->findOrFail($lessonId);
        DB::transaction(function () use ($course, $lesson, $direction) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $ordered = $lesson->module->lessons()->orderBy('sort_order')->orderBy('id')->get()->values();
            $index = $ordered->search(fn ($item) => $item->id === $lesson->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= $ordered->count()) {
                return;
            }
            $items = $ordered->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['sort_order' => $position + 1]);
            }
            $this->returnToReview($course);
        });
    }

    public function unpublishLesson(int $lessonId): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        DB::transaction(function () use ($course, $lessonId) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $course->lessons()->findOrFail($lessonId)->update(['published' => false]);
            $this->returnToReview($course);
        });
    }

    public function publish(): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        $this->authorize('publish', $course);
        DB::transaction(function () use ($course) {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if (! $locked->category->active || ! $locked->summary || ! $locked->description || ! $locked->hasReadyCurriculum() || (! $locked->is_free && $locked->price_minor < 1)) {
                throw ValidationException::withMessages(['publication' => 'A course needs an active category, complete description, valid price, and complete lessons. Videos need verified assets and configured private storage.']);
            }
            $previous = $locked->status;
            $locked->update(['status' => 'published', 'published_at' => $locked->published_at ?? now()]);
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'course.published', 'subject_type' => 'course', 'subject_id' => $locked->id, 'changes' => ['status' => ['from' => $previous, 'to' => 'published']]]);
        });
        session()->flash('editor-status', 'Course published.');
    }

    /** Accounts an admin can assign as the course instructor. */
    public function instructorOptions(): Collection
    {
        return User::where('status', 'active')->whereIn('role', ['instructor', 'admin'])->orderByRaw("role = 'admin'")->orderBy('name')->get(['id', 'name', 'role']);
    }

    public function render(): View
    {
        $course = $this->editableCourse();
        $course?->load('modules.lessons');

        return view('livewire.instructor.course-editor', ['course' => $course, 'categories' => Category::where('active', true)->orderBy('name')->get()])->layout('components.layout', ['title' => 'Course editor']);
    }
}
