@extends('layouts.app')

@section('title', 'Peran - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Peran dan izin</h1>
        <p class="mt-2 text-muted">Peran menentukan tindakan yang boleh dilakukan staf.</p>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mt-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)]">
        <x-ui.panel title="Daftar peran">
            <div class="space-y-3">
                @foreach ($roles as $role)
                    <div class="flex flex-col gap-3 rounded-control border border-divider p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-ink">{{ $role->name }}</p>
                                @if ($role->is_system)
                                    <x-ui.alert tone="info" class="!py-1">Sistem</x-ui.alert>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $role->code }} • {{ $role->permissions_count }} izin</p>
                        </div>
                        <a href="{{ route('roles.edit', $role) }}" class="inline-flex min-h-12 shrink-0 items-center rounded-control border border-slate-400 px-4 text-sm font-semibold text-ink hover:bg-slate-50">Kelola izin</a>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>

        <x-ui.panel title="Peran baru">
            <form method="POST" action="{{ route('roles.store') }}" class="grid gap-3">
                @csrf
                <label class="grid gap-1 text-sm font-semibold text-ink">Kode
                    <input name="code" value="{{ old('code') }}" required class="min-h-12 rounded-control border border-slate-400 px-4 font-normal" placeholder="supervisor">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Nama
                    <input name="name" value="{{ old('name') }}" required class="min-h-12 rounded-control border border-slate-400 px-4 font-normal" placeholder="Supervisor">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Keterangan
                    <input name="reason" value="{{ old('reason') }}" class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Buat peran</button>
            </form>
        </x-ui.panel>
    </div>
@endsection
