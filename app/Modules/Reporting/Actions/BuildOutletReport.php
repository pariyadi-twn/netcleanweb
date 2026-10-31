<?php

namespace App\Modules\Reporting\Actions;

use App\Modules\Identity\Models\Branch;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BuildOutletReport
{
    public function __construct(private readonly OutstandingInvoices $outstandingInvoices) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $fromUtc = $from->utc();
        $toUtc = $to->endOfDay()->utc();

        $daily = $this->emptyDaily($from, $to);
        $daily = $this->mergeInvoices($daily, $branch, $fromUtc, $toUtc, $timezone);
        $daily = $this->mergeAdjustments($daily, $branch, $fromUtc, $toUtc, $timezone);
        $daily = $this->mergePayments($daily, $branch, $fromUtc, $toUtc, $timezone);
        $daily = $this->mergeRefunds($daily, $branch, $fromUtc, $toUtc, $timezone);

        foreach ($daily as &$day) {
            $day['sales_net_rupiah'] = (int) $day['invoice_original_rupiah'] + (int) $day['adjustment_rupiah'];
        }
        unset($day);

        $totals = $this->totals($daily);
        $receivables = $this->outstandingInvoices->summary($branch, $to->endOfDay());
        $pendingStatuses = [
            OrderStatus::Accepted->value,
            OrderStatus::InProgress->value,
            OrderStatus::PartiallyReady->value,
            OrderStatus::Ready->value,
        ];

        $pendingQuery = Order::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('sales_branch_id', $branch->id)
            ->whereIn('operational_status', $pendingStatuses);

        return [
            'report_type' => 'outlet',
            'metric_version' => 1,
            'branch' => [
                'id' => $branch->public_id,
                'code' => $branch->code,
                'name' => $branch->name,
            ],
            'timezone' => $timezone,
            'date_basis' => [
                'sales' => 'invoice.issued_at',
                'adjustments' => 'invoice_adjustment.created_at',
                'receipts' => 'payment.received_at',
                'refunds' => 'refund.succeeded_at',
                'receivables' => 'invoice net balance as of period end',
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'watermark' => $this->watermark($branch, $fromUtc, $toUtc)?->utc()->format('Y-m-d\TH:i:s\Z'),
            'totals' => [
                ...$totals,
                'sales_net_rupiah' => $totals['invoice_original_rupiah'] + $totals['adjustment_rupiah'],
                'receivables_rupiah' => $receivables['receivables_rupiah'],
                'receivable_invoice_count' => $receivables['invoice_count'],
                'overdue_receivables_rupiah' => $receivables['overdue_receivables_rupiah'],
                'overdue_receivable_invoice_count' => $receivables['overdue_invoice_count'],
                'pending_orders_current' => $pendingQuery->clone()->count(),
                'overdue_pending_orders_current' => $pendingQuery->clone()
                    ->where('due_at_current', '<', now())
                    ->count(),
                'on_time_completion' => [
                    'available' => false,
                    'eligible_count' => 0,
                    'on_time_count' => 0,
                    'late_count' => 0,
                    'rate' => null,
                    'reason' => 'Completion timestamps are introduced by handover T037.',
                ],
            ],
            'receivables_as_of' => $to->toDateString(),
            'daily' => array_values($daily),
        ];
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function emptyDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to->startOfDay()); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[$key] = [
                'business_date' => $key,
                'sales_net_rupiah' => 0,
                'invoice_original_rupiah' => 0,
                'adjustment_rupiah' => 0,
                'invoice_count' => 0,
                'adjustment_count' => 0,
                'receipts_rupiah' => 0,
                'cash_receipts_rupiah' => 0,
                'bank_receipts_rupiah' => 0,
                'payment_count' => 0,
                'refund_rupiah' => 0,
                'refund_count' => 0,
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, array<string, int|string>>  $daily
     * @return array<string, array<string, int|string>>
     */
    private function mergeInvoices(array $daily, Branch $branch, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $rows = DB::table('invoices')
            ->where('tenant_id', $branch->tenant_id)
            ->where('sales_branch_id', $branch->id)
            ->where('document_status', 'issued')
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw("to_char(issued_at AT TIME ZONE ?, 'YYYY-MM-DD') as business_date, count(*) as invoice_count, coalesce(sum(original_total_rupiah), 0) as amount", [$timezone])
            ->groupByRaw('1')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->business_date;
            if (isset($daily[$key])) {
                $daily[$key]['invoice_count'] = (int) $row->invoice_count;
                $daily[$key]['invoice_original_rupiah'] = (int) $row->amount;
            }
        }

        return $daily;
    }

    /**
     * @param  array<string, array<string, int|string>>  $daily
     * @return array<string, array<string, int|string>>
     */
    private function mergeAdjustments(array $daily, Branch $branch, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $rows = DB::table('invoice_adjustments as a')
            ->join('invoices as i', function ($join): void {
                $join->on('i.id', '=', 'a.invoice_id')
                    ->on('i.tenant_id', '=', 'a.tenant_id');
            })
            ->where('a.tenant_id', $branch->tenant_id)
            ->where('i.sales_branch_id', $branch->id)
            ->whereBetween('a.created_at', [$from, $to])
            ->selectRaw("to_char(a.created_at AT TIME ZONE ?, 'YYYY-MM-DD') as business_date, count(*) as adjustment_count, coalesce(sum(a.amount_rupiah), 0) as amount", [$timezone])
            ->groupByRaw('1')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->business_date;
            if (isset($daily[$key])) {
                $daily[$key]['adjustment_count'] = (int) $row->adjustment_count;
                $daily[$key]['adjustment_rupiah'] = (int) $row->amount;
            }
        }

        return $daily;
    }

    /**
     * @param  array<string, array<string, int|string>>  $daily
     * @return array<string, array<string, int|string>>
     */
    private function mergePayments(array $daily, Branch $branch, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $rows = DB::table('payments')
            ->where('tenant_id', $branch->tenant_id)
            ->where('received_branch_id', $branch->id)
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw(
                "to_char(received_at AT TIME ZONE ?, 'YYYY-MM-DD') as business_date,
                 count(*) as payment_count,
                 coalesce(sum(amount_rupiah), 0) as amount,
                 coalesce(sum(case when method = 'cash' then amount_rupiah else 0 end), 0) as cash_amount,
                 coalesce(sum(case when method = 'bank_manual' then amount_rupiah else 0 end), 0) as bank_amount",
                [$timezone],
            )
            ->groupByRaw('1')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->business_date;
            if (isset($daily[$key])) {
                $daily[$key]['payment_count'] = (int) $row->payment_count;
                $daily[$key]['receipts_rupiah'] = (int) $row->amount;
                $daily[$key]['cash_receipts_rupiah'] = (int) $row->cash_amount;
                $daily[$key]['bank_receipts_rupiah'] = (int) $row->bank_amount;
            }
        }

        return $daily;
    }

    /**
     * @param  array<string, array<string, int|string>>  $daily
     * @return array<string, array<string, int|string>>
     */
    private function mergeRefunds(array $daily, Branch $branch, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $rows = DB::table('refunds as r')
            ->join('payments as p', function ($join): void {
                $join->on('p.id', '=', 'r.payment_id')
                    ->on('p.tenant_id', '=', 'r.tenant_id');
            })
            ->where('r.tenant_id', $branch->tenant_id)
            ->where('p.received_branch_id', $branch->id)
            ->where('r.status', 'succeeded')
            ->whereBetween('r.succeeded_at', [$from, $to])
            ->selectRaw("to_char(r.succeeded_at AT TIME ZONE ?, 'YYYY-MM-DD') as business_date, count(*) as refund_count, coalesce(sum(r.amount_rupiah), 0) as amount", [$timezone])
            ->groupByRaw('1')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->business_date;
            if (isset($daily[$key])) {
                $daily[$key]['refund_count'] = (int) $row->refund_count;
                $daily[$key]['refund_rupiah'] = (int) $row->amount;
            }
        }

        return $daily;
    }

    /**
     * @param  array<string, array<string, int|string>>  $daily
     * @return array<string, int>
     */
    private function totals(array $daily): array
    {
        $totals = [
            'invoice_original_rupiah' => 0,
            'adjustment_rupiah' => 0,
            'invoice_count' => 0,
            'adjustment_count' => 0,
            'receipts_rupiah' => 0,
            'cash_receipts_rupiah' => 0,
            'bank_receipts_rupiah' => 0,
            'payment_count' => 0,
            'refund_rupiah' => 0,
            'refund_count' => 0,
        ];

        foreach ($daily as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (int) $row[$key];
            }
        }

        return $totals;
    }

    private function watermark(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): ?CarbonImmutable
    {
        $waters = [
            DB::table('invoices')
                ->where('tenant_id', $branch->tenant_id)
                ->where('sales_branch_id', $branch->id)
                ->whereBetween('issued_at', [$from, $to])
                ->max('updated_at'),
            DB::table('invoice_adjustments as a')
                ->join('invoices as i', function ($join): void {
                    $join->on('i.id', '=', 'a.invoice_id')
                        ->on('i.tenant_id', '=', 'a.tenant_id');
                })
                ->where('a.tenant_id', $branch->tenant_id)
                ->where('i.sales_branch_id', $branch->id)
                ->whereBetween('a.created_at', [$from, $to])
                ->max('a.created_at'),
            DB::table('payments')
                ->where('tenant_id', $branch->tenant_id)
                ->where('received_branch_id', $branch->id)
                ->whereBetween('received_at', [$from, $to])
                ->max('created_at'),
            DB::table('refunds as r')
                ->join('payments as p', function ($join): void {
                    $join->on('p.id', '=', 'r.payment_id')
                        ->on('p.tenant_id', '=', 'r.tenant_id');
                })
                ->where('r.tenant_id', $branch->tenant_id)
                ->where('p.received_branch_id', $branch->id)
                ->where('r.status', 'succeeded')
                ->whereBetween('r.succeeded_at', [$from, $to])
                ->max('r.updated_at'),
            DB::table('orders')
                ->where('tenant_id', $branch->tenant_id)
                ->where('sales_branch_id', $branch->id)
                ->whereBetween('created_at', [$from, $to])
                ->max('updated_at'),
        ];

        $dates = collect($waters)
            ->filter()
            ->map(fn ($value): CarbonImmutable => CarbonImmutable::parse((string) $value))
            ->sort()
            ->values();

        return $dates->last();
    }
}
