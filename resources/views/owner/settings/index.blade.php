@extends('layouts.app')

@section('title', 'Pengaturan - NetClean')

@section('content')
    <x-ui.page-header
        eyebrow="Konsol owner"
        title="Pengaturan"
        description="Kelola identitas usaha, struktur tim, akses, dan katalog dari satu tempat."
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($sections as $section)
            <a href="{{ route($section['route']) }}" class="group rounded-container border border-divider bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-600/40 hover:shadow-lg">
                <div class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <span class="text-sm font-extrabold">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <h2 class="mt-4 text-lg font-extrabold tracking-tight text-ink">{{ $section['label'] }}</h2>
                <p class="mt-1 text-sm leading-6 text-muted">{{ $section['description'] }}</p>
                <span class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-brand-700">Buka <span aria-hidden="true">&rarr;</span></span>
            </a>
        @endforeach
    </div>

    <div class="mt-6 rounded-container border border-divider bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-extrabold text-ink">Keamanan akun</h2>
                <p class="mt-1 text-sm text-muted">Verifikasi dua langkah untuk pemilik dan admin platform.</p>
            </div>
            <x-ui.alert :tone="$mfaMode === 'required' ? 'success' : 'warning'">
                {{ $mfaMode === 'required' ? 'MFA aktif' : 'MFA dinonaktifkan sementara' }}
            </x-ui.alert>
        </div>
    </div>
@endsection
