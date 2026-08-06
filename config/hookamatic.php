<?php

use VanDmade\Hookamatic\Outbound\Signing\HmacSigner;
use VanDmade\Hookamatic\Outbound\Retry\BackoffRetryPolicy;
use VanDmade\Hookamatic\Inbound\Providers\StripeVerifier;

return [

    // Maximum number of delivery attempts before a delivery is marked as exhausted.
    'max_delivery_attempts' => 3,

    // Disables a subscriber the moment any single delivery becomes exhausted.
    'toggle_disabled_on_exhausted_delivery' => false,

    // Disables a subscriber after this many exhausted deliveries within the past day.
    // Ignored when toggle_disabled_on_exhausted_delivery is true. Null disables this check.
    'toggle_disabled_after_exhausted_deliveries' => null,

    // Fully-qualified model class used for organization/tenant scoping. Null disables it.
    'organization_model' => null,

    // Throws instead of silently returning false when an event type has no subscribers.
    'fail_loud_on_no_subscribers' => false,

    'retry' => [
        // Base delay, in seconds, before a failed delivery is retried.
        'delay' => 5,
        // When true, the delay doubles per attempt (delay * 2^(attempt - 1)).
        'exponential_delay' => true,
    ],

    // Whether HmacSigner JSON-encodes non-string payloads automatically.
    'encode_payload' => true,

    // Algorithm passed to hash_hmac() when signing outbound payloads.
    'signing_algorithm' => 'sha256',

    'outbound' => [
        // Class implementing SignerInterface, used to sign outbound webhook payloads.
        'signer' => HmacSigner::class,
        // Class implementing RetryPolicyInterface, used to schedule retry delays.
        'retry_policy' => BackoffRetryPolicy::class,
    ],

    'inbound' => [
        // Global kill switch - turns off inbound verification/tracking for every provider.
        'enabled' => true,
        'verifiers' => [
            'stripe' => [
                'class' => StripeVerifier::class,
                'secret' => env('HOOKAMATIC_STRIPE_WEBHOOK_SECRET'),
                'tolerance' => 300,
                // Per-provider kill switch - turns off just this provider.
                'enabled' => true,
            ],
        ],
    ],

];
