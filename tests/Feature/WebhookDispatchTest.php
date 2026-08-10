<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Exception;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Facades\Hookamatic;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Event;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Tests\TestCase;

class WebhookDispatchTest extends TestCase
{

    private function makeSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'url' => 'https://example.test/webhooks',
        ], $overrides));
    }

    private function subscribe(Subscriber $subscriber, EventType $eventType, ?Priority $priority = null): Event
    {
        return Event::create([
            'subscriber_id' => $subscriber->id,
            'event_type_id' => $eventType->id,
            'priority' => $priority ?? Priority::NORMAL,
        ]);
    }

    public function test_dispatch_creates_a_delivery_for_each_subscribed_subscriber(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $subscriberOne = $this->makeSubscriber();
        $subscriberTwo = $this->makeSubscriber();
        $this->subscribe($subscriberOne, $eventType);
        $this->subscribe($subscriberTwo, $eventType);
        $result = Hookamatic::dispatch('order.shipped', ['order_id' => 1]);
        $this->assertTrue($result);
        $this->assertSame(2, Delivery::count());
        $this->assertSame(
            [$subscriberOne->id, $subscriberTwo->id],
            Delivery::orderBy('id')->pluck('subscriber_id')->all()
        );
    }

    public function test_dispatch_returns_false_when_the_event_type_has_no_subscribers(): void
    {
        EventType::create(['name' => 'order.shipped']);
        $result = Hookamatic::dispatch('order.shipped', ['order_id' => 1]);
        $this->assertFalse($result);
        $this->assertSame(0, Delivery::count());
    }

    public function test_dispatch_throws_when_configured_to_fail_loud_with_no_subscribers(): void
    {
        config()->set('hookamatic.fail_loud_on_no_subscribers', true);
        EventType::create(['name' => 'order.shipped']);
        $this->expectException(Exception::class);
        Hookamatic::dispatch('order.shipped', ['order_id' => 1]);
    }

    public function test_dispatch_throws_when_the_event_type_does_not_exist(): void
    {
        $this->expectException(Exception::class);
        Hookamatic::dispatch('no.such.event', [
            'order_id' => 1,
        ]);
    }

    public function test_dispatch_creates_a_paused_delivery_for_a_disabled_subscriber(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $subscriber = $this->makeSubscriber([
            'disabled_at' => now(),
            'disabled_reason' => 'testing',
        ]);
        $this->subscribe($subscriber, $eventType);
        Hookamatic::dispatch('order.shipped', [
            'order_id' => 1,
        ]);
        $delivery = Delivery::first();
        $this->assertSame(DeliveryStatus::PAUSED, $delivery->status);
    }

    public function test_dispatch_creates_a_pending_delivery_for_an_enabled_subscriber(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $subscriber = $this->makeSubscriber();
        $this->subscribe($subscriber, $eventType);
        Hookamatic::dispatch('order.shipped', [
            'order_id' => 1,
        ]);
        $delivery = Delivery::first();
        $this->assertSame(DeliveryStatus::PENDING, $delivery->status);
    }

    public function test_dispatch_captures_the_subscription_priority_on_the_delivery(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $subscriber = $this->makeSubscriber();
        $this->subscribe($subscriber, $eventType, Priority::HIGH);
        Hookamatic::dispatch('order.shipped', [
            'order_id' => 1,
        ]);
        $delivery = Delivery::first();
        $this->assertSame(Priority::HIGH, $delivery->priority);
    }

    public function test_dispatch_stores_the_given_payload_on_the_delivery(): void
    {
        $eventType = EventType::create(['name' => 'order.shipped']);
        $subscriber = $this->makeSubscriber();
        $this->subscribe($subscriber, $eventType);
        Hookamatic::dispatch('order.shipped', [
            'order_id' => 42,
            'tracking_number' => 'ABC123',
        ]);
        $delivery = Delivery::first();
        $this->assertSame([
            'order_id' => 42,
            'tracking_number' => 'ABC123',
        ], $delivery->request_payload);
    }

}
