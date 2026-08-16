<?php

namespace VanDmade\Hookamatic\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use VanDmade\Hookamatic\Enums\InboundStatus;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Events\InboundWebhookMaxAttempts;
use VanDmade\Hookamatic\Events\InboundWebhookProcessed;
use VanDmade\Hookamatic\Events\InboundWebhookReceived;
use VanDmade\Hookamatic\Events\InboundWebhookVerificationFailed;
use VanDmade\Hookamatic\Inbound\Verification\VerifierManager;
use VanDmade\Hookamatic\Services\InboundEventService;
use Closure;

class VerifyInboundWebhook
{

    public function __construct(
        private VerifierManager $verifierManager,
        private InboundEventService $inboundEventService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        ?string $provider = null,
        ?string $eventType = null
    ): Response {
        try {
            return $this->verify($request, $next, $provider, $eventType);
        } catch (Throwable $exception) {
            HookamaticLog::dispatch('error', $exception->getMessage(), [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            throw $exception;
        }
    }

    private function verify(
        Request $request,
        Closure $next,
        ?string $provider,
        ?string $eventType
    ): Response {
        $startTime = microtime(true);
        // Global kill switch - bypasses Hookamatic entirely for every provider.
        if (!config('hookamatic.inbound.enabled', true)) {
            return $next($request);
        }
        $allowWithoutProvider = config('hookamatic.allow_without_provider', false);
        if ($allowWithoutProvider && is_null($provider)) {
            // Explicitly opted into skipping Hookamatic entirely when no provider is given.
            // Useful for testing; otherwise, JUST REMOVE THE HOOKAMATIC MIDDLEWARE from the route.
            return $next($request);
        }
        if (is_null($provider)) {
            return response()->json(['message' => 'Provider not specified'], 400);
        }
        // Per-provider kill switch - bypasses Hookamatic just for this one provider.
        if (!config('hookamatic.inbound.verifiers.'.$provider.'.enabled', true)) {
            return $next($request);
        }
        // Grabs and validates the verifier from the config file. If the provider is not configured, an exception will be thrown.
        $verifier = $this->verifierManager->resolve($provider);
        $providerId = $verifier->extractEventId($request->getContent(), $request->headers->all());
        $inboundEvent = $this->inboundEventService->findOrCreateByProviderAndEventId(
            provider: $provider,
            providerEventId: $providerId,
        );
        InboundWebhookReceived::dispatch($provider, $inboundEvent);
        if ($inboundEvent->status == InboundStatus::PROCESSED) {
            // Prevents accidentally re-processing an event that has been processed by the system.
            return response()->json(['message' => 'Event already processed'], 200);
        }
        // Updates or sets the request
        $inboundEvent->event_type = $eventType;
        $inboundEvent->request = $request;
        $inboundEvent->status = InboundStatus::PENDING;
        // Verifies the inbound webhook request to make sure everything lines up
        if (!$verifier->verify($request->getContent(), $request->headers->all())) {
            $inboundEvent->status = InboundStatus::FAILED;
            $inboundEvent->save();
            InboundWebhookVerificationFailed::dispatch($provider, $inboundEvent);
            // If the verification fails, a 403 response is returned
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Count genuine attempts rather than spoofed attempts
        $inboundEvent->attempt_counter += 1;
        $response = $next($request);
        // Closes out the inbound event with the final status and duration.
        $successful = $response->isSuccessful();
        $inboundEvent->status = $successful ? InboundStatus::PROCESSED : InboundStatus::FAILED;
        $inboundEvent->duration_ms = (int) ((microtime(true) - $startTime) * 1000);
        $inboundEvent->save();
        $maxAttempts = config('hookamatic.inbound.max_attempts', 10);
        if (!$successful && !is_null($maxAttempts) && $inboundEvent->attempt_counter >= $maxAttempts) {
            // If the response was not successful and the attempts have exceeded the desired amount
            // then we will return a 200 response to prevent the provider spamming the server
            InboundWebhookMaxAttempts::dispatch($provider, $inboundEvent, $response);
            return response()->json([
                'message' => 'Our server has encountered an error too many times. '.
                    'We will investigate and resolve the issue shortly.',
            ], 200);
        } elseif ($successful) {
            // Dispatches the event to allow for the parent app to be notified of a successfull processing
            InboundWebhookProcessed::dispatch($provider, $inboundEvent, $response, $inboundEvent->duration_ms);
        }
        return $response;
    }

}
