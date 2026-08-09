# Retries and Backoff

> One paragraph: a failed delivery isn't given up on immediately - it's retried on a backoff schedule up to `max_delivery_attempts`, then marked exhausted.

## The retry policy

> Short sentence: `RetryPolicyInterface`/`BackoffRetryPolicy`, what `retry.delay` and `retry.exponential_delay` actually do to the schedule (with a quick worked example: attempt 2, 3, 4 delays).

## Exhausted deliveries

> Short sentence: what "exhausted" means (`DeliveryStatus::EXHAUSTED`), and the `toggle_disabled_on_exhausted_delivery`/`toggle_disabled_after_exhausted_deliveries` config options that can auto-disable a misbehaving subscriber.

## Retrying manually

> Short sentence + command: `php artisan hookamatic:retry-failed-deliveries` and its filter flags (event type/subscriber/organization/status/date) - link to [Artisan Commands](08-artisan-commands.md) for the full flag reference rather than repeating it here.

## Swapping the retry policy

> Short sentence: implement `RetryPolicyInterface` yourself and set `hookamatic.outbound.retry_policy` in config.

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Artisan Commands](08-artisan-commands.md)
- [Configuration](09-configuration.md)
