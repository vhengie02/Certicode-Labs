import * as crypto from 'crypto';
import { open, seal } from '../teamSync/seal';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const key = crypto.randomBytes(32);
const bytes = (text: string) => new Uint8Array(Buffer.from(text, 'utf8'));

test('a sealed message opens to the same bytes', () => {
    const opened = open(key, seal(key, bytes('class Main {}')));

    assert.ok(opened);
    assert.strictEqual(Buffer.from(opened as Uint8Array).toString('utf8'), 'class Main {}');
});

test('an empty message survives the round trip', () => {
    const opened = open(key, seal(key, new Uint8Array(0)));

    assert.ok(opened);
    assert.strictEqual((opened as Uint8Array).length, 0);
});

test('two seals of the same bytes differ', () => {
    assert.notStrictEqual(seal(key, bytes('same')), seal(key, bytes('same')));
});

test('the sealed form does not contain the plain text', () => {
    const sealed = seal(key, bytes('secret-solution-code'));

    assert.ok(!Buffer.from(sealed, 'base64').toString('latin1').includes('secret-solution-code'));
});

test('a message sealed with another key does not open', () => {
    assert.strictEqual(open(crypto.randomBytes(32), seal(key, bytes('ours'))), null);
});

test('an altered message does not open', () => {
    const raw = Buffer.from(seal(key, bytes('unchanged')), 'base64');
    for (const index of [0, 12, raw.length - 1]) {
        const altered = Buffer.from(raw);
        altered[index] ^= 0x01;
        assert.strictEqual(open(key, altered.toString('base64')), null, `byte ${index}`);
    }
});

test('a truncated message does not open', () => {
    const sealed = seal(key, bytes('whole message'));

    assert.strictEqual(open(key, sealed.slice(0, sealed.length - 8)), null);
});

test('garbage does not open and does not throw', () => {
    for (const garbage of ['', 'not base64!', 'AAAA', '%%%%', 'A'.repeat(5000)]) {
        assert.strictEqual(open(key, garbage), null, JSON.stringify(garbage.slice(0, 12)));
    }
    assert.strictEqual(open(key, undefined as unknown as string), null);
    assert.strictEqual(open(key, { d: 'x' } as unknown as string), null);
});

test('a key of the wrong length is refused rather than weakened', () => {
    assert.throws(() => seal(Buffer.alloc(16), bytes('x')));
    assert.strictEqual(open(Buffer.alloc(16), seal(key, bytes('x'))), null);
});
