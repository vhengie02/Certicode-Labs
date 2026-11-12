import * as vscode from 'vscode';
import { HostDocument, isShareableName, SyncHost } from './workspaceSync';

/** One lab file, open as a VS Code document. */
class VscodeDocument implements HostDocument {
    constructor(private readonly document: vscode.TextDocument) {}

    get isDirty(): boolean { return this.document.isDirty; }

    getText(): string {
        return this.document.getText();
    }

    replace(offset: number, length: number, text: string): Promise<boolean> {
        const edit = new vscode.WorkspaceEdit();
        edit.replace(this.document.uri, new vscode.Range(this.document.positionAt(offset), this.document.positionAt(offset + length)), text);
        return Promise.resolve(vscode.workspace.applyEdit(edit));
    }

    async save(): Promise<void> {
        await this.document.save();
    }
}

/** The lab folder, as the workspace layer sees it. */
export class VscodeSyncHost implements SyncHost {
    constructor(private readonly root: vscode.Uri) {}

    /** The file under the lab folder, or undefined for a name that could point outside it. */
    public uriFor(name: string): vscode.Uri | undefined {
        return isShareableName(name) ? vscode.Uri.joinPath(this.root, ...name.split('/')) : undefined;
    }

    /** The shared-file name of a document, or undefined when it is outside the lab folder. */
    public nameOf(uri: vscode.Uri): string | undefined {
        if (uri.scheme !== 'file') { return undefined; }
        const root = this.root.path.replace(/\/+$/, '') + '/';
        const path = uri.path;
        const inside = process.platform === 'win32' ? path.toLowerCase().startsWith(root.toLowerCase()) : path.startsWith(root);
        return inside ? path.slice(root.length) : undefined;
    }

    async openDocument(name: string, contentIfMissing: string): Promise<HostDocument | undefined> {
        const uri = this.uriFor(name);
        if (!uri) { return undefined; }

        try {
            await vscode.workspace.fs.stat(uri);
        } catch {
            try {
                await vscode.workspace.fs.writeFile(uri, Buffer.from(contentIfMissing, 'utf8'));
            } catch {
                return undefined;
            }
        }

        let document: vscode.TextDocument;
        try {
            document = await vscode.workspace.openTextDocument(uri);
        } catch {
            return undefined;
        }

        // The team's text uses LF; a CRLF document would never equal it
        if (document.eol === vscode.EndOfLine.CRLF) {
            const edit = new vscode.WorkspaceEdit();
            edit.set(uri, [vscode.TextEdit.setEndOfLine(vscode.EndOfLine.LF)]);
            await vscode.workspace.applyEdit(edit);
        }

        return new VscodeDocument(document);
    }

    async deleteFile(name: string): Promise<void> {
        const uri = this.uriFor(name);
        if (!uri) { return; }
        try {
            await vscode.workspace.fs.delete(uri, { useTrash: true });
        } catch {
            // Already gone
        }
    }
}
