@extends('layouts.app')

@section('title', 'Kelola peran - NetClean')

@section('content')
    @php($selected = old('permission_ids', $role->permissions->pluck('id')->all()))

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Peran</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $role->name }}</h1>
            <p class="mt-2 text-muted">{{ $role->code }}</p>
        </div>
        <a href="{{ route('roles.index') }}" class="inline-flex min-h-12 items-center rounded-control border border-slate-400 px-4 text-sm font-semibold text-ink hover:bg-white">Kembali</a>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('roles.update', $role) }}" class="grid gap-6">
        @csrf
        @method('PUT')
        <x-ui.panel title="Nama peran">
            <input name="name" value="{{ old('name', $role->name) }}" required class="min-h-12 w-full rounded-control border border-slate-400 px-4 font-normal">
        </x-ui.panel>

        <x-ui.panel title="Izin" description="Izin berlaku untuk seluruh staf dengan peran ini.">
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="flex min-h-12 items-start gap-3 rounded-control border border-divider px-3 py-2 text-sm">
                        <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected)) class="mt-1">
                        <span><span class="block font-semibold text-ink">{{ $permission->code }}</span><span class="text-muted">{{ $permission->description }}</span></span>
                    </label>
                @endforeach
            </div>
        </x-ui.panel>

        <x-ui.panel title="Alasan perubahan">
            <input name="reason" value="{{ old('reason') }}" class="min-h-12 w-full rounded-control border border-slate-400 px-4 font-normal">
        </x-ui.panel>

        <div>
            <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Simpan peran</button>
        </div>
    </form>
@endsection
