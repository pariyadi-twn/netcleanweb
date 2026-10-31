<?php

namespace App\Modules\Reporting\Actions;

use App\Modules\Identity\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OutstandingInvoices
{
    /**
     * @return array{invoice_count: int, receivables_rupiah: int, overdue_invoice_count: int, overdue_receivables_rupiah: int}
     */
    public function summary(Branch $branch, CarbonImmutable $asOf): array
    {
        $asOfUtc = $asOf->utc();
        $balance = $this->balanceExpression();

        $row = $this->base($branch, $asOf)
            ->selectRaw(
                "count(*) as invoice_count,
                 coalesce(sum({$balance}), 0) as receivables_rupiah,
                 coalesce(sum(case when i.due_at < ? then 1 else 0 end), 0) as overdue_invoice_count,
                 coalesce(sum(case when i.due_at < ? then {$balance} else 0 end), 0) as overdue_receivables_rupiah",
                [$asOfUtc, $asOfUtc],
            )
            ->first();

        return [
            'invoice_count' => (int) ($row?->invoice_count ?? 0),
            'receivables_rupiah' => (int) ($row?->receivables_rupiah ?? 0),
            'overdue_invoice_count' => (int) ($row?->overdue_invoice_count ?? 0),
            'overdue_receivables_rupiah' => (int) ($row?->overdue_receivables_rupiah ?? 0),
        ];
    }

    /**
     * @return CursorPaginator<int, object>
     */
    public function paginate(Branch $branch, CarbonImmutable $asOf, int $perPage, ?string $cursor): CursorPaginator
    {
        $balance = $this->balanceExpression();

        return $this->base($branch, $asOf)
            ->addSelect([
                'i.id as invoice_internal_id',
                'i.public_id as invoice_id',
                'i.invoice_number',
                'i.issued_at',
                'i.due_at',
                'i.original_total_rupiah',
                'o.order_number',
                'o.customer_snapshot',
            ])
            ->selectRaw("{$balance} as balance_due_rupiah")
            ->selectRaw($this->totalExpression().' as total_rupiah')
            ->orderBy('i.due_at')
            ->orderBy('i.id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    private function base(Branch $branch, CarbonImmutable $asOf): Builder
    {
        $balance = $this->balanceExpression();

        return DB::table('invoices as i')
            ->join('orders as o', function ($join): void {
                $join->on('o.id', '=', 'i.order_id')
                    ->on('o.tenant_id', '=', 'i.tenant_id');
            })
            ->where('i.tenant_id', $branch->tenant_id)
            ->where('i.sales_branch_id', $branch->id)
            ->where('i.document_status', 'issued')
            ->where('i.issued_at', '<=', $asOf->utc())
            ->whereRaw("{$balance} > 0");
    }

    private function totalExpression(): string
    {
        return '(i.original_total_rupiah
            + coalesce((select sum(ia.amount_rupiah) from invoice_adjustments ia where ia.tenant_id = i.tenant_id and ia.invoice_id = i.id), 0)
        )';
    }

    private function balanceExpression(): string
    {
        return '(i.original_total_rupiah
            + coalesce((select sum(ia.amount_rupiah) from invoice_adjustments ia where ia.tenant_id = i.tenant_id and ia.invoice_id = i.id), 0)
            - coalesce((select sum(pa.amount_rupiah) from payment_allocations pa where pa.tenant_id = i.tenant_id and pa.invoice_id = i.id), 0)
            + coalesce((select sum(ar.amount_rupiah) from allocation_reversals ar join payment_allocations apa on apa.id = ar.payment_allocation_id and apa.tenant_id = ar.tenant_id where apa.tenant_id = i.tenant_id and apa.invoice_id = i.id), 0)
            + coalesce((select sum(ra.amount_rupiah) from refund_allocations ra join refunds rf on rf.id = ra.refund_id and rf.tenant_id = ra.tenant_id join payment_allocations rpa on rpa.id = ra.payment_allocation_id and rpa.tenant_id = ra.tenant_id where rf.status = \'succeeded\' and rpa.tenant_id = i.tenant_id and rpa.invoice_id = i.id), 0)
        )';
    }
}
