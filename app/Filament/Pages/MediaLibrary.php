<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\VideoAsset;
use App\Services\Media\MediaStorage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class MediaLibrary extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.media-library';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 7;

    // Upload target, chosen step by step: course -> chapter (module) -> lesson.
    public ?int $courseId = null;

    public ?int $moduleId = null;

    public ?int $lessonId = null;

    public string $newChapter = '';

    public string $newLesson = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'content');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function updatedCourseId(): void
    {
        $this->reset('moduleId', 'lessonId', 'newChapter', 'newLesson');
    }

    public function updatedModuleId(): void
    {
        $this->reset('lessonId', 'newLesson');
    }

    public function addChapter(): void
    {
        abort_unless(static::canAccess(), 403);
        $course = Course::findOrFail($this->courseId);
        $title = $this->validate(['newChapter' => ['required', 'string', 'max:180']])['newChapter'];
        $module = $course->modules()->create(['title' => $title, 'sort_order' => (int) $course->modules()->max('sort_order') + 1]);
        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'module.created', 'subject_type' => 'module', 'subject_id' => $module->id, 'changes' => ['course' => $course->id, 'title' => $title]]);
        $this->moduleId = $module->id;
        $this->reset('newChapter', 'lessonId');
    }

    /** New video lessons start hidden; attaching the uploaded video publishes them (see MediaController::attach). */
    public function addLesson(): void
    {
        abort_unless(static::canAccess(), 403);
        $module = Module::where('course_id', $this->courseId)->findOrFail($this->moduleId);
        $title = $this->validate(['newLesson' => ['required', 'string', 'max:180']])['newLesson'];
        $lesson = $module->lessons()->create(['title' => $title, 'type' => 'video', 'published' => false, 'sort_order' => (int) $module->lessons()->max('sort_order') + 1]);
        AuditLog::create(['actor_id' => auth()->id(), 'action' => 'lesson.created', 'subject_type' => 'lesson', 'subject_id' => $lesson->id, 'changes' => ['module' => $module->id, 'title' => $title]]);
        $this->lessonId = $lesson->id;
        $this->reset('newLesson');
        Notification::make()->title('Lesson created — now choose the video file')->success()->send();
    }

    public function chapters(): Collection
    {
        return $this->courseId ? Module::where('course_id', $this->courseId)->orderBy('sort_order')->orderBy('id')->get(['id', 'title']) : collect();
    }

    public function lessons(): Collection
    {
        return $this->moduleId ? Lesson::where('module_id', $this->moduleId)->orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'video_asset_id']) : collect();
    }

    protected function getViewData(): array
    {
        $course = $this->courseId ? Course::find($this->courseId, ['id', 'slug', 'title']) : null;

        return [
            'storageReady' => app(MediaStorage::class)->available(),
            'course' => $course,
            'courses' => Course::orderBy('title')->get(['id', 'title', 'slug']),
            'videos' => VideoAsset::query()->latest()->paginate(20),
            'lessonsByAsset' => Lesson::whereNotNull('video_asset_id')->with('module:id,title')->get(['id', 'title', 'module_id', 'video_asset_id'])->keyBy('video_asset_id'),
        ];
    }
}
