<?php

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:10,1,messages.store')->name('messages.store');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->whereNumber('conversation')->name('messages.show');
    Route::post('/messages/{conversation}/reply', [MessageController::class, 'reply'])->whereNumber('conversation')->middleware('throttle:30,1,messages.reply')->name('messages.reply');
});
