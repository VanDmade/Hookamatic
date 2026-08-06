<?php

namespace VanDmade\Hookamatic\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Hookamatic\Models\Delivery;

class WebhookDeliveryExhausted
{

    use Dispatchable;

    public function __construct(
        public readonly Delivery $delivery,
        public readonly ?bool $subscriberDisabled = false
    ) {}

}
