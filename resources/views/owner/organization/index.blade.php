@extends('layouts.app')

@section('title', 'Organisasi - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Profil organisasi</h1>
        <p class="mt-2 text-muted">Nama dan zona waktu dipakai pada nota, laporan, dan perhitungan tenggat.</p>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mt-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
        <x-ui.panel title="Identitas usaha">
            <form method="POST" action="{{ route('organization.update') }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-sm font-semibold text-ink">Nama organisasi
                    <input name="name" value="{{ old('name', $tenant->name) }}" required class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Zona waktu
                    <input name="timezone" value="{{ old('timezone', $tenant->timezone) }}" required class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Alasan perubahan
                    <input name="reason" value="{{ old('reason') }}" class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Simpan profil</button>
            </form>
        </x-ui.panel>

        <x-ui.panel title="Status tenant">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-muted">Tenant ID</dt><dd class="break-all font-semibold">{{ $tenant->public_id }}</dd></div>
                <div><dt class="text-muted">Status</dt><dd class="font-semibold">{{ $tenant->status->value }}</dd></div>
                <div><dt class="text-muted">Zona waktu</dt><dd class="font-semibold">{{ $tenant->timezone }}</dd></div>
                <div><dt class="text-muted">Dibuat</dt><dd class="font-semibold">{{ $tenant->created_at?->format('Y-m-d') }}</dd></div>
            </dl>
        </x-ui.panel>
    </div>
@endsection
