<?php

namespace VanDmade\Hookamatic\Outbound\Signing;

use InvalidArgumentException;

class HmacSigner implements SignerInterface
{

    public function sign(
        string|array $payload,
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
        if (!is_string($payload)) {
            // Allows the develoepr to decide if they want to encode or throw an error if the payload is not a string. By default, we will encode the payload to a string.
            if (config('hookamatic.encode_payload', true)) {
                $payload = json_encode($payload);
                if (!$payload) {
                    throw new InvalidArgumentException('Payload could not be JSON-encoded when creating a signature.');
                }
            } else {
                throw new InvalidArgumentException('Payload must be a string when creating a signature.');
            }
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
