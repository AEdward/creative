<?php

use App\Models\Category;
use App\Models\Course;
use App\Support\HelpArticles;
use App\Support\ShareImage;
use Illuminate\Support\Facades\Route;

// Link-preview images (1200×630) for social apps and search results; cached on the server.
Route::get('/share/site.jpg', fn (ShareImage $images) => response()->file($images->default(), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=604800']))
    ->name('share.site');
Route::get('/share/courses/{slug}.jpg', function (string $slug, ShareImage $images) {
    $course = Course::published()->where('slug', $slug)->with('category')->firstOrFail();

    return response()->file($images->forCourse($course), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400']);
})->where('slug', '[A-Za-z0-9_-]+')->name('share.course');

// llms.txt: a plain-text guide for AI assistants and answer engines (GEO) — what the academy is,
// its courses with prices and languages, and where to find key information.
Route::get('/llms.txt', function () {
    $courses = Course::published()->with(['category:id,name'])->orderBy('title')->get();
    $lines = [
        '# Creative Lab Academy',
        '',
        '> Online academy in Ethiopia teaching practical creative skills — video editing, graphic design, motion design, 3D (Blender) and digital marketing — through project-based courses in English and Amharic. Learners pay in Ethiopian birr (ETB) by bank transfer; courses unlock step by step and award a certificate on completion.',
        '',
        '## Key pages',
        '- [All courses]('.route('courses.index').'): catalogue with prices, levels and languages',
        '- [About]('.route('about').'): who we are',
        '- [Questions & answers]('.route('faq').'): payments, locked lessons, quizzes, certificates, refunds',
        '- [Contact]('.route('contact').')',
        '- [Refund policy]('.route('refund-policy').')',
        '',
        '## Creative paths',
    ];
    foreach (Category::where('active', true)->orderBy('sort_order')->get(['name', 'slug']) as $category) {
        $lines[] = '- ['.$category->name.']('.route('categories.show', $category).')';
    }
    $lines[] = '';
    $lines[] = '## Courses';
    foreach ($courses as $course) {
        $price = $course->is_free ? 'Free' : ($course->currency ?: 'ETB').' '.number_format($course->price_minor / 100, 2);
        $lines[] = '- ['.$course->title.']('.route('courses.show', $course->slug).'): '.trim((string) $course->summary).' ('.$price.'; '.ucfirst((string) $course->level).'; '.($course->language === 'am' ? 'Amharic' : 'English').($course->category ? '; '.$course->category->name : '').($course->instructor_display_name ? '; instructor '.$course->instructor_display_name : '').')';
    }
    $lines[] = '';
    $lines[] = '## Common questions';
    foreach (array_slice(HelpArticles::all(), 0, 8) as $article) {
        $lines[] = '- '.$article['q'].' '.$article['a'];
    }

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
})->name('llms');
