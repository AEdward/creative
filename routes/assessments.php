<?php

use App\Http\Controllers\AssessmentController;
use Illuminate\Support\Facades\Route;

Route::get('/certificates/verify/{reference}', [AssessmentController::class, 'verify'])->middleware('throttle:60,1,certificates.verify')->name('certificates.verify');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/courses/{course:slug}/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::post('/courses/{course:slug}/assessments/{assessment}/attempt', [AssessmentController::class, 'attempt'])->middleware('throttle:20,1,assessments.attempt')->name('assessments.attempt');
    Route::post('/courses/{course:slug}/assessments/{assessment}/submit', [AssessmentController::class, 'submit'])->middleware('throttle:10,1,assignments.submit')->name('assignments.submit');
    Route::get('/assignments/{submission}/download', [AssessmentController::class, 'download'])->name('assignments.download');
    Route::get('/certificates', [AssessmentController::class, 'certificates'])->name('certificates.index');
    Route::post('/courses/{course:slug}/certificate', [AssessmentController::class, 'issue'])->name('certificates.issue');
    Route::get('/certificates/{certificate}/print', [AssessmentController::class, 'print'])->name('certificates.print');
    Route::post('/certificates/{certificate}/revoke', [AssessmentController::class, 'revoke'])->name('certificates.revoke');
    Route::get('/instructor/courses/{course:slug}/assessments', [AssessmentController::class, 'manage'])->name('instructor.assessments');
    Route::post('/instructor/courses/{course:slug}/assessments', [AssessmentController::class, 'store'])->name('instructor.assessments.store');
    Route::post('/instructor/courses/{course:slug}/certificate-settings', [AssessmentController::class, 'settings'])->name('instructor.certificates.settings');
    Route::post('/assignments/{submission}/review', [AssessmentController::class, 'review'])->name('assignments.review');
});
