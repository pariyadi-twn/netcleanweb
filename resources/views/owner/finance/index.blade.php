@extends('layouts.app')

@section('title', 'Keuangan - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Bisnis" title="Keuangan" description="Ringkasan kas, pembayaran, biaya, dan refund dalam scope cabang." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($canCash)<x-ui.stat-card label="Shift kas" :value="$sessions->where('status.value', 'open')->count()" description="Shift terbuka saat ini." />@endif
        @if ($canPayments)<x-ui.stat-card label="Pembayaran terbaru" :value="$payments->count()" description="Transaksi terakhir di cabang." />@endif
        @if ($canExpenses)<x-ui.stat-card label="Biaya terbaru" :value="$expenses->count()" description="Draft dan keputusan biaya." />@endif
        @if ($canRefunds)<x-ui.stat-card label="Refund terbaru" :value="$refunds->count()" description="Permintaan dan status refund." />@endif
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($canCash)<a href="{{ route('finance.cash.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600/40 hover:shadow-md"><p class="font-extrabold">Kas & shift</p><p class="mt-1 text-sm text-muted">Buka, tutup, dan audit kas.</p></a>@endif
        @if ($canPayments)<a href="{{ route('finance.payments.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600/40 hover:shadow-md"><p class="font-extrabold">Pembayaran</p><p class="mt-1 text-sm text-muted">Riwayat pembayaran pelanggan.</p></a>@endif
        @if ($canExpenses)<a href="{{ route('finance.expenses.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600/40 hover:shadow-md"><p class="font-extrabold">Biaya operasional</p><p class="mt-1 text-sm text-muted">Catat dan setujui pengeluaran.</p></a>@endif
        @if ($canRefunds)<a href="{{ route('finance.refunds.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600/40 hover:shadow-md"><p class="font-extrabold">Refund</p><p class="mt-1 text-sm text-muted">Tinjau dan proses pengembalian.</p></a>@endif
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        @if ($canCash)
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm"><div class="flex justify-between"><h2 class="font-extrabold">Shift terbaru</h2><a href="{{ route('finance.cash.index') }}" class="text-xs font-bold text-brand-700">Lihat</a></div><div class="mt-3 divide-y divide-divider">@forelse($sessions as $session)<div class="flex justify-between py-2 text-sm"><span>{{ $session->register?->code }}</span><span class="text-muted">{{ $session->status->value }} · {{ $session->opened_at?->format('d M H:i') }}</span></div>@empty<p class="py-3 text-sm text-muted">Belum ada shift.</p>@endforelse</div></section>
        @endif
        @if ($canPayments)
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm"><div class="flex justify-between"><h2 class="font-extrabold">Pembayaran terbaru</h2><a href="{{ route('finance.payments.index') }}" class="text-xs font-bold text-brand-700">Lihat</a></div><div class="mt-3 divide-y divide-divider">@forelse($payments as $payment)<div class="flex justify-between py-2 text-sm"><span>{{ $payment->customer?->name ?? 'Walk-in' }}</span><span class="font-bold">Rp{{ number_format($payment->amount_rupiah, 0, ',', '.') }}</span></div>@empty<p class="py-3 text-sm text-muted">Belum ada pembayaran.</p>@endforelse</div></section>
        @endif
    </div>
@endsection
