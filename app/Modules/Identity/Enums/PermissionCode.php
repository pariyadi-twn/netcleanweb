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
    case CashView = 'cash.view';
    case CashManage = 'cash.manage';
    case CashReopen = 'cash.reopen';
    case PaymentsView = 'payments.view';
    case PaymentsManage = 'payments.manage';
    case CreditManage = 'credit.manage';
    case RefundsRequest = 'refunds.request';
    case RefundsApprove = 'refunds.approve';
    case ExpensesManage = 'expenses.manage';
    case ExpensesApprove = 'expenses.approve';
    case CancellationsApprove = 'cancellations.approve';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ImportsManage = 'imports.manage';
    case SupportGrant = 'support.grant';
    case AuditView = 'audit.view';
}
