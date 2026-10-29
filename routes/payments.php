<?php

use App\Models\Course;
use App\Models\Order;
use App\Services\Payments\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/payments', fn () => view('payments', ['orders' => Order::where('user_id', auth()->id())->with('items', 'payment')->latest()->paginate(12)]))->name('payments');
    Route::post('/checkout/{course:slug}', function (Request $request, Course $course, CheckoutService $checkout) {
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $order = $checkout->create($request->user(), $course, $data['idempotency_key']);

        return response()->json(['order_reference' => $order->reference, 'status' => $order->status]);
    })->middleware('throttle:10,1,checkout.store')->name('checkout.store');
});
