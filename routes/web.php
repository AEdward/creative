<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [CatalogController::class, 'show'])->name('courses.show');
Route::get('/categories/{category:slug}', [CatalogController::class, 'index'])->name('categories.show');

foreach (['about' => ['A place for your creative potential.', 'Practical skills. Personal expression. New possibilities.'], 'contact' => ['Contact us', 'A conversation starts with a little curiosity.'], 'faq' => ['Good questions. Clear answers.', 'What to know before your next creative step.'], 'privacy' => ['Privacy policy', 'How the academy will handle your information.'], 'terms' => ['Terms of use', 'The terms for using Creative Lab Academy.'], 'refund-policy' => ['Refund policy', 'Course payment and refund information.']] as $page => [$title, $intro]) {
    Route::view('/'.$page, 'static', ['page' => $page, 'title' => $title, 'intro' => $intro, 'legal' => in_array($page, ['privacy', 'terms', 'refund-policy'])])->name($page);
}

require __DIR__.'/auth.php';

require __DIR__.'/learning.php';

require __DIR__.'/account.php';
