<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#061225">
    <title>@yield('title', 'NetClean')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <a href="#main" class="sr-only z-[100] rounded-control bg-white px-4 py-3 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>


    @isset($tenant)
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[17.5rem] flex-col bg-chrome text-white lg:flex">
            <div class="flex h-[4.5rem] items-center gap-3 border-b border-white/10 px-6">
                <span class="grid size-12 place-items-center rounded-xl bg-gradient-to-br from-accent via-emerald-300 to-cyan-300 text-base font-black text-chrome">N</span>
                <span class="leading-none">
                    <span class="block text-base font-bold tracking-tight">NetClean</span>
                    <span class="mt-1 block text-[9px] font-bold uppercase tracking-[0.2em] text-white/40">Konsol owner</span>
                </span>
            </div>
    
            @isset($tenant)
                <div class="mx-4 mt-4 rounded-2xl border border-white/10 bg-white/[0.06] p-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Tenant aktif</p>
                    <p class="mt-1 truncate text-sm font-bold">{{ $tenant->name }}</p>
                    <p class="mt-1 text-[11px] text-white/45">{{ $tenantContext->branchIds ? count($tenantContext->branchIds).' cabang dalam scope' : 'Tanpa cabang' }}</p>
                </div>
            @endisset
    
            <div class="flex-1 overflow-y-auto px-3 py-6">
                @include('layouts.partials.shell-nav')
            </div>
    
            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-2xl bg-white/[0.05] p-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-accent text-xs font-black text-chrome">{{ str(auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold">{{ auth()->user()?->name }}</p>
                        <p class="truncate text-[10px] text-white/40">{{ auth()->user()?->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="grid size-12 place-items-center rounded-control text-white/50 transition hover:bg-white/10 hover:text-white" aria-label="Keluar">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3.5H4.5v13H8M12 6.5 15.5 10 12 13.5M7 10h8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
    @endisset

    @isset($tenant)
    <header class="sticky top-0 z-30 border-b border-divider bg-white/90 backdrop-blur lg:hidden">
            <div class="flex min-h-16 items-center justify-between gap-3 px-4">
                <button type="button" class="grid size-12 place-items-center rounded-control border border-divider text-ink" data-nav-toggle aria-controls="mobile-navigation" aria-expanded="false" aria-label="Buka navigasi">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
                <span class="text-sm font-extrabold tracking-tight">NetClean</span>
                <span class="grid size-12 place-items-center rounded-full bg-chrome text-xs font-black text-accent">{{ str(auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}</span>
            </div>
        </header>
    
        <div id="mobile-navigation" data-nav-panel class="fixed inset-0 z-50 hidden lg:hidden">
            <button type="button" class="absolute inset-0 bg-chrome/60 backdrop-blur-sm" data-nav-close aria-label="Tutup navigasi"></button>
            <aside class="relative flex h-full w-[19rem] max-w-[85vw] flex-col bg-chrome text-white shadow-2xl">
                <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
                    <span class="text-sm font-extrabold">Menu</span>
                    <button type="button" class="grid size-12 place-items-center rounded-control text-white/60 hover:bg-white/10 hover:text-white" data-nav-close aria-label="Tutup navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-5">
                    @include('layouts.partials.shell-nav')
                </div>
                <div class="border-t border-white/10 p-4">
                    @isset($tenant)
                        <a href="{{ route('tenants.select') }}" class="mb-2 flex min-h-12 items-center rounded-xl px-3 text-sm font-semibold text-white/65 hover:bg-white/10 hover:text-white">Ganti tenant</a>
                    @endisset
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-12 w-full items-center rounded-xl px-3 text-left text-sm font-semibold text-white/65 hover:bg-white/10 hover:text-white">Keluar</button>
                    </form>
                </div>
            </aside>
        </div>
    @endisset

    @unless (isset($tenant))
        <header class="border-b border-divider bg-white">
            <div class="flex min-h-16 items-center justify-between gap-4 px-5 sm:px-8">
                <div class="flex items-center gap-3"><span class="grid size-12 place-items-center rounded-xl bg-chrome text-sm font-black text-accent">N</span><span class="text-sm font-extrabold tracking-tight">NetClean</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="min-h-12 rounded-control px-4 text-xs font-bold text-muted hover:bg-slate-100">Keluar</button></form>
            </div>
        </header>
    @endunless
    <div @class(['lg:pl-[17.5rem]' => isset($tenant)])>
        @isset($tenant)
            <header class="hidden min-h-[4.5rem] items-center justify-between gap-6 border-b border-divider bg-white/80 px-8 backdrop-blur lg:flex">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-muted">Konteks kerja</p>
                    <p class="mt-1 truncate text-sm font-extrabold text-ink">{{ $tenant->name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex min-h-12 items-center gap-2 rounded-full border border-divider bg-white px-3 text-xs font-semibold text-muted">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {{ $tenantContext->branchIds ? count($tenantContext->branchIds).' cabang' : 'Tanpa cabang' }}
                    </span>
                    <a href="{{ route('tenants.select') }}" class="inline-flex min-h-12 items-center rounded-control border border-divider bg-white px-4 text-xs font-bold text-ink transition hover:bg-slate-50">Ganti tenant</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-12 items-center rounded-control px-4 text-xs font-bold text-muted transition hover:bg-slate-100 hover:text-ink">Keluar</button>
                    </form>
                </div>
            </header>
        @endisset

        <main id="main" tabindex="-1" class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @unless (request()->routeIs('dashboard'))
                @php
                    $currentRoute = request()->route()?->getName() ?? '';
                    $backUrl = match (true) {
                        $currentRoute === 'tenants.select' => url('/'),
                        str_starts_with($currentRoute, 'roles.') && $currentRoute !== 'roles.index' => route('roles.index'),
                        str_starts_with($currentRoute, 'branches.') && $currentRoute !== 'branches.index' => route('branches.index'),
                        str_starts_with($currentRoute, 'staff.') && $currentRoute !== 'staff.index' => route('staff.index'),
                        str_starts_with($currentRoute, 'orders.') && $currentRoute !== 'orders.index' => route('orders.index'),
                        str_starts_with($currentRoute, 'production.') && $currentRoute !== 'production.index' => route('production.index'),
                        str_starts_with($currentRoute, 'finance.') && $currentRoute !== 'finance.index' => route('finance.index'),
                        str_starts_with($currentRoute, 'reports.') && $currentRoute !== 'reports.index' => route('reports.index'),
                        $currentRoute === 'settings.index' => route('dashboard'),
                        default => route('dashboard'),
                    };
                @endphp
                <div class="mb-5">
                    <x-ui.back-link :href="$backUrl" />
                </div>
            @endunless

            @if (session('status'))
                <p role="status" class="mb-6 rounded-container border border-green-200 bg-success-bg px-4 py-3 text-sm font-medium text-success-text">{{ session('status') }}</p>
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

    @stack('scripts')
</body>
</html>
