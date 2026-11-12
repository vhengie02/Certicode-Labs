import * as crypto from 'crypto';
import { seal } from '../teamSync/seal';
import { TeamDoc } from '../teamSync/teamDoc';
import { encode, KIND_UPDATE } from '../teamSync/wire';
import { FakeStore, Hub, HubTransport, InstantEditor, sleep, until } from './teamSyncFakes';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const STARTER = [{ name: 'Main.java', content: 'class Main {\r\n}\r\n' }, { name: 'Util.java', content: '' }];
const STARTER_MAIN = 'class Main {\n}\n';

interface Teammate {
    doc: TeamDoc;
    transport: HubTransport;
    editors: Map<string, InstantEditor>;
    /** Type into a file as this teammate's user. */
    type(name: string, offset: number, length: number, text: string): void;
    text(name: string): string | undefined;
}

function team(options: { key?: Buffer; minSendIntervalMs?: number; storeIntervalMs?: number; compactAfter?: number } = {}) {
    const hub = new Hub();
    const store = new FakeStore();
    const key = options.key ?? crypto.randomBytes(32);
    const members: Teammate[] = [];

    /** A teammate opens the lab: loads the document and binds an editor to every file. */
    const join = async (starter = STARTER, memberKey = key): Promise<Teammate> => {
        const transport = hub.join();
        const doc = new TeamDoc({
            key: memberKey,
            transport,
            store,
            minSendIntervalMs: options.minSendIntervalMs ?? 10,
            storeIntervalMs: options.storeIntervalMs ?? 20,
            compactAfter: options.compactAfter ?? 200,
            helloJitterMs: 20,
        });
        const editors = new Map<string, InstantEditor>();
        const bind = (name: string): void => {
            if (editors.has(name)) { return; }
            const editor = new InstantEditor();
            editor.text = doc.text(name) ?? '';
            editor.binding = doc.bind(name, editor);
            editors.set(name, editor);
        };
        doc.onFileAdded(bind);
        doc.onFileRemoved((name: string) => { doc.unbind(name); editors.delete(name); });
        await doc.start(starter);
        for (const name of doc.fileNames()) { bind(name); }

        const member: Teammate = {
            doc,
            transport,
            editors,
            type: (name, offset, length, text) => editors.get(name)!.type(offset, length, text),
            text: name => editors.get(name)?.text,
        };
        members.push(member);
        return member;
    };

    const stopAll = () => { for (const member of members) { member.doc.stop(); } };
    return { hub, store, key, join, stopAll };
}

test('the first teammate seeds the starter files and the second loads them', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();

    assert.deepStrictEqual(alice.doc.fileNames().sort(), ['Main.java', 'Util.java']);
    assert.deepStrictEqual(bob.doc.fileNames().sort(), ['Main.java', 'Util.java']);
    // Starter text is kept once, with LF line endings
    assert.strictEqual(alice.text('Main.java'), STARTER_MAIN);
    assert.strictEqual(bob.text('Main.java'), STARTER_MAIN);
    assert.strictEqual(bob.text('Util.java'), '');
    t.stopAll();
});

test('two teammates starting at once seed only once', async () => {
    const t = team();
    const [alice, bob] = await Promise.all([t.join(), t.join()]);
    await until(() => alice.text('Main.java') === STARTER_MAIN && bob.text('Main.java') === STARTER_MAIN);

    assert.strictEqual(alice.text('Main.java'), STARTER_MAIN);
    assert.strictEqual(bob.text('Main.java'), STARTER_MAIN);
    t.stopAll();
});

test('two teammates see each other\'s edits', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();

    alice.type('Main.java', 0, 0, '// alice\n');
    bob.type('Util.java', 0, 0, 'class Util {}');
    await until(() => bob.text('Main.java')!.startsWith('// alice') && alice.text('Util.java') === 'class Util {}');

    assert.strictEqual(bob.text('Main.java'), '// alice\n' + STARTER_MAIN);
    assert.strictEqual(alice.text('Util.java'), 'class Util {}');
    t.stopAll();
});

test('what goes over the channel is sealed', async () => {
    const t = team();
    const alice = await t.join();
    await t.join();

    alice.type('Main.java', 0, 0, 'TOP_SECRET_SOLUTION');
    await alice.doc.flush();
    await sleep(30);

    assert.ok(t.hub.traffic.length > 0);
    for (const message of t.hub.traffic) {
        assert.ok(!Buffer.from(message, 'base64').toString('latin1').includes('TOP_SECRET_SOLUTION'));
        assert.ok(!Buffer.from(message, 'base64').toString('latin1').includes('Main.java'));
    }
    t.stopAll();
});

test('a late joiner alone loads the stored document', async () => {
    const t = team();
    const alice = await t.join();
    alice.type('Main.java', 0, 0, '// kept\n');
    await alice.doc.flush();
    alice.doc.stop();

    const bob = await t.join();

    assert.strictEqual(bob.text('Main.java'), '// kept\n' + STARTER_MAIN);
    t.stopAll();
});

test('edits made offline merge on reconnect', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();

    bob.transport.goOffline();
    bob.type('Main.java', 0, 0, '// bob offline\n');
    alice.type('Main.java', STARTER_MAIN.length, 0, '// alice meanwhile\n');
    await sleep(60);
    assert.ok(!alice.text('Main.java')!.includes('bob offline'));

    bob.transport.goOnline();
    await until(() => alice.text('Main.java')!.includes('bob offline') && bob.text('Main.java')!.includes('alice meanwhile'));

    assert.strictEqual(alice.text('Main.java'), '// bob offline\n' + STARTER_MAIN + '// alice meanwhile\n');
    assert.strictEqual(bob.text('Main.java'), alice.text('Main.java'));
    t.stopAll();
});

test('local edits are merged into one message per send interval', async () => {
    const t = team({ minSendIntervalMs: 150 });
    const alice = await t.join();
    const bob = await t.join();
    await sleep(400);                        // past the hellos and their answers
    const before = alice.transport.sent;

    for (let i = 0; i < 10; i++) { alice.type('Main.java', 0, 0, 'x'); }
    await until(() => bob.text('Main.java')!.startsWith('xxxxxxxxxx'));

    // One goes at once; the rest travel together when the interval is up
    assert.ok(alice.transport.sent - before <= 2, `sent ${alice.transport.sent - before}`);
    t.stopAll();
});

test('the first edit after a quiet spell is sent at once', async () => {
    const t = team({ minSendIntervalMs: 300 });
    const alice = await t.join();
    const bob = await t.join();
    await sleep(700);

    const typedAt = Date.now();
    alice.type('Main.java', 0, 0, 'now');
    await until(() => bob.text('Main.java')!.startsWith('now'));

    assert.ok(Date.now() - typedAt < 150, `took ${Date.now() - typedAt} ms`);
    t.stopAll();
});

test('a message that does not open is ignored', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();
    const forged = encode(KIND_UPDATE, [{ name: 'Main.java', data: new Uint8Array([1, 2, 3]) }]);

    t.hub.inject('garbage that is not even base64 !!');
    t.hub.inject('');
    t.hub.inject(seal(crypto.randomBytes(32), forged));      // another team's key
    t.hub.inject(seal(t.key, new Uint8Array([9, 9, 9])));    // our key, but not laid out as a message
    t.hub.inject(seal(t.key, forged));                       // our key, edits that are nonsense
    await sleep(40);

    assert.strictEqual(alice.text('Main.java'), STARTER_MAIN);
    assert.strictEqual(bob.text('Main.java'), STARTER_MAIN);

    // And the document still works afterwards
    alice.type('Main.java', 0, 0, 'ok');
    await until(() => bob.text('Main.java')!.startsWith('ok'));
    assert.strictEqual(bob.text('Main.java'), 'ok' + STARTER_MAIN);
    t.stopAll();
});

test('someone holding another key reads and writes nothing', async () => {
    const t = team();
    const alice = await t.join();
    const outsider = await t.join([], crypto.randomBytes(32));

    alice.type('Main.java', 0, 0, 'private');
    outsider.doc.createFile('Evil.java', 'injected');
    await alice.doc.flush();
    await outsider.doc.flush();
    await sleep(60);

    assert.ok(!alice.doc.fileNames().includes('Evil.java'));
    assert.strictEqual(alice.text('Main.java'), 'private' + STARTER_MAIN);
    t.stopAll();
});

test('a file added by one teammate appears for the other, and a deleted one goes', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();

    alice.doc.createFile('Helper.java', 'class Helper {}\r\n');
    await until(() => bob.doc.fileNames().includes('Helper.java'));
    assert.strictEqual(bob.text('Helper.java'), 'class Helper {}\n');

    bob.doc.deleteFile('Util.java');
    await until(() => !alice.doc.fileNames().includes('Util.java'));
    assert.deepStrictEqual(alice.doc.fileNames().sort(), ['Helper.java', 'Main.java']);
    assert.strictEqual(alice.editors.has('Util.java'), false);
    t.stopAll();
});

test('a deleted file stays deleted for a teammate who joins later', async () => {
    const t = team();
    const alice = await t.join();
    alice.doc.deleteFile('Util.java');
    await alice.doc.flush();

    const bob = await t.join();

    assert.deepStrictEqual(bob.doc.fileNames(), ['Main.java']);
    t.stopAll();
});

test('flush stores what was typed', async () => {
    const t = team({ storeIntervalMs: 60000 });
    const alice = await t.join();
    const before = t.store.rows.length;

    alice.type('Main.java', 0, 0, 'typed');
    await alice.doc.flush();

    assert.strictEqual(t.store.rows.length, before + 1);
    t.stopAll();
});

test('edits the store refused are kept and stored later', async () => {
    const t = team({ storeIntervalMs: 15 });
    const alice = await t.join();
    t.store.failing = true;
    alice.type('Main.java', 0, 0, 'kept');
    await sleep(60);

    t.store.failing = false;
    await alice.doc.flush();
    alice.doc.stop();
    const bob = await t.join();

    assert.strictEqual(bob.text('Main.java'), 'kept' + STARTER_MAIN);
    t.stopAll();
});

test('a long document is compacted', async () => {
    const t = team({ storeIntervalMs: 5, compactAfter: 8 });
    const alice = await t.join();

    for (let i = 0; i < 30; i++) {
        alice.type('Main.java', 0, 0, String(i % 10));
        await alice.doc.flush();
    }
    await sleep(40);

    assert.ok(t.store.rows.length <= 10, `rows ${t.store.rows.length}`);
    alice.doc.stop();
    const bob = await t.join();
    assert.strictEqual(bob.text('Main.java'), alice.text('Main.java'));
    assert.strictEqual(bob.text('Main.java')!.length, 30 + STARTER_MAIN.length);
    t.stopAll();
});

test('a store that asks for compaction gets one', async () => {
    const t = team({ storeIntervalMs: 5, compactAfter: 1000 });
    const alice = await t.join();
    t.store.maxRows = 3;

    for (let i = 0; i < 6; i++) {
        alice.type('Main.java', 0, 0, 'y');
        await alice.doc.flush();
    }

    assert.ok(t.store.rows.length <= 3, `rows ${t.store.rows.length}`);
    alice.doc.stop();
    const bob = await t.join();
    assert.strictEqual(bob.text('Main.java'), 'yyyyyy' + STARTER_MAIN);
    t.stopAll();
});

test('stop sends and applies nothing more', async () => {
    const t = team();
    const alice = await t.join();
    const bob = await t.join();
    await sleep(350);

    bob.doc.stop();
    const sent = bob.transport.sent;
    alice.type('Main.java', 0, 0, 'after');
    bob.editors.get('Main.java')!.type(0, 0, 'ignored');
    await sleep(80);

    assert.strictEqual(bob.transport.sent, sent);
    assert.strictEqual(bob.text('Main.java'), 'ignored' + STARTER_MAIN);
    assert.ok(!alice.text('Main.java')!.includes('ignored'));
    t.stopAll();
});
