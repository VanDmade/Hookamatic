<?php

namespace VanDmade\Hookamatic\Outbound;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Outbound\Signing\SignerInterface;

class WebhookSender
{

    public function __construct(
        private SignerInterface $signer
    ) {}

    public function send(Delivery $delivery): ?Response
    {
        $subscriber = $delivery->subscriber;
        if (is_null($subscriber)) {
            $delivery->failure_reason = 'Subscriber no longer exists.';
            return null;
        }
        // Encoded once so the exact bytes that get signed are the exact bytes sent.
        $payload = json_encode($delivery->request_payload);
        $signature = $this->signer->sign($payload, $subscriber->signing_secret);
        $headers = array_merge($subscriber->headers ?? [], [
            'X-Hookamatic-Signature' => $signature,
            'X-Hookamatic-Delivery-ID' => $delivery->delivery_uuid,
        ]);
        try {
            return Http::withHeaders($headers)
                ->withBody($payload, 'application/json')
                ->post($subscriber->url);
        } catch (ConnectionException $error) {
            // DNS failure, timeout, connection refused, etc. Returned as null so the
            // job treats it identically to any other failed attempt.
            $delivery->failure_reason = $error->getMessage();
            return null;
        }
    }

}
