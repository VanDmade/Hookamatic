<?php

namespace VanDmade\Hookamatic\Inbound\Verification;

interface VerifierInterface
{

    /**
     * Verify the raw request body against the signature found in the headers,
     * using this verifier's own resolved secret.
     *
     * @param string $payload The raw, unparsed request body.
     * @param array $headers The request headers.
     * @return bool True if the verification is successful, false otherwise.
     */
    public function verify(string $payload, array $headers): bool;

    /**
     * Extract the provider's event ID from the payload and headers, prior to verification.
     *
     * @param string $payload The raw, unparsed request body.
     * @param array $headers The request headers.
     * @return string|null The extracted event ID, or null if not found.
     */
    public function extractEventId(string $payload, array $headers): ?string;

}
