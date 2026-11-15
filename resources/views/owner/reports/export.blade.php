@extends('layouts.app')

@section('title', 'Ekspor laporan - NetClean')

@section('content')
    <x-ui.page-header eyebrow="Laporan" title="Ekspor laporan" description="Pantau proses dan unduh hasil ekspor." />
    <section class="rounded-container border border-divider bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-xs text-muted">Status</dt><dd class="font-bold">{{ $export->status->value }}</dd></div><div><dt class="text-xs text-muted">Dibuat</dt><dd class="font-bold">{{ $export->created_at?->format('d M Y H:i') }}</dd></div><div><dt class="text-xs text-muted">Kedaluwarsa</dt><dd class="font-bold">{{ $export->expires_at?->format('d M Y H:i') ?? '-' }}</dd></div><div><dt class="text-xs text-muted">Baris</dt><dd class="font-bold">{{ $export->row_count ?? '-' }}</dd></div></dl>
        @if ($export->status->value === 'ready')
            <a href="{{ route('reports.exports.download', $export) }}" class="mt-5 inline-flex min-h-11 items-center rounded-control bg-brand-600 px-4 text-sm font-bold text-white">Unduh CSV</a>
        @elseif (in_array($export->status->value, ['queued', 'running'], true))
            <p class="mt-5 text-sm text-muted">Ekspor sedang diproses. Muat ulang halaman ini beberapa saat lagi.</p>
        @else
            <p class="mt-5 text-sm text-danger-text">Ekspor tidak tersedia.</p>
        @endif
    </section>
@endsection
