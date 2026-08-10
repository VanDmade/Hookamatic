# Signing

Every outbound delivery is signed with the receiving subscriber's own `signing_secret`, so they can verify a request actually came from your app and not an impersonator.

## The signature format

`HmacSigner` sends `X-Hookamatic-Signature: t={timestamp},v1={hmac}` - deliberately shaped like Stripe's signature header. The HMAC is computed over `{timestamp}.{payload}` using `hash_hmac()`, with the exact same JSON-encoded bytes that get sent as the request body (so the subscriber doesn't need to worry about re-serialization producing different bytes than what was actually signed).

`X-Hookamatic-Delivery-ID` is also sent alongside it - the delivery's UUID, stable across retry attempts of the same logical delivery (see [Retries & Backoff](04-retries-and-backoff.md)), useful for a subscriber that wants to deduplicate.

## Verifying it on the subscriber's end

```php
[$t, $v1] = ...; // Parse "t=...,v1=..." into its two parts

$expected = hash_hmac('sha256', $t.'.'.$rawRequestBody, $sharedSecret);

if (!hash_equals($expected, $v1)) {
    abort(403);
}

if (abs(time() - (int) $t) > 300) {
    abort(403); // Reject signatures older than your tolerance window
}
```

Always compare with `hash_equals()`, never `===` as this avoids any comparison / small leakage issues.

## Swapping the signer

```php
use VanDmade\Hookamatic\Outbound\Signing\SignerInterface;

class MySigner implements SignerInterface
{
    public function sign(string|array $payload, string $secret, ?int $timestamp = null): string
    {
        // ...
    }
}
```

Set `hookamatic.outbound.signer` to your class and `WebhookSender` will use it instead of `HmacSigner` - it's resolved through the container, so constructor dependencies work normally.

## Configuration

| Key | Default | What it does |
|---|---|---|
| `signing_algorithm` | `'sha256'` | Passed straight to `hash_hmac()`. Must be one PHP's `hash_algos()` actually supports. |
| `encode_payload` | `true` | When the payload isn't already a string, `json_encode()` it automatically. If `false`, a non-string payload throws instead of getting silently encoded. |

## See also

- [Outbound Dispatching](01-outbound-dispatching.md)
- [Configuration](09-configuration.md)
