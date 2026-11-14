<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TenantMembership::class);

        $memberships = TenantMembership::query()
            ->with(['user', 'roles', 'branches', 'locations'])
            ->orderBy('id')
            ->get()
            ->sortBy(fn (TenantMembership $membership): string => $membership->user->name)
            ->values();

        return view('owner.staff.index', [
            'user' => $request->user(),
            'memberships' => $memberships,
        ]);
    }

    public function edit(Request $request, TenantMembership $membership): View
    {
        abort_unless($membership->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $membership);

        $context = app(TenantContext::class);

        return view('owner.staff.edit', [
            'user' => $request->user(),
            'membership' => $membership->load(['user', 'roles', 'branches', 'locations']),
            'roles' => Role::query()->orderBy('name')->get(),
            'branches' => Branch::query()
                ->whereIn('id', $context->branchIds)
                ->orderBy('name')
                ->get(),
            'locations' => Location::query()
                ->whereIn('id', $context->locationIds)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        Request $request,
        TenantMembership $membership,
        UpdateMembershipAssignments $update,
    ): RedirectResponse {
        abort_unless($membership->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $membership);
        Gate::authorize('viewAny', Role::class);

        $validated = $request->validate([
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer'],
            'location_ids' => ['sometimes', 'array'],
            'location_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $context = app(TenantContext::class);
        $branchIds = array_map('intval', $validated['branch_ids'] ?? []);
        $locationIds = array_map('intval', $validated['location_ids'] ?? []);

        if (array_diff($branchIds, $context->branchIds) !== []) {
            throw ValidationException::withMessages([
                'branch_ids' => ['One or more branches are outside your access.'],
            ]);
        }

        if (array_diff($locationIds, $context->locationIds) !== []) {
            throw ValidationException::withMessages([
                'location_ids' => ['One or more locations are outside your access.'],
            ]);
        }

        $update->handle(
            $membership,
            array_map('intval', $validated['role_ids'] ?? []),
            $branchIds,
            $locationIds,
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('staff.edit', $membership)
            ->with('status', 'Penugasan staf diperbarui.');
    }
}
