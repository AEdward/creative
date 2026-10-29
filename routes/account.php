<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,10,contact.store')->name('contact.store');
Route::middleware('auth')->group(function () {
    Route::view('/profile', 'profile')->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1,profile.update')->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1,profile.password')->name('profile.password');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
    Route::post('/wishlist/{course:slug}', [WishlistController::class, 'store'])->middleware('throttle:30,1,wishlist.store')->name('wishlist.store');
    Route::delete('/wishlist/{course:slug}', [WishlistController::class, 'destroy'])->middleware('throttle:30,1,wishlist.destroy')->name('wishlist.destroy');
    Route::put('/learn/{course:slug}/{lesson}/notes', [LearningController::class, 'notes'])->middleware('throttle:30,1,learning.notes')->name('learning.notes');
});
