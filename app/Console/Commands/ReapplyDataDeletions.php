<?php

namespace App\Console\Commands;

use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantScope;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Lifecycle\Actions\AnonymizeCustomer;
use App\Modules\Lifecycle\Models\DataDeletionTombstone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('data:reapply-deletions {--tenant=}')]
#[Description('Reapply valid customer deletion tombstones after a restore')]
class ReapplyDataDeletions extends Command
{
    public function handle(TenantScope $scope, AnonymizeCustomer $anonymize): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('tenant')) {
            $query->where('public_id', $this->option('tenant'));
        }

        $applied = 0;
        foreach ($query->get() as $tenant) {
            $applied += $scope->forTenantId($tenant->id, 0, function () use ($anonymize): int {
                $count = 0;
                foreach (DataDeletionTombstone::query()->where('subject_type', 'customer')->get() as $tombstone) {
                    $customer = Customer::query()->where('public_id', $tombstone->subject_public_id)->first();
                    if ($customer !== null) {
                        $anonymize->handle($customer);
                        $count++;
                    }
                }

                return $count;
            });
        }

        $this->info("Reapplied {$applied} deletion tombstone(s).");

        return self::SUCCESS;
    }
}
