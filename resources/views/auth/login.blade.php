@extends('layouts.auth', [
    'title' => 'Masuk - NetClean',
    'heading' => 'Masuk ke konsol',
    'subheading' => 'Gunakan akun staf atau pemilik yang sudah terdaftar pada organisasi Anda.',
])

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="grid gap-4">
        @csrf
        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                class="min-h-12 rounded-control border border-slate-400 bg-white px-4 text-base font-normal outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
        </label>
        <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">Password
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="min-h-12 rounded-control border border-slate-400 bg-white px-4 text-base font-normal outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
        </label>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex min-h-12 items-center gap-2 text-sm text-muted">
                <input name="remember" type="checkbox" value="1" class="size-5 rounded border-slate-400">
                Ingat saya
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">Lupa password?</a>
        </div>

        <button type="submit" class="mt-2 min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white transition hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
            Masuk
        </button>
    </form>

    <div class="mt-6 border-t border-divider pt-5 text-center text-sm text-muted">
        Belum punya akun?
        <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:text-brand-800">Daftar organisasi baru</a>
    </div>
@endsection
