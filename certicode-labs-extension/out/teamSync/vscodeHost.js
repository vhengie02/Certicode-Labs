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
exports.VscodeSyncHost = void 0;
const vscode = __importStar(require("vscode"));
const workspaceSync_1 = require("./workspaceSync");
/** One lab file, open as a VS Code document. */
class VscodeDocument {
    constructor(document) {
        this.document = document;
    }
    get isDirty() { return this.document.isDirty; }
    getText() {
        return this.document.getText();
    }
    replace(offset, length, text) {
        const edit = new vscode.WorkspaceEdit();
        edit.replace(this.document.uri, new vscode.Range(this.document.positionAt(offset), this.document.positionAt(offset + length)), text);
        return Promise.resolve(vscode.workspace.applyEdit(edit));
    }
    async save() {
        await this.document.save();
    }
}
/** The lab folder, as the workspace layer sees it. */
class VscodeSyncHost {
    constructor(root) {
        this.root = root;
    }
    /** The file under the lab folder, or undefined for a name that could point outside it. */
    uriFor(name) {
        return (0, workspaceSync_1.isShareableName)(name) ? vscode.Uri.joinPath(this.root, ...name.split('/')) : undefined;
    }
    /** The shared-file name of a document, or undefined when it is outside the lab folder. */
    nameOf(uri) {
        if (uri.scheme !== 'file') {
            return undefined;
        }
        const root = this.root.path.replace(/\/+$/, '') + '/';
        const path = uri.path;
        const inside = process.platform === 'win32' ? path.toLowerCase().startsWith(root.toLowerCase()) : path.startsWith(root);
        return inside ? path.slice(root.length) : undefined;
    }
    async openDocument(name, contentIfMissing) {
        const uri = this.uriFor(name);
        if (!uri) {
            return undefined;
        }
        try {
            await vscode.workspace.fs.stat(uri);
        }
        catch {
            try {
                await vscode.workspace.fs.writeFile(uri, Buffer.from(contentIfMissing, 'utf8'));
            }
            catch {
                return undefined;
            }
        }
        let document;
        try {
            document = await vscode.workspace.openTextDocument(uri);
        }
        catch {
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
    async readText(name) {
        const uri = this.uriFor(name);
        if (!uri) {
            return undefined;
        }
        const open = vscode.workspace.textDocuments.find(document => document.uri.toString() === uri.toString());
        try {
            const text = open ? open.getText() : new TextDecoder().decode(await vscode.workspace.fs.readFile(uri));
            return text.replace(/\r\n?/g, '\n');
        }
        catch {
            return undefined;
        }
    }
    /** Copies go under .certicode/before-shared-typing/, which is never shared (names starting with a dot aren't). */
    async writeBackup(name, content) {
        if (!this.uriFor(name)) {
            return;
        }
        const folder = vscode.Uri.joinPath(this.root, '.certicode', 'before-shared-typing', ...name.split('/').slice(0, -1));
        try {
            await vscode.workspace.fs.createDirectory(folder);
            await vscode.workspace.fs.writeFile(vscode.Uri.joinPath(folder, name.split('/').pop()), Buffer.from(content, 'utf8'));
        }
        catch {
            // Best effort: the copy is extra safety, not something the lab depends on
        }
    }
    async deleteFile(name) {
        const uri = this.uriFor(name);
        if (!uri) {
            return;
        }
        try {
            await vscode.workspace.fs.delete(uri, { useTrash: true });
        }
        catch {
            // Already gone
        }
    }
}
exports.VscodeSyncHost = VscodeSyncHost;
//# sourceMappingURL=vscodeHost.js.map