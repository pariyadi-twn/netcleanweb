<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Catalog\Actions\CreatePriceBook;
use App\Modules\Catalog\Actions\CreatePriceEntry;
use App\Modules\Catalog\Actions\CreateService;
use App\Modules\Catalog\Actions\CreateWorkflowRevision;
use App\Modules\Catalog\Actions\SetServicePublication;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Enums\LocationType;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Operations\Actions\UpdateBranchCalendar;
use Illuminate\Support\Collection;

final class ProvisionStarterDefaults
{
    private const SERVICE_DEFINITIONS = [
        'kiloan' => ['name' => 'Cuci Kiloan', 'unit' => 'kg', 'minimum_quantity' => '3.000', 'quantity_step' => '0.100', 'unit_price_rupiah' => 8000],
        'bed-cover' => ['name' => 'Bed Cover', 'unit' => 'pcs', 'minimum_quantity' => '1.000', 'quantity_step' => '1.000', 'unit_price_rupiah' => 25000],
    ];

    private const STARTER_ROLES = [
        'kasir' => [
            'name' => 'Kasir',
            'permissions' => [
                PermissionCode::BranchesView, PermissionCode::CustomersView, PermissionCode::CustomersManage,
                PermissionCode::CatalogView, PermissionCode::OrdersView, PermissionCode::OrdersManage,
                PermissionCode::CashView, PermissionCode::CashManage, PermissionCode::PaymentsView,
                PermissionCode::PaymentsManage, PermissionCode::HandoversManage,
            ],
        ],
        'operator' => [
            'name' => 'Operator Produksi',
            'permissions' => [
                PermissionCode::BranchesView, PermissionCode::OrdersView, PermissionCode::ProductionView,
                PermissionCode::ProductionManage, PermissionCode::ProductionQc, PermissionCode::HandoversManage,
            ],
        ],
    ];

    public function __construct(
        private readonly CreateWorkflowRevision $workflows,
        private readonly CreateService $services,
        private readonly CreatePriceBook $priceBooks,
        private readonly CreatePriceEntry $priceEntries,
        private readonly SetServicePublication $publication,
        private readonly UpdateBranchCalendar $calendar,
        private readonly SyncRolePermissions $permissions,
    ) {}

    public function handle(Tenant $tenant, Branch $branch, User $owner): Location
    {
        $location = $this->location($tenant, $branch);
        $this->register($tenant, $branch);
        $this->roles($tenant, $owner);
        $workflow = $this->workflow($tenant);
        $services = $this->services($tenant, $workflow);
        $book = $this->priceBook($tenant);
        $this->prices($tenant, $book, $services);
        $this->publish($services, $owner);
        $this->calendar($branch, $owner);

        return $location;
    }

    private function location(Tenant $tenant, Branch $branch): Location
    {
        $location = Location::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'OUTLET')
            ->first();

        if ($location !== null) {
            return $location;
        }

        $location = new Location([
            'code' => 'OUTLET',
            'type' => LocationType::Outlet,
            'name' => 'Outlet Utama',
            'is_active' => true,
        ]);
        $location->tenant_id = $tenant->id;
        $location->branch_id = $branch->id;
        $location->save();

        return $location;
    }

    private function register(Tenant $tenant, Branch $branch): void
    {
        $exists = CashRegister::query()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('code', 'KASIR-1')
            ->exists();

        if ($exists) {
            return;
        }

        $register = new CashRegister([
            'branch_id' => $branch->id,
            'code' => 'KASIR-1',
            'is_active' => true,
        ]);
        $register->tenant_id = $tenant->id;
        $register->save();
    }

    private function roles(Tenant $tenant, User $owner): void
    {
        foreach (self::STARTER_ROLES as $code => $definition) {
            $role = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            if ($role === null) {
                $role = new Role([
                    'code' => $code,
                    'name' => $definition['name'],
                ]);
                $role->tenant_id = $tenant->id;
                $role->is_system = false;
                $role->save();
            }

            $permissionIds = Permission::query()
                ->whereIn('code', array_map(fn (PermissionCode $permission): string => $permission->value, $definition['permissions']))
                ->pluck('id')
                ->all();

            $this->permissions->handle($role, $permissionIds, $owner, 'Starter default');
        }
    }

    private function workflow(Tenant $tenant): WorkflowTemplate
    {
        $workflow = WorkflowTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderByDesc('revision')
            ->first();

        if ($workflow !== null) {
            return $workflow;
        }

        return $this->workflows->handle($tenant->id, [
            'code' => 'standar',
            'name' => 'Alur Cuci Standar',
            'steps' => [
                ['code' => 'cuci', 'is_required' => true, 'requires_qc' => false],
                ['code' => 'kering', 'is_required' => true, 'requires_qc' => false],
                ['code' => 'lipat', 'is_required' => true, 'requires_qc' => false],
            ],
        ]);
    }

    /**
     * @return Collection<string, Service>
     */
    private function services(Tenant $tenant, WorkflowTemplate $workflow): Collection
    {
        $services = collect();

        foreach (self::SERVICE_DEFINITIONS as $code => $definition) {
            $service = Service::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            if ($service === null) {
                $service = $this->services->handle($tenant->id, [
                    'code' => $code,
                    'name' => $definition['name'],
                    'unit' => $definition['unit'],
                    'minimum_quantity' => $definition['minimum_quantity'],
                    'quantity_step' => $definition['quantity_step'],
                    'workflow_template_id' => $workflow->public_id,
                ]);
            }

            $services->put($code, $service);
        }

        return $services;
    }

    private function priceBook(Tenant $tenant): PriceBook
    {
        $book = PriceBook::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('branch_id')
            ->whereNull('customer_id')
            ->where('is_active', true)
            ->first();

        if ($book !== null) {
            return $book;
        }

        return $this->priceBooks->handle($tenant->id, ['name' => 'Harga Umum']);
    }

    /**
     * @param  Collection<string, Service>  $services
     */
    private function prices(Tenant $tenant, PriceBook $book, Collection $services): void
    {
        foreach ($services as $code => $service) {
            $hasEntry = PriceEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('price_book_id', $book->id)
                ->where('service_id', $service->id)
                ->where('tier', PriceTier::Regular->value)
                ->exists();

            if ($hasEntry) {
                continue;
            }

            $this->priceEntries->handle($book, [
                'service_id' => $service->public_id,
                'tier' => PriceTier::Regular->value,
                'unit_price_rupiah' => (int) self::SERVICE_DEFINITIONS[$code]['unit_price_rupiah'],
                'effective_from' => now($tenant->timezone)->toDateString(),
            ]);
        }
    }

    /**
     * @param  Collection<string, Service>  $services
     */
    private function publish(Collection $services, User $owner): void
    {
        foreach ($services as $service) {
            if ($service->is_active) {
                continue;
            }

            $this->publication->handle($service, true, $owner, 'Starter default');
        }
    }

    private function calendar(Branch $branch, User $owner): void
    {
        if ($branch->calendars()->exists()) {
            return;
        }

        $this->calendar->handle($branch, [
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
        ], $owner, 'Starter default');
    }
}
