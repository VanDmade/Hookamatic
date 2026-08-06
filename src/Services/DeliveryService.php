<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use Illuminate\Support\Str;

class DeliveryService
{

    public function getPendingDeliveries($ignoreNextAttemptAt = false): Collection
    {
        return Delivery::where('status', DeliveryStatus::PENDING)
            ->where(function($query) use ($ignoreNextAttemptAt) {
                if ($ignoreNextAttemptAt) {
                    return;
                }
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->get();
    }

    public function getSentDeliveries(): Collection
    {
        return Delivery::where('status', DeliveryStatus::SENT)->get();
    }

    public function getFailedDeliveries(): Collection
    {
        return Delivery::where('status', DeliveryStatus::FAILED)->get();
    }

    public function create(
        int $subscriberId,
        int $outboundEventId,
        array $payload,
        DeliveryStatus $status = DeliveryStatus::PENDING,
        int $attemptNumber = 1,
        ?string $uuid = null,
        ?Carbon $nextAttemptAt = null
    ): Delivery {
        if (is_null($uuid)) {
            $uuid = (string) Str::uuid();
        }
        if ($attemptNumber < 1) {
            $attemptNumber = 1;
        }
        return Delivery::create([
            'subscriber_id' => $subscriberId,
            'outbound_event_id' => $outboundEventId,
            'delivery_uuid' => $uuid,
            'request_payload' => $payload,
            'status' => $status,
            'attempt_number' => $attemptNumber,
            'next_attempt_at' => $nextAttemptAt,
        ]);
    }

    public function createAttempt(
        Delivery $delivery,
        ?int $nextAttemptDelay = null
    ): Delivery {
        return $this->create(
            subscriberId: $delivery->subscriber_id,
            outboundEventId: $delivery->outbound_event_id,
            payload: $delivery->request_payload,
            uuid: $delivery->delivery_uuid,
            attemptNumber: $delivery->attempt_number + 1,
            nextAttemptAt: $nextAttemptDelay ? now()->addSeconds($nextAttemptDelay) : null
        );
    }

}