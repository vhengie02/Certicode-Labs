// "Run the submitted code" on an attempt, for the instructor reviewing it: posts to
// /instructor/sessions/{id}/run (Judge0) and shows what the program printed. The run never
// changes the attempt. Used on the results page and in the monitoring drawer, where one panel
// serves every student, so `url` may be a function and a result is kept only for its own URL.

function duration(ms) {
    if (ms === null || ms === undefined || Number.isNaN(Number(ms))) return '';
    const value = Number(ms);
    return value < 1000 ? `${Math.round(value)} ms` : `${(value / 1000).toFixed(2)} s`;
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
        errorLabel: result.compile_error ? 'Compiler output' : (result.status === 'unavailable' ? 'Why it did not run' : 'Error'),
        errorText,
        stderr,
        output: String(result.output || ''),
    };
}

export default function submissionRunner({ url }) {
    const target = () => (typeof url === 'function' ? url() : url);

    return {
        stdin: '',
        showStdin: false,
        running: false,
        // Kept with the URL it came from, so another student's drawer starts empty
        result: null,
        error: '',
        resultFor: null,

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
            this.running = true;
            this.error = '';
            this.resultFor = runUrl;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const response = await fetch(runUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ stdin: this.showStdin ? this.stdin : '' }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.result = null;
                    this.error = response.status === 429
                        ? 'Too many runs in a minute. Wait a moment and try again.'
                        : (data.message || "Couldn't run the code. Try again.");
                    return;
                }
                this.result = data;
            } catch {
                this.result = null;
                this.error = "Couldn't reach the server. Check your connection and try again.";
            } finally {
                this.running = false;
            }
        },
    };
}
