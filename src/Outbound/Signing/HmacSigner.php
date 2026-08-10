<?php

namespace VanDmade\Hookamatic\Outbound\Signing;

use InvalidArgumentException;

class HmacSigner implements SignerInterface
{

    public function sign(
        string $payload,
        string $secret,
        ?int $timestamp = null
    ): string {
        // Used for testing purposes, if no timestamp is provided, we will use the current time.
        if (is_null($timestamp)) {
            $timestamp = time();
        }
        if (empty($payload)) {
            throw new InvalidArgumentException('Payload cannot be empty when creating a signature.');
        }
        if (empty($secret)) {
            throw new InvalidArgumentException('The secret received was empty, unable to create a signature.');
        }
        $algorithm = config('hookamatic.signing_algorithm', 'sha256');
        if (in_array($algorithm, hash_algos()) === false) {
            throw new InvalidArgumentException('The signing algorithm received is not supported by the current PHP installation.');
        }
        $signature = hash_hmac($algorithm, $timestamp.'.'.$payload, $secret);
        return sprintf('t=%d,v1=%s', $timestamp, $signature);
    }

}
