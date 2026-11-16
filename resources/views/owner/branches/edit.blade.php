@extends('layouts.app')

@section('title', 'Kelola cabang - NetClean')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Organisasi</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ $branch->name }}</h1>
            <p class="mt-2 text-muted">Kode {{ $branch->code }}. Perubahan konfigurasi diaudit.</p>
        </div>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
        <x-ui.panel title="Konfigurasi cabang">
            <form method="POST" action="{{ route('branches.update', $branch) }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-sm font-semibold text-ink">Nama
                    <input name="name" value="{{ old('name', $branch->name) }}" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Alamat
                    <textarea name="address" required class="min-h-24 rounded-control border border-input px-4 py-3 font-normal">{{ old('address', $branch->address) }}</textarea>
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Telepon
                    <input name="phone" value="{{ old('phone', $branch->phone) }}" class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Keterangan
                    <input name="reason" value="{{ old('reason') }}" class="min-h-12 rounded-control border border-input px-4 font-normal">
                </label>
                <label class="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active))>
                    Cabang aktif dan dapat menerima order
                </label>
                <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Simpan cabang</button>
            </form>
        </x-ui.panel>

        <x-ui.panel title="Lokasi" description="Lokasi yang ditugaskan ke membership Anda.">
            @if ($locations->isEmpty())
                <p class="text-sm text-muted">Belum ada lokasi dalam scope akses.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($locations as $location)
                        <li class="rounded-control border border-divider p-3">
                            <p class="font-semibold text-ink">{{ $location->name }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $location->code }} • {{ $location->type->value }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.panel>
    </div>
@endsection
