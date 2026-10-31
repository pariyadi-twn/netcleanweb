@extends('layouts.auth', ['title' => 'Aktifkan MFA', 'heading' => 'Aktifkan MFA'])

@section('content')
    <p>Tambahkan akun ini ke aplikasi authenticator menggunakan secret berikut.</p>

    <p><code style="word-break: break-all;">{{ $secret }}</code></p>

    <p>
        <a href="{{ $provisioning_uri }}">Buka tautan konfigurasi authenticator</a>
    </p>

    <form method="POST" action="{{ route('mfa.setup.confirm') }}">
        @csrf

        <p>
            <label for="code">Kode verifikasi</label><br>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus>
        </p>

        <button type="submit">Aktifkan</button>
    </form>
@endsection
