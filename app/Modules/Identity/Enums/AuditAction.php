<?php

namespace App\Modules\Identity\Enums;

enum AuditAction: string
{
    case MembershipAssignmentsUpdated = 'membership.assignments_updated';
    case RolePermissionsUpdated = 'role.permissions_updated';
    case BranchConfigurationUpdated = 'branch.configuration_updated';
    case OutboxEventApplied = 'outbox.event_applied';
}
