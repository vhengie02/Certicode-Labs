import * as vscode from 'vscode';
import { SidebarProvider } from './sidebarProvider';
import { URLSearchParams } from 'url';


export function activate(context: vscode.ExtensionContext) {
    const sidebarProvider = new SidebarProvider(context.extensionUri);

    context.subscriptions.push(
        vscode.window.registerWebviewViewProvider(
            "certicode-labs.sidebar",
            sidebarProvider,
            {
                webviewOptions: {
                    retainContextWhenHidden: true,
                }
            }
        )
    );

    context.subscriptions.push(
        vscode.commands.registerCommand("certicode-labs.connectSession", () => {
            sidebarProvider.connectSessionPrompt();
        })
    );

    // Register URI Handler for deep linking (vscode://certicode.certicode-labs/connect)
    context.subscriptions.push(
        vscode.window.registerUriHandler({
            handleUri(uri: vscode.Uri) {
                if (uri.path === '/connect') {
                    const params = new URLSearchParams(uri.query);
                    const sessionId = params.get('sessionId');
                    const backendUrl = params.get('backendUrl');
                    const apiToken = params.get('apiToken');

                    if (sessionId && backendUrl) {
                        const parsedSessionId = parseInt(sessionId, 10);
                        if (!isNaN(parsedSessionId)) {
                            sidebarProvider.connectToSession(
                                backendUrl,
                                parsedSessionId,
                                apiToken || undefined
                            );
                            vscode.window.showInformationMessage(`CertiCode: Connecting to Lab Session #${sessionId}...`);
                        }
                    }
                }
            }
        })
    );
}

export function deactivate() {}
