# Subscribers & Event Types

A `Subscriber` is a URL that wants webhooks. An `EventType` is a named thing that can happen (`order.shipped`, `invoice.paid`, etc). `hookamatic_subscriber_events` is the join table that actually connects the two - and it's more than a plain pivot, since each pairing carries its own `priority` and `response_protocol_reference`.

## The Subscriber model

| Field | Notes |
|---|---|
| `url` | Where deliveries get POSTed. Required. |
| `headers` | Extra headers merged into every request sent to this subscriber. |
| `signing_secret` | Auto-generated (64-char random string) on creation if not set. Encrypted at rest, never exposed in API responses. |
| `slug` | Auto-generated from `name` if not set. Lets you reference a subscriber by a readable identifier instead of its ID. |
| `api_key_reference` | A key into `config('hookamatic.outbound.api_keys')`, not the key itself. Since that's not secure... |
| `rate_limit_max` / `rate_limit_interval_seconds` | Per-subscriber override of the global rate limit - see [Outbound Dispatching](01-outbound-dispatching.md#rate-limiting). |
| `expires_at` | Defaults to one year out if not set. |
| `disabled_at` / `disabled_reason` / `disabled_by` / `disabled_by_system` | See below. |

## Event types

An `EventType` is just a `name` + `description`. A way to say "call this handler with the subscriber's response" without storing a raw class name in the database.

## Subscribing to event types

```php
use VanDmade\Hookamatic\Services\SubscriberService;

app(SubscriberService::class)->syncEventTypes($subscriber, [
    ['event_type_id' => 1, 'priority' => 'HIGH'],
    ['event_type_id' => 2], // Priority defaults to NORMAL
]);
```

`syncEventTypes()` replaces the subscriber's entire set of event-type subscriptions with whatever you pass in - anything not in the list gets removed. It goes through `Event::updateOrCreate()` rather than the `eventTypes()` relation's `attach()`/`sync()`, because `attach()`/`sync()` write the pivot table directly and skip Eloquent model events entirely - which would silently skip the `Event` model's `creating` hook that sets `created_by`.

## Disabling & enabling a subscriber

```php
$subscriberService->markAsDisabled($subscriber, 'Too many failed deliveries.');
$subscriberService->markAsEnabled($subscriber);
```

A subscriber disabled by a person has `disabled_by` set to that user's ID. A subscriber disabled automatically (no authenticated user in context) gets `disabled_by_system` = true instead - that's the flag to check if you want to distinguish "we turned this off" from "a human turned this off". `hookamatic.toggle_disabled_after_exhausted_deliveries` in config can trigger this automatically - see [Retries & Backoff](04-retries-and-backoff.md).

## Managing subscribers & event types over HTTP

Both resources get a small CRUD API, all under a single `manage-hookamatic` Gate (default: any authenticated user - override it in your own app for anything more specific):

| Method | URI | Action |
|---|---|---|
| `GET` | `hookamatic/list/subscribers` | Lightweight `{value, label}` pairs, for populating a dropdown |
| `GET` | `hookamatic/subscriber/data` | Paginated, searchable, sortable listing |
| `POST` | `hookamatic/subscriber` | Create |
| `GET` | `hookamatic/subscriber/{subscriber}` | Show (accepts an ID **or** a slug) |
| `PUT` | `hookamatic/subscriber/{subscriber}` | Update |
| `DELETE` | `hookamatic/subscriber/{subscriber}` | Delete |

`hookamatic/event-type` mirrors this exactly (`hookamatic/list/event-types`, `hookamatic/event-type/data`, etc), except `{eventType}` only ever accepts an ID.

`store`/`update` on the subscriber endpoint accept an `event_types` array in the same shape `syncEventTypes()` expects, so you can create/update a subscriber and set its event-type subscriptions in one request. This CRUD layer is intentionally bare-minimum - if you need more than create/edit/delete/get/list, build your own controller on top of `SubscriberService`/`EventTypeService`.

## Multi-organization scoping

Set `hookamatic.organization_model` to your tenant/organization model to turn this on, then run `php artisan hookamatic:add-organization-scoping` to add the `organization_id` column to `hookamatic_subscribers` and `hookamatic_event_types` (safe to run more than once). Once both are in place, the `HasOrganization` trait - already applied to both models - automatically:

- Exposes an `organization()` relation
- Registers a global scope that filters every query by `auth()->user()->organization_id`, but only when `organization_model` is actually configured - so this is entirely inert until you opt in.

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Artisan Commands](08-artisan-commands.md)
- [Configuration](09-configuration.md)
