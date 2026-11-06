<?php

use App\Http\Controllers\CatalogController;
use App\Support\AcademySettings;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [CatalogController::class, 'show'])->name('courses.show');
Route::get('/categories/{category:slug}', [CatalogController::class, 'index'])->name('categories.show');

foreach (['about' => ['A place for your creative potential.', 'Practical skills. Personal expression. New possibilities.'], 'contact' => ['Contact us', 'A conversation starts with a little curiosity.'], 'faq' => ['Good questions. Clear answers.', 'What to know before your next creative step.'], 'privacy' => ['Privacy policy', 'What we collect, why, and your choices.'], 'terms' => ['Terms of use', 'The agreement for using Creative Lab Academy.'], 'refund-policy' => ['Refund policy', 'When and how you can get your money back.'], 'cookies' => ['Cookie policy', 'The few cookies we use, and why.']] as $page => [$title, $intro]) {
    Route::view('/'.$page, 'static', ['page' => $page, 'title' => $title, 'intro' => $intro, 'legal' => in_array($page, AcademySettings::LEGAL_PAGES)])->name($page);
}

require __DIR__.'/auth.php';

require __DIR__.'/learning.php';

require __DIR__.'/account.php';

require __DIR__.'/payments.php';

require __DIR__.'/media.php';

require __DIR__.'/curriculum.php';

if (file_exists(__DIR__.'/assessments.php')) {
    require __DIR__.'/assessments.php';
}

require __DIR__.'/operations.php';

require __DIR__.'/engagement.php';
require __DIR__.'/messages.php';
require __DIR__.'/instructors.php';
require __DIR__.'/two-factor.php';
require __DIR__.'/chat.php';
require __DIR__.'/seo.php';
