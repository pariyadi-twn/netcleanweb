@extends('layouts.app')

@section('title', 'Nota '.$receipt['order_number'].' - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Nota digital" :title="$receipt['order_number']" :description="$receipt['branch']['name'] ?? 'NetClean'" />
    <div class="mx-auto max-w-2xl rounded-container border border-divider bg-white p-6 shadow-sm sm:p-8">
        <div class="flex items-start justify-between gap-4 border-b border-dashed border-divider pb-5">
            <div><p class="text-lg font-extrabold">NetClean</p><p class="mt-1 text-xs text-muted">{{ $receipt['branch']['address'] ?? '' }}</p></div>
            <p class="text-right text-xs text-muted">{{ $receipt['accepted_at'] ? \Illuminate\Support\Carbon::parse($receipt['accepted_at'])->format('d M Y H:i') : '' }}</p>
        </div>
        <div class="grid gap-3 py-5 text-sm sm:grid-cols-2">
            <p><span class="block text-xs text-muted">Pelanggan</span><span class="font-semibold">{{ $receipt['customer']['name'] ?? 'Walk-in' }}</span></p>
            <p class="sm:text-right"><span class="block text-xs text-muted">Invoice</span><span class="font-semibold">{{ $receipt['invoice_number'] ?? '-' }}</span></p>
        </div>
        <div class="space-y-3 border-y border-dashed border-divider py-5 text-sm">
            @foreach ($receipt['lines'] as $line)
                <div class="flex justify-between gap-4"><span>{{ $line['service_name'] }} · {{ $line['billable_quantity'] }} {{ $line['unit'] }}</span><span class="font-semibold">Rp{{ number_format($line['line_total_rupiah'], 0, ',', '.') }}</span></div>
            @endforeach
        </div>
        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Total</dt><dd class="font-bold">Rp{{ number_format($receipt['totals']['total_rupiah'], 0, ',', '.') }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Dibayar</dt><dd class="font-bold">Rp{{ number_format($receipt['totals']['paid_rupiah'], 0, ',', '.') }}</dd></div>
            <div class="flex justify-between text-base"><dt class="font-bold">Sisa</dt><dd class="font-extrabold">Rp{{ number_format($receipt['totals']['balance_due_rupiah'], 0, ',', '.') }}</dd></div>
        </dl>
        <div class="mt-7 flex gap-2"><a href="{{ route('orders.show', $order) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Kembali ke pesanan</a><button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center rounded-control border border-divider px-4 text-sm font-bold text-muted hover:bg-slate-50">Cetak</button></div>
    </div>
@endsection
