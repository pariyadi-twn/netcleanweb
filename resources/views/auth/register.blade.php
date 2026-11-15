@extends('layouts.auth', [
    'title' => 'Daftar - NetClean',
    'heading' => 'Buat akun pemilik',
    'subheading' => 'Siapkan organisasi, cabang pertama, dan akun pemilik. Anda dapat menambahkan staf setelah organisasi aktif.',
])

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="grid gap-4">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-semibold text-ink" for="name">Nama pemilik
                <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"
                    class="min-h-12 rounded-control border border-slate-400 px-4 font-normal outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
            </label>
            <label class="grid gap-1.5 text-sm font-semibold text-ink" for="email">Email
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                    class="min-h-12 rounded-control border border-slate-400 px-4 font-normal outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password">Password
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    class="min-h-12 rounded-control border border-slate-400 px-4 font-normal outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                <span class="text-xs font-normal text-muted">Minimal 12 karakter.</span>
            </label>
            <label class="grid gap-1.5 text-sm font-semibold text-ink" for="password_confirmation">Ulangi password
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="min-h-12 rounded-control border border-slate-400 px-4 font-normal outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
            </label>
        </div>

        <div class="rounded-container border border-divider bg-canvas p-4">
            <p class="text-sm font-semibold text-ink">Organisasi dan cabang pertama</p>
            <div class="mt-3 grid gap-4">
                <label class="grid gap-1.5 text-sm font-semibold text-ink">Nama usaha
                    <input name="organization_name" value="{{ old('organization_name') }}" required
                        class="min-h-12 rounded-control border border-slate-400 px-4 font-normal outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-ink">Telepon cabang
                    <input name="branch_phone" value="{{ old('branch_phone') }}" class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-ink">Nama cabang
                    <input name="branch_name" value="{{ old('branch_name') }}" required class="min-h-12 rounded-control border border-slate-400 px-4 font-normal">
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-ink">Alamat cabang
                    <textarea name="branch_address" required class="min-h-24 rounded-control border border-slate-400 px-4 py-3 font-normal">{{ old('branch_address') }}</textarea>
                </label>
            </div>
        </div>

        <label class="flex items-start gap-3 text-sm text-muted">
            <input name="terms" type="checkbox" value="1" required class="mt-1 size-5 rounded border-slate-400">
            Saya memahami data operasional akan disimpan pada tenant organisasi ini.
        </label>

        <button type="submit" class="min-h-12 rounded-control bg-brand-600 px-5 font-semibold text-white transition hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700">
            Buat akun dan organisasi
        </button>
    </form>

    <div class="mt-6 border-t border-divider pt-5 text-center text-sm text-muted">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-800">Masuk</a>
    </div>
@endsection
