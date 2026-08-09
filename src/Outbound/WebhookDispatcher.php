<?php

namespace VanDmade\Hookamatic\Outbound;

use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Event as SubscriberEvent;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Services\DeliveryService;
use VanDmade\Hookamatic\Services\EventTypeService;
use VanDmade\Hookamatic\Services\OutboundEventService;
use Exception;

class WebhookDispatcher
{

    public function __construct(
        private EventTypeService $eventTypeService,
        private DeliveryService $deliveryService,
        private OutboundEventService $outboundEventService
    ) { }

    public function dispatch(string $event, array $payload): bool
    {
        $count = 0;
        $eventType = $this->eventTypeService->getByName($event);
        if (empty($eventType)) {
            throw new Exception('Event type not found: '.$event);
        }
        $subscribers = $this->eventTypeService->subscribers($eventType);
        if ($subscribers->isEmpty()) {
            if (config('hookamatic.fail_loud_on_no_subscribers')) {
                throw new Exception('No subscribers found for event type: '.$event);
            }
            return false;
        }
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $trace = [
            'file' => $backtrace[0]['file'] ?? null,
            'line' => $backtrace[0]['line'] ?? null,
            'class' => $backtrace[1]['class'] ?? null,
            'function' => $backtrace[1]['function'] ?? null,
        ];
        $outboundEvent = $this->outboundEventService->create(
            $eventType->id,
            $payload,
            $trace
        );
        $priorities = SubscriberEvent::where('event_type_id', $eventType->id)
            ->pluck('priority', 'subscriber_id');
        foreach ($subscribers as $subscriber) {
            $priority = isset($priorities[$subscriber->id])
                ? Priority::from($priorities[$subscriber->id]) : Priority::NORMAL;
            $delivery = $this->deliveryService->create(
                $subscriber->id,
                $outboundEvent->id,
                $payload,
                // Disabled subscribers still get a Delivery row (audit trail + a
                // ready-to-run queue for whenever they're re-enabled), it's just
                // created paused instead of pending so nothing actually attempts it.
                !is_null($subscriber->disabled_at) ?
                    DeliveryStatus::PAUSED : DeliveryStatus::PENDING,
                priority: $priority
            );
            // Total deliveries created for this event
            $count++;
        }
        return true;
    }

}
