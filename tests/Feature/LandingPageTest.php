<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_page_promotes_the_product_and_shows_auth_actions(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Operasional laundry dalam satu alur')
            ->assertSee('Buat akun owner')
            ->assertSee('Masuk ke konsol')
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    }
}
