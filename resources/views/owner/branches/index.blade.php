@extends('layouts.app')

@section('title', 'Cabang - NetClean')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Cabang</h1>
            <p class="mt-2 text-muted">Cabang dan lokasi yang berada dalam scope akses Anda.</p>
        </div>
    </div>

    @if ($branches->isEmpty())
        <x-ui.alert tone="warning" class="mt-6">Belum ada cabang dalam scope akses.</x-ui.alert>
    @else
        <div class="mt-8 grid gap-4 lg:grid-cols-2">
            @foreach ($branches as $branch)
                <x-ui.panel>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-muted">{{ $branch->code }}</p>
                            <h2 class="mt-1 text-xl font-bold text-ink">{{ $branch->name }}</h2>
                            <p class="mt-2 text-sm text-muted">{{ $branch->address }}</p>
                        </div>
                        <x-ui.alert :tone="$branch->is_active ? 'success' : 'danger'" class="shrink-0">
                            {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-ui.alert>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-muted">Zona waktu</dt><dd class="font-semibold">{{ $branch->timezone }}</dd></div>
                        <div><dt class="text-muted">Lokasi</dt><dd class="font-semibold">{{ $branch->locations_count }}</dd></div>
                        <div><dt class="text-muted">Telepon</dt><dd class="font-semibold">{{ $branch->phone ?: '-' }}</dd></div>
                    </dl>
                    <a href="{{ route('branches.edit', $branch) }}" class="mt-5 inline-flex min-h-12 items-center rounded-control bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700">Kelola cabang</a>
                </x-ui.panel>
            @endforeach
        </div>
    @endif
@endsection
