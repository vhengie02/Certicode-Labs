import { LineStats, TeamDoc } from './teamDoc';
import { EditorPort, LocalChange, TextBinding } from './textBinding';

/** A lab file loaded in the editor (not necessarily shown). */
export interface HostDocument extends EditorPort {
    readonly isDirty: boolean;
    save(): Promise<void>;
}

/** What the workspace layer needs from VS Code. Kept small so it can be faked in tests. */
export interface SyncHost {
    /**
     * Load a lab file as a document, creating it with `contentIfMissing` when it isn't on disk.
     * Its line endings are made LF. Undefined when the file can't be opened.
     */
    openDocument(name: string, contentIfMissing: string): Promise<HostDocument | undefined>;
    /** A lab file's current text (the open document's, else the file on disk), with LF endings; undefined if it isn't there. */
    readText(name: string): Promise<string | undefined>;
    /** Keep a copy of the student's own version of a file, outside what is shared. */
    writeBackup(name: string, content: string): Promise<void>;
    deleteFile(name: string): Promise<void>;
}

const toLf = (text: string) => text.replace(/\r\n?/g, '\n');

export { isShareableName } from './names';
import { isShareableName } from './names';

export interface WorkspaceSyncOptions {
    /** A teammate's edit is saved to disk this long after it lands, unless the user has unsaved edits of their own. */
    autosaveMs?: number;
    /** Longest flush() waits for the editor to take teammates' edits before it stores anyway. */
    flushTimeoutMs?: number;
}

/**
 * Ties the team's shared document to the files in the lab folder: every shared file is open
 * as a document bound to its shared text, files a teammate adds or deletes appear and go, and
 * files the student adds or deletes reach the team.
 */
export class WorkspaceSync {
    private readonly documents = new Map<string, HostDocument>();
    private readonly bindings = new Map<string, TextBinding>();
    /** Files being opened: their change events are setup, not the student typing. */
    private readonly preparing = new Set<string>();
    /** Files with edits of the student's own not saved yet. */
    private readonly locallyDirty = new Set<string>();
    private readonly saveTimers = new Map<string, ReturnType<typeof setTimeout>>();
    /** Lines deleted by the student in files that were since closed or deleted. */
    private removedLinesDeleted = 0;
    /** The lab's starter code, per file: a copy of just that is nothing of the student's own. */
    private readonly starterText = new Map<string, string>();
    /** What a file being opened is being set to, while that edit is on its way. */
    private readonly preparingTargets = new Map<string, string>();
    private readonly backupListeners: ((name: string) => void)[] = [];
    private readonly undoListeners: ((name: string) => void)[] = [];
    private readonly autosaveMs: number;
    private readonly flushTimeoutMs: number;
    private stopped = false;

    constructor(private readonly host: SyncHost, private readonly doc: TeamDoc, options: WorkspaceSyncOptions = {}) {
        this.autosaveMs = options.autosaveMs ?? 1000;
        this.flushTimeoutMs = options.flushTimeoutMs ?? 5000;
    }

    /** Load the team's document, seeding it from `starter` if it is new, and open every shared file. */
    public async start(starter: { name: string; content: string }[]): Promise<void> {
        this.doc.onFileAdded(name => { void this.attach(name); });
        this.doc.onFileRemoved(name => { void this.detach(name, true); });

        // A team document that is new is seeded from this student's files as they are now, not
        // from the lab's starter code: the attempt may be under way, and its work is in them
        const shareable = starter.filter(file => isShareableName(file.name));
        const seed: { name: string; content: string }[] = [];
        for (const file of shareable) {
            this.starterText.set(file.name, toLf(file.content));
            seed.push({ name: file.name, content: (await this.host.readText(file.name)) ?? file.content });
        }
        await this.doc.start(seed);
        for (const name of this.doc.fileNames()) {
            await this.attach(name);
        }
    }

    public sharedNames(): string[] {
        return [...this.bindings.keys()];
    }

    public isShared(name: string): boolean {
        return this.bindings.has(name) || this.preparing.has(name);
    }

    /**
     * Every change event of a lab document goes through here, before anything else looks at
     * it. True when the event was a teammate's edit (or setup): not the student's typing.
     */
    public handleChange(name: string, changes: LocalChange[], undoRedo = false): boolean {
        if (this.stopped) { return false; }
        if (this.preparing.has(name)) { return true; }

        const binding = this.bindings.get(name);
        if (!binding) { return false; }

        const remote = binding.handleEditorChange(changes);
        if (remote) {
            this.scheduleSave(name);
        } else {
            this.locallyDirty.add(name);
            // VS Code's undo also takes back teammates' edits that landed in this editor
            if (undoRedo && binding.lastChangeRemovedOthers) {
                for (const listener of this.undoListeners) { listener(name); }
            }
        }
        return remote;
    }

    /** A student's own copy of a file was replaced by the team's, and kept as a backup. */
    public onBackup(listener: (name: string) => void): void { this.backupListeners.push(listener); }

    /** The student's Undo or Redo removed text a teammate wrote. */
    public onUndoOthers(listener: (name: string) => void): void { this.undoListeners.push(listener); }

    /** The student renamed a file in the lab folder: the team gets it under the new name. */
    public fileRenamed(oldName: string, newName: string, content: string): void {
        if (this.stopped) { return; }
        if (this.bindings.has(oldName)) {
            void this.detach(oldName, false);
            this.doc.deleteFile(oldName);
        }
        this.fileCreated(newName, content);
    }

    /** The student saved a file: a teammate's later edits may be saved for them again. */
    public noteSaved(name: string): void {
        this.locallyDirty.delete(name);
    }

    /** The student created a code file in the lab folder. */
    public fileCreated(name: string, content: string): void {
        if (this.stopped || this.isShared(name) || !isShareableName(name)) { return; }
        this.doc.createFile(name, content);
    }

    /** The student deleted a file from the lab folder. */
    public fileDeleted(name: string): void {
        if (this.stopped || !this.bindings.has(name)) { return; }
        void this.detach(name, false);
        this.doc.deleteFile(name);
    }

    /** Per shared file, the lines this student wrote, changed and deleted. */
    public lineStats(): (LineStats & { name: string; deleted: number })[] {
        return [...this.bindings].map(([name, binding]) => ({ name, ...this.doc.lineStats(name), deleted: binding.localLinesDeleted }));
    }

    /** The editor ids the student's edits carry in this window (see TeamDoc.clientIds). */
    public clientIds(): number[] {
        return this.doc.clientIds();
    }

    /** Lines the student deleted in files that are no longer shared. */
    public linesDeletedElsewhere(): number {
        return this.removedLinesDeleted;
    }

    /** Resolves once teammates' edits are in the editor and the student's are sent and stored. */
    public async flush(): Promise<void> {
        if (this.stopped) { return; }
        const shown = Promise.all([...this.bindings.values()].map(binding => binding.settled()));
        await Promise.race([shown, new Promise(resolve => setTimeout(resolve, this.flushTimeoutMs))]);
        await this.doc.flush();
    }

    /**
     * Stop sharing. Returns, per file, the text a teammate's edit that is still on its way to
     * the editor will leave: when that edit lands, it is not the student's typing.
     */
    public stop(): Map<string, string> {
        const inFlight = new Map(this.preparingTargets);
        for (const [name, binding] of this.bindings) {
            const target = binding.inFlightTarget();
            if (target !== undefined) { inFlight.set(name, target); }
        }
        if (this.stopped) { return inFlight; }
        this.stopped = true;
        for (const timer of this.saveTimers.values()) { clearTimeout(timer); }
        this.saveTimers.clear();
        this.doc.stop();
        this.bindings.clear();
        this.documents.clear();
        return inFlight;
    }

    private async attach(name: string): Promise<void> {
        if (this.stopped || this.isShared(name) || !isShareableName(name)) { return; }
        this.preparing.add(name);
        try {
            const document = await this.host.openDocument(name, this.doc.text(name) ?? '');
            if (!document || this.stopped) { return; }

            // The team's text wins: the file shows it before it is bound. The check and the bind
            // happen together, so no teammate's edit can arrive in between.
            let backedUp = false;
            for (let attempt = 0; attempt < 20; attempt++) {
                const shared = this.doc.text(name) ?? '';
                const shown = document.getText();
                if (shown === shared) {
                    this.documents.set(name, document);
                    this.bindings.set(name, this.doc.bind(name, document));
                    return;
                }
                // The student's own version, if it is more than the starter code, is kept
                if (!backedUp && shown.trim() !== '' && shown !== this.starterText.get(name)) {
                    backedUp = true;
                    await this.host.writeBackup(name, shown);
                    for (const listener of this.backupListeners) { listener(name); }
                }
                this.preparingTargets.set(name, shared);
                await document.replace(0, shown.length, shared);
                if (this.stopped) { return; }
            }
        } finally {
            this.preparing.delete(name);
            if (!this.stopped) { this.preparingTargets.delete(name); }
        }
    }

    /** Stop sharing a file; `fromTeammate`: a teammate deleted it, so it goes from the folder too. */
    private async detach(name: string, fromTeammate: boolean): Promise<void> {
        const binding = this.bindings.get(name);
        if (!binding) { return; }
        this.removedLinesDeleted += binding.localLinesDeleted;
        this.doc.unbind(name);
        this.bindings.delete(name);
        this.documents.delete(name);
        this.locallyDirty.delete(name);
        const timer = this.saveTimers.get(name);
        if (timer) { clearTimeout(timer); }
        this.saveTimers.delete(name);
        if (fromTeammate && !this.stopped) {
            await this.host.deleteFile(name);
        }
    }

    private scheduleSave(name: string): void {
        if (this.locallyDirty.has(name)) { return; }
        const previous = this.saveTimers.get(name);
        if (previous) { clearTimeout(previous); }
        this.saveTimers.set(name, setTimeout(() => {
            this.saveTimers.delete(name);
            const document = this.documents.get(name);
            if (!this.stopped && document && document.isDirty && !this.locallyDirty.has(name)) {
                void document.save().catch(() => undefined);
            }
        }, this.autosaveMs));
    }
}
