<?php

use App\Http\Controllers\SupportChatController;
use Illuminate\Support\Facades\Route;

// Chat bubble: help search and state work for visitors; sending needs a verified account.
Route::get('/chat/help', [SupportChatController::class, 'help'])->middleware('throttle:60,1,chat.help')->name('chat.help');
Route::get('/chat/state', [SupportChatController::class, 'state'])->middleware('throttle:40,1,chat.state')->name('chat.state');
Route::post('/chat/messages', [SupportChatController::class, 'send'])->middleware(['auth', 'verified', 'throttle:20,1,chat.send'])->name('chat.send');
