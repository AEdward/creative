<?php

namespace App\Services\Payments;

use App\Models\Order;

interface PaymentGateway
{
    public function name(): string;

    public function available(): bool;

    /** Initiation must use the stored payment idempotency key when a real provider is implemented. */
    public function initiate(Order $order): PaymentSession;

    /** Real adapters must authenticate evidence, merchant identity and signatures before returning a receipt. */
    public function verify(array $evidence): VerifiedPayment;
}
