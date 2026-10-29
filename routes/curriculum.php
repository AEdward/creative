<?php

use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\LocalFileController;
use Illuminate\Support\Facades\Route;

Route::get('/lesson-resources/{resource}', [LocalFileController::class, 'download'])->middleware('throttle:60,1,resources.download')->name('resources.download');
Route::get('/courses/{course:slug}/thumbnail', [LocalFileController::class, 'thumbnailImage'])->name('courses.thumbnail');
Route::middleware('auth')->group(function (): void {
    Route::get('/profile/avatar', [LocalFileController::class, 'avatarImage'])->name('profile.avatar');
    Route::post('/profile/avatar', [LocalFileController::class, 'avatar'])->middleware('throttle:10,1,profile.avatar')->name('profile.avatar.store');
});
Route::middleware(['auth', 'verified', 'can:instructor', 'throttle:60,1,curriculum'])->prefix('/instructor/courses/{course:slug}')->group(function (): void {
    Route::get('/materials', [CurriculumController::class, 'index'])->name('instructor.materials');
    Route::put('/modules/{module}', [CurriculumController::class, 'module'])->name('curriculum.module');
    Route::post('/modules/{module}/move', [CurriculumController::class, 'moveModule'])->name('curriculum.module.move');
    Route::post('/lessons/{lesson}/move', [CurriculumController::class, 'moveLesson'])->name('curriculum.lesson.move');
    Route::post('/lessons/{lesson}/resources', [LocalFileController::class, 'store'])->name('resources.store');
    Route::delete('/resources/{resource}', [LocalFileController::class, 'destroy'])->name('resources.destroy');
    Route::post('/thumbnail', [LocalFileController::class, 'thumbnail'])->name('courses.thumbnail.store');
});
