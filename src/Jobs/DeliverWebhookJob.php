<?php

namespace VanDmade\Hookamatic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Events\WebhookDelivered;
use VanDmade\Hookamatic\Events\WebhookDeliveryExhausted;
use VanDmade\Hookamatic\Events\WebhookDeliveryFailed;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Outbound\WebhookRateLimiter;
use VanDmade\Hookamatic\Outbound\WebhookSender;
use VanDmade\Hookamatic\Outbound\Retry\RetryPolicyInterface;
use VanDmade\Hookamatic\Services\DeliveryService;
use VanDmade\Hookamatic\Services\SubscriberService;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{

    use Dispatchable, Queueable;

    /**
     * WARNING:
     *   If you forget to add `->withoutOverlapping()` to your schedule, or if you dispatch
     *   multiple jobs with overlapping subscriber sets, you MAY see duplicate deliveries.
     */
    public int $timeout;
    private ?Delivery $currentDelivery = null;

    public function __construct(
        private int|string|array $subscribers = [],
        private bool $exclude = false
    ) {
        $this->timeout = config('hookamatic.outbound.job_timeout', 900);
    }

    public function handle(
        WebhookRateLimiter $webhookRateLimiter,
        WebhookSender $webhookSender,
        RetryPolicyInterface $retryPolicy,
        DeliveryService $deliveryService,
        SubscriberService $subscriberService
    ): void {
        // Normalizes a comma-separated string the same way getPendingDeliveries() does.
        $subscribers = is_string($this->subscribers) ? explode(',', $this->subscribers) : $this->subscribers;
        $subscriberIds = array_map(
            fn($identifier) => $subscriberService->findByIdOrSlug($identifier)->id,
            $subscribers
        );
        $maxLockSeconds = config('hookamatic.outbound.max_lock_seconds', 300);
        // Removes anything that is already locked to prevent multiple jobs from sending the same delivery
        $deliveries = $deliveryService->getPendingDeliveries(
            subscriberIds: $subscriberIds,
            excludeSubscribers: $this->exclude
        )->reject(fn($delivery) => $this->isLocked($delivery));
        foreach ($deliveries as $delivery) {
            // Checks the attempts each time, just incase a job runs long enough to potentially clean up the limiter
            if ($webhookRateLimiter->tooManyAttempts($delivery->subscriber)) {
                continue;
            }
            // Locks the delivery so that if there are more than one job it'll prevent sending it twice.
            $this->currentDelivery = $delivery;
            cache()->lock($this->lockKey($delivery), $maxLockSeconds)->get(function() use (
                $delivery,
                $webhookRateLimiter,
                $webhookSender,
                $retryPolicy,
                $deliveryService,
                $subscriberService
            ) {
                $startingTime = microtime(true);
                // Sends the webhook to the subscriber's endpoint
                $response = $webhookSender->send($delivery);
                if (!is_null($response)) {
                    $delivery->response = $response;
                    $webhookRateLimiter->hit($delivery->subscriber);
                }
                $disabled = false;
                $retryDelay = null;
                if (is_null($response) || !$response->successful()) {
                    $delivery->failed_at = now();
                    // The delivery was not successful, increment the attempt number and check if it has reached the max attempts
                    if ($delivery->attempt_number >= config('hookamatic.max_delivery_attempts', 3)) {
                        $delivery->status = DeliveryStatus::EXHAUSTED;
                        // If the delivery has failed, we can mark the subscriber as disabled
                        if (!is_null($amount = config('hookamatic.toggle_disabled_after_exhausted_deliveries', null))) {
                            // This will allow for a subscriber's delivery to fail, BUT, if they consistently fail it'll disable it.
                            // +1 accounts for this delivery itself - its EXHAUSTED status is only set in memory
                            // so far, not yet saved, so the query below can't see it.
                            $totalFailures = $delivery->subscriber->deliveries()
                                ->where('status', DeliveryStatus::EXHAUSTED)
                                ->where('created_at', '>=', now()->subDay())
                                ->count() + 1;
                            if ($totalFailures >= $amount) {
                                $disabled = true;
                                $subscriberService->markAsDisabled(
                                    $delivery->subscriber,
                                    'Multiple deliveries have failed for this subscriber over the past day. '.
                                    'Marked as disabled after '.$totalFailures.' failed deliveries.'
                                );
                            }
                        }
                    } else {
                        $delivery->status = DeliveryStatus::FAILED;
                        $retryDelay = $retryPolicy->nextAttemptDelay($delivery->attempt_number+1);
                        // Creates a new pending delivery request based on the current delivery
                        $deliveryService->createAttempt($delivery, $retryDelay);
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
            });
            $this->currentDelivery = null;
        }
    }

    public function failed(Throwable $exception): void
    {
        // Tries it's best to clean it up if something crazy happens
        if (!is_null($this->currentDelivery)) {
            cache()->lock($this->lockKey($this->currentDelivery))->forceRelease();
        }
    }

    private function lockKey(Delivery $delivery): string
    {
        return 'hookamatic-delivery-lock:'.$delivery->id;
    }

    private function isLocked(Delivery $delivery): bool
    {
        return cache()->has($this->lockKey($delivery));
    }

}
