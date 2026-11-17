{{--
    "Run the submitted code" button and output panel, for staff reviewing an attempt.
    $runUrl: a JavaScript expression for the run URL, a string or a function returning one
    (the monitoring drawer passes a function, because one drawer shows every student).
    $readsInput: a JavaScript expression, true/false or a function returning one: whether the code
    reads standard input (LabSession::submissionReadsInput), so the input box shows only then.
--}}
<div x-data="submissionRunner({ url: {{ $runUrl }}, readsInput: {{ $readsInput }} })" data-submission-runner class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-medium text-[#ededed]">Run the submitted code</h3>
            <p class="text-xs text-[#888888]">Runs the code exactly as it was handed in, on the code runner. The grade doesn't change.</p>
        </div>
        <button type="button" class="ui-btn ui-btn-primary ui-btn-sm shrink-0" @click="run()" :disabled="running" :aria-busy="running.toString()">
            <span x-text="running ? 'Running…' : (current ? 'Run again' : 'Run code')">Run code</span>
        </button>
    </div>

    {{-- The runner takes all input up front (it can't stop and wait for typing), so it is asked
         for here, and only when the code reads some or a run stopped for want of it --}}
    <label x-show="inputShown" x-cloak class="block">
        <span class="ui-label">Input <span class="ui-optional">(this program reads input: type what it would read, one value per line)</span></span>
        <textarea x-model="stdin" rows="3" maxlength="65536" spellcheck="false" placeholder="One value per line"
                  class="ui-input ui-input-mono mt-1 w-full min-h-[4.5rem] py-2 text-[13px]"></textarea>
    </label>

    <p x-show="summary && summary.ranOutOfInput" x-cloak class="text-sm text-amber-300" role="status"
       x-text="current && current.had_input ? 'The program stopped because it wanted more input than was given. Add the rest above and run again.' : 'The program stopped because it wanted input and none was given. Type its input above and run again.'"></p>

    <p x-show="currentError" x-cloak x-text="currentError" class="text-sm text-red-300" role="alert"></p>

    <div aria-live="polite">
        <template x-if="summary">
            <div class="rounded-xl border border-[#2e2e2e] bg-[#0f0f0f] overflow-hidden">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-4 py-2.5 border-b border-[#232323]">
                    <span class="ui-badge text-[11px]"
                          :class="{ 'ui-badge-brand': summary.tone === 'brand', 'ui-badge-warn': summary.tone === 'warn', 'ui-badge-danger': summary.tone === 'danger' }"
                          x-text="summary.label"></span>
                    <template x-for="fact in summary.facts" :key="fact">
                        <span class="text-xs tabular-nums text-[#888888]" x-text="fact"></span>
                    </template>
                    <span class="ml-auto text-xs text-[#888888] truncate max-w-full" x-show="current.files && current.files.length"
                          x-text="'Ran ' + current.files.join(', ') + ' (' + current.language + ')'"></span>
                </div>

                <template x-if="summary.errorText">
                    <div class="px-4 pt-3">
                        <p class="text-xs font-medium" :class="summary.tone === 'warn' ? 'text-amber-300' : 'text-red-300'" x-text="summary.errorLabel"></p>
                        <pre class="mt-1 max-h-72 overflow-auto whitespace-pre-wrap break-words font-mono text-[13px] leading-relaxed text-[#fca5a5]" x-text="summary.errorText"></pre>
                    </div>
                </template>

                <div class="px-4 py-3" x-show="current.status !== 'unavailable'">
                    <p class="text-xs font-medium text-[#a3a3a3]">Output</p>
                    <pre x-show="summary.output !== ''" class="mt-1 max-h-[50vh] overflow-auto whitespace-pre-wrap break-words font-mono text-[13px] leading-relaxed text-[#d4d4d4]" x-text="summary.output"></pre>
                    <p x-show="summary.output === ''" class="mt-1 text-sm text-[#888888]">The program printed nothing.</p>
                </div>

                <template x-if="summary.stderr">
                    <div class="px-4 pb-3">
                        <p class="text-xs font-medium text-[#a3a3a3]">Error output (stderr)</p>
                        <pre class="mt-1 max-h-72 overflow-auto whitespace-pre-wrap break-words font-mono text-[13px] leading-relaxed text-[#fca5a5]" x-text="summary.stderr"></pre>
                    </div>
                </template>

                <template x-if="current.skipped_files && current.skipped_files.length">
                    <p class="px-4 pb-3 text-xs text-[#888888]"
                       x-text="'Only the main file runs for this language, as when the student submitted. Not run: ' + current.skipped_files.join(', ') + '.'"></p>
                </template>
            </div>
        </template>
    </div>
</div>
