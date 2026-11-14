<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\CreateRole;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('owner.roles.index', [
            'user' => $request->user(),
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, CreateRole $create): RedirectResponse
    {
        Gate::authorize('viewAny', Role::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:100'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $create->handle(
            $request->user(),
            app(TenantContext::class)->tenantId,
            $validated['code'],
            $validated['name'],
            array_map('intval', $validated['permission_ids'] ?? []),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('roles.index')
            ->with('status', 'Peran dibuat.');
    }

    public function edit(Request $request, Role $role): View
    {
        abort_unless($role->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $role);

        return view('owner.roles.edit', [
            'user' => $request->user(),
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('code')->get(),
        ]);
    }

    public function update(
        Request $request,
        Role $role,
        SyncRolePermissions $sync,
    ): RedirectResponse {
        abort_unless($role->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $role);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $role->forceFill(['name' => $validated['name']])->save();
        $sync->handle(
            $role,
            array_map('intval', $validated['permission_ids'] ?? []),
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('roles.edit', $role)
            ->with('status', 'Peran diperbarui.');
    }
}
