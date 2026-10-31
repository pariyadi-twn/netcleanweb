@extends('layouts.auth', ['title' => 'Verifikasi MFA', 'heading' => 'Verifikasi MFA'])

@section('content')
    <p>Masukkan kode dari aplikasi authenticator atau salah satu recovery code.</p>

    <form method="POST" action="{{ route('mfa.challenge.verify') }}">
        @csrf

        <p>
            <label for="code">Kode</label><br>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus>
        </p>

        <button type="submit">Verifikasi</button>
    </form>
@endsection
