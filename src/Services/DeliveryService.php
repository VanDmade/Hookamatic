<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use Illuminate\Support\Str;

class DeliveryService
{

    public function getPendingDeliveries(
        array|string $subscriberIds = [],
        bool $excludeSubscribers = false,
        bool $ignoreNextAttemptAt = false
    ): Collection {
        // Determines if the subscriberIds needs to be normalized to an array
        if (is_string($subscriberIds)) {
            $subscriberIds = explode(',', $subscriberIds);
        }
        [$priorityOrderSql, $priorityOrderBindings] = $this->priorityOrderExpression(
            config('hookamatic.outbound.priority_aging_seconds')
        );
        return Delivery::where('status', DeliveryStatus::PENDING)
            ->with('subscriber')
            ->when(!empty($subscriberIds), fn($query) => $excludeSubscribers
                ? $query->whereNotIn('subscriber_id', $subscriberIds)
                : $query->whereIn('subscriber_id', $subscriberIds))
            ->where(function($query) use ($ignoreNextAttemptAt) {
                if ($ignoreNextAttemptAt) {
                    return;
                }
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderByRaw("({$priorityOrderSql}) desc", $priorityOrderBindings)
            // Within the same effective priority, oldest waiting first.
            ->orderBy('created_at', 'asc')
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
        ?Carbon $nextAttemptAt = null,
        Priority $priority = Priority::NORMAL
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
            'priority' => $priority,
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
            nextAttemptAt: $nextAttemptDelay ? now()->addSeconds($nextAttemptDelay) : null,
            priority: $delivery->priority
        );
    }

    private function priorityOrderExpression(?int $agingSeconds): array
    {
        if (is_null($agingSeconds)) {
            return ['priority', []];
        }
        $lowest = Priority::LOWEST->value;
        $highest = Priority::HIGHEST->value;
        $cases = [];
        $bindings = [];
        for ($priority = $lowest; $priority < $highest; $priority++) {
            for ($boost = $highest - $priority; $boost >= 1; $boost--) {
                $cases[] = 'WHEN priority = ? AND COALESCE(next_attempt_at, created_at) <= ? THEN ?';
                $bindings[] = $priority;
                $bindings[] = now()->subSeconds($agingSeconds * $boost);
                $bindings[] = $priority + $boost;
            }
        }
        return ['CASE '.implode(' ', $cases).' ELSE priority END', $bindings];
    }

}