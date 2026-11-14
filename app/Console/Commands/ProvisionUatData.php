<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Catalog\Actions\CreatePriceBook;
use App\Modules\Catalog\Actions\CreatePriceEntry;
use App\Modules\Catalog\Actions\CreateService;
use App\Modules\Catalog\Actions\CreateWorkflowRevision;
use App\Modules\Catalog\Actions\SetServicePublication;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Actions\ProvisionTenant;
use App\Modules\Identity\Actions\SyncRolePermissions;
use App\Modules\Identity\Actions\UpdateMembershipAssignments;
use App\Modules\Identity\Authorization\ProvisioningScope;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Enums\LocationType;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Operations\Actions\UpdateBranchCalendar;
use Database\Seeders\IdentityPermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('uat:provision {--owner-email=owner@uat.netclean.id} {--tenant-name=NetClean UAT}')]
#[Description('Provision idempotent synthetic tenant, locations, roles, and staff accounts for UAT')]
class ProvisionUatData extends Command
{
    public function handle(
        IdentityPermissionSeeder $permissionSeeder,
        ProvisioningScope $provisioningScope,
        ProvisionTenant $provisionTenant,
        TenantScope $tenantScope,
        SyncRolePermissions $syncPermissions,
        UpdateMembershipAssignments $updateAssignments,
        CreateWorkflowRevision $createWorkflow,
        CreateService $createService,
        CreatePriceBook $createPriceBook,
        CreatePriceEntry $createPriceEntry,
        SetServicePublication $setPublication,
        CreateCustomer $createCustomer,
        UpdateBranchCalendar $updateCalendar,
    ): int {
        if (app()->isProduction()) {
            $this->components->error('UAT provisioning is disabled in production.');

            return self::FAILURE;
        }

        $ownerEmail = mb_strtolower(trim((string) $this->option('owner-email')));
        $tenantName = trim((string) $this->option('tenant-name'));

        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) || $tenantName === '') {
            $this->components->error('Provide a valid owner email and a non-empty tenant name.');

            return self::FAILURE;
        }

        $permissionSeeder->run();

        [$owner, $ownerPassword] = $this->user('Pemilik UAT', $ownerEmail);
        $tenant = $this->findTenant($tenantScope, $owner, $tenantName);

        if ($tenant === null) {
            $result = $provisioningScope->run(
                $owner,
                fn (): array => $provisionTenant->handle($owner, [
                    'organization_name' => $tenantName,
                    'timezone' => 'Asia/Jakarta',
                    'branch_name' => 'Cabang UAT Utama',
                    'branch_code' => 'UAT-MAIN',
                    'branch_timezone' => 'Asia/Jakarta',
                    'branch_address' => 'Jl. Data Sintetis No. 51, Jakarta',
                    'branch_phone' => '080000000051',
                ], (string) Str::uuid()),
            );
            $tenant = $result['tenant'];
        }

        $credentials = array_values(array_filter([
            $this->credential('owner', $owner, $ownerPassword),
            $this->staffCredential('cashier', 'Kasir UAT', 'cashier@uat.netclean.id'),
            $this->staffCredential('production', 'Operator Produksi UAT', 'production@uat.netclean.id'),
            $this->staffCredential('supervisor', 'Supervisor UAT', 'supervisor@uat.netclean.id'),
        ]));

        $staff = [
            'cashier' => User::query()->where('email', 'cashier@uat.netclean.id')->sole(),
            'production' => User::query()->where('email', 'production@uat.netclean.id')->sole(),
            'supervisor' => User::query()->where('email', 'supervisor@uat.netclean.id')->sole(),
        ];

        $tenantScope->forTenantId($tenant->id, $owner->id, function () use ($owner, $staff, $syncPermissions, $tenant, $updateAssignments, $createWorkflow, $createService, $createPriceBook, $createPriceEntry, $setPublication, $createCustomer, $updateCalendar): void {
            $branch = Branch::query()->where('code', 'UAT-MAIN')->firstOrFail();
            $outlet = $this->location($tenant, $branch, 'OUTLET-MAIN', LocationType::Outlet, 'Outlet UAT Utama');
            $plant = $this->location($tenant, null, 'PLANT-MAIN', LocationType::Plant, 'Pusat Produksi UAT');

            if ($branch->calendars()->doesntExist()) {
                $updateCalendar->handle($branch, [
                    'weekly_hours' => [
                        ['weekday' => 0, 'is_open' => false, 'open_time' => null, 'close_time' => null],
                        ['weekday' => 1, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                        ['weekday' => 2, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                        ['weekday' => 3, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                        ['weekday' => 4, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                        ['weekday' => 5, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                        ['weekday' => 6, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00'],
                    ],
                    'cutoff_time' => '15:00',
                    'deadline_local_time' => '20:00',
                ], $owner, 'UAT provisioning');
            }

            $roles = [
                'cashier' => $this->role(
                    $tenant,
                    'cashier',
                    'Kasir',
                    [
                        PermissionCode::BranchesView,
                        PermissionCode::CustomersView,
                        PermissionCode::CustomersManage,
                        PermissionCode::CatalogView,
                        PermissionCode::OrdersView,
                        PermissionCode::OrdersManage,
                        PermissionCode::DiscountsStandard,
                        PermissionCode::CashView,
                        PermissionCode::CashManage,
                        PermissionCode::PaymentsView,
                        PermissionCode::PaymentsManage,
                        PermissionCode::HandoversManage,
                    ],
                    $owner,
                    $syncPermissions,
                ),
                'production' => $this->role(
                    $tenant,
                    'production',
                    'Operator Produksi',
                    [
                        PermissionCode::OrdersView,
                        PermissionCode::ProductionView,
                        PermissionCode::ProductionManage,
                        PermissionCode::ProductionQc,
                        PermissionCode::HandoversManage,
                        PermissionCode::ComplaintsManage,
                    ],
                    $owner,
                    $syncPermissions,
                ),
                'supervisor' => $this->role(
                    $tenant,
                    'supervisor',
                    'Supervisor',
                    array_values(array_filter(
                        PermissionCode::cases(),
                        fn (PermissionCode $permission): bool => $permission !== PermissionCode::SupportGrant,
                    )),
                    $owner,
                    $syncPermissions,
                ),
            ];

            $ownerMembership = TenantMembership::query()->where('user_id', $owner->id)->firstOrFail();
            $ownerRole = Role::query()->where('code', RoleCode::Owner->value)->firstOrFail();
            $this->syncRole($ownerRole, Permission::query()->pluck('id')->all(), $owner, $syncPermissions);
            $this->membership($ownerMembership, $ownerRole, [$branch->id], [$outlet->id, $plant->id], $owner, $updateAssignments);

            foreach ($staff as $code => $user) {
                $membership = TenantMembership::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->first();
                if ($membership === null) {
                    $membership = new TenantMembership;
                    $membership->forceFill([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                        'status' => MembershipStatus::Active,
                        'permission_version' => 1,
                        'invited_by_user_id' => $owner->id,
                    ])->save();
                } elseif ($membership->status !== MembershipStatus::Active) {
                    $membership->forceFill(['status' => MembershipStatus::Active])->save();
                }

                $locationIds = $code === 'production' ? [$plant->id] : [$outlet->id, $plant->id];
                $this->membership($membership, $roles[$code], [$branch->id], $locationIds, $owner, $updateAssignments);
            }

            $this->catalog(
                $tenant,
                $owner,
                $createWorkflow,
                $createService,
                $createPriceBook,
                $createPriceEntry,
                $setPublication,
                $createCustomer,
            );
        });

        $this->components->info('Synthetic UAT tenant, catalog, and staff are ready. Existing records were preserved.');
        $this->line('Tenant ID: '.$tenant->public_id);

        if ($credentials !== []) {
            $this->newLine();
            $this->components->warn('Store these generated passwords now. They will not be shown on rerun.');
            $this->table(['Role', 'Email', 'Password'], $credentials);
        }

        return self::SUCCESS;
    }

    private function catalog(
        Tenant $tenant,
        User $owner,
        CreateWorkflowRevision $createWorkflow,
        CreateService $createService,
        CreatePriceBook $createPriceBook,
        CreatePriceEntry $createPriceEntry,
        SetServicePublication $setPublication,
        CreateCustomer $createCustomer,
    ): void {
        $workflow = WorkflowTemplate::query()
            ->where('code', 'standard-uat')
            ->where('is_active', true)
            ->first()
            ?? $createWorkflow->handle($tenant->id, [
                'code' => 'standard-uat',
                'name' => 'Alur Produksi UAT',
                'steps' => [
                    ['code' => 'washing', 'is_required' => true, 'requires_qc' => false],
                    ['code' => 'drying', 'is_required' => true, 'requires_qc' => false],
                    ['code' => 'ironing', 'is_required' => true, 'requires_qc' => false],
                    ['code' => 'qc', 'is_required' => true, 'requires_qc' => true],
                ],
            ]);

        $book = PriceBook::query()
            ->whereNull('branch_id')
            ->whereNull('customer_id')
            ->where('is_active', true)
            ->first()
            ?? $createPriceBook->handle($tenant->id, [
                'name' => 'Harga UAT Tenant',
            ]);

        $definitions = [
            [
                'code' => 'uat-kiloan',
                'name' => 'Kiloan UAT',
                'unit' => 'kg',
                'minimum_quantity' => '3.000',
                'quantity_step' => '0.100',
                'unit_price_rupiah' => 8000,
            ],
            [
                'code' => 'uat-bedcover',
                'name' => 'Bed Cover UAT',
                'unit' => 'pcs',
                'minimum_quantity' => '1',
                'quantity_step' => '1',
                'unit_price_rupiah' => 25000,
            ],
        ];

        foreach ($definitions as $definition) {
            $service = Service::query()
                ->where('code', $definition['code'])
                ->first()
                ?? $createService->handle($tenant->id, [
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'unit' => $definition['unit'],
                    'minimum_quantity' => $definition['minimum_quantity'],
                    'quantity_step' => $definition['quantity_step'],
                    'workflow_template_id' => $workflow->public_id,
                ]);

            $hasPrice = PriceEntry::query()
                ->where('price_book_id', $book->id)
                ->where('service_id', $service->id)
                ->where('tier', 'regular')
                ->exists();

            if (! $hasPrice) {
                $createPriceEntry->handle($book, [
                    'service_id' => $service->public_id,
                    'tier' => 'regular',
                    'unit_price_rupiah' => $definition['unit_price_rupiah'],
                    'effective_from' => now()->toDateString(),
                ]);
            }

            if (! $service->is_active) {
                $setPublication->handle($service, true, $owner, 'UAT provisioning');
            }
        }

        if (! Customer::query()->where('name', 'Pelanggan UAT')->exists()) {
            $createCustomer->handle($tenant->id, [
                'name' => 'Pelanggan UAT',
                'phone' => '081200000051',
                'addresses' => [
                    [
                        'label' => 'Utama',
                        'address' => 'Jl. Data Sintetis No. 51, Jakarta',
                        'is_default' => true,
                    ],
                ],
            ], $owner);
        }
    }

    private function findTenant(TenantScope $scope, User $owner, string $name): ?Tenant
    {
        return $scope->identity(
            $owner,
            fn (): ?Tenant => Tenant::query()
                ->where('name', $name)
                ->whereHas('memberships', fn ($query) => $query
                    ->where('user_id', $owner->id)
                    ->where('status', MembershipStatus::Active->value))
                ->first(),
        );
    }

    /** @return array{User, ?string} */
    private function user(string $name, string $email): array
    {
        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null) {
            return [$existing, null];
        }

        $password = Str::random(32);
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
            'is_platform_admin' => false,
        ])->save();

        return [$user, $password];
    }

    /** @return array{string, string, string}|null */
    private function staffCredential(string $role, string $name, string $email): ?array
    {
        [$user, $password] = $this->user($name, $email);

        return $this->credential($role, $user, $password);
    }

    /** @return array{string, string, string}|null */
    private function credential(string $role, User $user, ?string $password): ?array
    {
        return $password === null ? null : [$role, $user->email, $password];
    }

    private function location(
        Tenant $tenant,
        ?Branch $branch,
        string $code,
        LocationType $type,
        string $name,
    ): Location {
        $location = Location::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();
        if ($location === null) {
            $location = new Location;
            $location->tenant_id = $tenant->id;
            $location->code = $code;
        }
        $location->fill([
            'type' => $type,
            'name' => $name,
            'is_active' => true,
        ]);
        $location->branch_id = $branch?->id;
        $location->parent_location_id = null;
        $location->save();

        return $location;
    }

    /** @param list<PermissionCode> $permissions */
    private function role(
        Tenant $tenant,
        string $code,
        string $name,
        array $permissions,
        User $owner,
        SyncRolePermissions $syncPermissions,
    ): Role {
        $role = Role::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();
        if ($role === null) {
            $role = new Role(['code' => $code]);
            $role->tenant_id = $tenant->id;
        }
        $role->fill(['name' => $name]);
        $role->is_system = true;
        $role->save();

        $permissionIds = Permission::query()
            ->whereIn('code', array_map(fn (PermissionCode $permission): string => $permission->value, $permissions))
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $this->syncRole($role, $permissionIds, $owner, $syncPermissions);

        return $role;
    }

    /** @param list<int> $permissionIds */
    private function syncRole(Role $role, array $permissionIds, User $owner, SyncRolePermissions $syncPermissions): void
    {
        $current = $role->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        sort($permissionIds);

        if ($current !== $permissionIds) {
            $syncPermissions->handle($role, $permissionIds, $owner, 'UAT provisioning');
        }
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $locationIds
     */
    private function membership(
        TenantMembership $membership,
        Role $role,
        array $branchIds,
        array $locationIds,
        User $owner,
        UpdateMembershipAssignments $updateAssignments,
    ): void {
        $roleIds = [$role->id];
        $currentRoles = $membership->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        $currentBranches = $membership->branches()->orderBy('branches.id')->pluck('branches.id')->all();
        $currentLocations = $membership->locations()->orderBy('locations.id')->pluck('locations.id')->all();
        sort($branchIds);
        sort($locationIds);

        if ($currentRoles !== $roleIds || $currentBranches !== $branchIds || $currentLocations !== $locationIds) {
            $updateAssignments->handle($membership, $roleIds, $branchIds, $locationIds, $owner, 'UAT provisioning');
        }
    }
}
