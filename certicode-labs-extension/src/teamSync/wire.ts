/**
 * How a batch of edits is laid out before it is sealed and sent, or stored. Each shared file
 * is its own document, so every entry names its file:
 *
 *   [kind: 1 byte] then, per entry, [name length: 2 bytes][name, UTF-8][data length: 4 bytes][data]
 */

/** Edits to apply. */
export const KIND_UPDATE = 1;
/** "This is what I have": per file, a state vector. Sent on connecting. */
export const KIND_HELLO = 2;
/** Edits answering a hello: what the asker was missing. Applied like KIND_UPDATE. */
export const KIND_ANSWER = 3;
/** A hello sent in reply to a hello. Answered with edits, never with another hello. */
export const KIND_HELLO_BACK = 4;

export interface Entry {
    name: string;
    data: Uint8Array;
}

const MAX_NAME_BYTES = 1024;

export function encode(kind: number, entries: Entry[]): Uint8Array {
    const parts: Buffer[] = [Buffer.from([kind])];
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

/** The kind and entries of a message, or null when it isn't laid out as one. Never throws. */
export function decode(bytes: Uint8Array): { kind: number; entries: Entry[] } | null {
    const buffer = Buffer.from(bytes.buffer, bytes.byteOffset, bytes.length);
    if (buffer.length < 1) { return null; }

    const kind = buffer[0];
    if (kind !== KIND_UPDATE && kind !== KIND_HELLO && kind !== KIND_ANSWER && kind !== KIND_HELLO_BACK) { return null; }

    const entries: Entry[] = [];
    let at = 1;
    while (at < buffer.length) {
        if (at + 2 > buffer.length) { return null; }
        const nameLength = buffer.readUInt16BE(at);
        at += 2;
        if (nameLength === 0 || nameLength > MAX_NAME_BYTES || at + nameLength + 4 > buffer.length) { return null; }
        const name = buffer.toString('utf8', at, at + nameLength);
        at += nameLength;
        const dataLength = buffer.readUInt32BE(at);
        at += 4;
        if (at + dataLength > buffer.length) { return null; }
        entries.push({ name, data: new Uint8Array(buffer.subarray(at, at + dataLength)) });
        at += dataLength;
    }
    return { kind, entries };
}
