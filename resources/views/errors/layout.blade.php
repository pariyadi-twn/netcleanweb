<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NetClean')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-dvh place-items-center bg-chrome px-5 py-10 text-white">
    <main class="w-full max-w-lg rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 text-center shadow-2xl backdrop-blur">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-accent via-emerald-300 to-cyan-300 text-xl font-black text-chrome">N</span>
        <p class="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-accent">@yield('code')</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight">@yield('heading')</h1>
        <p class="mt-3 text-sm leading-6 text-white/60">@yield('message')</p>
        <div class="mt-7 flex flex-col justify-center gap-2 sm:flex-row">
            <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center justify-center rounded-control bg-white px-5 text-sm font-bold text-chrome">Ke beranda</a>
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-control border border-white/15 px-5 text-sm font-bold text-white/80 hover:bg-white/10">Buka konsol</a>
            @endauth
        </div>
    </main>
</body>
</html>
