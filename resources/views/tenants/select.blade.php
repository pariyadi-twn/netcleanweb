@extends('layouts.app')

@section('title', 'Pilih tenant - NetClean')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Konteks kerja</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Pilih usaha yang akan dikelola</h1>
            <p class="mt-2 text-base text-muted">Data cabang, transaksi, dan staf akan dibatasi ke tenant yang dipilih.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-6 rounded-container border border-red-200 bg-danger-bg p-4 text-danger-text">
                <p class="font-bold">Tenant belum dapat dipilih.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($tenants->isEmpty())
            <section class="rounded-container border border-divider bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">Belum ada tenant aktif</h2>
                <p class="mt-2 text-muted">Hubungi administrator platform untuk menambahkan membership aktif.</p>
                @if ($user->is_platform_admin)
                    <a href="{{ route('platform.dashboard') }}" class="mt-5 inline-flex min-h-12 items-center rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Buka platform admin</a>
                @endif
            </section>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($tenants as $option)
                    <form method="POST" action="{{ route('tenants.store') }}" class="rounded-container border border-divider bg-white p-5 shadow-sm transition hover:border-brand-600 hover:shadow-md">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $option->public_id }}">
                        <div class="flex min-h-32 flex-col">
                            <div class="grid size-11 place-items-center rounded-container bg-brand-50 font-bold text-brand-700" aria-hidden="true">{{ str($option->name)->substr(0, 2)->upper() }}</div>
                            <h2 class="mt-4 text-lg font-bold text-ink">{{ $option->name }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ $option->timezone }}</p>
                            <button type="submit" class="mt-5 min-h-12 w-full rounded-control bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700">Kelola tenant</button>
                        </div>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endsection
