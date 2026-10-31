@extends('layouts.auth', ['title' => 'Reset Password', 'heading' => 'Reset Password'])

@section('content')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input name="token" type="hidden" value="{{ $token }}">

        <p>
            <label for="email">Email</label><br>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
        </p>

        <p>
            <label for="password">Password baru</label><br>
            <input id="password" name="password" type="password" required autocomplete="new-password">
        </p>

        <p>
            <label for="password_confirmation">Ulangi password baru</label><br>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </p>

        <button type="submit">Simpan password</button>
    </form>
@endsection
