{{--
    An (i) button that explains a stat. Shows on hover and on keyboard focus, so it also
    works on touch screens (tap focuses it). Usage: x-info-tip with an optional align attribute, explanation text as the slot.
    align: center (default) | left (opens rightwards) | right (opens leftwards, for tiles at the right edge)
--}}
@props(['align' => 'center', 'label' => 'What does this mean?'])
@php
    $tipId = 'tip-' . \Illuminate\Support\Str::random(8);
@endphp
<span {{ $attributes->merge(['class' => 'info-tip info-tip-' . $align]) }}>
    <button type="button" class="info-tip-btn" aria-label="{{ $label }}" aria-describedby="{{ $tipId }}">
        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.2v3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="5.1" r=".85" fill="currentColor"/></svg>
    </button>
    <span role="tooltip" id="{{ $tipId }}" class="info-tip-bubble">{{ $slot }}</span>
</span>
