import { fakeSecrets, shown, stubState } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

/**
 * A provider connected to session 7, with the workspace and server calls replaced.
 * Each grading request stays open until the test answers it.
 */
function connectedProvider() {
    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, { get: (_k: string, d: any) => d, update: async () => undefined } as any, fakeSecrets());
    const requests: { path: string; answer: (status: number, body: object) => void }[] = [];

    provider._sessionId = 7;
    provider.recordActivity = () => undefined;
    provider.syncSessionState = async () => undefined;
    provider.stopSessionTimers = () => undefined;
    provider.collectSubmissionFiles = async () => ({
        primaryCode: 'class Main {}',
        primaryFileName: 'Main.java',
        filesPayload: [{ name: 'Main.java', content: 'class Main {}', is_primary: true }],
        fileUris: [],
    });
    provider.collectDiagnostics = () => [];
    provider.makeRequest = (_method: string, path: string) => new Promise(resolve => {
        requests.push({ path, answer: (status, body) => resolve({ status, body: JSON.stringify(body) }) });
    });

    return { provider, requests };
}

test('a second check-progress click while grading sends no second request', async () => {
    const { provider, requests } = connectedProvider();

    const first = provider.handleCheckProgress();
    await new Promise(r => setImmediate(r));
    const second = provider.handleCheckProgress();
    await new Promise(r => setImmediate(r));
    await second;

    assert.strictEqual(requests.length, 1);
    assert.ok(shown.some(s => s.message.includes('still being graded')));

    requests[0].answer(200, { completed_tasks: [], performance_score: 50, evaluation: {} });
    await first;

    // Once the grade is back, checking again works
    const third = provider.handleCheckProgress();
    await new Promise(r => setImmediate(r));
    assert.strictEqual(requests.length, 2);
    requests[1].answer(200, { completed_tasks: [], performance_score: 60, evaluation: {} });
    await third;
});

test('submit is refused while a check-progress is still grading', async () => {
    const { provider, requests } = connectedProvider();

    const check = provider.handleCheckProgress();
    await new Promise(r => setImmediate(r));
    await provider.handleSubmit();

    assert.strictEqual(requests.length, 1);
    assert.ok(requests[0].path.endsWith('/check-progress'));

    requests[0].answer(200, { completed_tasks: [], performance_score: 50, evaluation: {} });
    await check;
});

test('a failed grading request frees the lock and shows the server message', async () => {
    const { provider, requests } = connectedProvider();

    const check = provider.handleCheckProgress();
    await new Promise(r => setImmediate(r));
    requests[0].answer(409, { error: 'session_not_in_progress', message: 'This lab attempt has already ended, so it can no longer be graded.' });
    await check;

    assert.ok(shown.some(s => s.level === 'error' && s.message.includes('already ended')));
    assert.strictEqual(provider._gradingInFlight, false);
});

/** A provider ready to submit, keeping every message it sends the sidebar. */
function submittingProvider(warningAnswer: string | undefined) {
    const { provider, requests } = connectedProvider();
    const posted: any[] = [];

    provider._view = { webview: { postMessage: (m: any) => { posted.push(m); } } };
    provider.syncActivityBeforeSubmit = async () => undefined;
    stubState.warningAnswer = warningAnswer;

    return { provider, requests, posted };
}

test('confirming a submit stops the clock before the grader answers', async () => {
    const { provider, requests, posted } = submittingProvider('Yes, Submit');

    const submit = provider.handleSubmit();
    await new Promise(r => setImmediate(r));

    assert.strictEqual(requests.length, 1);
    assert.ok(posted.some(m => m.type === 'submitting'));
    assert.ok(!posted.some(m => m.type === 'submitResult'));

    requests[0].answer(200, { performance_score: 80 });
    await submit;
});

test('cancelling the submit dialog leaves the clock running', async () => {
    const { provider, requests, posted } = submittingProvider(undefined);

    await provider.handleSubmit();

    assert.strictEqual(requests.length, 0);
    assert.ok(!posted.some(m => m.type === 'submitting'));
});

test('a failed sync before submitting reports an error, so the clock restarts', async () => {
    const { provider, requests, posted } = submittingProvider('Yes, Submit');
    provider.syncActivityBeforeSubmit = async () => { throw new Error('offline'); };

    await provider.handleSubmit();

    assert.strictEqual(requests.length, 0);
    assert.ok(posted.some(m => m.type === 'error' && m.message.includes('offline')));
});
