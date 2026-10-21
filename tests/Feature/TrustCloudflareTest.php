<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustCloudflareTest extends TestCase
{
    private array $cf = ['CF-Connecting-IP' => '203.0.113.7', 'CF-Visitor' => '{"scheme":"https"}'];

    public function test_takes_visitor_ip_and_https_from_cloudflare_when_enabled(): void
    {
        config(['app.behind_cloudflare' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/up', $this->cf)->assertOk();

        $this->assertSame('203.0.113.7', request()->ip());
        $this->assertTrue(request()->isSecure());
    }

    public function test_ignores_the_headers_from_a_public_address_or_when_disabled(): void
    {
        config(['app.behind_cloudflare' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/up', $this->cf)->assertOk();
        $this->assertSame('8.8.8.8', request()->ip());
        $this->assertFalse(request()->isSecure());

        config(['app.behind_cloudflare' => false]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/up', $this->cf)->assertOk();
        $this->assertSame('127.0.0.1', request()->ip());
    }
}
