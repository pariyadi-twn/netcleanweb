<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApiSecuritySurfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_api_surface_rejects_anonymous_requests(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_api_security_headers_are_present_and_untrusted_cors_origin_is_not_allowed(): void
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'")
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
