<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Order;
use App\Services\CouponService;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(): View
    {
        return view('payments', ['orders' => Order::where('user_id', auth()->id())->with('items', 'payment')->latest()->paginate(12)]);
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $refunds->request($request->user(), $order, $data['reason']);

        return back()->with('status', 'Refund request sent. An admin will review it and you’ll get a notification.');
    }

    public function store(Request $request, Course $course, CheckoutService $checkout): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'coupon' => ['nullable', 'string', 'max:'.CouponService::MAX_CODE_LENGTH]]);
        $order = $checkout->create($request->user(), $course, $data['idempotency_key'], $data['coupon'] ?? null);

        return response()->json(['order_reference' => $order->reference, 'status' => $order->status]);
    }

    /**
     * Provider callback. The adapter's verify() must authenticate the raw body and signature headers;
     * replays of an already processed event return 200 so providers stop retrying.
     */
    public function webhook(Request $request, PaymentGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        abort_unless(config('academy.payments_enabled') && $gateway->available(), 503);
        $order = $processor->handle($gateway, $request->all() + ['raw_body' => $request->getContent(), 'headers' => $request->headers->all()]);

        return response()->json(['status' => $order->status]);
    }
}
