import { EditorPort, LocalChange, TextBinding } from '../teamSync/textBinding';
import { AppendResult, DocStore, Transport } from '../teamSync/transport';

export const sleep = (ms: number) => new Promise<void>(resolve => setTimeout(resolve, ms));

/** Waits until `check` holds, or gives up after about two seconds. */
export async function until(check: () => boolean): Promise<void> {
    for (let i = 0; i < 400 && !check(); i++) { await sleep(5); }
}

/** An editor whose edits land at once. For tests about everything but edit timing. */
export class InstantEditor implements EditorPort {
    public text = '';
    public binding?: TextBinding;

    getText() { return this.text; }

    async replace(offset: number, length: number, text: string): Promise<boolean> {
        this.apply({ offset, length, text });
        return true;
    }

    /** The user types or deletes. */
    type(offset: number, length: number, text: string) {
        this.apply({ offset, length, text });
    }

    private apply(change: LocalChange) {
        this.text = this.text.slice(0, change.offset) + change.text + this.text.slice(change.offset + change.length);
        this.binding?.handleEditorChange([change]);
    }
}

/** A channel in memory: what one transport sends, the others on it receive. */
export class Hub {
    public readonly transports: HubTransport[] = [];
    /** Every message that crossed the channel, as sent. */
    public readonly traffic: string[] = [];
    public delayMs = 0;

    join(): HubTransport {
        const transport = new HubTransport(this);
        this.transports.push(transport);
        return transport;
    }

    /** Someone who is not a teammate puts something on the channel. */
    inject(message: string) {
        for (const transport of this.transports) { transport.deliver(message); }
    }
}

export class HubTransport implements Transport {
    public sent = 0;
    private online = false;
    private closed = false;
    private messageListeners: ((message: string) => void)[] = [];
    private openListeners: (() => void)[] = [];

    constructor(private hub: Hub) {}

    connect() { this.goOnline(); }

    send(message: string) {
        if (!this.online || this.closed) { return; }
        this.sent++;
        this.hub.traffic.push(message);
        for (const other of this.hub.transports) {
            if (other !== this) { setTimeout(() => other.deliver(message), this.hub.delayMs); }
        }
    }

    onMessage(listener: (message: string) => void) { this.messageListeners.push(listener); }
    onOpen(listener: () => void) { this.openListeners.push(listener); }
    close() { this.closed = true; this.online = false; }

    deliver(message: string) {
        if (!this.online || this.closed) { return; }
        for (const listener of this.messageListeners) { listener(message); }
    }

    goOffline() { this.online = false; }

    goOnline() {
        if (this.closed || this.online) { return; }
        this.online = true;
        for (const listener of this.openListeners) { listener(); }
    }
}

/** Keeps edits the way the server does (see TeamDocTest on the Laravel side). */
export class FakeStore implements DocStore {
    public rows: { id: number; payload: string }[] = [];
    public maxRows = 400;
    public failing = false;
    public appends = 0;
    private nextId = 1;

    async load(after: number) {
        await sleep(1);
        const updates = this.rows.filter(row => row.id > after);
        return { updates, lastUpdateId: this.rows.length ? this.rows[this.rows.length - 1].id : 0 };
    }

    async append(payload: string, options: { seed?: boolean; replacesThrough?: number } = {}): Promise<AppendResult> {
        await sleep(1);
        if (this.failing) { return { error: 'failed' }; }
        const lastUpdateId = this.rows.length ? this.rows[this.rows.length - 1].id : 0;
        if (options.seed && this.rows.length > 0) { return { error: 'already_seeded', lastUpdateId }; }
        if (!options.replacesThrough && this.rows.length >= this.maxRows) { return { error: 'compaction_needed', lastUpdateId }; }

        this.appends++;
        const row = { id: this.nextId++, payload };
        this.rows.push(row);
        if (options.replacesThrough) {
            this.rows = this.rows.filter(other => other.id > options.replacesThrough! || other.id === row.id);
        }
        return { id: row.id };
    }
}
