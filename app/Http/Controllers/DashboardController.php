<?php

namespace App\Http\Controllers;

use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        if (! $context->can(PermissionCode::ReportsView)) {
            abort(403);
        }

        return view('dashboard', [
            'user' => $request->user(),
            'tenant' => Tenant::query()->findOrFail($context->tenantId),
            'branchCount' => Branch::query()->whereIn('id', $context->branchIds)->count(),
            'locationCount' => Location::query()->whereIn('id', $context->locationIds)->count(),
        ]);
    }
}
