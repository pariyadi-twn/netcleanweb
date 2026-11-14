<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NetClean')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="border-b border-divider bg-white lg:border-r lg:border-b-0">
            <div class="flex min-h-18 items-center justify-between gap-4 px-5 lg:px-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-bold tracking-tight text-ink">
                    <span class="grid size-10 place-items-center rounded-container bg-brand-600 text-white" aria-hidden="true">NC</span>
                    <span>NetClean</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                    @csrf
                    <button type="submit" class="min-h-12 rounded-control px-3 text-sm font-semibold text-muted hover:bg-slate-100">Keluar</button>
                </form>
            </div>

            @isset($tenant)
                <nav aria-label="Navigasi utama" class="flex gap-1 overflow-x-auto border-t border-divider px-4 py-3 lg:block lg:border-t-0 lg:px-3 lg:py-5">
                    <a href="{{ route('dashboard') }}" @class([
                        'flex min-h-12 items-center rounded-control px-4 text-sm font-semibold',
                        'bg-brand-50 text-brand-700' => request()->routeIs('dashboard'),
                        'text-muted hover:bg-slate-100 hover:text-ink' => ! request()->routeIs('dashboard'),
                    ])>Ringkasan</a>
                    @foreach (['Pesanan', 'Produksi', 'Keuangan', 'Laporan', 'Pengaturan'] as $item)
                        <span aria-disabled="true" class="flex min-h-12 cursor-not-allowed items-center rounded-control px-4 text-sm font-semibold text-slate-400">{{ $item }}</span>
                    @endforeach
                </nav>
            @endisset
        </aside>

        <div class="min-w-0">
            <header class="hidden min-h-18 items-center justify-between gap-6 border-b border-divider bg-white px-8 lg:flex">
                <div class="min-w-0">
                    @isset($tenant)
                        <p class="truncate text-sm font-semibold text-muted">Tenant aktif</p>
                        <p class="truncate font-bold text-ink">{{ $tenant->name }}</p>
                    @else
                        <p class="font-bold text-ink">Pilih tenant</p>
                    @endisset
                </div>
                <div class="flex items-center gap-3">
                    @isset($tenant)
                        <a href="{{ route('tenants.select') }}" class="inline-flex min-h-12 items-center rounded-control border border-slate-400 px-4 text-sm font-semibold text-ink hover:bg-slate-50">Ganti tenant</a>
                    @endisset
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-12 rounded-control px-4 text-sm font-semibold text-muted hover:bg-slate-100">Keluar</button>
                    </form>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @if (session('status'))
                    <p role="status" class="mb-6 rounded-container border border-green-200 bg-success-bg px-4 py-3 text-success-text">{{ session('status') }}</p>
                @endif

                @if (session('recovery_codes'))
                    <section class="mb-6 rounded-container border border-amber-300 bg-warning-bg p-5 text-warning-text">
                        <h2 class="font-bold">Recovery codes</h2>
                        <p class="mt-1">Simpan kode ini sekarang. Kode tidak akan ditampilkan lagi.</p>
                        <ul class="mt-3 grid gap-1 sm:grid-cols-2">
                            @foreach (session('recovery_codes') as $code)
                                <li><code>{{ $code }}</code></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
