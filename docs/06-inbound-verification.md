# Inbound Verification

Before your route handler ever runs, the `hookamatic` middleware asks a per-provider `VerifierInterface` to confirm the request is genuinely from that provider - not spoofed by an attacker who knows your endpoint URL.

## Configuring a provider

```php
'inbound' => [
    'enabled' => true,
    'verifiers' => [
        'stripe' => [
            'class' => StripeVerifier::class,
            'secret' => env('HOOKAMATIC_STRIPE_WEBHOOK_SECRET'),
            'tolerance' => 300,
            'enabled' => true,
        ],
        // Add your own provider the same way - each one's secret is a real credential,
        // so always source it via env(), not just for Stripe.
    ],
],
```

`VerifierManager::resolve($provider)` reads `class` out of this config and resolves it through the container - if the key doesn't exist, it throws (and logs via `HookamaticLog`) rather than silently letting an unverified request through.

## The built-in Stripe verifier

`StripeVerifier` parses the `Stripe-Signature` header (`t=...,v1=...`), recomputes the HMAC over `{timestamp}.{payload}` with the configured `secret`, and compares with `hash_equals()`. It also rejects anything older than `tolerance` seconds (default 300) to block replay attacks. Header parsing (finding a header case-insensitively, since Symfony's `HeaderBag` handling can vary) goes through the shared `ExtractsHeaderValue` trait, which any custom verifier can use too.

## Writing your own verifier

```php
use VanDmade\Hookamatic\Inbound\Verification\VerifierInterface;

class MyVerifier implements VerifierInterface
{

    public function verify(string $payload, array $headers): bool
    {
        // Return true only if the request is genuinely from this provider.
    }

    public function extractEventId(string $payload, array $headers): ?string
    {
        // Return this provider's own ID for the event, used for idempotency
        // (see Inbound Receiving) - null if it can't be determined yet.
    }

}
```

Register it under a new key in `hookamatic.inbound.verifiers`, then use that key as the middleware's provider parameter: `->middleware('hookamatic:my-provider')`.

## What happens on failure

A `verify()` returning `false` marks the `InboundEvent` `failed`, fires `InboundWebhookVerificationFailed`, and the middleware responds `403` - your route handler never runs. This is different from your route handler itself returning a non-2xx response after verification passes; see [Inbound Processing](07-inbound-processing.md) for that case.

## Per-provider kill switch

`hookamatic.inbound.verifiers.{provider}.enabled` (default `true`) bypasses Hookamatic just for that one provider - your route handler runs directly, with no tracking or verification at all. Compare with the global `hookamatic.inbound.enabled`, which does the same for every provider at once. Had an issue in the past where I needed to quickly turn off a broken inbound webhook call, so I created this for the off chance you need it!

## See also

- [Inbound Receiving](05-inbound-receiving.md)
- [Inbound Processing](07-inbound-processing.md)
- [Configuration](09-configuration.md)
