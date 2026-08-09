# Configuration

## Publishing the config file

> Short sentence + command: `php artisan vendor:publish --tag=hookamatic-config`.

## `config/hookamatic.php`

> Table (mirror Cacheeze's `02-configuration-and-profiles.md` layout: Key | Env Var | Default | What it does) covering the top-level keys: `max_delivery_attempts`, `toggle_disabled_on_exhausted_delivery`, `toggle_disabled_after_exhausted_deliveries`, `organization_model`, `fail_loud_on_no_subscribers`, `encode_payload`, `signing_algorithm`.
>
> **Gap to resolve before writing this table**: none of these currently have `env()` calls in the config file (unlike Cacheeze's), so decide whether that's intentional or worth adding while you're in here.

## `retry`

> Short sentence + table: `delay`, `exponential_delay` - link to [Retries & Backoff](04-retries-and-backoff.md) rather than re-explaining the schedule math here.

## `outbound`

> Short sentence + table: `signer`, `retry_policy`, `rate_limiter.max_sends`/`rate_limiter.interval_seconds`, `max_lock_seconds`, `job_timeout`, `priority_aging_seconds`.

## `inbound`

> Short sentence + table: `enabled`, `verifiers.{provider}.class`/`secret`/`tolerance`/`enabled` - link to [Inbound Verification](06-inbound-verification.md) for how verifiers actually use these.

## Known gaps to flag or fill in before this doc ships

> These are referenced in code via `config('hookamatic.X', $default)` but don't actually exist as keys in `config/hookamatic.php` right now, so they currently just silently fall back to their default every time:
> - `allow_without_provider` (used in `VerifyInboundWebhook`)
> - `inbound.max_attempts` (used in `VerifyInboundWebhook`, defaults to `10`)
> - `outbound.response_protocols` (referenced by the `hookamatic_subscriber_events.response_protocol_reference` migration comment)
> - `outbound.api_keys` (referenced by the `hookamatic_subscribers.api_key_reference` migration comment)
>
> Either add them to `config/hookamatic.php` with real defaults, or don't document them as configurable yet - just don't document a default that doesn't actually exist in the file.

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Signing](03-signing.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Inbound Verification](06-inbound-verification.md)
