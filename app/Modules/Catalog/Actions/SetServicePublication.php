<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Enums\PriceTier;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Audit\AuditRecorder;
use App\Modules\Identity\Enums\AuditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetServicePublication
{
    public function __construct(private readonly AuditRecorder $auditor) {}

    public function handle(Service $service, bool $isActive, ?User $actor = null, ?string $reason = null): Service
    {
        return DB::transaction(function () use ($service, $isActive, $actor, $reason): Service {
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();

            if ($isActive) {
                $this->ensureWorkflowIsActive($locked);
                $this->ensureTenantPriceIsEffective($locked);
            }

            $before = ['is_active' => $locked->is_active];
            $locked->forceFill([
                'is_active' => $isActive,
                'version' => $locked->version + 1,
            ])->save();

            $this->auditor->record(
                tenantId: $locked->tenant_id,
                action: AuditAction::ServicePublicationChanged,
                subjectType: AuditRecorder::SUBJECT_SERVICE,
                subjectPublicId: $locked->public_id,
                changes: [
                    'before' => $before,
                    'after' => ['is_active' => $locked->is_active],
                ],
                actor: $actor,
                reason: $reason,
            );

            return $locked->refresh()->load('workflowTemplate.steps');
        });
    }

    private function ensureWorkflowIsActive(Service $service): void
    {
        if (! $service->workflowTemplate()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'is_active' => ['The service must use an active workflow revision before publication.'],
            ]);
        }
    }

    private function ensureTenantPriceIsEffective(Service $service): void
    {
        $now = now();

        $hasPrice = PriceEntry::query()
            ->where('tenant_id', $service->tenant_id)
            ->where('service_id', $service->id)
            ->where('tier', PriceTier::Regular->value)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $now);
            })
            ->whereHas('priceBook', function ($query) use ($service): void {
                $query->where('tenant_id', $service->tenant_id)
                    ->where('is_active', true)
                    ->whereNull('branch_id')
                    ->whereNull('customer_id');
            })
            ->exists();

        if (! $hasPrice) {
            throw ValidationException::withMessages([
                'is_active' => ['An active tenant-scope regular price is required before publication.'],
            ]);
        }
    }
}
