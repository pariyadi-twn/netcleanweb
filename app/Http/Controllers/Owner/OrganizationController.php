<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdateTenantProfile;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $context = app(TenantContext::class);
        abort_unless($context->can(PermissionCode::MembershipsManage), 403);

        return view('owner.organization.index', [
            'user' => $request->user(),
            'tenant' => Tenant::query()->findOrFail($context->tenantId),
        ]);
    }

    public function update(
        Request $request,
        UpdateTenantProfile $update,
    ): RedirectResponse {
        $context = app(TenantContext::class);
        abort_unless($context->can(PermissionCode::MembershipsManage), 403);

        $tenant = Tenant::query()->findOrFail($context->tenantId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $update->handle(
            $tenant,
            [
                'name' => $validated['name'],
                'timezone' => $validated['timezone'] ?? ($tenant->timezone ?: 'Asia/Jakarta'),
            ],
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('organization.index')
            ->with('status', 'Profil organisasi diperbarui.');
    }
}
