# Artisan Commands

## `hookamatic:retry-failed-deliveries`

```bash
php artisan hookamatic:retry-failed-deliveries
php artisan hookamatic:retry-failed-deliveries --subscribers=42 --re-enable-subscriber
php artisan hookamatic:retry-failed-deliveries --priority=HIGH --start-date=2026-01-01 --end-date=2026-01-31
```

Revives every `exhausted` delivery matching the given filters - see [Retries & Backoff](04-retries-and-backoff.md#retrying-manually). Filters: `--event-type=`, `--subscribers=`, `--priority=` (repeatable), `--date=` (repeatable) or `--start-date=`/`--end-date=`. `--re-enable-subscriber` additionally re-enables any matched subscriber that was disabled *by the system* as a result.

## `hookamatic:outbound-stats`

```bash
php artisan hookamatic:outbound-stats
php artisan hookamatic:outbound-stats totals
php artisan hookamatic:outbound-stats --failed --exhausted
php artisan hookamatic:outbound-stats --subscribers=42
```

Prints a table of `Delivery` rows. The first argument picks a report shape:

| Report | Shows |
|---|---|
| *(none)* / `summary` | Every matching delivery, most recent first |
| `totals` | Row counts grouped by status |
| `totals_by_subscriber` | Row counts grouped by subscriber |
| `totals_by_event_type` | Row counts grouped by event type |

Filters: `--event-type=`, `--subscribers=`, `--organization=`, `--priority=` (all repeatable), `--pending`/`--sent`/`--failed`/`--exhausted`/`--paused` (any combination), `--date=` (repeatable) or `--start-date=`/`--end-date=`.

## `hookamatic:inbound-stats`

```bash
php artisan hookamatic:inbound-stats
php artisan hookamatic:inbound-stats totals_by_provider
php artisan hookamatic:inbound-stats --provider=stripe --failed
```

Same idea, for `InboundEvent` rows:

| Report | Shows |
|---|---|
| *(none)* / `summary` | Every matching inbound event, most recent first |
| `totals` | Row counts grouped by status |
| `totals_by_provider` | Row counts grouped by provider |

Filters: `--provider=`, `--event-type=` (both repeatable), `--pending`/`--processed`/`--failed`, `--date=` (repeatable) or `--start-date=`/`--end-date=`.

## `hookamatic:add-organization-scoping`

```bash
php artisan hookamatic:add-organization-scoping
```

Adds an `organization_id` column (with a foreign key to `hookamatic.organization_model`) to `hookamatic_subscribers` and `hookamatic_event_types`, for when you turn on multi-organization scoping *after* the base migrations already ran - see [Subscribers & Event Types](02-subscribers.md#multi-organization-scoping). Requires `organization_model` to already be set in config. Safe to run more than once; it skips any table that already has the column.

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Configuration](09-configuration.md)
