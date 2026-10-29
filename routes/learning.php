<?php

use App\Http\Controllers\LearningController;
use App\Livewire\Instructor\CourseEditor;
use Illuminate\Support\Facades\Route;

Route::get('/learn/{course:slug}/{lesson}', [LearningController::class, 'show'])->name('learning.show');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/courses/{course:slug}/enroll', [LearningController::class, 'enroll'])->middleware('throttle:20,1')->name('courses.enroll');
    Route::get('/my-learning', [LearningController::class, 'index'])->name('learning.index');
    Route::post('/learn/{course:slug}/{lesson}/complete', [LearningController::class, 'complete'])->middleware('throttle:60,1')->name('learning.complete');
    Route::post('/learn/{course:slug}/{lesson}/progress', [LearningController::class, 'progress'])->middleware('throttle:60,1')->name('learning.progress');
    Route::middleware('can:instructor')->group(function () {
        Route::get('/instructor/courses/create', CourseEditor::class)->name('instructor.courses.create');
        Route::get('/instructor/courses/{course:slug}/edit', CourseEditor::class)->name('instructor.courses.edit');
    });
});
