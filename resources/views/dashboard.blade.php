@extends('layouts.app')

@section('title', 'Ringkasan - NetClean')

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Ringkasan operasional</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $tenant->name }}</h1>
            <p class="mt-2 text-muted">Masuk sebagai {{ $user->name }} ({{ $user->email }}).</p>
        </div>
        <a href="{{ route('mfa.setup') }}" class="inline-flex min-h-12 items-center justify-center rounded-control border border-slate-400 px-4 text-sm font-semibold text-ink hover:bg-white">Kelola MFA</a>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-muted">Cabang dalam scope</p>
            <p class="mt-3 text-4xl font-bold tabular-nums text-ink">{{ $branchCount }}</p>
            <p class="mt-2 text-sm text-muted">Cabang yang dapat diakses membership aktif.</p>
        </section>
        <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-muted">Lokasi dalam scope</p>
            <p class="mt-3 text-4xl font-bold tabular-nums text-ink">{{ $locationCount }}</p>
            <p class="mt-2 text-sm text-muted">Outlet dan pusat produksi yang ditugaskan.</p>
        </section>
        <section class="rounded-container border border-blue-200 bg-brand-50 p-5 sm:col-span-2 xl:col-span-1">
            <p class="text-sm font-semibold text-brand-700">Status fondasi</p>
            <p class="mt-3 text-lg font-bold text-ink">Konteks tenant terlindungi</p>
            <p class="mt-2 text-sm text-muted">Setiap halaman operasional memvalidasi ulang membership sebelum membuka scope data.</p>
        </section>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('branches.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600">
            <p class="font-bold text-ink">Cabang</p>
            <p class="mt-1 text-sm text-muted">Profil cabang dan lokasi.</p>
        </a>
        <a href="{{ route('staff.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600">
            <p class="font-bold text-ink">Staf</p>
            <p class="mt-1 text-sm text-muted">Peran, cabang, dan lokasi staf.</p>
        </a>
        <a href="{{ route('catalog.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600">
            <p class="font-bold text-ink">Katalog &amp; harga</p>
            <p class="mt-1 text-sm text-muted">Layanan, workflow, dan price book.</p>
        </a>
        <a href="{{ route('customers.index') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600">
            <p class="font-bold text-ink">Pelanggan</p>
            <p class="mt-1 text-sm text-muted">Cari dan tambah pelanggan.</p>
        </a>
    </div>

    @if ($user->is_platform_admin)
        <a href="{{ route('platform.dashboard') }}" class="mt-8 inline-flex min-h-12 items-center rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Buka platform admin</a>
    @endif
@endsection
