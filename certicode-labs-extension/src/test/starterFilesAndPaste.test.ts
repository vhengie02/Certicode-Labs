import { fakeSecrets, files, memento, shown, stubState, vscodeStub } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, beforeEach } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const LAB1 = [{ name: 'Main.java', content: 'class Main { /* lab 1 */ }', is_primary: true }];
const LAB2 = [{ name: 'Main.java', content: 'class Main { /* lab 2 */ }', is_primary: true }];

function provider(state = memento()) {
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any, fakeSecrets());
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
async function insert(text: string, { dirty = true, starterFiles = LAB1 } = {}) {
    const p = provider();
    const sent: any[] = [];
    p._sessionId = 1;
    p._lastSessionData = { laboratory: { starter_files: starterFiles } };
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

const MULTILINE_STARTER = [{
    name: 'Strings.java',
    content: 'public class Strings {\n    public static String reverse(String s) {\n        return s;\n    }\n}\n',
    is_primary: true,
}];

test('starter code with Windows line endings is not flagged, with no snapshot taken', async () => {
    const crlf = MULTILINE_STARTER[0].content.replace(/\n/g, '\r\n');
    stubState.clipboard = crlf;
    assert.strictEqual((await insert(crlf, { starterFiles: MULTILINE_STARTER })).length, 0);
});

test('a paste that only starts like the starter code is still flagged', async () => {
    const solution = MULTILINE_STARTER[0].content.replace('return s;', 'return new StringBuilder(s).reverse().toString();');
    stubState.clipboard = solution;
    assert.strictEqual((await insert(solution, { starterFiles: MULTILINE_STARTER })).length, 1);
});

test('a long paste reaches the instructor whole', async () => {
    const longPaste = 'int value = 1;\n'.repeat(400);
    stubState.clipboard = longPaste;
    const [flag] = await insert(longPaste);
    assert.strictEqual(flag.payload.snippet, longPaste);
});

const SOLUTION = 'class Main {\n'
    + Array.from({ length: 12 }, (_, i) => `    int value${i} = compute(${i});`).join('\n')
    + '\n}\n';

/** A provider whose telemetry is captured instead of sent. */
function recording(state = memento()) {
    const p = provider(state);
    const sent: any[] = [];
    p._sessionId = 1;
    p._lastSessionData = { laboratory: { starter_files: LAB1 } };
    p.sendTelemetry = async (type: string, payload: any) => { sent.push({ type, payload }); };
    return { p, sent };
}

async function pasteWithChat(paste: string, chat: string) {
    const { p, sent } = recording();
    p.recordActivity = () => undefined;
    p._recentChatSnippets = [chat];
    stubState.clipboard = paste;
    p.handleDocumentChange({
        document: { uri: { scheme: 'file', fsPath: '/workspace/Main.java' }, fileName: '/workspace/Main.java', isDirty: true },
        contentChanges: [{ text: paste }],
        reason: undefined,
    });
    await new Promise(r => setTimeout(r, 10));
    return sent.filter(s => s.type === 'paste_anomaly');
}

test('one chat line does not excuse a whole paste that contains it', async () => {
    assert.strictEqual((await pasteWithChat(SOLUTION, '    int value3 = compute(3);'.trim())).length, 1);
});

test('pasting a teammate\'s snippet from chat is not flagged', async () => {
    assert.strictEqual((await pasteWithChat(SOLUTION, SOLUTION.trim())).length, 0);
});

test('keeping files that differ from the starter code tells the instructor', async () => {
    const { p, sent } = recording();
    files.set('/workspace/Main.java', SOLUTION);
    stubState.warningAnswer = 'Keep my files';

    await p.provisionStarterFiles(LAB1);

    assert.strictEqual(files.get('/workspace/Main.java'), SOLUTION);
    const [kept] = sent.filter(s => s.type === 'starter_files_kept');
    assert.deepStrictEqual(kept.payload.files, ['Main.java']);
    assert.ok(kept.payload.changed_lines >= 12);
});

test('closing the dialog keeps the files too, so it also tells the instructor', async () => {
    const { p, sent } = recording();
    files.set('/workspace/Main.java', SOLUTION);
    stubState.warningAnswer = undefined;

    await p.provisionStarterFiles(LAB1);

    assert.strictEqual(sent.filter(s => s.type === 'starter_files_kept').length, 1);
});

test('keeping a file with only a small difference is not reported', async () => {
    const { p, sent } = recording();
    files.set('/workspace/Main.java', 'class Main { /* lab 1, edited */ }');
    stubState.warningAnswer = 'Keep my files';

    await p.provisionStarterFiles(LAB1);

    assert.strictEqual(sent.filter(s => s.type === 'starter_files_kept').length, 0);
});

/** A provider that has provisioned the lab, so it knows what is on disk. */
async function provisioned() {
    const recorded = recording();
    await recorded.p.provisionStarterFiles(LAB1);
    return recorded;
}

test('a lab file replaced on disk is reported', async () => {
    const { p, sent } = await provisioned();

    files.set('/workspace/Main.java', SOLUTION);
    await p.reportExternalFileChanges();

    const [change] = sent.filter(s => s.type === 'external_file_change');
    assert.strictEqual(change.payload.file, 'Main.java');
    assert.ok(change.payload.changed_lines >= 12);
});

test('the same outside change is reported once', async () => {
    const { p, sent } = await provisioned();

    files.set('/workspace/Main.java', SOLUTION);
    await p.reportExternalFileChanges();
    await p.reportExternalFileChanges();

    assert.strictEqual(sent.filter(s => s.type === 'external_file_change').length, 1);
});

test('saving from the editor is not an outside change', async () => {
    const { p, sent } = await provisioned();

    files.set('/workspace/Main.java', SOLUTION);
    p.rememberSavedFile({ fileName: '/workspace/Main.java', getText: () => SOLUTION });
    await p.reportExternalFileChanges();

    assert.strictEqual(sent.filter(s => s.type === 'external_file_change').length, 0);
});

test('saving a different file with a similar name leaves the lab file alone', async () => {
    const { p } = await provisioned();

    p.rememberSavedFile({ fileName: '/workspace/NotMain.java', getText: () => SOLUTION });

    assert.strictEqual(p._starterDiskTexts.get('Main.java'), LAB1[0].content);
});

test('a small outside change is not reported', async () => {
    const { p, sent } = await provisioned();

    files.set('/workspace/Main.java', 'class Main { /* lab 1, edited */ }');
    await p.reportExternalFileChanges();

    assert.strictEqual(sent.filter(s => s.type === 'external_file_change').length, 0);
});

test('submitting sends pending typing figures and the latest diff first', async () => {
    const { p, sent } = await provisioned();
    const order: string[] = [];
    p.computeAndSyncDiffs = async () => { order.push('diff'); };
    p._totalKeystrokes = 250;
    p._currentWpm = 30;
    p._wpmSyncTimer = setTimeout(() => assert.fail('the pending sync should have been cancelled'), 5);

    await p.syncActivityBeforeSubmit();
    await new Promise(r => setTimeout(r, 10));

    assert.deepStrictEqual(order, ['diff']);
    const [typing] = sent.filter(s => s.type === 'wpm_update');
    assert.strictEqual(typing.payload.keystroke_count, 250);
});

test('a reload carries on from the server\'s keystroke count', async () => {
    const p = provider();
    p._sessionId = 1;
    p._filesGenerated = true;
    p.warnIfOutdated = () => undefined;
    p.makeRequest = async () => ({ status: 200, body: JSON.stringify({ keystroke_count: 800 }) });

    await p.syncSessionState();
    assert.strictEqual(p._totalKeystrokes, 800);

    p._totalKeystrokes = 900;
    await p.syncSessionState();
    assert.strictEqual(p._totalKeystrokes, 900);
});

/** A window with no folder open, where opened documents live in `docs` by URI. */
function withNoFolder() {
    const docs = new Map<string, { uri: any; text: string; getText(): string }>();
    const opened: any[] = [];
    const { workspace, window } = vscodeStub;
    const saved = {
        workspaceFolders: workspace.workspaceFolders,
        openTextDocument: workspace.openTextDocument,
        applyEdit: workspace.applyEdit,
        showTextDocument: window.showTextDocument,
    };
    workspace.workspaceFolders = undefined;
    workspace.openTextDocument = async (uri: any) => {
        const key = uri.toString();
        if (!docs.has(key)) {
            docs.set(key, { uri, text: '', getText() { return this.text; } });
        }
        return docs.get(key);
    };
    workspace.applyEdit = async (edit: any) => {
        for (const { uri, text } of edit.inserts) {
            docs.get(uri.toString())!.text += text;
        }
        return true;
    };
    window.showTextDocument = async (doc: any) => { opened.push(doc); };
    return {
        docs,
        opened,
        restore() {
            workspace.workspaceFolders = saved.workspaceFolders;
            workspace.openTextDocument = saved.openTextDocument;
            workspace.applyEdit = saved.applyEdit;
            window.showTextDocument = saved.showTextDocument;
        },
    };
}

const STRINGS = [{ name: 'src/Strings.java', content: 'public class Strings {}\n', is_primary: true }];

test('with no folder open the starter file opens under its own name, not as Untitled-1', async () => {
    const editor = withNoFolder();
    try {
        const p = provider();
        p._sessionId = 1;
        await p.provisionStarterFiles(STRINGS);

        const [doc] = editor.opened;
        assert.strictEqual(doc.uri.scheme, 'untitled');
        assert.strictEqual(doc.uri.fsPath, 'Strings.java');
        assert.strictEqual(doc.getText(), STRINGS[0].content);
    } finally {
        editor.restore();
    }
});

test('with no folder open the session sync does not reopen the starter file', async () => {
    const editor = withNoFolder();
    try {
        const p = provider();
        p._sessionId = 1;
        await p.provisionStarterFiles(STRINGS);

        assert.strictEqual(p._filesGenerated, true);
    } finally {
        editor.restore();
    }
});

test('opening the starter file again keeps what the student wrote', async () => {
    const editor = withNoFolder();
    try {
        const p = provider();
        p._sessionId = 1;
        await p.provisionStarterFiles(STRINGS);
        editor.opened[0].text = 'public class Strings { int myWork; }';

        await p.openUnsavedStarterFile(STRINGS[0]);

        assert.strictEqual(editor.docs.size, 1);
        assert.strictEqual(editor.opened[1].getText(), 'public class Strings { int myWork; }');
    } finally {
        editor.restore();
    }
});
