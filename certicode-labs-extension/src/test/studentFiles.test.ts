import { fakeSecrets, files, fileTimes, memento, vscodeStub } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, beforeEach } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const STARTED = 1_000_000_000;
const BEFORE = STARTED - 3_600_000;
const DURING = STARTED + 60_000;
const MAIN = { name: 'Main.java', content: 'class Main {}', is_primary: true };

function provider(starterFiles: unknown[] = [MAIN]) {
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, memento() as any, fakeSecrets());
    p._lastSessionData = { laboratory: { starter_files: starterFiles } };
    p._attemptStartLocalMs = STARTED;
    return p;
}

function disk(name: string, content: string, ctime: number, mtime = ctime) {
    files.set(`/workspace/${name}`, content);
    fileTimes.set(`/workspace/${name}`, { ctime, mtime });
}

const names = (result: any) => result.filesPayload.map((f: any) => f.name);

beforeEach(() => {
    files.clear();
    fileTimes.clear();
    vscodeStub.window.activeTextEditor = undefined;
    disk('Main.java', 'class Main {}', DURING);
});

test('a file the student added during the attempt is sent, and is not the main file', async () => {
    disk('src/Helper.java', 'class Helper {}', DURING);

    const result = await provider().collectSubmissionFiles();

    assert.deepStrictEqual(result.filesPayload, [
        { name: 'Main.java', content: 'class Main {}', is_primary: true },
        { name: 'src/Helper.java', content: 'class Helper {}', is_primary: false },
    ]);
    assert.strictEqual(result.primaryFileName, 'Main.java');
});

test('an added file stays out of the files whose compiler errors count', async () => {
    disk('Helper.java', 'class Helper {}', DURING);

    const result = await provider().collectSubmissionFiles();

    assert.deepStrictEqual(result.fileUris.map((u: any) => u.fsPath), ['/workspace/Main.java']);
});

test('a file from before the attempt stays on the student\'s computer', async () => {
    disk('OldLab.java', 'class OldLab {}', BEFORE);

    assert.deepStrictEqual(names(await provider().collectSubmissionFiles()), ['Main.java']);
});

test('an old file changed during the attempt is sent', async () => {
    disk('OldLab.java', 'class OldLab { int edited; }', BEFORE, DURING);

    assert.ok(names(await provider().collectSubmissionFiles()).includes('OldLab.java'));
});

test('a file copied in keeps its old modified time but is still sent', async () => {
    disk('Copied.java', 'class Copied {}', DURING, BEFORE);

    assert.ok(names(await provider().collectSubmissionFiles()).includes('Copied.java'));
});

test('notes, data and config files are never sent', async () => {
    for (const name of ['.env', 'secrets.json', 'notes.txt', 'config.yml', 'README.md']) {
        disk(name, 'API_KEY=abc', DURING);
    }

    assert.deepStrictEqual(names(await provider().collectSubmissionFiles()), ['Main.java']);
});

test('a starter file is sent once', async () => {
    disk('Helper.java', 'class Helper {}', DURING);
    const starter = [MAIN, { name: './Helper.java', content: '', is_primary: false }];

    const sent = names(await provider(starter).collectSubmissionFiles());

    assert.strictEqual(sent.filter((n: string) => n.endsWith('Helper.java')).length, 1);
});

test('a file over the server\'s size limit is skipped so it cannot block the submit', async () => {
    disk('Huge.js', 'x'.repeat(100_001), DURING);
    disk('Small.js', 'let small;', DURING);

    assert.deepStrictEqual(names(await provider().collectSubmissionFiles()), ['Main.java', 'Small.js']);
});

test('added files stop at the total size budget, newest first', async () => {
    disk('Old.js', 'a'.repeat(100_000), DURING);
    disk('Mid.js', 'b'.repeat(100_000), DURING + 1);
    disk('New.js', 'c'.repeat(100_000), DURING + 2);
    disk('Newest.js', 'd'.repeat(100_000), DURING + 3);

    assert.deepStrictEqual(names(await provider().collectSubmissionFiles()), ['Main.java', 'Newest.js', 'New.js', 'Mid.js']);
});

test('the submission never goes over the server\'s file count', async () => {
    for (let i = 0; i < 60; i++) {
        disk(`File${i}.js`, 'let x;', DURING + i);
    }

    assert.strictEqual((await provider().collectSubmissionFiles()).filesPayload.length, 50);
});

test('code from the open editor is listed with the added files', async () => {
    files.clear();
    fileTimes.clear();
    disk('Helper.java', 'class Helper {}', DURING);
    vscodeStub.window.activeTextEditor = {
        document: { fileName: '/workspace/Scratch.java', uri: vscodeStub.Uri.file('/workspace/Scratch.java'), getText: () => 'class Scratch {}' },
    };

    const result = await provider([]).collectSubmissionFiles();

    assert.deepStrictEqual(result.filesPayload, [
        { name: 'Scratch.java', content: 'class Scratch {}', is_primary: true },
        { name: 'Helper.java', content: 'class Helper {}', is_primary: false },
    ]);
});

test('a failed folder search does not stop the submission', async () => {
    const findFiles = vscodeStub.workspace.findFiles;
    vscodeStub.workspace.findFiles = async () => { throw new Error('search failed'); };

    try {
        assert.deepStrictEqual(names(await provider().collectSubmissionFiles()), ['Main.java']);
    } finally {
        vscodeStub.workspace.findFiles = findFiles;
    }
});
