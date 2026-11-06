<?php

namespace App\Filament\Pages;

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\EngagementController;
use App\Models\Course;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * One admin-panel home for building a course: the step builder plus files, quizzes & certificate,
 * announcements and learners. The tabs reuse the existing screens, shown without the website layout;
 * their forms post to the same routes and come back here.
 */
class CourseStudio extends Page
{
    protected string $view = 'filament.pages.course-studio';

    protected static ?string $slug = 'course-studio';

    protected static bool $shouldRegisterNavigation = false;

    public const TABS = ['build' => 'Steps & videos', 'files' => 'Files & captions', 'quizzes' => 'Quizzes & certificate', 'announcements' => 'Announcements', 'learners' => 'Learners'];

    #[Url]
    public ?string $course = null;

    #[Url]
    public string $tab = 'build';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'content');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! array_key_exists($this->tab, self::TABS) || ! $this->course) {
            $this->tab = 'build';
        }
    }

    public function getTitle(): string
    {
        return $this->model()?->title ?? 'New course';
    }

    public function model(): ?Course
    {
        return $this->course ? Course::where('slug', $this->course)->firstOrFail() : null;
    }

    /** Non-builder tabs: the existing screen rendered inside the panel. */
    public function tabContent(): ?HtmlString
    {
        $course = $this->model();
        if (! $course || $this->tab === 'build') {
            return null;
        }
        $view = match ($this->tab) {
            'files' => app()->call([app(CurriculumController::class), 'index'], ['course' => $course]),
            'learners' => app()->call([app(CurriculumController::class), 'learners'], ['course' => $course]),
            'quizzes' => app()->call([app(AssessmentController::class), 'manage'], ['course' => $course]),
            'announcements' => app()->call([app(EngagementController::class), 'manageAnnouncements'], ['course' => $course]),
        };

        return new HtmlString($view->with('embedded', true)->render());
    }
}
