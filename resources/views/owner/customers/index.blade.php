@extends('layouts.app')

@section('title', 'Pelanggan - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Pelanggan</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Data pelanggan</h1>
        <p class="mt-2 text-muted">Cari berdasarkan nama atau nomor telepon, lalu tambahkan pelanggan baru.</p>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mt-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)]">
        <x-ui.panel title="Cari pelanggan">
            <form method="GET" action="{{ route('customers.index') }}" class="flex flex-col gap-3 sm:flex-row">
                <input name="q" value="{{ $query }}" placeholder="Nama atau nomor telepon" aria-label="Cari pelanggan" class="min-h-12 flex-1 rounded-control border border-input px-4">
                <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Cari</button>
            </form>

            <div class="mt-5 space-y-3">
                @forelse ($customers as $customer)
                    <div class="rounded-control border border-divider p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-bold text-ink">{{ $customer->name }}</p>
                                <p class="mt-1 text-sm text-muted">{{ $customer->normalized_phone ?: 'Tanpa telepon' }} • {{ $customer->email ?: 'Tanpa email' }}</p>
                                @if ($customer->notes)
                                    <p class="mt-1 text-sm text-muted">{{ $customer->notes }}</p>
                                @endif
                            </div>
                            <x-ui.status-chip :tone="$customer->is_active ? 'success' : 'warning'">{{ $customer->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-chip>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">Tidak ada pelanggan yang cocok.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>
        </x-ui.panel>

        <x-ui.panel title="Pelanggan baru">
            <form method="POST" action="{{ route('customers.store') }}" class="grid gap-3">
                @csrf
                <label class="grid gap-1 text-sm font-semibold text-ink">Nama<input name="name" value="{{ old('name') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Telepon<input name="phone" value="{{ old('phone') }}" class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Email<input name="email" type="email" value="{{ old('email') }}" class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Catatan<textarea name="notes" class="min-h-20 rounded-control border border-input px-4 py-3 font-normal">{{ old('notes') }}</textarea></label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Label alamat<input name="address_label" value="{{ old('address_label') }}" class="min-h-12 rounded-control border border-input px-4 font-normal" placeholder="Utama"></label>
                <label class="grid gap-1 text-sm font-semibold text-ink">Alamat<textarea name="address" class="min-h-20 rounded-control border border-input px-4 py-3 font-normal">{{ old('address') }}</textarea></label>
                <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Simpan pelanggan</button>
            </form>
        </x-ui.panel>
    </div>
@endsection
