{{-- One pop-up notification. Lives inside <x-toast-region>; resources/js/toast.js handles timing and dismissal. --}}
@props(['tone' => 'success', 'title' => null])

<div data-toast data-tone="{{ $tone }}" class="toast toast-{{ $tone }}" role="{{ $tone === 'error' ? 'alert' : 'status' }}">
    @if ($tone === 'success')
        <svg class="toast-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    @elseif ($tone === 'error')
        <svg class="toast-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
    @else
        <svg class="toast-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
    @endif
    <div class="toast-body">
        @if ($title)
            <p class="toast-title">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
    <button type="button" data-toast-close class="toast-close" aria-label="Dismiss">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
