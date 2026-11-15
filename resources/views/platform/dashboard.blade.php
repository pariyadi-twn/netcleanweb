@extends('layouts.platform')

@section('title', 'NetClean Platform')

@section('content')
    <x-ui.page-header eyebrow="Kontrol platform" title="NetClean Platform" description="Pantau tenant, paket, tagihan SaaS, dan permintaan data tanpa membuka rincian bisnis tenant." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Tenant" :value="$tenants->count()" description="Organisasi aktif di platform." />
        <x-ui.stat-card label="Paket" :value="$plans->count()" description="Versi paket terdaftar." />
        <x-ui.stat-card label="Tagihan" :value="$invoices->count()" description="Tagihan platform terbaru." />
        <x-ui.stat-card label="Permintaan data" :value="$dataRequests->count()" description="Kasus yang perlu ditinjau." />
    </div>

    <section id="tenants" class="mt-6 rounded-container border border-divider bg-white p-5 shadow-sm">
        <h2 class="text-base font-extrabold text-ink">Tenant</h2>
        <p class="mt-1 text-sm text-muted">Ringkasan operasional tanpa nama, nomor, atau alamat pelanggan.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm"><caption class="sr-only">Daftar tenant platform</caption><thead class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-muted"><tr><th class="px-3 py-3">Nama</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Zona waktu</th><th class="px-3 py-3">Plan</th><th class="px-3 py-3">Periode berakhir</th><th class="px-3 py-3">Grant</th></tr></thead><tbody class="divide-y divide-divider">@forelse($tenants as $tenant)<tr><td class="px-3 py-3 font-semibold">{{ $tenant->name }}<br><span class="text-xs font-normal text-muted">{{ $tenant->public_id }}</span></td><td class="px-3 py-3"><span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-700">{{ $tenant->status->value }}</span></td><td class="px-3 py-3">{{ $tenant->timezone }}</td><td class="px-3 py-3">@if($tenant->platform_subscription){{ $tenant->platform_subscription->plan->code }} rev {{ $tenant->platform_subscription->plan->revision }}<br><span class="text-xs text-muted">{{ $tenant->platform_subscription->status->value }}</span>@else<span class="text-muted">Tanpa subscription</span>@endif</td><td class="px-3 py-3">{{ $tenant->platform_subscription?->current_period_end?->format('d M Y H:i') ?? '-' }}</td><td class="px-3 py-3">{{ $tenant->platform_subscription ? 'Aktif' : '-' }}</td></tr>@empty<tr><td colspan="6" class="px-3 py-10 text-center text-muted">Belum ada tenant.</td></tr>@endforelse</tbody></table>
        </div>
    </section>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section id="plans" class="rounded-container border border-divider bg-white p-5 shadow-sm"><h2 class="text-base font-extrabold">Paket</h2><div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-[10px] uppercase tracking-wider text-muted"><tr><th class="py-2">Code</th><th class="py-2">Revisi</th><th class="py-2">Nama</th><th class="py-2 text-right">Harga</th></tr></thead><tbody class="divide-y divide-divider">@forelse($plans as $plan)<tr><td class="py-2 font-semibold">{{ $plan->code }}</td><td class="py-2">{{ $plan->revision }}</td><td class="py-2">{{ $plan->name }}</td><td class="py-2 text-right">Rp{{ number_format($plan->base_price_rupiah, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-muted">Belum ada paket.</td></tr>@endforelse</tbody></table></div></section>
        <section id="invoices" class="rounded-container border border-divider bg-white p-5 shadow-sm"><h2 class="text-base font-extrabold">Tagihan platform terbaru</h2><div class="mt-4 divide-y divide-divider">@forelse($invoices as $invoice)<div class="py-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold">{{ $invoice->platform_invoice_number }}</span><span class="font-bold">Rp{{ number_format($invoice->amount_rupiah, 0, ',', '.') }}</span></div><p class="mt-1 text-xs text-muted">{{ $invoice->subscription?->plan?->code ?? '-' }} · {{ $invoice->status->value }} · {{ $invoice->period_start?->format('d M Y') }}—{{ $invoice->period_end?->format('d M Y') }}</p></div>@empty<p class="py-8 text-center text-sm text-muted">Belum ada tagihan.</p>@endforelse</div></section>
    </div>

    <section id="data-requests" class="mt-6 rounded-container border border-divider bg-white p-5 shadow-sm">
        <h2 class="text-base font-extrabold">Permintaan data</h2>
        <p class="mt-1 text-sm text-muted">Verifikasi identitas sebelum memproses. Proses hanya menganonimkan PII pelanggan; ledger tidak dihapus.</p>
        <div class="mt-4 divide-y divide-divider">
            @forelse($dataRequests as $dataRequest)
                <div class="py-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-bold">{{ $dataRequest->tenant?->name ?? $dataRequest->tenant_id }}</p><p class="mt-1 text-xs text-muted">{{ $dataRequest->public_id }} · {{ $dataRequest->reason }}</p></div><span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-700">{{ $dataRequest->status }}</span></div>
                    @if($dataRequest->status === 'requested')
                        <div class="mt-3 flex flex-wrap gap-2"><form method="POST" action="{{ route('platform.data-requests.verify', $dataRequest->public_id) }}" class="flex flex-wrap gap-2">@csrf<input name="verification_method" value="owner_video_call" aria-label="Metode verifikasi" required class="min-h-10 rounded-control border border-input px-3 text-xs"><button class="min-h-10 rounded-control bg-[#312e81] px-3 text-xs font-bold text-white">Verifikasi</button></form><form method="POST" action="{{ route('platform.data-requests.reject', $dataRequest->public_id) }}" class="flex flex-wrap gap-2">@csrf<input name="reason" placeholder="Keterangan penolakan" aria-label="Keterangan penolakan" required class="min-h-10 rounded-control border border-input px-3 text-xs"><button class="min-h-10 rounded-control border border-red-200 px-3 text-xs font-bold text-danger-text">Tolak</button></form></div>
                    @elseif($dataRequest->status === 'verified')
                        <form method="POST" action="{{ route('platform.data-requests.process', $dataRequest->public_id) }}" class="mt-3">@csrf<button class="min-h-10 rounded-control bg-danger-text px-3 text-xs font-bold text-white">Proses anonimisasi</button></form>
                    @else
                        <p class="mt-3 text-xs text-muted">Selesai.</p>
                    @endif
                </div>
            @empty
                <p class="py-8 text-center text-sm text-muted">Belum ada permintaan data.</p>
            @endforelse
        </div>
    </section>
@endsection
