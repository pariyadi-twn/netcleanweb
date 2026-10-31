<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashierPrototypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_prototype_renders_br05_fixture_for_usability_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/prototype/kasir')->assertOk();

        $response->assertSee('Rp71.600');
        $response->assertSee('Rp41.600');
        $response->assertSee('2,3 kg');
        $response->assertSee('3 kg');
        $response->assertSee('Prototype tidak menyimpan');
    }
}
