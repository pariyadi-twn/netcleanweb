@extends('layouts.app')

@section('title', 'Kas & shift - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Keuangan" title="Kas & shift" description="Kelola laci kas, shift, dan selisih kas per cabang." />
    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($registers as $register)
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <div class="flex justify-between gap-4"><div><p class="text-sm font-extrabold">{{ $register->code }}</p><p class="mt-1 text-xs text-muted">{{ $register->branch?->name }} · {{ $register->is_active ? 'Aktif' : 'Nonaktif' }}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted">{{ $register->currentSession ? 'Shift terbuka' : 'Shift tertutup' }}</span></div>
                @if ($register->currentSession)
                    @php($session = $register->currentSession)
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-muted">Kas awal</dt><dd class="font-bold">Rp{{ number_format($session->opening_rupiah, 0, ',', '.') }}</dd></div><div><dt class="text-xs text-muted">Dibuka</dt><dd class="font-bold">{{ $session->opened_at?->format('d M H:i') }}</dd></div></dl>
                    @if ($canManage)
                        <form method="POST" action="{{ route('finance.cash.close', $session) }}" class="mt-4 grid gap-2 border-t border-divider pt-4"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input name="counted_closing_rupiah" type="number" min="0" required placeholder="Kas fisik dihitung" class="min-h-11 rounded-control border border-input px-3 text-sm"><input name="reason" placeholder="Keterangan selisih (jika ada)" class="min-h-11 rounded-control border border-input px-3 text-sm"><button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Tutup shift</button></form>
                    @endif
                @elseif ($canManage)
                    <form method="POST" action="{{ route('finance.cash.open', $register) }}" class="mt-4 grid gap-2"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input name="opening_rupiah" type="number" min="0" required placeholder="Kas awal" class="min-h-11 rounded-control border border-input px-3 text-sm"><button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Buka shift</button></form>
                @endif
            </section>
        @empty
            <x-ui.alert tone="warning">Belum ada laci kas dalam scope.</x-ui.alert>
        @endforelse
    </div>
    <section class="mt-6 rounded-container border border-divider bg-white p-5 shadow-sm"><h2 class="font-extrabold">Riwayat shift</h2><div class="mt-3 divide-y divide-divider">@foreach($sessions as $session)<div class="py-3 text-sm"><div class="flex flex-wrap justify-between gap-2"><span class="font-semibold">{{ $session->register?->code }} · {{ $session->register?->branch?->name }}</span><span class="text-muted">{{ $session->status->value }} · {{ $session->opened_at?->format('d M H:i') }}</span></div>@if($canManage && $session->status->value === 'closed')<form method="POST" action="{{ route('finance.cash.reopen', $session) }}" class="mt-2 flex flex-wrap gap-2">@csrf<input name="reason" required placeholder="Keterangan buka kembali" class="min-h-9 flex-1 rounded-control border border-input px-3 text-xs"><button class="min-h-9 rounded-control border border-divider px-3 text-xs font-bold text-brand-700">Buka kembali</button></form>@endif</div>@endforeach</div></section>
@endsection
