import { fakeSecrets, files, memento, setWindowFocused, shown, stubState, vscodeStub } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, beforeEach, afterEach, mock } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const realShowWarning = vscodeStub.window.showWarningMessage;

/**
 * On Windows a modal dialog is a native OS window, so VS Code loses OS focus while it is
 * open. This stands in for that: the window blurs when a modal dialog opens and regains
 * focus when the student answers it.
 */
function modalDialogsTakeOsFocus() {
    vscodeStub.window.showWarningMessage = async (message: string, ...rest: any[]) => {
        const modal = typeof rest[0] === 'object' && rest[0]?.modal === true;
        if (modal) { setWindowFocused(false); }
        const answer = await realShowWarning(message, ...rest);
        if (modal) { setWindowFocused(true); }
        return answer;
    };
}

/** A provider in session 7 with focus tracking on, keeping every telemetry event it sends. */
function trackedProvider(state = memento()) {
    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any, fakeSecrets());
    const events: string[] = [];
    provider._sessionId = 7;
    provider.sendTelemetry = async (eventType: string) => { events.push(eventType); };
    provider.verifyWorkspaceFileIntegrity = async () => undefined;
    provider.computeAndSyncDiffs = async () => undefined;
    provider.setupFocusTracking();
    return { provider, events };
}

beforeEach(() => {
    files.clear();
    stubState.warningAnswer = undefined;
    modalDialogsTakeOsFocus();
});

afterEach(() => {
    vscodeStub.window.showWarningMessage = realShowWarning;
    mock.timers.reset();
});

test('switching away from VS Code is a focus loss', () => {
    const { provider, events } = trackedProvider();

    setWindowFocused(false);

    assert.deepStrictEqual(events, ['focus_lost']);
    provider._windowStateListener.dispose();
});

test('the "files from another lab" dialog at lab start is not a focus loss', async () => {
    const state = memento();
    const first: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any, fakeSecrets());
    first._sessionId = 1;
    first.verifyWorkspaceFileIntegrity = async () => undefined;
    await first.provisionStarterFiles([{ name: 'Main.java', content: 'class Main { /* lab 1 */ }', is_primary: true }]);
    files.set('/workspace/Main.java', 'class Main { int lab1Solution; }');

    const { provider, events } = trackedProvider(state);
    stubState.warningAnswer = 'Keep my files';
    await provider.provisionStarterFiles([{ name: 'Main.java', content: 'class Main { /* lab 2 */ }', is_primary: true }]);

    assert.ok(!events.includes('focus_lost'), `got ${events.join(', ')}`);
    provider._windowStateListener.dispose();
});

test('the submit confirmation dialog is not a focus loss', async () => {
    const { provider, events } = trackedProvider();
    provider.collectSubmissionFiles = async () => ({
        primaryCode: 'class Main {}',
        primaryFileName: 'Main.java',
        filesPayload: [{ name: 'Main.java', content: 'class Main {}', is_primary: true }],
        fileUris: [],
    });
    stubState.warningAnswer = undefined; // the student cancels
    shown.length = 0;

    await provider.handleSubmit();

    assert.ok(shown.some(s => s.level === 'warning' && s.message.startsWith('Submit Lab Solution')));
    assert.ok(!events.includes('focus_lost'), `got ${events.join(', ')}`);
    provider._windowStateListener.dispose();
});

test('a blur that reaches the extension just after the dialog closes is not a focus loss', async () => {
    mock.timers.enable({ apis: ['Date'], now: 1_000_000 });
    const { provider, events } = trackedProvider();
    vscodeStub.window.showWarningMessage = realShowWarning;

    await provider.showModalWarning('Sure?', 'Yes');
    mock.timers.tick(200);
    setWindowFocused(false);

    assert.deepStrictEqual(events, []);
    provider._windowStateListener.dispose();
});

test('leaving VS Code after the dialog is answered is a focus loss again', async () => {
    mock.timers.enable({ apis: ['Date'], now: 1_000_000 });
    const { provider, events } = trackedProvider();

    await provider.showModalWarning('Sure?', 'Yes');
    mock.timers.tick(5000);
    setWindowFocused(false);

    assert.deepStrictEqual(events, ['focus_lost']);
    provider._windowStateListener.dispose();
});
