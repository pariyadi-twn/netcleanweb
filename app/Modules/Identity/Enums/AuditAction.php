<?php

namespace App\Modules\Identity\Enums;

enum AuditAction: string
{
    case MembershipAssignmentsUpdated = 'membership.assignments_updated';
    case RolePermissionsUpdated = 'role.permissions_updated';
    case BranchConfigurationUpdated = 'branch.configuration_updated';
    case BranchCalendarUpdated = 'branch.calendar_updated';
    case ServicePublicationChanged = 'service.publication_changed';
    case DeadlinePromiseRecorded = 'deadline.promise_recorded';
    case DeadlineRevised = 'deadline.revised';
    case OrderAccepted = 'order.accepted';
    case DiscountApprovalDecided = 'discount.approval_decided';
    case OrderRevised = 'order.revised';
    case OrderRevisionDecided = 'order.revision_decided';
    case PaymentRecorded = 'payment.recorded';
    case CreditApproved = 'credit.approved';
    case RefundRequested = 'refund.requested';
    case RefundDecided = 'refund.decided';
    case RefundStatusChanged = 'refund.status_changed';
    case AllocationReversed = 'allocation.reversed';
    case ExpenseCreated = 'expense.created';
    case ExpenseDecided = 'expense.decided';
    case CashTransferConfirmed = 'cash_transfer.confirmed';
    case OrderCancelled = 'order.cancelled';
    case ImportCompleted = 'import.completed';
    case ExportCompleted = 'export.completed';
    case BagRepacked = 'bag.repacked';
    case OutboxEventApplied = 'outbox.event_applied';
}
