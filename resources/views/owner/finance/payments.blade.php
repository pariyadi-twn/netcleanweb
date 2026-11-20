@extends('layouts.app')

@section('title', 'Pembayaran - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Keuangan" title="Pembayaran" description="Riwayat pembayaran pelanggan pada cabang yang dapat Anda akses." />
    <div class="overflow-hidden rounded-container border border-divider bg-white shadow-sm">
        <div class="divide-y divide-divider">
            @forelse ($payments as $payment)
                <div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_0.8fr_0.6fr_0.5fr] sm:items-center"><div><p class="text-sm font-extrabold">{{ $payment->customer?->name ?? 'Walk-in' }}</p><p class="mt-1 text-xs text-muted">{{ $payment->receivedBranch?->name }} · {{ $payment->received_at?->format('d M Y H:i') }}</p></div><p class="text-xs text-muted">{{ $payment->method }} · {{ $payment->allocations->count() }} alokasi</p><p class="text-sm font-bold">Rp{{ number_format($payment->amount_rupiah, 0, ',', '.') }}</p><span class="justify-self-start rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted sm:justify-self-end">{{ $payment->unallocated_rupiah > 0 ? 'Ada saldo' : 'Teralokasi' }}</span></div>
                @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::RefundsRequest))
                    <details class="border-t border-divider px-5 py-3"><summary class="cursor-pointer text-xs font-bold text-brand-700">Ajukan refund</summary><form method="POST" action="{{ route('finance.payments.refund', $payment) }}" class="mt-2 flex flex-wrap items-center gap-2">@csrf<input name="amount_rupiah" type="number" min="1" max="{{ $payment->amount_rupiah }}" placeholder="Nominal" aria-label="Nominal refund" class="min-h-12 rounded-control border border-input px-3 text-xs"><input name="reason" required placeholder="Keterangan" aria-label="Keterangan refund" class="min-h-12 flex-1 rounded-control border border-input px-3 text-xs"><button class="min-h-12 rounded-control bg-brand-600 px-3 text-xs font-bold text-white">Ajukan</button></form></details>
                @endif
            @empty
                <div class="px-5 py-14 text-center text-sm text-muted">Belum ada pembayaran.</div>
            @endforelse
        </div>
    </div>
    <div class="mt-5">{{ $payments->links() }}</div>
@endsection
