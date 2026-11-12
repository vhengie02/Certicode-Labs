import * as Y from 'yjs';

/** The editor the binding writes into. In VS Code: one text document. */
export interface EditorPort {
    /** The document's text, with LF line endings. */
    getText(): string;
    /** Replace [offset, offset + length) with text. False when the editor changed first. */
    replace(offset: number, length: number, text: string): Promise<boolean>;
}

/** One change of a change event, with its offset in the text before the event. */
export interface LocalChange {
    offset: number;
    length: number;
    text: string;
}

/** Origin of transactions that bring in a teammate's edits (or the stored document). */
export const REMOTE = 'remote';
/** Origin of transactions that record what the user did in the editor. */
export const LOCAL = 'local';

/** Smallest single replacement that turns `from` into `to`. */
function singleDiff(from: string, to: string): LocalChange {
    let start = 0;
    const max = Math.min(from.length, to.length);
    while (start < max && from.charCodeAt(start) === to.charCodeAt(start)) { start++; }
    let endFrom = from.length;
    let endTo = to.length;
    while (endFrom > start && endTo > start && from.charCodeAt(endFrom - 1) === to.charCodeAt(endTo - 1)) { endFrom--; endTo--; }
    return { offset: start, length: endFrom - start, text: to.slice(start, endTo) };
}

const pause = (ms: number) => new Promise<void>(resolve => setTimeout(resolve, ms));

/**
 * Keeps one editor document and one shared file the same.
 *
 * `doc` always holds exactly what the editor shows. That is what makes positions safe: what
 * the user types is written to it at the offsets the editor reported, with nothing to
 * translate. A teammate's edits do not go into `doc` when they arrive. They wait in `pending`
 * until the editor has taken them, because an edit asked of VS Code lands later, and is
 * refused if the user typed in the meantime. When that happens the edit is worked out again
 * against the new text and retried, and the merge library places both sides' changes.
 */
export class TextBinding {
    /** Characters the user inserted, as opposed to a teammate. */
    public localChars = 0;
    /** Line breaks the user deleted: the lines they removed. */
    public localLinesDeleted = 0;

    private readonly ytext: Y.Text;
    private pending: Uint8Array[] = [];
    /** The text the editor will show once the edit now on its way lands. */
    private expected: { target: string; update: Uint8Array; count: number } | null = null;
    private showing = false;
    private disposed = false;
    private waiters: (() => void)[] = [];

    constructor(private readonly doc: Y.Doc, private readonly editor: EditorPort) {
        this.ytext = doc.getText('text');
    }

    /** A teammate's edits (or stored ones) for this file. They are shown as soon as the editor allows. */
    public receive(update: Uint8Array): void {
        if (this.disposed) { return; }
        this.pending.push(update);
        void this.show();
    }

    /**
     * Call for every change event on the document, once the editor's text has changed.
     * Returns true when the event was a teammate's edit arriving, so it must not be counted
     * as the user's typing.
     */
    public handleEditorChange(changes: LocalChange[]): boolean {
        if (this.disposed) { return false; }

        // VS Code may report our one replacement as several smaller changes, so the edit is
        // recognised by the text it leaves, not by its shape
        if (this.expected && this.editor.getText() === this.expected.target) {
            const landed = this.expected;
            this.expected = null;
            Y.applyUpdate(this.doc, landed.update, REMOTE);
            this.pending.splice(0, landed.count);
            this.wake();
            return true;
        }

        // The user's own edit. `doc` shows what the editor showed before it, so the offsets fit.
        const before = this.ytext.toString();
        for (const change of changes) {
            this.localLinesDeleted += before.slice(change.offset, change.offset + change.length).split('\n').length - 1;
        }
        this.doc.transact(() => {
            for (const change of [...changes].sort((a, b) => b.offset - a.offset)) {
                if (change.length > 0) { this.ytext.delete(change.offset, change.length); }
                if (change.text) {
                    this.ytext.insert(change.offset, change.text);
                    this.localChars += change.text.length;
                }
            }
        }, LOCAL);
        this.matchEditor();
        this.wake();
        return false;
    }

    /** The whole file as teammates should get it: what is shown plus what is waiting to be. */
    public fullState(): Uint8Array {
        return Y.mergeUpdates([Y.encodeStateAsUpdate(this.doc), ...this.pending]);
    }

    /** Resolves when the editor shows everything received and nothing is on its way. */
    public settled(): Promise<void> {
        if (this.isSettled()) { return Promise.resolve(); }
        return new Promise(resolve => this.waiters.push(resolve));
    }

    public dispose(): void {
        this.disposed = true;
        this.expected = null;
        this.pending = [];
        this.wake();
    }

    private isSettled(): boolean {
        return this.disposed || (!this.showing && !this.expected && this.pending.length === 0);
    }

    private wake(): void {
        if (this.isSettled()) {
            for (const resolve of this.waiters.splice(0)) { resolve(); }
        }
    }

    /**
     * `doc` must equal the editor. If a change was reported in a way that left them apart,
     * the editor wins: the user must never see their text rewritten to fit ours.
     */
    private matchEditor(): void {
        const shown = this.editor.getText();
        if (shown === this.ytext.toString()) { return; }

        const fix = singleDiff(this.ytext.toString(), shown);
        this.doc.transact(() => {
            if (fix.length > 0) { this.ytext.delete(fix.offset, fix.length); }
            if (fix.text) { this.ytext.insert(fix.offset, fix.text); }
        }, LOCAL);
    }

    /** What the file's text will be once `update` is applied on top of what the editor shows. */
    private textAfter(update: Uint8Array): string {
        const preview = new Y.Doc();
        Y.applyUpdate(preview, Y.encodeStateAsUpdate(this.doc));
        Y.applyUpdate(preview, update);
        const text = preview.getText('text').toString();
        preview.destroy();
        return text;
    }

    private async show(): Promise<void> {
        if (this.showing) { return; }
        this.showing = true;
        try {
            while (!this.disposed && this.pending.length > 0) {
                const count = this.pending.length;
                const update = count === 1 ? this.pending[0] : Y.mergeUpdates(this.pending.slice(0, count));
                let target: string;
                try {
                    target = this.textAfter(update);
                } catch {
                    // Not edits we can apply. Drop them rather than stop showing what follows.
                    this.pending.splice(0, count);
                    continue;
                }
                const shown = this.editor.getText();

                // Nothing to show (a change the editor already has, or one that isn't text)
                if (shown === target) {
                    Y.applyUpdate(this.doc, update, REMOTE);
                    this.pending.splice(0, count);
                    continue;
                }

                const edit = singleDiff(shown, target);
                this.expected = { target, update, count };
                const applied = await this.editor.replace(edit.offset, edit.length, edit.text);
                if (this.disposed) { return; }
                if (!applied) {
                    // The user typed first. Work the edit out again against the new text.
                    this.expected = null;
                    await pause(0);
                    continue;
                }

                // The change event normally arrives before the edit reports success
                for (let waited = 0; this.expected && waited < 400; waited++) { await pause(5); }
                if (this.expected) {
                    this.expected = null;
                    this.matchEditor();
                }
            }
        } finally {
            this.showing = false;
            this.wake();
        }
    }
}
