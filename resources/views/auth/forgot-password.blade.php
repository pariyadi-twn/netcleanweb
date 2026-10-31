@extends('layouts.auth', ['title' => 'Lupa Password', 'heading' => 'Lupa Password'])

@section('content')
    <p>Masukkan email akun. Kami akan mengirim tautan reset bila akun tersebut ada.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <p>
            <label for="email">Email</label><br>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </p>

        <button type="submit">Kirim tautan reset</button>
    </form>

    <p><a href="{{ route('login') }}">Kembali ke login</a></p>
@endsection
