<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','TreeFund') · TreeFund</title><link rel="icon" href="{{ asset('assets/logo.png') }}"><link rel="preload" href="/assets/fonts/material-symbols-outlined.ttf" as="font" type="font/ttf" crossorigin><link rel="stylesheet" href="/assets/fonts/fonts.css?v=2">@vite(['resources/css/app.css','resources/js/app.js']) @stack('head')</head>
<body class="site-shell">
    <x-public-header/>
    <main class="site-main" id="main-content">
        @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
        @if(session('status'))<div class="flash success" role="status">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
    <x-public-footer/>
    @stack('scripts')
</body></html>
