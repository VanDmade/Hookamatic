# Inbound Processing

There's no dedicated "processing job" - once verification passes, the middleware calls `$next($request)` and *your own* route handler is the processing step. This doc is about what happens around your handler, not instead of it. (Not my fault if it doesn't work)

## Your route handler is the processor

Whatever your route/controller does with the verified payload *is* the processing logic - Hookamatic doesn't dictate a shape for it, doesn't queue it for you, and doesn't parse the payload into anything beyond what's already on the request. (We are just a middleman... We don't tell you what to do! We try to protect you as best as you allow!)

## How success/failure is determined

After your handler returns, the middleware checks `$response->isSuccessful()` (a 2xx status) and sets the `InboundEvent`'s status to `processed` or `failed` accordingly. So a "processing failure" from Hookamatic's point of view just means your handler returned a non-2xx response - it has no visibility into *why*.

## Retry attempts & giving up

Webhook providers retry on anything other than a 2xx. `attempt_counter` on the `InboundEvent` increments only when a request passes verification and your handler actually runs - a forged/failed-signature request never counts against it, so an attacker guessing at an event ID can't burn through a legitimate event's retry budget before it's even arrived. Once `attempt_counter` reaches `hookamatic.inbound.max_attempts` (default 10) without a successful response, `InboundWebhookMaxAttempts` fires and Hookamatic starts returning `200` for that event instead of running your handler again - not because it succeeded, but to stop the provider from retrying indefinitely. Treat that event firing as "needs manual investigation." Shame on you for breaking something!

## Reacting to processing outcomes

| Event | Fires when |
|---|---|
| `InboundWebhookReceived` | Immediately, before verification - the earliest hook available. |
| `InboundWebhookVerificationFailed` | `verify()` returned `false`. See [Inbound Verification](06-inbound-verification.md#what-happens-on-failure). |
| `InboundWebhookProcessed` | Your route handler returned a 2xx. Carries the `Response` and how long it took. |
| `InboundWebhookMaxAttempts` | `attempt_counter` hit `max_attempts` without ever succeeding. |

Note there's no `InboundWebhookProcessingFailed` - a non-2xx response just leaves the `InboundEvent` `failed` and lets the provider retry; nothing fires except (eventually) `InboundWebhookMaxAttempts` if it never recovers.

## See also

- [Inbound Receiving](05-inbound-receiving.md)
- [Inbound Verification](06-inbound-verification.md)
- [Artisan Commands](08-artisan-commands.md)
