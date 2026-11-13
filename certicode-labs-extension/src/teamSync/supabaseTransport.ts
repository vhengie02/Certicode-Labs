import { RealtimeChannel, RealtimeClient } from '@supabase/realtime-js';
import { Transport } from './transport';

export type TransportStatus = 'connecting' | 'open' | 'closed';

/** The event name messages travel under on the channel. */
const EVENT = 'm';

/**
 * A Supabase Realtime Broadcast channel. Everything sent is already sealed with the team's key,
 * so the channel only has to carry strings between whoever is on it.
 */
export class SupabaseTransport implements Transport {
    private client?: RealtimeClient;
    private channel?: RealtimeChannel;
    private subscribed = false;
    private closed = false;
    private readonly messageListeners: ((message: string) => void)[] = [];
    private readonly openListeners: (() => void)[] = [];
    private readonly statusListeners: ((status: TransportStatus, reason?: string) => void)[] = [];

    constructor(
        private readonly supabaseUrl: string,
        private readonly publishableKey: string,
        private readonly topic: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        private readonly webSocket: any,
    ) {}

    connect(): void {
        if (this.client || this.closed) { return; }
        this.emitStatus('connecting');

        const endpoint = this.supabaseUrl.replace(/^http/i, 'ws').replace(/\/+$/, '') + '/realtime/v1';
        this.client = new RealtimeClient(endpoint, { params: { apikey: this.publishableKey }, transport: this.webSocket });
        this.channel = this.client.channel(this.topic, { config: { broadcast: { self: false, ack: false } } });

        this.channel.on('broadcast', { event: EVENT }, message => {
            const data = message?.payload?.d;
            if (typeof data === 'string' && !this.closed) {
                for (const listener of this.messageListeners) { listener(data); }
            }
        });

        // The callback runs again each time the channel is joined, after a reconnect too
        this.channel.subscribe((status, error) => {
            if (this.closed) { return; }
            if (status === 'SUBSCRIBED') {
                this.subscribed = true;
                this.emitStatus('open');
                for (const listener of this.openListeners) { listener(); }
            } else {
                this.subscribed = false;
                this.emitStatus(status === 'CLOSED' ? 'closed' : 'connecting', error?.message ?? status);
            }
        });
    }

    send(message: string): void {
        if (!this.subscribed || !this.channel || this.closed) { return; }
        void this.channel.send({ type: 'broadcast', event: EVENT, payload: { d: message } }).catch(() => undefined);
    }

    onMessage(listener: (message: string) => void): void { this.messageListeners.push(listener); }
    onOpen(listener: () => void): void { this.openListeners.push(listener); }
    onStatus(listener: (status: TransportStatus, reason?: string) => void): void { this.statusListeners.push(listener); }

    close(): void {
        if (this.closed) { return; }
        this.closed = true;
        this.subscribed = false;
        const client = this.client;
        if (client) {
            void client.removeAllChannels().catch(() => undefined).then(() => client.disconnect());
        }
    }

    private emitStatus(status: TransportStatus, reason?: string): void {
        for (const listener of this.statusListeners) { listener(status, reason); }
    }
}
