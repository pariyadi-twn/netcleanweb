<?php

namespace Database\Factories;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashSessionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSessionEvent>
 */
class CashSessionEventFactory extends Factory
{
    protected $model = CashSessionEvent::class;

    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'tenant_id' => fn (array $attributes): int => CashSession::query()->findOrFail($attributes['cash_session_id'])->tenant_id,
            'event_type' => 'opened',
            'actor_membership_id' => 1,
            'payload' => [],
            'reason' => null,
        ];
    }
}
