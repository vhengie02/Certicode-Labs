import { files, memento, shown, stubState } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, beforeEach } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const LAB1 = [{ name: 'Main.java', content: 'class Main { /* lab 1 */ }', is_primary: true }];
const LAB2 = [{ name: 'Main.java', content: 'class Main { /* lab 2 */ }', is_primary: true }];

function provider(state = memento()) {
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any);
    p.verifyWorkspaceFileIntegrity = async () => undefined;
    p.computeAndSyncDiffs = async () => undefined;
    p.ensureTrustedBackend = async () => 'https://labs.example';
    p.startMonitoring = () => undefined;
    p.sendVsCodePing = async () => undefined;
    return p;
}

beforeEach(() => {
    files.clear();
    shown.length = 0;
    stubState.warningAnswer = undefined;
    stubState.clipboard = '';
});

test('resuming the same session keeps the student\'s work without asking', async () => {
    const state = memento();
    const p = provider(state);
    p._sessionId = 1;
    await p.provisionStarterFiles(LAB1);
    files.set('/workspace/Main.java', 'class Main { int myWork; }');

    const again = provider(state);
    again._sessionId = 1;
    await again.provisionStarterFiles(LAB1);

    assert.strictEqual(files.get('/workspace/Main.java'), 'class Main { int myWork; }');
    assert.ok(!shown.some(s => s.message.includes('another lab')));
});

test('a new lab in the same folder offers to replace the old lab\'s file and backs it up', async () => {
    const state = memento();
    const p = provider(state);
    p._sessionId = 1;
    await p.provisionStarterFiles(LAB1);
    files.set('/workspace/Main.java', 'class Main { int lab1Solution; }');

    stubState.warningAnswer = 'Replace files';
    const next = provider(state);
    next._sessionId = 2;
    await next.provisionStarterFiles(LAB2);

    assert.ok(shown.some(s => s.level === 'warning' && s.message.includes('Main.java')));
    assert.strictEqual(files.get('/workspace/Main.java'), LAB2[0].content);
    const backups = [...files.entries()].filter(([path]) => path.startsWith('/workspace/.certicode/previous/'));
    assert.strictEqual(backups.length, 1);
    assert.strictEqual(backups[0][1], 'class Main { int lab1Solution; }');
});

test('switching sessions forgets the previous lab\'s starter baseline', async () => {
    const p = provider();
    p._sessionId = 1;
    await p.provisionStarterFiles(LAB1);
    assert.strictEqual(p._starterFileSnapshots.get('Main.java'), LAB1[0].content);

    await p.connectToSession('https://labs.example', 2, 'token');

    assert.strictEqual(p._starterFileSnapshots.size, 0);
    assert.strictEqual(p._lastSessionData, null);
});

/**
 * Feed the provider one big insertion, as VS Code reports it, and return the telemetry sent.
 */
async function insert(text: string, { dirty = true } = {}) {
    const p = provider();
    const sent: any[] = [];
    p._sessionId = 1;
    p._lastSessionData = { laboratory: { starter_files: LAB1 } };
    p.recordActivity = () => undefined;
    p.sendTelemetry = async (type: string, payload: any) => { sent.push({ type, payload }); };
    p.handleDocumentChange({
        document: { uri: { scheme: 'file', fsPath: '/workspace/Main.java' }, fileName: '/workspace/Main.java', isDirty: dirty },
        contentChanges: [{ text }],
        reason: undefined,
    });
    await new Promise(r => setTimeout(r, 10));
    return sent.filter(s => s.type === 'paste_anomaly');
}

const SNIPPET = 'public static void main(String[] args) {\n        \n    }';

test('a snippet expansion or quick fix is not flagged as a paste', async () => {
    stubState.clipboard = 'something else the student copied';
    assert.strictEqual((await insert(SNIPPET)).length, 0);
});

test('a reload from disk is not flagged as a paste', async () => {
    stubState.clipboard = SNIPPET;
    assert.strictEqual((await insert(SNIPPET, { dirty: false })).length, 0);
});

test('text that came from the clipboard is still flagged', async () => {
    stubState.clipboard = '  public static void main(String[] args) {\n\n  }';
    assert.strictEqual((await insert(SNIPPET)).length, 1);
});
