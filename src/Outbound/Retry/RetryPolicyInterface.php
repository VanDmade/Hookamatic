<?php

namespace VanDmade\Hookamatic\Outbound\Retry;

interface RetryPolicyInterface
{

    /**
     * Number of seconds to wait before the next attempt is allowed to run.
     * A null return means the next attempt is allowed to run immediately.
     * 
     * @param int $attemptNumber The number of the current attempt.
     * @return int|null The number of seconds to wait before the next attempt is allowed to run, or null if the next attempt is allowed to run immediately.
     */
    public function nextAttemptDelay(int $attemptNumber): ?int;

}
