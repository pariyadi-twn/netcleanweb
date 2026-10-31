<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_web_responses_include_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'");
    }

    #[TestWith(['production', 'https://localhost/', true])]
    #[TestWith(['production', 'http://localhost/', false])]
    #[TestWith(['local', 'https://localhost/', false])]
    public function test_hsts_is_only_sent_for_secure_production_requests(string $environment, string $url, bool $expected): void
    {
        $this->app->instance('env', $environment);

        $response = $this->get($url);

        $response->assertOk();

        if ($expected) {
            $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        } else {
            $response->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_anonymous_users_cannot_access_horizon_even_in_local_environment(): void
    {
        $this->app->instance('env', 'local');

        $this->get('/horizon')->assertForbidden();
    }
}
