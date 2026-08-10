<?php

namespace VanDmade\Hookamatic\Tests\Unit;

use VanDmade\Hookamatic\Outbound\Retry\BackoffRetryPolicy;
use VanDmade\Hookamatic\Tests\TestCase;

class BackoffRetryPolicyTest extends TestCase
{

    public function test_returns_null_for_a_non_positive_attempt_number(): void
    {
        $policy = new BackoffRetryPolicy();
        $this->assertNull($policy->nextAttemptDelay(0));
        $this->assertNull($policy->nextAttemptDelay(-1));
    }

    public function test_delay_doubles_per_attempt_when_exponential(): void
    {
        config()->set('hookamatic.retry.delay', 5);
        config()->set('hookamatic.retry.exponential_delay', true);
        $policy = new BackoffRetryPolicy();
        $this->assertSame(5, $policy->nextAttemptDelay(1));
        $this->assertSame(10, $policy->nextAttemptDelay(2));
        $this->assertSame(20, $policy->nextAttemptDelay(3));
        $this->assertSame(40, $policy->nextAttemptDelay(4));
    }

    public function test_delay_is_flat_when_exponential_is_disabled(): void
    {
        config()->set('hookamatic.retry.delay', 5);
        config()->set('hookamatic.retry.exponential_delay', false);
        $policy = new BackoffRetryPolicy();
        $this->assertSame(5, $policy->nextAttemptDelay(1));
        $this->assertSame(5, $policy->nextAttemptDelay(2));
        $this->assertSame(5, $policy->nextAttemptDelay(10));
    }

}
