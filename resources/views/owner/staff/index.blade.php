@extends('layouts.app')

@section('title', 'Staf - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Staf dan penugasan</h1>
        <p class="mt-2 text-muted">Peran, cabang, dan lokasi menentukan data yang dapat diakses staf.</p>
    </div>

    @if ($memberships->isEmpty())
        <x-ui.alert tone="warning" class="mt-6">Belum ada membership staf.</x-ui.alert>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($memberships as $membership)
                <x-ui.panel>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-bold text-ink">{{ $membership->user->name }}</h2>
                                <x-ui.alert :tone="$membership->status->value === 'active' ? 'success' : 'warning'" class="!py-1">{{ $membership->status->value }}</x-ui.alert>
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $membership->user->email }}</p>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                <div><dt class="text-muted">Peran</dt><dd class="font-semibold">{{ $membership->roles->pluck('name')->join(', ') ?: '-' }}</dd></div>
                                <div><dt class="text-muted">Cabang</dt><dd class="font-semibold">{{ $membership->branches->pluck('name')->join(', ') ?: '-' }}</dd></div>
                                <div><dt class="text-muted">Lokasi</dt><dd class="font-semibold">{{ $membership->locations->pluck('name')->join(', ') ?: '-' }}</dd></div>
                            </dl>
                        </div>
                        <a href="{{ route('staff.edit', $membership) }}" class="inline-flex min-h-12 shrink-0 items-center rounded-control bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700">Kelola penugasan</a>
                    </div>
                </x-ui.panel>
            @endforeach
        </div>
    @endif
@endsection
