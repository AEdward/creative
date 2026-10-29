<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/learn/{course:slug}/{lesson}/playback', [MediaController::class, 'playback'])->middleware('throttle:60,1,media.playback')->name('media.playback');
Route::middleware(['auth', 'verified', 'can:instructor', 'throttle:20,1,media.upload'])->prefix('/instructor/courses/{course:slug}/videos')->group(function (): void {
    Route::post('/', [MediaController::class, 'store'])->name('media.store');
    Route::post('/{asset}/confirm', [MediaController::class, 'confirm'])->name('media.confirm');
    Route::post('/{asset}/attach', [MediaController::class, 'attach'])->name('media.attach');
});
