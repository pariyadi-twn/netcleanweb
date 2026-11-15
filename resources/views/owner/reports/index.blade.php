@extends('layouts.app')

@section('title', 'Laporan - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Bisnis" title="Laporan" description="Lihat performa outlet, piutang, dan ekspor laporan." />

    <div class="grid gap-4 lg:grid-cols-2">
        <form method="GET" action="{{ route('reports.outlet') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <h2 class="font-extrabold">Laporan outlet</h2>
            <p class="mt-1 text-sm text-muted">Penjualan, penerimaan, refund, dan piutang per hari.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <select name="branch_id" class="min-h-11 rounded-control border border-input px-3 text-sm">@foreach($branches as $branch)<option value="{{ $branch->public_id }}">{{ $branch->name }}</option>@endforeach</select>
                <input name="from" type="date" required value="{{ now()->subDays(6)->toDateString() }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
                <input name="to" type="date" required value="{{ now()->toDateString() }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
            </div>
            <button class="mt-3 min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Buka laporan</button>
        </form>
        <form method="GET" action="{{ route('reports.receivables') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <h2 class="font-extrabold">Piutang</h2>
            <p class="mt-1 text-sm text-muted">Invoice dengan saldo tersisa pada tanggal tertentu.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <select name="branch_id" class="min-h-11 rounded-control border border-input px-3 text-sm">@foreach($branches as $branch)<option value="{{ $branch->public_id }}">{{ $branch->name }}</option>@endforeach</select>
                <input name="as_of" type="date" value="{{ now()->toDateString() }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
            </div>
            <button class="mt-3 min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Buka piutang</button>
        </form>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('reports.outlet') }}" class="inline-flex min-h-11 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted hover:bg-slate-50">Buka laporan outlet</a>
        <a href="{{ route('reports.receivables') }}" class="inline-flex min-h-11 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted hover:bg-slate-50">Buka piutang</a>
        @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::ImportsManage))
            <a href="{{ route('reports.imports') }}" class="inline-flex min-h-11 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted hover:bg-slate-50">Impor data</a>
        @endif
    </div>

    <section class="mt-6 rounded-container border border-divider bg-white p-5 shadow-sm">
        <h2 class="font-extrabold">Ekspor terbaru</h2>
        <div class="mt-3 divide-y divide-divider">
            @forelse($exports as $export)
                <a href="{{ route('reports.exports.show', $export) }}" class="flex flex-wrap justify-between gap-2 py-2 text-sm"><span>{{ $export->filters['branch_id'] ?? '-' }} · {{ $export->filters['from'] ?? '-' }}—{{ $export->filters['to'] ?? '-' }}</span><span class="text-muted">{{ $export->status->value }}</span></a>
            @empty
                <p class="py-3 text-sm text-muted">Belum ada ekspor.</p>
            @endforelse
        </div>
    </section>
@endsection
