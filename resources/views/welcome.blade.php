<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0b0c0b] text-[#ececea] scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>Certicode Labs | See how your students actually code</title>
    <meta name="description" content="Students write Java in VS Code while Certicode watches the process: camera presence, focus, pasted code. Work is graded against your rubric and earns a verifiable certificate.">
    <meta property="og:title" content="Certicode Labs | See how your students actually code">
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

        /* Hero atmosphere: offset emerald light */
        .hero-atmosphere {
            background:
                radial-gradient(60% 55% at 78% 30%, rgba(62, 207, 142, 0.14) 0%, transparent 70%),
                radial-gradient(40% 40% at 10% 0%, rgba(62, 207, 142, 0.06) 0%, transparent 70%);
        }

        .hero-shot { -webkit-mask-image: linear-gradient(to bottom, #000 62%, transparent); mask-image: linear-gradient(to bottom, #000 62%, transparent); }

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
        .rise { opacity: 0; animation: rise 800ms cubic-bezier(.2, .8, .2, 1) forwards; animation-delay: calc(var(--d, 0) * 90ms); }

        /* Scroll reveal (class added by the observer below) */
        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 900ms cubic-bezier(.2, .8, .2, 1), transform 900ms cubic-bezier(.2, .8, .2, 1); }
        .reveal.is-visible { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .rise, .reveal { opacity: 1 !important; transform: none !important; }
            html { scroll-behavior: auto; }
        }

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
                <a href="#for-students" class="hidden md:inline-flex px-3 py-2 text-sm text-[var(--text-dim)] hover:text-white transition-colors rounded-md">For students</a>
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

        <!-- Hero: the pitch, then the real instructor monitoring screen it describes -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 hero-atmosphere" aria-hidden="true"></div>

            <div class="relative max-w-[1240px] mx-auto px-6 pt-16 lg:pt-24">
                <div class="max-w-[940px]">
                    <p class="rise text-[13px] font-medium text-[#3ecf8e]" style="--d:0">Live coding labs for Java courses</p>

                    <h1 class="rise font-display mt-5 pb-2 text-[44px] leading-[1.06] sm:text-6xl lg:text-[76px] lg:leading-[1.02] font-bold balance" style="--d:1">
                        See how your students <span class="font-serif-accent text-[#3ecf8e] font-normal text-[1.12em]">actually</span> code.
                    </h1>

                    <p class="rise mt-6 text-lg text-[var(--text-dim)] leading-relaxed max-w-[52ch] pretty" style="--d:2">
                        Students write Java in VS Code. You see how the work happened, grade it against your rubric, and certify it.
                    </p>

                    <div class="rise mt-9 flex flex-col sm:flex-row gap-3" style="--d:3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-brand">Open dashboard <span class="arrow" aria-hidden="true">&rarr;</span></a>
                        @else
                            <a href="{{ route('register.show') }}" class="btn btn-brand">Get started <span class="arrow" aria-hidden="true">&rarr;</span></a>
                        @endauth
                        <a href="#how-it-works" class="btn btn-quiet">How it works</a>
                    </div>
                </div>

                <!-- Real screens, rendered with sample data: the instructor's monitor, and the student's
                     VS Code sidebar in front of it -->
                <figure class="rise relative mt-16 lg:mt-20" style="--d:4">
                    <div class="hero-shot panel rounded-t-2xl overflow-hidden p-1.5 pb-0">
                        <img src="{{ asset('images/landing/instructor-monitoring.jpg') }}"
                             width="1600" height="750"
                             fetchpriority="high" decoding="async"
                             alt="The instructor monitoring screen during a live lab: 18 minutes left, 4 of 6 students connected, 2 integrity flags, and a row per student with typing speed, tasks done, focus lost, paste flags and grade."
                             class="block w-full h-auto rounded-t-xl">
                    </div>
                    <div class="hidden lg:block absolute -right-4 xl:-right-10 top-20 w-[300px] panel rounded-xl overflow-hidden p-1">
                        <img src="{{ asset('images/landing/extension-tasks.jpg') }}"
                             width="760" height="1016" decoding="async"
                             alt="The Certicode sidebar in VS Code: connected, a shared 18:51 clock, and the lab's four tasks, three done and one to do."
                             class="block w-full h-auto rounded-lg">
                    </div>
                    <figcaption class="sr-only">The instructor's monitor and a student's VS Code sidebar during the same live lab</figcaption>
                </figure>
            </div>
        </section>

        <!-- How it works: a numbered path -->
        <section id="how-it-works" class="relative border-t border-[var(--line)] scroll-mt-16">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-32">
                <div class="reveal max-w-[720px]">
                    <h2 class="font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance">
                        One lab, start to <span class="font-serif-accent font-normal text-[#3ecf8e]">certificate.</span>
                    </h2>
                    <p class="mt-5 text-[var(--text-dim)] text-lg leading-relaxed max-w-[56ch] pretty">
                        Every signal comes from the student&rsquo;s real session, and every grade from the code they actually wrote.
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

        <!-- For students: the VS Code extension, one real tab per panel -->
        <section id="for-students" class="relative border-t border-[var(--line)] scroll-mt-16">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-32">
                <div class="reveal max-w-[720px]">
                    <h2 class="font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance">The whole lab lives inside VS Code.</h2>
                    <p class="mt-5 text-[var(--text-dim)] text-lg leading-relaxed max-w-[56ch] pretty">
                        The extension adds one sidebar with the tasks, the shared timer, live standings, their own changes and team chat.
                    </p>
                    <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="mt-7 inline-flex text-sm font-medium text-[#3ecf8e] link-underline">Download the extension (.vsix)</a>
                </div>

                <div class="mt-14 -mx-6 px-6 flex gap-5 overflow-x-auto snap-x snap-mandatory pb-4 md:mx-0 md:px-0 md:grid md:grid-cols-4 md:gap-6 md:overflow-visible md:pb-0 items-start">
                    <figure class="reveal shrink-0 w-[72%] sm:w-[44%] md:w-auto snap-start" style="transition-delay:0ms">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/extension-tasks.jpg') }}" width="760" height="1016" loading="lazy" decoding="async" alt="The Tasks tab: a progress bar at 75% and four lab tasks, three done and one to do." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4">
                            <span class="block font-semibold">Tasks</span>
                            <span class="block mt-1 text-sm text-[var(--text-dim)] leading-relaxed">Marked off each time the grader checks their code.</span>
                        </figcaption>
                    </figure>
                    <figure class="reveal shrink-0 w-[72%] sm:w-[44%] md:w-auto snap-start" style="transition-delay:90ms">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/extension-leaderboard.jpg') }}" width="760" height="1406" loading="lazy" decoding="async" alt="The Standings tab during a live lab: seven students ranked by tasks done, with the shared clock." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4">
                            <span class="block font-semibold">Standings</span>
                            <span class="block mt-1 text-sm text-[var(--text-dim)] leading-relaxed">Ranked by tasks done, then by time taken.</span>
                        </figcaption>
                    </figure>
                    <figure class="reveal shrink-0 w-[72%] sm:w-[44%] md:w-auto snap-start" style="transition-delay:180ms">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/extension-diff.jpg') }}" width="760" height="1130" loading="lazy" decoding="async" alt="The Changes tab: 24 lines added, 5 deleted and 7 modified, broken down across three Java files, with each teammate's share of the work." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4">
                            <span class="block font-semibold">Changes</span>
                            <span class="block mt-1 text-sm text-[var(--text-dim)] leading-relaxed">Every line added and removed, file by file.</span>
                        </figcaption>
                    </figure>
                    <figure class="reveal shrink-0 w-[72%] sm:w-[44%] md:w-auto snap-start" style="transition-delay:270ms">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/extension-chat.jpg') }}" width="760" height="1096" loading="lazy" decoding="async" alt="The Chat tab in a pair lab: two students split the work, one sharing the Java age check as a snippet." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4">
                            <span class="block font-semibold">Chat</span>
                            <span class="block mt-1 text-sm text-[var(--text-dim)] leading-relaxed">For pair labs, with code snippets inline.</span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- For instructors: bento of real review screens -->
        <section id="for-instructors" class="relative border-t border-[var(--line)] scroll-mt-16 overflow-hidden">
            <div class="absolute -left-40 top-1/3 w-[520px] h-[520px] rounded-full bg-[#3ecf8e]/[0.05] blur-[120px]" aria-hidden="true"></div>
            <div class="relative max-w-[1240px] mx-auto px-6 py-24 lg:py-32">
                <h2 class="reveal font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance max-w-[720px]">See the process, not just the result.</h2>

                <div class="mt-14 grid grid-cols-1 lg:grid-cols-5 gap-6 [&>*]:min-w-0">
                    <article class="reveal panel rounded-2xl p-6 sm:p-8 lg:col-span-3">
                        <h3 class="text-xl font-semibold">Every flag, with the moment it happened.</h3>
                        <p class="mt-2 text-[15px] text-[var(--text-dim)] leading-relaxed max-w-[56ch] pretty">Pastes that match nothing in the file or team chat, time away from VS Code, and missing faces on camera.</p>
                        <div class="mt-6 rounded-xl overflow-hidden border border-[var(--line)]"><img src="{{ asset('images/landing/integrity-trail.jpg') }}" width="1100" height="563" loading="lazy" decoding="async" alt="A student's integrity trail: a high-severity paste of 14 lines that matches nothing in the file or chat, and a low-severity 41 seconds away from VS Code." class="block w-full h-auto"></div>
                    </article>

                    <article class="reveal panel rounded-2xl p-6 sm:p-8 lg:col-span-2 lg:row-span-2" style="transition-delay:120ms">
                        <h3 class="text-xl font-semibold">The AI grades first. You make the call.</h3>
                        <p class="mt-2 text-[15px] text-[var(--text-dim)] leading-relaxed pretty">A plain-language reason for every grade, competencies passed or failed, and an override that keeps an audit note.</p>
                        <div class="mt-6 rounded-xl overflow-hidden border border-[var(--line)]"><img src="{{ asset('images/landing/grade-review.jpg') }}" width="1100" height="1238" loading="lazy" decoding="async" alt="The grade review for one submission: 92.5% from the AI, 4 of 4 checks, a plain-language explanation, two competencies passed and a field to override the grade with a reason." class="block w-full h-auto"></div>
                    </article>

                    <article class="reveal panel rounded-2xl p-6 sm:p-8 lg:col-span-3" style="transition-delay:60ms">
                        <h3 class="text-xl font-semibold">Near-identical code, side by side.</h3>
                        <p class="mt-2 text-[15px] text-[var(--text-dim)] leading-relaxed max-w-[56ch] pretty">Submissions are compared by structure, so renaming the variables doesn&rsquo;t hide a copy.</p>
                        <div class="mt-6 rounded-xl overflow-hidden border border-[var(--line)]"><img src="{{ asset('images/landing/code-similarity.jpg') }}" width="1400" height="601" loading="lazy" decoding="async" alt="Two submissions compared side by side and flagged as a 100% structural match: identical logic with the variables renamed." class="block w-full h-auto"></div>
                    </article>
                </div>
            </div>
        </section>

        <!-- The student's side of a course: two screens side by side -->
        <section id="student-journey" class="border-t scroll-mt-16 border-[var(--line)]">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-32">
                <h2 class="reveal font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance max-w-[720px]">Clear for students, lab after lab.</h2>
                <div class="mt-14 grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10 [&>*]:min-w-0">
                    <figure class="reveal">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/student-lab.jpg') }}" width="1600" height="689" loading="lazy" decoding="async" alt="A lab page for a student: instructions, three starter files, a live-now badge with 19 minutes left, and the steps to continue in VS Code after a camera check." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4 text-[15px] text-[var(--text-dim)] leading-relaxed pretty"><span class="font-semibold text-[var(--text)]">Each lab on one page.</span> Instructions, starter files and the tasks the grader checks, then a camera check before VS Code opens.</figcaption>
                    </figure>
                    <figure class="reveal" style="transition-delay:120ms">
                        <div class="panel rounded-xl overflow-hidden p-1"><img src="{{ asset('images/landing/student-progress.jpg') }}" width="1600" height="778" loading="lazy" decoding="async" alt="A class page for a student: the syllabus with completed labs ticked, a lab that is live now, and progress of 67% toward a 75% pass mark." class="block w-full h-auto rounded-lg"></div>
                        <figcaption class="mt-4 text-[15px] text-[var(--text-dim)] leading-relaxed pretty"><span class="font-semibold text-[var(--text)]">Progress they can see.</span> Every module, every finished lab, and how far they are from the pass mark.</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Certificates: text and the real verification page -->
        <section id="certificates" class="relative border-t scroll-mt-16 border-[var(--line)] overflow-hidden">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-32 grid grid-cols-1 lg:grid-cols-[0.8fr_1.2fr] gap-12 lg:gap-16 items-center [&>*]:min-w-0">
                <div class="reveal max-w-[460px]">
                    <h2 class="font-display text-4xl sm:text-5xl font-bold leading-[1.05] balance">A certificate anyone can <span class="font-serif-accent font-normal text-[#3ecf8e]">check.</span></h2>
                    <p class="mt-5 text-[var(--text-dim)] text-lg leading-relaxed pretty">Students who pass get a certificate with their lab record. Scanning its QR code opens a public page that confirms it&rsquo;s genuine.</p>
                </div>
                <div class="reveal panel rounded-2xl overflow-hidden p-1.5" style="transition-delay:120ms"><img src="{{ asset('images/landing/certificate-verified.jpg') }}" width="1300" height="835" loading="lazy" decoding="async" alt="The public verification page: a confirmation that the certificate is genuine, above the certificate itself with the student's name, course, average grade of 90.3% and 3 of 3 labs completed." class="block w-full h-auto rounded-xl"></div>
            </div>
        </section>

        <!-- Capabilities: divided list instead of four equal cards -->
        <section class="border-t border-[var(--line)]">
            <div class="max-w-[1240px] mx-auto px-6 py-24 lg:py-28">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-[var(--line)] border-y border-[var(--line)]">
                    <div class="reveal py-8 sm:px-8 first:sm:pl-0">
                        <h3 class="font-semibold">Real VS Code</h3>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Students work in the editor they&rsquo;ll use on the job, not a browser box.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:80ms">
                        <h3 class="font-semibold">Live or open labs</h3>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">One shared countdown for the whole class, or self-paced labs that are open from the start.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:160ms">
                        <h3 class="font-semibold">Team labs</h3>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Pairs share a chat, and each person&rsquo;s lines are counted toward the team.</p>
                    </div>
                    <div class="reveal py-8 sm:px-8" style="transition-delay:240ms">
                        <h3 class="font-semibold">Competency tracking</h3>
                        <p class="mt-2 text-sm text-[var(--text-dim)] leading-relaxed">Tag labs with competencies. Each score averages the student&rsquo;s best grade on those labs.</p>
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
                        <a href="{{ route('dashboard') }}" class="btn btn-brand">Open dashboard <span class="arrow" aria-hidden="true">&rarr;</span></a>
                    @else
                        <a href="{{ route('register.show') }}" class="btn btn-brand">Get started <span class="arrow" aria-hidden="true">&rarr;</span></a>
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
