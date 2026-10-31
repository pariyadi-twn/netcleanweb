<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'NetClean' }}</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 4rem auto; padding: 0 1.5rem; line-height: 1.5;">
    <h1>{{ $heading ?? 'NetClean' }}</h1>

    @if (session('status'))
        <p role="status" style="padding: .75rem; background: #ecfdf3;">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul role="alert" style="padding: .75rem; background: #fef2f2; color: #991b1b;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @yield('content')
</body>
</html>
