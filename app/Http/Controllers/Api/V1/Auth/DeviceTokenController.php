<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\IssueDeviceTokenRequest;
use App\Http\Resources\Api\V1\DeviceSessionResource;
use App\Http\Resources\Api\V1\DeviceTokenResource;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\RevokeDeviceToken;
use App\Modules\Identity\Models\Device;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenController extends Controller
{
    public function store(
        IssueDeviceTokenRequest $request,
        AuthenticateUser $authenticate,
        IssueDeviceToken $issue,
    ): JsonResponse {
        $user = $authenticate->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('mfa_code'),
        );

        $result = $issue->handle(
            $user,
            $request->string('installation_id')->toString(),
            $request->string('device_name')->toString(),
        );

        return DeviceTokenResource::make([
            'token' => $result['token']->plainTextToken,
            'expires_at' => $result['token']->accessToken->expires_at,
            'device_id' => $result['device']->public_id,
        ])->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = $request->user()->devices()
            ->whereNull('revoked_at')
            ->whereHas('tokens', fn ($query) => $query->where('expires_at', '>', now()))
            ->with(['tokens' => fn ($query) => $query->where('expires_at', '>', now())->latest('id')])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $sessions = $devices->map(function (Device $device): array {
            $token = $device->tokens->first();

            return [
                'device_id' => $device->public_id,
                'name' => $device->label,
                'created_at' => $device->created_at,
                'last_used_at' => $token?->last_used_at,
                'expires_at' => $token?->expires_at,
            ];
        });

        return DeviceSessionResource::collection($sessions)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]]);
    }

    public function destroy(Device $device, Request $request, RevokeDeviceToken $revoke): Response
    {
        $owned = $request->user()->devices()->where('public_id', $device->public_id)->firstOrFail();

        $revoke->handle($request->user(), $owned);

        return response()->noContent();
    }
}
