"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.SupabaseTransport = void 0;
const realtime_js_1 = require("@supabase/realtime-js");
/** The event name messages travel under on the channel. */
const EVENT = 'm';
/**
 * A Supabase Realtime Broadcast channel. Everything sent is already sealed with the team's key,
 * so the channel only has to carry strings between whoever is on it.
 */
class SupabaseTransport {
    constructor(supabaseUrl, publishableKey, topic, 
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    webSocket) {
        this.supabaseUrl = supabaseUrl;
        this.publishableKey = publishableKey;
        this.topic = topic;
        this.webSocket = webSocket;
        this.subscribed = false;
        this.closed = false;
        this.messageListeners = [];
        this.openListeners = [];
        this.statusListeners = [];
    }
    connect() {
        if (this.client || this.closed) {
            return;
        }
        this.emitStatus('connecting');
        const endpoint = this.supabaseUrl.replace(/^http/i, 'ws').replace(/\/+$/, '') + '/realtime/v1';
        this.client = new realtime_js_1.RealtimeClient(endpoint, { params: { apikey: this.publishableKey }, transport: this.webSocket });
        this.channel = this.client.channel(this.topic, { config: { broadcast: { self: false, ack: false } } });
        this.channel.on('broadcast', { event: EVENT }, message => {
            const data = message?.payload?.d;
            if (typeof data === 'string' && !this.closed) {
                for (const listener of this.messageListeners) {
                    listener(data);
                }
            }
        });
        // The callback runs again each time the channel is joined, after a reconnect too
        this.channel.subscribe((status, error) => {
            if (this.closed) {
                return;
            }
            if (status === 'SUBSCRIBED') {
                this.subscribed = true;
                this.emitStatus('open');
                for (const listener of this.openListeners) {
                    listener();
                }
            }
            else {
                this.subscribed = false;
                this.emitStatus(status === 'CLOSED' ? 'closed' : 'connecting', error?.message ?? status);
            }
        });
    }
    send(message) {
        if (!this.subscribed || !this.channel || this.closed) {
            return;
        }
        void this.channel.send({ type: 'broadcast', event: EVENT, payload: { d: message } }).catch(() => undefined);
    }
    onMessage(listener) { this.messageListeners.push(listener); }
    onOpen(listener) { this.openListeners.push(listener); }
    onStatus(listener) { this.statusListeners.push(listener); }
    close() {
        if (this.closed) {
            return;
        }
        this.closed = true;
        this.subscribed = false;
        const client = this.client;
        if (client) {
            void client.removeAllChannels().catch(() => undefined).then(() => client.disconnect());
        }
    }
    emitStatus(status, reason) {
        for (const listener of this.statusListeners) {
            listener(status, reason);
        }
    }
}
exports.SupabaseTransport = SupabaseTransport;
//# sourceMappingURL=supabaseTransport.js.map