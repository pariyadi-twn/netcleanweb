@extends('layouts.app')

@section('title', 'Staf - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Staf dan penugasan</h1>
        <p class="mt-2 text-muted">Peran, cabang, dan lokasi menentukan data yang dapat diakses staf.</p>
    </div>

    @if ($tenantContext->can(\App\Modules\Identity\Enums\PermissionCode::MembershipsManage))
        <x-ui.panel title="Tambah akun staf" description="Buat akun kasir atau operator. Bagikan kata sandi awal secara aman; staf dapat menggantinya lewat pemulihan akun." class="mt-6">
            @if ($errors->any())
                <x-ui.alert tone="danger" class="mb-4"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
            @endif
            <form method="POST" action="{{ route('staff.store') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="grid gap-1 text-sm font-semibold text-ink">Nama
                    <input name="name" value="{{ old('name') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Email
                    <input name="email" type="email" value="{{ old('email') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Kata sandi awal
                    <input name="password" type="password" minlength="12" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Ulangi kata sandi
                    <input name="password_confirmation" type="password" minlength="12" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Peran
                    <select name="role_id" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="grid gap-2 text-sm font-semibold text-ink sm:col-span-2 sm:grid-cols-2">
                    <legend>Cabang</legend>
                    @foreach ($branches as $branch)
                        <label class="flex items-center gap-2 font-normal"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, array_map('intval', (array) old('branch_ids', [$branches->first()?->id])), true))> {{ $branch->name }}</label>
                    @endforeach
                </fieldset>
                <fieldset class="grid gap-2 text-sm font-semibold text-ink sm:col-span-2 sm:grid-cols-2">
                    <legend>Lokasi</legend>
                    @foreach ($locations as $location)
                        <label class="flex items-center gap-2 font-normal"><input type="checkbox" name="location_ids[]" value="{{ $location->id }}" @checked(in_array($location->id, array_map('intval', (array) old('location_ids', [$locations->first()?->id])), true))> {{ $location->name }}</label>
                    @endforeach
                </fieldset>
                <div class="sm:col-span-2">
                    <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Buat akun staf</button>
                </div>
            </form>
        </x-ui.panel>
    @endif

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
                                <x-ui.status-chip :tone="$membership->status->value === 'active' ? 'success' : 'warning'">{{ $membership->status->value }}</x-ui.status-chip>
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
