# Artisan Commands

## `hookamatic:retry-failed-deliveries`

> Short sentence + example command: what it retries (failed/exhausted, depending on flags), and its filter flags (event type, subscriber, organization, priority, status, date range).

## `hookamatic:outbound-stats`

> Short sentence + table: the report argument (`summary`/`totals`/`totals_by_subscriber`/`totals_by_event_type`) and the filter flags (`--event-type`, `--subscribers`, `--organization`, `--priority`, status flags, date range) - mirror the layout of Cacheeze's `cacheeze:stats` section.

## `hookamatic:inbound-stats`

> Short sentence + table: the report argument (`summary`/`totals`/`totals_by_provider`) and its filter flags (`--provider`, `--event-type`, status flags, date range).

## `hookamatic:add-organization-scoping`

> Short sentence: what it does (adds `organization_id` to `hookamatic_subscribers`/`hookamatic_event_types` after the fact), when you'd need it instead of just re-running migrations, and that it's safe to run more than once.

## See also

- [Subscribers & Event Types](02-subscribers.md)
- [Retries & Backoff](04-retries-and-backoff.md)
- [Configuration](09-configuration.md)
