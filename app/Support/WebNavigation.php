<?php

namespace App\Support;

use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use Illuminate\Support\Facades\Route;

final class WebNavigation
{
    /**
     * @return array<string, list<array{label: string, route: string, icon: string, active: string, permissions: list<PermissionCode>, any: bool}>>
     */
    public static function groups(TenantContext $context): array
    {
        $items = [
            [
                'group' => 'Operasional',
                'label' => 'Ringkasan',
                'route' => 'dashboard',
                'icon' => 'home',
                'active' => 'dashboard',
                'permissions' => [PermissionCode::ReportsView],
                'any' => false,
            ],
            [
                'group' => 'Operasional',
                'label' => 'Pesanan',
                'route' => 'orders.index',
                'icon' => 'receipt',
                'active' => 'orders.*',
                'permissions' => [PermissionCode::OrdersView],
                'any' => false,
            ],
            [
                'group' => 'Operasional',
                'label' => 'Produksi',
                'route' => 'production.index',
                'icon' => 'factory',
                'active' => 'production.*',
                'permissions' => [PermissionCode::ProductionView],
                'any' => false,
            ],
            [
                'group' => 'Bisnis',
                'label' => 'Keuangan',
                'route' => 'finance.index',
                'icon' => 'wallet',
                'active' => 'finance.*',
                'permissions' => [PermissionCode::CashView, PermissionCode::PaymentsView],
                'any' => true,
            ],
            [
                'group' => 'Bisnis',
                'label' => 'Laporan',
                'route' => 'reports.index',
                'icon' => 'chart',
                'active' => 'reports.*',
                'permissions' => [PermissionCode::ReportsView],
                'any' => false,
            ],
            [
                'group' => 'Bisnis',
                'label' => 'Pelanggan',
                'route' => 'customers.index',
                'icon' => 'users',
                'active' => 'customers.*',
                'permissions' => [PermissionCode::CustomersView],
                'any' => false,
            ],
            [
                'group' => 'Pengaturan',
                'label' => 'Pengaturan',
                'route' => 'settings.index',
                'icon' => 'settings',
                'active' => 'settings.*',
                'permissions' => [PermissionCode::MembershipsManage, PermissionCode::RolesManage, PermissionCode::BranchesView, PermissionCode::MembershipsView, PermissionCode::CatalogView],
                'any' => true,
            ],
        ];

        $groups = [];

        foreach ($items as $item) {
            if (! Route::has($item['route']) || ! self::allows($context, $item['permissions'], $item['any'])) {
                continue;
            }

            $groups[$item['group']][] = $item;
        }

        return $groups;
    }

    /**
     * @param  list<PermissionCode>  $permissions
     */
    private static function allows(TenantContext $context, array $permissions, bool $any): bool
    {
        foreach ($permissions as $permission) {
            if ($context->can($permission) === $any) {
                return $any;
            }
        }

        return ! $any;
    }
}
