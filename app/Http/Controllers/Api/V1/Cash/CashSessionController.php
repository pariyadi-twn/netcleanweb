<?php

namespace App\Http\Controllers\Api\V1\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cash\CloseCashSessionRequest;
use App\Http\Requests\Api\V1\Cash\OpenCashSessionRequest;
use App\Http\Requests\Api\V1\Cash\ReopenCashSessionRequest;
use App\Http\Resources\Api\V1\CashSessionResource;
use App\Modules\Cash\Actions\CloseCashSession;
use App\Modules\Cash\Actions\OpenCashSession;
use App\Modules\Cash\Actions\ReopenCashSession;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CashSessionController extends Controller
{
    public function store(
        OpenCashSessionRequest $request,
        string $register,
        OpenCashSession $open,
    ): JsonResponse {
        $context = app(TenantContext::class);
        $model = CashRegister::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $register)
            ->with('branch')
            ->firstOrFail();
        Gate::authorize('open', [CashSession::class, $model]);

        $session = $open->handle($model, (int) $request->input('opening_rupiah'), $context->membershipId);

        return CashSessionResource::make($session)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $session): CashSessionResource
    {
        $model = $this->findSession($session);
        Gate::authorize('view', $model);

        return new CashSessionResource($model);
    }

    public function close(
        CloseCashSessionRequest $request,
        string $session,
        CloseCashSession $close,
    ): CashSessionResource {
        $model = $this->findSession($session);
        Gate::authorize('close', $model);

        return new CashSessionResource($close->handle(
            $model,
            (int) $request->input('counted_closing_rupiah'),
            $request->input('reason'),
            app(TenantContext::class)->membershipId,
        ));
    }

    public function reopen(
        ReopenCashSessionRequest $request,
        string $session,
        ReopenCashSession $reopen,
    ): CashSessionResource {
        $model = $this->findSession($session);
        Gate::authorize('reopen', $model);

        return new CashSessionResource($reopen->handle(
            $model,
            (string) $request->input('reason'),
            app(TenantContext::class)->membershipId,
        ));
    }

    public function events(string $session): JsonResponse
    {
        $model = $this->findSession($session);
        Gate::authorize('view', $model);

        return response()->json([
            'data' => $model->events->map(fn ($event): array => [
                'id' => $event->public_id,
                'event_type' => $event->event_type,
                'actor_membership_id' => $event->actor_membership_id,
                'payload' => $event->payload,
                'reason' => $event->reason,
                'created_at' => $event->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            ])->values()->all(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function findSession(string $publicId): CashSession
    {
        return CashSession::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->with(['register.branch', 'cashier', 'entries', 'events'])
            ->firstOrFail();
    }
}
