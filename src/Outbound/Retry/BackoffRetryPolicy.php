<?php

namespace VanDmade\Hookamatic\Outbound\Retry;

class BackoffRetryPolicy implements RetryPolicyInterface
{

    public function nextAttemptDelay(int $attemptNumber): ?int
    {
        if ($attemptNumber <= 0) {
            return null;
        }
        $delay = config('hookamatic.retry.delay', 5);
        $exponentialDelay = config('hookamatic.retry.exponential_delay', true);
        if (!$exponentialDelay) {
            return $delay;
        }
        // The delay is calculated using the formula: delay * (2 ^ (attemptNumber - 1))
        return (int) pow(2, $attemptNumber - 1) * $delay;
    }

}
