/**
 * A stand-in for the 'vscode' module, so the provider can be loaded and exercised by
 * Node's built-in test runner without starting VS Code. Import this before the provider.
 */
// eslint-disable-next-line @typescript-eslint/no-var-requires
const Module = require('module');

export const shown: { level: string; message: string }[] = [];

class Uri {
    constructor(public readonly fsPath: string) {}
    static file(path: string) { return new Uri(path); }
    static joinPath(base: Uri, ...parts: string[]) { return new Uri([base.fsPath, ...parts].join('/')); }
    toString() { return `file://${this.fsPath}`; }
}

const record = (level: string) => async (message: string) => {
    shown.push({ level, message });
    return undefined;
};

export const files = new Map<string, string>();

export const vscodeStub: any = {
    Uri,
    extensions: { getExtension: () => undefined },
    window: {
        showInformationMessage: record('info'),
        showWarningMessage: record('warning'),
        showErrorMessage: record('error'),
        activeTextEditor: undefined,
    },
    workspace: {
        workspaceFolders: [{ uri: Uri.file('/workspace') }],
        getConfiguration: () => ({ get: () => undefined }),
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
    env: { clipboard: { readText: async () => '' } },
};

const originalLoad = Module._load;
Module._load = function (request: string, ...rest: any[]) {
    if (request === 'vscode') {
        return vscodeStub;
    }
    return originalLoad.call(this, request, ...rest);
};
