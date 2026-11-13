import * as crypto from 'crypto';
import { TeamDoc } from '../teamSync/teamDoc';
import { LocalChange } from '../teamSync/textBinding';
import { HostDocument, isShareableName, SyncHost, WorkspaceSync } from '../teamSync/workspaceSync';
import { FakeStore, Hub, sleep, until } from './teamSyncFakes';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

/** A lab file open in a fake editor. Every change reaches the workspace layer, as VS Code's events would. */
class FakeDocument implements HostDocument {
    public isDirty = false;
    public saves = 0;
    /** Edits asked for by the extension wait until released. */
    public hold = false;
    private held: (() => void)[] = [];
    public onChange?: (changes: LocalChange[]) => void;

    constructor(public text: string) {}

    getText() { return this.text; }

    async replace(offset: number, length: number, text: string): Promise<boolean> {
        if (this.hold) {
            await new Promise<void>(resolve => this.held.push(resolve));
        }
        this.apply({ offset, length, text });
        return true;
    }

    release() {
        this.hold = false;
        for (const resolve of this.held.splice(0)) { resolve(); }
    }

    /** The student types. */
    type(offset: number, length: number, text: string) {
        this.apply({ offset, length, text });
    }

    async save() {
        this.saves++;
        this.isDirty = false;
    }

    private apply(change: LocalChange) {
        this.text = this.text.slice(0, change.offset) + change.text + this.text.slice(change.offset + change.length);
        this.isDirty = true;
        this.onChange?.([change]);
    }
}

/** A lab folder on a fake disk. */
class FakeHost implements SyncHost {
    public disk = new Map<string, string>();
    public open = new Map<string, FakeDocument>();
    public sync?: WorkspaceSync;

    async openDocument(name: string, contentIfMissing: string) {
        if (!this.disk.has(name)) { this.disk.set(name, contentIfMissing); }
        let document = this.open.get(name);
        if (!document) {
            // A CRLF file is opened as LF, as the VS Code host does
            document = new FakeDocument(this.disk.get(name)!.replace(/\r\n/g, '\n'));
            document.onChange = changes => this.sync?.handleChange(name, changes);
            this.open.set(name, document);
        }
        return document;
    }

    async readText(name: string) {
        return this.open.get(name)?.text ?? this.disk.get(name);
    }

    async writeBackup(name: string, content: string) {
        this.disk.set(`.certicode/before-shared-typing/${name}`, content);
    }

    async deleteFile(name: string) {
        this.disk.delete(name);
        this.open.delete(name);
    }
}

const STARTER = [
    { name: 'Main.java', content: 'class Main {\n}\n' },
    { name: 'README.md', content: '# notes' },
];

function lab(options: { autosaveMs?: number } = {}) {
    const hub = new Hub();
    const store = new FakeStore();
    const key = crypto.randomBytes(32);

    const join = async (disk: Record<string, string> = { 'Main.java': STARTER[0].content }, ownClientIds: number[] = [], before?: (sync: WorkspaceSync) => void) => {
        const host = new FakeHost();
        for (const [name, content] of Object.entries(disk)) { host.disk.set(name, content); }
        const doc = new TeamDoc({ key, transport: hub.join(), store, minSendIntervalMs: 5, storeIntervalMs: 10, helloJitterMs: 5, ownClientIds });
        const sync = new WorkspaceSync(host, doc, { autosaveMs: options.autosaveMs ?? 30 });
        host.sync = sync;
        before?.(sync);
        await sync.start(STARTER);
        return { host, doc, sync, file: (name: string) => host.open.get(name) };
    };

    return { hub, store, join };
}

test('only code files with a plain path inside the lab can be shared', () => {
    for (const name of ['Main.java', 'src/app/Main.java', 'util.py']) {
        assert.strictEqual(isShareableName(name), true, name);
    }
    for (const name of ['README.md', 'data.json', 'notes.txt', '../secret.java', 'src/../../x.java', '/etc/passwd',
        'C:/Windows/x.java', 'a\\b.java', '.hidden.java', 'src/.git/config', '', 'a//b.java']) {
        assert.strictEqual(isShareableName(name), false, name);
    }
});

test('a notes file is not shared, code files are', async () => {
    const t = lab();
    const alice = await t.join();

    assert.deepStrictEqual(alice.sync.sharedNames(), ['Main.java']);
    t.hub.transports.forEach(transport => transport.close());
});

test('a CRLF file on disk is bound with LF line endings', async () => {
    const t = lab();
    const alice = await t.join({ 'Main.java': 'class Main {\r\n}\r\n' });

    assert.strictEqual(alice.file('Main.java')!.text, 'class Main {\n}\n');
    assert.strictEqual(alice.doc.text('Main.java'), 'class Main {\n}\n');
    alice.sync.stop();
});

test('a teammate\'s edit is reported as theirs and saved a moment later', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    const reports: boolean[] = [];
    const document = bob.file('Main.java')!;
    document.onChange = changes => reports.push(bob.sync.handleChange('Main.java', changes));

    alice.file('Main.java')!.type(0, 0, '// from alice\n');
    await until(() => document.text.startsWith('// from alice'));
    await until(() => document.saves > 0);

    assert.deepStrictEqual(reports, [true]);
    assert.strictEqual(document.saves, 1);
    alice.sync.stop();
    bob.sync.stop();
});

test('the student\'s own typing is reported as theirs', async () => {
    const t = lab();
    const alice = await t.join();

    const remote = alice.sync.handleChange('Main.java', [{ offset: 0, length: 0, text: 'x' }]);

    assert.strictEqual(remote, false);
    alice.sync.stop();
});

test('the student\'s own unsaved edits stop the automatic save', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    const document = bob.file('Main.java')!;

    document.type(0, 0, '// bob, not saved\n');
    alice.file('Main.java')!.type(alice.file('Main.java')!.text.length, 0, '// alice\n');
    await until(() => document.text.endsWith('// alice\n'));
    await sleep(80);
    assert.strictEqual(document.saves, 0);

    // Once the student saves, teammates' later edits are saved for them again
    await document.save();
    bob.sync.noteSaved('Main.java');
    alice.file('Main.java')!.type(0, 0, '// again\n');
    await until(() => document.saves > 1);
    assert.strictEqual(document.saves, 2);
    alice.sync.stop();
    bob.sync.stop();
});

test('the team\'s text wins over an out-of-date file on disk', async () => {
    const t = lab();
    const alice = await t.join();
    alice.file('Main.java')!.type(0, 0, '// newer\n');
    await alice.sync.flush();

    const bob = await t.join({ 'Main.java': 'stale copy' });

    assert.strictEqual(bob.file('Main.java')!.text, '// newer\n' + STARTER[0].content);
    alice.sync.stop();
    bob.sync.stop();
});

test('a file a teammate adds is created in the lab folder, and one they delete goes', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();

    alice.sync.fileCreated('Helper.java', 'class Helper {}');
    await until(() => bob.host.disk.has('Helper.java') && bob.sync.sharedNames().includes('Helper.java'));
    assert.strictEqual(bob.file('Helper.java')!.text, 'class Helper {}');

    alice.sync.fileDeleted('Helper.java');
    await until(() => !bob.host.disk.has('Helper.java'));
    assert.deepStrictEqual(bob.sync.sharedNames(), ['Main.java']);
    alice.sync.stop();
    bob.sync.stop();
});

test('a notes file or a path outside the lab is never created for a teammate', async () => {
    const t = lab();
    const alice = await t.join();

    alice.sync.fileCreated('todo.md', 'x');
    alice.doc.createFile('../outside.java', 'x');
    await alice.sync.flush();
    const bob = await t.join();

    assert.deepStrictEqual(bob.sync.sharedNames(), ['Main.java']);
    assert.ok(![...bob.host.disk.keys()].some(name => name.includes('..') || name === 'todo.md'));
    alice.sync.stop();
    bob.sync.stop();
});

test('each student is credited only for the lines they wrote', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    const main = (who: typeof alice) => who.file('Main.java')!;

    main(alice).type(main(alice).text.length, 0, 'int a = 1;\nint b = 2;\n');
    await until(() => main(bob).text.endsWith('int b = 2;\n'));
    main(bob).type(main(bob).text.length, 0, 'int c = 3;\n');
    await until(() => main(alice).text.endsWith('int c = 3;\n'));

    const stats = (who: typeof alice) => who.sync.lineStats().find(file => file.name === 'Main.java')!;
    assert.deepStrictEqual({ added: stats(alice).added, modified: stats(alice).modified }, { added: 2, modified: 0 });
    assert.deepStrictEqual({ added: stats(bob).added, modified: stats(bob).modified }, { added: 1, modified: 0 });

    // Bob changes one of Alice's lines a little: it stays hers, and counts as changed for him
    const at = main(bob).text.indexOf('1;');
    main(bob).type(at, 1, '9');
    await until(() => main(alice).text.includes('9;'));
    assert.strictEqual(stats(alice).added, 2);
    assert.strictEqual(stats(bob).modified, 1);
    // Starter code is nobody's
    assert.ok(stats(alice).added + stats(bob).added === 3);

    // Bob deletes a whole line: counted as his deletion
    const line = main(bob).text.indexOf('int c = 3;\n');
    main(bob).type(line, 'int c = 3;\n'.length, '');
    assert.strictEqual(stats(bob).deleted, 1);
    assert.strictEqual(stats(alice).deleted, 0);
    alice.sync.stop();
    bob.sync.stop();
});

test('lines written before a reload still count for the student', async () => {
    const t = lab();
    const first = await t.join();
    first.file('Main.java')!.type(0, 0, 'int mine = 1;\n');
    await first.sync.flush();
    const earlierIds = first.doc.clientIds();
    first.sync.stop();

    const again = await t.join(undefined, earlierIds);
    const forgetful = await t.join();

    assert.strictEqual(again.sync.lineStats()[0].added, 1);
    assert.strictEqual(forgetful.sync.lineStats()[0].added, 0);
    again.sync.stop();
    forgetful.sync.stop();
});

test('flush waits for teammates\' edits to reach the editor and stores the student\'s', async () => {
    const t = lab();
    const alice = await t.join();
    const before = t.store.rows.length;

    alice.file('Main.java')!.type(0, 0, 'typed');
    await alice.sync.flush();

    assert.ok(t.store.rows.length > before);
    alice.sync.stop();
});

test('after stop nothing more is shared or applied', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    await sleep(40);

    bob.sync.stop();
    alice.file('Main.java')!.type(0, 0, 'late ');
    await sleep(60);

    assert.ok(!bob.file('Main.java')!.text.startsWith('late'));
    assert.strictEqual(bob.sync.handleChange('Main.java', [{ offset: 0, length: 0, text: 'x' }]), false);
    alice.sync.stop();
});

test('a new team document is seeded from the student\'s own files, not the lab\'s starter code', async () => {
    const t = lab();
    const alice = await t.join({ 'Main.java': 'class Main { void work() {} }\n' });

    assert.strictEqual(alice.doc.text('Main.java'), 'class Main { void work() {} }\n');
    assert.strictEqual(alice.file('Main.java')!.text, 'class Main { void work() {} }\n');
    alice.sync.stop();
});

test('a student\'s own copy that the team\'s text replaces is kept as a backup', async () => {
    const t = lab();
    const alice = await t.join({ 'Main.java': 'alice work\n' });
    const backups: string[] = [];

    const bob = await t.join({ 'Main.java': 'bob work\n' }, [], sync => sync.onBackup(name => backups.push(name)));

    assert.strictEqual(bob.file('Main.java')!.text, 'alice work\n');
    assert.strictEqual(bob.host.disk.get('.certicode/before-shared-typing/Main.java'), 'bob work\n');
    assert.deepStrictEqual(backups, ['Main.java']);
    alice.sync.stop();
    bob.sync.stop();
});

test('a copy that is only the lab\'s starter code is replaced without a backup', async () => {
    const t = lab();
    const alice = await t.join({ 'Main.java': 'alice work\n' });

    const bob = await t.join();

    assert.strictEqual(bob.file('Main.java')!.text, 'alice work\n');
    assert.ok(![...bob.host.disk.keys()].some(name => name.startsWith('.certicode/')));
    alice.sync.stop();
    bob.sync.stop();
});

test('a renamed file is shared under its new name', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();

    alice.sync.fileRenamed('Main.java', 'App.java', alice.file('Main.java')!.text);
    await until(() => bob.sync.sharedNames().includes('App.java') && !bob.sync.sharedNames().includes('Main.java'));

    assert.deepStrictEqual(bob.sync.sharedNames(), ['App.java']);
    assert.strictEqual(bob.file('App.java')!.text, STARTER[0].content);
    assert.ok(!bob.host.disk.has('Main.java'));
    alice.sync.stop();
    bob.sync.stop();
});

test('stopping says which teammates\' edits are still on their way to the editor', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    bob.file('Main.java')!.hold = true;

    alice.file('Main.java')!.type(0, 0, '// late\n');
    await sleep(60);
    const inFlight = bob.sync.stop();

    assert.deepStrictEqual([...inFlight], [['Main.java', '// late\n' + STARTER[0].content]]);
    alice.sync.stop();
});

test('an undo that removes a teammate\'s edit is reported', async () => {
    const t = lab();
    const alice = await t.join();
    const bob = await t.join();
    const undone: string[] = [];
    bob.sync.onUndoOthers(name => undone.push(name));
    alice.file('Main.java')!.type(0, 0, '// alice\n');
    await until(() => bob.file('Main.java')!.text.startsWith('// alice'));

    // As VS Code reports an Undo: the teammate's insertion goes again
    const document = bob.file('Main.java')!;
    document.text = document.text.slice('// alice\n'.length);
    bob.sync.handleChange('Main.java', [{ offset: 0, length: '// alice\n'.length, text: '' }], true);

    assert.deepStrictEqual(undone, ['Main.java']);
    alice.sync.stop();
    bob.sync.stop();
});
