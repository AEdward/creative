<?php

namespace App\Services\Payments;

use App\Models\Order;

class UnavailableGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'unconfigured';
    }

    public function available(): bool
    {
        return false;
    }

    public function initiate(Order $order): PaymentSession
    {
        throw new \LogicException('No verified payment provider is configured.');
    }

    public function verify(array $evidence): VerifiedPayment
    {
        throw new \LogicException('No verified payment provider is configured.');
    }
}
