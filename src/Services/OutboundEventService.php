<?php

namespace VanDmade\Hookamatic\Services;

use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\OutboundEvent;

class OutboundEventService
{

    public function create(
        int $eventTypeId,
        array $payload,
        array $trace = []
    ): OutboundEvent {
        $outboundEvent = OutboundEvent::create([
            'event_type_id' => $eventTypeId,
            'payload' => $payload,
            'trace' => $trace,
        ]);
        return $outboundEvent;
    }

}