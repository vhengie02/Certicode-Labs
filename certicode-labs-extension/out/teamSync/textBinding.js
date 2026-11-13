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
exports.TextBinding = exports.LOCAL = exports.REMOTE = void 0;
const Y = __importStar(require("yjs"));
/** Origin of transactions that bring in a teammate's edits (or the stored document). */
exports.REMOTE = 'remote';
/** Origin of transactions that record what the user did in the editor. */
exports.LOCAL = 'local';
/** Smallest single replacement that turns `from` into `to`. */
function singleDiff(from, to) {
    let start = 0;
    const max = Math.min(from.length, to.length);
    while (start < max && from.charCodeAt(start) === to.charCodeAt(start)) {
        start++;
    }
    let endFrom = from.length;
    let endTo = to.length;
    while (endFrom > start && endTo > start && from.charCodeAt(endFrom - 1) === to.charCodeAt(endTo - 1)) {
        endFrom--;
        endTo--;
    }
    return { offset: start, length: endFrom - start, text: to.slice(start, endTo) };
}
const pause = (ms) => new Promise(resolve => setTimeout(resolve, ms));
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
class TextBinding {
    constructor(doc, editor, options = {}) {
        this.doc = doc;
        this.editor = editor;
        /** Characters the user inserted, as opposed to a teammate. */
        this.localChars = 0;
        /** Line breaks the user deleted: the lines they removed. */
        this.localLinesDeleted = 0;
        /**
         * Whether the user's last change removed text this editor didn't write: a teammate's, or
         * the starter code. Used to warn when Undo takes back a teammate's edit.
         */
        this.lastChangeRemovedOthers = false;
        this.pending = [];
        /** The text the editor will show once the edit now on its way lands. */
        this.expected = null;
        this.showing = false;
        this.disposed = false;
        this.waiters = [];
        this.ytext = doc.getText('text');
        this.echoTimeoutMs = options.echoTimeoutMs ?? 2000;
    }
    /** A teammate's edits (or stored ones) for this file. They are shown as soon as the editor allows. */
    receive(update) {
        if (this.disposed) {
            return;
        }
        this.pending.push(update);
        void this.show();
    }
    /**
     * Call for every change event on the document, once the editor's text has changed.
     * Returns true when the event was a teammate's edit arriving, so it must not be counted
     * as the user's typing.
     */
    handleEditorChange(changes) {
        if (this.disposed) {
            return false;
        }
        // VS Code may report our one replacement as several smaller changes, so the edit is
        // recognised by the text it leaves, not by its shape
        if (this.expected && this.editor.getText() === this.expected.target) {
            const landed = this.expected;
            this.expected = null;
            Y.applyUpdate(this.doc, landed.update, exports.REMOTE);
            this.pending.splice(0, landed.count);
            this.wake();
            return true;
        }
        // The user's own edit. `doc` shows what the editor showed before it, so the offsets fit.
        const before = this.ytext.toString();
        this.lastChangeRemovedOthers = changes.some(change => change.length > 0 && this.othersWroteIn(change.offset, change.length));
        for (const change of changes) {
            this.localLinesDeleted += before.slice(change.offset, change.offset + change.length).split('\n').length - 1;
        }
        this.doc.transact(() => {
            for (const change of [...changes].sort((a, b) => b.offset - a.offset)) {
                if (change.length > 0) {
                    this.ytext.delete(change.offset, change.length);
                }
                if (change.text) {
                    this.ytext.insert(change.offset, change.text);
                    this.localChars += change.text.length;
                }
            }
        }, exports.LOCAL);
        this.matchEditor();
        this.wake();
        return false;
    }
    /** The text the editor will show once the teammate's edit now on its way lands; undefined if none is. */
    inFlightTarget() {
        return this.expected?.target;
    }
    /** Whether any character in [offset, offset + length) was written by another editor. */
    othersWroteIn(offset, length) {
        let position = 0;
        const end = offset + length;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        for (let item = this.ytext._start; item && position < end; item = item.right) {
            const text = item.deleted ? undefined : item.content?.str;
            if (typeof text !== 'string') {
                continue;
            }
            const itemEnd = position + text.length;
            if (itemEnd > offset && item.id.client !== this.doc.clientID) {
                return true;
            }
            position = itemEnd;
        }
        return false;
    }
    /** The whole file as teammates should get it: what is shown plus what is waiting to be. */
    fullState() {
        return Y.mergeUpdates([Y.encodeStateAsUpdate(this.doc), ...this.pending]);
    }
    /** Resolves when the editor shows everything received and nothing is on its way. */
    settled() {
        if (this.isSettled()) {
            return Promise.resolve();
        }
        return new Promise(resolve => this.waiters.push(resolve));
    }
    dispose() {
        this.disposed = true;
        this.expected = null;
        this.pending = [];
        this.wake();
    }
    isSettled() {
        return this.disposed || (!this.showing && !this.expected && this.pending.length === 0);
    }
    wake() {
        if (this.isSettled()) {
            for (const resolve of this.waiters.splice(0)) {
                resolve();
            }
        }
    }
    /**
     * `doc` must equal the editor. If a change was reported in a way that left them apart,
     * the editor wins: the user must never see their text rewritten to fit ours.
     */
    matchEditor() {
        const shown = this.editor.getText();
        if (shown === this.ytext.toString()) {
            return;
        }
        const fix = singleDiff(this.ytext.toString(), shown);
        this.doc.transact(() => {
            if (fix.length > 0) {
                this.ytext.delete(fix.offset, fix.length);
            }
            if (fix.text) {
                this.ytext.insert(fix.offset, fix.text);
            }
        }, exports.LOCAL);
    }
    /** What the file's text will be once `update` is applied on top of what the editor shows. */
    textAfter(update) {
        const preview = new Y.Doc();
        Y.applyUpdate(preview, Y.encodeStateAsUpdate(this.doc));
        Y.applyUpdate(preview, update);
        const text = preview.getText('text').toString();
        preview.destroy();
        return text;
    }
    async show() {
        if (this.showing) {
            return;
        }
        this.showing = true;
        try {
            while (!this.disposed && this.pending.length > 0) {
                const count = this.pending.length;
                const update = count === 1 ? this.pending[0] : Y.mergeUpdates(this.pending.slice(0, count));
                let target;
                try {
                    target = this.textAfter(update);
                }
                catch {
                    // Not edits we can apply. Drop them rather than stop showing what follows.
                    this.pending.splice(0, count);
                    continue;
                }
                const shown = this.editor.getText();
                // Nothing to show (a change the editor already has, or one that isn't text)
                if (shown === target) {
                    Y.applyUpdate(this.doc, update, exports.REMOTE);
                    this.pending.splice(0, count);
                    continue;
                }
                const edit = singleDiff(shown, target);
                this.expected = { target, update, count };
                const applied = await this.editor.replace(edit.offset, edit.length, edit.text);
                if (this.disposed) {
                    return;
                }
                if (!applied) {
                    // The user typed first. Work the edit out again against the new text.
                    this.expected = null;
                    await pause(0);
                    continue;
                }
                // The change event normally arrives before the edit reports success
                const deadline = Date.now() + this.echoTimeoutMs;
                while (this.expected && !this.disposed && Date.now() < deadline) {
                    await pause(5);
                }
                if (this.disposed) {
                    return;
                }
                if (this.expected) {
                    const landed = this.expected;
                    this.expected = null;
                    if (this.editor.getText() === landed.target) {
                        // The editor took the edit but its change event never reached us
                        Y.applyUpdate(this.doc, landed.update, exports.REMOTE);
                        this.pending.splice(0, landed.count);
                    }
                    // Otherwise the edit didn't land as asked: it stays waiting and is worked out
                    // again against what the editor shows. Never written in as the student's.
                }
            }
        }
        finally {
            this.showing = false;
            this.wake();
        }
    }
}
exports.TextBinding = TextBinding;
//# sourceMappingURL=textBinding.js.map