# Outbound Dispatching

> One paragraph: what "dispatching" means here and the one-line mental model (an event fires, matching subscribers get a signed POST queued for them).

## How to use

> Short sentence + code block: calling `Hookamatic::dispatch($event, $payload)` (or the facade), and where that lives in a typical controller/model.

## What happens under the hood

> Short sentence: `WebhookDispatcher` finds subscribers via their `EventType` subscriptions, `DeliverWebhookJob` sends each one, a `Delivery` row is recorded per attempt.

## Priority & delivery ordering

> Short sentence: each subscriber+event-type pairing has a `Priority` (LOWEST-HIGHEST), pending deliveries are ordered by it, and `priority_aging_seconds` bumps a stuck delivery's effective priority the longer it waits.

## Rate limiting

> Short sentence: per-subscriber `rate_limit_max`/`rate_limit_interval_seconds` (or the config fallback), what happens to a delivery when a subscriber is over its limit.

## Delivery lifecycle & events

> Short sentence + table: `DeliveryStatus` states (pending/sent/failed/exhausted/paused) and the `WebhookDelivered`/`WebhookDeliveryFailed`/`WebhookDeliveryExhausted` events fired along the way.

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Signing](03-signing.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Artisan Commands](08-artisan-commands.md)
