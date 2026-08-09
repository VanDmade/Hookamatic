# Inbound Receiving

> One paragraph, and a note before writing the rest: there isn't a separate "receiving" stage as its own class - the `hookamatic` middleware (see [Inbound Verification](06-inbound-verification.md)) receives, tracks, and verifies in one pass, wrapping *your own* route. Decide here whether this doc should stay receiving-specific (the route/middleware wiring itself) or fold into 06.

## Wiring the middleware onto a route

> Short sentence + code block: `Route::post('webhooks/{provider}/{eventType?}', ...)->middleware('hookamatic')`, and what `{provider}`/`{eventType}` are used for.

## What gets tracked

> Short sentence: an `InboundEvent` row is created/found per provider+event id (`InboundEventService::findOrCreateByProviderAndEventId()`), so a provider retrying the same event doesn't create duplicates.

## Idempotency

> Short sentence: an event already `PROCESSED` short-circuits with a 200 immediately, before your route handler even runs.

## Opting a route out

> Short sentence: `hookamatic.inbound.enabled` (global kill switch) vs `hookamatic.allow_without_provider` vs just not applying the middleware at all - when you'd use each.

## See also

- [Inbound Verification](06-inbound-verification.md)
- [Inbound Processing](07-inbound-processing.md)
- [Configuration](09-configuration.md)
