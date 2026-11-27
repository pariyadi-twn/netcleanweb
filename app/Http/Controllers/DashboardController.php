<?php

namespace App\Http\Controllers;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        if (! $context->can(PermissionCode::ReportsView)) {
            abort(403);
        }

        $pendingOrders = $context->can(PermissionCode::OrdersView)
            ? Order::query()
                ->where('tenant_id', $context->tenantId)
                ->whereIn('sales_branch_id', $context->branchIds)
                ->whereIn('operational_status', [
                    OrderStatus::Accepted->value,
                    OrderStatus::InProgress->value,
                    OrderStatus::PartiallyReady->value,
                    OrderStatus::Ready->value,
                ])
                ->count()
            : 0;

        $activeTasks = $context->can(PermissionCode::ProductionView)
            ? ProductionTask::query()
                ->where('tenant_id', $context->tenantId)
                ->whereIn('production_location_id', $context->locationIds)
                ->whereIn('status', [
                    ProductionTaskStatus::Queued->value,
                    ProductionTaskStatus::InProgress->value,
                    ProductionTaskStatus::Blocked->value,
                ])
                ->count()
            : 0;

        $openCashSessions = $context->can(PermissionCode::CashView)
            ? CashSession::query()
                ->where('tenant_id', $context->tenantId)
                ->where('status', 'open')
                ->whereHas('register', fn ($query) => $query->whereIn('branch_id', $context->branchIds))
                ->count()
            : 0;

        $recentOrders = $context->can(PermissionCode::OrdersView)
            ? Order::query()
                ->where('tenant_id', $context->tenantId)
                ->whereIn('sales_branch_id', $context->branchIds)
                ->with(['customer', 'invoice'])
                ->latest('accepted_at')
                ->latest('id')
                ->limit(5)
                ->get()
            : collect();

        $locationCount = Location::query()->whereIn('id', $context->locationIds)->count();

        $checklist = [
            [
                'label' => 'Lokasi produksi siap',
                'description' => 'Minimal satu outlet atau pusat produksi dalam scope.',
                'done' => $locationCount > 0,
                'route' => $context->can(PermissionCode::BranchesManage) ? route('branches.index') : null,
            ],
            [
                'label' => 'Layanan dan harga aktif',
                'description' => 'Layanan kiloan dan harga berlaku sudah tersedia.',
                'done' => Service::query()->where('tenant_id', $context->tenantId)->where('is_active', true)->exists()
                    && PriceEntry::query()->where('tenant_id', $context->tenantId)->exists(),
                'route' => $context->can(PermissionCode::CatalogView) ? route('catalog.index') : null,
            ],
            [
                'label' => 'Laci kas siap',
                'description' => 'Diperlukan untuk menerima pembayaran tunai.',
                'done' => CashRegister::query()->where('tenant_id', $context->tenantId)->whereIn('branch_id', $context->branchIds)->exists(),
                'route' => $context->can(PermissionCode::CashManage) ? route('finance.cash.index') : null,
            ],
            [
                'label' => 'Akun staf tambahan',
                'description' => 'Opsional; bisa dipakai untuk kasir atau operator.',
                'done' => TenantMembership::query()->where('tenant_id', $context->tenantId)->where('status', 'active')->count() > 1,
                'route' => $context->can(PermissionCode::MembershipsView) ? route('staff.index') : null,
            ],
        ];

        return view('dashboard', [
            'user' => $request->user(),
            'tenant' => Tenant::query()->findOrFail($context->tenantId),
            'branchCount' => Branch::query()->whereIn('id', $context->branchIds)->count(),
            'locationCount' => $locationCount,
            'pendingOrders' => $pendingOrders,
            'activeTasks' => $activeTasks,
            'openCashSessions' => $openCashSessions,
            'recentOrders' => $recentOrders,
            'checklist' => $checklist,
            'checklistComplete' => collect($checklist)->every(fn (array $item): bool => $item['done']),
        ]);
    }
}
