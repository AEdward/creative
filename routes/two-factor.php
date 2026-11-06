<?php

use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Staff two-step sign-in (RequireTwoFactor sends staff here before any other page).
Route::middleware(['auth', 'verified'])->prefix('two-factor')->name('two-factor.')->group(function (): void {
    Route::get('/setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1,two-factor.setup')->name('confirm');
    Route::get('/', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/', [TwoFactorController::class, 'verify'])->middleware('throttle:20,1,two-factor.verify')->name('verify');
});
