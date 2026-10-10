<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Certicode Labs - Interactive coding challenges, virtual laboratory environments, and automated competency verification.">
    <meta name="theme-color" content="#0b0c0b">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'; script-src 'self' https: 'unsafe-inline' 'unsafe-eval' blob: data:; style-src 'self' https: 'unsafe-inline'; font-src 'self' https: data:; img-src 'self' https: data: blob:; connect-src 'self' https: ws: wss:;">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>@yield('title', 'Certicode Labs') - Certicode Labs</title>

    <!-- Immediate Theme Initialization (No-FOUC) -->
    <script>
        (function() {
            try {
                const stored = localStorage.getItem('theme') || 'system';
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = stored === 'dark' || (stored === 'system' && systemDark);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    document.documentElement.style.colorScheme = 'light';
                }
            } catch (e) {}
        })();
    </script>

    <!-- Vite Compiled Assets -->
    @vite(['resources/css/app.css', 'resources/css/brand.css', 'resources/js/app.js'])
    <!-- Brand fonts: Geist (UI), Geist Mono (code, labels), Instrument Serif (accents) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Brick Sans';
            src: url('/fonts/BrickSans-Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }
        body {
            font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            background-color: #0f0f0f;
            color: #ededed;
        }
        .glass-panel, .surface-panel {
            background-color: #171717;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
        }
        .glass-card, .surface-card {
            background-color: #171717;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
            transition: border-color 150ms ease, background-color 150ms ease;
        }
        .glass-card:hover, .surface-card:hover {
            border-color: rgba(62, 207, 142, 0.35);
            background-color: #1c1c1c;
            box-shadow: none !important;
        }
        /* Supabase Pill CTA */
        .btn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background-color: #3ecf8e;
            color: #0f0f0f;
            font-weight: 600;
            font-size: 0.8125rem;
            padding: 0.5rem 1.25rem;
            transition: background-color 150ms ease;
        }
        .btn-pill:hover {
            background-color: #00c573;
        }
        /* Supabase 6px Secondary Button */
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background-color: #171717;
            border: 1px solid #2e2e2e;
            color: #ededed;
            font-weight: 500;
            font-size: 0.8125rem;
            padding: 0.4rem 0.875rem;
            transition: background-color 150ms ease, border-color 150ms ease;
        }
        .btn-secondary:hover {
            background-color: #222222;
            border-color: #3a3a3a;
        }
        /* Uppercase technical tags */
        .tech-tag {
            font-family: 'Geist Mono', ui-monospace, monospace;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.6875rem;
            font-weight: 600;
        }
        /* Custom sidebar active state style matching Supabase */
        .sidebar-active-item {
            background-color: #1c1c1c !important;
            border-left: 3px solid #3ecf8e !important;
            border-top-left-radius: 0px !important;
            border-bottom-left-radius: 0px !important;
            color: #ffffff !important;
        }
        /* Custom scrollbar matching Supabase Dark */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0f0f0f;
        }
        ::-webkit-scrollbar-thumb {
            background: #2e2e2e;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #404040;
        }

        /* Loading Skeletons */
        .skeleton-pulse {
            background: linear-gradient(90deg, #232323 25%, #303030 50%, #232323 75%);
            background-size: 200% 100%;
            animation: skeleton-pulse-anim 1.5s infinite ease-in-out;
            border-radius: 6px;
        }
        @media (prefers-reduced-motion: reduce) {
            .skeleton-pulse { animation: none; background: #262626; }
        }
        @keyframes skeleton-pulse-anim {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        html:not(.dark) .skeleton-pulse {
            background: linear-gradient(90deg, #eceef1 25%, #dfe2e6 50%, #eceef1 75%);
            background-size: 200% 100%;
        }

        /* ==========================================================================
           SUPABASE LIGHT MODE SYSTEM (UI-UX PRO MAX SPECIFICATION)
           Applied dynamically when html does not have .dark or has .light
           ========================================================================== */
        html:not(.dark) body {
            background-color: #f8f9fa !important;
            color: #111827 !important;
        }

        /* 1. Canvas & Surface Backgrounds */
        html:not(.dark) .bg-\[\#0f0f0f\],
        html:not(.dark) .bg-slate-950 {
            background-color: #f8f9fa !important;
        }
        html:not(.dark) .bg-\[\#171717\],
        html:not(.dark) .bg-slate-900 {
            background-color: #ffffff !important;
        }
        html:not(.dark) .bg-\[\#141414\] {
            background-color: #f9fafb !important;
        }
        html:not(.dark) .bg-\[\#1c1c1c\],
        html:not(.dark) .bg-slate-850 {
            background-color: #f3f4f6 !important;
        }
        html:not(.dark) .bg-\[\#101010\],
        html:not(.dark) .bg-\[\#0d0d0d\] {
            background-color: #f8fafc !important;
        }
        html:not(.dark) .bg-\[\#222222\],
        html:not(.dark) .bg-slate-800 {
            background-color: #e5e7eb !important;
        }

        /* 2. Crisp 1px Hairline Borders */
        html:not(.dark) .border-\[\#2e2e2e\],
        html:not(.dark) .border-\[\#2e2e2e\]\/30,
        html:not(.dark) .border-slate-800,
        html:not(.dark) .border-slate-850,
        html:not(.dark) .border-slate-700 {
            border-color: #e5e7eb !important;
        }
        html:not(.dark) .border-\[\#232323\],
        html:not(.dark) .border-\[\#262626\],
        html:not(.dark) .divide-slate-800,
        html:not(.dark) .divide-slate-800\/60 {
            border-color: #f0f2f5 !important;
        }
        html:not(.dark) .border-\[\#383838\] {
            border-color: #d1d5db !important;
        }

        /* 3. Typography & High-Contrast Readability (WCAG 4.5:1+) */
        html:not(.dark) .text-\[\#ededed\],
        html:not(.dark) .text-slate-200 {
            color: #111827 !important;
        }
        html:not(.dark) .text-\[\#a3a3a3\],
        html:not(.dark) .text-slate-300 {
            color: #374151 !important;
        }
        html:not(.dark) .text-\[\#888888\],
        html:not(.dark) .text-slate-400 {
            color: #4b5563 !important;
        }
        html:not(.dark) .text-\[\#666666\],
        html:not(.dark) .text-slate-500 {
            color: #6b7280 !important;
        }
        html:not(.dark) h1.text-white,
        html:not(.dark) h2.text-white,
        html:not(.dark) h3.text-white,
        html:not(.dark) h4.text-white,
        html:not(.dark) p.text-white,
        html:not(.dark) header span.text-white {
            color: #111827 !important;
        }

        /* 4. Signature Emerald Accent (Deepened for WCAG contrast on Light Canvas) */
        html:not(.dark) .text-\[\#3ecf8e\] {
            color: #059669 !important;
        }
        html:not(.dark) .btn-pill,
        html:not(.dark) .bg-\[\#3ecf8e\] {
            background-color: #059669 !important;
            color: #ffffff !important;
        }
        html:not(.dark) .btn-pill:hover,
        html:not(.dark) .hover\:bg-\[\#00c573\]:hover {
            background-color: #047857 !important;
            color: #ffffff !important;
        }
        html:not(.dark) .hover\:border-\[\#3ecf8e\]\/35:hover,
        html:not(.dark) .hover\:border-\[\#3ecf8e\]\/50:hover {
            border-color: rgba(5, 150, 105, 0.4) !important;
        }

        /* 5. Panels, Cards & Interactive Surfaces */
        html:not(.dark) .glass-panel,
        html:not(.dark) .surface-panel,
        html:not(.dark) .glass-card,
        html:not(.dark) .surface-card {
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04) !important;
        }
        html:not(.dark) .glass-card:hover,
        html:not(.dark) .surface-card:hover {
            border-color: rgba(5, 150, 105, 0.4) !important;
            background-color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        }

        /* 6. Form Controls & Dropdown Inputs */
        html:not(.dark) input[type="text"],
        html:not(.dark) input[type="email"],
        html:not(.dark) input[type="password"],
        html:not(.dark) input[type="number"],
        html:not(.dark) select,
        html:not(.dark) textarea {
            background-color: #ffffff !important;
            border: 1px solid #d1d5db !important;
            color: #111827 !important;
        }
        html:not(.dark) input::placeholder,
        html:not(.dark) textarea::placeholder {
            color: #9ca3af !important;
        }
        html:not(.dark) select option {
            background-color: #ffffff !important;
            color: #111827 !important;
        }
        html:not(.dark) input:focus,
        html:not(.dark) select:focus,
        html:not(.dark) textarea:focus {
            border-color: #059669 !important;
            box-shadow: 0 0 0 1px #059669 !important;
        }

        /* 7. Switch Toggles */
        html:not(.dark) .sr-only.peer + div {
            background-color: #e5e7eb !important;
            border-color: #d1d5db !important;
        }
        html:not(.dark) .sr-only.peer + div:after {
            background-color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
        }
        html:not(.dark) .sr-only.peer:checked + div {
            background-color: #059669 !important;
            border-color: #059669 !important;
        }
        html:not(.dark) .sr-only.peer:checked + div:after {
            background-color: #ffffff !important;
        }

        /* 8. Top Navigation & Dropdown Panels */
        html:not(.dark) header.h-15 {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        html:not(.dark) header input[placeholder="Search..."] {
            background-color: #f8f9fa !important;
            border-color: #e5e7eb !important;
            color: #111827 !important;
        }
        html:not(.dark) #notifications-dropdown,
        html:not(.dark) #profile-dropdown {
            background-color: #ffffff !important;
            border-color: #e5e7eb !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
        }
        html:not(.dark) #notifications-dropdown .bg-slate-950,
        html:not(.dark) #profile-dropdown .bg-slate-950\/40 {
            background-color: #f9fafb !important;
            border-color: #e5e7eb !important;
        }
        html:not(.dark) #notifications-list a:hover,
        html:not(.dark) #profile-dropdown a:hover,
        html:not(.dark) #profile-dropdown button:hover {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }

        /* 9. Search Command Palette Modal */
        html:not(.dark) #search-modal > div:last-child {
            background-color: #ffffff !important;
            border-color: #e5e7eb !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15) !important;
        }
        html:not(.dark) #search-modal input {
            color: #111827 !important;
        }
        html:not(.dark) #search-quick-links a:hover,
        html:not(.dark) #search-results a:hover {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }
        html:not(.dark) #search-modal a.is-active {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }

        /* 10. Secondary Button & Sidebar Active */
        html:not(.dark) .btn-secondary {
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            color: #1f2937 !important;
        }
        html:not(.dark) .btn-secondary:hover {
            background-color: #f9fafb !important;
            border-color: #d1d5db !important;
        }
        html:not(.dark) .sidebar-active-item {
            background-color: #f3f4f6 !important;
            border-left: 3px solid #059669 !important;
            color: #111827 !important;
        }

        /* 11. Light Scrollbars */
        html:not(.dark) ::-webkit-scrollbar-track {
            background: #f8f9fa;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* 12. Active Theme Selection Cards */
        html:not(.dark) .active-theme-card {
            border-color: #059669 !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 1px #059669 !important;
        }
        html:not(.dark) .theme-check-badge {
            background-color: #059669 !important;
            color: #ffffff !important;
        }
        html:not(.dark) #theme-status-indicator {
            background-color: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #047857 !important;
        }
        html:not(.dark) #theme-status-indicator span.rounded-full {
            background-color: #059669 !important;
        }
        /* App shell: header nav, icon buttons, menu states */
        .app-nav-link.is-active::after {
            content: ''; position: absolute; left: 0.75rem; right: 0.75rem; bottom: -1px;
            height: 2px; border-radius: 2px 2px 0 0; background: #3ecf8e;
        }
        .app-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.25rem; height: 2.25rem; border-radius: 0.5rem;
            color: #888888; transition: color 150ms ease, background-color 150ms ease;
        }
        .app-icon-btn:hover { color: #ededed; background-color: #1c1c1c; }
        #theme-toggle-sun, #theme-toggle-moon { color: inherit; }
        .notif-item.is-unread { background-color: rgba(62, 207, 142, 0.05); box-shadow: inset 2px 0 0 #3ecf8e; }
        #search-modal a.is-active { background-color: #1c1c1c; color: #ededed; }
        .app-mobile-nav { scrollbar-width: none; }
        .app-mobile-nav::-webkit-scrollbar { display: none; }
        :focus-visible { outline: 2px solid #3ecf8e; outline-offset: 2px; }

        /* App UI kit: shared form and card styles for redesigned pages */
        .ui-card { background: #171717; border: 1px solid #2e2e2e; border-radius: 0.875rem; }
        .ui-card-header { padding: 1.25rem 1.5rem 0; }
        .ui-card-body { padding: 1.25rem 1.5rem 1.5rem; }
        .ui-card-title { font-size: 0.95rem; font-weight: 600; color: #ededed; }
        .ui-card-subtitle { margin-top: 0.25rem; font-size: 0.8125rem; color: #888888; line-height: 1.5; }
        .ui-label { display: block; margin-bottom: 0.4rem; font-size: 0.8125rem; font-weight: 500; color: #ededed; }
        .ui-label .ui-optional { font-weight: 400; color: #888888; }
        .ui-hint { margin-top: 0.4rem; font-size: 0.75rem; color: #888888; line-height: 1.5; }
        .ui-error { margin-top: 0.4rem; font-size: 0.75rem; color: #f87171; }
        .ui-input {
            display: block; width: 100%; min-height: 2.5rem; padding: 0.5rem 0.8rem;
            background: #141414; border: 1px solid #2e2e2e; border-radius: 0.6rem;
            font-size: 0.875rem; color: #ededed; transition: border-color 150ms ease, box-shadow 150ms ease;
        }
        .ui-input::placeholder { color: #666666; }
        .ui-input:hover { border-color: #383838; }
        .ui-input:focus { outline: none; border-color: #3ecf8e; box-shadow: 0 0 0 3px rgba(62, 207, 142, 0.15); }
        .ui-input[aria-invalid="true"] { border-color: rgba(248, 113, 113, 0.6); }
        textarea.ui-input { min-height: 6rem; line-height: 1.55; resize: vertical; }
        .ui-input-mono { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 0.8125rem; }
        .ui-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            height: 2.5rem; padding: 0 1rem; border-radius: 0.6rem; font-size: 0.875rem; font-weight: 500;
            border: 1px solid transparent; white-space: nowrap;
            transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }
        .ui-btn-sm { height: 2rem; padding: 0 0.75rem; font-size: 0.8125rem; border-radius: 0.5rem; }
        .ui-btn-primary { background: #3ecf8e; color: #06150e; font-weight: 600; }
        .ui-btn-primary:hover { background: #00c573; }
        .ui-btn-secondary { background: #171717; border-color: #2e2e2e; color: #ededed; }
        .ui-btn-secondary:hover { border-color: #3a3a3a; background: #1c1c1c; }
        .ui-btn-ghost { color: #a3a3a3; }
        .ui-btn-ghost:hover { color: #ededed; background: #1c1c1c; }
        .ui-btn-danger { background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; }
        .ui-btn-danger:hover { background: rgba(239, 68, 68, 0.16); }
        .ui-btn:disabled, .ui-btn[aria-disabled="true"] { opacity: 0.5; cursor: not-allowed; }
        .ui-eyebrow { font-family: 'Geist Mono', ui-monospace, monospace; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.14em; color: #888888; }
        .ui-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.15rem 0.55rem; border-radius: 9999px; border: 1px solid #2e2e2e; font-size: 0.75rem; color: #a3a3a3; }
        .ui-badge-brand { border-color: rgba(62, 207, 142, 0.3); background: rgba(62, 207, 142, 0.06); color: #3ecf8e; }
        .ui-badge-warn { border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.06); color: #fcd34d; }
        .ui-badge-danger { border-color: rgba(248, 113, 113, 0.3); background: rgba(248, 113, 113, 0.06); color: #fca5a5; }
        html:not(.dark) .ui-card { background: #ffffff; border-color: #e5e7eb; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04); }
        html:not(.dark) .ui-card-title, html:not(.dark) .ui-label { color: #111827; }
        html:not(.dark) .ui-card-subtitle, html:not(.dark) .ui-hint, html:not(.dark) .ui-eyebrow, html:not(.dark) .ui-label .ui-optional { color: #6b7280; }
        html:not(.dark) .ui-input:focus { border-color: #059669 !important; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15) !important; }
        html:not(.dark) .ui-btn-primary { background: #059669; color: #ffffff; }
        html:not(.dark) .ui-btn-primary:hover { background: #047857; }
        html:not(.dark) .ui-btn-secondary { background: #ffffff; border-color: #e5e7eb; color: #111827; }
        html:not(.dark) .ui-btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }
        html:not(.dark) .ui-btn-ghost { color: #4b5563; }
        html:not(.dark) .ui-btn-ghost:hover { color: #111827; background: #f3f4f6; }
        html:not(.dark) .ui-btn-danger { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
        html:not(.dark) .ui-badge { border-color: #e5e7eb; color: #4b5563; }
        html:not(.dark) .ui-badge-brand { color: #047857; background: #ecfdf5; border-color: #a7f3d0; }
        html:not(.dark) .ui-badge-warn { color: #92400e; background: #fffbeb; border-color: #fde68a; }
        html:not(.dark) .ui-badge-danger { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }

        /* Live lab timer: amber in the last five minutes */
        .live-timer.is-ending { border-color: rgba(251, 191, 36, 0.45); background-color: rgba(251, 191, 36, 0.07); }
        .live-timer.is-ending .live-timer-clock { color: #fbbf24; }
        .live-timer.is-over .live-timer-clock { color: #f87171; }
        html:not(.dark) .live-timer.is-ending .live-timer-clock { color: #b45309; }

        /* Lab switcher on the class monitoring page */
        .monitor-tab-active::after {
            content: ''; position: absolute; left: 0.75rem; right: 0.75rem; bottom: -1px;
            height: 2px; border-radius: 2px 2px 0 0; background: #3ecf8e;
        }
        .monitor-tabs { scrollbar-width: none; }
        .monitor-tabs::-webkit-scrollbar { display: none; }
        html:not(.dark) .monitor-tab-active::after { background: #059669; }

        /* (i) explanations: the info-tip Blade component */
        .info-tip { position: relative; display: inline-flex; vertical-align: middle; }
        .info-tip-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.125rem; height: 1.125rem; border-radius: 9999px;
            color: #777777; transition: color 150ms ease;
        }
        .info-tip-btn svg { width: 0.95rem; height: 0.95rem; }
        .info-tip-btn:hover, .info-tip:focus-within .info-tip-btn { color: #3ecf8e; }
        .info-tip-bubble {
            position: absolute; top: calc(100% + 8px); z-index: 60;
            width: max-content; max-width: min(18rem, 80vw);
            padding: 0.6rem 0.75rem; border-radius: 0.6rem;
            background: #232323; border: 1px solid #383838; color: #e5e5e5;
            box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.7);
            font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.78rem; font-weight: 400; line-height: 1.45;
            letter-spacing: normal; text-transform: none; text-align: left; white-space: normal;
            opacity: 0; visibility: hidden; transform: translateY(-3px);
            transition: opacity 140ms ease, transform 140ms ease, visibility 0s linear 140ms;
            pointer-events: none;
        }
        .info-tip-center .info-tip-bubble { left: 50%; translate: -50% 0; }
        .info-tip-left .info-tip-bubble { left: -0.5rem; }
        .info-tip-right .info-tip-bubble { right: -0.5rem; }
        .info-tip:hover .info-tip-bubble, .info-tip:focus-within .info-tip-bubble {
            opacity: 1; visibility: visible; transform: translateY(0);
            transition: opacity 140ms ease, transform 140ms ease, visibility 0s;
        }
        @media (prefers-reduced-motion: reduce) { .info-tip-bubble { transition: none; transform: none; } }
        html:not(.dark) .info-tip-btn { color: #9ca3af; }
        html:not(.dark) .info-tip-btn:hover, html:not(.dark) .info-tip:focus-within .info-tip-btn { color: #059669; }
        html:not(.dark) .info-tip-bubble { background: #111827; border-color: #111827; color: #f9fafb; }

        html:not(.dark) .app-nav-link.is-active::after { background: #059669; }
        html:not(.dark) .app-icon-btn { color: #6b7280; }
        html:not(.dark) .app-icon-btn:hover { color: #111827; background-color: #f3f4f6; }
        html:not(.dark) .notif-item.is-unread { background-color: #ecfdf5; box-shadow: inset 2px 0 0 #059669; }
        html:not(.dark) .app-mobile-nav { background-color: #ffffff !important; }
        html:not(.dark) .app-search-trigger { background-color: #f8f9fa !important; }
        html:not(.dark) .app-flash-success { color: #047857 !important; background-color: #ecfdf5 !important; border-color: #a7f3d0 !important; }
        html:not(.dark) .app-flash-error { color: #b91c1c !important; background-color: #fef2f2 !important; border-color: #fecaca !important; }
        html:not(.dark) .app-flash-warning { color: #92400e !important; background-color: #fffbeb !important; border-color: #fde68a !important; }
        html:not(.dark) :focus-visible { outline-color: #059669; }
    </style>
</head>
<body class="h-full text-[#ededed] bg-[#0f0f0f] flex flex-col overflow-hidden antialiased selection:bg-[#3ecf8e]/25" data-is-instructor-or-admin="{{ auth()->user() && (auth()->user()->role === 'instructor' || auth()->user()->role === 'admin') ? 'true' : 'false' }}">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[80] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-[#3ecf8e] focus:text-[#06150e] focus:font-semibold">Skip to content</a>

    @php
        $authUser = auth()->user();
        $isAdmin = $authUser->role === 'admin';
        $pendingInstructorRequests = $isAdmin
            ? \App\Models\User::where('role', 'student')->whereNotNull('instructor_requested_at')->count()
            : 0;
        $primaryNav = array_filter([
            ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Classes', 'url' => route('classes.index'), 'active' => request()->routeIs('classes.*', 'laboratories.*', 'modules.*', 'instructor.*', 'certificates.*')],
            $isAdmin ? ['label' => 'Students', 'url' => route('students.index'), 'active' => request()->routeIs('students.*', 'profiles.*')] : null,
            $isAdmin ? ['label' => 'Requests', 'url' => route('admin.instructor-requests.index'), 'active' => request()->routeIs('admin.instructor-requests.*'), 'badge' => $pendingInstructorRequests] : null,
        ]);
    @endphp

    <!-- Main Content Shell -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top bar -->
        <header class="app-header h-15 bg-[#0f0f0f] border-b border-[#2e2e2e] flex-shrink-0 z-50">
            <div class="h-full mx-auto max-w-[1440px] px-4 sm:px-6 flex items-center gap-4">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 shrink-0 group" aria-label="Certicode Labs dashboard">
                    <x-logo-mark class="w-[22px] h-[22px] text-[var(--color-text,#ededed)] transition-transform duration-300 group-hover:-translate-y-px" />
                    <span class="hidden sm:flex items-baseline gap-1 text-[15px] font-semibold tracking-tight text-[#ededed]">
                        Certicode<span class="font-medium text-[#888888]">Labs</span>
                    </span>
                </a>

                <!-- Primary navigation (desktop) -->
                <nav class="hidden md:flex items-center gap-1 ml-4 h-full" aria-label="Primary">
                    @foreach ($primaryNav as $item)
                        <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                           class="app-nav-link relative h-full inline-flex items-center gap-2 px-3 text-sm font-medium transition-colors {{ $item['active'] ? 'text-[#ededed] is-active' : 'text-[#888888] hover:text-[#ededed]' }}">
                            {{ $item['label'] }}
                            @if (!empty($item['badge']))
                                <span class="min-w-5 h-5 px-1.5 rounded-full bg-[#3ecf8e] text-[11px] font-bold text-[#06150e] inline-flex items-center justify-center" aria-label="{{ $item['badge'] }} pending">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="flex-1"></div>

                <!-- Search trigger -->
                <button type="button" id="global-search-bar-input" onclick="openSearchModal()" aria-label="Search (Ctrl+K)"
                        class="app-search-trigger hidden lg:flex items-center gap-2.5 w-64 h-9 pl-3 pr-2 rounded-lg bg-[#171717] border border-[#2e2e2e] text-sm text-[#888888] hover:border-[#383838] hover:text-[#a3a3a3] transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/></svg>
                    <span class="flex-1 text-left">Search…</span>
                    <kbd class="rounded-md border border-[#2e2e2e] px-1.5 py-0.5 font-mono text-[10px] text-[#888888]">Ctrl K</kbd>
                </button>

                <!-- Right actions -->
                <div class="flex items-center gap-1">
                    <div class="lg:hidden">
                        <button type="button" onclick="openSearchModal()" aria-label="Search" class="app-icon-btn">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/></svg>
                        </button>
                    </div>

                    <!-- Quick Theme Toggle Button -->
                    <button type="button" id="quick-theme-toggle" onclick="toggleQuickTheme()" title="Switch light / dark" aria-label="Switch light or dark theme" class="app-icon-btn">
                        <svg id="theme-toggle-sun" class="w-[18px] h-[18px] hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg id="theme-toggle-moon" class="w-[18px] h-[18px] hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <!-- Notification Bell and Dropdown -->
                    <div class="relative" id="notification-bell-container">
                        @php
                            $currentUserId = auth()->id();
                            $cachedNotificationsData = \Illuminate\Support\Facades\Cache::store('file')->remember("user_notifs_summary_{$currentUserId}", 60, function () {
                                /** @var \App\Models\User|null $u */
                                $u = auth()->user();
                                return [
                                    'count' => $u ? $u->unreadNotifications()->count() : 0,
                                    'items' => $u ? $u->notifications()->take(5)->get() : collect(),
                                ];
                            });
                            $unreadCount = $cachedNotificationsData['count'] ?? 0;
                            $notifications = $cachedNotificationsData['items'] ?? collect();
                        @endphp
                        <button type="button" onclick="toggleNotifications()" aria-label="Notifications" aria-haspopup="true" class="app-icon-btn relative">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            @if($unreadCount > 0)
                                <span class="notif-dot absolute top-1.5 right-1.5 block h-2 w-2 rounded-full bg-[#3ecf8e] ring-2 ring-[#0f0f0f]"></span>
                            @endif
                        </button>

                        <!-- Dropdown Panel -->
                        <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] bg-[#171717] border border-[#2e2e2e] rounded-xl overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.8)] z-50 text-left">
                            <div id="notifications-header" class="px-4 py-3 border-b border-[#2e2e2e] flex items-center justify-between">
                                <span class="text-sm font-semibold text-[#ededed]">Notifications</span>
                                @if($unreadCount > 0)
                                    <button type="button" onclick="markAllAsRead()" class="text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2">Mark all read</button>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-[#232323]" id="notifications-list">
                                @forelse($notifications as $notif)
                                    @php $notifType = $notif->data['type'] ?? 'info'; @endphp
                                    <a href="{{ \App\Support\InternalUrl::path($notif->data['url'] ?? null) }}" class="notif-item block px-4 py-3 hover:bg-[#1c1c1c] transition-colors {{ $notif->unread() ? 'is-unread' : '' }}">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1.5 flex h-1.5 w-1.5 shrink-0 rounded-full {{ $notifType === 'certificate' ? 'bg-amber-400' : ($notifType === 'class' || $notifType === 'module' ? 'bg-sky-400' : 'bg-[#3ecf8e]') }}"></span>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-semibold text-[#ededed] truncate">{{ $notif->data['title'] }}</p>
                                                <p class="text-xs text-[#a3a3a3] mt-0.5 leading-normal line-clamp-2">{{ $notif->data['message'] }}</p>
                                                <span class="text-[11px] text-[#666666] font-mono block mt-1">{{ $notif->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="px-4 py-8 text-center text-sm text-[#888888]">You're all caught up.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Profile menu -->
                    <div class="relative ml-1" id="profile-dropdown-container">
                        <button type="button" onclick="toggleProfileDropdown()" aria-haspopup="true" aria-label="Account menu"
                                class="flex items-center gap-2 h-9 pl-1 pr-2 rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors">
                            <span class="h-7 w-7 rounded-full bg-[#3ecf8e]/10 border border-[#3ecf8e]/30 flex items-center justify-center text-[#3ecf8e] font-semibold text-[11px]">
                                {{ strtoupper(\Illuminate\Support\Str::substr($authUser->name, 0, 2)) }}
                            </span>
                            <span class="text-sm font-medium text-[#ededed] hidden xl:block max-w-[10rem] truncate">{{ $authUser->name }}</span>
                            <svg class="w-3.5 h-3.5 text-[#888888] hidden sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-60 bg-[#171717] border border-[#2e2e2e] rounded-xl overflow-hidden shadow-[0_24px_60px_-20px_rgba(0,0,0,0.8)] z-50 text-left">
                            <div class="px-4 py-3 border-b border-[#2e2e2e]">
                                <p class="text-sm font-semibold text-[#ededed] truncate">{{ $authUser->name }}</p>
                                <p class="text-xs text-[#888888] truncate mt-0.5">{{ $authUser->email }}</p>
                                <span class="inline-block mt-2 px-2 py-0.5 rounded-full border border-[#3ecf8e]/30 bg-[#3ecf8e]/10 font-mono text-[10px] uppercase tracking-[0.12em] text-[#3ecf8e]">{{ $authUser->role }}</span>
                            </div>
                            <div class="p-1.5">
                                <a href="{{ route('settings.show') }}" class="block px-3 py-2 rounded-lg text-sm text-[#a3a3a3] hover:bg-[#1c1c1c] hover:text-[#ededed] transition-colors">Account settings</a>
                            </div>
                            <div class="border-t border-[#2e2e2e] p-1.5">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-400 hover:bg-red-500/10 transition-colors">
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Primary navigation (mobile) -->
        <nav class="app-mobile-nav md:hidden flex items-center gap-1 overflow-x-auto px-4 h-11 border-b border-[#2e2e2e] bg-[#0f0f0f] flex-shrink-0" aria-label="Primary">
            @foreach ($primaryNav as $item)
                <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                   class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[13px] font-medium transition-colors {{ $item['active'] ? 'bg-[#1c1c1c] text-[#ededed]' : 'text-[#888888] hover:text-[#ededed]' }}">
                    {{ $item['label'] }}
                    @if (!empty($item['badge']))
                        <span class="min-w-4 h-4 px-1 rounded-full bg-[#3ecf8e] text-[10px] font-bold text-[#06150e] inline-flex items-center justify-center">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <!-- Main Viewport -->
        <main id="main-content" tabindex="-1" class="flex-1 relative overflow-y-auto focus:outline-none">
            <div class="mx-auto max-w-[1440px] px-4 sm:px-6 py-6 sm:py-8">
                @php
                    $flashes = array_filter([
                        $authUser->hasPendingInstructorRequest() ? ['warning', 'Your instructor access is waiting for an admin to approve it. Until then you can use Certicode as a student.'] : null,
                        session('success') ? ['success', session('success')] : null,
                        session('error') ? ['error', session('error')] : null,
                        session('warning') ? ['warning', session('warning')] : null,
                    ]);
                    $flashStyles = [
                        'success' => 'border-[#3ecf8e]/30 bg-[#3ecf8e]/[0.06] text-[#3ecf8e]',
                        'error' => 'border-red-500/30 bg-red-500/[0.06] text-red-400',
                        'warning' => 'border-amber-400/30 bg-amber-400/[0.06] text-amber-300',
                    ];
                @endphp
                @foreach ($flashes as [$tone, $message])
                    <div class="app-flash app-flash-{{ $tone }} mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 {{ $flashStyles[$tone] }}" role="{{ $tone === 'error' ? 'alert' : 'status' }}">
                        @if ($tone === 'success')
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @elseif ($tone === 'error')
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                        @else
                            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                        @endif
                        <p class="flex-1 text-sm font-medium">{{ $message }}</p>
                        <button type="button" onclick="this.closest('.app-flash').remove()" aria-label="Dismiss" class="-mr-1 p-1 rounded-md opacity-60 hover:opacity-100 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach

                @yield('content')
            </div>
        </main>
    </div>
    
    @yield('scripts')

    <script>
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) {
                pollNotifications(true);
            }
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown');
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            // Notification close
            const container = document.getElementById('notification-bell-container');
            const dropdown = document.getElementById('notifications-dropdown');
            if (container && !container.contains(e.target) && dropdown) {
                dropdown.classList.add('hidden');
            }

            // Profile close
            const profileContainer = document.getElementById('profile-dropdown-container');
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileContainer && !profileContainer.contains(e.target) && profileDropdown) {
                profileDropdown.classList.add('hidden');
            }
        });

        let lastNotifState = null;
        let notifPollTimer = null;

        function renderNotifications(data, force = false) {
            if (!data) return;
            const notifs = data.notifications || [];
            const unreadCount = data.unreadCount ?? 0;
            const stateKey = JSON.stringify({ count: unreadCount, ids: notifs.map(n => n.id + ':' + n.unread) });
            if (!force && stateKey === lastNotifState) return;
            lastNotifState = stateKey;

            // Update unread dot on the bell icon
            const container = document.getElementById('notification-bell-container');
            if (container) {
                let dot = container.querySelector('.notif-dot');
                if (unreadCount > 0) {
                    if (!dot) {
                        const btn = container.querySelector('button');
                        if (btn) {
                            dot = document.createElement('span');
                            dot.className = 'notif-dot absolute top-1.5 right-1.5 block h-2 w-2 rounded-full bg-[#3ecf8e] ring-2 ring-[#0f0f0f]';
                            btn.appendChild(dot);
                        }
                    }
                } else {
                    if (dot) dot.remove();
                }
            }

            // Update "Mark all read" button in dropdown header
            const header = document.getElementById('notifications-header');
            if (header) {
                let markReadBtn = header.querySelector('button');
                if (unreadCount > 0) {
                    if (!markReadBtn) {
                        markReadBtn = document.createElement('button');
                        markReadBtn.type = 'button';
                        markReadBtn.onclick = markAllAsRead;
                        markReadBtn.className = 'text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2';
                        markReadBtn.innerText = 'Mark all read';
                        header.appendChild(markReadBtn);
                    }
                } else {
                    if (markReadBtn) markReadBtn.remove();
                }
            }

            // Update list items
            const list = document.getElementById('notifications-list');
            if (list) {
                if (notifs.length === 0) {
                    list.innerHTML = '<div class="px-4 py-8 text-center text-sm text-[#888888]">You\'re all caught up.</div>';
                } else {
                    list.innerHTML = notifs.map(notif => {
                        const typeColor = notif.type === 'certificate' ? 'bg-amber-400' : ((notif.type === 'class' || notif.type === 'module') ? 'bg-sky-400' : 'bg-[#3ecf8e]');
                        return `
                            <a href="${escapeHtml(safeUrl(notif.url))}" class="notif-item block px-4 py-3 hover:bg-[#1c1c1c] transition-colors ${notif.unread ? 'is-unread' : ''}">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1.5 flex h-1.5 w-1.5 shrink-0 rounded-full ${typeColor}"></span>
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold text-[#ededed] truncate">${escapeHtml(notif.title)}</p>
                                        <p class="text-xs text-[#a3a3a3] mt-0.5 leading-normal line-clamp-2">${escapeHtml(notif.message)}</p>
                                        <span class="text-[11px] text-[#666666] font-mono block mt-1">${escapeHtml(notif.time)}</span>
                                    </div>
                                </div>
                            </a>
                        `;
                    }).join('');
                }
            }
        }

        // Notification and search text comes from user-entered names; never insert it as HTML
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        }

        function safeUrl(url) {
            return /^(https?:\/\/|\/(?!\/)|#)/i.test(String(url ?? '')) ? url : '#';
        }

        function markAllAsRead() {
            // 1. Instant optimistic update: remove the dot, clear unread styles, remove mark all button
            const container = document.getElementById('notification-bell-container');
            if (container) {
                const dot = container.querySelector('.notif-dot');
                if (dot) dot.remove();
            }

            const header = document.getElementById('notifications-header');
            if (header) {
                const btn = header.querySelector('button');
                if (btn) btn.remove();
            }

            document.querySelectorAll('#notifications-list a').forEach(item => item.classList.remove('is-unread'));

            // Invalidate cache tracking state so re-render forces clean state
            lastNotifState = null;

            // 2. Send mark-as-read request and refresh with fresh payload
            fetch("{{ route('notifications.mark-as-read') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.notifications) {
                    renderNotifications(data, true);
                } else {
                    pollNotifications(true);
                }
            })
            .catch(() => {
                pollNotifications(true);
            });
        }

        function pollNotifications(force = false) {
            if (document.hidden && !force) return;

            fetch("{{ route('notifications.fetch') }}?t=" + Date.now(), { cache: 'no-store' })
                .then(res => res.json())
                .then(data => {
                    renderNotifications(data, force);
                })
                .catch(() => {});
        }

        // Start polling every 25 seconds; wake up immediately when tab gains focus
        notifPollTimer = setInterval(pollNotifications, 25000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                pollNotifications();
            }
        });

        // --- Global Search Command Palette Logic ---
        let searchTimeout = null;
        let searchAbortController = null;

        function openSearchModal() {
            const modal = document.getElementById('search-modal');
            const input = document.getElementById('search-modal-input');
            if (modal && input) {
                modal.classList.remove('hidden');
                input.value = '';
                document.getElementById('search-quick-links').classList.remove('hidden');
                document.getElementById('search-results').classList.add('hidden');
                
                // Clear highlighted states
                const items = Array.from(modal.querySelectorAll('a'));
                items.forEach(item => item.classList.remove('is-active'));
                
                setTimeout(() => input.focus(), 50);
            }
        }

        function closeSearchModal() {
            const modal = document.getElementById('search-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function performSearch(query) {
            clearTimeout(searchTimeout);
            if (searchAbortController) {
                searchAbortController.abort();
            }
            
            const quickLinks = document.getElementById('search-quick-links');
            const results = document.getElementById('search-results');
            
            if (!query || query.trim().length < 2) {
                quickLinks.classList.remove('hidden');
                results.classList.add('hidden');
                return;
            }

            const isInstructorOrAdmin = document.body.getAttribute('data-is-instructor-or-admin') === 'true';
            
            const navLinks = [
                { label: 'Dashboard', url: "{{ route('dashboard') }}", type: 'Navigation', keywords: ['dashboard', 'home', 'dash'] },
                { label: 'All Classes', url: "{{ route('classes.index') }}", type: 'Navigation', keywords: ['classes', 'all classes', 'course', 'courses'] },
                { label: 'Account Settings', url: "{{ route('settings.show') }}", type: 'Navigation', keywords: ['settings', 'account', 'profile', 'password', 'gmail', 'github'] }
            ];

            if (isInstructorOrAdmin) {
                navLinks.push({ label: 'Create a Class', url: "{{ route('classes.create') }}", type: 'Navigation', keywords: ['create a class', 'create class', 'new class', 'add class'] });
            }
            @if(auth()->user()->role === 'admin')
                navLinks.push({ label: 'Student Directory', url: "{{ route('students.index') }}", type: 'Navigation', keywords: ['students', 'directory', 'users', 'profiles', 'student directory'] });
                navLinks.push({ label: 'Instructor requests', url: "{{ route('admin.instructor-requests.index') }}", type: 'Navigation', keywords: ['requests', 'instructor', 'approve', 'admin'] });
            @endif

            const queryLower = query.toLowerCase().trim();
            const matchingNavs = navLinks.filter(link => {
                return link.label.toLowerCase().includes(queryLower) || 
                       link.keywords.some(kw => kw.includes(queryLower));
            });

            searchTimeout = setTimeout(() => {
                searchAbortController = new AbortController();
                fetch(`/search?q=${encodeURIComponent(query)}`, { signal: searchAbortController.signal })
                    .then(res => res.json())
                    .then(data => {
                        quickLinks.classList.add('hidden');
                        results.classList.remove('hidden');

                        data.navigation = matchingNavs;

                        const sections = {
                            navigation: document.getElementById('search-section-navigation'),
                            classes: document.getElementById('search-section-classes'),
                            modules: document.getElementById('search-section-modules'),
                            laboratories: document.getElementById('search-section-laboratories')
                        };

                        let hasAnyResults = false;

                        for (const [key, section] of Object.entries(sections)) {
                            if (!section) continue;
                            const items = data[key] || [];
                            const container = section.querySelector('.search-items-container');
                            container.innerHTML = '';

                            if (items.length > 0) {
                                hasAnyResults = true;
                                section.classList.remove('hidden');
                                items.forEach(item => {
                                    const a = document.createElement('a');
                                    a.href = safeUrl(item.url);
                                    a.className = 'flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors';
                                    a.innerHTML = `
                                        <svg class="h-4 w-4 text-[#666666] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            ${getIconSvg(item.type)}
                                        </svg>
                                        <span class="truncate">${escapeHtml(item.label)}</span>
                                    `;
                                    container.appendChild(a);
                                });
                            } else {
                                section.classList.add('hidden');
                            }
                        }

                        const noResults = document.getElementById('search-no-results');
                        if (hasAnyResults) {
                            noResults.classList.add('hidden');
                        } else {
                            noResults.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            console.error('Search failed:', err);
                        }
                    });
            }, 250);
        }

        function getIconSvg(type) {
            if (type === 'Navigation') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />';
            } else if (type === 'Class') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />';
            } else if (type === 'Module') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />';
            } else { // Laboratory
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />';
            }
        }

        document.addEventListener('keydown', function(e) {
            // Ctrl+K or Cmd+K to toggle search modal
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const modal = document.getElementById('search-modal');
                if (modal && modal.classList.contains('hidden')) {
                    openSearchModal();
                } else {
                    closeSearchModal();
                }
            }

            // Close with Escape
            if (e.key === 'Escape') {
                closeSearchModal();
            }

            // Keyboard navigation inside search modal
            const modal = document.getElementById('search-modal');
            if (modal && !modal.classList.contains('hidden')) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') {
                    const activeContainer = document.getElementById('search-results').classList.contains('hidden') 
                        ? document.getElementById('search-quick-links') 
                        : document.getElementById('search-results');
                    
                    const items = Array.from(activeContainer.querySelectorAll('a:not(.hidden)'));
                    if (items.length === 0) return;

                    e.preventDefault();

                    let activeIndex = items.findIndex(item => item.classList.contains('is-active'));

                    if (e.key === 'Enter') {
                        if (activeIndex >= 0) {
                            items[activeIndex].click();
                        }
                        return;
                    }

                    if (activeIndex >= 0) {
                        items[activeIndex].classList.remove('is-active');
                    }

                    if (e.key === 'ArrowDown') {
                        activeIndex = (activeIndex + 1) % items.length;
                    } else if (e.key === 'ArrowUp') {
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                    }

                    items[activeIndex].classList.add('is-active');
                    items[activeIndex].scrollIntoView({ block: 'nearest' });
                }
            }
        });

        // --- Lazy sections ---
        // <div data-lazy-src="/same-origin/url">skeleton</div> renders at once; the server HTML
        // at that URL replaces the skeleton after the page appears. Fragments are our own Blade
        // templates (escaped server-side) and must not contain scripts.
        function loadLazySection(el) {
            const src = el.getAttribute('data-lazy-src');
            let url;
            try { url = new URL(src, window.location.href); } catch (e) { return; }
            if (url.origin !== window.location.origin) return;

            el.setAttribute('data-lazy-state', 'loading');
            el.setAttribute('aria-busy', 'true');
            const skeleton = el.innerHTML;

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(res => {
                    // Signed out meanwhile: the request was redirected to the login page
                    if (res.redirected && new URL(res.url).pathname === '/login') {
                        window.location.reload();
                        return null;
                    }
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(html => {
                    if (html === null) return;
                    el.innerHTML = html;
                    el.setAttribute('data-lazy-state', 'done');
                    el.removeAttribute('aria-busy');
                    el.dispatchEvent(new CustomEvent('certicode:lazyloaded', { bubbles: true }));
                })
                .catch(() => {
                    el.setAttribute('data-lazy-state', 'error');
                    el.removeAttribute('aria-busy');
                    el.innerHTML = `
                        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] px-5 py-8 text-center" role="alert">
                            <p class="text-sm font-medium text-[#ededed]">This section didn't load.</p>
                            <p class="text-sm text-[#888888] mt-1">Check your connection and try again.</p>
                            <button type="button" class="lazy-retry mt-4 inline-flex items-center h-9 px-4 rounded-lg border border-[#2e2e2e] text-sm font-medium text-[#ededed] hover:border-[#383838] transition-colors">Try again</button>
                        </div>`;
                    el.querySelector('.lazy-retry').addEventListener('click', () => {
                        el.innerHTML = skeleton;
                        loadLazySection(el);
                    }, { once: true });
                });
        }

        // --- Info tips: nudge each bubble back inside the window when it opens ---
        function placeInfoTip(tip) {
            const bubble = tip.querySelector('.info-tip-bubble');
            if (!bubble) return;
            // Right-anchored bubbles move with margin-right; the rest with margin-left
            const anchoredRight = tip.classList.contains('info-tip-right');
            bubble.style.marginLeft = bubble.style.marginRight = '0px';
            const rect = bubble.getBoundingClientRect();
            const gutter = 8;
            let shift = 0;
            if (rect.right > window.innerWidth - gutter) shift = window.innerWidth - gutter - rect.right;
            if (rect.left + shift < gutter) shift = gutter - rect.left;
            if (anchoredRight) {
                bubble.style.marginRight = (-shift) + 'px';
            } else {
                bubble.style.marginLeft = shift + 'px';
            }
        }
        ['mouseover', 'focusin'].forEach(type => document.addEventListener(type, e => {
            const tip = e.target.closest && e.target.closest('.info-tip');
            if (tip) placeInfoTip(tip);
        }));

        window.loadLazySections = function (root = document) {
            root.querySelectorAll('[data-lazy-src]:not([data-lazy-state])').forEach(loadLazySection);
        };
        window.loadLazySections();

        // Global Theme Management
        window.applyTheme = function(theme) {
            try {
                localStorage.setItem('theme', theme);
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (theme === 'system' && systemDark);
                
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    document.documentElement.style.colorScheme = 'light';
                }
                
                // Update Quick Theme Toggle Icons
                const sunIcon = document.getElementById('theme-toggle-sun');
                const moonIcon = document.getElementById('theme-toggle-moon');
                if (sunIcon && moonIcon) {
                    if (isDark) {
                        sunIcon.classList.remove('hidden');
                        moonIcon.classList.add('hidden');
                    } else {
                        sunIcon.classList.add('hidden');
                        moonIcon.classList.remove('hidden');
                    }
                }
                
                window.dispatchEvent(new CustomEvent('certicode:themechange', { detail: { theme, isDark } }));
            } catch (e) {
                console.error('Theme switch error:', e);
            }
        };

        window.toggleQuickTheme = function() {
            const isDark = document.documentElement.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            window.applyTheme(newTheme);
        };

        // Initialize theme UI state
        (function initThemeIcons() {
            const stored = localStorage.getItem('theme') || 'system';
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = stored === 'dark' || (stored === 'system' && systemDark);
            
            const sunIcon = document.getElementById('theme-toggle-sun');
            const moonIcon = document.getElementById('theme-toggle-moon');
            if (sunIcon && moonIcon) {
                if (isDark) {
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                } else {
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                }
            }
        })();

        // Listen for OS color scheme changes when system mode is selected
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            const stored = localStorage.getItem('theme') || 'system';
            if (stored === 'system') {
                window.applyTheme('system');
            }
        });
    </script>

    <!-- Search Command Palette Modal -->
    <div id="search-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto p-4 sm:p-6 md:pt-24" role="dialog" aria-modal="true" aria-label="Search">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" onclick="closeSearchModal()"></div>

        <!-- Modal Box -->
        <div class="relative z-10 mx-auto max-w-xl overflow-hidden rounded-2xl border border-[#2e2e2e] bg-[#171717] shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8)]">
            <div class="relative border-b border-[#2e2e2e]">
                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#888888]">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/></svg>
                </div>
                <input type="text" id="search-modal-input" name="search_modal_query" oninput="performSearch(this.value)" placeholder="Search classes, modules, labs…" autocomplete="off"
                       class="h-14 w-full border-0 bg-transparent pl-12 pr-16 text-[15px] text-[#ededed] placeholder-[#666666] focus:ring-0 focus:outline-none" role="combobox" aria-expanded="false" aria-controls="search-results">
                <kbd class="absolute right-4 top-1/2 -translate-y-1/2 rounded-md border border-[#2e2e2e] px-1.5 py-0.5 font-mono text-[10px] text-[#888888]">Esc</kbd>
            </div>

            <!-- Default Quick Links (when input is empty) -->
            <div id="search-quick-links" class="p-2">
                <span class="block px-3 pt-2 pb-1.5 font-mono text-[10px] uppercase tracking-[0.14em] text-[#888888]">Jump to</span>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors">Dashboard</a>
                <a href="{{ route('classes.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors">All classes</a>
                @if(in_array(auth()->user()->role, ['instructor', 'admin'], true))
                    <a href="{{ route('classes.create') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors">Create a class</a>
                @endif
                <a href="{{ route('settings.show') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg text-[#a3a3a3] hover:text-[#ededed] hover:bg-[#1c1c1c] transition-colors">Account settings</a>
            </div>

            <!-- Search Results -->
            <div id="search-results" class="hidden max-h-96 overflow-y-auto p-2 space-y-3">
                @foreach (['navigation' => 'Pages', 'classes' => 'Classes', 'modules' => 'Modules', 'laboratories' => 'Labs'] as $section => $heading)
                    <div id="search-section-{{ $section }}" class="hidden">
                        <span class="block px-3 pt-2 pb-1.5 font-mono text-[10px] uppercase tracking-[0.14em] text-[#888888]">{{ $heading }}</span>
                        <div class="search-items-container space-y-0.5"></div>
                    </div>
                @endforeach
                <div id="search-no-results" class="hidden text-center py-8 text-sm text-[#888888]">
                    Nothing matches that. Try another word.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
