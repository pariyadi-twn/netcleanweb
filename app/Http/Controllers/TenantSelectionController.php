<?php

namespace App\Http\Controllers;

use App\Modules\Identity\Authorization\MembershipAuthorizer;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\TenantStatus;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSelectionController extends Controller
{
    public function index(Request $request, TenantScope $scope): View
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }
        $tenants = $scope->identity(
            $user,
            fn () => Tenant::query()
                ->where('status', TenantStatus::Active->value)
                ->whereHas('memberships', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', MembershipStatus::Active->value))
                ->orderBy('name')
                ->orderBy('id')
                ->get(['public_id', 'name', 'timezone']),
        );

        return view('tenants.select', [
            'user' => $user,
            'tenants' => $tenants,
        ]);
    }

    public function store(
        Request $request,
        TenantScope $scope,
        MembershipAuthorizer $authorizer,
    ): RedirectResponse {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }
        $tenantPublicId = (string) $validated['tenant_id'];
        $tenantId = $scope->identity(
            $user,
            fn (): ?int => Tenant::query()
                ->where('public_id', $tenantPublicId)
                ->whereHas('memberships', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', MembershipStatus::Active->value))
                ->value('id'),
        );

        if ($tenantId === null) {
            abort(404);
        }

        $authorized = $scope->forTenantId((int) $tenantId, $user->id, function () use ($authorizer, $tenantId, $user): bool {
            $tenant = Tenant::query()->findOrFail($tenantId);

            return $authorizer->contextFor($user, $tenant) !== null;
        });

        if (! $authorized) {
            abort(403);
        }

        $request->session()->put('tenant.public_id', $tenantPublicId);

        return redirect()->route('dashboard');
    }
}
