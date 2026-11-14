<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdateBranchConfiguration;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    public function update(
        Request $request,
        Branch $branch,
        UpdateBranchConfiguration $update,
    ): RedirectResponse {
        abort_unless($branch->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('manage', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', 'timezone'],
            'address' => ['required', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $update->handle(
            $branch,
            [...$validated, 'is_active' => $request->boolean('is_active')],
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('branches.edit', $branch)
            ->with('status', 'Konfigurasi cabang diperbarui.');
    }
}
