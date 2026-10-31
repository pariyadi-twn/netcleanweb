<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MyContextResource;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\TenantMembership;
use App\Support\Http\CorrelationId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyContextController extends Controller
{
    public function __invoke(Request $request, TenantScope $scope): AnonymousResourceCollection
    {
        $contexts = $scope->identity($request->user(), function () use ($request) {
            $memberships = $request->user()->memberships()
                ->where('status', MembershipStatus::Active->value)
                ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active->value))
                ->with(['tenant', 'branches', 'locations'])
                ->orderBy('tenant_id')
                ->orderBy('id')
                ->get();

            return $memberships->map(fn (TenantMembership $membership): array => [
                'tenant_id' => $membership->tenant->public_id,
                'membership_id' => $membership->public_id,
                'permission_version' => $membership->permission_version,
                'branch_ids' => $membership->branches->pluck('public_id')->values()->all(),
                'location_ids' => $membership->locations->pluck('public_id')->values()->all(),
            ]);
        });

        return MyContextResource::collection($contexts)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]]);
    }
}
