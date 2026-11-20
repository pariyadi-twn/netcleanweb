@extends('layouts.app')

@section('title', 'Pesanan baru - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Kasir web" title="Pesanan baru" description="Hitung quote di server lalu konfirmasi order. Pembayaran dapat dicatat dari detail pesanan.">
        <x-slot:actions><a href="{{ route('orders.index') }}" class="inline-flex min-h-12 items-center rounded-control border border-divider bg-white px-4 text-sm font-bold text-muted hover:bg-slate-50">Daftar pesanan</a></x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())<x-ui.alert tone="danger" class="mb-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

    @if ($quote)
        @php($snapshot = $quote->pricing_snapshot)
        <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Ringkasan quote</h2>
                <div class="mt-4 divide-y divide-divider">
                    @foreach ($snapshot['lines'] as $line)
                        <div class="flex justify-between gap-4 py-3 text-sm"><span>{{ $line['service_snapshot']['name'] ?? 'Layanan' }} · {{ $line['billable_quantity'] }} {{ $line['unit'] }}<span class="ml-1 text-xs text-muted">({{ $line['tier'] }})</span></span><span class="font-bold">Rp{{ number_format($line['line_total_rupiah'], 0, ',', '.') }}</span></div>
                    @endforeach
                </div>
                <dl class="mt-4 space-y-2 border-t border-divider pt-4 text-sm"><div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd>Rp{{ number_format($snapshot['totals']['service_subtotal_rupiah'], 0, ',', '.') }}</dd></div><div class="flex justify-between"><dt class="text-muted">Diskon</dt><dd>Rp{{ number_format($snapshot['totals']['discount_rupiah'], 0, ',', '.') }}</dd></div><div class="flex justify-between"><dt class="text-muted">Express</dt><dd>Rp{{ number_format($snapshot['totals']['express_surcharge_rupiah'], 0, ',', '.') }}</dd></div><div class="flex justify-between text-base font-extrabold"><dt>Total</dt><dd>Rp{{ number_format($snapshot['totals']['total_rupiah'], 0, ',', '.') }}</dd></div></dl>
            </section>
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Konfirmasi</h2>
                <dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-muted">Pelanggan</dt><dd class="font-semibold">{{ $snapshot['customer_snapshot']['name'] ?? 'Walk-in' }}</dd></div><div><dt class="text-xs text-muted">Berlaku hingga</dt><dd class="font-semibold">{{ $quote->expires_at?->format('d M Y H:i') }}</dd></div><div><dt class="text-xs text-muted">Estimasi selesai</dt><dd class="font-semibold">{{ $quote->due_at?->format('d M Y H:i') }}</dd></div></dl>
                <form method="POST" action="{{ route('orders.accept') }}" class="mt-5">@csrf<button class="min-h-12 w-full rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Konfirmasi pesanan</button></form>
                <a href="{{ route('orders.create', ['reset' => 1]) }}" class="mt-2 flex min-h-12 items-center justify-center rounded-control text-xs font-bold text-muted hover:text-ink">Hitung ulang</a>
            </section>
        </div>
    @else
        <form method="POST" action="{{ route('orders.quote') }}" class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
            @csrf
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Pelanggan dan layanan</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1 text-xs font-bold text-muted">Cabang
                        <select name="branch_id" required class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal text-ink">
                            @foreach ($branches as $branch)<option value="{{ $branch->public_id }}" @selected(old('branch_id') === $branch->public_id)>{{ $branch->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-bold text-muted">Pelanggan
                        <select name="customer_id" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal text-ink">
                            <option value="">Walk-in</option>
                            @foreach ($customers as $customer)<option value="{{ $customer->public_id }}" @selected(old('customer_id') === $customer->public_id)>{{ $customer->name }} · {{ $customer->normalized_phone ?? 'tanpa telepon' }}</option>@endforeach
                        </select>
                    </label>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($services as $service)
                        <div class="grid items-end gap-3 rounded-xl border border-divider p-3 sm:grid-cols-[1fr_7rem_7rem]">
                            <div><p class="text-sm font-bold">{{ $service->name }}</p><p class="mt-1 text-xs text-muted">per {{ $service->unit->value }} · min {{ $service->minimum_quantity }} · step {{ $service->quantity_step }}</p></div>
                            <label class="grid gap-1 text-xs font-bold text-muted">Jumlah
                                <input name="quantities[{{ $service->public_id }}]" value="{{ old('quantities.'.$service->public_id) }}" inputmode="decimal" class="min-h-12 rounded-control border border-input px-3 text-sm font-normal" placeholder="0">
                            </label>
                            <label class="grid gap-1 text-xs font-bold text-muted">Tier
                                <select name="tiers[{{ $service->public_id }}]" class="min-h-12 rounded-control border border-input bg-white px-3 text-sm font-normal"><option value="regular" @selected(old('tiers.'.$service->public_id) === 'regular')>Reguler</option><option value="express" @selected(old('tiers.'.$service->public_id) === 'express')>Express</option></select>
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
                <h2 class="text-base font-extrabold text-ink">Hitung quote</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Harga dan tenggat dihitung server dari katalog aktif. Quote berlaku singkat; konfirmasi sebelum kedaluwarsa.</p>
                <button class="mt-5 min-h-12 w-full rounded-control bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Hitung pesanan</button>
            </section>
        </form>
    @endif
@endsection
