"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || function (mod) {
    if (mod && mod.__esModule) return mod;
    var result = {};
    if (mod != null) for (var k in mod) if (k !== "default" && Object.prototype.hasOwnProperty.call(mod, k)) __createBinding(result, mod, k);
    __setModuleDefault(result, mod);
    return result;
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.TeamDoc = void 0;
const Y = __importStar(require("yjs"));
const seal_1 = require("./seal");
const textBinding_1 = require("./textBinding");
const wire_1 = require("./wire");
const toLf = (text) => text.replace(/\r\n?/g, '\n');
const base64 = (bytes) => Buffer.from(bytes.buffer, bytes.byteOffset, bytes.length).toString('base64');
/**
 * The files one team attempt types in together. Each file is its own shared document. Edits
 * made here are sent to the teammates over the transport, sealed, and written to the store
 * for whoever joins later; edits from teammates and from the store are applied here.
 */
class TeamDoc {
    constructor(options) {
        this.options = options;
        this.files = new Map();
        /** Local edits not sent yet, and not stored yet, per file. */
        this.outbox = new Map();
        this.unsaved = new Map();
        this.addedListeners = [];
        this.removedListeners = [];
        this.lastSentAt = 0;
        this.lastLoadedId = 0;
        this.storedSinceCompaction = 0;
        this.storing = Promise.resolve();
        this.stopped = false;
        this.storeIntervalMs = options.storeIntervalMs ?? 5000;
        this.compactAfter = options.compactAfter ?? 200;
        this.helloJitterMs = options.helloJitterMs ?? 300;
    }
    /**
     * Load the stored document and join the teammates. `starter` goes in only if nothing is
     * stored yet, and only once however many teammates start together: the store keeps the
     * first seed and everyone loads that one.
     */
    async start(starter) {
        this.options.transport.onMessage(message => this.handleMessage(message));
        this.options.transport.onOpen(() => this.sendHello(wire_1.KIND_HELLO));
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
            await this.options.store.append(base64((0, wire_1.encode)(wire_1.KIND_UPDATE, seed)), { seed: true });
            stored = await this.options.store.load(0);
            if (stored.updates.length === 0) {
                throw new Error('The team document could not be saved.');
            }
        }
        this.applyStored(stored);
        if (!this.stopped) {
            this.options.transport.connect();
        }
    }
    /** Names of the files the team has, without the ones a teammate deleted. */
    fileNames() {
        return [...this.files].filter(([, file]) => file.exists).map(([name]) => name);
    }
    /** The editor ids this student's edits carry in this window. Remember them for lineStats() after a reload. */
    clientIds() {
        return [...new Set([...this.files.values()].map(file => file.doc.clientID))];
    }
    /**
     * Which lines of a file this student wrote. The merge library keeps, for every character,
     * the editor that typed it, so a teammate's lines are never credited here. Starter code
     * was put in by none of the teammates' editors, so it counts for nobody.
     */
    lineStats(name) {
        const file = this.files.get(name);
        const stats = { added: 0, modified: 0 };
        if (!file) {
            return stats;
        }
        const own = new Set([...(this.options.ownClientIds ?? []), ...this.clientIds()]);
        let mine = 0;
        let total = 0;
        const endLine = () => {
            if (mine > 0) {
                if (mine * 2 > total) {
                    stats.added++;
                }
                else {
                    stats.modified++;
                }
            }
            mine = 0;
            total = 0;
        };
        // The library's internal list of insertions, in document order
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        for (let item = file.doc.getText('text')._start; item; item = item.right) {
            const text = item.deleted ? undefined : item.content?.str;
            if (typeof text !== 'string') {
                continue;
            }
            const isMine = own.has(item.id.client);
            for (const character of text) {
                if (character === '\n') {
                    endLine();
                }
                else if (character.trim() !== '') {
                    total++;
                    if (isMine) {
                        mine++;
                    }
                }
            }
        }
        endLine();
        return stats;
    }
    text(name) {
        return this.files.get(name)?.doc.getText('text').toString();
    }
    onFileAdded(listener) { this.addedListeners.push(listener); }
    onFileRemoved(listener) { this.removedListeners.push(listener); }
    /** Tie a file to the editor showing it. The editor must show the file's current text. */
    bind(name, editor) {
        const file = this.file(name);
        this.unbind(name);
        file.binding = new textBinding_1.TextBinding(file.doc, editor);
        return file.binding;
    }
    unbind(name) {
        const file = this.files.get(name);
        if (!file?.binding) {
            return;
        }
        // Keep what the editor had not taken yet
        const waiting = file.binding.fullState();
        file.binding.dispose();
        file.binding = undefined;
        Y.applyUpdate(file.doc, waiting, textBinding_1.REMOTE);
    }
    /** The user created a file. */
    createFile(name, content) {
        if (this.stopped) {
            return;
        }
        const file = this.file(name);
        file.doc.transact(() => {
            const text = file.doc.getText('text');
            text.delete(0, text.length);
            text.insert(0, toLf(content));
            file.doc.getMap('meta').set('created', true);
            file.doc.getMap('meta').set('deleted', false);
        }, textBinding_1.LOCAL);
    }
    /** The user deleted a file. */
    deleteFile(name) {
        const file = this.files.get(name);
        if (this.stopped || !file) {
            return;
        }
        file.doc.transact(() => file.doc.getMap('meta').set('deleted', true), textBinding_1.LOCAL);
    }
    /** Resolves once everything typed here has been sent and stored. */
    async flush() {
        if (this.sendTimer) {
            clearTimeout(this.sendTimer);
            this.sendTimer = undefined;
        }
        this.sendNow();
        await this.storeNow();
    }
    stop() {
        this.stopped = true;
        if (this.sendTimer) {
            clearTimeout(this.sendTimer);
        }
        if (this.storeTimer) {
            clearTimeout(this.storeTimer);
        }
        this.sendTimer = this.storeTimer = undefined;
        this.options.transport.close();
        for (const file of this.files.values()) {
            file.binding?.dispose();
            file.binding = undefined;
        }
        // Edits typed since the last write still reach the store, for whoever comes next.
        // Nothing reaches an editor any more, so this can't be mistaken for typing.
        void this.storeNow();
    }
    file(name) {
        let file = this.files.get(name);
        if (!file) {
            const created = { doc: new Y.Doc(), exists: false };
            created.doc.on('update', (update, origin) => {
                if (origin !== textBinding_1.REMOTE && !this.stopped) {
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
    refreshExistence(name, file) {
        const meta = file.doc.getMap('meta');
        const exists = meta.get('created') === true && meta.get('deleted') !== true;
        if (exists === file.exists) {
            return;
        }
        file.exists = exists;
        for (const listener of exists ? this.addedListeners : this.removedListeners) {
            listener(name);
        }
    }
    queue(box, name, update) {
        const updates = box.get(name);
        if (updates) {
            updates.push(update);
        }
        else {
            box.set(name, [update]);
        }
    }
    /** Empties a box into one merged entry per file. */
    drain(box) {
        const entries = [...box].map(([name, updates]) => ({ name, data: updates.length === 1 ? updates[0] : Y.mergeUpdates(updates) }));
        box.clear();
        return entries;
    }
    fullState(file) {
        return file.binding ? file.binding.fullState() : Y.encodeStateAsUpdate(file.doc);
    }
    /** Edits from a teammate or the store. Anything that isn't a well-formed edit is dropped. */
    applyEntries(entries) {
        for (const entry of entries) {
            try {
                const probe = new Y.Doc();
                Y.applyUpdate(probe, entry.data);
                probe.destroy();
            }
            catch {
                continue;
            }
            const file = this.file(entry.name);
            if (file.binding) {
                file.binding.receive(entry.data);
            }
            else {
                Y.applyUpdate(file.doc, entry.data, textBinding_1.REMOTE);
            }
        }
    }
    applyStored(stored) {
        for (const update of stored.updates) {
            const decoded = /^[A-Za-z0-9+/]+={0,2}$/.test(update.payload) ? (0, wire_1.decode)(new Uint8Array(Buffer.from(update.payload, 'base64'))) : null;
            if (decoded) {
                this.applyEntries(decoded.entries);
            }
        }
        this.lastLoadedId = Math.max(this.lastLoadedId, stored.lastUpdateId);
    }
    handleMessage(message) {
        if (this.stopped) {
            return;
        }
        const plain = (0, seal_1.open)(this.options.key, message);
        const decoded = plain && (0, wire_1.decode)(plain);
        if (!decoded) {
            return;
        }
        if (decoded.kind === wire_1.KIND_UPDATE || decoded.kind === wire_1.KIND_ANSWER) {
            this.applyEntries(decoded.entries);
            return;
        }
        // A teammate (re)connected and says what they have. Send what they lack, after a short
        // random wait so every teammate's answer doesn't land at once. On a first hello, also
        // say what we have, so they send back what we lack (edits they made while offline).
        const wantsOurs = decoded.kind === wire_1.KIND_HELLO;
        setTimeout(() => {
            if (this.stopped) {
                return;
            }
            const theirs = new Map(decoded.entries.map(entry => [entry.name, entry.data]));
            const missing = [];
            for (const [name, file] of this.files) {
                const full = this.fullState(file);
                const known = theirs.get(name);
                let data = full;
                try {
                    if (known) {
                        data = Y.diffUpdate(full, known);
                    }
                }
                catch {
                    data = full;
                }
                // An update with no content is two zero bytes
                if (data.length > 2) {
                    missing.push({ name, data });
                }
            }
            if (missing.length > 0) {
                this.send(wire_1.KIND_ANSWER, missing);
            }
            if (wantsOurs) {
                this.sendHello(wire_1.KIND_HELLO_BACK);
            }
        }, Math.floor(Math.random() * this.helloJitterMs));
    }
    sendHello(kind) {
        if (this.stopped) {
            return;
        }
        const have = [...this.files].map(([name, file]) => ({ name, data: Y.encodeStateVectorFromUpdate(this.fullState(file)) }));
        this.send(kind, have);
    }
    send(kind, entries) {
        this.options.transport.send((0, seal_1.seal)(this.options.key, (0, wire_1.encode)(kind, entries)));
    }
    scheduleSend() {
        if (this.sendTimer || this.stopped) {
            return;
        }
        // The first edit after a quiet spell goes at once; later ones wait out the interval
        const wait = Math.max(0, this.lastSentAt + this.options.minSendIntervalMs - Date.now());
        this.sendTimer = setTimeout(() => {
            this.sendTimer = undefined;
            this.sendNow();
        }, wait);
    }
    sendNow() {
        if (this.stopped || this.outbox.size === 0) {
            return;
        }
        this.lastSentAt = Date.now();
        this.send(wire_1.KIND_UPDATE, this.drain(this.outbox));
    }
    scheduleStore() {
        if (this.storeTimer || this.stopped) {
            return;
        }
        this.storeTimer = setTimeout(() => { void this.storeNow(); }, this.storeIntervalMs);
    }
    /** Writes pending edits to the store, one write at a time. */
    storeNow() {
        if (this.storeTimer) {
            clearTimeout(this.storeTimer);
            this.storeTimer = undefined;
        }
        this.storing = this.storing.then(async () => {
            if (this.unsaved.size === 0) {
                return;
            }
            const batch = this.drain(this.unsaved);
            const keep = () => { for (const entry of batch) {
                this.queue(this.unsaved, entry.name, entry.data);
            } };
            let result;
            try {
                result = await this.options.store.append(base64((0, wire_1.encode)(wire_1.KIND_UPDATE, batch)));
            }
            catch {
                result = { error: 'failed' };
            }
            if ('id' in result) {
                this.storedSinceCompaction++;
                if (this.storedSinceCompaction >= this.compactAfter) {
                    await this.compact();
                }
            }
            else if (result.error === 'compaction_needed') {
                keep();
                await this.compact();
            }
            else {
                // Not stored: keep it and try again later
                keep();
                if (result.error === 'failed') {
                    this.scheduleStore();
                }
            }
        }).catch(() => undefined);
        return this.storing;
    }
    /**
     * Fold the stored document into one batch. Everything stored so far is loaded first, so
     * the batch really does hold all it replaces.
     */
    async compact() {
        try {
            this.applyStored(await this.options.store.load(this.lastLoadedId));
            if (this.lastLoadedId === 0) {
                return;
            }
            const whole = [...this.files].map(([name, file]) => ({ name, data: this.fullState(file) }));
            const result = await this.options.store.append(base64((0, wire_1.encode)(wire_1.KIND_UPDATE, whole)), { replacesThrough: this.lastLoadedId });
            if ('id' in result) {
                // The batch held the edits that were still waiting to be stored
                this.unsaved.clear();
                this.storedSinceCompaction = 0;
            }
        }
        catch {
            // The store is unreachable; the next write tries again
        }
    }
}
exports.TeamDoc = TeamDoc;
//# sourceMappingURL=teamDoc.js.map