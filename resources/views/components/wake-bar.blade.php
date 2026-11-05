{{--
    A read-only progress bar drawn as a row of bars, lit up to a percentage. It renders at its final
    value, and resources/js/wake-bar.js replays the fill from 0. Usage: x-wake-bar :value="$percent"
    label="Labs done", with marker and marker-title for a tick at a percentage, and muted for a grey
    fill.
--}}
@props(['value', 'label', 'bars' => 32, 'muted' => false, 'marker' => null, 'markerTitle' => null])
@php
    $percent = min(100, max(0, (float) $value));
    $lit = (int) round($percent / 100 * $bars);
@endphp
<div data-wake-bar {{ $attributes->class(['wake-bar', 'wake-bar--muted' => $muted]) }} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ $label }}">
    @for ($i = 0; $i < $bars; $i++)
        <span class="wake-bar__bar" data-on="{{ $i < $lit ? 'true' : 'false' }}"></span>
    @endfor
    @if ($marker !== null)
        <span class="absolute inset-y-0 w-0.5 rounded bg-[#ededed]/60" style="left: {{ $marker }}%" title="{{ $markerTitle }}" aria-hidden="true"></span>
    @endif
</div>
