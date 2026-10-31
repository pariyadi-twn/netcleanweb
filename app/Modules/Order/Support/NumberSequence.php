<?php

namespace App\Modules\Order\Support;

use Illuminate\Support\Facades\DB;

final class NumberSequence
{
    public function next(int $tenantId, int $branchId, string $kind, string $localPeriod): int
    {
        $row = DB::selectOne(
            <<<'SQL'
                INSERT INTO number_counters (tenant_id, branch_id, counter_kind, local_period, last_value)
                VALUES (?, ?, ?, ?, 1)
                ON CONFLICT (tenant_id, branch_id, counter_kind, local_period)
                DO UPDATE SET last_value = number_counters.last_value + 1
                RETURNING last_value
            SQL,
            [$tenantId, $branchId, $kind, $localPeriod],
        );

        return (int) $row->last_value;
    }

    public function orderNumber(string $branchCode, string $localDate, int $sequence): string
    {
        return mb_strtoupper($branchCode).'-'.str_replace('-', '', $localDate).'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function invoiceNumber(string $branchCode, string $localDate, int $sequence): string
    {
        return 'INV-'.mb_strtoupper($branchCode).'-'.str_replace('-', '', $localDate).'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
