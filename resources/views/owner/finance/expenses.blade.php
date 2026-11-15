@extends('layouts.app')

@section('title', 'Biaya operasional - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Keuangan" title="Biaya operasional" description="Catat dan setujui pengeluaran dengan jejak kas." />
    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
    @if ($canManage)
        <form method="POST" action="{{ route('finance.expenses.store') }}" class="mb-5 grid gap-3 rounded-container border border-divider bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <select name="branch_id" class="min-h-11 rounded-control border border-input px-3 text-sm">@foreach($branches as $branch)<option value="{{ $branch->public_id }}">{{ $branch->name }}</option>@endforeach</select>
            <input name="category" required placeholder="Kategori" class="min-h-11 rounded-control border border-input px-3 text-sm">
            <input name="amount_rupiah" type="number" min="1" required placeholder="Nominal" class="min-h-11 rounded-control border border-input px-3 text-sm">
            <input name="occurred_at" type="date" required value="{{ now()->toDateString() }}" class="min-h-11 rounded-control border border-input px-3 text-sm">
            <button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Catat biaya</button>
            <input name="description" required placeholder="Keterangan biaya" class="min-h-11 rounded-control border border-input px-3 text-sm sm:col-span-2 lg:col-span-5">
        </form>
    @endif
    <div class="space-y-3">
        @forelse ($expenses as $expense)
            <section class="rounded-container border border-divider bg-white p-4 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-extrabold">{{ $expense->category }} · Rp{{ number_format($expense->amount_rupiah, 0, ',', '.') }}</p><p class="mt-1 text-xs text-muted">{{ $expense->branch?->name }} · {{ $expense->description }} · {{ $expense->occurred_at?->format('d M Y') }}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted">{{ $expense->approval_status->value }}</span></div>
            @if ($canApprove && $expense->approval_status->value === 'draft')
                <form method="POST" action="{{ route('finance.expenses.decision', $expense) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-divider pt-3">@csrf<select name="cash_session_id" class="min-h-10 rounded-control border border-input px-3 text-xs">@foreach($sessions as $session)<option value="{{ $session->public_id }}">{{ $session->register?->code }} · {{ $session->register?->branch?->name }}</option>@endforeach</select><input name="reason" placeholder="Keterangan" class="min-h-10 flex-1 rounded-control border border-input px-3 text-xs"><button name="decision" value="approved" class="min-h-10 rounded-control bg-brand-600 px-3 text-xs font-bold text-white">Setujui</button><button name="decision" value="rejected" class="min-h-10 rounded-control border border-red-200 px-3 text-xs font-bold text-danger-text">Tolak</button></form>
            @endif
        </section>
        @empty<p class="text-sm text-muted">Belum ada biaya.</p>@endforelse
    </div>
    <div class="mt-5">{{ $expenses->links() }}</div>
@endsection
