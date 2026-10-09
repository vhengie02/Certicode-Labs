{{-- Student/Instructor radio cards (name=role). Instructor access is granted by an admin. --}}
<fieldset>
    <legend class="cc-label">I'm joining as</legend>
    <div class="grid grid-cols-2 gap-3">
        @foreach (['student' => ['Student', 'Take labs, earn certificates'], 'instructor' => ['Instructor', 'Run classes; an admin approves access']] as $value => [$label, $hint])
            <label class="group relative cursor-pointer">
                <input type="radio" name="role" value="{{ $value }}" class="peer sr-only" required @checked(old('role', 'student') === $value)>
                <span class="block h-full rounded-[10px] border border-[var(--cc-line-strong)] bg-white/[0.02] p-3.5 transition-all duration-200
                             group-hover:border-white/25
                             peer-checked:border-[#3ecf8e] peer-checked:bg-[#3ecf8e]/[0.06] peer-checked:shadow-[0_0_0_4px_rgba(62,207,142,0.12)]
                             peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-[#3ecf8e] peer-focus-visible:outline-offset-2">
                    <span class="flex items-center justify-between">
                        <span class="text-[15px] font-semibold">{{ $label }}</span>
                        <span class="w-4 h-4 rounded-full border border-white/30 flex items-center justify-center transition-colors group-has-[:checked]:border-[#3ecf8e]" aria-hidden="true">
                            <span class="w-2 h-2 rounded-full bg-[#3ecf8e] scale-0 transition-transform duration-200 group-has-[:checked]:scale-100"></span>
                        </span>
                    </span>
                    <span class="mt-1 block text-[13px] text-[var(--cc-text-dim)] leading-snug">{{ $hint }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
