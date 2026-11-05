{{-- Fixed stack for pop-up notifications. Pages add to it with @push('toasts'); scripts with window.showToast(). --}}
<div id="toast-region" class="toast-region" aria-label="Notifications">
    {{ $slot }}
    @stack('toasts')
</div>
<noscript><style>.toast { transform: none; opacity: 1; }</style></noscript>

@foreach (['success', 'error', 'warning'] as $tone)
    <template data-toast-template="{{ $tone }}">
        <x-toast :tone="$tone"><p data-toast-message></p></x-toast>
    </template>
@endforeach
