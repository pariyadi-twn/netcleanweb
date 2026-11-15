@extends('layouts.app')

@section('title', 'Task produksi - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Task produksi" :title="str($task->step_code)->replace('_', ' ')->title()" :description="($task->content?->order?->order_number ?? '-').' · '.($task->location?->name ?? '-')">
        <x-slot:actions><a href="{{ route('orders.show', $task->content?->order) }}" class="inline-flex min-h-11 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted">Buka pesanan</a></x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Status" :value="str($task->status->value)->replace('_', ' ')->title()" />
        <x-ui.stat-card label="Attempt" :value="$task->attempt_no" />
        <x-ui.stat-card label="Tenggat" :value="$task->due_at?->format('d M H:i') ?? '-'" />
        <x-ui.stat-card label="Bag" :value="$task->content?->bag?->label_code ?? '-'" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_0.7fr]">
        <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <h2 class="text-base font-extrabold text-ink">Riwayat QC</h2>
            <div class="mt-4 space-y-3">
                @forelse ($task->qcResults->sortByDesc('id') as $qc)
                    <div class="rounded-xl border border-divider p-3"><div class="flex justify-between gap-3"><p class="text-sm font-bold">{{ $qc->outcome }}</p><span class="text-xs text-muted">{{ $qc->created_at?->format('d M H:i') }}</span></div>@if($qc->notes)<p class="mt-1 text-xs text-muted">{{ $qc->notes }}</p>@endif</div>
                @empty
                    <p class="text-sm text-muted">Belum ada hasil QC.</p>
                @endforelse
            </div>
            @if ($task->status->value === 'done' && $task->requires_qc)
                <form method="POST" action="{{ route('production.tasks.qc', $task) }}" class="mt-5 grid gap-3 border-t border-divider pt-5">
                    @csrf
                    <div class="flex gap-4 text-sm"><label class="flex items-center gap-2"><input type="radio" name="outcome" value="passed" checked> Lulus</label><label class="flex items-center gap-2"><input type="radio" name="outcome" value="failed"> Gagal</label></div>
                    <textarea name="notes" rows="2" placeholder="Keterangan QC" class="rounded-control border border-input px-3 py-2 text-sm"></textarea>
                    <button class="min-h-11 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Simpan QC</button>
                </form>
            @endif
        </section>

        <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <h2 class="text-base font-extrabold text-ink">Aksi task</h2>
            @php($transitions = ['queued' => ['in_progress' => 'Mulai', 'blocked' => 'Blokir', 'skipped' => 'Lewati'], 'in_progress' => ['done' => 'Selesaikan', 'blocked' => 'Blokir', 'skipped' => 'Lewati'], 'blocked' => ['queued' => 'Buka blokir']])
            <div class="mt-4 space-y-3">
                @foreach (($transitions[$task->status->value] ?? []) as $to => $label)
                    <form method="POST" action="{{ route('production.tasks.transition', $task) }}" class="grid gap-2 rounded-xl bg-slate-50 p-3">
                        @csrf
                        <input type="hidden" name="to_status" value="{{ $to }}"><input type="hidden" name="expected_version" value="{{ $task->version }}">
                        @if (in_array($to, ['blocked', 'skipped'], true) || $task->status->value === 'blocked')<input name="reason" required placeholder="Keterangan" class="min-h-10 rounded-control border border-input px-3 text-sm">@endif
                        <button class="min-h-10 rounded-control bg-brand-600 px-3 text-sm font-bold text-white hover:bg-brand-700">{{ $label }}</button>
                    </form>
                @endforeach
                @if ($task->status->value === 'done' && ! $task->requires_qc)<p class="text-sm text-muted">Task selesai tanpa QC.</p>@endif
            </div>
        </section>
    </div>
@endsection
