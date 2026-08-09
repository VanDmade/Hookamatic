# Signing

> One paragraph: every outbound delivery is signed with the subscriber's own `signing_secret` so they can verify a request actually came from your app.

## The signature format

> Short sentence + example: `HmacSigner` produces `t={timestamp},v1={hmac}` (deliberately Stripe-shaped), which header it's sent in, and what exactly gets hashed (`{timestamp}.{payload}`).

## Verifying it on the subscriber's end

> Short sentence + code snippet: what a subscriber needs to do to check the signature themselves (recompute the HMAC, compare, check timestamp tolerance).

## Swapping the signer

> Short sentence: `SignerInterface`, and setting `hookamatic.outbound.signer` in config to use your own instead of `HmacSigner`.

## Configuration

> Short sentence + table: `signing_algorithm` and `encode_payload` - what each controls and their defaults.

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Configuration](09-configuration.md)
