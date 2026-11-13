// Runs inside a real VS Code (see run.js). One student types in real editors through the real
// shared-typing code (WorkspaceSync on VS Code documents); a teammate types into the same files
// from this process, headless. At the end every copy of every file must be the same, and the
// characters counted as the student's must be exactly the ones the student typed.
//
// Edits travel over a local relay, or over the live Supabase channel when SYNC_CHECK_SUPABASE_URL
// and SYNC_CHECK_SUPABASE_KEY are set. SYNC_CHECK_BREAK=1 counts teammates' edits as the
// student's, to show the check can fail.

const vscode = require('vscode');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const WebSocket = require('ws');

const OUT = path.join(__dirname, '..', '..', 'out', 'teamSync');
const { TeamDoc } = require(path.join(OUT, 'teamDoc.js'));
const { WorkspaceSync } = require(path.join(OUT, 'workspaceSync.js'));
const { VscodeSyncHost } = require(path.join(OUT, 'vscodeHost.js'));
const { SupabaseTransport } = require(path.join(OUT, 'supabaseTransport.js'));

const RESULT = process.env.SYNC_CHECK_RESULT;
const BREAK = process.env.SYNC_CHECK_BREAK === '1';
const LIVE = Boolean(process.env.SYNC_CHECK_SUPABASE_URL && process.env.SYNC_CHECK_SUPABASE_KEY);
const FILES = ['Main.java', 'Util.java'];

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

function random(seed) {
    let state = seed >>> 0;
    return () => {
        state = (state * 1664525 + 1013904223) >>> 0;
        return state / 0x100000000;
    };
}

/** A local stand-in for the Realtime channel: what one client sends, the others receive. */
function startRelay() {
    const server = new WebSocket.Server({ port: 0, host: '127.0.0.1' });
    server.on('connection', socket => {
        socket.on('message', data => {
            for (const other of server.clients) {
                if (other !== socket && other.readyState === WebSocket.OPEN) { other.send(data.toString()); }
            }
        });
    });
    return new Promise(resolve => server.on('listening', () => resolve({ server, url: `ws://127.0.0.1:${server.address().port}` })));
}

class RelayTransport {
    constructor(url, lagMs) { this.url = url; this.lagMs = lagMs; this.messageListeners = []; this.openListeners = []; }
    connect() {
        this.socket = new WebSocket(this.url);
        this.socket.on('open', () => this.openListeners.forEach(listener => listener()));
        this.socket.on('message', data => setTimeout(() => this.messageListeners.forEach(listener => listener(data.toString())), this.lagMs));
    }
    send(message) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) { setTimeout(() => this.socket.send(message), this.lagMs); }
    }
    onMessage(listener) { this.messageListeners.push(listener); }
    onOpen(listener) { this.openListeners.push(listener); }
    close() { if (this.socket) { this.socket.close(); } }
}

/** The server's store of edits, in memory, shared by both teammates. */
function memoryStore() {
    let rows = [];
    let nextId = 1;
    return {
        rows: () => rows,
        async load(after) {
            return { updates: rows.filter(row => row.id > after), lastUpdateId: rows.length ? rows[rows.length - 1].id : 0 };
        },
        async append(payload, options = {}) {
            if (options.seed && rows.length > 0) { return { error: 'already_seeded' }; }
            const row = { id: nextId++, payload };
            rows.push(row);
            if (options.replacesThrough) { rows = rows.filter(other => other.id > options.replacesThrough || other.id === row.id); }
            return { id: row.id };
        },
    };
}

/** The headless teammate's editor for one file: edits land at once. */
class HeadlessEditor {
    constructor(text) { this.text = text; }
    getText() { return this.text; }
    async replace(offset, length, text) { this.apply(offset, length, text); return true; }
    type(offset, length, text) { this.apply(offset, length, text); }
    apply(offset, length, text) {
        this.text = this.text.slice(0, offset) + text + this.text.slice(offset + length);
        if (this.binding) { this.binding.handleEditorChange([{ offset, length, text }]); }
    }
}

function transportFor(relayUrl, topic, lagMs) {
    if (LIVE) {
        return new SupabaseTransport(process.env.SYNC_CHECK_SUPABASE_URL, process.env.SYNC_CHECK_SUPABASE_KEY, topic, globalThis.WebSocket || WebSocket);
    }
    return new RelayTransport(relayUrl, lagMs);
}

async function round(name, folder, options) {
    const relay = LIVE ? undefined : await startRelay();
    const key = crypto.randomBytes(32);
    const topic = 'td-check-' + crypto.randomBytes(12).toString('hex');
    const store = memoryStore();
    const rand = random(options.seed);
    const starter = FILES.map(file => ({ name: file, content: `class ${file.replace('.java', '')} {\r\n}\r\n` }));

    const root = vscode.Uri.file(path.join(folder, name));
    fs.mkdirSync(root.fsPath, { recursive: true });
    for (const file of starter) { fs.writeFileSync(path.join(root.fsPath, file.name), file.content); }

    // The student: the real code, on real VS Code documents
    const host = new VscodeSyncHost(root);
    const studentTransport = transportFor(relay && relay.url, topic, 0);
    const studentDoc = new TeamDoc({ key, transport: studentTransport, store, minSendIntervalMs: options.sendMs, storeIntervalMs: 200 });
    const sync = new WorkspaceSync(host, studentDoc, { autosaveMs: 300 });

    let countedLocal = 0;
    const counted = {};
    const subscription = vscode.workspace.onDidChangeTextDocument(event => {
        const fileName = host.nameOf(event.document.uri);
        if (!fileName || event.contentChanges.length === 0) { return; }
        const changes = event.contentChanges.map(change => ({ offset: change.rangeOffset, length: change.rangeLength, text: change.text }));
        const teammates = sync.handleChange(fileName, changes);
        if (!teammates || BREAK) {
            for (const change of changes) {
                countedLocal += change.text.length;
                const label = JSON.stringify(change.text) + (change.length ? ` replacing ${change.length}` : '');
                counted[label] = (counted[label] || 0) + 1;
            }
        }
    });

    await sync.start(starter);

    // The teammate: headless, through the same team document code
    const mateTransport = transportFor(relay && relay.url, topic, options.lagMs);
    const mateDoc = new TeamDoc({ key, transport: mateTransport, store, minSendIntervalMs: options.sendMs, storeIntervalMs: 200 });
    await mateDoc.start(starter);
    const mate = new Map();
    for (const file of mateDoc.fileNames()) {
        const editor = new HeadlessEditor(mateDoc.text(file));
        editor.binding = mateDoc.bind(file, editor);
        mate.set(file, editor);
    }

    // Let both join the channel
    for (let i = 0; i < 100 && !(studentTransport.subscribed !== false && mateTransport.subscribed !== false); i++) { await sleep(50); }
    await sleep(LIVE ? 2500 : 300);

    const editors = new Map();
    for (const file of FILES) {
        const document = await vscode.workspace.openTextDocument(vscode.Uri.joinPath(root, file));
        editors.set(file, await vscode.window.showTextDocument(document, { preview: false }));
    }

    let typedLocal = 0;
    const student = (async () => {
        for (let i = 0; i < options.rounds; i++) {
            const file = FILES[Math.floor(rand() * FILES.length)];
            const editor = await vscode.window.showTextDocument(editors.get(file).document, { preview: false });
            const length = editor.document.getText().length;
            if (rand() < 0.3 && length > 0) {
                const at = editor.document.positionAt(Math.floor(rand() * length));
                editor.selection = new vscode.Selection(at, at);
            }
            if (rand() < 0.12 && length > 4) {
                await vscode.commands.executeCommand('deleteLeft');
            } else {
                const text = rand() < 0.2 ? 'L\n' : 'L';
                await vscode.commands.executeCommand('type', { text });
                typedLocal += text.length;
            }
            if (options.pauseMs) { await sleep(Math.floor(rand() * options.pauseMs)); }
        }
    })();
    const teammate = (async () => {
        for (let i = 0; i < options.rounds; i++) {
            const editor = mate.get(FILES[Math.floor(rand() * FILES.length)]);
            const length = editor.text.length;
            if (rand() < 0.15 && length > 4) {
                editor.type(Math.floor(rand() * (length - 1)), 1, '');
            } else {
                editor.type(Math.floor(rand() * (length + 1)), 0, rand() < 0.2 ? 'rrrr\n' : 'r');
            }
            await sleep(Math.floor(rand() * Math.max(2, options.pauseMs)));
        }
    })();
    await Promise.all([student, teammate]);

    // Let everything arrive
    const same = () => FILES.every(file => editors.get(file).document.getText() === mate.get(file).text && studentDoc.text(file) === mate.get(file).text);
    const deadline = Date.now() + (LIVE ? 30000 : 15000);
    while (Date.now() < deadline && !same()) { await sleep(100); }
    await sync.flush();
    await sleep(LIVE ? 1500 : 200);

    const files = FILES.map(file => ({
        file,
        editor: editors.get(file).document.getText().length,
        studentShared: (studentDoc.text(file) || '').length,
        teammate: mate.get(file).text.length,
        same: editors.get(file).document.getText() === mate.get(file).text && studentDoc.text(file) === mate.get(file).text,
        crlf: editors.get(file).document.getText().includes('\r'),
    }));

    // A teammate who joins afterwards, alone, loads the same files from the store
    const late = new TeamDoc({ key, transport: { connect() {}, send() {}, onMessage() {}, onOpen() {}, close() {} }, store, minSendIntervalMs: 400 });
    await late.start([]);
    const lateMatches = FILES.every(file => late.text(file) === mate.get(file).text);
    late.stop();

    subscription.dispose();
    sync.stop();
    mateDoc.stop();
    if (relay) { relay.server.close(); }
    await vscode.commands.executeCommand('workbench.action.files.saveAll');
    await vscode.commands.executeCommand('workbench.action.closeAllEditors');

    return {
        name,
        transport: LIVE ? 'supabase' : 'relay',
        converged: files.every(file => file.same),
        lineEndings: files.every(file => !file.crlf),
        attributionExact: countedLocal === typedLocal,
        typedLocal,
        countedLocal,
        counted,
        lateJoinerMatches: lateMatches,
        storedRows: store.rows().length,
        files,
    };
}

exports.run = async () => {
    const folder = process.env.SYNC_CHECK_FOLDER;
    // Pressing Enter copies the line's indentation, so the student would insert more than the check
    // typed. This VS Code has its own settings folder, so changing them here touches nothing else.
    await vscode.workspace.getConfiguration('editor').update('autoIndent', 'none', vscode.ConfigurationTarget.Global);
    await vscode.workspace.getConfiguration('editor').update('autoClosingBrackets', 'never', vscode.ConfigurationTarget.Global);
    const results = [];
    try {
        const rounds = LIVE
            ? [['live-human', { seed: 5, rounds: 120, pauseMs: 150, lagMs: 0, sendMs: 400 }]]
            : [
                ['burst', { seed: 1, rounds: 300, pauseMs: 0, lagMs: 0, sendMs: 50 }],
                ['human', { seed: 2, rounds: 120, pauseMs: 200, lagMs: 60, sendMs: 400 }],
                ['laggy', { seed: 3, rounds: 200, pauseMs: 20, lagMs: 150, sendMs: 400 }],
            ];
        for (const [name, options] of rounds.filter(([n]) => !process.env.SYNC_CHECK_ONLY || n === process.env.SYNC_CHECK_ONLY)) {
            results.push(await round(name, folder, options));
        }
        const ok = results.every(r => r.converged && r.lineEndings && r.attributionExact && r.lateJoinerMatches);
        fs.writeFileSync(RESULT, JSON.stringify({ ok, broken: BREAK, results }, null, 2));
    } catch (error) {
        fs.writeFileSync(RESULT, JSON.stringify({ ok: false, error: String((error && error.stack) || error), results }, null, 2));
    }
};
