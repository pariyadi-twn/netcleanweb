<!DOCTYPE html>
<html lang="id" class="scroll-smooth motion-reduce:scroll-auto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="NetClean menyatukan kasir, produksi, QC, serah terima, dan kontrol owner laundry dalam satu alur yang rapi.">
        <meta name="theme-color" content="#061225">
        <meta property="og:type" content="website">
        <meta property="og:title" content="NetClean — Operasional laundry dalam satu alur">
        <meta property="og:description" content="Dari nota kasir sampai serah terima, semua tercatat dan mudah dipantau.">

        <title>NetClean — Operasional laundry dalam satu alur</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white text-ink antialiased selection:bg-lime-300 selection:text-slate-950">
        <a href="#main" class="sr-only z-[100] rounded-lg bg-white px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Lewati ke konten
        </a>

        <header class="absolute inset-x-0 top-0 z-30 bg-gradient-to-b from-[#061225] via-[#061225]/85 to-transparent text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 lg:py-7">
                <a href="/" class="flex items-center gap-3 rounded-xl" aria-label="NetClean, kembali ke beranda">
                    <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-lime-300 via-emerald-300 to-cyan-300 text-base font-black tracking-tight text-[#061225] shadow-lg shadow-lime-300/10">
                        N
                    </span>
                    <span class="leading-none">
                        <span class="block text-base font-bold tracking-tight">NetClean</span>
                        <span class="mt-1 block whitespace-nowrap text-[10px] font-semibold uppercase tracking-[0.22em] text-white/45">Laundry OS</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-white/65 md:flex" aria-label="Navigasi utama">
                    <a href="#fitur" class="transition hover:text-white">Fitur</a>
                    <a href="#alur" class="transition hover:text-white">Alur kerja</a>
                    <a href="#peran" class="transition hover:text-white">Untuk siapa</a>
                    <a href="#faq" class="transition hover:text-white">FAQ</a>
                </nav>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-full px-2 py-2.5 text-xs font-semibold text-white/70 transition hover:text-white sm:px-4 sm:text-sm">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full px-2 py-2.5 text-xs font-semibold text-white/70 transition hover:text-white sm:px-4 sm:text-sm">
                            Masuk
                        </a>
                    @endauth

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="group inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-lime-300 px-3 py-2.5 text-xs font-bold text-[#061225] shadow-lg shadow-lime-300/10 transition hover:bg-lime-200 sm:gap-2 sm:px-5 sm:text-sm">
                            <span class="sm:hidden">Mulai</span>
                            <span class="hidden sm:inline">Mulai sekarang</span>
                            <svg class="size-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M4.167 10h11.666M10.833 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </header>

        <main id="main">
            <section class="relative overflow-hidden bg-[#061225] text-white">
                <div aria-hidden="true" class="landing-grid absolute inset-0 opacity-70"></div>
                <div aria-hidden="true" class="absolute -left-32 top-24 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
                <div aria-hidden="true" class="absolute -right-24 top-8 h-[28rem] w-[28rem] rounded-full bg-cyan-400/15 blur-3xl"></div>
                <div aria-hidden="true" class="absolute bottom-0 left-1/2 h-64 w-[42rem] -translate-x-1/2 rounded-full bg-lime-300/10 blur-3xl"></div>

                <section class="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-5 pb-20 pt-32 sm:px-8 sm:pb-24 lg:grid-cols-[1.02fr_0.98fr] lg:gap-10 lg:pb-28 lg:pt-40">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.06] px-3.5 py-1.5 text-xs font-semibold text-white/75 backdrop-blur">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-lime-300 opacity-60"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-lime-300"></span>
                            </span>
                            Platform operasional laundry
                        </div>

                        <h1 class="mt-7 text-balance text-[2.8rem] font-extrabold leading-[1.03] tracking-[-0.045em] sm:text-6xl lg:text-[4.35rem]">
                            Laundry jalan rapi.
                            <span class="block bg-gradient-to-r from-lime-200 via-emerald-200 to-cyan-200 bg-clip-text text-transparent">Tanpa tumpukan kertas.</span>
                        </h1>

                        <p class="mt-6 max-w-xl text-pretty text-base leading-7 text-white/65 sm:text-lg sm:leading-8">
                            Dari nota kasir sampai serah terima, NetClean menyatukan kasir, produksi, QC, dan kontrol owner dalam satu alur yang bisa dipantau dari mana saja.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="group inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-bold text-[#061225] shadow-xl shadow-black/10 transition hover:-translate-y-0.5 hover:bg-lime-200">
                                    Buat akun owner
                                    <svg class="size-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M4.167 10h11.666M10.833 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @endif
                            <a href="#alur" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-white/15 bg-white/[0.04] px-6 py-3 text-sm font-semibold text-white/85 backdrop-blur transition hover:border-white/30 hover:bg-white/[0.08] hover:text-white">
                                Lihat cara kerja
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M10 4.167v11.666M5 10.833l5 5 5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>

                        <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm font-medium text-white/55">
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4 text-lime-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Web untuk owner
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4 text-lime-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Android untuk staf
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4 text-lime-300" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Siap multi-cabang
                            </span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-[31rem] lg:max-w-none" aria-hidden="true">
                        <div aria-hidden="true" class="absolute -inset-8 rounded-[3rem] bg-gradient-to-tr from-brand-600/30 via-cyan-400/10 to-lime-300/20 blur-3xl"></div>

                        <div class="relative rounded-[2.1rem] border border-white/15 bg-white/[0.07] p-2 shadow-2xl shadow-black/40 backdrop-blur-xl">
                            <div class="overflow-hidden rounded-[1.7rem] bg-[#f7f9fc] text-ink">
                                <div class="flex items-center justify-between border-b border-slate-200/80 bg-white px-5 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-8 place-items-center rounded-lg bg-[#061225] text-xs font-bold text-lime-300">NC</span>
                                        <div>
                                            <p class="text-xs font-bold">Cabang Kemang</p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-[10px] font-medium text-slate-500">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Kasir buka
                                            </p>
                                        </div>
                                    </div>
                                    <span class="grid size-8 place-items-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">RA</span>
                                </div>

                                <div class="space-y-4 p-4 sm:p-5">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Pesanan baru</p>
                                                <p class="mt-1 text-sm font-bold">Rina Anggraini</p>
                                                <p class="mt-0.5 text-[11px] font-medium text-slate-500">NC-2408-0192 · 3 layanan</p>
                                            </div>
                                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700 ring-1 ring-inset ring-amber-200">Belum bayar</span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                            <div class="rounded-xl bg-slate-50 px-2 py-2.5">
                                                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Cuci</p>
                                                <p class="mt-1 text-xs font-bold">3 kg</p>
                                            </div>
                                            <div class="rounded-xl bg-slate-50 px-2 py-2.5">
                                                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Setrika</p>
                                                <p class="mt-1 text-xs font-bold">3 kg</p>
                                            </div>
                                            <div class="rounded-xl bg-slate-50 px-2 py-2.5">
                                                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Express</p>
                                                <p class="mt-1 text-xs font-bold">Ya</p>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex items-end justify-between border-t border-dashed border-slate-200 pt-4">
                                            <div>
                                                <p class="text-[10px] font-semibold text-slate-400">Total tagihan</p>
                                                <p class="mt-0.5 text-xl font-extrabold tracking-tight">Rp84.000</p>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-[#061225] px-3.5 py-2.5 text-[11px] font-bold text-white">
                                                Terima bayar
                                                <svg class="size-3.5 text-lime-300" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.333 8h9.334M8.667 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-center justify-between">
                                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Status produksi</p>
                                            <span class="text-[10px] font-semibold text-brand-600">2 dari 4 selesai</span>
                                        </div>
                                        <div class="mt-4 flex items-center gap-1.5">
                                            <div class="h-1.5 flex-1 rounded-full bg-emerald-500"></div>
                                            <div class="h-1.5 flex-1 rounded-full bg-emerald-500"></div>
                                            <div class="h-1.5 flex-1 rounded-full bg-slate-200"></div>
                                            <div class="h-1.5 flex-1 rounded-full bg-slate-200"></div>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between text-[9px] font-semibold text-slate-400">
                                            <span class="text-emerald-600">Diterima</span>
                                            <span class="text-emerald-600">Dicuci</span>
                                            <span>QC</span>
                                            <span>Siap</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -right-4 top-16 hidden items-center gap-3 rounded-2xl border border-white/15 bg-[#0b1d38]/95 p-3 pr-4 shadow-2xl shadow-black/30 backdrop-blur-xl motion-safe:animate-float sm:flex">
                            <span class="grid size-9 place-items-center rounded-xl bg-lime-300 text-[#061225]">
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <div>
                                <p class="text-[11px] font-bold text-white">QC lulus</p>
                                <p class="mt-0.5 text-[10px] text-white/50">Siap diambil pelanggan</p>
                            </div>
                        </div>

                        <div class="absolute -bottom-5 -left-4 hidden items-center gap-3 rounded-2xl border border-white/15 bg-[#0b1d38]/95 p-3 pr-4 shadow-2xl shadow-black/30 backdrop-blur-xl motion-safe:animate-float [animation-delay:1.4s] sm:flex">
                            <span class="grid size-9 place-items-center rounded-xl bg-cyan-300 text-[#061225]">
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.333 6.667h13.334M5 10h10M7.5 13.333h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            </span>
                            <div>
                                <p class="text-[10px] text-white/50">Shift kas</p>
                                <p class="mt-0.5 text-[11px] font-bold text-white">Seimbang · Rp0 selisih</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="relative z-10 border-t border-white/10 bg-white/[0.03]">
                    <div class="mx-auto grid max-w-7xl gap-px px-5 sm:px-8 md:grid-cols-3">
                        <div class="flex items-center gap-3 py-5 md:py-6">
                            <span class="text-xs font-bold text-lime-300">01</span>
                            <p class="text-sm font-semibold text-white/80">Nota dibuat, pembayaran tercatat</p>
                        </div>
                        <div class="flex items-center gap-3 border-t border-white/10 py-5 md:border-l md:border-t-0 md:pl-8 md:py-6">
                            <span class="text-xs font-bold text-cyan-300">02</span>
                            <p class="text-sm font-semibold text-white/80">Produksi berjalan, QC menjaga mutu</p>
                        </div>
                        <div class="flex items-center gap-3 border-t border-white/10 py-5 md:border-l md:border-t-0 md:pl-8 md:py-6">
                            <span class="text-xs font-bold text-emerald-300">03</span>
                            <p class="text-sm font-semibold text-white/80">Owner melihat semuanya, tanpa tebak-tebakan</p>
                        </div>
                    </div>
                </section>
            </section>
            <section id="fitur" class="mx-auto max-w-7xl scroll-mt-8 px-5 py-20 sm:px-8 sm:py-24 lg:py-28">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">Satu sistem, semua peran</p>
                    <h2 class="mt-4 text-balance text-3xl font-extrabold tracking-[-0.035em] text-ink sm:text-4xl lg:text-5xl">
                        Yang biasanya tercecer, sekarang jadi satu alur.
                    </h2>
                    <p class="mt-5 max-w-2xl text-pretty text-base leading-7 text-muted sm:text-lg">
                        Nggak perlu lagi cari nota di grup chat, menghitung ulang kas di akhir shift, atau menebak order mana yang sudah siap. Semua langkah punya tempatnya.
                    </p>
                </div>

                <div class="mt-12 grid gap-4 lg:grid-cols-12 lg:gap-5">
                    <article class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-brand-600/30 hover:shadow-xl hover:shadow-slate-200/60 sm:p-8 lg:col-span-7">
                        <div class="flex items-start justify-between gap-6">
                            <div class="max-w-md">
                                <span class="grid size-11 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 8.5A2.5 2.5 0 0 1 7.5 6h9A2.5 2.5 0 0 1 19 8.5v7a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 15.5v-7Z" stroke="currentColor" stroke-width="1.6"/>
                                        <path d="M8.5 11.5h7M8.5 14.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <h3 class="mt-5 text-xl font-bold tracking-tight sm:text-2xl">Kasir yang nggak bikin antre</h3>
                                <p class="mt-3 text-sm leading-6 text-muted sm:text-base">
                                    Cari pelanggan, susun quote layanan, catat tunai atau transfer, lalu cetak nota. Satu layar, alur yang jelas.
                                </p>
                            </div>
                            <span class="hidden rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 sm:inline-flex">Front desk</span>
                        </div>

                        <div class="mt-7 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 sm:p-4" aria-hidden="true">
                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200/70">
                                <span class="text-xs font-semibold text-slate-600">Rina Anggraini</span>
                                <span class="text-[10px] font-bold text-emerald-700">Pelanggan ditemukan</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200/70">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Layanan</p>
                                    <p class="mt-1 text-xs font-bold">Cuci + setrika</p>
                                </div>
                                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200/70">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Pembayaran</p>
                                    <p class="mt-1 text-xs font-bold">Tunai · Rp84.000</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="relative overflow-hidden rounded-3xl bg-[#061225] p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8 lg:col-span-5">
                        <div aria-hidden="true" class="absolute -right-16 -top-16 size-52 rounded-full bg-cyan-400/20 blur-2xl"></div>
                        <div class="relative">
                            <span class="grid size-11 place-items-center rounded-2xl bg-white/10 text-cyan-300 ring-1 ring-inset ring-white/10">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 6.5h10M7 12h10M7 17.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    <path d="m15.5 16.5 1.5 1.5 3-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <h3 class="mt-5 text-xl font-bold tracking-tight sm:text-2xl">Produksi yang kelihatan</h3>
                            <p class="mt-3 text-sm leading-6 text-white/60 sm:text-base">
                                Task per bag, QC, kesiapan, sampai serah terima. Kalau ada komplain, jejaknya tetap ada.
                            </p>

                            <div class="mt-7 space-y-2" aria-hidden="true">
                                <div class="flex items-center justify-between rounded-xl bg-white/[0.06] px-4 py-3 ring-1 ring-inset ring-white/10">
                                    <span class="text-xs font-semibold text-white/80">Cuci · 3 kg</span>
                                    <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 text-[9px] font-bold text-emerald-300">Selesai</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-white/[0.06] px-4 py-3 ring-1 ring-inset ring-white/10">
                                    <span class="text-xs font-semibold text-white/80">Setrika · 3 kg</span>
                                    <span class="rounded-full bg-amber-400/15 px-2 py-0.5 text-[9px] font-bold text-amber-300">Berjalan</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-lime-300 px-4 py-3 text-[#061225]">
                                    <span class="text-xs font-bold">QC akhir</span>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Berikutnya</span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-brand-600/30 hover:shadow-xl hover:shadow-slate-200/60 sm:p-8 lg:col-span-5">
                        <span class="grid size-11 place-items-center rounded-2xl bg-amber-50 text-amber-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4.5 8.5h15v9h-15v-9Z" stroke="currentColor" stroke-width="1.6"/>
                                <path d="M7.5 8.5V7A2.5 2.5 0 0 1 10 4.5h4A2.5 2.5 0 0 1 16.5 7v1.5M8 13h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3 class="mt-5 text-xl font-bold tracking-tight sm:text-2xl">Kas yang bisa dipertanggungjawabkan</h3>
                        <p class="mt-3 text-sm leading-6 text-muted sm:text-base">
                            Buka shift, catat kas fisik, tutup shift. Selisih langsung kelihatan, bukan jadi misteri akhir bulan.
                        </p>
                        <div class="mt-7 grid grid-cols-2 gap-2" aria-hidden="true">
                            <div class="rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200/70">
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Kas fisik</p>
                                <p class="mt-1 text-sm font-extrabold">Rp1.250.000</p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-3 ring-1 ring-inset ring-emerald-200/70">
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-emerald-700/60">Selisih</p>
                                <p class="mt-1 text-sm font-extrabold text-emerald-700">Rp0</p>
                            </div>
                        </div>
                    </article>

                    <article class="relative overflow-hidden rounded-3xl border border-slate-200 bg-[#eef4ff] p-6 shadow-sm sm:p-8 lg:col-span-7">
                        <div aria-hidden="true" class="absolute -bottom-16 -right-10 size-56 rounded-full bg-white/70 blur-2xl"></div>
                        <div class="relative flex flex-col justify-between gap-7 sm:flex-row sm:items-end">
                            <div class="max-w-md">
                                <span class="grid size-11 place-items-center rounded-2xl bg-white text-brand-600 shadow-sm">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M4.5 18.5V13m5 5.5V9m5 9.5V5m5 13.5v-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <h3 class="mt-5 text-xl font-bold tracking-tight sm:text-2xl">Kontrol owner tanpa buka banyak tab</h3>
                                <p class="mt-3 text-sm leading-6 text-muted sm:text-base">
                                    Cabang, staf, akses, layanan, harga, dan pelanggan. Semua keputusan operasional ada dalam satu konsol.
                                </p>
                            </div>
                            <div class="grid shrink-0 grid-cols-3 gap-2" aria-hidden="true">
                                <div class="rounded-2xl bg-white/80 p-3 text-center shadow-sm ring-1 ring-white">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Cabang</p>
                                    <p class="mt-1 text-lg font-extrabold">3</p>
                                </div>
                                <div class="rounded-2xl bg-white/80 p-3 text-center shadow-sm ring-1 ring-white">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Staf</p>
                                    <p class="mt-1 text-lg font-extrabold">12</p>
                                </div>
                                <div class="rounded-2xl bg-white/80 p-3 text-center shadow-sm ring-1 ring-white">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Role</p>
                                    <p class="mt-1 text-lg font-extrabold">4</p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Riwayat order', 'Cari nota dan lihat perjalanan order tanpa buka buku.'],
                        ['Komplain & rewash', 'Catat masalah, jadwalkan ulang, dan tutup dengan bukti.'],
                        ['Akses berbasis peran', 'Owner, kasir, dan operator melihat yang relevan saja.'],
                        ['Multi-cabang', 'Satu akun owner untuk mengawal beberapa lokasi.'],
                    ] as [$title, $copy])
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 2.6 2.6 6.4-6.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-bold">{{ $title }}</p>
                                <p class="mt-1 text-xs leading-5 text-muted">{{ $copy }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section id="alur" class="scroll-mt-8 border-y border-slate-200 bg-canvas">
                <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24 lg:py-28">
                    <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">Alur kerja</p>
                            <h2 class="mt-4 text-balance text-3xl font-extrabold tracking-[-0.035em] text-ink sm:text-4xl lg:text-5xl">
                                Empat langkah. Satu cerita yang utuh.
                            </h2>
                            <p class="mt-5 text-pretty text-base leading-7 text-muted">
                                Setiap perpindahan order tercatat. Jadi saat pelanggan bertanya, jawabannya ada—bukan “coba saya cek dulu”.
                            </p>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="group mt-8 inline-flex items-center gap-2 text-sm font-bold text-brand-600">
                                    Mulai alurnya sekarang
                                    <svg class="size-4 transition-transform group-hover:translate-x-1" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M4.167 10h11.666M10.833 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @endif
                        </div>

                        <ol class="relative space-y-3">
                            <div aria-hidden="true" class="absolute bottom-8 left-[1.65rem] top-8 hidden w-px bg-slate-300 sm:block"></div>

                            @foreach ([
                                ['01', 'Nota dibuat', 'Pelanggan, layanan, estimasi, dan total dihitung di kasir. Tidak ada nota yang hilang di chat.'],
                                ['02', 'Pembayaran tercatat', 'Tunai, transfer, atau DP. Kas shift ikut tercatat otomatis.'],
                                ['03', 'Produksi & QC', 'Operator menerima bag, menjalankan task, dan memastikan hasil lolos QC.'],
                                ['04', 'Serah terima', 'Order siap diambil, bukti tercatat, dan riwayat pelanggan tetap tersimpan.'],
                            ] as [$number, $title, $copy])
                                <li class="relative flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:gap-5 sm:p-5">
                                    <span class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full bg-[#061225] text-[11px] font-bold text-lime-300 ring-4 ring-canvas sm:size-11 sm:text-xs">
                                        {{ $number }}
                                    </span>
                                    <div class="min-w-0 pt-0.5 sm:pt-1">
                                        <h3 class="text-base font-bold sm:text-lg">{{ $title }}</h3>
                                        <p class="mt-1.5 text-sm leading-6 text-muted">{{ $copy }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </section>

            <section id="peran" class="mx-auto max-w-7xl scroll-mt-8 px-5 py-20 sm:px-8 sm:py-24 lg:py-28">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">Untuk siapa</p>
                        <h2 class="mt-4 text-balance text-3xl font-extrabold tracking-[-0.035em] text-ink sm:text-4xl lg:text-5xl">
                            Satu tim. Tiga cara pandang.
                        </h2>
                    </div>
                    <p class="max-w-sm text-sm leading-6 text-muted">
                        Tidak semua orang perlu melihat semuanya. NetClean membagi informasi sesuai peran dan kebutuhan kerja.
                    </p>
                </div>

                <div class="mt-12 grid gap-4 lg:grid-cols-3">
                    <article class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-xl hover:shadow-slate-200/60 sm:p-7">
                        <div class="flex items-center justify-between">
                            <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4.5 19.5v-1.7a4.3 4.3 0 0 1 4.3-4.3h6.4a4.3 4.3 0 0 1 4.3 4.3v1.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/>
                                </svg>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Owner</span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold">Pegang kendali, bukan spreadsheet.</h3>
                        <ul class="mt-5 space-y-3 text-sm text-muted">
                            @foreach (['Pantau cabang dan tim', 'Atur layanan dan harga', 'Kelola akses staf'] as $item)
                                <li class="flex items-center gap-2.5">
                                    <svg class="size-4 shrink-0 text-brand-600" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </article>

                    <article class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-xl hover:shadow-slate-200/60 sm:p-7">
                        <div class="flex items-center justify-between">
                            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 8.5A2.5 2.5 0 0 1 7.5 6h9A2.5 2.5 0 0 1 19 8.5v7a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 15.5v-7Z" stroke="currentColor" stroke-width="1.6"/>
                                    <path d="M8.5 11.5h7M8.5 14.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Kasir</span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold">Layani cepat, catat rapi.</h3>
                        <ul class="mt-5 space-y-3 text-sm text-muted">
                            @foreach (['Buat order dan quote', 'Catat pembayaran', 'Buka dan tutup shift'] as $item)
                                <li class="flex items-center gap-2.5">
                                    <svg class="size-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </article>

                    <article class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-xl hover:shadow-slate-200/60 sm:p-7">
                        <div class="flex items-center justify-between">
                            <span class="grid size-12 place-items-center rounded-2xl bg-amber-50 text-amber-700">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 6.5h10M7 12h10M7 17.5h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    <path d="m15.5 16.5 1.5 1.5 3-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Operator</span>
                        </div>
                        <h3 class="mt-6 text-xl font-bold">Kerja jelas, hasil terjaga.</h3>
                        <ul class="mt-5 space-y-3 text-sm text-muted">
                            @foreach (['Terima bag dan jalankan task', 'Lakukan QC sebelum siap', 'Serah terima dengan catatan'] as $item)
                                <li class="flex items-center gap-2.5">
                                    <svg class="size-4 shrink-0 text-amber-600" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10.5 3.2 3.2 7.8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </article>
                </div>

                <div class="mt-14 overflow-hidden rounded-3xl bg-[#061225] px-6 py-10 text-white sm:px-10 sm:py-12 lg:flex lg:items-center lg:justify-between lg:gap-10">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">Dibuat untuk ritme laundry</p>
                        <p class="mt-4 text-balance text-2xl font-bold tracking-tight sm:text-3xl">
                            Satu sumber kebenaran, dari meja kasir sampai meja lipat.
                        </p>
                    </div>
                    <div class="mt-7 flex flex-wrap gap-2 lg:mt-0 lg:shrink-0">
                        @foreach (['Order', 'Pembayaran', 'Produksi', 'QC', 'Serah terima'] as $item)
                            <span class="rounded-full border border-white/10 bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-white/70">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="faq" class="scroll-mt-8 border-t border-slate-200 bg-white">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-8 sm:py-24 lg:grid-cols-[0.75fr_1.25fr] lg:gap-20 lg:py-28">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-600">FAQ</p>
                        <h2 class="mt-4 text-balance text-3xl font-extrabold tracking-[-0.035em] text-ink sm:text-4xl">
                            Hal yang sering ditanyakan.
                        </h2>
                        <p class="mt-5 text-sm leading-6 text-muted">
                            Belum menemukan jawabannya? Mulai dengan membuat akun owner dan lihat sendiri alurnya.
                        </p>
                    </div>

                    <div class="divide-y divide-slate-200 border-y border-slate-200">
                        @foreach ([
                            ['Apakah NetClean bisa dipakai untuk beberapa cabang?', 'Bisa. Owner dapat mengelola cabang, staf, layanan, dan harga dari satu akun, dengan data operasional yang tetap terpisah per lokasi.'],
                            ['Perangkat apa yang dibutuhkan?', 'Owner cukup memakai browser modern di laptop atau ponsel. Staf operasional menggunakan aplikasi Android untuk kasir dan produksi.'],
                            ['Apakah pembayaran tunai dan transfer didukung?', 'Ya. Kasir dapat mencatat pembayaran tunai maupun transfer, termasuk DP. Shift kas membantu menjaga uang fisik tetap cocok dengan catatan.'],
                            ['Bagaimana kalau pelanggan komplain?', 'Komplain dicatat dari order, lalu bisa diproses menjadi rewash dengan task baru. Riwayatnya tetap terhubung ke order asal.'],
                            ['Apakah data order dan pelanggan tersimpan?', 'Setiap order, pembayaran, task produksi, dan serah terima tercatat dalam sistem, sehingga bisa dicari kembali saat dibutuhkan.'],
                        ] as [$question, $answer])
                            <details class="group py-5">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-left text-base font-bold text-ink [&::-webkit-details-marker]:hidden">
                                    {{ $question }}
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 transition group-open:rotate-45 group-open:border-brand-600 group-open:text-brand-600">
                                        <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4.167v11.666M4.167 10h11.666" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    </span>
                                </summary>
                                <p class="mt-3 max-w-2xl pr-12 text-sm leading-6 text-muted">{{ $answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="px-5 pb-20 sm:px-8 sm:pb-24">
                <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-[#061225] px-6 py-14 text-center text-white sm:px-12 sm:py-20">
                    <div aria-hidden="true" class="landing-grid absolute inset-0 opacity-60"></div>
                    <div aria-hidden="true" class="absolute -left-20 -top-24 size-72 rounded-full bg-brand-600/30 blur-3xl"></div>
                    <div aria-hidden="true" class="absolute -bottom-24 -right-16 size-72 rounded-full bg-lime-300/15 blur-3xl"></div>

                    <div class="relative mx-auto max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">Mulai dari satu cabang</p>
                        <h2 class="mt-4 text-balance text-3xl font-extrabold tracking-[-0.04em] sm:text-5xl">
                            Operasional lebih ringan. Tim lebih tenang.
                        </h2>
                        <p class="mx-auto mt-5 max-w-xl text-pretty text-sm leading-6 text-white/60 sm:text-base">
                            Buat akun owner, atur cabang pertama, dan jalankan alur kasir sampai serah terima dalam satu sistem.
                        </p>
                        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="group inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-lime-300 px-6 py-3 text-sm font-bold text-[#061225] transition hover:-translate-y-0.5 hover:bg-lime-200">
                                    Buat akun owner
                                    <svg class="size-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.167 10h11.666M10.833 5l5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            @endif
                            <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-white/15 bg-white/[0.04] px-6 py-3 text-sm font-semibold text-white/80 transition hover:border-white/30 hover:bg-white/[0.08] hover:text-white">
                                Masuk ke konsol
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8">
                <div class="flex flex-col gap-10 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-xs">
                        <a href="/" class="inline-flex items-center gap-3 rounded-xl" aria-label="NetClean, kembali ke beranda">
                            <span class="grid size-9 place-items-center rounded-xl bg-[#061225] text-sm font-black text-lime-300">N</span>
                            <span class="text-base font-bold tracking-tight">NetClean</span>
                        </a>
                        <p class="mt-4 text-sm leading-6 text-muted">
                            Operasional laundry yang rapi, dari nota pertama sampai serah terima terakhir.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-10 text-sm sm:gap-16">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Produk</p>
                            <ul class="mt-4 space-y-3 font-medium text-muted">
                                <li><a href="#fitur" class="transition hover:text-ink">Fitur</a></li>
                                <li><a href="#alur" class="transition hover:text-ink">Alur kerja</a></li>
                                <li><a href="#peran" class="transition hover:text-ink">Untuk siapa</a></li>
                                <li><a href="#faq" class="transition hover:text-ink">FAQ</a></li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Mulai</p>
                            <ul class="mt-4 space-y-3 font-medium text-muted">
                                @if (Route::has('register'))
                                    <li><a href="{{ route('register') }}" class="transition hover:text-ink">Daftar owner</a></li>
                                @endif
                                <li><a href="{{ route('login') }}" class="transition hover:text-ink">Masuk</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ now()->year }} NetClean. Dibangun untuk operasional laundry.</p>
                    <p class="inline-flex items-center gap-2">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Web owner &amp; Android staf
                    </p>
                </div>
            </div>
        </footer>
    </body>
</html>
