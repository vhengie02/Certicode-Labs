// "Run the submitted code" on an attempt, for the instructor reviewing it: posts to
// /instructor/sessions/{id}/run (Judge0) and shows what the program printed. The run never
// changes the attempt. Used on the results page and in the monitoring drawer, where one panel
// serves every student, so `url` may be a function and a result is kept only for its own URL.

function duration(ms) {
    if (ms === null || ms === undefined || Number.isNaN(Number(ms))) return '';
    const value = Number(ms);
    return value < 1000 ? `${Math.round(value)} ms` : `${(value / 1000).toFixed(2)} s`;
}

// What a program prints when it reads past the end of its input: Python's input() and Java's Scanner
const OUT_OF_INPUT = /\bEOFError\b|java\.util\.NoSuchElementException/;

/**
 * Whether a run crashed because it wanted more input than it was given. The runner hands a
 * program all its input up front, so one that reads past the end stops with an error.
 */
export function ranOutOfInput(result) {
    if (!result || result.status !== 'error' || result.compile_error) return false;
    return OUT_OF_INPUT.test(`${result.stderr || ''}\n${result.errors || ''}`);
}

/**
 * How a run ended, for the panel's heading line: a label, a tone (brand, warn or danger) and
 * the figures worth showing.
 */
export function describeRun(result) {
    let label = 'Ran';
    let tone = 'brand';

    if (result.status === 'unavailable') {
        label = 'Not run';
        tone = 'warn';
    } else if (result.compile_error) {
        label = 'Compile error';
        tone = 'danger';
    } else if (result.status === 'error' && String(result.errors || '').startsWith('Execution Timed Out')) {
        label = 'Timed out';
        tone = 'danger';
    } else if (result.status === 'error') {
        label = 'Runtime error';
        tone = 'danger';
    }

    const facts = [];
    if (result.exit_code !== null && result.exit_code !== undefined) facts.push(`Exit code ${result.exit_code}`);
    if (result.execution_time_ms !== null && result.execution_time_ms !== undefined) facts.push(`${duration(result.execution_time_ms)} total`);
    if (result.cpu_time_ms !== null && result.cpu_time_ms !== undefined) facts.push(`${duration(result.cpu_time_ms)} CPU`);
    if (result.memory_kb) facts.push(`${(result.memory_kb / 1024).toFixed(1)} MB`);

    // The error text, unless it only repeats what stderr already shows
    const stderr = String(result.stderr || '');
    const errors = String(result.errors || '');
    const errorText = errors && errors.trim() !== stderr.trim() ? errors : '';

    return {
        label,
        tone,
        facts,
        ranOutOfInput: ranOutOfInput(result),
        errorLabel: result.compile_error ? 'Compiler output' : (result.status === 'unavailable' ? 'Why it did not run' : 'Error'),
        errorText,
        stderr,
        output: String(result.output || ''),
    };
}

export default function submissionRunner({ url, readsInput = false }) {
    const target = () => (typeof url === 'function' ? url() : url);
    const codeReadsInput = () => Boolean(typeof readsInput === 'function' ? readsInput() : readsInput);

    return {
        stdin: '',
        running: false,
        // Kept with the URL it came from, so another student's drawer starts empty
        result: null,
        error: '',
        resultFor: null,
        // The attempt whose run stopped for want of input, though its code didn't look like it read any
        inputNeededFor: null,

        // The input box shows when the code reads input, or once a run has shown it does
        get inputShown() {
            return codeReadsInput() || this.inputNeededFor === target();
        },
        get current() {
            return this.resultFor === target() ? this.result : null;
        },
        get currentError() {
            return this.resultFor === target() ? this.error : '';
        },
        get summary() {
            return this.current ? describeRun(this.current) : null;
        },

        async run() {
            if (this.running) return;
            const runUrl = target();
            const sentInput = this.inputShown ? this.stdin : '';
            this.running = true;
            this.error = '';
            this.resultFor = runUrl;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const response = await fetch(runUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ stdin: sentInput }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.result = null;
                    this.error = response.status === 429
                        ? 'Too many runs in a minute. Wait a moment and try again.'
                        : (data.message || "Couldn't run the code. Try again.");
                    return;
                }
                // Whether this run had any input, for the out-of-input hint ("none" or "more")
                this.result = { ...data, had_input: sentInput.trim() !== '' };
                if (ranOutOfInput(data)) this.inputNeededFor = runUrl;
            } catch {
                this.result = null;
                this.error = "Couldn't reach the server. Check your connection and try again.";
            } finally {
                this.running = false;
            }
        },
    };
}
