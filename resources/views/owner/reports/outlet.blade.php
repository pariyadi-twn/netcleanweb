@extends('layouts.app')

@section('title', 'Laporan outlet - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Laporan" title="Outlet" :description="$branch->name.' · '.$from.' sampai '.$to" />
    <form method="GET" action="{{ route('reports.outlet') }}" class="mb-5 grid gap-3 rounded-container border border-divider bg-white p-4 shadow-sm sm:grid-cols-4">
        <select name="branch_id" class="min-h-11 rounded-control border border-input px-3 text-sm">@foreach($branches as $option)<option value="{{ $option->public_id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>@endforeach</select>
        <input name="from" type="date" value="{{ $from }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
        <input name="to" type="date" value="{{ $to }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
        <button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Tampilkan</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Penjualan net" :value="'Rp'.number_format($report['totals']['sales_net_rupiah'], 0, ',', '.')" />
        <x-ui.stat-card label="Penerimaan" :value="'Rp'.number_format($report['totals']['receipts_rupiah'], 0, ',', '.')" />
        <x-ui.stat-card label="Piutang" :value="'Rp'.number_format($report['totals']['receivables_rupiah'], 0, ',', '.')" />
        <x-ui.stat-card label="Refund" :value="'Rp'.number_format($report['totals']['refund_rupiah'], 0, ',', '.')" />
    </div>

    <div class="mt-5 overflow-x-auto rounded-container border border-divider bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-sm">
            <caption class="sr-only">Laporan harian outlet {{ $branch->name }}</caption>
            <thead class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-muted"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3 text-right">Penjualan</th><th class="px-4 py-3 text-right">Penerimaan</th><th class="px-4 py-3 text-right">Tunai</th><th class="px-4 py-3 text-right">Transfer</th><th class="px-4 py-3 text-right">Refund</th></tr></thead>
            <tbody class="divide-y divide-divider">
                @foreach($report['daily'] as $day)
                    <tr><td class="px-4 py-3 font-semibold">{{ \Illuminate\Support\Carbon::parse($day['business_date'])->format('d M Y') }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($day['sales_net_rupiah'], 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($day['receipts_rupiah'], 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($day['cash_receipts_rupiah'], 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($day['bank_receipts_rupiah'], 0, ',', '.') }}</td><td class="px-4 py-3 text-right">Rp{{ number_format($day['refund_rupiah'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-muted">Zona waktu {{ $report['timezone'] }} · basis tanggal {{ $report['date_basis']['sales'] }} · watermark {{ $report['watermark'] ?? '-' }}</p>

    @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::ReportsExport))
        <form method="POST" action="{{ route('reports.exports.store') }}" class="mt-5 flex flex-wrap items-end gap-2 rounded-container border border-divider bg-white p-4 shadow-sm">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->public_id }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
            <p class="flex-1 text-sm text-muted">Ekspor CSV untuk periode ini.</p><button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Minta ekspor</button>
        </form>
    @endif
@endsection
