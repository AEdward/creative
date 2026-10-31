<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo catalogue taught by the demo instructor, with the demo student enrolled in two courses.
 * Run after DemoUserSeeder: php artisan db:seed --class=DemoCourseSeeder. Refuses to run in production.
 */
class DemoCourseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo courses must not be created in production.');
        }
        $this->call([CategorySeeder::class, DemoUserSeeder::class]);
        $instructor = User::where('email', 'instructor@demo.test')->firstOrFail();
        $student = User::where('email', 'student@demo.test')->firstOrFail();

        $courses = [
            ['video-editing', 'Complete Video Editing Masterclass with Premiere Pro', 'beginner', 24, 510, 0],
            ['graphic-design', 'Graphic Design Fundamentals with Photoshop', 'beginner', 18, 375, 0],
            ['motion-design', 'Motion Graphics & Animation with After Effects', 'intermediate', 20, 460, 150000],
            ['3d-blender', '3D Modeling & Animation with Blender', 'intermediate', 28, 620, 180000],
            ['digital-marketing', 'Digital Marketing for Creators and Small Businesses', 'beginner', 16, 310, 90000],
            ['graphic-design', 'UI/UX Design with Figma', 'intermediate', 22, 385, 120000],
            ['video-editing', 'Photography Basics for Content Creators', 'beginner', 14, 290, 0],
            ['video-editing', 'Advanced Editing Techniques', 'advanced', 26, 555, 200000],
        ];
        foreach ($courses as $i => [$categorySlug, $title, $level, $lessonCount, $minutes, $price]) {
            $course = Course::updateOrCreate(['slug' => \Illuminate\Support\Str::slug($title)], [
                'instructor_id' => $instructor->id,
                'category_id' => Category::where('slug', $categorySlug)->value('id'),
                'title' => $title,
                'summary' => 'A practical, project-based course: '.$title.'.',
                'description' => 'Demo course for local development.',
                'level' => $level,
                'language' => 'en',
                'price_minor' => $price,
                'currency' => 'ETB',
                'is_free' => $price === 0,
                'status' => 'published',
                'published_at' => now()->subDays($i),
            ]);
            if ($course->modules()->doesntExist()) {
                $module = $course->modules()->create(['title' => 'Getting started', 'sort_order' => 1]);
                $per = intdiv($minutes * 60, $lessonCount);
                for ($n = 1; $n <= $lessonCount; $n++) {
                    $module->lessons()->create(['title' => 'Lesson '.$n, 'type' => 'text', 'body' => 'Demo lesson content.', 'duration_seconds' => $per, 'sort_order' => $n, 'published' => true, 'is_preview' => $n === 1]);
                }
            }
        }

        foreach (Course::whereIn('slug', ['complete-video-editing-masterclass-with-premiere-pro', 'graphic-design-fundamentals-with-photoshop'])->get() as $course) {
            Enrollment::firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id], ['status' => 'active', 'source' => 'free', 'enrolled_at' => now()]);
            CourseReview::firstOrCreate(['course_id' => $course->id, 'user_id' => $student->id], ['rating' => 5, 'body' => 'Clear and practical.', 'status' => 'approved']);
        }
        $this->command?->info('Demo catalogue ready: '.count($courses).' courses.');
    }
}
