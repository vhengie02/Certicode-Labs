import * as vscode from 'vscode';
import { SidebarProvider } from './sidebarProvider';
import { URLSearchParams } from 'url';


export function activate(context: vscode.ExtensionContext) {
    const sidebarProvider = new SidebarProvider(context.extensionUri, context.globalState);
    context.subscriptions.push(sidebarProvider);

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
                const params = new URLSearchParams(uri.query);
                const sessionId = params.get('sessionId') || params.get('id') || params.get('session_id');
                const backendUrl = params.get('backendUrl') || params.get('endpoint') || params.get('url') || params.get('backend_url');
                const apiToken = params.get('apiToken') || params.get('token') || params.get('api_token');

                if (sessionId && backendUrl) {
                    const parsedSessionId = parseInt(sessionId, 10);
                    if (!isNaN(parsedSessionId)) {
                        // connectToSession asks before trusting a server the student has not used before.
                        sidebarProvider.connectToSession(backendUrl, parsedSessionId, apiToken || undefined).then(connected => {
                            if (connected) {
                                vscode.window.showInformationMessage(`CertiCode: Connecting to Lab Session #${parsedSessionId}...`);
                            }
                        });
                    }
                }
            }
        })
    );
}

export function deactivate() {}
