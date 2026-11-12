"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.WorkspaceSync = exports.isShareableName = void 0;
/** Files that are notes or data rather than code; the same list as LabSession::isCodeFile. */
const NOT_CODE = /\.(md|txt|json|env|log|lock|ya?ml)$/i;
/**
 * Whether a teammate may share a file under this name: a code file, by a plain relative path
 * inside the lab folder. Names come from teammates, so nothing may point outside it.
 */
function isShareableName(name) {
    if (typeof name !== 'string' || name.length === 0 || name.length > 200 || NOT_CODE.test(name)) {
        return false;
    }
    if (name.startsWith('/') || name.includes('\\') || name.includes(':') || name.includes('\0')) {
        return false;
    }
    return name.split('/').every(part => part !== '' && part !== '.' && part !== '..' && !part.startsWith('.'));
}
exports.isShareableName = isShareableName;
/**
 * Ties the team's shared document to the files in the lab folder: every shared file is open
 * as a document bound to its shared text, files a teammate adds or deletes appear and go, and
 * files the student adds or deletes reach the team.
 */
class WorkspaceSync {
    constructor(host, doc, options = {}) {
        this.host = host;
        this.doc = doc;
        this.documents = new Map();
        this.bindings = new Map();
        /** Files being opened: their change events are setup, not the student typing. */
        this.preparing = new Set();
        /** Files with edits of the student's own not saved yet. */
        this.locallyDirty = new Set();
        this.saveTimers = new Map();
        /** Lines deleted by the student in files that were since closed or deleted. */
        this.removedLinesDeleted = 0;
        this.stopped = false;
        this.autosaveMs = options.autosaveMs ?? 1000;
        this.flushTimeoutMs = options.flushTimeoutMs ?? 5000;
    }
    /** Load the team's document, seeding it from `starter` if it is new, and open every shared file. */
    async start(starter) {
        this.doc.onFileAdded(name => { void this.attach(name); });
        this.doc.onFileRemoved(name => { void this.detach(name, true); });
        await this.doc.start(starter.filter(file => isShareableName(file.name)));
        for (const name of this.doc.fileNames()) {
            await this.attach(name);
        }
    }
    sharedNames() {
        return [...this.bindings.keys()];
    }
    isShared(name) {
        return this.bindings.has(name) || this.preparing.has(name);
    }
    /**
     * Every change event of a lab document goes through here, before anything else looks at
     * it. True when the event was a teammate's edit (or setup): not the student's typing.
     */
    handleChange(name, changes) {
        if (this.stopped) {
            return false;
        }
        if (this.preparing.has(name)) {
            return true;
        }
        const binding = this.bindings.get(name);
        if (!binding) {
            return false;
        }
        const remote = binding.handleEditorChange(changes);
        if (remote) {
            this.scheduleSave(name);
        }
        else {
            this.locallyDirty.add(name);
        }
        return remote;
    }
    /** The student saved a file: a teammate's later edits may be saved for them again. */
    noteSaved(name) {
        this.locallyDirty.delete(name);
    }
    /** The student created a code file in the lab folder. */
    fileCreated(name, content) {
        if (this.stopped || this.isShared(name) || !isShareableName(name)) {
            return;
        }
        this.doc.createFile(name, content);
    }
    /** The student deleted a file from the lab folder. */
    fileDeleted(name) {
        if (this.stopped || !this.bindings.has(name)) {
            return;
        }
        void this.detach(name, false);
        this.doc.deleteFile(name);
    }
    /** Per shared file, the lines this student wrote, changed and deleted. */
    lineStats() {
        return [...this.bindings].map(([name, binding]) => ({ name, ...this.doc.lineStats(name), deleted: binding.localLinesDeleted }));
    }
    /** The editor ids the student's edits carry in this window (see TeamDoc.clientIds). */
    clientIds() {
        return this.doc.clientIds();
    }
    /** Lines the student deleted in files that are no longer shared. */
    linesDeletedElsewhere() {
        return this.removedLinesDeleted;
    }
    /** Resolves once teammates' edits are in the editor and the student's are sent and stored. */
    async flush() {
        if (this.stopped) {
            return;
        }
        const shown = Promise.all([...this.bindings.values()].map(binding => binding.settled()));
        await Promise.race([shown, new Promise(resolve => setTimeout(resolve, this.flushTimeoutMs))]);
        await this.doc.flush();
    }
    stop() {
        if (this.stopped) {
            return;
        }
        this.stopped = true;
        for (const timer of this.saveTimers.values()) {
            clearTimeout(timer);
        }
        this.saveTimers.clear();
        this.doc.stop();
        this.bindings.clear();
        this.documents.clear();
    }
    async attach(name) {
        if (this.stopped || this.isShared(name) || !isShareableName(name)) {
            return;
        }
        this.preparing.add(name);
        try {
            const document = await this.host.openDocument(name, this.doc.text(name) ?? '');
            if (!document || this.stopped) {
                return;
            }
            // The team's text wins: the file shows it before it is bound. The check and the bind
            // happen together, so no teammate's edit can arrive in between.
            for (let attempt = 0; attempt < 20; attempt++) {
                const shared = this.doc.text(name) ?? '';
                const shown = document.getText();
                if (shown === shared) {
                    this.documents.set(name, document);
                    this.bindings.set(name, this.doc.bind(name, document));
                    return;
                }
                await document.replace(0, shown.length, shared);
                if (this.stopped) {
                    return;
                }
            }
        }
        finally {
            this.preparing.delete(name);
        }
    }
    /** Stop sharing a file; `fromTeammate`: a teammate deleted it, so it goes from the folder too. */
    async detach(name, fromTeammate) {
        const binding = this.bindings.get(name);
        if (!binding) {
            return;
        }
        this.removedLinesDeleted += binding.localLinesDeleted;
        this.doc.unbind(name);
        this.bindings.delete(name);
        this.documents.delete(name);
        this.locallyDirty.delete(name);
        const timer = this.saveTimers.get(name);
        if (timer) {
            clearTimeout(timer);
        }
        this.saveTimers.delete(name);
        if (fromTeammate && !this.stopped) {
            await this.host.deleteFile(name);
        }
    }
    scheduleSave(name) {
        if (this.locallyDirty.has(name)) {
            return;
        }
        const previous = this.saveTimers.get(name);
        if (previous) {
            clearTimeout(previous);
        }
        this.saveTimers.set(name, setTimeout(() => {
            this.saveTimers.delete(name);
            const document = this.documents.get(name);
            if (!this.stopped && document && document.isDirty && !this.locallyDirty.has(name)) {
                void document.save().catch(() => undefined);
            }
        }, this.autosaveMs));
    }
}
exports.WorkspaceSync = WorkspaceSync;
//# sourceMappingURL=workspaceSync.js.map