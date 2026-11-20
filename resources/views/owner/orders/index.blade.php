@extends('layouts.app')

@section('title', 'Pesanan - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Operasional" title="Pesanan" description="Cari dan pantau order dari cabang dalam scope akses Anda.">
        <x-slot:actions><a href="{{ route('orders.create') }}" class="inline-flex min-h-12 items-center rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Pesanan baru</a></x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('orders.index') }}" class="mb-5 grid gap-3 rounded-container border border-divider bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <label class="grid gap-1 text-xs font-bold text-muted">Cari
            <input name="q" value="{{ request('q') }}" placeholder="Nomor nota atau pelanggan" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
        </label>
        <label class="grid gap-1 text-xs font-bold text-muted">Status
            <select name="status" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status['value'] }}" @selected(request('status') === $status['value'])>{{ $status['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-xs font-bold text-muted">Cabang
            <select name="branch" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal text-ink outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                <option value="">Semua cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->public_id }}" @selected(request('branch') === $branch->public_id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="min-h-12 flex-1 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Terapkan</button>
            <a href="{{ route('orders.index') }}" class="inline-flex min-h-12 items-center rounded-control border border-divider px-4 text-sm font-bold text-muted hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-container border border-divider bg-white shadow-sm">
        <div class="hidden grid-cols-[1.2fr_1fr_0.8fr_0.8fr_0.8fr] gap-4 border-b border-divider bg-slate-50 px-5 py-3 text-[10px] font-bold uppercase tracking-[0.14em] text-muted lg:grid">
            <span>Pesanan</span><span>Pelanggan</span><span>Status</span><span>Estimasi</span><span class="text-right">Sisa</span>
        </div>
        <div class="divide-y divide-divider">
            @forelse ($orders as $order)
                @php($balance = $order->invoice?->totalRupiah() - $order->invoice?->netAllocatedRupiah())
                <a href="{{ route('orders.show', $order) }}" class="grid gap-3 px-5 py-4 transition hover:bg-brand-50/50 lg:grid-cols-[1.2fr_1fr_0.8fr_0.8fr_0.8fr] lg:items-center lg:gap-4">
                    <div>
                        <p class="text-sm font-extrabold text-ink">{{ $order->order_number }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $order->salesBranch?->name }}</p>
                    </div>
                    <p class="text-sm text-muted">{{ $order->customer?->name ?? ($order->customer_snapshot['name'] ?? 'Walk-in') }}</p>
                    <div><span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold text-brand-700">{{ str($order->operational_status->value)->replace('_', ' ')->title() }}</span></div>
                    <p class="text-xs text-muted">{{ $order->due_at_current?->format('d M Y H:i') }}</p>
                    <p class="text-sm font-bold text-ink lg:text-right">Rp{{ number_format((int) $balance, 0, ',', '.') }}</p>
                </a>
            @empty
                <div class="px-5 py-14 text-center text-sm text-muted">Belum ada pesanan yang cocok.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-5">{{ $orders->links() }}</div>
@endsection
