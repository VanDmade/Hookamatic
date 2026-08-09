<?php

namespace VanDmade\Hookamatic\Events;

use Illuminate\Foundation\Events\Dispatchable;

class HookamaticLog
{

    use Dispatchable;

    public function __construct(
        public string $type,
        public string $message,
        public array $context = []
    ) {}

}
