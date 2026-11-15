@extends('layouts.app')

@section('title', 'Pilih tenant - NetClean')

@section('content')
    <div class="mx-auto max-w-4xl">
        <x-ui.page-header eyebrow="Konteks kerja" title="Pilih usaha" description="Data cabang, transaksi, dan staf akan dibatasi ke tenant yang dipilih." />

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-5">
                <p class="font-bold">Tenant belum dapat dipilih.</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.alert>
        @endif

        @if ($tenants->isEmpty())
            <section class="rounded-container border border-divider bg-white p-6 shadow-sm">
                <h2 class="text-lg font-extrabold">Belum ada tenant aktif</h2>
                <p class="mt-2 text-sm text-muted">Hubungi administrator platform untuk menambahkan membership aktif.</p>
                @if ($user->is_platform_admin)
                    <a href="{{ route('platform.dashboard') }}" class="mt-5 inline-flex min-h-11 items-center rounded-control bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-700">Buka platform admin</a>
                @endif
            </section>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($tenants as $option)
                    <form method="POST" action="{{ route('tenants.store') }}" class="group rounded-container border border-divider bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-600/40 hover:shadow-lg">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $option->public_id }}">
                        <div class="flex min-h-32 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid size-11 place-items-center rounded-xl bg-chrome text-sm font-black text-accent" aria-hidden="true">{{ str($option->name)->substr(0, 2)->upper() }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-muted">Tenant</span>
                            </div>
                            <h2 class="mt-4 text-lg font-extrabold tracking-tight text-ink">{{ $option->name }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ $option->timezone }}</p>
                            <button type="submit" class="mt-5 min-h-11 w-full rounded-control bg-brand-600 px-4 text-sm font-bold text-white transition group-hover:bg-brand-700">Kelola tenant</button>
                        </div>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endsection
