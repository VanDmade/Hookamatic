<?php

namespace VanDmade\Hookamatic\Outbound;

use Illuminate\Contracts\Cache\Repository;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;

class WebhookRateLimiter
{

    public function __construct(
        private Repository $cache
    ) {}

    public function tooManyAttempts(Subscriber $subscriber): bool
    {
        $max = $this->getMaxSends($subscriber);
        if (is_null($max)) {
            return false;
        }
        $key = $this->key($subscriber);
        $interval = $this->getIntervalSeconds($subscriber);
        $cache = $this->clean($key, $interval);
        return count($cache) >= $max;
    }

    public function hit(Subscriber $subscriber): void
    {
        $max = $this->getMaxSends($subscriber);
        if (is_null($max)) {
            return;
        }
        $key = $this->key($subscriber);
        $interval = $this->getIntervalSeconds($subscriber);
        $cache = $this->clean($key, $interval);
        // Adds a new record to the cache with when the amount will expire
        $cache[] = now()->addSeconds($interval);
        $this->cache->put($key, $cache, $interval);
    }

    private function clean(string $key, ?int $ttlSeconds): array
    {
        // Grabs the cache and removes any items that have expired.
        $cache = $this->cache->get($key, []);
        // This allows for a moving window of time so that at no point a subscriber can exceed the max amount
        $cache = array_filter($cache, fn($item) => now() < $item);
        $this->cache->put($key, $cache, $ttlSeconds);
        return $cache;
    }

    private function key(Subscriber $subscriber): string
    {
        return 'hookamatic-subscriber-rate-limit:'.$subscriber->id;
    }

    private function getMaxSends(Subscriber $subscriber): ?int
    {
        return $subscriber->rate_limit_max ?? config('hookamatic.outbound.rate_limiter.max_sends');
    }

    private function getIntervalSeconds(Subscriber $subscriber): ?int
    {
        return $subscriber->rate_limit_interval_seconds ?? config('hookamatic.outbound.rate_limiter.interval_seconds', 60);
    }

}
