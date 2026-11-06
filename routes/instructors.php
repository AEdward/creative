<?php

use App\Http\Controllers\InstructorProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/instructors', [InstructorProfileController::class, 'index'])->name('instructors.index');
Route::get('/instructors/{instructorProfile}', [InstructorProfileController::class, 'show'])->whereNumber('instructorProfile')->name('instructors.show');
Route::get('/instructors/{instructorProfile}/photo', [InstructorProfileController::class, 'photo'])->whereNumber('instructorProfile')->name('instructors.photo');
Route::middleware(['auth', 'verified', 'can:instructor'])->group(function (): void {
    Route::get('/instructor/profile', [InstructorProfileController::class, 'edit'])->name('instructor.profile.edit');
    Route::patch('/instructor/profile', [InstructorProfileController::class, 'update'])->middleware('throttle:10,1,instructor.profile')->name('instructor.profile.update');
});
