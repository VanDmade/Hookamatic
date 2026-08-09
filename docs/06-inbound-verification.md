# Inbound Verification

> One paragraph: before your route handler ever runs, the `hookamatic` middleware asks a per-provider `VerifierInterface` to confirm the request is genuinely from that provider (not spoofed).

## Configuring a provider

> Short sentence + code block: the `hookamatic.inbound.verifiers.{provider}` config shape (`class`, `secret`, `tolerance`, `enabled`), using the built-in `stripe` entry as the example.

## The built-in Stripe verifier

> Short sentence: what `StripeVerifier` actually checks (signature + timestamp tolerance), and `ExtractsHeaderValue` as the shared header-parsing helper behind it.

## Writing your own verifier

> Short sentence + code block: implement `VerifierInterface` (`extractEventId()`, `verify()`), register it under a new key in `hookamatic.inbound.verifiers`.

## What happens on failure

> Short sentence: a failed `verify()` marks the `InboundEvent` `FAILED`, fires `InboundWebhookVerificationFailed`, and returns a 403 - your route handler never runs.

## Per-provider kill switch

> Short sentence: `hookamatic.inbound.verifiers.{provider}.enabled` vs the global `hookamatic.inbound.enabled`.

## See also

- [Inbound Receiving](05-inbound-receiving.md)
- [Inbound Processing](07-inbound-processing.md)
- [Configuration](09-configuration.md)
