import { createHash } from 'crypto';
import * as Y from 'yjs';
import { isShareableName } from './names';
import { open, seal } from './seal';
import { EditorPort, LOCAL, REMOTE, TextBinding } from './textBinding';
import { DocStore, Transport } from './transport';
import { decode, encode, Entry, KIND_ANSWER, KIND_HELLO, KIND_HELLO_BACK, KIND_UPDATE } from './wire';

export interface TeamDocOptions {
    /** The team attempt's 32-byte key. Everything sent is sealed with it. */
    key: Buffer;
    transport: Transport;
    store: DocStore;
    /** At most one message of edits goes out per this many milliseconds. */
    minSendIntervalMs: number;
    /** Edits are written to the store at most this often. */
    storeIntervalMs?: number;
    /** After this many stored batches the document is folded into one. */
    compactAfter?: number;
    /** A hello is answered after a random wait of up to this long, so answers don't all land at once. */
    helloJitterMs?: number;
    /**
     * Ids this student's editor used before (an earlier window of the same attempt). Characters
     * they wrote count as this student's in lineStats().
     */
    ownClientIds?: number[];
    /**
     * How often to check with the teammates and the store that nothing was missed: a message
     * the channel lost, or one too big to go over it.
     */
    repairIntervalMs?: number;
    /** Longest message sent over the channel; bigger edits go through the store only. */
    maxMessageChars?: number;
}

/** Lines of one file credited to this student, by who typed the characters on them. */
export interface LineStats {
    /** Lines mostly written by this student. */
    added: number;
    /** Lines this student changed that are mostly someone else's or the starter code's. */
    modified: number;
}

interface SharedFile {
    /** What the file holds. When an editor is bound, exactly what that editor shows. */
    doc: Y.Doc;
    binding?: TextBinding;
    exists: boolean;
}

const toLf = (text: string) => text.replace(/\r\n?/g, '\n');
const base64 = (bytes: Uint8Array) => Buffer.from(bytes.buffer, bytes.byteOffset, bytes.length).toString('base64');

/**
 * The files one team attempt types in together. Each file is its own shared document. Edits
 * made here are sent to the teammates over the transport, sealed, and written to the store
 * for whoever joins later; edits from teammates and from the store are applied here.
 */
export class TeamDoc {
    private readonly files = new Map<string, SharedFile>();
    /** Local edits not sent yet, and not stored yet, per file. */
    private readonly outbox = new Map<string, Uint8Array[]>();
    private readonly unsaved = new Map<string, Uint8Array[]>();
    private readonly addedListeners: ((name: string) => void)[] = [];
    private readonly removedListeners: ((name: string) => void)[] = [];

    private readonly storeIntervalMs: number;
    private readonly compactAfter: number;
    private readonly helloJitterMs: number;
    private readonly repairIntervalMs: number;
    private readonly maxMessageChars: number;

    private sendTimer?: ReturnType<typeof setTimeout>;
    private storeTimer?: ReturnType<typeof setTimeout>;
    private repairTimer?: ReturnType<typeof setInterval>;
    /** Hellos answered lately, so the same one arriving from several teammates is answered once. */
    private readonly answered = new Map<string, number>();
    /** Goes up with every change to any file, from here or from a teammate. */
    private version = 0;
    private lastSentAt = 0;
    private lastLoadedId = 0;
    private storedSinceCompaction = 0;
    private storing: Promise<void> = Promise.resolve();
    private stopped = false;

    constructor(private readonly options: TeamDocOptions) {
        this.storeIntervalMs = options.storeIntervalMs ?? 5000;
        this.compactAfter = options.compactAfter ?? 200;
        this.helloJitterMs = options.helloJitterMs ?? 300;
        this.repairIntervalMs = options.repairIntervalMs ?? 60000;
        // Supabase refuses messages over 256 KB on the Free plan; the sealed form is base64
        this.maxMessageChars = options.maxMessageChars ?? 200_000;
    }

    /**
     * Load the stored document and join the teammates. `starter` goes in only if nothing is
     * stored yet, and only once however many teammates start together: the store keeps the
     * first seed and everyone loads that one.
     */
    public async start(starter: { name: string; content: string }[]): Promise<void> {
        this.options.transport.onMessage(message => this.handleMessage(message));
        this.options.transport.onOpen(() => this.sendHello(KIND_HELLO));

        let stored = await this.options.store.load(0);
        if (stored.updates.length === 0 && starter.length > 0) {
            const seed = starter.map(file => {
                const doc = new Y.Doc();
                doc.getText('text').insert(0, toLf(file.content));
                doc.getMap('meta').set('created', true);
                const data = Y.encodeStateAsUpdate(doc);
                doc.destroy();
                return { name: file.name, data };
            });
            await this.options.store.append(base64(encode(KIND_UPDATE, seed)), { seed: true });
            stored = await this.options.store.load(0);
            if (stored.updates.length === 0) {
                throw new Error('The team document could not be saved.');
            }
        }

        this.applyStored(stored);
        if (!this.stopped) {
            this.options.transport.connect();
            // Now and then: say what we have, so a teammate answers what a lost message left
            // out, and pick up from the store what was too big for the channel
            this.repairTimer = setInterval(() => {
                this.sendHello(KIND_HELLO_BACK);
                void this.pullStored();
            }, this.repairIntervalMs);
            // Never the reason a process stays up
            (this.repairTimer as { unref?: () => void }).unref?.();
        }
    }

    /** Names of the files the team has, without the ones a teammate deleted. */
    public fileNames(): string[] {
        return [...this.files].filter(([, file]) => file.exists).map(([name]) => name);
    }

    /** The editor ids this student's edits carry in this window. Remember them for lineStats() after a reload. */
    public clientIds(): number[] {
        return [...new Set([...this.files.values()].map(file => file.doc.clientID))];
    }

    /**
     * Which lines of a file this student wrote. The merge library keeps, for every character,
     * the editor that typed it, so a teammate's lines are never credited here. Starter code
     * was put in by none of the teammates' editors, so it counts for nobody.
     */
    public lineStats(name: string): LineStats {
        const file = this.files.get(name);
        const stats = { added: 0, modified: 0 };
        if (!file) { return stats; }

        const own = new Set([...(this.options.ownClientIds ?? []), ...this.clientIds()]);
        let mine = 0;
        let total = 0;
        const endLine = () => {
            if (mine > 0) {
                if (mine * 2 > total) { stats.added++; } else { stats.modified++; }
            }
            mine = 0;
            total = 0;
        };

        // The library's internal list of insertions, in document order
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        for (let item = (file.doc.getText('text') as any)._start; item; item = item.right) {
            const text: unknown = item.deleted ? undefined : item.content?.str;
            if (typeof text !== 'string') { continue; }
            const isMine = own.has(item.id.client);
            for (const character of text) {
                if (character === '\n') {
                    endLine();
                } else if (character.trim() !== '') {
                    total++;
                    if (isMine) { mine++; }
                }
            }
        }
        endLine();
        return stats;
    }

    public text(name: string): string | undefined {
        return this.files.get(name)?.doc.getText('text').toString();
    }

    public onFileAdded(listener: (name: string) => void): void { this.addedListeners.push(listener); }
    public onFileRemoved(listener: (name: string) => void): void { this.removedListeners.push(listener); }

    /** Tie a file to the editor showing it. The editor must show the file's current text. */
    public bind(name: string, editor: EditorPort): TextBinding {
        const file = this.file(name);
        this.unbind(name);
        file.binding = new TextBinding(file.doc, editor);
        return file.binding;
    }

    public unbind(name: string): void {
        const file = this.files.get(name);
        if (!file?.binding) { return; }
        // Keep what the editor had not taken yet
        const waiting = file.binding.fullState();
        file.binding.dispose();
        file.binding = undefined;
        Y.applyUpdate(file.doc, waiting, REMOTE);
    }

    /** The user created a file. */
    public createFile(name: string, content: string): void {
        if (this.stopped) { return; }
        const file = this.file(name);
        file.doc.transact(() => {
            const text = file.doc.getText('text');
            text.delete(0, text.length);
            text.insert(0, toLf(content));
            file.doc.getMap('meta').set('created', true);
            file.doc.getMap('meta').set('deleted', false);
        }, LOCAL);
    }

    /** The user deleted a file. */
    public deleteFile(name: string): void {
        const file = this.files.get(name);
        if (this.stopped || !file) { return; }
        file.doc.transact(() => file.doc.getMap('meta').set('deleted', true), LOCAL);
    }

    /** Resolves once everything typed here has been sent and stored. */
    public async flush(): Promise<void> {
        if (this.sendTimer) {
            clearTimeout(this.sendTimer);
            this.sendTimer = undefined;
        }
        this.sendNow();
        await this.storeNow();
    }

    public stop(): void {
        if (this.stopped) { return; }
        // What was typed but is still waiting out the send interval goes now
        if (this.sendTimer) { clearTimeout(this.sendTimer); }
        this.sendTimer = undefined;
        this.sendNow();

        this.stopped = true;
        if (this.storeTimer) { clearTimeout(this.storeTimer); }
        if (this.repairTimer) { clearInterval(this.repairTimer); }
        this.storeTimer = this.repairTimer = undefined;
        this.options.transport.close();
        for (const file of this.files.values()) {
            file.binding?.dispose();
            file.binding = undefined;
        }
        // Edits typed since the last write still reach the store, for whoever comes next.
        // Nothing reaches an editor any more, so this can't be mistaken for typing.
        void this.storeNow();
    }

    private file(name: string): SharedFile {
        let file = this.files.get(name);
        if (!file) {
            const created: SharedFile = { doc: new Y.Doc(), exists: false };
            created.doc.on('update', (update: Uint8Array, origin: unknown) => {
                this.version++;
                if (origin !== REMOTE && !this.stopped) {
                    this.queue(this.outbox, name, update);
                    this.queue(this.unsaved, name, update);
                    this.scheduleSend();
                    this.scheduleStore();
                }
                this.refreshExistence(name, created);
            });
            this.files.set(name, created);
            file = created;
        }
        return file;
    }

    private refreshExistence(name: string, file: SharedFile): void {
        const meta = file.doc.getMap('meta');
        const exists = meta.get('created') === true && meta.get('deleted') !== true;
        if (exists === file.exists) { return; }
        file.exists = exists;
        for (const listener of exists ? this.addedListeners : this.removedListeners) { listener(name); }
    }

    private queue(box: Map<string, Uint8Array[]>, name: string, update: Uint8Array): void {
        const updates = box.get(name);
        if (updates) { updates.push(update); } else { box.set(name, [update]); }
    }

    /** Empties a box into one merged entry per file. */
    private drain(box: Map<string, Uint8Array[]>): Entry[] {
        const entries = [...box].map(([name, updates]) => ({ name, data: updates.length === 1 ? updates[0] : Y.mergeUpdates(updates) }));
        box.clear();
        return entries;
    }

    private fullState(file: SharedFile): Uint8Array {
        return file.binding ? file.binding.fullState() : Y.encodeStateAsUpdate(file.doc);
    }

    /** Edits from a teammate or the store. Anything that isn't a well-formed edit is dropped. */
    private applyEntries(entries: Entry[]): void {
        for (const entry of entries) {
            // A name that could point outside the lab, or isn't code, is never kept
            if (!isShareableName(entry.name)) { continue; }
            try {
                const probe = new Y.Doc();
                Y.applyUpdate(probe, entry.data);
                probe.destroy();
            } catch {
                continue;
            }

            const file = this.file(entry.name);
            if (file.binding) {
                file.binding.receive(entry.data);
            } else {
                Y.applyUpdate(file.doc, entry.data, REMOTE);
            }
        }
    }

    private applyStored(stored: { updates: { id: number; payload: string }[]; lastUpdateId: number }): void {
        for (const update of stored.updates) {
            const decoded = /^[A-Za-z0-9+/]+={0,2}$/.test(update.payload) ? decode(new Uint8Array(Buffer.from(update.payload, 'base64'))) : null;
            if (decoded) { this.applyEntries(decoded.entries); }
        }
        this.lastLoadedId = Math.max(this.lastLoadedId, stored.lastUpdateId);
    }

    private handleMessage(message: string): void {
        if (this.stopped) { return; }
        // Anything can arrive on the channel; nothing in it may break the editor
        try {
            const plain = open(this.options.key, message);
            const decoded = plain && decode(plain);
            if (!decoded) { return; }

            if (decoded.kind === KIND_UPDATE || decoded.kind === KIND_ANSWER) {
                this.applyEntries(decoded.entries);
                return;
            }

            // A teammate says what they have: on connecting (HELLO), in reply to that, or now and
            // then (HELLO_BACK). Answer only for files whose content differs, after a short random
            // wait so teammates' answers don't all land at once. A first HELLO also gets ours, so
            // they send what we lack (edits they made while offline). A HELLO_BACK gets no hello,
            // so this never loops.
            // The same hello is answered once, unless our own files changed since
            const helloId = createHash('sha256').update(plain).digest('hex') + ':' + this.version;
            const now = Date.now();
            for (const [id, at] of this.answered) { if (now - at > 3000) { this.answered.delete(id); } }
            if (this.answered.has(helloId)) { return; }
            this.answered.set(helloId, now);

            const wantsOurs = decoded.kind === KIND_HELLO;
            setTimeout(() => {
                if (this.stopped) { return; }
                try {
                    this.answer(decoded.entries);
                } catch {
                    // A file we can't diff; the next repair tries again
                }
                if (wantsOurs) { this.sendHello(KIND_HELLO_BACK); }
            }, Math.floor(Math.random() * this.helloJitterMs));
        } catch {
            // Dropped
        }
    }

    /** Send a teammate what they lack, given what their hello says they have. */
    private answer(entries: Entry[]): void {
        const theirs = new Map<string, { hash: Buffer; vector: Uint8Array }>();
        for (const entry of entries) {
            if (entry.data.length >= 32) {
                theirs.set(entry.name, { hash: Buffer.from(entry.data.subarray(0, 32)), vector: entry.data.subarray(32) });
            }
        }

        const missing: Entry[] = [];
        for (const [name, file] of this.files) {
            const { full, hash } = this.stateOf(file);
            const known = theirs.get(name);
            if (known && known.hash.equals(hash)) { continue; }
            let data = full;
            try {
                if (known) { data = Y.diffUpdate(full, known.vector); }
            } catch {
                data = full;
            }
            // Two bytes is an update with nothing in it: they have all we have, and will send
            // us what we lack when they answer our hello
            if (data.length > 2) { missing.push({ name, data }); }
        }
        if (missing.length > 0) { this.send(KIND_ANSWER, missing); }
    }

    /**
     * A file's whole state, and a fingerprint of its content: what was inserted (the state
     * vector), the text as it stands (so deletions count) and whether it exists. Two teammates
     * with the same fingerprint have nothing to send each other for that file.
     */
    private stateOf(file: SharedFile): { full: Uint8Array; hash: Buffer } {
        const full = this.fullState(file);
        const doc = new Y.Doc();
        Y.applyUpdate(doc, full);
        const hash = createHash('sha256')
            .update(Y.encodeStateVector(doc))
            .update('\0')
            .update(doc.getText('text').toString())
            .update('\0')
            .update(JSON.stringify(doc.getMap('meta').toJSON()))
            .digest();
        doc.destroy();
        return { full, hash };
    }

    /** Per file: [fingerprint, 32 bytes][state vector]. */
    private sendHello(kind: number): void {
        if (this.stopped) { return; }
        const have = [...this.files].map(([name, file]) => {
            const { full, hash } = this.stateOf(file);
            return { name, data: new Uint8Array(Buffer.concat([hash, Buffer.from(Y.encodeStateVectorFromUpdate(full))])) };
        });
        this.send(kind, have);
    }

    private send(kind: number, entries: Entry[]): void {
        const sealed = seal(this.options.key, encode(kind, entries));
        if (sealed.length > this.maxMessageChars) {
            // Too big for the channel: teammates get it from the store at the next repair
            if (kind === KIND_UPDATE) { void this.storeNow(); }
            return;
        }
        this.options.transport.send(sealed);
    }

    /** Edits teammates stored since we last looked. */
    private async pullStored(): Promise<void> {
        if (this.stopped) { return; }
        try {
            this.applyStored(await this.options.store.load(this.lastLoadedId));
        } catch {
            // The store is unreachable; the next repair tries again
        }
    }

    private scheduleSend(): void {
        if (this.sendTimer || this.stopped) { return; }
        // The first edit after a quiet spell goes at once; later ones wait out the interval
        const wait = Math.max(0, this.lastSentAt + this.options.minSendIntervalMs - Date.now());
        this.sendTimer = setTimeout(() => {
            this.sendTimer = undefined;
            this.sendNow();
        }, wait);
    }

    private sendNow(): void {
        if (this.stopped || this.outbox.size === 0) { return; }
        this.lastSentAt = Date.now();
        this.send(KIND_UPDATE, this.drain(this.outbox));
    }

    private scheduleStore(): void {
        if (this.storeTimer || this.stopped) { return; }
        this.storeTimer = setTimeout(() => { void this.storeNow(); }, this.storeIntervalMs);
    }

    /** Writes pending edits to the store, one write at a time. */
    private storeNow(): Promise<void> {
        if (this.storeTimer) {
            clearTimeout(this.storeTimer);
            this.storeTimer = undefined;
        }

        this.storing = this.storing.then(async () => {
            if (this.unsaved.size === 0) { return; }
            const batch = this.drain(this.unsaved);
            const keep = () => { for (const entry of batch) { this.queue(this.unsaved, entry.name, entry.data); } };

            let result;
            try {
                result = await this.options.store.append(base64(encode(KIND_UPDATE, batch)));
            } catch {
                result = { error: 'failed' as const };
            }

            if ('id' in result) {
                this.storedSinceCompaction++;
                if (this.storedSinceCompaction >= this.compactAfter) { await this.compact(); }
            } else if (result.error === 'compaction_needed') {
                keep();
                await this.compact();
            } else {
                // Not stored: keep it and try again later
                keep();
                if (result.error === 'failed') { this.scheduleStore(); }
            }
        }).catch(() => undefined);

        return this.storing;
    }

    /**
     * Fold the stored document into one batch. Everything stored so far is loaded first, so
     * the batch really does hold all it replaces.
     */
    private async compact(): Promise<void> {
        try {
            this.applyStored(await this.options.store.load(this.lastLoadedId));
            if (this.lastLoadedId === 0) { return; }

            // The whole document, and the edits it holds that were waiting to be stored, taken at
            // the same moment: edits typed while the store answers stay waiting, for the next write
            const whole = [...this.files].map(([name, file]) => ({ name, data: this.fullState(file) }));
            const folded = this.drain(this.unsaved);
            let result;
            try {
                result = await this.options.store.append(base64(encode(KIND_UPDATE, whole)), { replacesThrough: this.lastLoadedId });
            } catch {
                result = { error: 'failed' as const };
            }
            if ('id' in result) {
                this.storedSinceCompaction = 0;
            } else {
                for (const entry of folded) { this.queue(this.unsaved, entry.name, entry.data); }
            }
            if (this.unsaved.size > 0) { this.scheduleStore(); }
        } catch {
            // The store is unreachable; the next write tries again
        }
    }
}
