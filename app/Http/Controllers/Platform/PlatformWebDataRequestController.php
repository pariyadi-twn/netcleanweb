<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Lifecycle\Actions\DecideDataRequest;
use App\Modules\Lifecycle\Actions\ProcessDataDeletion;
use App\Modules\Lifecycle\Models\DataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformWebDataRequestController extends Controller
{
    public function verify(Request $request, string $dataRequest, TenantScope $scope, DecideDataRequest $decide): RedirectResponse
    {
        $validated = $request->validate([
            'verification_method' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $scope->identity($request->user(), function () use ($request, $dataRequest, $scope, $decide, $validated): void {
            $model = DataRequest::query()->where('public_id', $dataRequest)->firstOrFail();
            $scope->forTenantId($model->tenant_id, $request->user()->id, fn () => $decide->handle(
                $model,
                true,
                $validated['verification_method'],
                $request->user()->id,
                $validated['reason'] ?? null,
                $request->user(),
            ));
        });

        return back()->with('status', 'Permintaan data diverifikasi.');
    }

    public function process(Request $request, string $dataRequest, TenantScope $scope, ProcessDataDeletion $process): RedirectResponse
    {
        $scope->identity($request->user(), function () use ($request, $dataRequest, $scope, $process): void {
            $model = DataRequest::query()->where('public_id', $dataRequest)->firstOrFail();
            $scope->forTenantId($model->tenant_id, $request->user()->id, fn () => $process->handle(
                $model,
                $request->user()->id,
                $request->user(),
            ));
        });

        return back()->with('status', 'PII dianonimkan dan tombstone dicatat.');
    }

    public function reject(Request $request, string $dataRequest, TenantScope $scope, DecideDataRequest $decide): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $scope->identity($request->user(), function () use ($request, $dataRequest, $scope, $decide, $validated): void {
            $model = DataRequest::query()->where('public_id', $dataRequest)->firstOrFail();
            $scope->forTenantId($model->tenant_id, $request->user()->id, fn () => $decide->handle(
                $model,
                false,
                'platform_review',
                $request->user()->id,
                $validated['reason'],
                $request->user(),
            ));
        });

        return back()->with('status', 'Permintaan data ditolak.');
    }
}
