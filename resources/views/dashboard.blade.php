@extends('layouts.app')

@section('title', 'Ringkasan - NetClean')

@section('content')
    <x-ui.page-header
        eyebrow="Ringkasan operasional"
        :title="$tenant->name"
        description="Pantau pekerjaan hari ini dan masuk ke area yang paling relevan."
    >
        <x-slot:actions>
            @if (config('identity.mfa.mode') === 'required')
                <a href="{{ route('mfa.setup') }}" class="inline-flex min-h-11 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-ink hover:bg-slate-50">Kelola MFA</a>
            @else
                <span class="inline-flex min-h-11 items-center rounded-control border border-amber-300 bg-warning-bg px-4 text-sm font-semibold text-warning-text">MFA nonaktif sementara</span>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Cabang" :value="$branchCount" description="Cabang dalam scope membership." />
        <x-ui.stat-card label="Lokasi" :value="$locationCount" description="Outlet dan plant yang ditugaskan." />
        <x-ui.stat-card label="Order berjalan" :value="number_format($pendingOrders, 0, ',', '.')" description="Order yang belum selesai." />
        <x-ui.stat-card label="Tugas produksi" :value="number_format($activeTasks, 0, ',', '.')" description="Task queued, berjalan, atau blocked." />
    </div>

    @unless ($checklistComplete)
        <section class="mt-5 rounded-container border border-amber-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold tracking-tight text-ink">Siapkan usaha Anda</h2>
                    <p class="mt-1 text-sm text-muted">Beberapa langkah singkat agar kasir dan produksi bisa langsung dipakai.</p>
                </div>
                <x-ui.status-chip tone="warning">{{ collect($checklist)->where('done', true)->count() }}/{{ count($checklist) }} selesai</x-ui.status-chip>
            </div>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($checklist as $item)
                    <li class="flex items-start gap-3 rounded-control border border-divider p-3">
                        <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-bold {{ $item['done'] ? 'bg-success-bg text-success-text' : 'bg-slate-100 text-muted' }}">
                            {{ $item['done'] ? '✓' : '·' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-ink">{{ $item['label'] }}</p>
                            <p class="mt-1 text-xs text-muted">{{ $item['description'] }}</p>
                            @if (! $item['done'] && $item['route'])
                                <a href="{{ $item['route'] }}" class="mt-2 inline-flex text-xs font-bold text-brand-700 hover:text-brand-800">Buka pengaturan &rarr;</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endunless

    <div class="mt-5 grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
        <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-extrabold tracking-tight text-ink">Order terbaru</h2>
                    <p class="mt-1 text-sm text-muted">Akses cepat ke pekerjaan yang baru masuk.</p>
                </div>
                @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::OrdersView))
                    <a href="{{ route('orders.index') }}" class="text-xs font-bold text-brand-700 hover:text-brand-800">Lihat semua</a>
                @endif
            </div>

            <div class="mt-4 divide-y divide-divider">
                @forelse ($recentOrders as $order)
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-4 py-3 transition hover:text-brand-700">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold">{{ $order->order_number }}</p>
                            <p class="mt-1 truncate text-xs text-muted">{{ $order->customer?->name ?? 'Walk in' }} · {{ $order->invoice?->invoice_number }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-bold">{{ $order->operational_status->value }}</p>
                            <p class="mt-1 text-[11px] text-muted">{{ $order->due_at_current?->format('d M H:i') }}</p>
                        </div>
                    </a>
                @empty
                    <p class="py-8 text-sm text-muted">Belum ada order dalam scope.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-container border border-divider bg-chrome p-5 text-white shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-accent">Status fondasi</p>
            <h2 class="mt-3 text-xl font-extrabold leading-tight">Konteks tenant terlindungi</h2>
            <p class="mt-2 text-sm leading-6 text-white/55">Setiap halaman operasional memvalidasi membership dan scope cabang sebelum menampilkan data.</p>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-white/[0.06] p-3">
                    <dt class="text-[10px] uppercase tracking-wider text-white/40">Shift terbuka</dt>
                    <dd class="mt-1 text-xl font-extrabold">{{ $openCashSessions }}</dd>
                </div>
                <div class="rounded-xl bg-white/[0.06] p-3">
                    <dt class="text-[10px] uppercase tracking-wider text-white/40">Akses</dt>
                    <dd class="mt-1 text-sm font-bold">Owner / supervisor</dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="mt-8">
        <h2 class="text-base font-extrabold tracking-tight text-ink">Aksi cepat</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['Pesanan', 'Cari order, invoice, dan status produksi.', 'orders.index', \App\Modules\Identity\Enums\PermissionCode::OrdersView],
                ['Produksi', 'Pantau antrean dan QC per lokasi.', 'production.index', \App\Modules\Identity\Enums\PermissionCode::ProductionView],
                ['Keuangan', 'Kas, pembayaran, biaya, dan refund.', 'finance.index', \App\Modules\Identity\Enums\PermissionCode::CashView],
                ['Laporan', 'Penjualan, piutang, dan ekspor.', 'reports.index', \App\Modules\Identity\Enums\PermissionCode::ReportsView],
                ['Pelanggan', 'Cari dan tambah pelanggan.', 'customers.index', \App\Modules\Identity\Enums\PermissionCode::CustomersView],
                ['Pengaturan', 'Organisasi, cabang, staf, dan akses.', 'settings.index', \App\Modules\Identity\Enums\PermissionCode::MembershipsManage],
            ] as [$label, $description, $route, $permission])
                @if ($tenantContext->can($permission) && \Illuminate\Support\Facades\Route::has($route))
                    <a href="{{ route($route) }}" class="group rounded-container border border-divider bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-600/40 hover:shadow-lg">
                        <p class="font-extrabold text-ink">{{ $label }}</p>
                        <p class="mt-1 text-xs leading-5 text-muted">{{ $description }}</p>
                        <span class="mt-3 inline-flex text-xs font-bold text-brand-700">Buka <span class="ml-1 transition group-hover:translate-x-0.5" aria-hidden="true">&rarr;</span></span>
                    </a>
                @endif
            @endforeach
        </div>
    </section>

    @if ($user->is_platform_admin)
        <a href="{{ route('platform.dashboard') }}" class="mt-8 inline-flex min-h-11 items-center rounded-control bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-700">Buka platform admin</a>
    @endif
@endsection
