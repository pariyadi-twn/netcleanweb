<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'installation_hash' => hash('sha256', (string) Str::uuid()),
            'label' => fake()->words(2, true),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()]);
    }
}
