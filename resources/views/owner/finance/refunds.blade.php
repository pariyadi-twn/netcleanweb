@extends('layouts.app')

@section('title', 'Refund - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Keuangan" title="Refund" description="Tinjau permintaan pengembalian dan status pemrosesannya." />
    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
    <div class="space-y-3">
        @forelse ($refunds as $refund)
            <section class="rounded-container border border-divider bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-extrabold">Rp{{ number_format($refund->amount_rupiah, 0, ',', '.') }} · {{ $refund->payment?->customer?->name ?? 'Walk-in' }}</p><p class="mt-1 text-xs text-muted">{{ $refund->reason }} · {{ $refund->created_at?->format('d M Y H:i') }}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted">{{ $refund->status->value }}</span></div>
                @if ($canApprove && $refund->status->value === 'requested')
                    <form method="POST" action="{{ route('finance.refunds.decision', $refund) }}" class="mt-3 flex flex-wrap items-center gap-2 border-t border-divider pt-3">@csrf<input name="reason" placeholder="Keterangan keputusan" class="min-h-10 flex-1 rounded-control border border-input px-3 text-xs"><button name="decision" value="approved" class="min-h-10 rounded-control bg-brand-600 px-3 text-xs font-bold text-white">Setujui</button><button name="decision" value="rejected" class="min-h-10 rounded-control border border-red-200 px-3 text-xs font-bold text-danger-text">Tolak</button></form>
                @elseif ($canApprove && in_array($refund->status->value, ['approved', 'processing'], true))
                    <form method="POST" action="{{ route('finance.refunds.status', $refund) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-divider pt-3">@csrf<select name="status" class="min-h-10 rounded-control border border-input px-3 text-xs"><option value="succeeded">Selesai</option><option value="failed">Gagal</option></select>@if($sessions->isNotEmpty())<select name="cash_session_id" class="min-h-10 rounded-control border border-input px-3 text-xs"><option value="">Tanpa kas</option>@foreach($sessions as $session)<option value="{{ $session->public_id }}">{{ $session->register?->code }}</option>@endforeach</select>@endif<input name="reason" placeholder="Keterangan" class="min-h-10 flex-1 rounded-control border border-input px-3 text-xs"><button class="min-h-10 rounded-control bg-brand-600 px-3 text-xs font-bold text-white">Perbarui</button></form>
                @endif
            </section>
        @empty<p class="text-sm text-muted">Belum ada refund.</p>@endforelse
    </div>
    <div class="mt-5">{{ $refunds->links() }}</div>
@endsection
