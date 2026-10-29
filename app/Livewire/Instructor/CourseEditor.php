<?php

namespace App\Livewire\Instructor;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
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

    public string $title = '';

    public string $slug = '';

    public string $summary = '';

    public string $description = '';

    public ?int $categoryId = null;

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

    public function mount(?Course $course = null): void
    {
        $this->courseId = $course?->exists ? $course->id : null;
        $course = $this->editableCourse();
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
        $data = ['title' => $this->title, 'slug' => $this->slug, 'summary' => $this->summary, 'description' => $this->description, 'category_id' => $this->categoryId, 'level' => $this->level, 'language' => $this->language, 'is_free' => $this->isFree, 'price_minor' => $minor, 'outcomes' => $lines($this->outcomesText), 'requirements' => $lines($this->requirementsText)];
        DB::transaction(function () use ($course, $data) {
            if ($course) {
                $course->update($data);
                $this->returnToReview($course);
            } else {
                $created = Course::create($data + ['instructor_id' => auth()->id(), 'currency' => 'ETB', 'status' => 'draft']);
                $this->courseId = $created->id;
            }
        });
        session()->flash('editor-status', 'Course saved. Instructor changes remain in draft until an administrator publishes them.');
    }

    private function returnToReview(Course $course): void
    {
        if (auth()->user()->role !== 'admin') {
            $course->update(['status' => 'draft', 'published_at' => null]);
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
        $lesson = $course->lessons()->findOrFail($lessonId);
        abort_unless($lesson->type === 'text', 422);
        $this->editingLessonId = $lesson->id;
        $this->lessonModuleId = $lesson->module_id;
        $this->lessonTitle = $lesson->title;
        $this->lessonBody = $lesson->body ?? '';
        $this->lessonPreview = $lesson->is_preview;
    }

    public function cancelLessonEdit(): void
    {
        $this->editableCourse();
        $this->reset('editingLessonId', 'lessonTitle', 'lessonBody', 'lessonPreview');
    }

    public function addLesson(): void
    {
        $course = $this->editableCourse();
        abort_unless($course, 422);
        $this->validate(['lessonModuleId' => ['required', 'integer'], 'lessonTitle' => ['required', 'string', 'max:180'], 'lessonBody' => ['required', 'string', 'max:30000'], 'lessonPreview' => ['boolean']]);
        $module = $course->modules()->findOrFail($this->lessonModuleId);
        DB::transaction(function () use ($course, $module) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $data = ['title' => $this->lessonTitle, 'type' => 'text', 'body' => $this->lessonBody, 'is_preview' => $this->lessonPreview, 'published' => true];
            if ($this->editingLessonId) {
                $lesson = $course->lessons()->findOrFail($this->editingLessonId);
                if ($lesson->module_id !== $module->id) {
                    $data['sort_order'] = (int) $module->lessons()->max('sort_order') + 1;
                }
                $lesson->update($data + ['module_id' => $module->id]);
            } else {
                $module->lessons()->create($data + ['sort_order' => (int) $module->lessons()->max('sort_order') + 1]);
            }
            $this->returnToReview($course);
        });
        $this->reset('editingLessonId', 'lessonTitle', 'lessonBody', 'lessonPreview');
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
            if (! $locked->category->active || ! $locked->summary || ! $locked->description || ! $locked->lessons()->where('published', true)->where('type', 'text')->whereNotNull('body')->where('body', '!=', '')->exists() || $locked->lessons()->where('published', true)->where('type', 'video')->exists() || (! $locked->is_free && $locked->price_minor < 1)) {
                throw ValidationException::withMessages(['publication' => 'A course needs an active category, complete description, valid price, and complete text lessons. Unattached video lessons must stay unpublished until media integration is ready.']);
            }
            $previous = $locked->status;
            $locked->update(['status' => 'published', 'published_at' => $locked->published_at ?? now()]);
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'course.published', 'subject_type' => 'course', 'subject_id' => $locked->id, 'changes' => ['status' => ['from' => $previous, 'to' => 'published']]]);
        });
        session()->flash('editor-status', 'Course published.');
    }

    public function render(): View
    {
        $course = $this->editableCourse();
        $course?->load('modules.lessons');

        return view('livewire.instructor.course-editor', ['course' => $course, 'categories' => Category::where('active', true)->orderBy('name')->get()])->layout('components.layout', ['title' => 'Course editor']);
    }
}
