# Inbound Processing

> One paragraph, and a note before writing the rest: there's no dedicated "processing job" - once verification passes, the middleware calls `$next($request)` and *your own* route handler is the processing step. This doc is really about what happens around your handler, not instead of it.

## Your route handler is the processor

> Short sentence: whatever your route/controller does with the verified payload *is* the processing logic - Hookamatic doesn't dictate a shape for it.

## How success/failure is determined

> Short sentence: the middleware checks `$response->isSuccessful()` after your handler returns, and sets the `InboundEvent` to `PROCESSED` or `FAILED` accordingly - so a processing failure just means your handler returned a non-2xx response.

## Retry attempts & giving up

> Short sentence: `attempt_counter` increments per delivery attempt from the provider, and `hookamatic.inbound.max_attempts` caps how many times a provider is allowed to keep retrying before `InboundWebhookMaxAttempts` fires and Hookamatic starts returning 200 anyway (to stop the provider spamming you).

## Reacting to processing outcomes

> Short sentence + table: `InboundWebhookProcessed`/`InboundWebhookVerificationFailed`/`InboundWebhookMaxAttempts` - what each carries and when you'd listen for them.

## See also

- [Inbound Receiving](05-inbound-receiving.md)
- [Inbound Verification](06-inbound-verification.md)
- [Artisan Commands](08-artisan-commands.md)
