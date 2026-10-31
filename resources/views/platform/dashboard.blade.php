<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform NetClean</title>
    <style>
        :root { color-scheme: light; --ink:#111827; --muted:#4b5563; --line:#d1d5db; --bg:#f3f4f6; --platform:#312e81; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:system-ui,sans-serif; line-height:1.5; }
        header { background:var(--platform); color:white; padding:1rem 1.25rem; }
        header strong { font-size:1.2rem; }
        header a { color:white; margin-left:1rem; }
        nav { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.35rem; font-size:.9rem; opacity:.9; }
        main { max-width:80rem; margin:0 auto; padding:1.25rem; display:grid; gap:1rem; }
        section { background:white; border:1px solid var(--line); border-radius:.75rem; padding:1rem; overflow:auto; }
        h1 { margin:0; font-size:1.25rem; }
        h2 { margin:0 0 .75rem; font-size:1rem; }
        table { width:100%; border-collapse:collapse; font-size:.9rem; }
        th,td { text-align:left; border-bottom:1px solid var(--line); padding:.55rem .4rem; vertical-align:top; }
        th { color:var(--muted); font-size:.8rem; text-transform:uppercase; letter-spacing:.03em; }
        .badge { display:inline-block; border-radius:999px; padding:.1rem .5rem; background:#e0e7ff; color:#3730a3; font-size:.75rem; font-weight:700; }
        .muted { color:var(--muted); }
        form.inline { display:flex; gap:.35rem; align-items:center; margin:.25rem 0; flex-wrap:wrap; }
        input { min-height:2.3rem; padding:.35rem .45rem; border:1px solid var(--line); border-radius:.4rem; font:inherit; }
        button { min-height:2.3rem; border:0; border-radius:.4rem; background:var(--platform); color:white; padding:.35rem .65rem; font:inherit; cursor:pointer; }
        button.danger { background:#991b1b; }
        .notice { background:#ecfdf5; border:1px solid #a7f3d0; padding:.65rem .8rem; border-radius:.5rem; }
        @media (max-width:48rem) { main { padding:.65rem; } section { padding:.75rem; } }
    </style>
</head>
<body>
<header>
    <strong>NetClean Platform</strong>
    <a href="{{ route('dashboard') }}">Dashboard tenant</a>
    <nav aria-label="Navigasi platform">
        <span>Konteks: platform / semua tenant</span>
        <span>Data pelanggan diminimalkan</span>
    </nav>
</header>
<main>
    @if (session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    <section>
        <h1>Tenant</h1>
        <p class="muted">Ringkasan operasional tanpa nama, nomor, atau alamat pelanggan.</p>
        <table>
            <thead><tr><th>Nama</th><th>Status</th><th>Timezone</th><th>Plan</th><th>Periode berakhir</th><th>Grant</th></tr></thead>
            <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td>{{ $tenant->name }}<br><span class="muted">{{ $tenant->public_id }}</span></td>
                    <td><span class="badge">{{ $tenant->status->value }}</span></td>
                    <td>{{ $tenant->timezone }}</td>
                    <td>
                        @if ($tenant->platform_subscription)
                            {{ $tenant->platform_subscription->plan->code }} rev {{ $tenant->platform_subscription->plan->revision }}
                            <br><span class="muted">{{ $tenant->platform_subscription->status->value }}</span>
                        @else
                            <span class="muted">Tanpa subscription</span>
                        @endif
                    </td>
                    <td>{{ $tenant->platform_subscription?->current_period_end?->utc()->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $tenant->platform_subscription ? 'Aktif' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Belum ada tenant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>Paket</h2>
        <table>
            <thead><tr><th>Code</th><th>Revisi</th><th>Nama</th><th>Harga</th><th>Aktif</th></tr></thead>
            <tbody>
            @forelse ($plans as $plan)
                <tr>
                    <td>{{ $plan->code }}</td>
                    <td>{{ $plan->revision }}</td>
                    <td>{{ $plan->name }}</td>
                    <td>Rp{{ number_format($plan->base_price_rupiah, 0, ',', '.') }}</td>
                    <td>{{ $plan->is_active ? 'Ya' : 'Tidak' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Belum ada paket.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>Tagihan platform terbaru</h2>
        <table>
            <thead><tr><th>Invoice</th><th>Tenant plan</th><th>Periode</th><th>Nominal</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->platform_invoice_number }}</td>
                    <td>{{ $invoice->subscription?->plan?->code ?? '—' }}</td>
                    <td>{{ $invoice->period_start?->format('Y-m-d') }} — {{ $invoice->period_end?->format('Y-m-d') }}</td>
                    <td>Rp{{ number_format($invoice->amount_rupiah, 0, ',', '.') }}</td>
                    <td><span class="badge">{{ $invoice->status->value }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Belum ada tagihan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>Permintaan data</h2>
        <p class="muted">Verifikasi identitas sebelum memproses. Proses hanya menganonimkan PII pelanggan; ledger tidak dihapus.</p>
        <table>
            <thead><tr><th>Tenant / kasus</th><th>Status</th><th>Alasan</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse ($dataRequests as $dataRequest)
                <tr>
                    <td>{{ $dataRequest->tenant?->name ?? $dataRequest->tenant_id }}<br><span class="muted">{{ $dataRequest->public_id }}</span></td>
                    <td><span class="badge">{{ $dataRequest->status }}</span></td>
                    <td>{{ $dataRequest->reason }}</td>
                    <td>
                        @if ($dataRequest->status === 'requested')
                            <form class="inline" method="POST" action="{{ route('platform.data-requests.verify', $dataRequest->public_id) }}">
                                @csrf
                                <input name="verification_method" value="owner_video_call" aria-label="Metode verifikasi" required>
                                <button type="submit">Verifikasi</button>
                            </form>
                            <form class="inline" method="POST" action="{{ route('platform.data-requests.reject', $dataRequest->public_id) }}">
                                @csrf
                                <input name="reason" placeholder="Alasan tolak" aria-label="Alasan penolakan" required>
                                <button class="danger" type="submit">Tolak</button>
                            </form>
                        @elseif ($dataRequest->status === 'verified')
                            <form class="inline" method="POST" action="{{ route('platform.data-requests.process', $dataRequest->public_id) }}">
                                @csrf
                                <button class="danger" type="submit">Proses anonimisasi</button>
                            </form>
                        @else
                            <span class="muted">Selesai</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Belum ada permintaan data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
