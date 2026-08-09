# Subscribers & Event Types

> One paragraph: a `Subscriber` is a URL that wants webhooks; an `EventType` is a named thing that can happen; the `hookamatic_subscriber_events` pivot is what actually connects them (and carries its own priority/protocol data).

## The Subscriber model

> Short sentence + table: the fields that matter (`url`, `headers`, `signing_secret`, `api_key_reference`, `rate_limit_max`/`rate_limit_interval_seconds`, `expires_at`) and which ones are auto-generated for you (`slug`, `signing_secret`).

## Event types

> Short sentence: what an `EventType` represents, that it's soft-deletable, and where `response_protocol_reference` fits in (a pointer into `config('hookamatic.outbound.response_protocols')`, not a raw class name).

## Subscribing to event types

> Short sentence + code block: `SubscriberService::syncEventTypes()`, why it goes through `Event::updateOrCreate()` instead of the pivot's `attach()/sync()` (so `created_by` still gets set), and what a `priority`/`response_protocol_reference` pairing means.

## Disabling & enabling a subscriber

> Short sentence: `SubscriberService::markAsDisabled()`/`markAsEnabled()`, the difference between a user-disabled subscriber and a system-disabled one (`disabled_by_system`), and the `toggle_disabled_*` config options that can do this automatically.

## Managing subscribers over HTTP

> Short sentence: the `hookamatic/subscriber` and `hookamatic/event-type` CRUD routes plus the `hookamatic/list/*` lightweight-dropdown routes, that they're all gated behind a single `manage-hookamatic` Gate (override it in your own app), and that `{subscriber}` accepts either an ID or a slug.

## Multi-organization scoping

> Short sentence: set `hookamatic.organization_model` to turn this on, run `hookamatic:add-organization-scoping` to add the column, and `HasOrganization` handles the rest automatically (see [Configuration](09-configuration.md)).

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Artisan Commands](08-artisan-commands.md)
- [Configuration](09-configuration.md)
