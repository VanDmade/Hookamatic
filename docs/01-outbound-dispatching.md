# Outbound Dispatching

Dispatching fires a named event with a payload; every `Subscriber` subscribed to that event type gets a signed HTTP POST queued for it. Nothing is sent synchronously - dispatching only ever creates `Delivery` rows, and `DeliverWebhookJob` is what actually sends them.

## How to use

```php
use VanDmade\Hookamatic\Facades\Hookamatic;

Hookamatic::dispatch('order.shipped', [
    'order_id' => $order->id,
    'tracking_number' => $shipment->tracking_number,
]);
```

`dispatch()` looks up the `EventType` by name, finds every `Subscriber` attached to it, and creates one `Delivery` per subscriber. It returns `false` (rather than throwing) if the event type has no subscribers - unless `hookamatic.fail_loud_on_no_subscribers` is `true`, in which case it throws an error instead. If the event type name itself doesn't exist as an `EventType`, it always throws an error.

A disabled subscriber still gets a `Delivery` row created for it - just with a `paused` status instead of `pending`, so nothing is actually attempted. That row becomes a ready-to-run queue entry the moment the subscriber is re-enabled.

## Actually sending: DeliverWebhookJob

Dispatching only creates the `Delivery` rows - you're responsible for running `DeliverWebhookJob` (typically on a schedule):

```php
use VanDmade\Hookamatic\Jobs\DeliverWebhookJob;

DeliverWebhookJob::dispatch();

// Only for specific subscribers (by ID or slug):
DeliverWebhookJob::dispatch(['acme-inc', 42]);

// Every subscriber EXCEPT these:
DeliverWebhookJob::dispatch(['acme-inc'], exclude: true);
```

Each run pulls every `pending` delivery whose `next_attempt_at` has passed (or is null), UNLESS subscribers are attached to the job. The job skips anything already locked by another in-flight run, and for each one: checks the rate limiter, acquires a per-delivery cache lock (`hookamatic.outbound.max_lock_seconds`, default 300s), signs and sends it, then records the result. If you forget to prevent overlapping scheduled runs (`->withoutOverlapping()`), or dispatch jobs with overlapping subscriber sets, duplicate sends are possible (Rare, but can happen) - the per-delivery lock only protects against overlap *within* the deliveries a single run has already started sending.

## Priority & delivery ordering

Each subscriber and event type pairing (`hookamatic_subscriber_events`) has its own `Priority`, from `LOWEST` to `HIGHEST` (default `NORMAL`), captured onto the `Delivery` at creation time. Pending deliveries are always fetched in priority order (highest first), then oldest-first within the same priority.

`hookamatic.outbound.priority_aging_seconds` (default 300) prevents a low-priority delivery from waiting forever behind a constant stream of high-priority ones: for every interval it waits past its `next_attempt_at`/`created_at`, its *effective* priority (for ordering only - the stored `priority` column never changes) bumps up one level. Set it to `null` to disable aging entirely.

## Rate limiting

Each subscriber can set its own `rate_limit_max`/`rate_limit_interval_seconds`; if either is unset, `hookamatic.outbound.rate_limiter.max_sends`/`interval_seconds` (default 60 sends per 60 seconds) applies instead. `WebhookRateLimiter` tracks a continuously changing window per subscriber in the cache - once a subscriber is over its limit, `DeliverWebhookJob` simply skips that delivery for this run and leaves it `pending` for the next one. There's no dedicated "rate limited" status; it just doesn't get picked up yet.

## Delivery lifecycle & events

| Status | Meaning |
|---|---|
| `pending` | Waiting to be sent (or retried) |
| `sent` | Delivered successfully (2xx response) |
| `failed` | This attempt failed, but a new `pending` retry attempt was created |
| `exhausted` | Failed `hookamatic.max_delivery_attempts` (default 3) times - no further attempts |
| `paused` | Created for a subscriber that was disabled at dispatch time |

A failed delivery doesn't get retried immediately - `DeliveryService::createAttempt()` creates a brand-new `Delivery` row (same `delivery_uuid`, incremented `attempt_number`, `next_attempt_at` set per the retry policy) and leaves the failed one as a historical record. See [Retries & Backoff](04-retries-and-backoff.md) for the delay schedule.

`WebhookDelivered`, `WebhookDeliveryFailed`, and `WebhookDeliveryExhausted` all fire after the delivery's final state is saved, so listeners always see persisted data. `WebhookDeliveryExhausted` also tells you whether the subscriber was auto-disabled as a result (`toggle_disabled_after_exhausted_deliveries` in config).

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Signing](03-signing.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Artisan Commands](08-artisan-commands.md)
