<?php

namespace VanDmade\Hookamatic\Tests\Unit;

use InvalidArgumentException;
use VanDmade\Hookamatic\Outbound\Signing\HmacSigner;
use VanDmade\Hookamatic\Tests\TestCase;

class HmacSignerTest extends TestCase
{

    public function test_sign_produces_the_expected_format_and_hash(): void
    {
        $signer = new HmacSigner();
        $signature = $signer->sign('{"hello":"world"}', 'shh-its-a-secret', 1700000000);
        $expected = hash_hmac('sha256', '1700000000.{"hello":"world"}', 'shh-its-a-secret');
        $this->assertSame('t=1700000000,v1='.$expected, $signature);
    }

    public function test_sign_uses_the_current_time_when_no_timestamp_is_given(): void
    {
        $signer = new HmacSigner();
        $before = time();
        $signature = $signer->sign('payload', 'secret');
        $after = time();
        preg_match('/^t=(\d+),v1=/', $signature, $matches);
        $this->assertGreaterThanOrEqual($before, (int) $matches[1]);
        $this->assertLessThanOrEqual($after, (int) $matches[1]);
    }

    public function test_sign_throws_when_the_payload_is_empty(): void
    {
        $signer = new HmacSigner();
        $this->expectException(InvalidArgumentException::class);
        $signer->sign('', 'secret');
    }

    public function test_sign_throws_when_the_secret_is_empty(): void
    {
        $signer = new HmacSigner();
        $this->expectException(InvalidArgumentException::class);
        $signer->sign('payload', '');
    }

    public function test_sign_throws_for_an_unsupported_algorithm(): void
    {
        config()->set('hookamatic.signing_algorithm', 'not-a-real-algorithm');
        $signer = new HmacSigner();
        $this->expectException(InvalidArgumentException::class);
        $signer->sign('payload', 'secret');
    }

    public function test_sign_respects_a_configured_algorithm(): void
    {
        config()->set('hookamatic.signing_algorithm', 'sha512');
        $signer = new HmacSigner();
        $signature = $signer->sign('payload', 'secret', 1700000000);
        $expected = hash_hmac('sha512', '1700000000.payload', 'secret');
        $this->assertSame('t=1700000000,v1='.$expected, $signature);
    }

}
