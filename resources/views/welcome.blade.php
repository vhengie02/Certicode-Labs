<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certicode Labs - Telemetry-Powered Coding Education & Verification</title>
    <!-- Google Fonts: Inter & Source Code Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Source+Code+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Vite Compiled Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f0f0f;
            color: #ededed;
            -webkit-font-smoothing: antialiased;
        }
        .mono-tag {
            font-family: 'Source Code Pro', monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .bento-card {
            background-color: #171717;
            border: 1px solid #2e2e2e;
            transition: all 0.2s ease-in-out;
        }
        .bento-card:hover {
            border-color: rgba(62, 207, 142, 0.35);
            background-color: #1a1a1a;
        }
        .code-syntax-keyword { color: #ff7b72; }
        .code-syntax-string { color: #a5d6ff; }
        .code-syntax-func { color: #d2a8ff; }
        .code-syntax-comment { color: #8b949e; }
        .code-syntax-brand { color: #3ecf8e; }
    </style>
</head>
<body class="min-h-full flex flex-col justify-between selection:bg-[#3ecf8e]/20 selection:text-[#3ecf8e]">

    <!-- Top Navigation -->
    <header class="w-full max-w-7xl mx-auto px-6 h-20 flex items-center justify-between border-b border-[#232323]">
        <div class="flex items-center gap-3">
            <a href="/" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-[6px] bg-[#171717] border border-[#2e2e2e] group-hover:border-[#3ecf8e]/50 flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <span class="text-lg font-bold tracking-tight text-[#ededed]">
                    Certicode <span class="text-[#3ecf8e]">Labs</span>
                </span>
            </a>
            <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-[4px] bg-[#1a1a1a] border border-[#2e2e2e] text-[10px] font-mono text-[#a3a3a3] uppercase tracking-wider ml-2">
                v2.4 TELEMETRY
            </span>
        </div>

        <nav class="flex items-center space-x-4">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 rounded-[6px] border border-[#2e2e2e] bg-[#171717] text-xs font-semibold text-[#ededed] hover:bg-[#222222] hover:border-[#383838] transition">
                    Dashboard &rarr;
                </a>
            @else
                <a href="{{ route('login') }}" class="text-xs font-medium text-[#a3a3a3] hover:text-[#ededed] transition-colors">
                    Sign In
                </a>
                @if (Route::has('register.show'))
                    <a href="{{ route('register.show') }}" class="inline-flex items-center px-4 py-2 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                        Get Started
                    </a>
                @endif
            @endauth
        </nav>
    </header>

    <!-- Main Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-6 py-16 lg:py-24 space-y-24">
        
        <!-- Hero Section -->
        <section class="text-center max-w-4xl mx-auto space-y-8">
            <!-- Monospace status badge -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-[#2e2e2e] bg-[#171717]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e] animate-pulse"></span>
                <span class="mono-tag text-[11px] text-[#a3a3a3]">Next-Gen Sandbox Telemetry Engine</span>
            </div>

            <!-- Tight 72px Headline -->
            <h1 class="text-4xl sm:text-6xl lg:text-[72px] font-extrabold text-[#ededed] tracking-tight leading-[1.02]">
                See how your students actually code &mdash; <span class="text-[#3ecf8e]">not just what they turn in.</span>
            </h1>

            <!-- Subtitle -->
            <p class="text-base sm:text-xl text-[#a3a3a3] font-normal max-w-2xl mx-auto leading-relaxed">
                Students write Java in a live coding environment connected straight to your rubric. The AI grades against the criteria you set, and once they&rsquo;ve met every requirement, they get a certificate that holds up on its own.
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3 rounded-full bg-[#3ecf8e] text-sm font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                        Launch Console &rarr;
                    </a>
                @else
                    <a href="{{ route('register.show') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3 rounded-full bg-[#3ecf8e] text-sm font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                        Create Developer Account &rarr;
                    </a>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 rounded-[6px] border border-[#2e2e2e] bg-[#171717] text-sm font-medium text-[#ededed] hover:bg-[#222222] hover:border-[#383838] transition">
                        Sign In with Email
                    </a>
                @endauth
            </div>

            <!-- Developer CLI prompt pill -->
            <div class="pt-4 flex items-center justify-center">
                <div class="inline-flex items-center gap-3 px-4 py-2 rounded-[6px] bg-[#141414] border border-[#232323] text-xs font-mono text-[#a3a3a3]">
                    <span class="text-[#3ecf8e] select-none">$</span>
                    <span class="text-[#d1d1d1] select-all">npx @certicode/telemetry init</span>
                    <span class="text-[10px] text-[#666666] uppercase tracking-wider pl-2 border-l border-[#2e2e2e]">VS Code Ready</span>
                </div>
            </div>
        </section>

        <!-- Supabase-Style Bento Grid Showcase -->
        <section class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-end justify-between border-b border-[#232323] pb-4">
                <div>
                    <span class="mono-tag text-xs text-[#3ecf8e]">Architecture</span>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#ededed] mt-1">
                        How it actually runs.
                    </h2>
                    <p class="text-xs text-[#888888] font-mono mt-1">
                        Every submission runs and compiles in a real environment &mdash; nothing is simulated.
                    </p>
                </div>
                <p class="text-xs text-[#888888] font-mono mt-2 md:mt-0">
                    STATUS: REAL-TIME RUNTIME VERIFIED
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                
                <!-- Bento Tile 1: Live Code Editor Mockup (2 Cols) -->
                <div class="md:col-span-2 bento-card rounded-xl p-6 flex flex-col justify-between overflow-hidden">
                    <div>
                        <!-- Editor Top Bar -->
                        <div class="flex items-center justify-between border-b border-[#2e2e2e] pb-3 mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-[#2a2a2a]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#2a2a2a]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#2a2a2a]"></div>
                                <span class="text-xs font-mono text-[#888888] ml-2">sandbox/engine.py</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#3ecf8e]"></span>
                                <span class="mono-tag text-[10px] text-[#3ecf8e]">Live Sandbox</span>
                            </div>
                        </div>

                        <!-- Code Body -->
                        <pre class="font-mono text-xs sm:text-sm text-[#d4d4d4] leading-relaxed overflow-x-auto py-2"><code><span class="code-syntax-comment"># Certicode Verified Execution Engine</span>
<span class="code-syntax-keyword">async def</span> <span class="code-syntax-func">verify_candidate_session</span>(session_id: <span class="code-syntax-keyword">str</span>):
    sandbox = <span class="code-syntax-keyword">await</span> TelemetryCluster.attach(session_id)
    telemetry = <span class="code-syntax-keyword">await</span> sandbox.stream_events()

    <span class="code-syntax-comment"># Real-time integrity assertion</span>
    <span class="code-syntax-keyword">assert</span> telemetry.tab_switches == <span class="code-syntax-string">0</span>
    <span class="code-syntax-keyword">assert</span> telemetry.camera_presence_verified == <span class="code-syntax-keyword">True</span>

    <span class="code-syntax-keyword">return</span> <span class="code-syntax-brand">VerifiedCredential</span>(status=<span class="code-syntax-string">"PASSED"</span>, grade=<span class="code-syntax-string">"A"</span>)</code></pre>
                    </div>

                    <!-- Editor Bottom Execution Tray -->
                    <div class="mt-4 pt-3 border-t border-[#2e2e2e] flex flex-wrap items-center justify-between text-xs font-mono gap-2 text-[#888888]">
                        <div class="flex items-center gap-2">
                            <span class="text-[#3ecf8e] font-bold">✓</span>
                            <span class="text-[#d1d1d1]">14/14 test cases passed</span>
                            <span class="text-[#666666]">(42ms)</span>
                        </div>
                        <span class="text-[11px] text-[#888888]">Memory: 18.2MB • CPU: 1.2%</span>
                    </div>
                </div>

                <!-- Bento Tile 2: Real-Time Integrity Shield (1 Col) -->
                <div class="bento-card rounded-xl p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="mono-tag text-[11px] text-[#a3a3a3]">Integrity Stream</span>
                            <span class="px-2 py-0.5 rounded-[4px] bg-[#141414] border border-[#2e2e2e] text-[10px] font-mono text-[#3ecf8e]">
                                ACTIVE
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-[#ededed] mb-1">Integrity Shield</h3>
                        <p class="text-xs text-[#888888] leading-relaxed mb-4">
                            While a student works, the system checks that they&rsquo;re actually there &mdash; camera presence, whether they&rsquo;ve switched away from the editor, and whether a block of code just appeared out of nowhere. If something looks off, the instructor sees it. The student never does.
                        </p>

                        <!-- Log feed -->
                        <div class="space-y-2 bg-[#121212] border border-[#232323] rounded-[6px] p-3 font-mono text-[11px]">
                            <div class="flex items-center justify-between text-[#3ecf8e]">
                                <span>FOCUS_LOCKED</span>
                                <span class="text-[#666666]">0.00s</span>
                            </div>
                            <div class="flex items-center justify-between text-[#d1d1d1]">
                                <span>CAMERA_PRESENCE</span>
                                <span class="text-[#3ecf8e]">VERIFIED</span>
                            </div>
                            <div class="flex items-center justify-between text-[#d1d1d1]">
                                <span>TAB_SWITCH</span>
                                <span class="text-[#3ecf8e]">0 DETECTED</span>
                            </div>
                            <div class="flex items-center justify-between text-[#a3a3a3]">
                                <span>UNUSUAL_DIFF</span>
                                <span class="text-[#3ecf8e]">NONE (CLEAN)</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[#2e2e2e] flex items-center justify-between text-[11px] font-mono text-[#666666]">
                        <span>Heartbeat interval</span>
                        <span class="text-[#d1d1d1]">500ms</span>
                    </div>
                </div>

                <!-- Bento Tile 3: Automated AI Rubric (1 Col) -->
                <div class="bento-card rounded-xl p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="mono-tag text-[11px] text-[#a3a3a3]">Automated Evaluation</span>
                            <span class="text-xs font-mono font-bold text-[#3ecf8e]">SCORE 98%</span>
                        </div>
                        <h3 class="text-lg font-bold text-[#ededed] mb-1">AI Rubric Engine</h3>
                        <p class="text-xs text-[#888888] leading-relaxed mb-4">
                            Every submission gets checked against your rubric automatically &mdash; does the logic work, does it handle edge cases, is the code readable. You still make the final call; the AI just gets there first.
                        </p>

                        <!-- Metric bars -->
                        <div class="space-y-3 font-mono text-xs">
                            <div>
                                <div class="flex justify-between text-[11px] text-[#a3a3a3] mb-1">
                                    <span>Logic & Correctness</span>
                                    <span class="text-[#ededed]">98%</span>
                                </div>
                                <div class="w-full h-1.5 bg-[#232323] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#3ecf8e] rounded-full" style="width: 98%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[11px] text-[#a3a3a3] mb-1">
                                    <span>Edge Case Handling</span>
                                    <span class="text-[#ededed]">100%</span>
                                </div>
                                <div class="w-full h-1.5 bg-[#232323] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#3ecf8e] rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[11px] text-[#a3a3a3] mb-1">
                                    <span>Code Quality & Readability</span>
                                    <span class="text-[#ededed]">95%</span>
                                </div>
                                <div class="w-full h-1.5 bg-[#232323] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#3ecf8e] rounded-full" style="width: 95%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[#2e2e2e] flex items-center justify-between text-[11px] font-mono text-[#666666]">
                        <span>Rubric standard</span>
                        <span class="text-[#d1d1d1]">Certicode v2</span>
                    </div>
                </div>

                <!-- Bento Tile 4: Verifiable Credentials (2 Cols) -->
                <div class="md:col-span-2 bento-card rounded-xl p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="mono-tag text-[11px] text-[#a3a3a3]">Credential Authority</span>
                            <span class="px-2 py-0.5 rounded-[4px] bg-[#141414] border border-[#2e2e2e] text-[10px] font-mono text-[#3ecf8e]">
                                VERIFIED RECORD
                            </span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-[#ededed]">Verifiable Competency Certificates</h3>
                                <p class="text-xs text-[#888888] mt-1 max-w-md leading-relaxed">
                                    Once a student meets the required competency threshold, they get a certificate tied to a verifiable ID &mdash; something they can actually show an employer, not just a PDF that says &lsquo;trust me.&rsquo;
                                </p>
                            </div>
                            <div class="p-4 rounded-[6px] bg-[#121212] border border-[#2e2e2e] font-mono text-xs text-left shrink-0">
                                <div class="text-[#888888] text-[10px] uppercase">Certificate Code</div>
                                <div class="text-[#3ecf8e] font-bold text-sm tracking-wider mt-0.5">CERT-9481-KD82</div>
                                <div class="text-[10px] text-[#666666] mt-1">ID: cert_9481kd82</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-3 border-t border-[#2e2e2e] flex flex-wrap items-center justify-between text-xs font-mono text-[#888888] gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span class="text-[#d1d1d1]">Public verification endpoint included</span>
                        </div>
                        <span class="text-[#3ecf8e]">Institutional Registry Backed</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- Feature Pillar Highlights -->
        <section class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-6 border-t border-[#232323]">
            <div class="p-5 rounded-lg border border-[#2e2e2e] bg-[#141414] space-y-2">
                <div class="w-7 h-7 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-[#ededed]">Live Sandboxes</h4>
                <p class="text-xs text-[#888888] leading-relaxed">
                    Students start coding right away. No setup, no environment to configure.
                </p>
            </div>

            <div class="p-5 rounded-lg border border-[#2e2e2e] bg-[#141414] space-y-2">
                <div class="w-7 h-7 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-[#ededed]">Curriculum Pathways</h4>
                <p class="text-xs text-[#888888] leading-relaxed">
                    Instructors build out lessons as modules and can see where students are likely to get stuck.
                </p>
            </div>

            <div class="p-5 rounded-lg border border-[#2e2e2e] bg-[#141414] space-y-2">
                <div class="w-7 h-7 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-[#ededed]">Instructor Monitor</h4>
                <p class="text-xs text-[#888888] leading-relaxed">
                    Watch a session unfold in real time &mdash; who&rsquo;s finished what, and who&rsquo;s triggered a flag worth a second look.
                </p>
            </div>

            <div class="p-5 rounded-lg border border-[#2e2e2e] bg-[#141414] space-y-2">
                <div class="w-7 h-7 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-[#ededed]">VS Code Integration</h4>
                <p class="text-xs text-[#888888] leading-relaxed">
                    Students code in VS Code, not a browser tab. Every keystroke and submission syncs straight through, so grading is based on what they actually wrote.
                </p>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-[#232323] bg-[#0f0f0f] py-8 text-xs text-[#666666]">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center">
                    <svg class="w-3 h-3 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <span class="text-[#a3a3a3] font-medium">Certicode Labs</span>
                <span>&copy; 2026. Built for educators & engineers.</span>
            </div>
            <div class="flex items-center space-x-6 font-mono text-[11px]">
                <a href="{{ route('login') }}" class="hover:text-[#3ecf8e] transition-colors">LOGIN</a>
                <a href="{{ route('register.show') }}" class="hover:text-[#3ecf8e] transition-colors">REGISTER</a>
                <span class="text-[#383838]">|</span>
                <span class="text-[#3ecf8e]">POSTGRESQL CONNECTED</span>
            </div>
        </div>
    </footer>

</body>
</html>
