<?php

namespace App\Services\Payments;

use Illuminate\Validation\ValidationException;

/**
 * HMAC check every real gateway adapter must run in verify() before trusting a callback:
 * signature over "<timestamp>.<raw body>" with the merchant secret, compared in constant time,
 * and rejected when older or newer than the tolerance (stops replays of captured callbacks).
 * Adapt the header names / signed string to the provider's official documentation.
 */
class WebhookSignature
{
    public static function sign(string $secret, string $rawBody, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public static function verify(string $secret, string $rawBody, ?string $signature, ?string $timestamp, int $toleranceSeconds = 300): void
    {
        if ($secret === '') {
            throw ValidationException::withMessages(['signature' => 'Webhook secret is not configured.']);
        }
        if (! is_string($signature) || ! is_string($timestamp) || ! ctype_digit($timestamp)) {
            throw ValidationException::withMessages(['signature' => 'Missing webhook signature.']);
        }
        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            throw ValidationException::withMessages(['signature' => 'Webhook timestamp is outside the allowed window.']);
        }
        if (! hash_equals(self::sign($secret, $rawBody, (int) $timestamp), strtolower($signature))) {
            throw ValidationException::withMessages(['signature' => 'Invalid webhook signature.']);
        }
    }
}
