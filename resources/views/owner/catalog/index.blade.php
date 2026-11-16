@extends('layouts.app')

@section('title', 'Katalog dan harga - NetClean')

@section('content')
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-700">Katalog</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink">Layanan dan harga</h1>
        <p class="mt-2 text-muted">Layanan harus memiliki harga tenant aktif sebelum dipublikasikan.</p>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mt-6">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
        <x-ui.panel title="Layanan" description="Layanan aktif dapat dipilih kasir saat membuat order.">
            @if ($services->isEmpty())
                <p class="text-sm text-muted">Belum ada layanan.</p>
            @else
                <div class="space-y-3">
                    @foreach ($services as $service)
                        <div class="rounded-control border border-divider p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-bold text-ink">{{ $service->name }}</p>
                                        <x-ui.status-chip :tone="$service->is_active ? 'success' : 'warning'">{{ $service->is_active ? 'Aktif' : 'Draft' }}</x-ui.status-chip>
                                    </div>
                                    <p class="mt-1 text-sm text-muted">{{ $service->code }} • per {{ $service->unit->value }} • min {{ $service->minimum_quantity }} • step {{ $service->quantity_step }}</p>
                                    <p class="mt-1 text-sm text-muted">Workflow: {{ $service->workflowTemplate?->name }} rev. {{ $service->workflowTemplate?->revision }}</p>
                                </div>
                                <form method="POST" action="{{ route('catalog.services.publication', $service) }}" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="is_active" value="{{ $service->is_active ? 0 : 1 }}">
                                    <button type="submit" class="min-h-12 rounded-control border border-input px-4 text-sm font-semibold text-ink hover:bg-slate-50">{{ $service->is_active ? 'Nonaktifkan' : 'Publikasikan' }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 border-t border-divider pt-5">
                <h3 class="font-bold text-ink">Layanan baru</h3>
                @if ($workflows->isEmpty())
                    <x-ui.alert tone="warning" class="mt-3">Belum ada workflow aktif. Workflow harus dibuat sebelum layanan.</x-ui.alert>
                @else
                    <form method="POST" action="{{ route('catalog.services.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <label class="grid gap-1 text-sm font-semibold text-ink">Kode<input name="code" value="{{ old('code') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                        <label class="grid gap-1 text-sm font-semibold text-ink">Nama<input name="name" value="{{ old('name') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                        <label class="grid gap-1 text-sm font-semibold text-ink">Unit
                            <select name="unit" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                                @foreach (['kg', 'pcs', 'pair', 'meter', 'package'] as $unit)
                                    <option value="{{ $unit }}" @selected(old('unit', 'kg') === $unit)>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-ink">Workflow
                            <select name="workflow_template_id" required class="min-h-12 rounded-control border border-input px-4 font-normal">
                                @foreach ($workflows as $workflow)
                                    <option value="{{ $workflow->public_id }}" @selected(old('workflow_template_id') === $workflow->public_id)>{{ $workflow->name }} ({{ $workflow->code }} rev. {{ $workflow->revision }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-ink">Minimum<input name="minimum_quantity" value="{{ old('minimum_quantity', '1.000') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                        <label class="grid gap-1 text-sm font-semibold text-ink">Step<input name="quantity_step" value="{{ old('quantity_step', '0.100') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                        <div class="sm:col-span-2">
                            <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white hover:bg-brand-700">Buat layanan draft</button>
                        </div>
                    </form>
                @endif
            </div>
        </x-ui.panel>

        <x-ui.panel title="Price book" description="Harga tenant berlaku untuk semua cabang yang tidak punya override.">
            @if ($priceBooks->isEmpty())
                <p class="text-sm text-muted">Belum ada price book.</p>
            @else
                <div class="space-y-4">
                    @foreach ($priceBooks as $book)
                        <div class="rounded-control border border-divider p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-ink">{{ $book->name }}</p>
                                <x-ui.status-chip :tone="$book->is_active ? 'success' : 'warning'">{{ $book->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-chip>
                                <span class="text-sm text-muted">{{ $book->branch?->name ?? 'Tenant' }}</span>
                            </div>
                            <ul class="mt-3 space-y-1 text-sm text-muted">
                                @forelse ($book->entries as $entry)
                                    <li>{{ $entry->service?->name }} • {{ $entry->tier->value }} • Rp{{ number_format($entry->unit_price_rupiah, 0, ',', '.') }} • {{ $entry->effective_from?->format('Y-m-d') }}</li>
                                @empty
                                    <li>Belum ada harga.</li>
                                @endforelse
                            </ul>
                            @if ($book->is_active)
                                <form method="POST" action="{{ route('catalog.price-entries.store', $book) }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                                    @csrf
                                    <label class="grid gap-1 text-sm font-semibold text-ink">Layanan
                                        <select name="service_id" required class="min-h-12 rounded-control border border-input px-3 font-normal">
                                            @foreach ($services as $service)
                                                <option value="{{ $service->public_id }}">{{ $service->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="grid gap-1 text-sm font-semibold text-ink">Tier
                                        <select name="tier" class="min-h-12 rounded-control border border-input px-3 font-normal">
                                            <option value="regular">regular</option>
                                            <option value="express">express</option>
                                        </select>
                                    </label>
                                    <label class="grid gap-1 text-sm font-semibold text-ink">Harga (Rp)
                                        <input name="unit_price_rupiah" type="number" min="0" required class="min-h-12 rounded-control border border-input px-3 font-normal">
                                    </label>
                                    <label class="grid gap-1 text-sm font-semibold text-ink">Berlaku dari
                                        <input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="min-h-12 rounded-control border border-input px-3 font-normal">
                                    </label>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700">Tambah harga</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 border-t border-divider pt-5">
                <h3 class="font-bold text-ink">Price book baru</h3>
                <form method="POST" action="{{ route('catalog.price-books.store') }}" class="mt-3 grid gap-3">
                    @csrf
                    <label class="grid gap-1 text-sm font-semibold text-ink">Nama<input name="name" value="{{ old('name') }}" required class="min-h-12 rounded-control border border-input px-4 font-normal"></label>
                    <label class="grid gap-1 text-sm font-semibold text-ink">Cabang (opsional)
                        <select name="branch_id" class="min-h-12 rounded-control border border-input px-4 font-normal">
                            <option value="">Tenant (semua cabang)</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->public_id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-4 font-semibold text-white hover:bg-brand-700">Buat price book</button>
                </form>
            </div>
        </x-ui.panel>
    </div>
@endsection
