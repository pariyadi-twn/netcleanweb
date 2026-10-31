<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'status' => 'active',
            'permission_version' => 1,
            'invited_by_user_id' => null,
        ];
    }
}
