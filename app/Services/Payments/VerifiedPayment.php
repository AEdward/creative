<?php

namespace App\Services\Payments;

final readonly class VerifiedPayment
{
    public function __construct(public string $provider, public string $eventId, public string $orderReference, public string $providerReference, public int $amountMinor, public string $currency, public bool $success) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->provider, $this->eventId, $this->orderReference, $this->providerReference, $this->amountMinor, $this->currency, $this->success], JSON_THROW_ON_ERROR));
    }
}
