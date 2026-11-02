<?php

use App\Http\Controllers\BankCheckoutController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payments', [CheckoutController::class, 'index'])->name('payments');
    Route::get('/checkout/{course:slug}/pay', [BankCheckoutController::class, 'show'])->name('checkout.bank');
    Route::post('/checkout/{course:slug}/pay', [BankCheckoutController::class, 'submit'])->middleware('throttle:6,1,checkout.bank')->name('checkout.bank.submit');
    Route::get('/payment-proofs/{submission}', [BankCheckoutController::class, 'proof'])->name('payment-proofs.show');
    Route::post('/checkout/{course:slug}', [CheckoutController::class, 'store'])->middleware('throttle:10,1,checkout.store')->name('checkout.store');
});
Route::post('/payments/webhook', [CheckoutController::class, 'webhook'])->middleware('throttle:120,1')->name('payments.webhook');
