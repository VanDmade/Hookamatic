<?php

namespace VanDmade\Hookamatic\Inbound\Providers;

use VanDmade\Hookamatic\Inbound\Concerns\ExtractsHeaderValue;
use VanDmade\Hookamatic\Inbound\Verification\VerifierInterface;

class StripeVerifier implements VerifierInterface
{

    use ExtractsHeaderValue;

    public function verify(string $payload, array $headers): bool
    {
        $secret = config('hookamatic.inbound.verifiers.stripe.secret', null);
        if (is_null($secret)) {
            return false;
        }
        // Gets the signature header, which is a comma-separated list of key-value pairs.
        $signatureHeader = $this->headerValue($headers, 'stripe-signature');
        if (is_null($signatureHeader)) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $parts[$key] = $value;
        }
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if (is_null($timestamp) || is_null($signature)) {
            return false;
        }
        // Rejects signatures older than the tolerance window to prevent replay attacks.
        $tolerance = config('hookamatic.inbound.verifiers.stripe.tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function extractEventId(string $payload, array $headers): ?string
    {
        $decoded = json_decode($payload, true);
        return $decoded['id'] ?? null;
    }

}
