<?php

use VanDmade\Hookamatic\Outbound\Signing\HmacSigner;
use VanDmade\Hookamatic\Outbound\Retry\BackoffRetryPolicy;
use VanDmade\Hookamatic\Inbound\Providers\StripeVerifier;

return [
    'max_delivery_attempts' => 3,
    // Disables a subscriber after this many exhausted deliveries in a day. Set to 1 to
    // disable on the very first exhaustion. Null disables this check.
    'toggle_disabled_after_exhausted_deliveries' => null,
    // Null disables organization/tenant scoping.
    'organization_model' => null,
    'fail_loud_on_no_subscribers' => false,
    // Bypasses Hookamatic on a provider-less route instead of returning 400.
    'allow_without_provider' => false,

    'retry' => [
        'delay' => 5,
        // Doubles the delay per attempt (delay * 2^(attempt - 1)).
        'exponential_delay' => true,
    ],
    'signing_algorithm' => 'sha256',
    'outbound' => [
        // Implements SignerInterface.
        'signer' => HmacSigner::class,
        // Implements RetryPolicyInterface.
        'retry_policy' => BackoffRetryPolicy::class,
        // Fallback when a subscriber doesn't set its own.
        'rate_limiter' => [
            'max_sends' => 60,
            'interval_seconds' => 60,
        ],
        'max_lock_seconds' => 300,
        'job_timeout' => 900,
        // Seconds before a waiting delivery's effective priority bumps up a level. Null disables aging.
        'priority_aging_seconds' => 300,
        // Maps api_key_reference to the real credential.
        'api_keys' => [],
        // Maps response_protocol_reference to a response handler.
        'response_protocols' => [],
    ],
    'inbound' => [
        'enabled' => true,
        // Counts only verified attempts - forged/failed-signature requests don't count.
        'max_attempts' => 10,
        // Each secret is a real credential - source it via env(), like stripe does.
        'verifiers' => [
            'stripe' => [
                'class' => StripeVerifier::class,
                'secret' => env('HOOKAMATIC_STRIPE_WEBHOOK_SECRET'),
                'tolerance' => 300,
                'enabled' => true,
            ],
        ],
    ],
    'subscriber' => [
        'default_sort_column' => 'created_at',
        'default_sort_order' => 'asc',
    ],
    'event_type' => [
        'default_sort_column' => 'created_at',
        'default_sort_order' => 'asc',
    ],
];
