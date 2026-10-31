<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;

class CustomerPolicy
{
    public function __construct(private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->id === $this->context->userId
            && $customer->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CustomersView);
    }

    public function create(User $user): bool
    {
        return $user->id === $this->context->userId
            && $this->context->can(PermissionCode::CustomersManage);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->id === $this->context->userId
            && $customer->tenant_id === $this->context->tenantId
            && $this->context->can(PermissionCode::CustomersManage);
    }
}
