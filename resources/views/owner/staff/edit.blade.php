@extends('layouts.app')

@section('title', 'Penugasan staf - NetClean')

@section('content')
    @php
        $selectedRoles = old('role_ids', $membership->roles->pluck('id')->all());
        $selectedBranches = old('branch_ids', $membership->branches->pluck('id')->all());
        $selectedLocations = old('location_ids', $membership->locations->pluck('id')->all());
    @endphp

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $membership->user->name }}</h1>
            <p class="mt-2 text-muted">{{ $membership->user->email }}</p>
        </div>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('staff.update', $membership) }}" class="grid gap-6">
        @csrf
        @method('PUT')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.panel title="Peran">
                <div class="space-y-2">
                    @foreach ($roles as $role)
                        <label class="flex min-h-12 items-center gap-3 rounded-control border border-divider px-3 text-sm">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles))>
                            <span class="font-semibold text-ink">{{ $role->name }}</span>
                            <span class="text-muted">{{ $role->code }}</span>
                        </label>
                    @endforeach
                </div>
            </x-ui.panel>
            <x-ui.panel title="Cabang">
                <div class="space-y-2">
                    @forelse ($branches as $branch)
                        <label class="flex min-h-12 items-center gap-3 rounded-control border border-divider px-3 text-sm">
                            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selectedBranches))>
                            <span class="font-semibold text-ink">{{ $branch->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-muted">Tidak ada cabang dalam scope.</p>
                    @endforelse
                </div>
            </x-ui.panel>
            <x-ui.panel title="Lokasi">
                <div class="space-y-2">
                    @forelse ($locations as $location)
                        <label class="flex min-h-12 items-center gap-3 rounded-control border border-divider px-3 text-sm">
                            <input type="checkbox" name="location_ids[]" value="{{ $location->id }}" @checked(in_array($location->id, $selectedLocations))>
                            <span class="font-semibold text-ink">{{ $location->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-muted">Tidak ada lokasi dalam scope.</p>
                    @endforelse
                </div>
            </x-ui.panel>
        </div>

        <x-ui.panel title="Keterangan">
            <input name="reason" value="{{ old('reason') }}" class="min-h-12 w-full rounded-control border border-input px-4 font-normal" placeholder="Opsional, tercatat pada audit">
        </x-ui.panel>

        <div>
            <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Simpan penugasan</button>
        </div>
    </form>
@endsection
