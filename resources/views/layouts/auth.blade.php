<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'NetClean' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_0.95fr]">
        <aside class="relative hidden overflow-hidden bg-[#0b1730] px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16">
            <div class="absolute -left-24 top-20 size-80 rounded-full bg-brand-600/30 blur-3xl" aria-hidden="true"></div>
            <div class="absolute -bottom-32 right-0 size-96 rounded-full bg-blue-400/20 blur-3xl" aria-hidden="true"></div>

            <a href="{{ url('/') }}" class="relative flex items-center gap-3 text-lg font-bold tracking-tight">
                <span class="grid size-11 place-items-center rounded-container bg-white text-brand-700" aria-hidden="true">NC</span>
                NetClean
            </a>

            <div class="relative max-w-xl">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-200">Operasional laundry modern</p>
                <h1 class="mt-5 text-4xl font-bold leading-tight tracking-tight xl:text-5xl">Satu alur dari penerimaan sampai serah terima.</h1>
                <p class="mt-5 text-base leading-relaxed text-blue-100">Kelola order, produksi, pembayaran, dan kas tanpa kehilangan konteks cabang atau status cucian.</p>
                <ul class="mt-8 grid gap-4 text-sm text-blue-50">
                    <li class="flex items-start gap-3"><span class="mt-1 grid size-6 shrink-0 place-items-center rounded-full bg-white/10">01</span><span><strong class="block text-white">Kasir cepat</strong>Quote server, pembayaran, nota, dan shift kas dalam satu perangkat.</span></li>
                    <li class="flex items-start gap-3"><span class="mt-1 grid size-6 shrink-0 place-items-center rounded-full bg-white/10">02</span><span><strong class="block text-white">Produksi terpantau</strong>Task, QC, readiness, dan handover dengan bukti penerima.</span></li>
                    <li class="flex items-start gap-3"><span class="mt-1 grid size-6 shrink-0 place-items-center rounded-full bg-white/10">03</span><span><strong class="block text-white">Pemilik tenang</strong>Cabang, staf, katalog, harga, dan pelanggan dalam satu konsol.</span></li>
                </ul>
            </div>

            <p class="relative text-xs text-blue-200/70">NetClean · Konsol operasional tenant</p>
        </aside>

        <main class="flex min-h-screen flex-col px-4 py-6 sm:px-8 lg:px-12">
            <div class="flex items-center justify-between lg:hidden">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold tracking-tight">
                    <span class="grid size-10 place-items-center rounded-container bg-brand-600 text-white" aria-hidden="true">NC</span>
                    NetClean
                </a>
                <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-700">Masuk</a>
            </div>

            <div class="flex flex-1 items-center justify-center py-8">
                <div class="w-full max-w-xl">
                    <div class="rounded-[1.75rem] border border-divider bg-white p-6 shadow-[0_24px_80px_-40px_rgba(15,23,42,0.35)] sm:p-8">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $heading ?? 'NetClean' }}</h2>
                            @isset($subheading)
                                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $subheading }}</p>
                            @endisset
                        </div>

                        @if (session('status'))
                            <div role="status" class="mb-5 rounded-control border border-green-200 bg-success-bg px-4 py-3 text-sm text-success-text">{{ session('status') }}</div>
                        @endif

                        @if ($errors->any())
                            <div role="alert" class="mb-5 rounded-control border border-red-200 bg-danger-bg px-4 py-3 text-sm text-danger-text">
                                <p class="font-semibold">Periksa kembali data berikut.</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @yield('content')
                    </div>

                    <p class="mt-5 text-center text-xs text-muted">Dengan melanjutkan, Anda menyetujui penggunaan data operasional sesuai kebijakan tenant.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
