<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payments', [CheckoutController::class, 'index'])->name('payments');
    Route::post('/checkout/{course:slug}', [CheckoutController::class, 'store'])->middleware('throttle:10,1,checkout.store')->name('checkout.store');
});
Route::post('/payments/webhook', [CheckoutController::class, 'webhook'])->middleware('throttle:120,1')->name('payments.webhook');
