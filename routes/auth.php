<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Models\Course;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.form', ['mode' => 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1,login.store')->name('login.store');
    Route::view('/register', 'auth.form', ['mode' => 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1,register.store')->name('register.store');
    Route::view('/forgot-password', 'auth.form', ['mode' => 'forgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,1,password.email')->name('password.email');
    Route::get('/reset-password/{token}', fn (Request $request, string $token) => view('auth.form', ['mode' => 'reset', 'token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']))->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:6,1,password.update')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/verify-email', 'auth.verify')->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard');
    })->middleware(['signed', 'throttle:6,1,verification.verify'])->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Verification link sent.');
    })->middleware('throttle:3,1,verification.send')->name('verification.send');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/instructor', fn () => view('instructor.index', ['courses' => Course::with('category')->when(auth()->user()->role !== 'admin', fn ($query) => $query->where('instructor_id', auth()->id()))->latest()->paginate(12)]))->middleware('can:instructor')->name('instructor.dashboard');
});
