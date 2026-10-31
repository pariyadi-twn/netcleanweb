@extends('layouts.auth', ['title' => 'Masuk', 'heading' => 'Masuk'])

@section('content')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <p>
            <label for="email">Email</label><br>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </p>

        <p>
            <label for="password">Password</label><br>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </p>

        <p>
            <label><input name="remember" type="checkbox" value="1"> Ingat saya</label>
        </p>

        <button type="submit">Masuk</button>
    </form>

    <p><a href="{{ route('password.request') }}">Lupa password?</a></p>
@endsection
