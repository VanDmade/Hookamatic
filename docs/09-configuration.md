# Configuration

## Publishing the config file

```bash
php artisan vendor:publish --tag=hookamatic-config
```

Every key in `config/hookamatic.php` is a behavior/tuning setting, not sensitive data - so unlike some packages, none of them read from `env()`; they're set directly in the published file. The one exception is any verifier's `secret` under `inbound.verifiers.*` - that's an actual credential, so it always goes through `env()` instead, the same way you'd never hardcode a database password into a config file. This isn't Stripe-specific: it's why the built-in `stripe` entry uses `env('HOOKAMATIC_STRIPE_WEBHOOK_SECRET')`, and it's the pattern to follow for any other provider you configure later.

## `config/hookamatic.php` - top level

| Key | Default | What it does |
|---|---|---|
| `max_delivery_attempts` | `3` | Total attempts (including the first) before a delivery is marked `exhausted`. |
| `toggle_disabled_after_exhausted_deliveries` | `null` | Disable a subscriber after this many exhausted deliveries in the past day (the triggering one counts). Set to `1` to disable on the first exhaustion. |
| `organization_model` | `null` | Fully-qualified model class for multi-organization scoping. `null` disables it entirely. |
| `fail_loud_on_no_subscribers` | `false` | Throw instead of returning `false` from `dispatch()` when the event type has no subscribers. |
| `allow_without_provider` | `false` | Let a route using the `hookamatic` middleware with no provider parameter bypass Hookamatic entirely instead of returning a 400. |
| `signing_algorithm` | `'sha256'` | Algorithm passed to `hash_hmac()` when signing outbound payloads. |

See [Retries & Backoff](04-retries-and-backoff.md) for `max_delivery_attempts`/`toggle_disabled_after_exhausted_deliveries`; [Subscribers & Event Types](02-subscribers.md) for `organization_model`/`fail_loud_on_no_subscribers`; [Inbound Receiving](05-inbound-receiving.md#opting-a-route-out) for `allow_without_provider`; [Signing](03-signing.md) for `signing_algorithm`.

## `retry`

| Key | Default | What it does |
|---|---|---|
| `delay` | `5` | Base delay, in seconds, before a failed delivery is retried. |
| `exponential_delay` | `true` | Doubles the delay per attempt instead of using a flat `delay` every time. |

Full schedule math in [Retries & Backoff](04-retries-and-backoff.md#the-retry-policy).

## `outbound`

| Key | Default | What it does |
|---|---|---|
| `signer` | `HmacSigner::class` | Class implementing `SignerInterface`. |
| `retry_policy` | `BackoffRetryPolicy::class` | Class implementing `RetryPolicyInterface`. |
| `rate_limiter.max_sends` / `rate_limiter.interval_seconds` | `60` / `60` | Fallback rate limit when a subscriber doesn't set its own `rate_limit_max`/`rate_limit_interval_seconds`. |
| `max_lock_seconds` | `300` | Worst-case ceiling a per-delivery lock is held for - a crash safety net, not the expected send duration. |
| `job_timeout` | `900` | Ceiling for a whole `DeliverWebhookJob` run (which may process many deliveries). Larger than `max_lock_seconds` since one run covers many deliveries. |
| `priority_aging_seconds` | `300` | How often a waiting delivery's effective priority bumps up a level. `null` disables aging. |
| `api_keys` | `[]` | Maps a subscriber's `api_key_reference` to the actual credential to send - keeps real credentials out of the database. Not yet consumed anywhere in code; the column exists for this purpose but nothing reads this config key yet. |
| `response_protocols` | `[]` | Maps a subscription's `response_protocol_reference` to a handler invoked with the subscriber's response. Same caveat as `api_keys` - not yet consumed anywhere. |

See [Outbound Dispatching](01-outbound-dispatching.md).

## `inbound`

| Key | Default | What it does |
|---|---|---|
| `enabled` | `true` | Global kill switch for inbound verification/tracking, every provider. |
| `max_attempts` | `10` | How many *verified* attempts at the same provider event can fail before Hookamatic gives up and starts returning 200 anyway. Forged/failed-signature requests don't count. |
| `verifiers.{provider}.class` | - | Class implementing `VerifierInterface` for this provider. |
| `verifiers.{provider}.secret` | - | Shared secret used to verify this provider's signatures. |
| `verifiers.{provider}.tolerance` | - | Max signature age (seconds) before it's rejected as a possible replay. Built into `StripeVerifier`; a custom verifier decides for itself whether to use this. |
| `verifiers.{provider}.enabled` | `true` | Per-provider kill switch. |

See [Inbound Verification](06-inbound-verification.md) and [Inbound Processing](07-inbound-processing.md#retry-attempts--giving-up).

## `subscriber` / `event_type`

| Key | Default | What it does |
|---|---|---|
| `subscriber.default_sort_column` / `subscriber.default_sort_order` | `'created_at'` / `'asc'` | Default sort for `hookamatic/subscriber/data` and `hookamatic/list/subscribers` when the request doesn't specify one. |
| `event_type.default_sort_column` / `event_type.default_sort_order` | `'created_at'` / `'asc'` | Same, for the `event-type` endpoints. |

See [Subscribers & Event Types](02-subscribers.md#managing-subscribers--event-types-over-http).

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Signing](03-signing.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Inbound Verification](06-inbound-verification.md)
