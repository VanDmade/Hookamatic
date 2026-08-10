<?php

namespace VanDmade\Hookamatic\Services;

use Illuminate\Http\Request;
use VanDmade\Hookamatic\Enums\InboundStatus;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\InboundEvent;

class InboundEventService
{

    public function findOrCreateByProviderAndEventId(
        string $provider,
        string $providerEventId
    ): ?InboundEvent {
        return InboundEvent::firstOrCreate([
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
        ], [
            'payload' => [],
            'status' => InboundStatus::PENDING,
        ]);
    }

    public function create(
        string $provider,
        ?string $eventType = null,
        Request $request = null
    ): InboundEvent {
        $inboundEvent = InboundEvent::create([
            'provider' => $provider,
            'event_type' => $eventType,
            'request' => $request,
        ]);
        return $inboundEvent;
    }

}