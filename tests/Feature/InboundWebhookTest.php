<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use VanDmade\Hookamatic\Enums\InboundStatus;
use VanDmade\Hookamatic\Events\InboundWebhookMaxAttempts;
use VanDmade\Hookamatic\Events\InboundWebhookVerificationFailed;
use VanDmade\Hookamatic\Models\InboundEvent;
use VanDmade\Hookamatic\Tests\TestCase;

class InboundWebhookTest extends TestCase
{

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hookamatic.inbound.enabled', true);
        config()->set('hookamatic.inbound.verifiers.stripe.secret', self::SECRET);
        config()->set('hookamatic.inbound.verifiers.stripe.tolerance', 300);
        config()->set('hookamatic.inbound.verifiers.stripe.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        $router->post('hookamatic-test/stripe', function(Request $request) {
            return $request->boolean('should_fail') ?
                response()->json(['ok' => false], 500) : response()->json(['ok' => true], 200);
        })->middleware('hookamatic:stripe');
        $router->post('hookamatic-test/no-provider', fn() => response()->json(['ok' => true]))
            ->middleware('hookamatic');
        $router->post('hookamatic-test/unknown-provider', fn() => response()->json(['ok' => true]))
            ->middleware('hookamatic:not-a-real-provider');
    }

    private function signedServerVars(
        string $body,
        string $secret = self::SECRET,
        ?int $timestamp = null
    ): array {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        return $this->transformHeadersToServerVars([
            'Stripe-Signature' => 't='.$timestamp.',v1='.$signature,
            'Content-Type' => 'application/json',
        ]);
    }

    private function invalidServerVars(): array
    {
        return $this->transformHeadersToServerVars([
            'Stripe-Signature' => 't='.time().',v1=not-a-real-signature',
            'Content-Type' => 'application/json',
        ]);
    }

    public function test_valid_signature_passes_and_reaches_the_route_handler(): void
    {
        $body = json_encode([
            'id' => 'evt_valid',
            'should_fail' => false,
        ]);
        $response = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->signedServerVars($body), $body);
        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $event = InboundEvent::where('provider_event_id', 'evt_valid')->first();
        $this->assertNotNull($event);
        // Successful call means the inbound event was processed
        $this->assertSame(InboundStatus::PROCESSED, $event->status);
    }

    public function test_invalid_signature_is_rejected_before_the_handler_runs(): void
    {
        $body = json_encode(['id' => 'evt_invalid']);
        $response = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body);
        $response->assertStatus(403);
        $event = InboundEvent::where('provider_event_id', 'evt_invalid')->first();
        $this->assertSame(InboundStatus::FAILED, $event->status);
    }

    public function test_verification_failure_fires_the_event(): void
    {
        Event::fake([InboundWebhookVerificationFailed::class]);
        $body = json_encode(['id' => 'evt_invalid']);
        $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body);
        Event::assertDispatched(InboundWebhookVerificationFailed::class);
    }

    public function test_duplicate_event_id_short_circuits_without_re_verifying(): void
    {
        $body = json_encode([
            'id' => 'evt_dup',
            'should_fail' => false,
        ]);
        $first = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->signedServerVars($body), $body);
        $first->assertOk();
        // Second request reuses the same event id but carries a garbage signature - if
        // idempotency is working, this never reaches verify() and still returns 200.
        $second = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body);
        $second->assertOk();
        $this->assertSame(1, InboundEvent::where('provider_event_id', 'evt_dup')->count());
    }

    public function test_global_kill_switch_bypasses_everything(): void
    {
        config()->set('hookamatic.inbound.enabled', false);
        $body = json_encode(['id' => 'evt_bypass']);
        $response = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body);
        $response->assertOk();
        $this->assertSame(0, InboundEvent::count());
    }

    public function test_per_provider_kill_switch_bypasses_just_that_provider(): void
    {
        config()->set('hookamatic.inbound.verifiers.stripe.enabled', false);
        $body = json_encode(['id' => 'evt_bypass']);
        $response = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body);
        $response->assertOk();
        $this->assertSame(0, InboundEvent::count());
    }

    public function test_unconfigured_provider_throws(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $body = json_encode(['id' => 'evt_x']);
        $server = $this->transformHeadersToServerVars(['Content-Type' => 'application/json']);
        $this->call('POST', 'hookamatic-test/unknown-provider', [], [], [], $server, $body);
    }

    public function test_missing_provider_without_opt_in_returns_a_400(): void
    {
        $body = json_encode(['id' => 'evt_x']);
        $server = $this->transformHeadersToServerVars(['Content-Type' => 'application/json']);
        $response = $this->call('POST', 'hookamatic-test/no-provider', [], [], [], $server, $body);
        $response->assertStatus(400);
    }

    public function test_max_attempts_eventually_forces_a_200_and_fires_the_event(): void
    {
        config()->set('hookamatic.inbound.max_attempts', 2);
        Event::fake([InboundWebhookMaxAttempts::class]);
        $body = json_encode(['id' => 'evt_maxattempts', 'should_fail' => true]);
        $server = $this->signedServerVars($body);
        $first = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $server, $body);
        $first->assertStatus(500);
        $second = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $server, $body);
        $second->assertOk();
        Event::assertDispatched(InboundWebhookMaxAttempts::class);
    }

    public function test_failed_verification_attempts_do_not_count_toward_max_attempts(): void
    {
        config()->set('hookamatic.inbound.max_attempts', 2);
        $body = json_encode(['id' => 'evt_budget', 'should_fail' => true]);

        // Several forged-signature attempts against the same event id first - none of
        // these should draw down the retry budget that max_attempts governs.
        for ($i = 0; $i < 5; $i++) {
            $this->call('POST', 'hookamatic-test/stripe', [], [], [], $this->invalidServerVars(), $body)
                ->assertStatus(403);
        }

        $server = $this->signedServerVars($body);
        // Now two genuinely-signed (but failing-handler) attempts - should take exactly
        // max_attempts of THESE to trip the max-attempts short circuit, not fewer.
        $first = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $server, $body);
        $first->assertStatus(500);

        $second = $this->call('POST', 'hookamatic-test/stripe', [], [], [], $server, $body);
        $second->assertOk();

        $event = InboundEvent::where('provider_event_id', 'evt_budget')->first();
        $this->assertSame(2, $event->attempt_counter);
    }

}
