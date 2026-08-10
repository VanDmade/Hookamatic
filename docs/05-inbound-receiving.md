# Inbound Receiving

There isn't a separate "receiving" stage as its own class - the `hookamatic` middleware (see [Inbound Verification](06-inbound-verification.md)) receives, tracks, and verifies a request in one pass, wrapping *your own* route handler. The inbound functionality is more for logging purposes rather than anything crazy expensive to an inbound call.

## Wiring the middleware onto a route

```php
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('hookamatic:stripe');

// Optionally tag which of your own event types this route represents:
Route::post('webhooks/stripe/invoices', [StripeInvoiceController::class, 'handle'])
    ->middleware('hookamatic:stripe,invoice.paid');
```

`provider` and `eventType` are middleware *parameters* (`hookamatic:{provider},{eventType}`), not URL segments - `provider` must match a key under `hookamatic.inbound.verifiers` (see [Inbound Verification](06-inbound-verification.md)). `eventType` is optional and purely descriptive - it's stored on the `InboundEvent` row but doesn't affect verification.

## What gets tracked

Every request that reaches the middleware creates (or finds) an `InboundEvent` row, keyed on `provider` + the provider's own event ID (extracted from the payload via that provider's verifier, before verification even runs). A provider retrying the same event - which webhook providers do constantly - reuses the same row instead of creating duplicates.

## Idempotency

If the matched `InboundEvent` is already `processed`, the middleware returns a `200` immediately without calling `verify()` or your route handler at all. This is what makes retried/duplicate webhook deliveries safe by default - you don't need to build your own dedup logic on top. (Something might be up with your application or it is too slow.)

## Opting a route out

- `hookamatic.inbound.enabled` (default `true`) - global kill switch. `false` bypasses Hookamatic entirely for every provider, every route.
- `hookamatic.inbound.verifiers.{provider}.enabled` - same idea, scoped to one provider. See [Inbound Verification](06-inbound-verification.md#per-provider-kill-switch).
- `hookamatic.allow_without_provider` (default `false`) - if `true`, a route using the middleware with *no* provider parameter (`->middleware('hookamatic')` alone) bypasses Hookamatic instead of returning a 400. Useful for temporarily testing a route without the middleware actually doing anything; for a permanent case, just don't apply the middleware at all. Sometimes we forget to remove the middleware call... I am not naming names!

## See also

- [Inbound Verification](06-inbound-verification.md)
- [Inbound Processing](07-inbound-processing.md)
- [Configuration](09-configuration.md)
