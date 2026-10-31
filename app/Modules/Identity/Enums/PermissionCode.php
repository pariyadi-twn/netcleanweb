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
    case AuditView = 'audit.view';
}
