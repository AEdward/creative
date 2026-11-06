<x-layout :title="__('Payment')">
@php
    $step = $pending ? 2 : ($selected ? 2 : 1);
    $thumb = 'images/categories/'.$course->category?->slug.'.webp';
    $money = fn (int $minor) => $course->currency.' '.number_format($minor / 100, 2);
    $amount = $money($quote['total']);
@endphp
<div class="pay-head">
    <span class="pay-head-icon" aria-hidden="true"><x-heroicon-s-wallet /></span>
    <div><h1>{{ __('Payment') }}</h1><p>{{ __('Complete your payment to get access to this course.') }}</p></div>
</div>

<ol class="pay-steps" aria-label="{{ __('Checkout progress') }}">
    @foreach([1 => 'Payment Method', 2 => 'Confirm Payment', 3 => 'Access Course'] as $n => $label)
        <li @class(['done' => $n < $step, 'current' => $n === $step]) @if($n === $step) aria-current="step" @endif><span>{{ $n }}</span>{{ __($label) }}</li>
    @endforeach
</ol>

@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
@error('course')<div class="notice notice-error" role="alert">{{ __($message) }}</div>@enderror

<div class="pay-grid">
    <aside class="pay-card pay-summary">
        <a class="pay-cover" href="{{ route('courses.show', $course->slug) }}">@if($course->thumbnail_key)<img src="{{ route('courses.thumbnail', $course->slug) }}" alt="">@elseif(file_exists(public_path($thumb)))<img src="{{ asset($thumb) }}" alt="">@endif<span class="pay-play" aria-hidden="true"><x-heroicon-s-play /></span></a>
        <h2>{{ $course->title }}</h2>
        <p class="pay-teacher"><span class="dash-ini" aria-hidden="true">{{ mb_substr($course->instructor_display_name ?? '?', 0, 1) }}</span>{{ $course->instructor_display_name }}</p>
        <dl>
            <div><dt>{{ __('Course Price') }}</dt><dd>{{ $money($quote['subtotal']) }}</dd></div>
            @if($quote['discount'])<div><dt>{{ __('Coupon :code', ['code' => $quote['code']]) }}</dt><dd>−{{ $money($quote['discount']) }}</dd></div>@endif
            @if($quote['vat'])<div><dt>{{ __('Includes VAT (:rate%)', ['rate' => rtrim(rtrim(number_format($quote['vat_percent'], 2), '0'), '.')]) }}</dt><dd>{{ $money($quote['vat']) }}</dd></div>@endif
            <div class="pay-total"><dt>{{ __('Total Amount') }}</dt><dd>{{ $amount }}</dd></div>
        </dl>
    </aside>

    <section class="pay-card pay-main">
        @if($pending)
            <div class="pay-waiting">
                <span class="pay-waiting-icon" aria-hidden="true"><x-heroicon-o-clock /></span>
                <h2>{{ __('Waiting for confirmation') }}</h2>
                <p>{{ __('We received your payment details. An admin will check the transfer and unlock the course — you’ll get a notification as soon as it’s confirmed.') }}</p>
                <dl class="pay-facts">
                    <div><dt>{{ __('Paid with') }}</dt><dd>{{ $pending->method?->name }}</dd></div>
                    @if($pending->transaction_reference)<div><dt>{{ __('Transaction ID') }}</dt><dd>{{ $pending->transaction_reference }}</dd></div>@endif
                    @if($pending->proof_path)<div><dt>{{ __('Receipt') }}</dt><dd><a href="{{ route('payment-proofs.show', $pending) }}" target="_blank" rel="noopener">{{ __('View uploaded screenshot') }}</a></dd></div>@endif
                    <div><dt>{{ __('Submitted') }}</dt><dd>{{ $pending->created_at->diffForHumans() }}</dd></div>
                </dl>
                <a class="dash-outline" href="{{ route('messages.index', ['to' => 'support']) }}"><x-heroicon-o-chat-bubble-left-right aria-hidden="true" />{{ __('Questions? Message support') }}</a>
            </div>
        @elseif($blocked)
            <div class="pay-waiting">
                <span class="pay-waiting-icon" aria-hidden="true"><x-heroicon-o-lock-closed /></span>
                <h2>{{ __('Please contact support first') }}</h2>
                <p>{{ __('Your access to this course was removed by an admin, so it can’t be bought again yet. Message support and we’ll sort it out — please don’t send a payment until then.') }}</p>
                <a class="dash-outline" href="{{ route('messages.index', ['to' => 'support']) }}"><x-heroicon-o-chat-bubble-left-right aria-hidden="true" />{{ __('Message support') }}</a>
            </div>
        @elseif(! $enabled)
            <div class="pay-waiting">
                <span class="pay-waiting-icon" aria-hidden="true"><x-heroicon-o-pause-circle /></span>
                <h2>{{ __('Payments are paused') }}</h2>
                <p>{{ __('We’re not taking payments right now. Please check back soon or message support.') }}</p>
                <a class="dash-outline" href="{{ route('messages.index', ['to' => 'support']) }}"><x-heroicon-o-chat-bubble-left-right aria-hidden="true" />{{ __('Message support') }}</a>
            </div>
        @elseif(! $selected)
            <h2 class="pay-title"><x-heroicon-o-credit-card aria-hidden="true" />{{ __('Choose Payment Method') }}</h2>
            @if($lastRejected)<div class="notice notice-error" role="alert">{{ __('Your last payment couldn’t be confirmed: :reason', ['reason' => $lastRejected->rejection_reason]) }}</div>@endif
            @if($methods->isEmpty())
                <p class="dash-empty">{{ __('Online payment isn’t available yet. Please message the support team to arrange access.') }}</p>
            @else
                <form method="GET" action="{{ route('checkout.bank', $course->slug) }}" class="pay-methods">
                    <fieldset><legend class="sr-only">{{ __('Payment method') }}</legend>
                        @foreach($methods as $method)
                            <label class="pay-method">
                                <input type="radio" name="method" value="{{ $method->id }}" required @checked($loop->first)>
                                <span class="pay-logo">@if($method->logoUrl())<img src="{{ $method->logoUrl() }}" alt="">@else<x-heroicon-o-building-library aria-hidden="true" />@endif</span>
                                <span class="pay-method-text"><b>{{ $method->name }}</b><small>{{ __('Transfer to our :bank account', ['bank' => $method->name]) }}</small></span>
                            </label>
                        @endforeach
                    </fieldset>
                    @if($quote['code'])<input type="hidden" name="coupon" value="{{ $quote['code'] }}">@endif
                    <button type="submit" class="pay-primary"><x-heroicon-s-lock-closed aria-hidden="true" />{{ __('Proceed to Pay') }}<x-heroicon-o-arrow-right aria-hidden="true" /></button>
                </form>
            @endif
        @else
            <h2 class="pay-title"><span class="pay-logo small">@if($selected->logoUrl())<img src="{{ $selected->logoUrl() }}" alt="">@else<x-heroicon-o-building-library aria-hidden="true" />@endif</span>{{ __('Pay with :bank', ['bank' => $selected->name]) }}</h2>
            <div class="pay-account">
                <p>{{ __('Transfer exactly :amount to this account:', ['amount' => $amount]) }}</p>
                <dl>
                    <div><dt>{{ __('Account name') }}</dt><dd>{{ $selected->account_name }}</dd></div>
                    <div><dt>{{ __('Account number') }}</dt><dd><code id="acct">{{ $selected->account_number }}</code><button type="button" class="pay-copy" data-copy="#acct">{{ __('Copy') }}</button></dd></div>
                    <div><dt>{{ __('Amount') }}</dt><dd>{{ $amount }}</dd></div>
                </dl>
                @if($selected->instructions)<p class="pay-instructions preserve-lines">{{ $selected->instructions }}</p>@endif
            </div>
            <form method="GET" action="{{ route('checkout.bank', $course->slug) }}" class="pay-coupon">
                <input type="hidden" name="method" value="{{ $selected->id }}">
                <label for="pay-coupon">{{ __('Have a coupon?') }}</label>
                <div><input id="pay-coupon" name="coupon" maxlength="{{ \App\Services\CouponService::MAX_CODE_LENGTH }}" autocomplete="off" value="{{ $quote['code'] ?? (is_string(request('coupon')) ? request('coupon') : '') }}"><button type="submit" class="dash-outline">{{ __('Apply') }}</button></div>
                @if($quote['error'])<p class="field-error" role="alert">{{ $quote['error'] }}</p>@elseif($quote['code'])<p class="small">{{ __('Coupon applied.') }}</p>@endif
            </form>
            <form method="POST" action="{{ route('checkout.bank.submit', $course->slug) }}" enctype="multipart/form-data" class="pay-confirm">@csrf
                <input type="hidden" name="payment_method_id" value="{{ $selected->id }}">
                @if($quote['code'])<input type="hidden" name="coupon" value="{{ $quote['code'] }}">@endif
                @error('coupon')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror
                <h3>{{ __('After you pay, confirm it here') }}</h3>
                <div class="form-field"><label for="pay-ref">{{ __('Transaction ID') }}</label><input id="pay-ref" name="transaction_reference" maxlength="120" autocomplete="off" value="{{ is_string(old('transaction_reference')) ? old('transaction_reference') : '' }}" placeholder="{{ __('e.g. FT24312ABC12') }}">@error('transaction_reference')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                <div class="form-field"><label for="pay-proof">{{ __('Receipt screenshot (optional if you entered the ID)') }}</label><input id="pay-proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"><p class="small">{{ __('JPG, PNG, WebP or PDF, up to 5 MB.') }}</p>@error('proof')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                <div class="pay-actions"><a href="{{ route('checkout.bank', $course->slug) }}" class="dash-outline">{{ __('Change method') }}</a><button type="submit" class="pay-primary" data-busy-label="{{ __('Submitting…') }}">{{ __('Submit payment') }}<x-heroicon-o-arrow-right aria-hidden="true" /></button></div>
            </form>
        @endif
        <p class="pay-secure"><x-heroicon-o-shield-check aria-hidden="true" />{{ __('Your course unlocks only after an admin confirms the transfer.') }}</p>
    </section>
</div>
</x-layout>
