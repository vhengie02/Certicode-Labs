<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0b0c0b] text-[#ececea] scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>Certicode Labs — See how your students actually code</title>
    <meta name="description" content="Students write Java in VS Code while Certicode watches the process: camera presence, focus, pasted code. Work is graded against your rubric and earns a verifiable certificate.">
    <meta property="og:title" content="Certicode Labs — See how your students actually code">
    <meta property="og:description" content="Live coding labs with integrity signals, rubric-based AI grading, and verifiable certificates.">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#0b0c0b">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --ink: #0b0c0b;
            --ink-raised: #121412;
            --ink-card: #151815;
            --line: rgba(236, 236, 234, 0.08);
            --line-strong: rgba(236, 236, 234, 0.14);
            --text: #ececea;
            --text-dim: #a6a9a4;
            --text-faint: #6f736d;
            --brand: #3ecf8e;
            --brand-deep: #1f8a5c;
            --brand-glow: rgba(62, 207, 142, 0.16);
            --shadow-tint: 0 30px 80px -30px rgba(8, 40, 26, 0.9), 0 12px 30px -12px rgba(0, 0, 0, 0.6);
        }

        body {
            font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
            background-color: var(--ink);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            font-feature-settings: 'ss01', 'cv11';
        }

        .font-display { font-family: 'Geist', ui-sans-serif, system-ui, sans-serif; letter-spacing: -0.035em; }
        .font-serif-accent { font-family: 'Instrument Serif', Georgia, serif; font-style: italic; font-weight: 400; letter-spacing: -0.01em; }
        .font-code { font-family: 'Geist Mono', ui-monospace, Menlo, monospace; }
        .balance { text-wrap: balance; }
        .pretty { text-wrap: pretty; }

        /* Fixed grain overlay: breaks the digital flatness of the dark canvas */
        .grain {
            position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0.06; mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* Hero atmosphere: offset emerald light + fading engineering grid */
        .hero-atmosphere {
            background:
                radial-gradient(60% 55% at 78% 30%, rgba(62, 207, 142, 0.14) 0%, transparent 70%),
                radial-gradient(40% 40% at 10% 0%, rgba(62, 207, 142, 0.06) 0%, transparent 70%);
        }
        .hero-grid {
            background-image:
                linear-gradient(to right, rgba(236, 236, 234, 0.045) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(236, 236, 234, 0.045) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(70% 60% at 60% 35%, #000 0%, transparent 75%);
            -webkit-mask-image: radial-gradient(70% 60% at 60% 35%, #000 0%, transparent 75%);
        }

        /* Glass panel with an inner hairline to suggest a refracted edge */
        .panel {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.035), rgba(255, 255, 255, 0.01)) , var(--ink-card);
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), var(--shadow-tint);
        }

        /* Spotlight border: lights up under the cursor (position set by the script below) */
        .spotlight { position: relative; isolation: isolate; }
        .spotlight::before {
            content: ''; position: absolute; inset: -1px; border-radius: inherit; padding: 1px; z-index: -1;
            background: radial-gradient(320px circle at var(--mx, 50%) var(--my, 50%), rgba(62, 207, 142, 0.55), transparent 45%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
            opacity: 0; transition: opacity 300ms ease;
        }
        .spotlight:hover::before { opacity: 1; }

        /* Buttons: physical lift on hover, press on click */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            border-radius: 10px; font-weight: 600; font-size: 0.9rem; line-height: 1;
            padding: 0.95rem 1.35rem; cursor: pointer;
            transition: transform 220ms cubic-bezier(.2, .8, .2, 1), background-color 220ms ease, box-shadow 220ms ease, border-color 220ms ease, color 220ms ease;
        }
        .btn:active { transform: translateY(1px) scale(0.985); }
        .btn-brand { background: var(--brand); color: #06150e; box-shadow: 0 10px 30px -10px rgba(62, 207, 142, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.35); }
        .btn-brand:hover { background: #52dba0; transform: translateY(-2px); box-shadow: 0 16px 40px -12px rgba(62, 207, 142, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.35); }
        .btn-quiet { color: var(--text); border: 1px solid var(--line-strong); background: rgba(255, 255, 255, 0.02); }
        .btn-quiet:hover { border-color: rgba(62, 207, 142, 0.45); background: rgba(62, 207, 142, 0.06); transform: translateY(-2px); }
        .btn .arrow { transition: transform 220ms cubic-bezier(.2, .8, .2, 1); }
        .btn:hover .arrow { transform: translateX(3px); }

        .link-underline { background-image: linear-gradient(currentColor, currentColor); background-size: 0% 1px; background-repeat: no-repeat; background-position: 0 100%; transition: background-size 250ms ease, color 200ms ease; }
        .link-underline:hover { background-size: 100% 1px; }

        :focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; border-radius: 6px; }

        /* Staggered entrance */
        @keyframes rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes float-y { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(62, 207, 142, 0.5); } 100% { box-shadow: 0 0 0 10px rgba(62, 207, 142, 0); } }
        .rise { opacity: 0; animation: rise 800ms cubic-bezier(.2, .8, .2, 1) forwards; animation-delay: calc(var(--d, 0) * 90ms); }
        .float-slow { animation: float-y 7s ease-in-out infinite; }
        .float-slower { animation: float-y 9s ease-in-out infinite; animation-delay: -3s; }
        .caret::after { content: ''; display: inline-block; width: 7px; height: 1.05em; margin-left: 1px; vertical-align: text-bottom; background: var(--brand); animation: blink 1.1s steps(1) infinite; }
        .live-dot { animation: pulse-ring 1.8s ease-out infinite; }

        /* Scroll reveal (class added by the observer below) */
        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 900ms cubic-bezier(.2, .8, .2, 1), transform 900ms cubic-bezier(.2, .8, .2, 1); }
        .reveal.is-visible { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .rise, .reveal { opacity: 1 !important; transform: none !important; }
            html { scroll-behavior: auto; }
        }

        .syn-kw { color: #ff8f7a; }
        .syn-type { color: #7cc7ff; }
        .syn-fn { color: #c8a6ff; }
        .syn-str { color: #a8e6b8; }
        .syn-cm { color: #6f736d; font-style: italic; }
        .syn-num { color: #f5c37a; }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-[#3ecf8e]/25 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[60] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#3ecf8e] focus:text-[#06150e] focus:font-semibold">Skip to content</a>
    <div class="grain" aria-hidden="true"></div>

    <!-- Navigation -->
    <header class="sticky top-0 z-40 border-b border-[var(--line)] bg-[#0b0c0b]/75 backdrop-blur-xl">
        <div class="max-w-[1240px] mx-auto px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5 group" aria-label="Certicode Labs home">
                <x-logo-mark class="w-7 h-7 text-[#ececea] transition-transform duration-300 group-hover:-translate-y-px" />
                <span class="font-display text-[17px] font-semibold whitespace-nowrap">Certicode <span class="text-[var(--text-dim)] font-medium">Labs</span></span>
            </a>

            <nav class="flex items-center gap-1 sm:gap-2 whitespace-nowrap" aria-label="Primary">
                <a href="#how-it-works" class="hidden md:inline-flex px-3 py-2 text-sm text-[var(--text-dim)] hover:text-white transition-colors rounded-md">How it works</a>
                <a href="#for-instructors" class="hidden md:inline-flex px-3 py-2 text-sm text-[var(--text-dim)] hover:text-white transition-colors rounded-md">For instructors</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-brand !py-2.5 !px-4 !text-[13px] ml-2">Open dashboard <span class="arrow" aria-hidden="true">&rarr;</span></a>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 text-sm text-[var(--text-dim)] hover:text-white transition-colors rounded-md">Sign in</a>
                    @if (Route::has('register.show'))
                        <a href="{{ route('register.show') }}" class="btn btn-brand !py-2.5 !px-4 !text-[13px] ml-1">Get started</a>
                    @endif
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" class="flex-1">

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 hero-atmosphere" aria-hidden="true"></div>
            <div class="absolute inset-0 hero-grid" aria-hidden="true"></div>

            <div class="relative max-w-[1240px] mx-auto px-6 pt-20 pb-28 lg:pt-28 lg:pb-36 grid grid-cols-1 lg:grid-cols-[1.05fr_1fr] gap-16 lg:gap-10 items-center">
                <!-- Copy -->
                <div class="max-w-[620px] min-w-0">
                    <p class="rise inline-flex items-center gap-2.5 text-[13px] text-[var(--text-dim)]" style="--d:0">
                        <span class="relative flex w-2 h-2 rounded-full bg-[#3ecf8e] live-dot" aria-hidden="true"></span>
                        Live coding labs for Java courses
                    </p>

                    <h1 class="rise font-display mt-6 text-[44px] leading-[1.02] sm:text-6xl lg:text-[76px] lg:leading-[0.98] font-bold balance" style="--d:1">
                        See how your students <span class="font-serif-accent text-[#3ecf8e] font-normal text-[1.12em] leading-none">actually</span> code.
                    </h1>

                    <p class="rise mt-7 text-lg text-[var(--text-dim)] leading-relaxed max-w-[54ch] pretty" style="--d:2">
                        Not just what they turn in. Students write Java in VS Code while you watch the process unfold &mdash; then the work is graded against your rubric and earns a certificate anyone can verify.
                    </p>

                    <div class="rise mt-10 flex flex-col sm:flex-row gap-3" style="--d:3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-brand">Open your dashboard <span class="arrow" aria-hidden="true">&rarr;</span></a>
                        @else
                            <a href="{{ route('register.show') }}" class="btn btn-brand">Create your account <span class="arrow" aria-hidden="true">&rarr;</span></a>
                            <a href="{{ route('login') }}" class="btn btn-quiet">I have an account</a>
                        @endauth
                    </div>

                    <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="rise group mt-10 inline-flex items-center gap-3 text-sm text-[var(--text-dim)] hover:text-white transition-colors" style="--d:4">
                        <span class="w-9 h-9 rounded-lg border border-[var(--line-strong)] bg-[var(--ink-raised)] flex items-center justify-center group-hover:border-[#3ecf8e]/50 transition-colors">
                            <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        </span>
                        <span><span class="link-underline text-[var(--text)]">Get the VS Code extension</span><span class="font-code text-xs text-[var(--text-faint)] ml-2">v1.4.0 &middot; .vsix</span></span>
                    </a>
                </div>

                <!-- Layered product visual -->
                <div class="relative min-w-0 mt-20 lg:mt-0 lg:pl-6 rise" style="--d:2" aria-hidden="true">
                    <!-- Editor -->
                    <div class="panel rounded-2xl overflow-hidden rotate-[-1.2deg] lg:translate-x-4">
                        <div class="flex items-center justify-between px-4 h-11 border-b border-[var(--line)] bg-black/20">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#ff5f57]/80"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#febc2e]/80"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#28c840]/80"></span>
                            </div>
                            <div class="font-code text-[12px] text-[var(--text-faint)]">Student.java &mdash; Lab 3: Exceptions</div>
                            <span class="font-code text-[11px] text-[#3ecf8e]">&#9679; synced</span>
                        </div>
                        <pre class="font-code text-[12.5px] sm:text-[13.5px] leading-[1.75] px-5 py-5 overflow-x-auto text-[#d7d9d4]"><code><span class="syn-cm">// Reject impossible ages before construction</span>
<span class="syn-kw">public class</span> <span class="syn-type">Student</span> {
    <span class="syn-kw">private final</span> <span class="syn-type">String</span> name;
    <span class="syn-kw">private final</span> <span class="syn-type">int</span> age;

    <span class="syn-kw">public</span> <span class="syn-fn">Student</span>(<span class="syn-type">String</span> name, <span class="syn-type">int</span> age)
            <span class="syn-kw">throws</span> <span class="syn-type">InvalidAgeException</span> {
        <span class="syn-kw">if</span> (age &lt; <span class="syn-num">0</span> || age &gt; <span class="syn-num">150</span>) {
            <span class="syn-kw">throw new</span> <span class="syn-type">InvalidAgeException</span>(<span class="syn-str">"Age out of range"</span>);
        }
        <span class="syn-kw">this</span>.name = name;<span class="caret"></span></code></pre>
                    </div>

                    <!-- Instructor monitor card, overlapping the editor -->
                    <div class="panel float-slow absolute -left-2 sm:-left-8 -bottom-16 w-[260px] rounded-xl p-4 rotate-[1.5deg]">
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] font-medium text-[var(--text-dim)]">Instructor monitor</span>
                            <span class="font-code text-[11px] text-[#3ecf8e]">18:42 left</span>
                        </div>
                        <ul class="mt-3 space-y-2.5 text-[13px]">
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>Amara Okafor</span><span class="font-code text-[11px] text-[var(--text-faint)]">3/4 tasks</span></li>
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>Diego Ramos</span><span class="font-code text-[11px] text-[var(--text-faint)]">2/4 tasks</span></li>
                            <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#f5c37a]"></span>Lin Wei</span><span class="font-code text-[11px] text-[#f5c37a]">paste flagged</span></li>
                        </ul>
                    </div>

                    <!-- Certificate stub -->
                    <div class="panel float-slower absolute -right-2 sm:-right-4 -top-20 w-[210px] rounded-xl p-4 rotate-[3deg]">
                        <div class="flex items-center gap-2 text-[12px] text-[var(--text-dim)]">
                            <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="m9 14.5-2 7 5-2.5 5 2.5-2-7"/></svg>
                            Certificate issued
                        </div>
                        <div class="font-code mt-2 text-[15px] tracking-wide text-white">CERT-4713-QX</div>
                        <div class="mt-1 text-[12px] text-[var(--text-faint)]">Score 91.4% &middot; verifiable</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works: a numbered path instead of a card grid -->
        <section id="how-it-works" class="relative border-t border-[var(--line)] scroll-mt-16">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-32">
                <div class="reveal grid grid-cols-1 lg:grid-cols-[0.9fr_1.1fr] gap-10 items-end">
                    <h2 class="font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance">
                        One lab, start to <span class="font-serif-accent font-normal text-[#3ecf8e]">certificate.</span>
                    </h2>
                    <p class="text-[var(--text-dim)] text-lg leading-relaxed max-w-[52ch] lg:justify-self-end pretty">
                        Nothing is simulated. Every signal you see comes from the student&rsquo;s actual session, and every grade comes from the code they actually wrote.
                    </p>
                </div>

                <ol class="mt-16 grid md:grid-cols-3 gap-px rounded-2xl overflow-hidden border border-[var(--line)] bg-[var(--line)]">
                    <li class="reveal bg-[var(--ink)] p-8 lg:p-10" style="transition-delay:0ms">
                        <span class="font-code text-sm text-[#3ecf8e]">01</span>
                        <h3 class="mt-6 text-xl font-semibold">Start with a camera check</h3>
                        <p class="mt-3 text-[15px] text-[var(--text-dim)] leading-relaxed pretty">The student confirms they&rsquo;re present on camera, then the lab opens straight into VS Code with the starter files in place.</p>
                    </li>
                    <li class="reveal bg-[var(--ink)] p-8 lg:p-10" style="transition-delay:120ms">
                        <span class="font-code text-sm text-[#3ecf8e]">02</span>
                        <h3 class="mt-6 text-xl font-semibold">Code while the session is watched</h3>
                        <p class="mt-3 text-[15px] text-[var(--text-dim)] leading-relaxed pretty">Typing pace, focus changes, idle time and pasted blocks stream to the instructor. The student just writes code.</p>
                    </li>
                    <li class="reveal bg-[var(--ink)] p-8 lg:p-10" style="transition-delay:240ms">
                        <span class="font-code text-sm text-[#3ecf8e]">03</span>
                        <h3 class="mt-6 text-xl font-semibold">Graded, then certified</h3>
                        <p class="mt-3 text-[15px] text-[var(--text-dim)] leading-relaxed pretty">The AI scores the submission against your rubric. You can override any grade. Pass the threshold and the certificate is issued.</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- For instructors: zig-zag rows -->
        <section id="for-instructors" class="relative border-t border-[var(--line)] scroll-mt-16 overflow-hidden">
            <div class="absolute -left-40 top-1/3 w-[520px] h-[520px] rounded-full bg-[#3ecf8e]/[0.05] blur-[120px]" aria-hidden="true"></div>
            <div class="relative max-w-[1240px] mx-auto px-6 py-24 lg:py-32 space-y-28 lg:space-y-36">

                <!-- Row 1: integrity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center [&>*]:min-w-0">
                    <div class="reveal max-w-[480px]">
                        <p class="text-sm text-[#3ecf8e] font-medium">Integrity signals</p>
                        <h3 class="font-display mt-4 text-3xl sm:text-4xl font-bold leading-[1.1] balance">Know when a block of code appears out of nowhere.</h3>
                        <p class="mt-5 text-[var(--text-dim)] leading-relaxed pretty">Camera presence, switching away from the editor, long idle stretches and large pastes are flagged as they happen. You see them on the monitor. The student never does.</p>
                    </div>
                    <div class="reveal panel spotlight rounded-2xl p-6 sm:p-8" aria-hidden="true">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Session timeline &middot; Lin Wei</span>
                            <span class="font-code text-[11px] px-2 py-1 rounded-md bg-[#f5c37a]/10 text-[#f5c37a]">1 flag</span>
                        </div>
                        <ol class="mt-6 relative border-l border-[var(--line-strong)] ml-1.5 space-y-5 font-code text-[13px]">
                            <li class="pl-5 relative"><span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-[#3ecf8e]"></span><span class="text-[var(--text-faint)]">09:02</span> <span class="ml-2">Camera presence verified</span></li>
                            <li class="pl-5 relative"><span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-[#3ecf8e]"></span><span class="text-[var(--text-faint)]">09:14</span> <span class="ml-2">Typing at 38 wpm</span></li>
                            <li class="pl-5 relative"><span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-[#a6a9a4]"></span><span class="text-[var(--text-faint)]">09:21</span> <span class="ml-2 text-[var(--text-dim)]">Left the editor for 40s</span></li>
                            <li class="pl-5 relative"><span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-[#f5c37a] shadow-[0_0_0_4px_rgba(245,195,122,0.15)]"></span><span class="text-[var(--text-faint)]">09:23</span> <span class="ml-2 text-[#f5c37a]">Pasted 412 characters into Student.java</span></li>
                        </ol>
                    </div>
                </div>

                <!-- Row 2: grading (visual first) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center [&>*]:min-w-0">
                    <div class="reveal panel spotlight rounded-2xl p-6 sm:p-8 lg:order-1 order-2" aria-hidden="true">
                        <div class="flex items-end justify-between">
                            <div>
                                <span class="text-sm text-[var(--text-dim)]">Rubric score</span>
                                <div class="font-display text-5xl font-bold mt-1 tabular-nums">87<span class="text-2xl text-[var(--text-faint)]">.5%</span></div>
                            </div>
                            <span class="text-[12px] px-2.5 py-1 rounded-md border border-[var(--line-strong)] text-[var(--text-dim)]">Override available</span>
                        </div>
                        <div class="mt-7 space-y-4 text-[13px]">
                            <div><div class="flex justify-between mb-2"><span class="text-[var(--text-dim)]">Validates input before construction</span><span class="font-code tabular-nums">100</span></div><div class="h-1.5 rounded-full bg-white/[0.06] overflow-hidden"><div class="h-full w-full rounded-full bg-gradient-to-r from-[#1f8a5c] to-[#3ecf8e]"></div></div></div>
                            <div><div class="flex justify-between mb-2"><span class="text-[var(--text-dim)]">Custom exception is thrown and handled</span><span class="font-code tabular-nums">92</span></div><div class="h-1.5 rounded-full bg-white/[0.06] overflow-hidden"><div class="h-full w-[92%] rounded-full bg-gradient-to-r from-[#1f8a5c] to-[#3ecf8e]"></div></div></div>
                            <div><div class="flex justify-between mb-2"><span class="text-[var(--text-dim)]">Encapsulation and readability</span><span class="font-code tabular-nums">71</span></div><div class="h-1.5 rounded-full bg-white/[0.06] overflow-hidden"><div class="h-full w-[71%] rounded-full bg-gradient-to-r from-[#1f8a5c] to-[#3ecf8e]"></div></div></div>
                        </div>
                    </div>
                    <div class="reveal max-w-[480px] lg:order-2 order-1 lg:justify-self-end">
                        <p class="text-sm text-[#3ecf8e] font-medium">Rubric grading</p>
                        <h3 class="font-display mt-4 text-3xl sm:text-4xl font-bold leading-[1.1] balance">The AI gets there first. You make the call.</h3>
                        <p class="mt-5 text-[var(--text-dim)] leading-relaxed pretty">Every submission is checked against the criteria you wrote. Code with syntax errors scores zero instead of earning partial credit, and any grade can be overridden with a reason.</p>
                    </div>
                </div>

                <!-- Row 3: certificates -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center [&>*]:min-w-0">
                    <div class="reveal max-w-[480px]">
                        <p class="text-sm text-[#3ecf8e] font-medium">Verifiable certificates</p>
                        <h3 class="font-display mt-4 text-3xl sm:text-4xl font-bold leading-[1.1] balance">Proof that holds up without you in the room.</h3>
                        <p class="mt-5 text-[var(--text-dim)] leading-relaxed pretty">Students who clear your passing threshold get a certificate with a public verification code &mdash; something an employer can check, not a PDF that says &lsquo;trust me&rsquo;.</p>
                    </div>
                    <div class="reveal relative" aria-hidden="true">
                        <div class="panel spotlight rounded-2xl p-8 sm:p-10 rotate-[-1deg]">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[12px] text-[var(--text-faint)]">Certificate of competency</p>
                                    <p class="font-serif-accent text-3xl sm:text-4xl mt-3 text-white">Amara Okafor</p>
                                    <p class="mt-2 text-sm text-[var(--text-dim)]">Object-Oriented Programming in Java &middot; CS 102</p>
                                </div>
                                <svg class="w-10 h-10 text-[#3ecf8e] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="m9 14.5-2 7 5-2.5 5 2.5-2-7"/><path d="m9.5 9 1.7 1.7L14.5 7.5"/></svg>
                            </div>
                            <div class="mt-10 pt-5 border-t border-dashed border-[var(--line-strong)] flex flex-wrap items-center justify-between gap-3 font-code text-[12px]">
                                <span class="text-[var(--text-dim)]">/verify-certificate/<span class="text-white">CERT-4713-QX</span></span>
                                <span class="text-[#3ecf8e]">&#10003; valid</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Capabilities: divided list instead of four equal cards -->
        <section class="border-t border-[var(--line)]">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-28">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-[var(--line)] border-y border-[var(--line)]">
                    <div class="reveal py-8 sm:px-8 first:sm:pl-0">
                        <svg class="w-5 h-5 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 9-4 3 4 3"/><path d="m16 9 4 3-4 3"/><path d="m13.5 5-3 14"/></svg>
                        <h4 class="mt-5 font-semibold">Real VS Code</h4>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Students work in the editor they&rsquo;ll use on the job, not a browser box.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:80ms">
                        <svg class="w-5 h-5 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M9 2h6"/></svg>
                        <h4 class="mt-5 font-semibold">Live labs</h4>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Open a timed window for the whole class with one shared countdown.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:160ms">
                        <svg class="w-5 h-5 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 8h10"/><path d="M7 12h6"/><path d="M5 20l2.5-3H19a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12z"/></svg>
                        <h4 class="mt-5 font-semibold">Team labs</h4>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Shared chat and per-person contribution, so group work stays fair.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:240ms">
                        <svg class="w-5 h-5 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5"/><path d="M4 19h16"/><path d="m8 15 3-4 3 2 5-6"/></svg>
                        <h4 class="mt-5 font-semibold">Similarity checks</h4>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Compare submissions across the cohort to spot near-identical code.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Closing call to action -->
        <section class="relative overflow-hidden border-t border-[var(--line)]">
            <div class="absolute inset-0 bg-[radial-gradient(50%_70%_at_50%_100%,rgba(62,207,142,0.16),transparent_70%)]" aria-hidden="true"></div>
            <div class="relative max-w-[1240px] mx-auto px-6 py-28 lg:py-36 text-center reveal">
                <h2 class="font-display text-4xl sm:text-6xl font-bold leading-[1.02] balance max-w-3xl mx-auto">
                    Grade the <span class="font-serif-accent font-normal text-[#3ecf8e]">process,</span> not just the output.
                </h2>
                <p class="mt-6 text-lg text-[var(--text-dim)] max-w-[46ch] mx-auto pretty">Set up a class, add a lab, and send students the join code.</p>
                <div class="mt-10 flex flex-col sm:flex-row gap-3 justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-brand">Open your dashboard <span class="arrow" aria-hidden="true">&rarr;</span></a>
                    @else
                        <a href="{{ route('register.show') }}" class="btn btn-brand">Create your account <span class="arrow" aria-hidden="true">&rarr;</span></a>
                        <a href="{{ route('login') }}" class="btn btn-quiet">Sign in</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-[var(--line)]">
        <div class="max-w-[1240px] mx-auto px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-[var(--text-faint)]">
            <div class="flex items-center gap-2.5">
                <x-logo-mark class="w-4 h-4 text-[#ececea]" />
                <span>&copy; {{ date('Y') }} Certicode Labs</span>
            </div>
            <nav class="flex items-center gap-6" aria-label="Footer">
                <a href="#how-it-works" class="link-underline hover:text-white transition-colors">How it works</a>
                <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="link-underline hover:text-white transition-colors">VS Code extension</a>
                <a href="{{ route('login') }}" class="link-underline hover:text-white transition-colors">Sign in</a>
            </nav>
        </div>
    </footer>

    <script>
        // Scroll reveals
        (() => {
            const items = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window)) { items.forEach(el => el.classList.add('is-visible')); return; }
            const io = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
                });
            }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
            items.forEach(el => io.observe(el));
        })();

        // Spotlight borders follow the cursor
        document.querySelectorAll('.spotlight').forEach(card => {
            card.addEventListener('pointermove', e => {
                const r = card.getBoundingClientRect();
                card.style.setProperty('--mx', `${e.clientX - r.left}px`);
                card.style.setProperty('--my', `${e.clientY - r.top}px`);
            });
        });
    </script>
</body>
</html>
