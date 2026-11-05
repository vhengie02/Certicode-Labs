{{-- Flash messages and validation errors for auth forms, shown as toasts. Optional: $errorTitle, $errorHint, $inlineFields (fields whose errors the form shows beside the input). --}}
@php($boxErrors = collect($errors->getMessages())->except($inlineFields ?? [])->flatten())
@push('toasts')
    @foreach (['status' => 'success', 'success' => 'success', 'warning' => 'warning', 'error' => 'error'] as $key => $tone)
        @if (session($key))
            <x-toast :tone="$tone"><p>{{ session($key) }}</p></x-toast>
        @endif
    @endforeach

    @if ($boxErrors->isNotEmpty())
        <x-toast tone="error" :title="$errorTitle ?? null">
            <ul>
                @foreach ($boxErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            @if (! empty($errorHint))
                <p class="toast-hint">{{ $errorHint }}</p>
            @endif
        </x-toast>
    @endif
@endpush
