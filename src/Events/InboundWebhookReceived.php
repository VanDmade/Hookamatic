<?php

namespace VanDmade\Hookamatic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Hookamatic\Models\InboundEvent;

class InboundWebhookReceived
{

    use Dispatchable;

    public function __construct(
        public readonly string $provider,
        public readonly InboundEvent $inboundEvent
    ) {}

}
