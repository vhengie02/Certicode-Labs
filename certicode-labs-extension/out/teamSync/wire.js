"use strict";
/**
 * How a batch of edits is laid out before it is sealed and sent, or stored. Each shared file
 * is its own document, so every entry names its file:
 *
 *   [kind: 1 byte] then, per entry, [name length: 2 bytes][name, UTF-8][data length: 4 bytes][data]
 */
Object.defineProperty(exports, "__esModule", { value: true });
exports.decode = exports.encode = exports.KIND_HELLO_BACK = exports.KIND_ANSWER = exports.KIND_HELLO = exports.KIND_UPDATE = void 0;
/** Edits to apply. */
exports.KIND_UPDATE = 1;
/** "This is what I have": per file, a state vector. Sent on connecting. */
exports.KIND_HELLO = 2;
/** Edits answering a hello: what the asker was missing. Applied like KIND_UPDATE. */
exports.KIND_ANSWER = 3;
/** A hello sent in reply to a hello. Answered with edits, never with another hello. */
exports.KIND_HELLO_BACK = 4;
const MAX_NAME_BYTES = 1024;
function encode(kind, entries) {
    const parts = [Buffer.from([kind])];
    for (const entry of entries) {
        const name = Buffer.from(entry.name, 'utf8');
        if (name.length === 0 || name.length > MAX_NAME_BYTES) {
            throw new Error('A shared file needs a name of 1 to 1024 bytes.');
        }
        const header = Buffer.alloc(2 + name.length + 4);
        header.writeUInt16BE(name.length, 0);
        name.copy(header, 2);
        header.writeUInt32BE(entry.data.length, 2 + name.length);
        parts.push(header, Buffer.from(entry.data.buffer, entry.data.byteOffset, entry.data.length));
    }
    return new Uint8Array(Buffer.concat(parts));
}
exports.encode = encode;
/** The kind and entries of a message, or null when it isn't laid out as one. Never throws. */
function decode(bytes) {
    const buffer = Buffer.from(bytes.buffer, bytes.byteOffset, bytes.length);
    if (buffer.length < 1) {
        return null;
    }
    const kind = buffer[0];
    if (kind !== exports.KIND_UPDATE && kind !== exports.KIND_HELLO && kind !== exports.KIND_ANSWER && kind !== exports.KIND_HELLO_BACK) {
        return null;
    }
    const entries = [];
    let at = 1;
    while (at < buffer.length) {
        if (at + 2 > buffer.length) {
            return null;
        }
        const nameLength = buffer.readUInt16BE(at);
        at += 2;
        if (nameLength === 0 || nameLength > MAX_NAME_BYTES || at + nameLength + 4 > buffer.length) {
            return null;
        }
        const name = buffer.toString('utf8', at, at + nameLength);
        at += nameLength;
        const dataLength = buffer.readUInt32BE(at);
        at += 4;
        if (at + dataLength > buffer.length) {
            return null;
        }
        entries.push({ name, data: new Uint8Array(buffer.subarray(at, at + dataLength)) });
        at += dataLength;
    }
    return { kind, entries };
}
exports.decode = decode;
//# sourceMappingURL=wire.js.map