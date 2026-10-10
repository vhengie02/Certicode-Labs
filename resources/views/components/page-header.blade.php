{{-- Page title block: optional back link, eyebrow, title, subtitle, and actions on the right (default slot). --}}
@props(['title', 'subtitle' => null, 'eyebrow' => null, 'back' => null, 'backLabel' => 'Back'])
<header {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-sm text-[#888888] hover:text-[#ededed] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ $backLabel }}
            </a>
        @endif
        @if ($eyebrow)
            <p class="ui-eyebrow {{ $back ? 'mt-3' : '' }}">{{ $eyebrow }}</p>
        @endif
        <h1 class="cc-display {{ $back || $eyebrow ? 'mt-1.5' : '' }} text-3xl font-bold text-[#ededed] break-words">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 text-[15px] text-[#a3a3a3] max-w-2xl">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">{{ $slot }}</div>
    @endif
</header>
