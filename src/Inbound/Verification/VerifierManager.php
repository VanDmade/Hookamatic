<?php

namespace VanDmade\Hookamatic\Inbound\Verification;

use Illuminate\Contracts\Container\Container;
use VanDmade\Hookamatic\Events\HookamaticLog;
use RuntimeException;

class VerifierManager
{

    public function __construct(private Container $app) { }

    public function resolve(string $provider): VerifierInterface
    {
        $class = config('hookamatic.inbound.verifiers.'.$provider.'.class', null);
        if (is_null($class)) {
            $message = 'No verifier configured for provider: '.$provider;
            HookamaticLog::dispatch('error', $message, ['provider' => $provider]);
            throw new RuntimeException($message);
        }
        return $this->app->make($class);
    }

}
