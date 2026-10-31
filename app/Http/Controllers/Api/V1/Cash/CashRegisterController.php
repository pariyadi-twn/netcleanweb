<?php

namespace App\Http\Controllers\Api\V1\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cash\StoreCashRegisterRequest;
use App\Http\Resources\Api\V1\CashRegisterResource;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CashRegisterController extends Controller
{
    public function index(string $branch): AnonymousResourceCollection
    {
        $branchModel = $this->findBranch($branch);
        Gate::authorize('view', $branchModel);
        Gate::authorize('viewAny', CashRegister::class);

        $registers = CashRegister::query()
            ->where('tenant_id', $branchModel->tenant_id)
            ->where('branch_id', $branchModel->id)
            ->with('branch')
            ->orderBy('code')
            ->get();

        return CashRegisterResource::collection($registers)->additional([
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function store(StoreCashRegisterRequest $request, string $branch): JsonResponse
    {
        $branchModel = $this->findBranch($branch);
        Gate::authorize('create', [CashRegister::class, $branchModel]);

        $exists = CashRegister::query()
            ->where('tenant_id', $branchModel->tenant_id)
            ->where('branch_id', $branchModel->id)
            ->where('code', $request->input('code'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['The code has already been taken for this branch.']]);
        }

        $register = new CashRegister([
            'branch_id' => $branchModel->id,
            'code' => (string) $request->input('code'),
            'is_active' => true,
        ]);
        $register->tenant_id = $branchModel->tenant_id;
        $register->save();

        return CashRegisterResource::make($register->load('branch'))
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    private function findBranch(string $publicId): Branch
    {
        return Branch::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
