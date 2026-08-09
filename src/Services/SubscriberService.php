<?php

namespace VanDmade\Hookamatic\Services;

use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use InvalidArgumentException;

class SubscriberService
{

    public function get($id): ?Subscriber
    {
        return Subscriber::find($id);
    }

    public function findByIdOrSlug(int|string $identifier): Subscriber
    {
        $subscriber = is_numeric($identifier)
            ? $this->get($identifier) : Subscriber::where('slug', $identifier)->first();
        if (is_null($subscriber)) {
            throw new InvalidArgumentException('No subscriber found for identifier: '.$identifier);
        }
        return $subscriber;
    }

    public function markAsDisabled(int|Subscriber $subscriber, string $reason): void
    {
        // Allows for the subscriber to be passed in as either an integer ID or a Subscriber instance
        if (is_int($subscriber)) {
            $subscriber = $this->get($subscriber);
        }
        if ($subscriber) {
            $subscriber->disabled_at = now();
            $subscriber->disabled_reason = $reason;
            if (auth()->check()) {
                $subscriber->disabled_by = auth()->id();
            } else {
                $subscriber->disabled_by_system = true;
            }
            $subscriber->save();
        }
    }

}