<?php

namespace VanDmade\Hookamatic;

use VanDmade\Hookamatic\Outbound\WebhookDispatcher;

class Hookamatic
{

    public function __construct(
        private WebhookDispatcher $dispatcher
    ) {}

    public function dispatch(string $event, array $payload): bool
    {
        return $this->dispatcher->dispatch($event, $payload);
    }

}
