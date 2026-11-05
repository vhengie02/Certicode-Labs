{{--
    A date, or a date and time, for the viewer. With a saved time zone the server renders it in that
    zone. Without one it renders in UTC (labelled, for times) and the browser rewrites it in device
    time. Either way the viewer's date style applies. Usage: x-local-time :at="$date", with date set
    to true for the date alone.
--}}
@props(['at', 'date' => false])
@php
    $zone = \App\Support\DisplayTime::zone();
    $text = \App\Support\DisplayTime::format($at, $date);
@endphp
@if ($zone)
<time datetime="{{ $at->toIso8601String() }}" {{ $attributes }}>{{ $text }}</time>
@else
<time datetime="{{ $at->toIso8601String() }}" data-local-time="{{ $date ? 'date' : 'datetime' }}" data-date-style="{{ \App\Support\DisplayTime::style() }}" {{ $attributes }}>{{ $date ? $text : $text.' UTC' }}</time>@once<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('time[data-local-time]').forEach((el) => {
        const at = new Date(el.getAttribute('datetime'));
        if (isNaN(at)) return;
        const style = el.dataset.dateStyle;
        const locale = { mdy: 'en-US', dmy: 'en-GB', ymd: 'sv-SE' }[style];
        const options = style === 'ymd'
            ? { month: '2-digit', day: '2-digit', year: 'numeric' }
            : { month: 'short', day: 'numeric', year: 'numeric' };
        if (el.dataset.localTime === 'datetime') Object.assign(options, { hour: style === 'ymd' ? '2-digit' : 'numeric', minute: '2-digit' });
        el.textContent = at.toLocaleString(locale, options);
    });
});
</script>@endonce
@endif
