@props(['title' => 'Billing'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Billing' }} · Lone Oak Property Tax</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="{{ route('clients.index') }}" class="brand">Lone Oak Billing</a>
            <nav>
                <a href="{{ route('clients.index') }}" @class(['active' => request()->routeIs('clients.*')])>Clients</a>
                <a href="{{ route('outcomes.create') }}" @class(['active' => request()->routeIs('outcomes.*')])>Record outcome</a>
            </nav>
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
