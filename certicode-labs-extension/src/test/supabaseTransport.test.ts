import { SupabaseTransport } from '../teamSync/supabaseTransport';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

/** A socket that never connects: enough for the client to be built and torn down. */
class SilentSocket {
    public static readonly CONNECTING = 0;
    public static readonly OPEN = 1;
    public static readonly CLOSING = 2;
    public static readonly CLOSED = 3;
    public readyState = SilentSocket.CONNECTING;
    public binaryType = 'arraybuffer';
    public onopen: unknown = null;
    public onclose: unknown = null;
    public onerror: unknown = null;
    public onmessage: unknown = null;
    constructor(public readonly url: string) {}
    send(): void { /* never connected */ }
    close(): void { this.readyState = SilentSocket.CLOSED; }
}

test('a dead connection is noticed within seconds, not the library default of 25', () => {
    const transport = new SupabaseTransport('https://example.supabase.co', 'sb_publishable_test', 'topic', SilentSocket);
    transport.connect();
    try {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const client = (transport as any).client;
        // The library closes and reconnects when a heartbeat goes unanswered until the next one is due
        assert.strictEqual(client.heartbeatIntervalMs, 5000);
    } finally {
        // Left open, its reconnect timer would keep the test run from ending
        transport.close();
    }
});
