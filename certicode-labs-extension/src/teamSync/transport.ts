/** Carries sealed messages between teammates. In production: a Supabase Realtime channel. */
export interface Transport {
    /** Start connecting. Listeners are attached before this is called. */
    connect(): void;
    /** Send to every teammate now on the channel. Dropped when not connected. */
    send(message: string): void;
    onMessage(listener: (message: string) => void): void;
    /** Fires each time the channel becomes usable: on connecting and after every reconnect. */
    onOpen(listener: () => void): void;
    close(): void;
}

export type AppendResult =
    | { id: number }
    | { error: 'already_seeded' | 'compaction_needed' | 'team_sync_off' | 'failed'; lastUpdateId?: number };

/** Where the team's edits are kept for teammates who join later. In production: Laravel. */
export interface DocStore {
    /** Stored edits with an id above `after`, oldest first. */
    load(after: number): Promise<{ updates: { id: number; payload: string }[]; lastUpdateId: number }>;
    /**
     * Keep a batch of edits. `seed`: only if nothing is stored yet. `replacesThrough`: this
     * batch holds everything up to that id, which can go.
     */
    append(payload: string, options?: { seed?: boolean; replacesThrough?: number }): Promise<AppendResult>;
}
