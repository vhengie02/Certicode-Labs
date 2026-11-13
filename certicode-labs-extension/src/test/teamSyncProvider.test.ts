import { fakeSecrets, stubState } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const BIG_BLOCK = 'public static int sum(int[] values) {\n    int total = 0;\n    for (int v : values) { total += v; }\n    return total;\n}\n'.repeat(3);

function memento() {
    const values = new Map<string, any>();
    return { get: (key: string, fallback?: any) => (values.has(key) ? values.get(key) : fallback), update: async (key: string, value: any) => { values.set(key, value); }, values };
}

/** A shared-typing stand-in: says whether an event was a teammate's, and records the rest. */
function fakeSync(options: { remote?: boolean; stats?: any[] } = {}) {
    const calls = { handled: 0, flushed: 0, stopped: 0 };
    return {
        calls,
        handleChange: () => { calls.handled++; return options.remote ?? true; },
        noteSaved: () => undefined,
        lineStats: () => options.stats ?? [],
        linesDeletedElsewhere: () => 0,
        clientIds: () => [123],
        flush: async () => { calls.flushed++; },
        stop: () => { calls.stopped++; return new Map<string, string>(); },
    };
}

function provider() {
    const state = memento();
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any, fakeSecrets());
    const posted: any[] = [];
    const requests: { method: string; path: string; body?: any }[] = [];
    const sent: any[] = [];
    p._sessionId = 7;
    p._lastSessionData = { is_group_lab: true, laboratory: { starter_files: [{ name: 'Main.java', content: '' }] } };
    p._starterFileSnapshots = new Map([['Main.java', '']]);
    p._view = { webview: { postMessage: (message: any) => { posted.push(message); } } };
    p.recordActivity = () => undefined;
    p.sendTelemetry = async (type: string, payload: any) => { sent.push({ type, payload }); };
    p.matchesClipboard = async () => true;
    p.makeRequest = async (method: string, path: string, body?: any) => {
        requests.push({ method, path, body });
        return { status: 200, body: JSON.stringify({ enabled: false }) };
    };
    return { p, posted, requests, sent, state };
}

function sharedDocument(text = '') {
    const doc: any = {
        uri: { scheme: 'file', fsPath: '/workspace/Main.java', path: '/workspace/Main.java', toString: () => 'file:///workspace/Main.java' },
        fileName: '/workspace/Main.java',
        isDirty: true,
        text,
        getText: () => doc.text,
    };
    return doc;
}

function change(p: any, doc: any, rangeOffset: number, rangeLength: number, text: string) {
    doc.text = doc.text.slice(0, rangeOffset) + text + doc.text.slice(rangeOffset + rangeLength);
    p.handleDocumentChange({ document: doc, contentChanges: [{ text, rangeOffset, rangeLength }], reason: undefined });
}

function typingTogether(p: any, sync: ReturnType<typeof fakeSync>) {
    p._teamSync = sync;
    p._teamSyncHost = { nameOf: () => 'Main.java' };
    p._teamSyncKey = '7:1:0';
    p._teamSyncReady = true;
}

test('a teammate\'s edit is not counted as typing, a paste or a change of the student\'s', async () => {
    const { p, sent } = provider();
    const sync = fakeSync({ remote: true });
    typingTogether(p, sync);
    let diffsComputed = 0;
    p.computeAndSyncDiffs = async () => { diffsComputed++; };

    change(p, sharedDocument(), 0, 0, BIG_BLOCK);
    change(p, sharedDocument(BIG_BLOCK), 0, 0, 'x');
    await new Promise(resolve => setTimeout(resolve, 2100));

    assert.strictEqual(sync.calls.handled, 2);
    assert.strictEqual(p._totalKeystrokes, 0);
    assert.deepStrictEqual(sent.filter(s => s.type === 'paste_anomaly'), []);
    assert.strictEqual(p._insertRun, undefined);
    assert.strictEqual(diffsComputed, 0);
});

test('the student\'s own typing is still counted while typing together', async () => {
    const { p, sent } = provider();
    typingTogether(p, fakeSync({ remote: false }));
    p.computeAndSyncDiffs = async () => undefined;

    const doc = sharedDocument();
    change(p, doc, 0, 0, 'a');
    change(p, doc, 1, 0, 'b');
    change(p, doc, 2, 0, BIG_BLOCK);
    await new Promise(resolve => setTimeout(resolve, 20));

    assert.strictEqual(p._totalKeystrokes, 2);
    assert.strictEqual(sent.filter(s => s.type === 'paste_anomaly').length, 1);
    if (p._diffDebounceTimer) { clearTimeout(p._diffDebounceTimer); }
});

test('submit brings in the team\'s latest edits before collecting files', async () => {
    const { p, requests } = provider();
    const order: string[] = [];
    const sync = fakeSync();
    sync.flush = async () => { await new Promise(resolve => setTimeout(resolve, 30)); order.push('flushed'); };
    typingTogether(p, sync);
    p._lastSessionData.team = { can_submit: true };
    p.syncActivityBeforeSubmit = async () => undefined;
    p.stopSessionTimers = () => undefined;
    p.collectDiagnostics = () => [];
    p.collectSubmissionFiles = async () => {
        order.push('collected');
        return { primaryCode: 'class Main {}', primaryFileName: 'Main.java', filesPayload: [{ name: 'Main.java', content: 'class Main {}', is_primary: true }], fileUris: [] };
    };
    p.makeRequest = async (method: string, path: string) => {
        requests.push({ method, path });
        return { status: 200, body: JSON.stringify({ performance_score: 80, completed_tasks: [], evaluation: {} }) };
    };
    stubState.warningAnswer = 'Yes, Submit';

    await p.handleSubmit();

    assert.deepStrictEqual(order, ['flushed', 'collected']);
    assert.ok(requests.some(r => r.path.endsWith('/sessions/7/submit')));
});

test('line counts come from who typed them while typing together', async () => {
    const { p, requests, posted } = provider();
    typingTogether(p, fakeSync({ stats: [{ name: 'Main.java', added: 2, modified: 1, deleted: 3 }] }));
    // The file on disk holds every teammate's work: far more than this student wrote
    p.readWorkspaceText = async () => 'line\n'.repeat(40);

    await p.computeAndSyncDiffs();

    const diff = requests.find(r => r.path.endsWith('/diff'))!.body;
    assert.deepStrictEqual({ added: diff.lines_added, modified: diff.lines_modified, deleted: diff.lines_deleted }, { added: 2, modified: 1, deleted: 3 });
    assert.deepStrictEqual(diff.files, [{ name: 'Main.java', added: 2, deleted: 3, modified: 1 }]);
    assert.ok(posted.some(m => m.type === 'diffUpdate'));
});

test('shared typing does not start on a solo lab', async () => {
    const { p, requests, posted } = provider();

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: false, team_sync: { enabled: false }, team: null });

    assert.strictEqual(requests.length, 0);
    assert.strictEqual(p._teamSync, undefined);
    assert.ok(!posted.some(m => m.type === 'teamSync'));
});

test('the sidebar is told, with the reason, when shared typing is off on a group lab', async () => {
    const { p, requests, posted } = provider();

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: false }, team: { id: 1 } });

    assert.strictEqual(requests.length, 0);
    assert.deepStrictEqual(posted.filter(m => m.type === 'teamSync'), [{ type: 'teamSync', state: 'off', reason: 'your instructor has not set it up' }]);

    // Read again a few seconds later: nothing new to say
    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: false }, team: { id: 1 } });
    assert.strictEqual(posted.filter(m => m.type === 'teamSync').length, 1);
});

test('shared typing stays off when the server says so for this attempt', async () => {
    const { p, requests, posted } = provider();

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: true }, team: { id: 1 } });

    assert.deepStrictEqual(requests.map(r => r.path), ['/api/v1/sessions/7/team-doc']);
    assert.strictEqual(p._teamSync, undefined);
    assert.strictEqual(posted.filter(m => m.type === 'teamSync').pop().state, 'off');
});

test('shared typing stops when the attempt closes', async () => {
    const { p } = provider();
    const sync = fakeSync();
    typingTogether(p, sync);

    await p.updateTeamSync({ status: 'completed', is_group_lab: true, team_sync: { enabled: false }, team: { id: 1 } });

    assert.strictEqual(sync.calls.stopped, 1);
    assert.strictEqual(p._teamSync, undefined);
    assert.strictEqual(p._teamSyncKey, undefined);
});

test('shared typing restarts for the new team when the student is moved', async () => {
    const { p, requests } = provider();
    const sync = fakeSync();
    typingTogether(p, sync);

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: true }, team: { id: 2 } });

    assert.strictEqual(sync.calls.stopped, 1);
    assert.deepStrictEqual(requests.map(r => r.path), ['/api/v1/sessions/7/team-doc']);
});

test('the same team attempt does not restart on every session read', async () => {
    const { p, requests } = provider();
    const sync = fakeSync();
    typingTogether(p, sync);

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: true }, team: { id: 1 } });

    assert.strictEqual(sync.calls.stopped, 0);
    assert.strictEqual(requests.length, 0);
});

test('stopping the session stops shared typing', () => {
    const { p } = provider();
    const sync = fakeSync();
    typingTogether(p, sync);

    p.stopSessionTimers();

    assert.strictEqual(sync.calls.stopped, 1);
    assert.strictEqual(p._teamSync, undefined);
});

test('the sidebar script shows the shared typing state as plain text', () => {
    const { p } = provider();
    const html: string = p._getHtmlForWebview({ cspSource: 'test', asWebviewUri: (uri: unknown) => uri });
    const script = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]).join('\n');

    new Function(script);
    assert.ok(html.includes('id="team-sync-status"'));
    assert.ok(script.includes("case 'teamSync'"));
    assert.ok(/syncStatus\.textContent = /.test(script));
    assert.ok(!/syncStatus\.innerHTML/.test(script));
});

test('a start that is overtaken while it waits never runs alongside the newer one', async () => {
    const { p, requests } = provider();
    const built: any[] = [];
    p.buildTeamSync = () => {
        const sync: any = { ...fakeSync(), started: false, start: async () => { sync.started = true; }, onBackup: () => undefined, onUndoOthers: () => undefined };
        sync.stop = () => { sync.stopped = true; return new Map(); };
        built.push(sync);
        return { sync, host: { nameOf: () => 'Main.java' } };
    };
    (globalThis as any).__workspace = undefined;
    const answers: ((value: any) => void)[] = [];
    p.makeRequest = (method: string, path: string) => new Promise(resolve => {
        requests.push({ method, path });
        answers.push(resolve);
    });
    const { vscodeStub } = require('./vscodeStub');
    vscodeStub.workspace.workspaceFolders = [{ uri: vscodeStub.Uri.file('/workspace') }];
    const wanted = { status: 'in_progress', is_group_lab: true, team_sync: { enabled: true, rotation: 0 }, team: { id: 1 }, laboratory: { starter_files: [] } };
    const enabled = { status: 200, body: JSON.stringify({ enabled: true, key: Buffer.alloc(32).toString('base64'), topic: 't', supabase_url: 'https://x', publishable_key: 'k', min_send_interval_ms: 400 }) };

    const first = p.updateTeamSync(wanted);                          // waits for the server
    await p.updateTeamSync({ ...wanted, status: 'completed' });       // a read in between says stop
    const second = p.updateTeamSync(wanted);                          // and the next says start again
    answers[1](enabled);
    await second;
    answers[0](enabled);                                              // the first start's answer comes late
    await first;

    assert.strictEqual(built.length, 1);
    assert.strictEqual(p._teamSync, built[0]);
    assert.ok(!built[0].stopped);
});

test('line counts are not posted while shared typing is still starting', async () => {
    const { p, requests } = provider();
    p._lastSessionData = { ...p._lastSessionData, status: 'in_progress', team_sync: { enabled: true } };
    p._teamSyncReady = false;
    p.readWorkspaceText = async () => 'line\n'.repeat(40);

    await p.computeAndSyncDiffs();

    assert.ok(!requests.some(r => r.path.endsWith('/diff')));
});

test('a teammate\'s edit that lands just after shared typing stopped is not counted as typing', async () => {
    const { p, sent } = provider();
    const sync: any = fakeSync({ remote: false });
    sync.stop = () => new Map([['Main.java', BIG_BLOCK]]);
    typingTogether(p, sync);
    p.computeAndSyncDiffs = async () => undefined;

    p.stopTeamSync();
    change(p, sharedDocument(), 0, 0, BIG_BLOCK);
    await new Promise(resolve => setTimeout(resolve, 20));

    assert.strictEqual(p._totalKeystrokes, 0);
    assert.deepStrictEqual(sent.filter(s => s.type === 'paste_anomaly'), []);

    // The student's own typing afterwards is theirs again
    change(p, sharedDocument(BIG_BLOCK), 0, 0, 'a');
    assert.strictEqual(p._totalKeystrokes, 1);
    if (p._diffDebounceTimer) { clearTimeout(p._diffDebounceTimer); }
});

test('an undo in a shared file is reported to shared typing as an undo', () => {
    const { p } = provider();
    const calls: any[] = [];
    const sync: any = fakeSync({ remote: false });
    sync.handleChange = (...args: any[]) => { calls.push(args); return false; };
    typingTogether(p, sync);
    p.computeAndSyncDiffs = async () => undefined;

    const doc = sharedDocument('abc');
    doc.text = 'ab';
    p.handleDocumentChange({ document: doc, contentChanges: [{ text: '', rangeOffset: 2, rangeLength: 1 }], reason: 1 });

    assert.strictEqual(calls[0][2], true);
    if (p._diffDebounceTimer) { clearTimeout(p._diffDebounceTimer); }
});

test('the student is warned, once in a while, when an undo removed a teammate\'s edit', () => {
    const { p } = provider();
    const { shown } = require('./vscodeStub');
    shown.length = 0;

    p.warnUndoOthers('Main.java');
    p.warnUndoOthers('Main.java');

    const warnings = shown.filter((s: any) => s.level === 'warning');
    assert.strictEqual(warnings.length, 1);
    assert.match(warnings[0].message, /teammate's edit in Main\.java/);
});

test('a renamed file reaches shared typing under its new name', async () => {
    const { p } = provider();
    const renames: any[] = [];
    const sync: any = fakeSync();
    sync.fileRenamed = (...args: any[]) => renames.push(args);
    typingTogether(p, sync);
    p._teamSyncHost = { nameOf: (uri: any) => uri.fsPath.replace('/workspace/', '') };
    const { vscodeStub, files } = require('./vscodeStub');
    files.set('/workspace/App.java', 'class App {}');
    const readFile = vscodeStub.workspace.fs?.readFile;
    vscodeStub.workspace.fs = { ...(vscodeStub.workspace.fs || {}), readFile: async (uri: any) => Buffer.from(files.get(uri.fsPath) ?? '') };

    await p.handleRenamedFiles([{ oldUri: vscodeStub.Uri.file('/workspace/Main.java'), newUri: vscodeStub.Uri.file('/workspace/App.java') }]);

    assert.deepStrictEqual(renames, [['Main.java', 'App.java', 'class App {}']]);
    if (readFile) { vscodeStub.workspace.fs.readFile = readFile; }
});

test('a new channel and key for the team restarts shared typing', async () => {
    const { p, requests } = provider();
    const sync = fakeSync();
    typingTogether(p, sync);
    p._teamSyncKey = '7:1:0';

    await p.updateTeamSync({ status: 'in_progress', is_group_lab: true, team_sync: { enabled: true, rotation: 1 }, team: { id: 1 } });

    assert.strictEqual(sync.calls.stopped, 1);
    assert.deepStrictEqual(requests.map(r => r.path), ['/api/v1/sessions/7/team-doc']);
});
