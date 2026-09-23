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
exports.deactivate = exports.activate = void 0;
const vscode = __importStar(require("vscode"));
const sidebarProvider_1 = require("./sidebarProvider");
const url_1 = require("url");
function activate(context) {
    const sidebarProvider = new sidebarProvider_1.SidebarProvider(context.extensionUri);
    context.subscriptions.push(vscode.window.registerWebviewViewProvider("certicode-labs.sidebar", sidebarProvider, {
        webviewOptions: {
            retainContextWhenHidden: true,
        }
    }));
    context.subscriptions.push(vscode.commands.registerCommand("certicode-labs.connectSession", () => {
        sidebarProvider.connectSessionPrompt();
    }));
    // Register URI Handler for deep linking (vscode://certicode.certicode-labs/connect)
    context.subscriptions.push(vscode.window.registerUriHandler({
        handleUri(uri) {
            const params = new url_1.URLSearchParams(uri.query);
            const sessionId = params.get('sessionId') || params.get('id') || params.get('session_id');
            const backendUrl = params.get('backendUrl') || params.get('endpoint') || params.get('url') || params.get('backend_url');
            const apiToken = params.get('apiToken') || params.get('token') || params.get('api_token');
            if (sessionId && backendUrl) {
                const parsedSessionId = parseInt(sessionId, 10);
                if (!isNaN(parsedSessionId)) {
                    sidebarProvider.connectToSession(backendUrl, parsedSessionId, apiToken || undefined);
                    vscode.commands.executeCommand('workbench.view.extension.certicode-explorer');
                    vscode.commands.executeCommand('certicode-labs.sidebar.focus');
                    vscode.window.showInformationMessage(`CertiCode: Connecting automatically to Lab Session #${parsedSessionId}...`);
                }
            }
        }
    }));
}
exports.activate = activate;
function deactivate() { }
exports.deactivate = deactivate;
//# sourceMappingURL=extension.js.map