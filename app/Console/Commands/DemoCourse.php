<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseDeletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds one complete test course to the live site, covering every kind of step (multi-page text,
 * video, quiz, assignment) and a small price so bank-transfer checkout can be tried end to end.
 * --remove deletes it again (with any test enrollments and certificates; test orders stay in Finance).
 */
class DemoCourse extends Command
{
    public const SLUG = 'demo-test-course';

    protected $signature = 'academy:demo-course {--remove : Delete the demo course again} {--price=50 : Price in ETB (0 for free)}';

    protected $description = 'Create (or remove) a demo course for testing the live site';

    public function handle(): int
    {
        $owner = User::where('role', 'admin')->where('status', 'active')->orderByDesc('is_owner')->orderBy('id')->first();
        if (! $owner) {
            $this->error('Create an admin account first.');

            return self::FAILURE;
        }
        $existing = Course::where('slug', self::SLUG)->first();

        if ($this->option('remove')) {
            if (! $existing) {
                $this->info('There is no demo course to remove.');

                return self::SUCCESS;
            }
            app(CourseDeletion::class)->delete($existing, $owner->forceFill(['staff_role' => 'super']), $existing->title);
            $this->info('Demo course removed.');

            return self::SUCCESS;
        }
        if ($existing) {
            $this->info('The demo course already exists: '.route('courses.show', $existing->slug));

            return self::SUCCESS;
        }

        $price = max(0, (int) round((float) $this->option('price') * 100));
        $course = DB::transaction(function () use ($owner, $price) {
            $course = Course::create([
                'slug' => self::SLUG,
                'title' => 'Demo: Creative Lab test course',
                'instructor_id' => $owner->id,
                'instructor_name' => 'Creative Lab Team',
                'category_id' => Category::where('active', true)->orderBy('id')->value('id') ?? Category::query()->value('id'),
                'summary' => 'A short test course that walks through every kind of step on the academy.',
                'description' => "This course exists to test the academy before real courses go live.\n\nIt has a multi-page reading step, a video step, a quiz with a pass mark and an assignment. Each step unlocks the next one.",
                'outcomes' => ['How locked, step-by-step courses work', 'Taking a quiz with a pass mark', 'Submitting an assignment'],
                'requirements' => ['No experience needed'],
                'level' => 'beginner',
                'language' => 'en',
                'price_minor' => $price,
                'currency' => 'ETB',
                'is_free' => $price === 0,
                'status' => 'published',
                'published_at' => now(),
            ]);

            $start = $course->modules()->create(['title' => 'Getting started', 'sort_order' => 1]);
            $start->lessons()->create([
                'title' => 'Welcome', 'type' => 'text', 'sort_order' => 1, 'published' => true, 'is_preview' => true, 'duration_seconds' => 120,
                'pages' => [
                    "Welcome to the demo course.\n\nThis first step is free to preview. It has three pages: read each one, then use \"Finish & continue\".",
                    "Courses here are step by step. The next step stays locked until you finish this one.\n\nVideos must be watched to the end, quizzes need a pass mark, and assignments are sent to the academy.",
                    "That's it. Click \"Finish & continue\" to unlock the video step.",
                ],
                'body' => 'Welcome to the demo course.',
            ]);
            // Hidden until a video is attached in Media Library (Demo course › Getting started › Watch a short video).
            $start->lessons()->create(['title' => 'Watch a short video', 'type' => 'video', 'sort_order' => 2, 'published' => false, 'body' => 'Watch to the end to unlock the quiz.']);

            $check = $course->modules()->create(['title' => 'Check your understanding', 'sort_order' => 2]);
            $quiz = Assessment::create([
                'course_id' => $course->id, 'type' => 'quiz', 'title' => 'Quick quiz', 'instructions' => 'Answer both questions. You need 50% to pass, and you can retry as often as you like.',
                'questions' => [
                    ['prompt' => 'What unlocks the next step?', 'options' => ['Finishing the current step', 'Waiting a day', 'Nothing, all steps are open'], 'correct' => 0],
                    ['prompt' => 'How many times can you retry a quiz?', 'options' => ['Once', 'Three times', 'As many as you like'], 'correct' => 2],
                ],
                'pass_percent' => 50, 'attempt_limit' => 0, 'required' => false,
            ]);
            $check->lessons()->create(['title' => 'Quick quiz', 'type' => 'quiz', 'assessment_id' => $quiz->id, 'sort_order' => 1, 'published' => true]);
            $task = Assessment::create([
                'course_id' => $course->id, 'type' => 'assignment', 'title' => 'Send a test file', 'instructions' => 'Upload any small image or PDF to test assignments. Submitting is enough to finish the course.',
                'questions' => null, 'pass_percent' => 70, 'attempt_limit' => 0, 'required' => false, 'advance_on' => 'submit',
            ]);
            $check->lessons()->create(['title' => 'Send a test file', 'type' => 'assignment', 'assessment_id' => $task->id, 'sort_order' => 2, 'published' => true]);

            AuditLog::create(['actor_id' => null, 'action' => 'course.demo_created', 'subject_type' => 'course', 'subject_id' => $course->id, 'changes' => ['price_minor' => $price]]);

            return $course;
        });

        $this->info('Demo course created: '.route('courses.show', $course->slug));
        $this->line('Price: '.($price ? number_format($price / 100, 2).' ETB' : 'free').'. To add the video: Media Library › "'.$course->title.'" › Getting started › Watch a short video.');
        $this->line('Remove it later with: php artisan academy:demo-course --remove');

        return self::SUCCESS;
    }
}
