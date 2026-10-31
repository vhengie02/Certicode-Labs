{{--
    A date, or a date and time, in the viewer's own time zone. The server renders it in UTC
    (labelled, for times) and the browser rewrites it in local time. Usage: x-local-time :at="$date",
    with date set to true for the date alone.
--}}
@props(['at', 'date' => false])
<time datetime="{{ $at->toIso8601String() }}" data-local-time="{{ $date ? 'date' : 'datetime' }}" {{ $attributes }}>{{ $date ? $at->format('M j, Y') : $at->format('M j, Y, g:i A').' UTC' }}</time>@once<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('time[data-local-time]').forEach((el) => {
        const at = new Date(el.getAttribute('datetime'));
        if (isNaN(at)) return;
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        if (el.dataset.localTime === 'datetime') Object.assign(options, { hour: 'numeric', minute: '2-digit' });
        el.textContent = at.toLocaleString(undefined, options);
    });
});
</script>@endonce
