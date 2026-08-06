<?php

namespace VanDmade\Hookamatic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Hookamatic\Models\Delivery;

class WebhookDelivered
{

    use Dispatchable;

    public function __construct(
        public readonly Delivery $delivery,
        public readonly float $durationMs
    ) {}

}
