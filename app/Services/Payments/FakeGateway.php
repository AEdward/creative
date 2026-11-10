<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Validator;

/** Internal test fixtures only. This is not a telebirr or bank API contract. */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function available(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    private function guard(): void
    {
        if (! $this->available()) {
            throw new \LogicException('Fake payments are forbidden outside local/testing environments.');
        }
    }

    public function initiate(Order $order): PaymentSession
    {
        $this->guard();

        return new PaymentSession('fake_'.$order->reference);
    }

    public function verify(array $evidence): VerifiedPayment
    {
        $this->guard();
        $data = Validator::make($evidence, ['event_id' => ['required', 'string', 'max:128'], 'order_reference' => ['required', 'uuid'], 'provider_reference' => ['required', 'string', 'max:255'], 'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999'], 'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'], 'success' => ['required', 'boolean']])->validate();

        return new VerifiedPayment('fake', $data['event_id'], $data['order_reference'], $data['provider_reference'], (int) $data['amount_minor'], $data['currency'], (bool) $data['success']);
    }
}
