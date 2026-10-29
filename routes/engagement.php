<?php

use App\Http\Controllers\EngagementController as Engagement;
use Illuminate\Support\Facades\Route;

Route::get('/courses/{course:slug}/reviews', [Engagement::class, 'reviews'])->name('reviews.index');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/courses/{course:slug}/reviews', [Engagement::class, 'review'])->middleware('throttle:10,1,reviews')->name('reviews.store');
    Route::get('/courses/{course:slug}/announcements', [Engagement::class, 'announcements'])->name('announcements.index');
    Route::get('/instructor/courses/{course:slug}/announcements', [Engagement::class, 'manageAnnouncements'])->name('instructor.announcements');
    Route::post('/instructor/courses/{course:slug}/announcements', [Engagement::class, 'announce'])->middleware('throttle:10,1,announcements')->name('announcements.store');
    Route::get('/notifications', [Engagement::class, 'notifications'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [Engagement::class, 'read'])->name('notifications.read');
    Route::post('/notification-preferences', [Engagement::class, 'preferences'])->name('notifications.preferences');
    Route::get('/support', [Engagement::class, 'tickets'])->name('support.index');
    Route::post('/support', [Engagement::class, 'ticketCreate'])->middleware('throttle:10,1,support-create')->name('support.store');
    Route::get('/support/{ticket}', [Engagement::class, 'ticket'])->name('support.show');
    Route::post('/support/{ticket}/replies', [Engagement::class, 'reply'])->middleware('throttle:20,1,support-reply')->name('support.reply');
    Route::post('/support/{ticket}/status', [Engagement::class, 'ticketStatus'])->name('support.status');
    Route::middleware('can:administer-academy')->prefix('academy-admin')->group(function () {
        Route::get('/reviews', [Engagement::class, 'moderation'])->name('admin.reviews');
        Route::post('/reviews/settings', [Engagement::class, 'reviewSettings'])->name('admin.reviews.settings');
        Route::post('/reviews/{review}', [Engagement::class, 'moderate'])->name('admin.reviews.moderate');
        Route::get('/support', [Engagement::class, 'adminTickets'])->name('admin.support');
    });
});
