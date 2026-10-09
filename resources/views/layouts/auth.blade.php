<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0b0c0b]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0b0c0b">
    <title>@yield('title') — Certicode Labs</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/brand.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="cc-page min-h-full flex selection:bg-[#3ecf8e]/25 selection:text-white">
    <a href="#auth-main" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[60] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#3ecf8e] focus:text-[#06150e] focus:font-semibold">Skip to form</a>
    <div class="cc-grain" aria-hidden="true"></div>

    {{-- Brand side: hidden on small screens --}}
    <aside class="relative hidden lg:flex lg:w-[52%] xl:w-[55%] flex-col justify-between overflow-hidden border-r border-[var(--cc-line)] p-12 xl:p-16">
        <div class="absolute inset-0 cc-atmosphere" aria-hidden="true"></div>
        <div class="absolute inset-0 cc-grid" aria-hidden="true"></div>

        <a href="/" class="relative z-10 inline-flex items-center gap-2.5 self-start group" aria-label="Certicode Labs home">
            <x-logo-mark class="w-7 h-7 text-[#ececea] transition-transform duration-300 group-hover:-translate-y-px" />
            <span class="cc-display text-[17px] font-semibold">Certicode <span class="text-[var(--cc-text-dim)] font-medium">Labs</span></span>
        </a>

        <div class="relative z-10 max-w-[520px]">
            @hasSection('aside')
                @yield('aside')
            @else
                <h1 class="cc-display cc-rise text-5xl xl:text-6xl font-bold leading-[1.02] cc-balance" style="--d:0">
                    See how your students <span class="cc-serif text-[#3ecf8e] font-normal text-[1.1em]">actually</span> code.
                </h1>
                <p class="cc-rise mt-6 text-lg text-[var(--cc-text-dim)] leading-relaxed max-w-[44ch] cc-pretty" style="--d:1">
                    Live Java labs in VS Code, graded against your rubric and certified when students clear the bar.
                </p>
            @endif

            {{-- Product glimpse --}}
            <div class="relative mt-14 h-[190px] cc-rise" style="--d:2" aria-hidden="true">
                <div class="cc-panel cc-float absolute left-0 top-6 w-[270px] rounded-xl p-4 rotate-[-1.5deg]">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-medium text-[var(--cc-text-dim)]">Instructor monitor</span>
                        <span class="cc-mono text-[11px] text-[#3ecf8e]">18:42 left</span>
                    </div>
                    <ul class="mt-3 space-y-2.5 text-[13px]">
                        <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>Amara Okafor</span><span class="cc-mono text-[11px] text-[var(--cc-text-faint)]">3/4 tasks</span></li>
                        <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>Diego Ramos</span><span class="cc-mono text-[11px] text-[var(--cc-text-faint)]">2/4 tasks</span></li>
                        <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#f5c37a]"></span>Lin Wei</span><span class="cc-mono text-[11px] text-[#f5c37a]">paste flagged</span></li>
                    </ul>
                </div>
                <div class="cc-panel cc-float-slow absolute left-[258px] -top-4 w-[210px] rounded-xl p-4 rotate-[3deg]">
                    <div class="flex items-center gap-2 text-[12px] text-[var(--cc-text-dim)]">
                        <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="m9 14.5-2 7 5-2.5 5 2.5-2-7"/></svg>
                        Certificate issued
                    </div>
                    <div class="cc-mono mt-2 text-[15px] tracking-wide text-white">CERT-4713-QX</div>
                    <div class="mt-1 text-[12px] text-[var(--cc-text-faint)]">Score 91.4% &middot; verifiable</div>
                </div>
            </div>
        </div>

        <p class="relative z-10 text-sm text-[var(--cc-text-faint)]">&copy; {{ date('Y') }} Certicode Labs</p>
    </aside>

    {{-- Form side --}}
    <main id="auth-main" class="relative flex-1 flex flex-col min-h-screen">
        <div class="absolute inset-0 lg:hidden cc-atmosphere opacity-60" aria-hidden="true"></div>

        <div class="relative flex items-center justify-between px-6 sm:px-10 pt-8">
            <a href="/" class="inline-flex items-center gap-2.5 whitespace-nowrap lg:invisible" aria-label="Certicode Labs home">
                <x-logo-mark class="w-6 h-6 text-[#ececea]" />
                <span class="cc-display text-base font-semibold">Certicode <span class="text-[var(--cc-text-dim)] font-medium">Labs</span></span>
            </a>
            @hasSection('topLink')
                @yield('topLink')
            @endif
        </div>

        <div class="relative flex-1 flex items-center justify-center px-6 sm:px-10 py-12">
            <div class="w-full max-w-[400px]">
                @yield('content')
            </div>
        </div>
    </main>

    @stack('scripts')
</body>
</html>
