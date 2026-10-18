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
    readonly scheme = 'file';
    constructor(public readonly fsPath: string) {}
    static file(p: string) { return new Uri(p); }
    static joinPath(base: Uri, ...parts: string[]) { return new Uri(path.posix.join(base.fsPath, ...parts)); }
    toString() { return `file://${this.fsPath}`; }
}

const record = (level: string) => async (message: string) => {
    shown.push({ level, message });
    return level === 'warning' ? stubState.warningAnswer : undefined;
};

/** The fake disk: path -> content. */
export const files = new Map<string, string>();

export const vscodeStub: any = {
    Uri,
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
        fs: {
            stat: async (uri: Uri) => {
                if (!files.has(uri.fsPath)) {
                    throw new Error('FileNotFound');
                }
                return {};
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

const originalLoad = Module._load;
Module._load = function (request: string, ...rest: any[]) {
    if (request === 'vscode') {
        return vscodeStub;
    }
    return originalLoad.call(this, request, ...rest);
};
