import { directories, fakeSecrets, files, fileTimes, memento, shown, stubState, vscodeStub, watcher } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
import { isLabFolderFor, labFolderName, labIdOf, relativePathInside } from '../labFolder';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, beforeEach, afterEach } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const FOLDER = '/workspace/java-oop-basics-lab12';
const MAIN = { name: 'Main.java', content: 'class Main { /* lab 12 */ }', is_primary: true };
const LAB = { id: 12, title: 'Java OOP Basics', starter_files: [MAIN] };

const realWorkspace = { ...vscodeStub.workspace };
const realWindow = { ...vscodeStub.window };
let opened: string[] = [];
let integrity: any[] = [];

function provider(state = memento(), lab: any = LAB, sessionId = 1) {
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, state as any, fakeSecrets());
    p._sessionId = sessionId;
    p._lastSessionData = { laboratory: lab };
    p.computeAndSyncDiffs = async () => undefined;
    p._view = { webview: { postMessage: (message: any) => { if (message.type === 'integrityStatus') { integrity.push(message); } } } };
    return p;
}

/** What an older build left behind: the lab's files straight in the workspace, owned by `sessionId`. */
async function olderBuildWroteAtRoot(state: any, sessionId: number, content: string) {
    files.set('/workspace/Main.java', content);
    await state.update('certicode.starterFileOwners:file:///workspace', { 'Main.java': sessionId });
}

beforeEach(() => {
    files.clear();
    fileTimes.clear();
    directories.clear();
    shown.length = 0;
    stubState.warningAnswer = undefined;
    opened = [];
    integrity = [];
    vscodeStub.workspace.workspaceFolders = [{ uri: vscodeStub.Uri.file('/workspace') }];
    vscodeStub.workspace.openTextDocument = async (uri: any) => { opened.push(uri.fsPath); return {}; };
});

afterEach(() => {
    Object.assign(vscodeStub.workspace, realWorkspace);
    Object.assign(vscodeStub.window, realWindow);
});

// Naming

test('the folder is named after the lab, with only characters every system accepts', () => {
    assert.strictEqual(labFolderName('Java OOP Basics', 12), 'java-oop-basics-lab12');
    assert.strictEqual(labFolderName('  Lab 3: Inheritance & <Polymorphism>? ', 17), 'lab-3-inheritance-polymorphism-lab17');
    assert.strictEqual(labFolderName('Pagbasa ng Señales', 4), 'pagbasa-ng-senales-lab4');
    assert.strictEqual(labFolderName('CON', 5), 'con-lab5');
    assert.strictEqual(labFolderName('C:\\..\\..\\etc/passwd', 6), 'c-etc-passwd-lab6');
});

test('a title with nothing usable still gives the lab a folder', () => {
    assert.strictEqual(labFolderName('', 7), 'lab7');
    assert.strictEqual(labFolderName(undefined, 7), 'lab7');
    assert.strictEqual(labFolderName('日本語 🙂', 7), 'lab7');
});

test('a long title is cut without leaving a dash at the end', () => {
    const name = labFolderName('An extremely long laboratory title that goes on and on and on', 9);
    assert.ok(name.length <= 40 + '-lab9'.length, name);
    assert.ok(/^[a-z0-9]+(-[a-z0-9]+)*-lab9$/.test(name), name);
});

test('the folder is recognised by the lab id alone, so a renamed lab keeps its folder', () => {
    assert.ok(isLabFolderFor('java-oop-basics-lab12', 12));
    assert.ok(isLabFolderFor('renamed-later-lab12', 12));
    assert.ok(isLabFolderFor('lab12', 12));
    assert.ok(isLabFolderFor('Java-OOP-LAB12', 12));
    assert.ok(!isLabFolderFor('java-oop-basics-lab12', 2));
    assert.ok(!isLabFolderFor('java-oop-basics-lab2', 12));
    assert.ok(!isLabFolderFor('mylab12', 12));
});

test('only a positive whole lab id names a folder', () => {
    assert.strictEqual(labIdOf({ id: 12 }), 12);
    assert.strictEqual(labIdOf({ id: '12' }), 12);
    assert.strictEqual(labIdOf({ id: 0 }), undefined);
    assert.strictEqual(labIdOf({ id: '../x' }), undefined);
    assert.strictEqual(labIdOf(null), undefined);
});

test('paths inside a folder are found on every system', () => {
    assert.strictEqual(relativePathInside('/workspace/lab12', '/workspace/lab12/src/Main.java', false), 'src/Main.java');
    assert.strictEqual(relativePathInside('c:\\Users\\me\\lab12', 'C:\\Users\\Me\\LAB12\\Main.java', true), 'Main.java');
    assert.strictEqual(relativePathInside('/workspace/lab12', '/workspace/Lab12/Main.java', false), undefined);
    assert.strictEqual(relativePathInside('/workspace/lab12', '/workspace/lab123/Main.java', false), undefined);
    assert.strictEqual(relativePathInside('/workspace/lab12', '/workspace/lab12', false), undefined);
});

// Provisioning and resume

test('a lab\'s files are written in its own folder, which is made first, and the primary file opens from there', async () => {
    const p = provider();
    await p.provisionStarterFiles(LAB.starter_files);

    assert.ok(directories.has(FOLDER));
    assert.strictEqual(files.get(`${FOLDER}/Main.java`), MAIN.content);
    assert.ok(!files.has('/workspace/Main.java'));
    assert.deepStrictEqual(opened, [`${FOLDER}/Main.java`]);
    assert.strictEqual(p._starterFileSnapshots.get('Main.java'), MAIN.content);
});

test('a resume finds the lab\'s folder again, even after the lab was renamed, and keeps the work', async () => {
    const state = memento();
    await provider(state).provisionStarterFiles(LAB.starter_files);
    files.set(`${FOLDER}/Main.java`, 'class Main { int myWork; }');

    const renamed = { ...LAB, title: 'Object-Oriented Java' };
    const again = provider(state, renamed);
    await again.provisionStarterFiles(renamed.starter_files);

    assert.strictEqual(files.get(`${FOLDER}/Main.java`), 'class Main { int myWork; }');
    assert.ok(![...files.keys()].some(path => path.startsWith('/workspace/object-oriented-java')));
    assert.ok(!files.has('/workspace/Main.java'));
    assert.deepStrictEqual(opened, [`${FOLDER}/Main.java`, `${FOLDER}/Main.java`]);
    assert.ok(!shown.some(s => s.message.includes('another lab')));
});

test('two labs with the same file names each get their own folder, with no question asked', async () => {
    const state = memento();
    await provider(state).provisionStarterFiles(LAB.starter_files);
    files.set(`${FOLDER}/Main.java`, 'class Main { int lab12Work; }');

    const other = { id: 13, title: 'Recursion', starter_files: [{ name: 'Main.java', content: 'class Main { /* lab 13 */ }', is_primary: true }] };
    await provider(state, other, 2).provisionStarterFiles(other.starter_files);

    assert.strictEqual(files.get('/workspace/recursion-lab13/Main.java'), 'class Main { /* lab 13 */ }');
    assert.strictEqual(files.get(`${FOLDER}/Main.java`), 'class Main { int lab12Work; }');
    assert.ok(!shown.some(s => s.level === 'warning'));
});

test('a new attempt of the same lab still offers to replace the files, backing them up in the lab folder', async () => {
    const state = memento();
    await provider(state).provisionStarterFiles(LAB.starter_files);
    files.set(`${FOLDER}/Main.java`, 'class Main { int firstAttempt; }');

    stubState.warningAnswer = 'Replace files';
    await provider(state, LAB, 2).provisionStarterFiles(LAB.starter_files);

    const warning = shown.find(s => s.level === 'warning');
    assert.ok(warning && warning.message.includes('java-oop-basics-lab12/.certicode/previous'), warning?.message);
    assert.strictEqual(files.get(`${FOLDER}/Main.java`), MAIN.content);
    const backups = [...files.entries()].filter(([path]) => path.startsWith(`${FOLDER}/.certicode/previous/`));
    assert.deepStrictEqual(backups.map(([, content]) => content), ['class Main { int firstAttempt; }']);
});

test('the lab folder opened as the workspace itself gets the files directly, not a folder inside it', async () => {
    vscodeStub.workspace.workspaceFolders = [{ uri: vscodeStub.Uri.file(FOLDER) }];
    await provider().provisionStarterFiles(LAB.starter_files);

    assert.strictEqual(files.get(`${FOLDER}/Main.java`), MAIN.content);
    assert.ok(![...files.keys()].some(path => path.startsWith(`${FOLDER}/java-oop-basics-lab12/`)));
});

test('without a lab id from the server, files go straight into the workspace as before', async () => {
    await provider(memento(), { starter_files: [MAIN] }).provisionStarterFiles([MAIN]);

    assert.strictEqual(files.get('/workspace/Main.java'), MAIN.content);
    assert.strictEqual(directories.size, 0);
});

// A lab in progress from an older build

test('a lab in progress from an older build keeps its files in the workspace, and resume finds the work', async () => {
    const state = memento();
    await olderBuildWroteAtRoot(state, 1, 'class Main { int workSoFar; }');

    const p = provider(state);
    await p.provisionStarterFiles(LAB.starter_files);

    assert.strictEqual(files.get('/workspace/Main.java'), 'class Main { int workSoFar; }');
    assert.ok(![...files.keys()].some(path => path.startsWith(FOLDER)));
    assert.ok(!directories.has(FOLDER));
    assert.ok(!shown.some(s => s.level === 'warning'));
    assert.deepStrictEqual(opened, ['/workspace/Main.java']);

    const submission = await p.collectSubmissionFiles();
    assert.strictEqual(submission.primaryCode, 'class Main { int workSoFar; }');
});

test('files an older build left for another session stay put, and the new lab gets its own folder', async () => {
    const state = memento();
    await olderBuildWroteAtRoot(state, 99, 'class Main { int oldLab; }');

    await provider(state).provisionStarterFiles(LAB.starter_files);

    assert.strictEqual(files.get('/workspace/Main.java'), 'class Main { int oldLab; }');
    assert.strictEqual(files.get(`${FOLDER}/Main.java`), MAIN.content);
    assert.ok(!shown.some(s => s.level === 'warning'));
});

test('an older build\'s session whose files were deleted starts fresh in a lab folder', async () => {
    const state = memento();
    await olderBuildWroteAtRoot(state, 1, '');
    files.delete('/workspace/Main.java');

    await provider(state).provisionStarterFiles(LAB.starter_files);

    assert.strictEqual(files.get(`${FOLDER}/Main.java`), MAIN.content);
});

// Everything else that reads the lab's files

test('the watcher checks for the lab\'s files in its folder, so a same-named file elsewhere doesn\'t count', async () => {
    const p = provider();
    p.scheduleDiskCheck = () => undefined;
    p.setupWorkspaceWatchers();
    await p.provisionStarterFiles(LAB.starter_files);
    files.set('/workspace/Main.java', 'class Main { /* some other lab */ }');
    integrity = [];

    files.delete(`${FOLDER}/Main.java`);
    watcher.onDidDelete!(vscodeStub.Uri.file(`${FOLDER}/Main.java`));
    await new Promise(r => setTimeout(r, 10));
    assert.deepStrictEqual(integrity.pop(), { type: 'integrityStatus', valid: false, missing: ['Main.java'] });

    files.set(`${FOLDER}/Main.java`, MAIN.content);
    watcher.onDidCreate!(vscodeStub.Uri.file(`${FOLDER}/Main.java`));
    await new Promise(r => setTimeout(r, 10));
    assert.deepStrictEqual(integrity.pop(), { type: 'integrityStatus', valid: true, missing: [] });
});

test('only files in this lab\'s folder count as its lab files for the paste and diff checks', async () => {
    const p = provider();
    await p.provisionStarterFiles(LAB.starter_files);

    assert.strictEqual(p.starterFileNameFor(`${FOLDER}/Main.java`), 'Main.java');
    assert.strictEqual(p.starterFileNameFor('/workspace/recursion-lab13/Main.java'), undefined);
    assert.strictEqual(p.starterFileNameFor('/workspace/Main.java'), undefined);
    assert.strictEqual(p.starterFileNameFor(`${FOLDER}/src/Main.java`), undefined);
});

test('the diff compares the file in the lab folder with the starter code', async () => {
    const p = provider();
    await p.provisionStarterFiles(LAB.starter_files);
    files.set(`${FOLDER}/Main.java`, 'class Main { /* lab 12 */ }\nint added;\n');
    files.set('/workspace/Main.java', 'unrelated\nlines\nhere\n');
    const posted: any[] = [];
    p.makeRequest = async (_method: string, path: string, body: any) => { posted.push({ path, body }); return { status: 200, body: '{}' }; };

    await (SidebarProvider.prototype as any).computeAndSyncDiffs.call(p);

    assert.deepStrictEqual(posted[0].body.files, [{ name: 'Main.java', added: 1, deleted: 0, modified: 0 }]);
});

test('a submit sends the lab folder\'s files under their names in the folder, and nothing from outside it', async () => {
    const p = provider();
    p._attemptStartLocalMs = 1_000;
    await p.provisionStarterFiles(LAB.starter_files);
    files.set(`${FOLDER}/src/Helper.java`, 'class Helper {}');
    fileTimes.set(`${FOLDER}/src/Helper.java`, { ctime: 2_000, mtime: 2_000 });
    files.set('/workspace/recursion-lab13/Other.java', 'class Other {}');
    fileTimes.set('/workspace/recursion-lab13/Other.java', { ctime: 2_000, mtime: 2_000 });

    const result = await p.collectSubmissionFiles();

    assert.deepStrictEqual(result.filesPayload.map((f: any) => f.name), ['Main.java', 'src/Helper.java']);
    assert.deepStrictEqual(result.fileUris.map((u: any) => u.fsPath), [`${FOLDER}/Main.java`]);
});

test('read-only starter files are marked by their path in the lab folder', async () => {
    const updates: any[] = [];
    vscodeStub.workspace.getConfiguration = () => ({ get: () => ({}), update: async (key: string, value: any) => { updates.push({ key, value }); } });
    const data = { name: 'Data.java', content: 'class Data {}', is_readonly: true };

    await provider(memento(), { ...LAB, starter_files: [MAIN, data] }).provisionStarterFiles([MAIN, data]);

    assert.deepStrictEqual(updates, [{ key: 'readonlyInclude', value: { 'java-oop-basics-lab12/Data.java': true } }]);
});

test('shared typing shares the files inside the lab folder', async () => {
    const p = provider();
    await p.provisionStarterFiles(LAB.starter_files);
    let root: any;
    p.buildTeamSync = (_config: any, _session: number, folder: any) => {
        root = folder;
        const sync: any = { start: async () => undefined, stop: () => new Map(), onBackup: () => undefined, onUndoOthers: () => undefined };
        return { sync, host: { nameOf: () => undefined } };
    };
    p.makeRequest = async () => ({ status: 200, body: JSON.stringify({ enabled: true }) });
    p.rememberTeamSyncClients = async () => undefined;

    await p.updateTeamSync({ status: 'in_progress', team_sync: { enabled: true }, team: { id: 1 }, laboratory: LAB });

    assert.strictEqual(root.fsPath, FOLDER);
});

test('opening a starter file from the sidebar opens the one in the lab folder', async () => {
    const p = provider();
    await p.provisionStarterFiles(LAB.starter_files);
    opened = [];
    let handler: any;
    p._getHtmlForWebview = () => '';
    p.setupWorkspaceWatchers = () => undefined;
    p.startMonitoring = () => undefined;
    p.resolveWebviewView({ webview: { options: {}, onDidReceiveMessage: (fn: any) => { handler = fn; }, postMessage: () => undefined }, onDidChangeVisibility: () => undefined }, {}, {});

    await handler({ type: 'openFile', name: 'Main.java' });

    assert.deepStrictEqual(opened, [`${FOLDER}/Main.java`]);
});
