<?php

namespace VanDmade\Hookamatic\Tests\Unit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Outbound\WebhookRateLimiter;
use VanDmade\Hookamatic\Tests\TestCase;

class WebhookRateLimiterTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Guarantees an array store exists regardless of Testbench's default cache config.
        config()->set('cache.stores.array', ['driver' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSubscriber(?int $max = null, ?int $interval = null): Subscriber
    {
        return Subscriber::create([
            'url' => 'https://example.test/webhooks',
            'rate_limit_max' => $max,
            'rate_limit_interval_seconds' => $interval,
        ]);
    }

    public function test_allows_sends_under_the_limit(): void
    {
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriber = $this->makeSubscriber(max: 2, interval: 60);
        $this->assertFalse($limiter->tooManyAttempts($subscriber));
        $limiter->hit($subscriber);
        $this->assertFalse($limiter->tooManyAttempts($subscriber));
    }

    public function test_blocks_once_the_max_is_reached(): void
    {
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriber = $this->makeSubscriber(max: 2, interval: 60);
        for ($i = 0; $i < 2; $i++) {
            $limiter->hit($subscriber);
        }
        // Hits the subscriber twice and it'll now be at the limit
        $this->assertTrue($limiter->tooManyAttempts($subscriber));
    }

    public function test_falls_back_to_config_when_the_subscriber_has_no_limit_of_its_own(): void
    {
        config()->set('hookamatic.outbound.rate_limiter.max_sends', 1);
        config()->set('hookamatic.outbound.rate_limiter.interval_seconds', 60);
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriber = $this->makeSubscriber();
        $this->assertFalse($limiter->tooManyAttempts($subscriber));
        $limiter->hit($subscriber);
        // There is only 1 allowed to be sent in 60 second period so it should be at the limit now
        $this->assertTrue($limiter->tooManyAttempts($subscriber));
    }

    public function test_never_blocks_when_no_limit_applies_at_all(): void
    {
        config()->set('hookamatic.outbound.rate_limiter.max_sends', null);
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriber = $this->makeSubscriber();
        for ($i = 0; $i < 5; $i++) {
            $limiter->hit($subscriber);
        }
        // You can hit the subscriber 1000s of times and it will never say no!
        $this->assertFalse($limiter->tooManyAttempts($subscriber));
    }

    public function test_the_window_moves_and_old_hits_expire(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriber = $this->makeSubscriber(max: 1, interval: 60);
        $limiter->hit($subscriber);
        // The limiter should be blocked due to the hit occuring
        $this->assertTrue($limiter->tooManyAttempts($subscriber));
        // After 60 seconds it will be unblocked again
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:01:01'));
        $this->assertFalse($limiter->tooManyAttempts($subscriber));
    }

    public function test_different_subscribers_have_independent_limits(): void
    {
        $limiter = new WebhookRateLimiter(Cache::store('array'));
        $subscriberOne = $this->makeSubscriber(max: 1, interval: 60);
        $subscriberTwo = $this->makeSubscriber(max: 1, interval: 60);
        $limiter->hit($subscriberOne);
        // Subscriber one has hit the limit, but subscriber two has not
        $this->assertTrue($limiter->tooManyAttempts($subscriberOne));
        $this->assertFalse($limiter->tooManyAttempts($subscriberTwo));
    }

}
