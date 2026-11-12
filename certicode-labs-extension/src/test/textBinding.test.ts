import * as Y from 'yjs';
import { EditorPort, LocalChange, REMOTE, TextBinding } from '../teamSync/textBinding';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const sleep = (ms: number) => new Promise<void>(resolve => setTimeout(resolve, ms));

/** A small deterministic random source, so a failing run can be repeated from its seed. */
function random(seed: number) {
    let state = seed >>> 0;
    return () => {
        state = (state * 1664525 + 1013904223) >>> 0;
        return state / 0x100000000;
    };
}

/**
 * Behaves like a VS Code document as far as the binding can tell: an edit asked for by the
 * extension lands a little later, is refused if the user typed in the meantime, and may be
 * reported as several smaller changes in one event.
 */
class FakeEditor implements EditorPort {
    public text = '';
    public binding?: TextBinding;
    public hold = false;
    public rejections = 0;
    /** Every text the editor ever showed, to catch a character appearing in the wrong place. */
    public history: string[] = [];
    private version = 0;
    private held: (() => void)[] = [];

    constructor(private rand: () => number = Math.random, private splitEchoes = true) {}

    getText() { return this.text; }

    replace(offset: number, length: number, text: string): Promise<boolean> {
        const requestedAt = this.version;
        return new Promise(resolve => {
            const land = () => {
                if (this.version !== requestedAt) {
                    this.rejections++;
                    resolve(false);
                    return;
                }
                this.apply(offset, length, text);
                this.binding?.handleEditorChange(this.asChanges(offset, length, text));
                resolve(true);
            };
            if (this.hold) {
                this.held.push(land);
            } else {
                setTimeout(land, Math.floor(this.rand() * 3));
            }
        });
    }

    /** Let edits that were held back land now. */
    release() {
        this.hold = false;
        for (const land of this.held.splice(0)) { land(); }
    }

    /** The user types or deletes. */
    type(offset: number, length: number, text: string) {
        this.apply(offset, length, text);
        return this.binding?.handleEditorChange([{ offset, length, text }]);
    }

    private apply(offset: number, length: number, text: string) {
        this.text = this.text.slice(0, offset) + text + this.text.slice(offset + length);
        this.version++;
        this.history.push(this.text);
    }

    /** One replacement, sometimes reported as two changes in the same event (as VS Code does). */
    private asChanges(offset: number, length: number, text: string): LocalChange[] {
        if (!this.splitEchoes || text.length < 2 || this.rand() < 0.5) {
            return [{ offset, length, text }];
        }
        const cut = 1 + Math.floor(this.rand() * (text.length - 1));
        // Offsets are in the text before the event, later change first, as VS Code reports them
        return [
            { offset: offset + length, length: 0, text: text.slice(cut) },
            { offset, length, text: text.slice(0, cut) },
        ];
    }
}

/** A binding on its own document, plus a teammate's document wired to it with a delay. */
function pair(seed = 1, options: { lagMs?: number; splitEchoes?: boolean } = {}) {
    const rand = random(seed);
    const mine = new Y.Doc();
    const theirs = new Y.Doc();
    const editor = new FakeEditor(rand, options.splitEchoes ?? true);
    const binding = new TextBinding(mine, editor);
    editor.binding = binding;
    const lag = () => (options.lagMs ? sleep(Math.floor(rand() * options.lagMs)) : Promise.resolve());

    mine.on('update', (update: Uint8Array, origin: unknown) => {
        if (origin !== REMOTE) { void lag().then(() => Y.applyUpdate(theirs, update, 'net')); }
    });
    theirs.on('update', (update: Uint8Array, origin: unknown) => {
        if (origin !== 'net') { void lag().then(() => binding.receive(update)); }
    });

    return { rand, mine, theirs, editor, binding, myText: mine.getText('text'), theirText: theirs.getText('text') };
}

async function quiet(p: ReturnType<typeof pair>) {
    for (let i = 0; i < 400; i++) {
        await p.binding.settled();
        await sleep(5);
        if (p.editor.text === p.myText.toString() && p.editor.text === p.theirText.toString()) { return; }
    }
}

test('a remote insert appears in the editor and is not counted as local', async () => {
    const p = pair();

    p.theirText.insert(0, 'hello');
    await quiet(p);

    assert.strictEqual(p.editor.text, 'hello');
    assert.strictEqual(p.myText.toString(), 'hello');
    assert.strictEqual(p.binding.localChars, 0);
});

test('a local insert reaches the shared text and is counted', async () => {
    const p = pair();

    const wasRemote = p.editor.type(0, 0, 'abc');
    await quiet(p);

    assert.strictEqual(wasRemote, false);
    assert.strictEqual(p.theirText.toString(), 'abc');
    assert.strictEqual(p.binding.localChars, 3);
});

test('the change event of a remote edit is reported as remote', async () => {
    const p = pair();
    const reports: boolean[] = [];
    const original = p.binding.handleEditorChange.bind(p.binding);
    p.binding.handleEditorChange = changes => {
        const remote = original(changes);
        reports.push(remote);
        return remote;
    };

    p.theirText.insert(0, 'from a teammate');
    await quiet(p);

    assert.deepStrictEqual(reports, [true]);
});

test('a rejected remote edit is retried until the editor matches', async () => {
    const p = pair();
    p.editor.type(0, 0, 'hello world');
    await quiet(p);

    p.editor.hold = true;
    p.theirText.insert(0, 'AAA');
    await sleep(20);
    p.editor.type(11, 0, '!');     // the user types while the teammate's edit is on its way
    p.editor.release();
    await quiet(p);

    assert.ok(p.editor.rejections >= 1);
    assert.strictEqual(p.editor.text, 'AAAhello world!');
    assert.strictEqual(p.theirText.toString(), 'AAAhello world!');
    assert.strictEqual(p.binding.localChars, 12);
});

test('a character typed while a remote edit is in flight stays where it was typed', async () => {
    const p = pair();
    p.editor.type(0, 0, 'hello world');
    await quiet(p);

    p.editor.hold = true;
    p.theirText.insert(0, 'AAA');          // before the caret
    p.theirText.insert(14, 'ZZZ');         // after the caret ("AAAhello world" + "ZZZ")
    await sleep(20);
    p.editor.type(5, 0, '!');              // right after "hello"
    p.editor.type(6, 0, '?');
    p.editor.release();
    await quiet(p);

    assert.strictEqual(p.editor.text, 'AAAhello!? worldZZZ');
    assert.strictEqual(p.theirText.toString(), 'AAAhello!? worldZZZ');
    // The typed characters never showed up anywhere but right after "hello"
    for (const shown of p.editor.history.filter(text => text.includes('!'))) {
        assert.ok(shown.includes('hello!'), `misplaced in ${JSON.stringify(shown)}`);
    }
});

test('a deletion made while a remote edit is in flight removes what the user selected', async () => {
    const p = pair();
    p.editor.type(0, 0, 'one two three');
    await quiet(p);

    p.editor.hold = true;
    p.theirText.insert(0, '>> ');
    await sleep(20);
    p.editor.type(4, 4, '');               // the user deletes "two "
    p.editor.release();
    await quiet(p);

    assert.strictEqual(p.editor.text, '>> one three');
    assert.strictEqual(p.theirText.toString(), '>> one three');
});

test('an echo reported as several changes in one event is still recognised', async () => {
    for (let seed = 1; seed <= 20; seed++) {
        const p = pair(seed);
        p.theirText.insert(0, 'a long enough remote insert');
        await quiet(p);

        assert.strictEqual(p.editor.text, 'a long enough remote insert', `seed ${seed}`);
        assert.strictEqual(p.binding.localChars, 0, `seed ${seed}`);
    }
});

for (const seed of [11, 22, 33]) {
    test(`random typing on both sides converges and counts only local characters (seed ${seed})`, async () => {
        const p = pair(seed, { lagMs: seed === 33 ? 12 : 0 });
        let typedLocally = 0;

        for (let round = 0; round < 400; round++) {
            if (p.rand() < 0.5) {
                const length = p.editor.text.length;
                if (p.rand() < 0.2 && length > 2) {
                    p.editor.type(Math.floor(p.rand() * (length - 1)), 1, '');
                } else {
                    const text = p.rand() < 0.2 ? 'L\n' : 'L';
                    p.editor.type(Math.floor(p.rand() * (length + 1)), 0, text);
                    typedLocally += text.length;
                }
            } else {
                const length = p.theirText.length;
                if (p.rand() < 0.2 && length > 2) {
                    p.theirText.delete(Math.floor(p.rand() * (length - 1)), 1);
                } else {
                    p.theirText.insert(Math.floor(p.rand() * (length + 1)), p.rand() < 0.2 ? 'rrrr\n' : 'r');
                }
            }
            if (p.rand() < 0.4) { await sleep(Math.floor(p.rand() * 3)); }
        }
        await quiet(p);

        assert.strictEqual(p.editor.text, p.myText.toString());
        assert.strictEqual(p.editor.text, p.theirText.toString());
        assert.strictEqual(p.binding.localChars, typedLocally);
    });
}

test('settled resolves only when nothing is in flight', async () => {
    const p = pair();
    p.editor.hold = true;
    p.theirText.insert(0, 'waiting');
    await sleep(20);

    let settled = false;
    void p.binding.settled().then(() => { settled = true; });
    await sleep(30);
    assert.strictEqual(settled, false);

    p.editor.release();
    await sleep(30);
    assert.strictEqual(settled, true);
    assert.strictEqual(p.editor.text, 'waiting');
});

test('what is waiting to be shown still counts as part of the document', async () => {
    const p = pair();
    p.editor.hold = true;
    p.theirText.insert(0, 'not shown yet');
    await sleep(20);

    // A teammate asking for the document now must get the text that is still queued
    const asked = new Y.Doc();
    Y.applyUpdate(asked, p.binding.fullState());
    assert.strictEqual(asked.getText('text').toString(), 'not shown yet');
    assert.strictEqual(p.editor.text, '');
    p.editor.release();
});

test('dispose stops applying remote edits', async () => {
    const p = pair();
    p.binding.dispose();

    p.theirText.insert(0, 'late');
    await sleep(40);

    assert.strictEqual(p.editor.text, '');
    assert.strictEqual(p.editor.type(0, 0, 'x'), false);
});
