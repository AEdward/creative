<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CurriculumController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('update', $course);
        $course->load('modules.lessons.resources');

        return view('instructor.materials', compact('course'));
    }

    public function learners(Course $course): View
    {
        Gate::authorize('update', $course);
        $lessonIds = $course->lessons()->where('published', true)->select('lessons.id');
        $totalLessons = (clone $lessonIds)->count();
        $enrollments = $course->enrollments()->with('user:id,name')->withCount(['progress as completed_lessons_count' => fn ($progress) => $progress->whereIn('lesson_id', $lessonIds)->whereNotNull('completed_at')])->latest('enrolled_at')->paginate(20);

        return view('instructor.learners', compact('course', 'enrollments', 'totalLessons'));
    }

    public function module(Request $request, Course $course, Module $module): RedirectResponse
    {
        Gate::authorize('update', $course);
        abort_unless($module->course_id === $course->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:180']]);
        DB::transaction(function () use ($course, $module, $data): void {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $module->update($data);
            $this->draft($course);
        });

        return back()->with('status', 'Module updated.');
    }

    public function moveModule(Request $request, Course $course, Module $module): RedirectResponse
    {
        Gate::authorize('update', $course);
        abort_unless($module->course_id === $course->id, 404);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        DB::transaction(function () use ($course, $module, $data): void {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $this->reorder($course->modules()->get(), $module->id, $data['direction']);
            $this->draft($course);
        });

        return back()->with('status', 'Module order updated.');
    }

    public function moveLesson(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $course);
        abort_unless($lesson->module->course_id === $course->id, 404);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        DB::transaction(function () use ($course, $lesson, $data): void {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            $this->reorder($lesson->module->lessons()->get(), $lesson->id, $data['direction']);
            $this->draft($course);
        });

        return back()->with('status', 'Lesson order updated.');
    }

    private function reorder(Collection $items, int $id, string $direction): void
    {
        $ordered = $items->all();
        $index = $items->search(fn ($item) => $item->id === $id);
        $target = $index + ($direction === 'up' ? -1 : 1);
        if (isset($ordered[$target])) {
            [$ordered[$index],$ordered[$target]] = [$ordered[$target], $ordered[$index]];
        }
        foreach ($ordered as $position => $item) {
            $item->update(['sort_order' => $position + 1]);
        }
    }

    private function draft(Course $course): void
    {
        $course->update(['status' => 'draft', 'published_at' => null]);
    }
}
