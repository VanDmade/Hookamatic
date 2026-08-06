<?php

namespace VanDmade\Hookamatic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Events\WebhookDelivered;
use VanDmade\Hookamatic\Events\WebhookDeliveryExhausted;
use VanDmade\Hookamatic\Events\WebhookDeliveryFailed;
use VanDmade\Hookamatic\Outbound\WebhookSender;
use VanDmade\Hookamatic\Outbound\Retry\RetryPolicyInterface;
use VanDmade\Hookamatic\Services\DeliveryService;
use VanDmade\Hookamatic\Services\SubscriberService;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{

    use Queueable;

    public function __construct(
        private WebhookSender $webhookSender,
        private RetryPolicyInterface $retryPolicy,
        private DeliveryService $deliveryService,
        private SubscriberService $subscriberService
    ) {
    }

    public function handle(): void
    {
        $deliveries = $this->deliveryService->getPendingDeliveries();
        foreach ($deliveries as $delivery) {
            $startingTime = microtime(true);
            // Sends the webhook to the subscriber's endpoint
            $response = $this->webhookSender->send($delivery);
            if (!is_null($response)) {
                // Records the status code and body on the delivery whenever an actual
                // HTTP response came back, regardless of whether it was successful.
                $delivery->response = $response;
            }
            $disabled = false;
            $retryDelay = null;
            if (is_null($response) || !$response->successful()) {
                $delivery->failed_at = now();
                // The delivery was not successful, increment the attempt number and check if it has reached the max attempts
                if ($delivery->attempt_number >= config('hookamatic.max_delivery_attempts', 3)) {
                    $delivery->status = DeliveryStatus::EXHAUSTED;
                    // If the delivery has failed, we can mark the subscriber as disabled
                    if (config('hookamatic.toggle_disabled_on_exhausted_delivery')) {
                        $disabled = true;
                        $this->subscriberService->markAsDisabled(
                            $delivery->subscriber_id,
                            'Marked as disabled due to failed delivery after '.$delivery->attempt_number.' attempts.'
                        );
                    } elseif (!is_null($amount = config('hookamatic.toggle_disabled_after_exhausted_deliveries', null))) {
                        // This will allow for a subscriber's delivery to fail, BUT, if they consistently fail it'll disable it
                        $totalFailures = $delivery->subscriber->deliveries()
                            ->where('status', DeliveryStatus::EXHAUSTED)
                            ->where('created_at', '>=', now()->subDay())
                            ->count();
                        if ($totalFailures >= $amount) {
                            $disabled = true;
                            $this->subscriberService->markAsDisabled(
                                $delivery->subscriber,
                                'Multiple deliveries have failed for this subscriber over the past day. '.
                                'Marked as disabled after '.$totalFailures.' failed deliveries.'
                            );
                        }
                    }
                } else {
                    $delivery->status = DeliveryStatus::FAILED;
                    $retryDelay = $this->retryPolicy->nextAttemptDelay($delivery->attempt_number+1);
                    // Creates a new pending delivery request based on the current delivery
                    $this->deliveryService->createAttempt($delivery, $retryDelay);
                }
            } else {
                // The delivery was successful
                $delivery->sent_at = now();
                $delivery->status = DeliveryStatus::SENT;
            }
            // Stores how long the request took to complete in milliseconds
            $delivery->duration_ms = (microtime(true) - $startingTime) * 1000;
            $delivery->save();
            // Dispatched after save() so listeners see the delivery's final, persisted state.
            switch ($delivery->status) {
                case DeliveryStatus::SENT:
                    WebhookDelivered::dispatch($delivery, $delivery->duration_ms);
                    break;
                case DeliveryStatus::FAILED:
                    WebhookDeliveryFailed::dispatch($delivery, $retryDelay);
                    break;
                case DeliveryStatus::EXHAUSTED:
                    WebhookDeliveryExhausted::dispatch($delivery, $disabled);
                    break;
            };
        }
    }

}
