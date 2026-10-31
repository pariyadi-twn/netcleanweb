<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\IssueSubscriptionInvoiceRequest;
use App\Http\Requests\Api\V1\Platform\VerifySubscriptionPaymentRequest;
use App\Http\Resources\Api\V1\SubscriptionInvoiceResource;
use App\Http\Resources\Api\V1\SubscriptionPaymentResource;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Saas\Actions\IssueSubscriptionInvoice;
use App\Modules\Saas\Actions\VerifySubscriptionPayment;
use App\Modules\Saas\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\TenantSubscription;
use App\Support\Http\CorrelationId;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionBillingController extends Controller
{
    public function store(
        IssueSubscriptionInvoiceRequest $request,
        string $tenant,
        TenantScope $scope,
        IssueSubscriptionInvoice $issue,
    ): JsonResponse {
        $this->authorizePlatform($request);

        $result = $scope->identity($request->user(), function () use ($tenant, $request, $issue): array {
            $model = Tenant::query()->where('public_id', $tenant)->firstOrFail();
            $subscription = TenantSubscription::query()
                ->where('tenant_id', $model->id)
                ->where('status', '!=', 'cancelled')
                ->with('plan')
                ->firstOrFail();

            $invoice = $issue->handle(
                $subscription,
                CarbonImmutable::parse($request->input('period_start')),
                CarbonImmutable::parse($request->input('period_end')),
                $request->input('kind', 'subscription'),
                $request->user(),
            );

            return [(new SubscriptionInvoiceResource($invoice))->resolve(), $model->public_id];
        });

        return response()->json([
            'data' => [...$result[0], 'tenant_id' => $result[1]],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function verify(
        VerifySubscriptionPaymentRequest $request,
        string $invoice,
        TenantScope $scope,
        VerifySubscriptionPayment $verify,
    ): JsonResponse {
        $this->authorizePlatform($request);

        $result = $scope->identity($request->user(), function () use ($invoice, $request, $verify): array {
            $model = SubscriptionInvoice::query()
                ->where('public_id', $invoice)
                ->with('subscription.plan')
                ->firstOrFail();
            $payment = $verify->handle(
                $model,
                (int) $request->integer('amount_rupiah'),
                $request->string('reference')->toString(),
                $request->user(),
            );

            return [(new SubscriptionPaymentResource($payment->load('invoice')))->resolve(), $model->tenant->public_id ?? null];
        });

        return response()->json([
            'data' => $result[0],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
    }
}
