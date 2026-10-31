<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Lifecycle\Models\DataRequest;
use App\Modules\Saas\Models\SaasPlan;
use App\Modules\Saas\Models\SubscriptionInvoice;
use App\Modules\Saas\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request, TenantScope $scope): View
    {
        $data = $scope->identity($request->user(), function (): array {
            $tenants = Tenant::query()->orderBy('name')->orderBy('id')->get();
            foreach ($tenants as $tenant) {
                $tenant->platform_subscription = TenantSubscription::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', '!=', 'cancelled')
                    ->with('plan')
                    ->first();
            }

            return [
                'tenants' => $tenants,
                'plans' => SaasPlan::query()->orderBy('code')->orderByDesc('revision')->get(),
                'dataRequests' => DataRequest::query()->with('tenant')->orderByDesc('created_at')->limit(20)->get(),
                'invoices' => SubscriptionInvoice::query()->with(['subscription.plan'])->orderByDesc('created_at')->limit(10)->get(),
            ];
        });

        return view('platform.dashboard', $data);
    }
}
