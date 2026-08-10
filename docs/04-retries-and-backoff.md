# Retries and Backoff

A failed delivery isn't given up on immediately (Never give up! Never surrender) - a new attempt is scheduled on a backoff delay, up to `hookamatic.max_delivery_attempts` (default 3) total attempts, before the delivery is marked `exhausted`. (It surrendered...)

## The retry policy

`BackoffRetryPolicy::nextAttemptDelay($attemptNumber)` returns how many seconds to wait before that attempt number runs. With `hookamatic.retry.exponential_delay` `true` (the default), the delay doubles each time: `delay * 2^(attemptNumber - 1)`. With it `false`, every retry waits the same flat `hookamatic.retry.delay` seconds.

With the defaults (`delay = 5`, exponential on):

| Attempt fails | Next attempt scheduled after |
|---|---|
| 1 | 10s (`5 * 2^1`) |
| 2 | 20s (`5 * 2^2`) |
| 3 | *(max_delivery_attempts reached - marked exhausted instead)* |

A retry isn't the same `Delivery` row updated in place - `DeliveryService::createAttempt()` creates a brand-new row with the same `delivery_uuid`, an incremented `attempt_number`, and `next_attempt_at` set to `now() + delay`. `DeliverWebhookJob` won't pick it up until that time passes. The original failed row stays as a permanent record of that attempt. (Log purposes... So you as the developer can blame someone else!)

## Exhausted deliveries

Once a delivery's `attempt_number` reaches `hookamatic.max_delivery_attempts`, its status becomes `exhausted` instead of scheduling another retry, and `WebhookDeliveryExhausted` fires. `toggle_disabled_after_exhausted_deliveries` (default `null`) can react automatically - it disables the subscriber once this many deliveries (including the one that just exhausted) have exhausted within the past 24 hours. Set it to `1` to disable on the very first exhaustion; leave it `null` to disable this check entirely.

Either way, the subscriber ends up disabled with `disabled_by_system = true` via `SubscriberService::markAsDisabled()` - see [Subscribers & Event Types](02-subscribers.md#disabling--enabling-a-subscriber).

## Retrying manually

```bash
php artisan hookamatic:retry-failed-deliveries
php artisan hookamatic:retry-failed-deliveries --subscribers=42 --re-enable-subscriber
```

Finds every `exhausted` delivery matching the given filters and calls `DeliveryService::reviveExhausted()` on each - which creates a fresh attempt-1 `Delivery`, exactly like a normal retry. `--re-enable-subscriber` additionally re-enables any matched subscriber that was disabled *by the system* (not manually) as a result of those exhausted deliveries. See [Artisan Commands](08-artisan-commands.md) for the full filter flag reference.

## Swapping the retry policy

```php
use VanDmade\Hookamatic\Outbound\Retry\RetryPolicyInterface;

class MyRetryPolicy implements RetryPolicyInterface
{
    public function nextAttemptDelay(int $attemptNumber): ?int
    {
        // Return null to mean "don't retry" for a given attempt number.
        // How dare you rewrite mine... I wrote it perfectly.
    }
}
```

Set `hookamatic.outbound.retry_policy` to your class and `DeliverWebhookJob` will use it instead of `BackoffRetryPolicy`.

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Artisan Commands](08-artisan-commands.md)
- [Configuration](09-configuration.md)
