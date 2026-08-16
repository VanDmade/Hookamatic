<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Events\WebhookDelivered;
use VanDmade\Hookamatic\Events\WebhookDeliveryExhausted;
use VanDmade\Hookamatic\Events\WebhookDeliveryFailed;
use VanDmade\Hookamatic\Jobs\DeliverWebhookJob;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\OutboundEvent;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Services\DeliveryService;
use VanDmade\Hookamatic\Tests\TestCase;
use InvalidArgumentException;

class DeliverWebhookJobTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.stores.array', ['driver' => 'array']);
        config()->set('cache.default', 'array');
    }

    private function makeSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'url' => 'https://example.test/webhooks',
        ], $overrides));
    }

    private function makeDelivery(Subscriber $subscriber, array $overrides = []): Delivery
    {
        $eventType = EventType::create(['name' => 'order.shipped.'.uniqid()]);
        // Acts like an outbound event was dispatched
        $outboundEvent = OutboundEvent::create([
            'event_type_id' => $eventType->id,
            'payload' => ['order_id' => 1],
        ]);
        return app(DeliveryService::class)->create(
            $subscriber->id,
            $outboundEvent->id,
            ['order_id' => 1],
            ...$overrides
        );
    }

    public function test_sends_a_pending_delivery_and_marks_it_sent(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $subscriber = $this->makeSubscriber();
        $delivery = $this->makeDelivery($subscriber);
        // Runs the job to simulate a delivery
        DeliverWebhookJob::dispatch();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::SENT, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame(200, $delivery->response_status_code);
    }

    public function test_creates_a_new_pending_attempt_on_failure(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        $delivery = $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::FAILED, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        // Gets the total attempts based on the UUID to ensure a new attempt was created
        $attempts = Delivery::where('delivery_uuid', $delivery->delivery_uuid)->orderBy('attempt_number')->get();
        $this->assertCount(2, $attempts);
        // The second attempt should be set to attempt #2, status of pending, and the time in which it should be ran next.
        $this->assertSame(2, $attempts->last()->attempt_number);
        $this->assertSame(DeliveryStatus::PENDING, $attempts->last()->status);
        $this->assertNotNull($attempts->last()->next_attempt_at);
    }

    public function test_exhausts_after_reaching_max_delivery_attempts(): void
    {
        config()->set('hookamatic.max_delivery_attempts', 3);
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        // Simulates a 3rd attempt of a delivery
        $delivery = $this->makeDelivery($subscriber, ['attemptNumber' => 3]);
        DeliverWebhookJob::dispatch();
        $delivery->refresh();
        // The delivery should be marked as exhausted and NO new attempts should be created
        $this->assertSame(DeliveryStatus::EXHAUSTED, $delivery->status);
        $this->assertSame(1, Delivery::where('delivery_uuid', $delivery->delivery_uuid)->count());
    }

    public function test_disables_the_subscriber_on_exhaustion_when_configured(): void
    {
        config()->set('hookamatic.max_delivery_attempts', 1);
        config()->set('hookamatic.toggle_disabled_after_exhausted_deliveries', 1);
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        $subscriber->refresh();
        $this->assertNotNull($subscriber->disabled_at);
        $this->assertTrue($subscriber->disabled_by_system);
    }

    public function test_does_not_disable_before_reaching_the_exhausted_delivery_threshold(): void
    {
        // Regression test: the threshold count must include the delivery currently being
        // exhausted (not yet saved at the point the count runs), or this would require one
        // extra exhaustion beyond the configured threshold to actually trigger.
        config()->set('hookamatic.max_delivery_attempts', 1);
        config()->set('hookamatic.toggle_disabled_after_exhausted_deliveries', 2);
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        $subscriber->refresh();
        $this->assertNull($subscriber->disabled_at);
    }

    public function test_fires_webhook_delivered_on_success(): void
    {
        Event::fake([WebhookDelivered::class]);
        Http::fake(['*' => Http::response('ok', 200)]);
        $subscriber = $this->makeSubscriber();
        $delivery = $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        Event::assertDispatched(
            WebhookDelivered::class,
            fn($event) => $event->delivery->id === $delivery->id
        );
    }

    public function test_fires_webhook_delivery_failed_on_failure(): void
    {
        Event::fake([WebhookDeliveryFailed::class]);
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        $delivery = $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        Event::assertDispatched(
            WebhookDeliveryFailed::class,
            fn($event) => $event->delivery->id === $delivery->id && !is_null($event->retryDelay)
        );
    }

    public function test_fires_webhook_delivery_exhausted_at_max_attempts(): void
    {
        config()->set('hookamatic.max_delivery_attempts', 1);
        Event::fake([WebhookDeliveryExhausted::class]);
        Http::fake(['*' => Http::response('error', 500)]);
        $subscriber = $this->makeSubscriber();
        $delivery = $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        Event::assertDispatched(
            WebhookDeliveryExhausted::class,
            fn($event) => $event->delivery->id === $delivery->id
        );
    }

    public function test_skips_a_delivery_that_is_over_the_rate_limit(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $subscriber = $this->makeSubscriber(['rate_limit_max' => 0, 'rate_limit_interval_seconds' => 60]);
        $delivery = $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::PENDING, $delivery->status);
        Http::assertNothingSent();
    }

    public function test_signs_the_outgoing_request(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $subscriber = $this->makeSubscriber();
        $this->makeDelivery($subscriber);
        DeliverWebhookJob::dispatch();
        Http::assertSent(fn($request) => $request->hasHeader('X-Hookamatic-Signature')
            && $request->hasHeader('X-Hookamatic-Delivery-ID'));
    }

    public function test_only_sends_deliveries_for_the_given_subscribers(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $subscriberOne = $this->makeSubscriber();
        $subscriberTwo = $this->makeSubscriber();
        $deliveryOne = $this->makeDelivery($subscriberOne);
        $deliveryTwo = $this->makeDelivery($subscriberTwo);
        DeliverWebhookJob::dispatch([$subscriberOne->id]);
        $deliveryOne->refresh();
        $deliveryTwo->refresh();
        $this->assertSame(DeliveryStatus::SENT, $deliveryOne->status);
        $this->assertSame(DeliveryStatus::PENDING, $deliveryTwo->status);
    }

    public function test_handle_logs_and_still_rethrows_when_something_fails(): void
    {
        Event::fake([HookamaticLog::class]);
        $thrown = null;
        try {
            DeliverWebhookJob::dispatch('a-subscriber-that-does-not-exist');
        } catch (InvalidArgumentException $exception) {
            $thrown = $exception;
        }
        $this->assertNotNull($thrown, 'Expected the exception to propagate out of the job.');
        Event::assertDispatched(HookamaticLog::class, fn(HookamaticLog $event) => $event->type === 'error');
    }

}
