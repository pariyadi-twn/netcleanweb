<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e1b4b">
    <title>@yield('title', 'NetClean Platform')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-100 text-ink antialiased">
    <a href="#main" class="sr-only z-[100] rounded-control bg-white px-4 py-3 font-semibold text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>
    <header class="bg-[#1e1b4b] text-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-5 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-white text-sm font-black text-[#312e81]">NC</span>
                <div><p class="text-base font-extrabold tracking-tight">NetClean Platform</p><p class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-indigo-200/60">Konteks: platform / semua tenant</p></div>
            </div>
            <nav aria-label="Navigasi platform" class="flex flex-wrap items-center gap-2 text-xs font-semibold text-indigo-100/70">
                <a href="#tenants" class="rounded-full px-3 py-2 hover:bg-white/10 hover:text-white">Tenant</a>
                <a href="#plans" class="rounded-full px-3 py-2 hover:bg-white/10 hover:text-white">Paket</a>
                <a href="#invoices" class="rounded-full px-3 py-2 hover:bg-white/10 hover:text-white">Tagihan</a>
                <a href="#data-requests" class="rounded-full px-3 py-2 hover:bg-white/10 hover:text-white">Permintaan data</a>
                <span class="mx-1 h-5 w-px bg-white/15"></span>
                <a href="{{ route('tenants.select') }}" class="rounded-full border border-white/15 px-3 py-2 hover:bg-white/10 hover:text-white">Konteks tenant</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-full px-3 py-2 text-indigo-100/60 hover:bg-white/10 hover:text-white">Keluar</button></form>
            </nav>
        </div>
    </header>
    <main id="main" tabindex="-1" class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8">
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-container border border-red-200 bg-danger-bg p-4 text-sm text-danger-text"><p class="font-bold">Periksa data berikut.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if (session('status'))<p role="status" class="mb-5 rounded-container border border-green-200 bg-success-bg px-4 py-3 text-sm text-success-text">{{ session('status') }}</p>@endif
        @yield('content')
    </main>
</body>
</html>
