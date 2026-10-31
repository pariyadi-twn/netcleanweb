<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operations\UpdateBranchCalendarRequest;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Modules\Operations\Actions\UpdateBranchCalendar;
use App\Modules\Operations\Support\BranchCalendarConfig;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BranchCalendarController extends Controller
{
    public function show(string $branch, BranchCalendarConfig $config): JsonResponse
    {
        $model = $this->findBranch($branch);

        Gate::authorize('view', $model);

        return response()->json([
            'data' => $config->present($model, $model->calendars()->get()),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function update(
        UpdateBranchCalendarRequest $request,
        string $branch,
        UpdateBranchCalendar $update,
    ): JsonResponse {
        $model = $this->findBranch($branch);

        Gate::authorize('manage', $model);

        $calendar = $update->handle(
            $model,
            $request->validated(),
            $request->user(),
            $request->input('reason'),
        );

        return response()->json([
            'data' => $calendar,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function findBranch(string $publicId): Branch
    {
        return Branch::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
