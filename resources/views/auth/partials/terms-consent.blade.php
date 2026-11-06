<label class="flex items-start gap-2.5 text-[13px] leading-relaxed text-[var(--cc-text-dim)] cursor-pointer">
    <input name="terms" type="checkbox" value="1" required @checked(old('terms')) class="cc-checkbox mt-0.5 shrink-0" @error('terms') aria-invalid="true" @enderror>
    <span>
        I'm 18 or older, or my school enrolled me with my parent or guardian's consent. I agree to the
        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="cc-link">Terms of Service</a> and
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="cc-link">Privacy Policy</a>.
    </span>
</label>
@error('terms') <p class="mt-2 text-[13px] text-red-400">{{ $message }}</p> @enderror
