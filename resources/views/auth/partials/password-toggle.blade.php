{{-- Show/hide button for a password field; pair with auth.partials.password-toggle-script. --}}
<button type="button" data-toggle-password="{{ $target }}" aria-label="Show password" aria-pressed="false"
        class="absolute inset-y-0 right-0 px-3.5 flex items-center text-[var(--cc-text-faint)] hover:text-white transition-colors rounded-r-[10px]">
    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path data-eye="open" stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
        <path data-eye="closed" class="hidden" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c6 0 9.5 7 9.5 7a17 17 0 0 1-3 3.9M6.6 6.6C3.9 8.4 2.5 12 2.5 12S6 19 12 19a9.6 9.6 0 0 0 5.4-1.6"/>
    </svg>
</button>
