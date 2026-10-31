<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\UpdateBranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Modules\Identity\Actions\UpdateBranchConfiguration;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use Illuminate\Support\Facades\Gate;

class BranchController extends Controller
{
    public function show(string $branch): BranchResource
    {
        $model = $this->findBranch($branch);

        Gate::authorize('view', $model);

        return new BranchResource($model);
    }

    public function update(UpdateBranchRequest $request, string $branch, UpdateBranchConfiguration $action): BranchResource
    {
        $model = $this->findBranch($branch);

        Gate::authorize('manage', $model);

        $updated = $action->handle(
            $model,
            $request->validated(),
            $request->user(),
            $request->input('reason'),
        );

        return new BranchResource($updated);
    }

    private function findBranch(string $publicId): Branch
    {
        $context = app(TenantContext::class);

        return Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
