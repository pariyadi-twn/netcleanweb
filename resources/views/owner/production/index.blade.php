@extends('layouts.app')

@section('title', 'Produksi - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Operasional" title="Produksi" description="Pantau antrean task, tenggat, dan QC per lokasi kerja." />

    @if ($tenant->production_mode->value === 'simple')
        <x-ui.alert tone="info" class="mb-5">Mode ringkas aktif. Selesaikan cucian dari halaman detail pesanan dengan satu tombol; antrean rinci di bawah tetap tersedia untuk pengawasan.</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('production.index') }}" class="mb-5 grid gap-3 rounded-container border border-divider bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        @if ($locations->count() > 1)
            <label class="grid gap-1 text-xs font-bold text-muted">Lokasi
                <select name="location" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal">
                    @foreach ($locations as $location)
                        <option value="{{ $location->public_id }}" @selected($selected?->id === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </label>
        @elseif ($selected !== null)
            <input type="hidden" name="location" value="{{ $selected->public_id }}">
            <div class="grid gap-1 text-xs font-bold text-muted">Lokasi<div class="flex min-h-12 items-center rounded-control border border-divider bg-slate-50 px-3 text-sm font-normal text-ink">{{ $selected->name }}</div></div>
        @endif
        <label class="grid gap-1 text-xs font-bold text-muted">Status
            <select name="status" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex min-h-12 items-end gap-2 pb-3 text-xs font-bold text-muted"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue')) class="size-4 rounded border-input"> Hanya terlambat</label>
        <div class="flex items-end gap-2"><button class="min-h-12 flex-1 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Terapkan</button><a href="{{ route('production.index') }}" class="inline-flex min-h-12 items-center rounded-control border border-divider px-4 text-sm font-bold text-muted hover:bg-slate-50">Reset</a></div>
    </form>

    @if ($selected === null)
        <x-ui.alert tone="warning">Belum ada lokasi produksi yang ditugaskan ke akun ini.</x-ui.alert>
    @else
        <div class="rounded-container border border-divider bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-divider px-5 py-4">
                <div><p class="text-sm font-extrabold text-ink">{{ $selected->name }}</p><p class="mt-1 text-xs text-muted">{{ $selected->type->value }} · {{ $selected->code }}</p></div>
                <span class="rounded-full bg-brand-50 px-3 py-1 text-[10px] font-bold text-brand-700">{{ $tasks->total() }} task</span>
            </div>
            <div class="divide-y divide-divider">
                @forelse ($tasks as $task)
                    <a href="{{ route('production.tasks.show', $task) }}" class="grid gap-3 px-5 py-4 transition hover:bg-brand-50/50 sm:grid-cols-[1fr_0.8fr_0.7fr_0.5fr] sm:items-center">
                        <div><p class="text-sm font-extrabold">{{ $task->content?->order?->order_number }} · {{ str($task->step_code)->replace('_', ' ')->title() }}</p><p class="mt-1 text-xs text-muted">{{ $task->content?->orderLine?->service_snapshot['name'] ?? $task->content?->orderLine?->service?->name }}</p></div>
                        <p class="text-xs text-muted">{{ $task->content?->bag?->label_code ?? 'Bag' }} · attempt {{ $task->attempt_no }}</p>
                        <p class="text-xs text-muted">{{ $task->due_at?->format('d M Y H:i') }}</p>
                        <span class="justify-self-start rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted sm:justify-self-end">{{ $task->status->value }}</span>
                    </a>
                @empty
                    <div class="px-5 py-14 text-center text-sm text-muted">Antrean bersih. Tidak ada task yang cocok.</div>
                @endforelse
            </div>
        </div>
        <div class="mt-5">{{ $tasks->links() }}</div>
    @endif
@endsection
