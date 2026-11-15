@extends('layouts.app')

@section('title', 'Piutang - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Laporan" title="Piutang" :description="$branch->name.' per '.$asOf" />
    <form method="GET" action="{{ route('reports.receivables') }}" class="mb-5 grid gap-3 rounded-container border border-divider bg-white p-4 shadow-sm sm:grid-cols-3">
        <select name="branch_id" class="min-h-11 rounded-control border border-input px-3 text-sm">@foreach($branches as $option)<option value="{{ $option->public_id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>@endforeach</select>
        <input name="as_of" type="date" value="{{ $asOf }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
        <button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Tampilkan</button>
    </form>
    <div class="grid gap-4 sm:grid-cols-3"><x-ui.stat-card label="Invoice" :value="$summary['invoice_count']" /><x-ui.stat-card label="Total piutang" :value="'Rp'.number_format($summary['receivables_rupiah'], 0, ',', '.')" /><x-ui.stat-card label="Lewat jatuh tempo" :value="$summary['overdue_invoice_count']" /></div>
    <div class="mt-5 overflow-x-auto rounded-container border border-divider bg-white shadow-sm">
        <table class="w-full min-w-[40rem] text-sm"><caption class="sr-only">Daftar piutang {{ $branch->name }}</caption><thead class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-muted"><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Jatuh tempo</th><th class="px-4 py-3 text-right">Sisa</th></tr></thead><tbody class="divide-y divide-divider">@forelse($rows as $row)@php($customer = is_string($row->customer_snapshot) ? json_decode($row->customer_snapshot, true) : (array) $row->customer_snapshot)<tr><td class="px-4 py-3 font-semibold">{{ $row->invoice_number }}</td><td class="px-4 py-3">{{ $row->order_number }}</td><td class="px-4 py-3">{{ $customer['name'] ?? 'Walk-in' }}</td><td class="px-4 py-3 text-muted">{{ $row->due_at ? \Illuminate\Support\Carbon::parse($row->due_at)->format('d M Y') : '-' }}</td><td class="px-4 py-3 text-right font-bold">Rp{{ number_format($row->balance_due_rupiah, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-12 text-center text-muted">Tidak ada piutang.</td></tr>@endforelse</tbody></table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
@endsection
