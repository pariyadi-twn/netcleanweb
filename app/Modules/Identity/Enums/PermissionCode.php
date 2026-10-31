<?php

namespace App\Modules\Identity\Enums;

enum PermissionCode: string
{
    case BranchesView = 'branches.view';
    case BranchesManage = 'branches.manage';
    case LocationsView = 'locations.view';
    case LocationsManage = 'locations.manage';
    case MembershipsView = 'memberships.view';
    case MembershipsManage = 'memberships.manage';
    case RolesManage = 'roles.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case DiscountsStandard = 'discounts.standard';
    case DiscountsOverride = 'discounts.override';
    case AuditView = 'audit.view';
}
