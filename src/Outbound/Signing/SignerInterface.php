<?php

namespace VanDmade\Hookamatic\Outbound\Signing;

interface SignerInterface
{

    /**
     * Sign the payload with the given secret and timestamp.
     *
     * @param string $payload The raw payload to sign - the exact bytes being sent.
     * @param string $secret The secret key used for signing. This normally comes from the Subscriber model.
     * @param int|null $timestamp The timestamp to include in the signature. If null, the current time will be used.
     * @return string The fully formatted header t=......,v1=.....
     */
    public function sign(string $payload, string $secret, ?int $timestamp = null): string;

}
