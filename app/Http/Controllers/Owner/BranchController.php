<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdateBranchConfiguration;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\LocationType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Branch::class);

        $context = app(TenantContext::class);
        $branches = Branch::query()
            ->whereIn('id', $context->branchIds)
            ->withCount('locations')
            ->orderBy('name')
            ->get();

        return view('owner.branches.index', [
            'user' => $request->user(),
            'branches' => $branches,
        ]);
    }

    public function edit(Request $request, Branch $branch): View
    {
        abort_unless($branch->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $branch);

        $context = app(TenantContext::class);
        $locations = Location::query()
            ->where('branch_id', $branch->id)
            ->whereIn('id', $context->locationIds)
            ->orderBy('name')
            ->get();

        return view('owner.branches.edit', [
            'user' => $request->user(),
            'branch' => $branch,
            'locations' => $locations,
        ]);
    }

    public function storeLocation(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $branch);
        Gate::authorize('create', [Location::class, $branch]);

        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:outlet,plant,warehouse,rack'],
        ]);

        $exists = Location::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['Kode lokasi sudah dipakai.']]);
        }

        $location = new Location([
            'code' => $validated['code'],
            'type' => LocationType::from($validated['type']),
            'name' => $validated['name'],
            'is_active' => true,
        ]);
        $location->tenant_id = $branch->tenant_id;
        $location->branch_id = $branch->id;
        $location->save();

        $context = app(TenantContext::class);
        $membership = TenantMembership::query()
            ->where('tenant_id', $context->tenantId)
            ->whereKey($context->membershipId)
            ->with(['roles', 'branches', 'locations'])
            ->firstOrFail();
        app(UpdateMembershipAssignments::class)->handle(
            $membership,
            $membership->roles->pluck('id')->all(),
            $membership->branches->pluck('id')->all(),
            $membership->locations->pluck('id')->push($location->id)->unique()->values()->all(),
            $request->user(),
            'Lokasi baru',
        );

        return redirect()
            ->route('branches.edit', $branch)
            ->with('status', 'Lokasi baru ditambahkan.');
    }

    public function update(
        Request $request,
        Branch $branch,
        UpdateBranchConfiguration $update,
    ): RedirectResponse {
        abort_unless($branch->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'address' => ['required', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $update->handle(
            $branch,
            [
                ...$validated,
                'timezone' => $validated['timezone'] ?? ($branch->timezone ?: 'Asia/Jakarta'),
                'is_active' => $request->boolean('is_active'),
            ],
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('branches.edit', $branch)
            ->with('status', 'Konfigurasi cabang diperbarui.');
    }
}
