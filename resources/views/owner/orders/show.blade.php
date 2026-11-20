@extends('layouts.app')

@section('title', $order->order_number.' - NetClean')

@php
    $invoiceTotal = $order->invoice?->totalRupiah() ?? 0;
    $invoicePaid = $order->invoice?->netAllocatedRupiah() ?? 0;
    $balance = $invoiceTotal - $invoicePaid;
@endphp

@section('content')
    <x-ui.page-header eyebrow="Detail pesanan" :title="$order->order_number" :description="($order->customer?->name ?? 'Walk-in').' · '.$order->salesBranch?->name">
        <x-slot:actions>
            <a href="{{ route('orders.receipt', $order) }}" class="inline-flex min-h-12 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-ink hover:bg-slate-50">Lihat nota</a>
            <a href="{{ route('orders.index') }}" class="inline-flex min-h-12 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted hover:bg-slate-50">Daftar pesanan</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total tagihan" :value="'Rp'.number_format($invoiceTotal, 0, ',', '.')" />
        <x-ui.stat-card label="Dibayar" :value="'Rp'.number_format($invoicePaid, 0, ',', '.')" />
        <x-ui.stat-card label="Sisa" :value="'Rp'.number_format($balance, 0, ',', '.')" :tone="$balance > 0 ? 'accent' : 'default'" />
        <x-ui.stat-card label="Estimasi selesai" :value="$order->due_at_current?->format('d M H:i') ?? '-'" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
        <div class="space-y-5">
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-base font-extrabold text-ink">Layanan</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-muted">{{ $order->operational_status->value }}</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-sm">
                        <caption class="sr-only">Rincian layanan pesanan {{ $order->order_number }}</caption>
                        <thead class="text-left text-[10px] uppercase tracking-wider text-muted"><tr><th class="pb-3">Layanan</th><th class="pb-3">Jumlah</th><th class="pb-3 text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-divider">
                            @foreach ($order->lines as $line)
                                <tr><td class="py-3 font-semibold">{{ $line->service_snapshot['name'] ?? $line->service?->name }}</td><td class="py-3 text-muted">{{ $line->billable_quantity }} {{ $line->unit->value }}</td><td class="py-3 text-right font-bold">Rp{{ number_format($line->line_total_rupiah, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Produksi</h2>
                @if ($tasks->isEmpty())
                    <p class="mt-3 text-sm text-muted">Belum ada tugas produksi untuk order ini.</p>
                @else
                    <div class="mt-4 divide-y divide-divider">
                        @foreach ($tasks as $task)
                            <a href="{{ route('production.tasks.show', $task) }}" class="flex items-center justify-between gap-4 py-3 hover:text-brand-700">
                                <div><p class="text-sm font-bold">{{ $task->location?->name }} · {{ $task->step_code }}</p><p class="mt-1 text-xs text-muted">Attempt {{ $task->attempt_no }} · {{ $task->due_at?->format('d M H:i') }}</p></div>
                                <span class="text-xs font-bold">{{ $task->status->value }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Aktivitas</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($order->events->sortByDesc('id') as $event)
                        <div class="flex gap-3 text-sm"><span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-600"></span><div><p class="font-semibold">{{ str($event->event_type)->replace('_', ' ')->title() }}</p><p class="mt-1 text-xs text-muted">{{ $event->created_at?->format('d M Y H:i') }}@if($event->reason) · {{ $event->reason }}@endif</p></div></div>
                    @empty
                        <p class="text-sm text-muted">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Pelanggan & invoice</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs text-muted">Nama</dt><dd class="font-semibold">{{ $order->customer?->name ?? 'Walk-in' }}</dd></div>
                    <div><dt class="text-xs text-muted">Telepon</dt><dd class="font-semibold">{{ $order->customer?->normalized_phone ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-muted">Invoice</dt><dd class="font-semibold">{{ $order->invoice?->invoice_number ?? '-' }}</dd></div>
                    <div><dt class="text-xs text-muted">Status invoice</dt><dd class="font-semibold">{{ $order->invoice?->document_status->value ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Bag</h2>
                <div class="mt-3 space-y-2 text-sm">
                    @forelse ($order->bags as $bag)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2"><span class="font-semibold">{{ $bag->label_code ?? 'Bag' }}</span><span class="text-xs text-muted">{{ $bag->custody_status }} · {{ $bag->currentLocation?->name ?? 'Belum diterima' }}</span></div>
                    @empty
                        <p class="text-sm text-muted">Belum ada bag.</p>
                    @endforelse
                </div>
                @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::ProductionManage))
                    <form method="POST" action="{{ route('orders.production.plan', $order) }}" class="mt-4 grid gap-2">
                        @csrf
                        <select name="location_id" aria-label="Lokasi produksi" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm">
                            @foreach ($locations as $location)
                                <option value="{{ $location->public_id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Rencanakan produksi</button>
                    </form>
                @endif
            </section>

            @if ($canComplaints)
                <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                    <h2 class="text-base font-extrabold text-ink">Komplain</h2>
                    <div class="mt-3 space-y-2">
                        @forelse($complaints as $complaint)
                            <div class="rounded-xl border border-divider p-3 text-sm">
                                <div class="flex justify-between gap-2"><p class="font-bold">{{ ucfirst($complaint->kind) }} · {{ $complaint->status }}</p><span class="text-xs text-muted">{{ $complaint->created_at?->format('d M H:i') }}</span></div>
                                <p class="mt-1 text-xs text-muted">{{ $complaint->description }}</p>
                                @if($complaint->resolution)<p class="mt-1 text-xs">Resolusi: {{ $complaint->resolution }}</p>@endif
                                @if(! in_array($complaint->status, ['resolved', 'rejected'], true))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if($complaint->kind === 'rewash')
                                            <form method="POST" action="{{ route('orders.complaints.rewash', [$order, $complaint]) }}">@csrf<button class="min-h-12 rounded-control border border-divider px-3 text-xs font-bold text-brand-700">Buat rewash</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('orders.complaints.resolve', [$order, $complaint]) }}" class="flex flex-1 gap-2">@csrf<input type="hidden" name="status" value="resolved"><input name="resolution" required placeholder="Resolusi" aria-label="Resolusi komplain" class="min-h-12 flex-1 rounded-control border border-input px-2 text-xs"><button class="min-h-12 rounded-control bg-brand-600 px-3 text-xs font-bold text-white">Selesaikan</button></form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-muted">Belum ada komplain.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('orders.complaints.store', $order) }}" class="mt-4 grid gap-2 border-t border-divider pt-4">
                        @csrf
                        <select name="kind" aria-label="Jenis komplain" class="min-h-12 rounded-control border border-input px-3 text-sm"><option value="rewash">Rewash</option><option value="missing">Hilang</option><option value="damaged">Rusak</option><option value="other">Lainnya</option></select>
                        <input name="description" required placeholder="Keterangan komplain" aria-label="Keterangan komplain" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <button class="min-h-12 rounded-control bg-brand-600 px-3 text-sm font-bold text-white">Catat komplain</button>
                    </form>
                </section>
            @endif

            @if ($canHandover && ! in_array($order->operational_status->value, ['completed', 'cancelled'], true) && $order->bags->where('custody_status', '!=', 'handed_over')->isNotEmpty())
                <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                    <h2 class="text-base font-extrabold text-ink">Serah terima</h2>
                    <form method="POST" action="{{ route('orders.handover', $order) }}" class="mt-3 grid gap-2">
                        @csrf
                        @foreach($order->bags->where('custody_status', '!=', 'handed_over') as $bag)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="bag_ids[]" value="{{ $bag->public_id }}" checked class="size-4 rounded border-input"> {{ $bag->label_code ?? 'Bag' }} · {{ $bag->currentLocation?->name ?? 'Belum diterima' }}</label>
                        @endforeach
                        <select name="location_id" aria-label="Lokasi serah terima" class="min-h-12 rounded-control border border-input px-3 text-sm">@foreach($locations as $location)<option value="{{ $location->public_id }}">{{ $location->name }}</option>@endforeach</select>
                        <input name="recipient_name" required placeholder="Nama penerima" aria-label="Nama penerima" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <select name="verification_method" aria-label="Metode verifikasi serah terima" class="min-h-12 rounded-control border border-input px-3 text-sm"><option value="receipt">Nota</option><option value="otp">OTP</option><option value="verified_staff_exception">Verifikasi staf</option></select>
                        <input name="evidence_notes" placeholder="Keterangan bukti (opsional)" aria-label="Keterangan bukti serah terima" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <button class="min-h-12 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Catat serah terima</button>
                    </form>
                </section>
            @endif

            @if ($canCredit && $order->invoice && $balance > 0)
                <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                    <h2 class="text-base font-extrabold text-ink">Credit approval</h2>
                    <form method="POST" action="{{ route('orders.credit', $order) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input name="limit_rupiah" type="number" min="0" value="{{ $balance }}" required aria-label="Batas kredit" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <input name="expires_at" type="date" aria-label="Credit approval berlaku sampai" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <input name="reason" required placeholder="Keterangan persetujuan" aria-label="Keterangan credit approval" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <button class="min-h-12 rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Simpan credit approval</button>
                    </form>
                </section>
            @endif

            @if ($pendingRevisions->isNotEmpty())
                <section class="rounded-container border border-amber-200 bg-warning-bg p-5">
                    <h2 class="text-base font-extrabold text-warning-text">Revisi menunggu</h2>
                    @foreach($pendingRevisions as $revision)
                        <form method="POST" action="{{ route('orders.revisions.decision', [$order, $revision]) }}" class="mt-3 grid gap-2">@csrf<input type="hidden" name="decision" value="approved"><input name="reason" required placeholder="Keterangan keputusan" aria-label="Keterangan keputusan revisi" class="min-h-12 rounded-control border border-amber-200 px-3 text-sm"><button class="min-h-12 rounded-control bg-brand-600 px-3 text-sm font-bold text-white">Setujui revisi</button></form>
                    @endforeach
                </section>
            @endif

            @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::PaymentsManage) && $balance > 0 && $order->invoice)
                <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                    <h2 class="text-base font-extrabold text-ink">Catat pembayaran</h2>
                    <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input name="amount_rupiah" type="number" min="1" value="{{ $balance }}" required aria-label="Nominal pembayaran" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <select name="method" aria-label="Metode pembayaran" class="min-h-12 rounded-control border border-input px-3 text-sm"><option value="bank_manual">Transfer</option><option value="cash">Tunai</option></select>
                        @if ($openSessions->isNotEmpty())
                            <select name="cash_session_id" aria-label="Sesi kas pembayaran" class="min-h-12 rounded-control border border-input px-3 text-sm"><option value="">Pilih shift untuk tunai</option>@foreach($openSessions as $session)<option value="{{ $session->public_id }}">{{ $session->register?->code }} · {{ $session->register?->branch?->name }}</option>@endforeach</select>
                        @endif
                        <input name="cash_received_rupiah" type="number" min="1" placeholder="Uang tunai diterima (opsional)" aria-label="Uang tunai diterima" class="min-h-12 rounded-control border border-input px-3 text-sm">
                        <button class="min-h-12 rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Simpan pembayaran</button>
                    </form>
                </section>
            @endif

            @if ($canCancel)
                <section class="rounded-container border border-red-200 bg-danger-bg p-5">
                    <h2 class="text-base font-extrabold text-danger-text">Batalkan pesanan</h2>
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $order->version }}">
                        <input name="reason" required placeholder="Keterangan pembatalan" aria-label="Keterangan pembatalan" class="min-h-12 rounded-control border border-red-200 bg-white px-3 text-sm">
                        <button type="submit" class="min-h-12 rounded-control bg-danger-text px-4 text-sm font-bold text-white hover:bg-red-800">Batalkan pesanan</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
@endsection
