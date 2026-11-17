/**
 * A stand-in for the 'vscode' module, so the provider can be loaded and exercised by
 * Node's built-in test runner without starting VS Code. Import this before the provider.
 */
// eslint-disable-next-line @typescript-eslint/no-var-requires
const Module = require('module');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const path = require('path');

export const shown: { level: string; message: string }[] = [];

/** What the next warning dialog answers, and what the clipboard holds. Tests set these. */
export const stubState: { warningAnswer: string | undefined; clipboard: string } = {
    warningAnswer: undefined,
    clipboard: '',
};

class Uri {
    constructor(public readonly fsPath: string, public readonly scheme = 'file') {}
    static file(p: string) { return new Uri(p); }
    static from(parts: { scheme: string; path: string }) { return new Uri(parts.path, parts.scheme); }
    static joinPath(base: Uri, ...parts: string[]) { return new Uri(path.posix.join(base.fsPath, ...parts)); }
    toString() { return `${this.scheme}://${this.fsPath}`; }
}

class Position {
    constructor(public readonly line: number, public readonly character: number) {}
}

class WorkspaceEdit {
    readonly inserts: { uri: Uri; text: string }[] = [];
    insert(uri: Uri, _position: Position, text: string) { this.inserts.push({ uri, text }); }
}

const record = (level: string) => async (message: string) => {
    shown.push({ level, message });
    return level === 'warning' ? stubState.warningAnswer : undefined;
};

/** The fake disk: path -> content. */
export const files = new Map<string, string>();

/** Created and modified times by path. A file not listed here is dated 0. */
export const fileTimes = new Map<string, { ctime: number; mtime: number }>();

/** Who listens for the VS Code window gaining or losing OS focus. */
const windowStateListeners: ((state: { focused: boolean }) => void)[] = [];

/** Tell every listener the VS Code window gained or lost OS focus, as VS Code would. */
export function setWindowFocused(focused: boolean) {
    [...windowStateListeners].forEach(listener => listener({ focused }));
}

/** Folders made with createDirectory; folders that hold a file in `files` exist too. */
export const directories = new Set<string>();

const FileType = { File: 1, Directory: 2 };

/** The entries directly inside a folder of the fake disk. */
function readDirectory(dir: string): [string, number][] {
    const prefix = dir.replace(/\/+$/, '') + '/';
    const entries = new Map<string, number>();
    for (const p of files.keys()) {
        if (!p.startsWith(prefix)) { continue; }
        const [first, ...rest] = p.slice(prefix.length).split('/');
        entries.set(first, rest.length > 0 ? FileType.Directory : FileType.File);
    }
    for (const d of directories) {
        if (d.startsWith(prefix)) { entries.set(d.slice(prefix.length).split('/')[0], FileType.Directory); }
    }
    return [...entries.entries()];
}

/** The listeners of the last file system watcher created, so tests can fire its events. */
export const watcher: { onDidCreate?: (uri: unknown) => void; onDidChange?: (uri: unknown) => void; onDidDelete?: (uri: unknown) => void } = {};

const listener = () => ({ dispose: () => undefined });

class RelativePattern {
    constructor(public readonly base: unknown, public readonly pattern: string) {}
}

export const vscodeStub: any = {
    Uri,
    Position,
    WorkspaceEdit,
    RelativePattern,
    FileType,
    TextDocumentChangeReason: { Undo: 1, Redo: 2 },
    ConfigurationTarget: { Global: 1, Workspace: 2, WorkspaceFolder: 3 },
    extensions: { getExtension: () => undefined },
    commands: { executeCommand: async () => undefined },
    window: {
        showInformationMessage: record('info'),
        showWarningMessage: record('warning'),
        showErrorMessage: record('error'),
        showTextDocument: async () => undefined,
        activeTextEditor: undefined,
        onDidChangeWindowState: (listener: (state: { focused: boolean }) => void) => {
            windowStateListeners.push(listener);
            return { dispose: () => { windowStateListeners.splice(windowStateListeners.indexOf(listener), 1); } };
        },
    },
    workspace: {
        workspaceFolders: [{ uri: Uri.file('/workspace') }],
        textDocuments: [] as any[],
        onDidOpenTextDocument: () => ({ dispose: () => undefined }),
        onDidCloseTextDocument: () => ({ dispose: () => undefined }),
        onDidChangeTextDocument: listener,
        onDidSaveTextDocument: listener,
        createFileSystemWatcher: () => ({
            dispose: () => undefined,
            onDidCreate: (fn: any) => { watcher.onDidCreate = fn; return listener(); },
            onDidChange: (fn: any) => { watcher.onDidChange = fn; return listener(); },
            onDidDelete: (fn: any) => { watcher.onDidDelete = fn; return listener(); },
        }),
        getConfiguration: () => ({ get: () => undefined, update: async () => undefined }),
        getWorkspaceFolder: () => ({ uri: Uri.file('/workspace') }),
        asRelativePath: (uri: Uri) => uri.fsPath.replace(/^\/workspace\//, ''),
        openTextDocument: async () => ({}),
        applyEdit: async () => true,
        // Matches the extensions in a "**/*.{a,b}" pattern under the pattern's folder; the exclude pattern isn't applied here
        findFiles: async (include: RelativePattern, _exclude: string, maxResults: number) => {
            const extensions = (/\{(.+)\}$/.exec(include.pattern)?.[1] ?? '').split(',');
            const base: string | undefined = (include.base as any)?.fsPath ?? (include.base as any)?.uri?.fsPath;
            return [...files.keys()]
                .filter(p => base === undefined || p.startsWith(base.replace(/\/+$/, '') + '/'))
                .filter(p => extensions.includes(p.split('.').pop() ?? ''))
                .slice(0, maxResults)
                .map(p => Uri.file(p));
        },
        fs: {
            stat: async (uri: Uri) => {
                if (!files.has(uri.fsPath)) {
                    throw new Error('FileNotFound');
                }
                return fileTimes.get(uri.fsPath) ?? { ctime: 0, mtime: 0 };
            },
            readFile: async (uri: Uri) => Buffer.from(files.get(uri.fsPath) ?? ''),
            writeFile: async (uri: Uri, content: Uint8Array) => {
                files.set(uri.fsPath, Buffer.from(content).toString('utf8'));
            },
            createDirectory: async (uri: Uri) => { directories.add(uri.fsPath); },
            readDirectory: async (uri: Uri) => readDirectory(uri.fsPath),
            delete: async (uri: Uri) => { files.delete(uri.fsPath); },
        },
    },
    env: { clipboard: { readText: async () => stubState.clipboard } },
};

/** A Memento that keeps values in memory, like the extension's globalState. */
export function memento() {
    const values = new Map<string, any>();
    return {
        get: (key: string, fallback?: any) => (values.has(key) ? values.get(key) : fallback),
        update: async (key: string, value: any) => { values.set(key, value); },
    };
}

/** An in-memory stand-in for vscode.SecretStorage. */
export function fakeSecrets() {
    const values = new Map<string, string>();
    return {
        get: async (key: string) => values.get(key),
        store: async (key: string, value: string) => { values.set(key, value); },
        delete: async (key: string) => { values.delete(key); },
    } as any;
}

const originalLoad = Module._load;
Module._load = function (request: string, ...rest: any[]) {
    if (request === 'vscode') {
        return vscodeStub;
    }
    return originalLoad.call(this, request, ...rest);
};
