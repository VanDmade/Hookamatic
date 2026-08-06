<?php

namespace VanDmade\Hookamatic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Symfony\Component\HttpFoundation\Response;
use VanDmade\Hookamatic\Models\InboundEvent;

class InboundWebhookMaxAttempts
{

    use Dispatchable;

    public function __construct(
        public readonly string $provider,
        public readonly InboundEvent $inboundEvent,
        public readonly Response $response
    ) {}

}
