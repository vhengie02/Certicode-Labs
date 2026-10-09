{{-- Flash messages and validation errors for auth forms. Optional: $errorTitle. --}}
@foreach (['status' => 'success', 'success' => 'success', 'warning' => 'warning'] as $key => $tone)
    @if (session($key))
        <div class="cc-notice cc-notice-{{ $tone }}" role="status">
            @if ($tone === 'success')
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            @else
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            @endif
            <p>{{ session($key) }}</p>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="cc-notice cc-notice-error" role="alert">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <div>
            @isset($errorTitle)
                <p class="font-medium">{{ $errorTitle }}</p>
            @endisset
            <ul class="{{ isset($errorTitle) ? 'mt-1' : '' }} space-y-0.5 text-[#fca5a5]">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
