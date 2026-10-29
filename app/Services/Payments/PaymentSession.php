<?php

namespace App\Services\Payments;

final readonly class PaymentSession
{
    public function __construct(public string $providerReference, public ?string $checkoutUrl = null) {}
}
