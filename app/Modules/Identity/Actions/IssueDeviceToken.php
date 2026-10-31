<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\PersonalAccessToken;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

final class IssueDeviceToken
{
    /**
     * @return array{token: NewAccessToken, device: Device}
     */
    public function handle(User $user, string $installationId, string $deviceName): array
    {
        $installationHash = hash('sha256', mb_strtolower(trim($installationId)));

        $device = $user->devices()->firstOrCreate(
            ['installation_hash' => $installationHash],
            ['label' => $deviceName, 'last_seen_at' => now()],
        );

        $device->forceFill([
            'label' => $deviceName,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        $device->tokens()->delete();

        $plainTextToken = Str::random(40);

        $token = PersonalAccessToken::query()->forceCreate([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => $deviceName,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'device_id' => $device->id,
            'expires_at' => now()->addMinutes((int) config('identity.token_ttl_minutes')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'token' => new NewAccessToken($token, $token->getKey().'|'.$plainTextToken),
            'device' => $device,
        ];
    }
}
