<x-layout :title="__('Orders and payments')">
<div class="msg-head">
    <div><h1>{{ __('Orders & payments') }}</h1><p>{{ __('Your course purchases, receipts and refund requests.') }}</p></div>
</div>
@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
@error('reason')<div class="notice notice-error" role="alert">{{ __($message) }}</div>@enderror

@forelse($orders as $order)
    @php
        $label = ['awaiting_payment' => __('Waiting for confirmation'), 'paid' => __('Paid'), 'failed' => __('Not confirmed'), 'refunded' => __('Refunded'), 'expired' => __('Expired'), 'cancelled' => __('Cancelled'), 'pending' => __('Pending')][$order->status] ?? $order->status;
        $tone = ['paid' => 'ok', 'refunded' => 'muted', 'awaiting_payment' => 'warn'][$order->status] ?? 'bad';
    @endphp
    <article class="dash-card order-card">
        <header class="order-head">
            <div>
                <h2>{{ $order->items->pluck('title_snapshot')->join(', ') }}</h2>
                <p class="small">{{ $order->created_at->translatedFormat('M j, Y') }} · <span class="order-reference">{{ $order->reference }}</span></p>
            </div>
            <span class="order-badge {{ $tone }}">{{ $label }}</span>
        </header>
        <dl class="order-lines">
            <div><dt>{{ __('Price') }}</dt><dd>{{ $order->money($order->subtotal_minor) }}</dd></div>
            @if($order->discount_minor)<div><dt>{{ __('Discount') }}</dt><dd>−{{ $order->money($order->discount_minor) }}</dd></div>@endif
            @if($order->vat_minor)<div><dt>{{ __('Includes VAT (:rate%)', ['rate' => rtrim(rtrim(number_format($order->vat_percent, 2), '0'), '.')]) }}</dt><dd>{{ $order->money($order->vat_minor) }}</dd></div>@endif
            <div class="order-total"><dt>{{ __('Total') }}</dt><dd>{{ $order->totalLabel() }}</dd></div>
        </dl>
        @if($order->refund_status === 'requested')
            <p class="notice" role="status">{{ __('Refund requested on :date. An admin is reviewing it.', ['date' => $order->refund_requested_at?->translatedFormat('M j, Y')]) }}</p>
        @elseif($order->refund_status === 'declined')
            <p class="notice notice-error">{{ __('Refund declined: :note', ['note' => $order->refund_note]) }}</p>
        @elseif($order->refund_status === 'refunded')
            <p class="notice">{{ __('Refund approved. The money is returned by bank transfer.') }} {{ $order->refund_note }}</p>
        @elseif($order->canRequestRefund())
            <details class="order-refund">
                <summary>{{ __('Request a refund') }}</summary>
                <form method="POST" action="{{ route('payments.refund', $order->reference) }}">@csrf
                    <label for="refund-{{ $order->id }}">{{ __('Tell us why (an admin reviews each request; approving it removes access to the course)') }}</label>
                    <textarea id="refund-{{ $order->id }}" name="reason" rows="3" minlength="10" maxlength="1000" required></textarea>
                    <button type="submit" class="dash-outline">{{ __('Send request') }}</button>
                    <p class="small"><a href="{{ route('refund-policy') }}">{{ __('Read the refund policy') }}</a></p>
                </form>
            </details>
        @endif
    </article>
@empty
    <div class="dash-card dash-empty"><h2>{{ __('No orders yet.') }}</h2><p>{{ __('Courses you buy will appear here with their receipts.') }}</p><a class="button" href="{{ route('courses.index') }}">{{ __('Browse courses') }}</a></div>
@endforelse
<div class="pagination">{{ $orders->links() }}</div>
</x-layout>
