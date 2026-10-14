<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0b0c0b] dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Certificates carry student names; keep them out of search engines --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b0c0b">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title') — Certicode Labs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/brand.css'])
    @stack('head')
</head>
<body class="cc-page min-h-full selection:bg-[#3ecf8e]/25 selection:text-white">
    <header class="border-b border-[#2e2e2e]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 h-15 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5" aria-label="Certicode Labs home">
                <x-logo-mark class="w-[22px] h-[22px] text-[#ececea]" />
                <span class="text-[15px] font-semibold tracking-tight">Certicode <span class="text-[#888888] font-medium">Labs</span></span>
            </a>
            <span class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Certificate verification</span>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 sm:px-6 py-8 sm:py-10">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
