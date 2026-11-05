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

class RelativePattern {
    constructor(public readonly base: unknown, public readonly pattern: string) {}
}

export const vscodeStub: any = {
    Uri,
    Position,
    WorkspaceEdit,
    RelativePattern,
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
    },
    workspace: {
        workspaceFolders: [{ uri: Uri.file('/workspace') }],
        textDocuments: [],
        getConfiguration: () => ({ get: () => undefined, update: async () => undefined }),
        getWorkspaceFolder: () => ({ uri: Uri.file('/workspace') }),
        asRelativePath: (uri: Uri) => uri.fsPath.replace(/^\/workspace\//, ''),
        openTextDocument: async () => ({}),
        applyEdit: async () => true,
        // Matches the extensions in a "**/*.{a,b}" pattern; the exclude pattern isn't applied here
        findFiles: async (include: RelativePattern, _exclude: string, maxResults: number) => {
            const extensions = (/\{(.+)\}$/.exec(include.pattern)?.[1] ?? '').split(',');
            return [...files.keys()]
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
            createDirectory: async () => undefined,
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
