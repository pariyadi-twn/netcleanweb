<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 4rem auto; padding: 0 1.5rem; line-height: 1.5;">
    <h1>Dashboard</h1>

    @if (session('status'))
        <p role="status" style="padding: .75rem; background: #ecfdf3;">{{ session('status') }}</p>
    @endif

    @if (session('recovery_codes'))
        <section style="padding: .75rem; background: #fffbeb; border: 1px solid #f59e0b;">
            <h2>Recovery codes</h2>
            <p>Simpan kode ini sekarang. Kode tidak akan ditampilkan lagi.</p>
            <ul>
                @foreach (session('recovery_codes') as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
        </section>
    @endif

    <p>Masuk sebagai {{ $user->name }} ({{ $user->email }}).</p>

    <p><a href="{{ route('mfa.setup') }}">Kelola MFA</a></p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</body>
</html>
