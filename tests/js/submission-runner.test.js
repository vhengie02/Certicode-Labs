// Tests the "Run the submitted code" panel: how a run's result is labelled and which parts of it
// are shown, and that a drawer shared by every student keeps a result only for its own attempt.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import submissionRunner, { describeRun } from '../../resources/js/submission-runner.js';

test('a successful run shows its output, exit code and timings', () => {
    const summary = describeRun({
        status: 'success', output: 'Hello\n', errors: null, stderr: '', compile_error: false,
        exit_code: 0, execution_time_ms: 812.4, cpu_time_ms: 42, memory_kb: 15360,
    });

    assert.equal(summary.label, 'Ran');
    assert.equal(summary.tone, 'brand');
    assert.deepEqual(summary.facts, ['Exit code 0', '812 ms total', '42 ms CPU', '15.0 MB']);
    assert.equal(summary.output, 'Hello\n');
    assert.equal(summary.errorText, '');
});

test('a compile error is labelled as one and shows the compiler output', () => {
    const summary = describeRun({
        status: 'error', output: '', errors: "Java Compilation Error:\nMain.java:2: error: ';' expected", compile_error: true, exit_code: null, execution_time_ms: 1500,
    });

    assert.equal(summary.label, 'Compile error');
    assert.equal(summary.tone, 'danger');
    assert.equal(summary.errorLabel, 'Compiler output');
    assert.match(summary.errorText, /';' expected/);
    assert.deepEqual(summary.facts, ['1.50 s total']);
});

test('a runtime error shows stderr once, not repeated as the error text', () => {
    const stderr = 'Exception in thread "main" java.lang.ArithmeticException: / by zero\n';
    const summary = describeRun({ status: 'error', output: 'before\n', errors: stderr, stderr, compile_error: false, exit_code: 1 });

    assert.equal(summary.label, 'Runtime error');
    assert.equal(summary.stderr, stderr);
    assert.equal(summary.errorText, '');
    assert.deepEqual(summary.facts, ['Exit code 1']);
});

test('a time-out and a run that never happened are told apart', () => {
    assert.equal(describeRun({ status: 'error', errors: 'Execution Timed Out (Maximum execution limit of 5.0 seconds reached).' }).label, 'Timed out');

    const notRun = describeRun({ status: 'unavailable', output: '', errors: 'The code execution service returned an error. Please try again.' });
    assert.equal(notRun.label, 'Not run');
    assert.equal(notRun.tone, 'warn');
    assert.equal(notRun.errorLabel, 'Why it did not run');
});

test("a shared drawer shows a result only on the attempt it came from", async () => {
    let studentId = 1;
    globalThis.document = { querySelector: () => ({ getAttribute: () => 'token' }) };
    const requests = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, body: JSON.parse(options.body), token: options.headers['X-CSRF-TOKEN'] });
        return { ok: true, status: 200, json: async () => ({ status: 'success', output: 'hi\n' }) };
    };

    const runner = submissionRunner({ url: () => `/instructor/sessions/${studentId}/run` });
    runner.stdin = 'ignored unless the input box is open';
    await runner.run();

    assert.deepEqual(requests, [{ url: '/instructor/sessions/1/run', body: { stdin: '' }, token: 'token' }]);
    assert.equal(runner.current.output, 'hi\n');
    assert.equal(runner.summary.label, 'Ran');

    studentId = 2;
    assert.equal(runner.current, null);
    assert.equal(runner.summary, null);
});

test('a refused or throttled run shows a message instead of a result', async () => {
    globalThis.document = { querySelector: () => null };
    globalThis.fetch = async () => ({ ok: false, status: 503, json: async () => ({ message: 'Code execution (Judge0) is not set up on this server, so the code was not run.' }) });

    const runner = submissionRunner({ url: '/instructor/sessions/7/run' });
    runner.showStdin = true;
    runner.stdin = '5\n';
    await runner.run();
    assert.equal(runner.current, null);
    assert.match(runner.currentError, /not set up/);
    assert.equal(runner.running, false);

    globalThis.fetch = async () => ({ ok: false, status: 429, json: async () => ({}) });
    await runner.run();
    assert.match(runner.currentError, /Too many runs/);
});
