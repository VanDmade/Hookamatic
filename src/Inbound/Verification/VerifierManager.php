<?php

namespace VanDmade\Hookamatic\Inbound\Verification;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

class VerifierManager
{

    public function __construct(private Container $app) { }

    public function resolve(string $provider): VerifierInterface
    {
        $class = config('hookamatic.inbound.verifiers.'.$provider.'.class', null);
        if (is_null($class)) {
            throw new RuntimeException('No verifier configured for provider: '.$provider);
        }
        return $this->app->make($class);
    }

}
