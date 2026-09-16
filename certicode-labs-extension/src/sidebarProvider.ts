import * as vscode from 'vscode';
import * as http from 'http';
import * as https from 'https';
import { URL } from 'url';

export class SidebarProvider implements vscode.WebviewViewProvider {
    private _view?: vscode.WebviewView;
    private _sessionId?: number;
    private _backendUrl: string = 'http://localhost';
    private _apiToken?: string;
    private _pingInterval?: any;
    private _chatPollInterval?: any;
    private _isReconnecting: boolean = false;
    private _lastSessionData: any = null;

    // Feature 1: Starter Files & Integrity
    private _starterFileSnapshots: Map<string, string> = new Map();
    private _filesGenerated: boolean = false;
    private _fileWatcher?: vscode.FileSystemWatcher;
    private _missingFiles: string[] = [];

    // Feature 2: Diff Engine & Attribution
    private _diffDebounceTimer?: NodeJS.Timeout;
    private _lastDiffStats: { lines_added: number; lines_deleted: number; lines_modified: number; files: any[] } = {
        lines_added: 0,
        lines_deleted: 0,
        lines_modified: 0,
        files: []
    };

    // Feature 3: Team Chat
    private _unreadChatCount: number = 0;
    private _isChatTabActive: boolean = false;
    private _recentChatSnippets: string[] = [];

    // Feature 4: WPM Baseline & Paste Anomaly Interceptor
    private _keystrokeTimestamps: number[] = [];
    private _totalKeystrokes: number = 0;
    private _currentWpm: number = 0;
    private _wpmSyncTimer?: NodeJS.Timeout;

    // Feature 5: Focus Tracking & Live Leaderboard
    private _windowStateListener?: vscode.Disposable;
    private _leaderboardInterval?: any;

    constructor(private readonly _extensionUri: vscode.Uri) {}

    public resolveWebviewView(
        webviewView: vscode.WebviewView,
        context: vscode.WebviewViewResolveContext,
        _token: vscode.CancellationToken
    ) {
        this._view = webviewView;

        webviewView.webview.options = {
            enableScripts: true,
            localResourceRoots: [this._extensionUri]
        };

        webviewView.webview.html = this._getHtmlForWebview();

        // Listen for postMessages from Webview
        webviewView.webview.onDidReceiveMessage(async (data) => {
            switch (data.type) {
                case 'connect': {
                    this._backendUrl = data.backendUrl.replace(/\/$/, '');
                    this._sessionId = parseInt(data.sessionId);
                    this._apiToken = data.apiToken;
                    this.startMonitoring();
                    break;
                }
                case 'checkProgress': {
                    this.handleCheckProgress();
                    break;
                }
                case 'submit': {
                    this.handleSubmit();
                    break;
                }
                case 'exit': {
                    this.handleExit();
                    break;
                }
                case 'sendChat': {
                    this.handleSendChat(data.message, data.codeSnippet);
                    break;
                }
                case 'setChatTabActive': {
                    this._isChatTabActive = data.active;
                    if (data.active) {
                        this._unreadChatCount = 0;
                        this._view?.webview.postMessage({ type: 'unreadReset' });
                    }
                    break;
                }
                case 'retryFileGeneration': {
                    if (this._lastSessionData?.laboratory?.starter_files) {
                        await this.provisionStarterFiles(this._lastSessionData.laboratory.starter_files);
                    }
                    break;
                }
                case 'openFile': {
                    const workspaceFolders = vscode.workspace.workspaceFolders;
                    if (workspaceFolders && workspaceFolders.length > 0) {
                        const fileUri = vscode.Uri.joinPath(workspaceFolders[0].uri, data.name);
                        try {
                            const doc = await vscode.workspace.openTextDocument(fileUri);
                            await vscode.window.showTextDocument(doc, { preview: false });
                            break;
                        } catch {}
                    }
                    const sfile = (this._lastSessionData?.laboratory?.starter_files || []).find((f: any) => f.name === data.name);
                    if (sfile) {
                        const doc = await vscode.workspace.openTextDocument({
                            content: sfile.content || '',
                            language: this.detectLanguage(sfile.name)
                        });
                        await vscode.window.showTextDocument(doc, { preview: false });
                    }
                    break;
                }
                case 'getLeaderboard': {
                    await this.fetchLeaderboard();
                    break;
                }
            }
        });

        // Setup FileSystemWatcher and Document change tracking
        this.setupWorkspaceWatchers();
    }

    /**
     * Shows an input prompt to connect from VS Code commands palette
     */
    public async connectSessionPrompt() {
        const url = await vscode.window.showInputBox({
            prompt: 'Enter CertiCode Backend URL',
            value: this._backendUrl
        });
        if (!url) { return; }

        const session = await vscode.window.showInputBox({
            prompt: 'Enter Lab Session ID',
            placeHolder: 'e.g. 1'
        });
        if (!session) { return; }

        const token = await vscode.window.showInputBox({
            prompt: 'Enter API Token (Optional, leave blank for local prototyping)',
            value: ''
        });

        this._backendUrl = url.replace(/\/$/, '');
        this._sessionId = parseInt(session);
        this._apiToken = token || undefined;

        this._view?.webview.postMessage({
            type: 'prefill',
            backendUrl: this._backendUrl,
            sessionId: this._sessionId,
            apiToken: this._apiToken
        });

        this.startMonitoring();
    }

    /**
     * Connect directly to a session (called via deep-linking custom URI handler)
     */
    public connectToSession(backendUrl: string, sessionId: number, apiToken?: string) {
        this._backendUrl = backendUrl.replace(/\/$/, '');
        this._sessionId = sessionId;
        this._apiToken = apiToken;

        this._view?.webview.postMessage({
            type: 'prefill',
            backendUrl: this._backendUrl,
            sessionId: this._sessionId,
            apiToken: this._apiToken || ''
        });

        this.startMonitoring();

        // Focus the sidebar view in VS Code
        vscode.commands.executeCommand('workbench.view.extension.certicode-explorer');
    }

    private startMonitoring() {
        if (this._pingInterval) {
            clearInterval(this._pingInterval);
        }
        if (this._chatPollInterval) {
            clearInterval(this._chatPollInterval);
        }
        if (this._leaderboardInterval) {
            clearInterval(this._leaderboardInterval);
        }

        this._filesGenerated = false;
        this._unreadChatCount = 0;
        this._keystrokeTimestamps = [];
        this._totalKeystrokes = 0;
        this._currentWpm = 0;

        // Setup window focus tracking (Feature 5)
        this.setupFocusTracking();

        // Run sync immediately
        this.syncSessionState();
        this.fetchLeaderboard();

        // Run sync every 15 seconds to detect dropped connections / keepalive
        this._pingInterval = setInterval(() => {
            this.syncSessionState();
        }, 15000);

        // Run chat sync every 4 seconds
        this._chatPollInterval = setInterval(() => {
            this.fetchChatMessages();
        }, 4000);

        // Run leaderboard sync every 10 seconds (Feature 5)
        this._leaderboardInterval = setInterval(() => {
            this.fetchLeaderboard();
        }, 10000);
    }

    private async syncSessionState() {
        if (!this._sessionId) {
            return;
        }

        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const result = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}`);

            if (result.status === 200) {
                this._isReconnecting = false;
                const data = JSON.parse(result.body);
                this._lastSessionData = data;

                // Feature 1: Provision starter files on first connect
                if (!this._filesGenerated && data.laboratory?.starter_files && Array.isArray(data.laboratory.starter_files)) {
                    await this.provisionStarterFiles(data.laboratory.starter_files);
                } else {
                    await this.verifyWorkspaceFileIntegrity();
                }

                this._view?.webview.postMessage({
                    type: 'update',
                    data: data,
                    isReconnecting: false
                });

                // Fetch chats if group lab
                this.fetchChatMessages();
            } else if (result.status === 403) {
                this._isReconnecting = false;
                try {
                    const errData = JSON.parse(result.body);
                    this._view?.webview.postMessage({
                        type: 'liveLabBlocked',
                        error: errData.error || 'live_blocked',
                        message: errData.message || 'Access blocked for this Live Lab.'
                    });
                } catch {
                    this._view?.webview.postMessage({
                        type: 'liveLabBlocked',
                        error: 'live_blocked',
                        message: 'Access blocked for this Live Lab.'
                    });
                }
            } else {
                throw new Error(`Server returned status code: ${result.status}`);
            }
        } catch (err) {
            this._isReconnecting = true;
            this._view?.webview.postMessage({
                type: 'reconnecting',
                isReconnecting: true
            });
        }
    }

    /**
     * Helper to detect document language from file extension.
     */
    private detectLanguage(filename: string): string {
        const ext = filename.split('.').pop()?.toLowerCase() || '';
        switch (ext) {
            case 'c':
            case 'h':
                return 'c';
            case 'cpp':
            case 'cc':
            case 'cxx':
            case 'hpp':
                return 'cpp';
            case 'java':
                return 'java';
            case 'py':
                return 'python';
            case 'js':
            case 'jsx':
                return 'javascript';
            case 'ts':
            case 'tsx':
                return 'typescript';
            case 'sh':
            case 'bash':
                return 'shellscript';
            case 'html':
                return 'html';
            case 'css':
                return 'css';
            case 'json':
                return 'json';
            default:
                return 'plaintext';
        }
    }

    /**
     * Feature 1: Generate starter files in active workspace and open primary file.
     */
    private async provisionStarterFiles(starterFiles: Array<{ name: string; content: string; is_primary?: boolean; is_readonly?: boolean }>) {
        if (!starterFiles || starterFiles.length === 0) {
            return;
        }

        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) {
            // Fallback: Open files directly as active editor documents in VS Code!
            for (const file of starterFiles) {
                if (file.is_primary || starterFiles.length === 1) {
                    const lang = this.detectLanguage(file.name);
                    try {
                        const doc = await vscode.workspace.openTextDocument({
                            content: file.content || '',
                            language: lang
                        });
                        await vscode.window.showTextDocument(doc, { preview: false });
                    } catch (e) {
                        console.error('Failed to open untitled document', e);
                    }
                }
            }

            vscode.window.showInformationMessage(
                'CertiCode Labs: Starter code opened in editor! Open a workspace folder (File > Open Folder) to save files directly to disk.',
                'Open Folder'
            ).then(selection => {
                if (selection === 'Open Folder') {
                    vscode.commands.executeCommand('vscode.openFolder');
                }
            });
            return;
        }

        const rootUri = workspaceFolders[0].uri;
        let primaryUri: vscode.Uri | null = null;

        for (const file of starterFiles) {
            if (!file.name) { continue; }
            const fileUri = vscode.Uri.joinPath(rootUri, file.name);

            // Cache snapshot for diff engine
            if (!this._starterFileSnapshots.has(file.name)) {
                this._starterFileSnapshots.set(file.name, file.content || '');
            }

            let exists = false;
            try {
                await vscode.workspace.fs.stat(fileUri);
                exists = true;
            } catch {
                exists = false;
            }

            if (!exists) {
                const enc = new TextEncoder();
                await vscode.workspace.fs.writeFile(fileUri, enc.encode(file.content || ''));
            }

            if (file.is_primary) {
                primaryUri = fileUri;
            }
        }

        // Fallback to first file if none marked primary
        if (!primaryUri && starterFiles.length > 0) {
            primaryUri = vscode.Uri.joinPath(rootUri, starterFiles[0].name);
        }

        // Auto-open primary starter file
        if (primaryUri) {
            try {
                const doc = await vscode.workspace.openTextDocument(primaryUri);
                await vscode.window.showTextDocument(doc, { preview: false });
            } catch (e) {
                console.error('Failed to open primary document', e);
            }
        }

        this._filesGenerated = true;
        await this.verifyWorkspaceFileIntegrity();
        await this.computeAndSyncDiffs();
    }

    /**
     * Feature 1: Filename integrity watcher - verify all required starter files exist.
     */
    private async verifyWorkspaceFileIntegrity() {
        if (!this._lastSessionData?.laboratory?.starter_files) { return; }

        const starterFiles: Array<{ name: string; is_readonly?: boolean }> = this._lastSessionData.laboratory.starter_files;
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) { return; }

        const rootUri = workspaceFolders[0].uri;
        const missing: string[] = [];

        for (const file of starterFiles) {
            if (!file.name) { continue; }
            const fileUri = vscode.Uri.joinPath(rootUri, file.name);
            try {
                await vscode.workspace.fs.stat(fileUri);
            } catch {
                missing.push(file.name);
            }
        }

        this._missingFiles = missing;
        this._view?.webview.postMessage({
            type: 'integrityStatus',
            valid: missing.length === 0,
            missing: missing
        });
    }

    /**
     * Feature 2: Workspace watchers for file integrity and code change diffs
     */
    private setupWorkspaceWatchers() {
        if (this._fileWatcher) {
            this._fileWatcher.dispose();
        }

        this._fileWatcher = vscode.workspace.createFileSystemWatcher('**/*');
        this._fileWatcher.onDidCreate(() => this.verifyWorkspaceFileIntegrity());
        this._fileWatcher.onDidDelete(() => this.verifyWorkspaceFileIntegrity());

        // Watch document changes for live diff tracking, WPM baseline, and paste anomaly detection
        vscode.workspace.onDidChangeTextDocument((event) => {
            if (!this._sessionId || !this._lastSessionData) { return; }
            
            const fileName = event.document.fileName;
            const isTracked = this._lastSessionData.laboratory?.starter_files?.some((f: any) => 
                fileName.endsWith(f.name)
            );

            // Feature 4: Keystroke velocity & Paste anomaly interceptor
            for (const change of event.contentChanges) {
                if (change.text.length === 1) {
                    this.recordKeystroke(1);
                } else if (change.text.length > 1 && change.text.length < 25) {
                    this.recordKeystroke(change.text.length);
                } else if (change.text.length >= 25 && (change.text.includes('\n') || change.text.split(/\s+/).length > 3)) {
                    // Bulk code insertion detected!
                    const trimmedPaste = change.text.trim();

                    // Suppression Rule A: Internal Move/Restructure check
                    let isSuppressedA = false;
                    for (const [, snapshotContent] of this._starterFileSnapshots.entries()) {
                        if (snapshotContent.includes(trimmedPaste)) {
                            isSuppressedA = true;
                            break;
                        }
                    }

                    // Suppression Rule B: Permitted Collaboration / Team Chat match
                    let isSuppressedB = false;
                    if (!isSuppressedA && this._recentChatSnippets.length > 0) {
                        isSuppressedB = this._recentChatSnippets.some(snippet =>
                            snippet.includes(trimmedPaste) || trimmedPaste.includes(snippet)
                        );
                    }

                    // Flagging Rule: External paste without internal/chat justification
                    if (!isSuppressedA && !isSuppressedB) {
                        this.sendTelemetry('paste_anomaly', {
                            pasted_length: change.text.length,
                            snippet: change.text.slice(0, 100),
                            file: fileName,
                            wpm: this._currentWpm
                        });
                    }
                }
            }

            if (isTracked) {
                if (this._diffDebounceTimer) {
                    clearTimeout(this._diffDebounceTimer);
                }
                this._diffDebounceTimer = setTimeout(() => {
                    this.computeAndSyncDiffs();
                }, 2000);
            }
        });

        vscode.workspace.onDidSaveTextDocument(() => {
            if (this._sessionId && this._lastSessionData) {
                this.computeAndSyncDiffs();
            }
        });
    }

    /**
     * Feature 5: OS-Level Focus Loss Sensor
     */
    private setupFocusTracking() {
        if (this._windowStateListener) {
            this._windowStateListener.dispose();
        }

        this._windowStateListener = vscode.window.onDidChangeWindowState((state) => {
            if (!this._sessionId) { return; }
            if (!state.focused) {
                // OS Focus lost (switched away from VS Code)
                this.sendTelemetry('focus_lost', {
                    timestamp: new Date().toISOString()
                });
            }
        });
    }

    /**
     * Feature 4: Record typing keystrokes and calculate rolling WPM
     */
    private recordKeystroke(charsCount: number = 1) {
        const now = Date.now();
        for (let i = 0; i < charsCount; i++) {
            this._keystrokeTimestamps.push(now);
            this._totalKeystrokes++;
        }

        // Keep timestamps within 60s moving window
        const cutoff = now - 60000;
        this._keystrokeTimestamps = this._keystrokeTimestamps.filter(t => t >= cutoff);

        // Approximate WPM (5 chars = 1 word)
        const windowMinutes = Math.max((now - (this._keystrokeTimestamps[0] || now)) / 60000, 1 / 6);
        const words = this._keystrokeTimestamps.length / 5;
        this._currentWpm = Math.round(words / windowMinutes);

        // Debounced telemetry sync to backend
        if (!this._wpmSyncTimer) {
            this._wpmSyncTimer = setTimeout(() => {
                this._wpmSyncTimer = undefined;
                this.sendTelemetry('wpm_update', {
                    wpm: this._currentWpm,
                    keystroke_count: this._totalKeystrokes
                });
            }, 10000);
        }
    }

    /**
     * Helper to dispatch telemetry logs & anomalies to backend
     */
    private async sendTelemetry(eventType: string, payload: any) {
        if (!this._sessionId) { return; }
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/telemetry`, {
                event_type: eventType,
                payload: payload
            });
        } catch (e) {
            console.error('Failed to send telemetry', e);
        }
    }

    /**
     * Feature 5: Fetch live session leaderboard
     */
    private async fetchLeaderboard() {
        if (!this._sessionId) { return; }
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const res = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}/leaderboard`);
            if (res.status === 200) {
                const data = JSON.parse(res.body);
                this._view?.webview.postMessage({
                    type: 'leaderboardData',
                    leaderboard: data.leaderboard || [],
                    availability_mode: data.availability_mode || 'open',
                    shared_time_remaining_formatted: data.shared_time_remaining_formatted,
                    live_status: data.live_status
                });
            }
        } catch (e) {
            console.error('Failed to fetch leaderboard', e);
        }
    }

    /**
     * Feature 2: Compute line diffs and sync with backend
     */
    private async computeAndSyncDiffs() {
        if (!this._sessionId || !this._lastSessionData) { return; }

        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) { return; }

        const starterFiles = this._lastSessionData.laboratory?.starter_files || [];
        let totalAdded = 0;
        let totalDeleted = 0;
        let totalModified = 0;
        const filesSummary: Array<{ name: string; added: number; deleted: number; modified: number }> = [];

        for (const sfile of starterFiles) {
            const initialContent = this._starterFileSnapshots.get(sfile.name) ?? (sfile.content || '');
            const fileUri = vscode.Uri.joinPath(workspaceFolders[0].uri, sfile.name);

            let currentContent = '';
            try {
                const data = await vscode.workspace.fs.readFile(fileUri);
                currentContent = new TextDecoder().decode(data);
            } catch {
                continue;
            }

            const diff = this.calculateLineDiff(initialContent, currentContent);
            totalAdded += diff.added;
            totalDeleted += diff.deleted;
            totalModified += diff.modified;

            filesSummary.push({
                name: sfile.name,
                added: diff.added,
                deleted: diff.deleted,
                modified: diff.modified
            });
        }

        this._lastDiffStats = {
            lines_added: totalAdded,
            lines_deleted: totalDeleted,
            lines_modified: totalModified,
            files: filesSummary
        };

        // Notify Webview with live diffs
        this._view?.webview.postMessage({
            type: 'diffUpdate',
            diffStats: this._lastDiffStats
        });

        // Transmit diff payload to backend
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/diff`, {
                lines_added: totalAdded,
                lines_deleted: totalDeleted,
                lines_modified: totalModified,
                files: filesSummary
            });
        } catch (e) {
            console.error('Failed to sync diff to backend', e);
        }
    }

    /**
     * Calculate line additions, deletions, and modifications relative to snapshot.
     */
    private calculateLineDiff(initial: string, current: string): { added: number; deleted: number; modified: number } {
        const initialLines = initial.split(/\r?\n/);
        const currentLines = current.split(/\r?\n/);

        const initialSet = new Set(initialLines.map(l => l.trim()));
        const currentSet = new Set(currentLines.map(l => l.trim()));

        let rawAdded = 0;
        for (const line of currentLines) {
            if (line.trim().length > 0 && !initialSet.has(line.trim())) {
                rawAdded++;
            }
        }

        let rawDeleted = 0;
        for (const line of initialLines) {
            if (line.trim().length > 0 && !currentSet.has(line.trim())) {
                rawDeleted++;
            }
        }

        const modified = Math.min(rawAdded, rawDeleted);
        return {
            added: Math.max(0, rawAdded - modified),
            deleted: Math.max(0, rawDeleted - modified),
            modified: modified
        };
    }

    /**
     * Feature 3: Fetch ephemeral chat messages
     */
    private async fetchChatMessages() {
        if (!this._sessionId) { return; }
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const res = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}/chat`);
            if (res.status === 200) {
                const data = JSON.parse(res.body);
                const chats = data.chats || [];
                
                // Cache recent chat snippets for paste suppression rule B
                this._recentChatSnippets = [];
                for (const c of chats) {
                    if (c.message && typeof c.message === 'string') {
                        this._recentChatSnippets.push(c.message.trim());
                    }
                    if (c.code_snippet && typeof c.code_snippet === 'string') {
                        this._recentChatSnippets.push(c.code_snippet.trim());
                    }
                }

                if (!this._isChatTabActive && chats.length > 0) {
                    this._unreadChatCount = chats.length;
                }

                this._view?.webview.postMessage({
                    type: 'chatUpdate',
                    chats: chats,
                    unreadCount: this._unreadChatCount
                });
            }
        } catch (e) {
            console.error('Failed to fetch chat messages', e);
        }
    }

    /**
     * Feature 3: Send ephemeral chat message
     */
    private async handleSendChat(message: string, codeSnippet?: string) {
        if (!this._sessionId) { return; }
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const res = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/chat`, {
                message,
                code_snippet: codeSnippet || null
            });
            if (res.status === 200) {
                await this.fetchChatMessages();
            }
        } catch (err: any) {
            vscode.window.showErrorMessage(`Send Chat Error: ${err.message}`);
        }
    }

    private async handleCheckProgress() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }

        // Collect all workspace files for evaluation
        const workspaceFolders = vscode.workspace.workspaceFolders;
        let primaryCode = '';
        let primaryFileName = '';
        const filesPayload: Array<{ name: string; content: string; is_primary: boolean }> = [];

        if (workspaceFolders && workspaceFolders.length > 0) {
            const starterFiles = this._lastSessionData?.laboratory?.starter_files || [];
            for (const sfile of starterFiles) {
                const fileUri = vscode.Uri.joinPath(workspaceFolders[0].uri, sfile.name);
                try {
                    const data = await vscode.workspace.fs.readFile(fileUri);
                    const content = new TextDecoder().decode(data);
                    filesPayload.push({
                        name: sfile.name,
                        content: content,
                        is_primary: !!sfile.is_primary
                    });
                    if (sfile.is_primary || !primaryCode) {
                        primaryCode = content;
                        primaryFileName = sfile.name;
                    }
                } catch {}
            }
        }

        const isNonCodeDoc = (name: string): boolean => {
            return /\.(md|txt|json|env|log|lock|ya?ml)$/i.test(name) || name.includes('.git');
        };

        // Fallback to active editor text only if it is an actual code file
        if (!primaryCode) {
            const activeEditor = vscode.window.activeTextEditor;
            if (activeEditor) {
                const fname = activeEditor.document.fileName;
                if (!isNonCodeDoc(fname)) {
                    primaryCode = activeEditor.document.getText();
                    primaryFileName = fname;
                }
            }
        }

        // Also search workspace for actual source code files if primaryCode still not found
        if (!primaryCode && workspaceFolders && workspaceFolders.length > 0) {
            try {
                const codeFiles = await vscode.workspace.findFiles(
                    '**/*.{c,cpp,h,java,py,js,ts,go,rs,cs,php}',
                    '**/{node_modules,vendor,.git,build,out,dist}/**',
                    10
                );
                for (const uri of codeFiles) {
                    const data = await vscode.workspace.fs.readFile(uri);
                    const content = new TextDecoder().decode(data);
                    const relPath = vscode.workspace.asRelativePath(uri);
                    filesPayload.push({
                        name: relPath,
                        content: content,
                        is_primary: !primaryCode
                    });
                    if (!primaryCode) {
                        primaryCode = content;
                        primaryFileName = relPath;
                    }
                }
            } catch {}
        }

        if (!primaryCode && filesPayload.length === 0) {
            vscode.window.showErrorMessage('No code files found in workspace. Please open or create your solution file (e.g. main.c, Solution.java) to check progress.');
            this._view?.webview.postMessage({
                type: 'checkResult',
                data: {
                    status: 'empty',
                    completed_tasks: [],
                    performance_score: 0,
                    evaluation: {
                        tasks: (this._lastSessionData?.laboratory?.tasks_definition || []).map((t: any) => ({
                            id: t.id,
                            completed: false,
                            feedback: 'No solution code file detected in workspace.'
                        })),
                        correctness_score: 0,
                        overall_feedback: 'No solution code detected in workspace. Create your code file and check progress again.',
                        code_quality_feedback: 'Workspace has no code to evaluate.'
                    }
                }
            });
            return;
        }

        let detectedLang = 'c';
        if (primaryFileName) {
            detectedLang = this.detectLanguage(primaryFileName);
        } else if (this._lastSessionData?.laboratory?.starter_files?.[0]?.name) {
            detectedLang = this.detectLanguage(this._lastSessionData.laboratory.starter_files[0].name);
        }

        this._view?.webview.postMessage({ type: 'status', message: 'Analyzing code with AI evaluator...' });

        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/check-progress`, {
                code: primaryCode,
                files: filesPayload,
                language: detectedLang
            });

            if (result.status === 200) {
                const responseData = JSON.parse(result.body);
                vscode.window.showInformationMessage('Check Progress: AI evaluation completed.');
                this._view?.webview.postMessage({
                    type: 'checkResult',
                    data: responseData
                });
                this.syncSessionState();
            } else {
                const responseData = JSON.parse(result.body);
                vscode.window.showErrorMessage(`Check Progress Failed: ${responseData.error || 'Server error'}`);
                this._view?.webview.postMessage({
                    type: 'error',
                    message: responseData.error || 'Check progress failed.'
                });
            }
        } catch (err: any) {
            vscode.window.showErrorMessage(`Check Progress Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }

    private async handleSubmit() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }

        // Feature 1 Client-Side Filename Integrity Check
        if (this._missingFiles && this._missingFiles.length > 0) {
            vscode.window.showErrorMessage(`Submission blocked: Missing required file(s): ${this._missingFiles.join(', ')}. Please restore or recreate the file to submit.`);
            return;
        }

        const workspaceFolders = vscode.workspace.workspaceFolders;
        let primaryCode = '';
        let primaryFileName = '';
        const filesPayload: Array<{ name: string; content: string; is_primary: boolean }> = [];

        if (workspaceFolders && workspaceFolders.length > 0) {
            const starterFiles = this._lastSessionData?.laboratory?.starter_files || [];
            for (const sfile of starterFiles) {
                const fileUri = vscode.Uri.joinPath(workspaceFolders[0].uri, sfile.name);
                try {
                    const data = await vscode.workspace.fs.readFile(fileUri);
                    const content = new TextDecoder().decode(data);
                    filesPayload.push({
                        name: sfile.name,
                        content: content,
                        is_primary: !!sfile.is_primary
                    });
                    if (sfile.is_primary || !primaryCode) {
                        primaryCode = content;
                        primaryFileName = sfile.name;
                    }
                } catch {}
            }
        }

        const isNonCodeDoc = (name: string): boolean => {
            return /\.(md|txt|json|env|log|lock|ya?ml)$/i.test(name) || name.includes('.git');
        };

        if (!primaryCode) {
            const activeEditor = vscode.window.activeTextEditor;
            if (activeEditor) {
                const fname = activeEditor.document.fileName;
                if (!isNonCodeDoc(fname)) {
                    primaryCode = activeEditor.document.getText();
                    primaryFileName = fname;
                }
            }
        }

        if (!primaryCode && workspaceFolders && workspaceFolders.length > 0) {
            try {
                const codeFiles = await vscode.workspace.findFiles(
                    '**/*.{c,cpp,h,java,py,js,ts,go,rs,cs,php}',
                    '**/{node_modules,vendor,.git,build,out,dist}/**',
                    10
                );
                for (const uri of codeFiles) {
                    const data = await vscode.workspace.fs.readFile(uri);
                    const content = new TextDecoder().decode(data);
                    const relPath = vscode.workspace.asRelativePath(uri);
                    filesPayload.push({
                        name: relPath,
                        content: content,
                        is_primary: !primaryCode
                    });
                    if (!primaryCode) {
                        primaryCode = content;
                        primaryFileName = relPath;
                    }
                }
            } catch {}
        }

        if (!primaryCode && filesPayload.length === 0) {
            vscode.window.showErrorMessage('No runnable code files found in workspace. Please create or open your solution file before submitting.');
            return;
        }

        let detectedLang = 'c';
        if (primaryFileName) {
            detectedLang = this.detectLanguage(primaryFileName);
        } else if (this._lastSessionData?.laboratory?.starter_files?.[0]?.name) {
            detectedLang = this.detectLanguage(this._lastSessionData.laboratory.starter_files[0].name);
        }

        const confirm = await vscode.window.showWarningMessage(
            'Submit Lab Solution: Are you sure you want to finalize your submission? This compiles, runs test cases, evaluates competencies, and completes your session.',
            { modal: true },
            'Yes, Submit'
        );

        if (confirm !== 'Yes, Submit') {
            return;
        }

        this._view?.webview.postMessage({ type: 'status', message: 'Running final submission & compilation...' });

        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/submit`, {
                code: primaryCode,
                files: filesPayload,
                language: detectedLang
            });

            if (result.status === 200) {
                const responseData = JSON.parse(result.body);
                vscode.window.showInformationMessage(`Lab Session Completed! Score: ${responseData.performance_score}%`);
                
                this._view?.webview.postMessage({
                    type: 'submitResult',
                    data: responseData
                });

                if (this._pingInterval) { clearInterval(this._pingInterval); }
                if (this._chatPollInterval) { clearInterval(this._chatPollInterval); }
            } else {
                const responseData = JSON.parse(result.body);
                vscode.window.showErrorMessage(`Submission Failed: ${responseData.error || 'Server error'}`);
                this._view?.webview.postMessage({
                    type: 'error',
                    message: responseData.error || 'Submission failed.'
                });
            }
        } catch (err: any) {
            vscode.window.showErrorMessage(`Submission Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }

    private async handleExit() {
        const confirm = await vscode.window.showWarningMessage(
            'Are you sure you want to exit this workspace? Any unsaved editor content or unsubmitted progress will remain pending.',
            { modal: true },
            'Confirm Exit'
        );

        if (confirm !== 'Confirm Exit') {
            return;
        }

        if (this._pingInterval) { clearInterval(this._pingInterval); }
        if (this._chatPollInterval) { clearInterval(this._chatPollInterval); }
        if (this._leaderboardInterval) { clearInterval(this._leaderboardInterval); }
        if (this._wpmSyncTimer) { clearTimeout(this._wpmSyncTimer); }
        if (this._windowStateListener) { this._windowStateListener.dispose(); }

        this._sessionId = undefined;
        this._lastSessionData = null;
        this._filesGenerated = false;
        this._starterFileSnapshots.clear();
        this._missingFiles = [];
        this._keystrokeTimestamps = [];
        this._totalKeystrokes = 0;
        this._currentWpm = 0;
        this._recentChatSnippets = [];
        this._view?.webview.postMessage({ type: 'disconnected' });
        vscode.window.showInformationMessage('Exited CertiCode Labs session.');
    }

    /**
     * Native HTTP/HTTPS client requests.
     */
    private makeRequest(
        method: 'GET' | 'POST',
        path: string,
        bodyData?: any
    ): Promise<{ status: number; body: string }> {
        return new Promise((resolve, reject) => {
            try {
                const targetUrl = new URL(`${this._backendUrl}${path}`);
                const headers: Record<string, string> = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };

                if (this._apiToken) {
                    headers['Authorization'] = `Bearer ${this._apiToken}`;
                }

                const options = {
                    method: method,
                    hostname: targetUrl.hostname,
                    port: targetUrl.port ? parseInt(targetUrl.port, 10) : (targetUrl.protocol === 'https:' ? 443 : 80),
                    path: targetUrl.pathname + targetUrl.search,
                    headers: headers
                };

                const handleResponse = (res: http.IncomingMessage) => {
                    let responseData = '';
                    res.on('data', (chunk: any) => {
                        responseData += chunk;
                    });
                    res.on('end', () => {
                        resolve({
                            status: res.statusCode || 0,
                            body: responseData
                        });
                    });
                };

                const req = targetUrl.protocol === 'https:'
                    ? https.request(options, handleResponse)
                    : http.request(options, handleResponse);

                req.on('error', (err: Error) => {
                    reject(err);
                });

                if (bodyData) {
                    req.write(JSON.stringify(bodyData));
                }
                req.end();
            } catch (err) {
                reject(err as Error);
            }
        });
    }

    private _getHtmlForWebview(): string {
        return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertiCode Labs IDE</title>
    <style>
        body {
            font-family: var(--vscode-font-family, sans-serif);
            font-size: var(--vscode-font-size, 13px);
            color: var(--vscode-foreground);
            background-color: var(--vscode-sideBar-background);
            padding: 8px;
            margin: 0;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .header {
            font-weight: bold;
            font-size: 1.15em;
            margin-bottom: 4px;
            color: var(--vscode-sideBarTitle-foreground);
        }
        .sub-header {
            color: var(--vscode-descriptionForeground);
            font-size: 0.9em;
            line-height: 1.35;
        }
        .connection-status {
            padding: 5px 8px;
            font-size: 0.85em;
            border-radius: 3px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-left {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .status-connected {
            background-color: rgba(62, 207, 142, 0.12);
            color: #3ecf8e;
            border: 1px solid rgba(62, 207, 142, 0.25);
        }
        .status-connected .status-dot {
            background-color: #3ecf8e;
        }
        .status-reconnecting {
            background-color: rgba(245, 166, 35, 0.15);
            color: #f5a623;
            border: 1px solid rgba(245, 166, 35, 0.3);
            animation: pulse 1.5s infinite;
        }
        .status-reconnecting .status-dot {
            background-color: #f5a623;
        }
        .diff-pill {
            font-size: 0.8em;
            font-family: var(--vscode-editor-font-family, monospace);
            padding: 2px 6px;
            border-radius: 10px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--vscode-panel-border);
        }
        .diff-added { color: #3ecf8e; }
        .diff-deleted { color: #f87171; }

        /* Feature 1 Integrity Warning Banner */
        .integrity-alert {
            background-color: rgba(248, 113, 113, 0.12);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fca5a5;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.35;
        }
        .integrity-alert strong { color: #f87171; }

        /* Navigation Tabs */
        .tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--vscode-panel-border);
            gap: 2px;
            margin-top: 4px;
        }
        .tab-btn {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--vscode-descriptionForeground);
            padding: 6px 10px;
            font-size: 0.85em;
            font-family: inherit;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: var(--vscode-foreground);
        }
        .tab-btn.active {
            color: #3ecf8e;
            border-bottom: 2px solid #3ecf8e;
        }
        .tab-badge {
            font-size: 0.75em;
            background-color: #3ecf8e;
            color: #0f0f0f;
            border-radius: 8px;
            padding: 1px 5px;
            font-weight: bold;
        }

        .tab-content {
            display: none;
            flex-direction: column;
            gap: 10px;
        }
        .tab-content.active {
            display: flex;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        label {
            font-weight: 600;
            font-size: 0.85em;
        }
        input, textarea {
            background-color: var(--vscode-input-background);
            color: var(--vscode-input-foreground);
            border: 1px solid var(--vscode-input-border, transparent);
            padding: 6px 8px;
            border-radius: 2px;
            font-family: inherit;
            font-size: 0.9em;
        }
        input:focus, textarea:focus {
            outline: 1px solid var(--vscode-focusBorder);
        }
        button {
            background-color: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
            border: none;
            padding: 7px 12px;
            border-radius: 2px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9em;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.2s;
        }
        button:hover {
            background-color: var(--vscode-button-hoverBackground);
        }
        button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .btn-secondary {
            background-color: var(--vscode-button-secondaryBackground, #3a3a3a);
            color: var(--vscode-button-secondaryForeground, #ffffff);
        }
        .btn-secondary:hover {
            background-color: var(--vscode-button-secondaryHoverBackground, #4a4a4a);
        }
        .timer-box {
            font-size: 1.25em;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            background-color: var(--vscode-editor-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .timer-label {
            font-size: 0.65em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--vscode-descriptionForeground);
        }
        .card {
            background-color: var(--vscode-editor-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
            padding: 8px 10px;
        }
        .leaderboard-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 9px;
            border-radius: 4px;
            background: var(--vscode-sideBar-background);
            border: 1px solid var(--vscode-panel-border);
            font-size: 0.85em;
            gap: 6px;
        }
        .leaderboard-row.current-user {
            border-color: #3ecf8e;
            background: rgba(62, 207, 142, 0.08);
        }
        .rank-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75em;
            background: var(--vscode-panel-border);
            color: var(--vscode-foreground);
            flex-shrink: 0;
        }
        .rank-1 { background: #f59e0b; color: #000; }
        .rank-2 { background: #94a3b8; color: #000; }
        .rank-3 { background: #b45309; color: #fff; }
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .task-item {
            display: flex;
            flex-direction: column;
            padding: 7px;
            border-radius: 3px;
            border: 1px solid var(--vscode-panel-border);
            background-color: var(--vscode-sideBar-background);
        }
        .task-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 6px;
        }
        .task-title {
            font-weight: 500;
            line-height: 1.3;
            font-size: 0.92em;
        }
        .task-badge {
            font-size: 0.75em;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: bold;
            white-space: nowrap;
        }
        .badge-pending {
            background-color: rgba(245, 166, 35, 0.15);
            color: #f5a623;
            border: 1px solid rgba(245, 166, 35, 0.3);
        }
        .badge-complete {
            background-color: rgba(62, 207, 142, 0.15);
            color: #3ecf8e;
            border: 1px solid rgba(62, 207, 142, 0.3);
        }
        .task-feedback {
            font-size: 0.85em;
            color: var(--vscode-descriptionForeground);
            margin-top: 5px;
            border-top: 1px dashed var(--vscode-panel-border);
            padding-top: 5px;
        }
        .feedback-box {
            font-style: italic;
            color: var(--vscode-descriptionForeground);
            padding: 8px;
            border-radius: 3px;
            background-color: rgba(255, 255, 255, 0.03);
            border-left: 3px solid #3ecf8e;
        }
        .console-output {
            background-color: #141414;
            color: #ededed;
            font-family: var(--vscode-editor-font-family, monospace);
            font-size: 0.85em;
            padding: 8px;
            border-radius: 3px;
            white-space: pre-wrap;
            max-height: 120px;
            overflow-y: auto;
            border: 1px solid var(--vscode-panel-border);
        }

        /* Starter files list styling */
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            border-radius: 4px;
            background: var(--vscode-sideBar-background);
            border: 1px solid var(--vscode-panel-border);
            font-family: var(--vscode-editor-font-family, monospace);
            font-size: 0.85em;
            cursor: pointer;
            user-select: none;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .file-item:hover {
            background-color: var(--vscode-list-hoverBackground, rgba(255, 255, 255, 0.08));
            border-color: #3ecf8e;
        }
        .file-item:active {
            opacity: 0.8;
        }
        .file-badge {
            font-size: 0.75em;
            padding: 1px 5px;
            border-radius: 6px;
        }

        /* Feature 3: Team Chat Styles */
        .chat-feed {
            display: flex;
            flex-direction: column;
            gap: 8px;
            height: 220px;
            overflow-y: auto;
            padding: 4px;
            background-color: var(--vscode-editor-background);
            border: 1px solid var(--vscode-panel-border);
            border-radius: 4px;
        }
        .chat-msg {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 6px 8px;
            border-radius: 4px;
            background-color: var(--vscode-sideBar-background);
            border: 1px solid var(--vscode-panel-border);
        }
        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8em;
        }
        .chat-author {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: bold;
        }
        .chat-avatar {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
        }
        .chat-time {
            color: var(--vscode-descriptionForeground);
            font-size: 0.85em;
        }
        .chat-text {
            font-size: 0.9em;
            word-break: break-word;
        }
        .chat-snippet {
            margin-top: 4px;
            padding: 4px 6px;
            background-color: #0e0e0e;
            border: 1px solid var(--vscode-panel-border);
            border-radius: 2px;
            font-family: var(--vscode-editor-font-family, monospace);
            font-size: 0.82em;
            color: #3ecf8e;
            white-space: pre-wrap;
            max-height: 80px;
            overflow-y: auto;
        }
        .chat-input-row {
            display: flex;
            gap: 4px;
        }

        /* Actions */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 6px;
        }
        .loader-status {
            font-style: italic;
            color: var(--vscode-descriptionForeground);
            text-align: center;
            margin-top: 6px;
            font-size: 0.88em;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    </style>
</head>
<body>
    <div id="connection-screen" class="container">
        <div class="header">Connect to Lab Session</div>
        <div class="sub-header">Enter details below to establish connection with CertiCode Labs platform.</div>
        
        <div class="form-group">
            <label for="backend-url">Backend Endpoint</label>
            <input type="text" id="backend-url" value="http://localhost">
        </div>
        
        <div class="form-group">
            <label for="session-id">Lab Session ID</label>
            <input type="text" id="session-id" placeholder="e.g. 1" value="1">
        </div>
        
        <div class="form-group">
            <label for="api-token">API Token (Optional)</label>
            <input type="password" id="api-token" placeholder="Optional token">
        </div>
        
        <button id="connect-btn">Connect Session</button>
    </div>

    <div id="session-screen" class="container" style="display: none;">
        <!-- Connection & Live Diff Status -->
        <div id="status-banner" class="connection-status status-connected">
            <div class="status-left">
                <div class="status-dot"></div>
                <span id="status-text">Connected</span>
            </div>
            <div class="diff-pill" id="diff-counter-pill">
                <span class="diff-added" id="pill-added">+0</span> / <span class="diff-deleted" id="pill-deleted">-0</span>
            </div>
        </div>

        <!-- Feature 1 Client-Side Integrity Warning -->
        <div id="integrity-alert" class="integrity-alert" style="display: none;">
            <span>⚠️</span>
            <div class="flex-1">
                <span id="integrity-text">Missing required starter file.</span>
                <div style="margin-top: 4px;">
                    <a href="#" id="regenerate-files-link" style="color: #3ecf8e; text-decoration: underline; font-size: 0.9em;">Re-generate missing starter files</a>
                </div>
            </div>
        </div>

        <!-- Feature 9 Live Lab State Alert -->
        <div id="live-lab-alert" class="integrity-alert" style="display: none; background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.35); color: #fca5a5;">
            <span>⏱️</span>
            <div class="flex-1">
                <span id="live-lab-alert-text" style="font-weight: bold;">Live Lab Alert</span>
            </div>
        </div>

        <!-- Timer -->
        <div class="timer-box">
            <span id="timer-display">00:00</span>
            <span id="timer-label" class="timer-label">Time Remaining</span>
        </div>

        <!-- Tabbed Navigation -->
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="tab-instructions">Instructions</button>
            <button class="tab-btn" data-tab="tab-tasks">Tasks</button>
            <button class="tab-btn" data-tab="tab-leaderboard" id="tab-leaderboard-btn">Leaderboard</button>
            <button class="tab-btn" data-tab="tab-diff">Diff View</button>
            <button class="tab-btn" data-tab="tab-chat" id="tab-chat-btn">
                Team Chat
                <span class="tab-badge" id="chat-badge" style="display: none;">0</span>
            </button>
        </div>

        <!-- TAB 1: Instructions & Starter Files -->
        <div id="tab-instructions" class="tab-content active">
            <div class="card">
                <div class="header" id="lab-title">Lab Title</div>
                <div class="sub-header" id="lab-desc" style="max-height: 120px; overflow-y: auto;">Lab description...</div>
            </div>

            <div class="card">
                <label style="margin-bottom: 6px; display: block;">Starter Workspace Files</label>
                <div id="starter-files-list" style="display: flex; flex-direction: column; gap: 4px;">
                    <!-- Starter files populated dynamically -->
                </div>
            </div>
        </div>

        <!-- TAB 2: Tasks Checklist & Evaluation -->
        <div id="tab-tasks" class="tab-content">
            <div class="task-list" id="tasks-container">
                <!-- Tasks populated dynamically -->
            </div>

            <div id="llm-feedback-section" style="display: none;">
                <label style="margin-bottom: 4px; display: block;">AI Rubric Feedback</label>
                <div class="feedback-box" id="llm-feedback-text"></div>
            </div>

            <div id="console-output-section" style="display: none;">
                <label style="margin-bottom: 4px; display: block;">Console Output</label>
                <div class="console-output" id="console-output-text"></div>
            </div>
        </div>

        <!-- TAB: Live Leaderboard (Feature 5) -->
        <div id="tab-leaderboard" class="tab-content">
            <div class="card">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-size: 0.75em; font-weight: bold; text-transform: uppercase; color: var(--vscode-descriptionForeground);">Live Leaderboard</span>
                    <button id="leaderboard-refresh-btn" style="background: none; border: 1px solid var(--vscode-panel-border); padding: 2px 8px; border-radius: 4px; color: #3ecf8e; cursor: pointer; font-size: 0.75em;">
                        Refresh
                    </button>
                </div>
                <div style="font-size: 0.72em; color: var(--vscode-descriptionForeground); margin-top: 4px;">
                    Ranked by tasks completed, with elapsed time tiebreaker.
                </div>
            </div>

            <div id="leaderboard-container" style="display: flex; flex-direction: column; gap: 5px;">
                <div style="text-align: center; color: var(--vscode-descriptionForeground); padding: 16px; font-size: 0.85em;">
                    Loading session standings...
                </div>
            </div>
        </div>

        <!-- TAB 3: Code Change Tracking & Diff View -->
        <div id="tab-diff" class="tab-content">
            <div class="card" style="display: flex; justify-content: space-around; text-align: center;">
                <div>
                    <div style="font-size: 1.2em; font-weight: bold; color: #3ecf8e;" id="diff-total-added">+0</div>
                    <div style="font-size: 0.75em; color: var(--vscode-descriptionForeground);">Lines Added</div>
                </div>
                <div>
                    <div style="font-size: 1.2em; font-weight: bold; color: #f87171;" id="diff-total-deleted">-0</div>
                    <div style="font-size: 0.75em; color: var(--vscode-descriptionForeground);">Lines Deleted</div>
                </div>
                <div>
                    <div style="font-size: 1.2em; font-weight: bold; color: #38bdf8;" id="diff-total-modified">~0</div>
                    <div style="font-size: 0.75em; color: var(--vscode-descriptionForeground);">Modified</div>
                </div>
            </div>

            <div class="card">
                <label style="margin-bottom: 6px; display: block;">File Change Breakdown</label>
                <div id="diff-files-container" style="display: flex; flex-direction: column; gap: 5px;">
                    <div style="color: var(--vscode-descriptionForeground); font-size: 0.85em; text-align: center; padding: 10px;">
                        No file changes detected yet. Start coding to track diffs!
                    </div>
                </div>
            </div>

            <div class="card" id="teammates-card" style="display: none;">
                <label style="margin-bottom: 6px; display: block;">Teammate Contributions</label>
                <div id="teammates-breakdown" style="display: flex; flex-direction: column; gap: 6px;"></div>
            </div>
        </div>

        <!-- TAB 4: Team Chat -->
        <div id="tab-chat" class="tab-content">
            <div class="chat-feed" id="chat-messages-container">
                <div style="text-align: center; color: var(--vscode-descriptionForeground); padding: 20px; font-size: 0.85em;">
                    Private team chat. Messages are shared only among teammates for this active session.
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div id="snippet-container" style="display: none;">
                    <textarea id="chat-snippet-input" placeholder="// Optional code snippet..." rows="2" style="font-family: monospace; font-size: 0.82em; width: 100%; box-sizing: border-box;"></textarea>
                </div>
                <div class="chat-input-row">
                    <button id="snippet-toggle-btn" class="btn-secondary" style="padding: 4px 8px; font-size: 0.8em;" title="Attach code snippet">&lt;/&gt;</button>
                    <input type="text" id="chat-input" placeholder="Type a message..." style="flex: 1;">
                    <button id="chat-send-btn" style="padding: 4px 10px;">Send</button>
                </div>
            </div>
        </div>

        <div id="action-status" class="loader-status" style="display: none;"></div>

        <!-- Actions CTA -->
        <div class="actions">
            <button id="check-progress-btn">Check Progress (AI)</button>
            <button id="submit-btn">Submit Lab (Finalize)</button>
            <button id="exit-btn" class="btn-secondary">Exit Session</button>
        </div>
    </div>

    <script>
        const vscode = acquireVsCodeApi();
        
        const connectionScreen = document.getElementById('connection-screen');
        const sessionScreen = document.getElementById('session-screen');
        
        // Form fields
        const backendUrlInput = document.getElementById('backend-url');
        const sessionIdInput = document.getElementById('session-id');
        const apiTokenInput = document.getElementById('api-token');
        const connectBtn = document.getElementById('connect-btn');
        
        // Session status & timer
        const statusBanner = document.getElementById('status-banner');
        const statusText = document.getElementById('status-text');
        const timerDisplay = document.getElementById('timer-display');
        const timerLabel = document.getElementById('timer-label');
        const labTitle = document.getElementById('lab-title');
        const labDesc = document.getElementById('lab-desc');
        const starterFilesList = document.getElementById('starter-files-list');
        const tasksContainer = document.getElementById('tasks-container');
        const actionStatus = document.getElementById('action-status');

        // Feature 1 Integrity
        const integrityAlert = document.getElementById('integrity-alert');
        const integrityText = document.getElementById('integrity-text');
        const regenerateFilesLink = document.getElementById('regenerate-files-link');

        // Feature 9 Live Lab Alert
        const liveLabAlert = document.getElementById('live-lab-alert');
        const liveLabAlertText = document.getElementById('live-lab-alert-text');

        // Feature 2 Diff UI
        const pillAdded = document.getElementById('pill-added');
        const pillDeleted = document.getElementById('pill-deleted');
        const diffTotalAdded = document.getElementById('diff-total-added');
        const diffTotalDeleted = document.getElementById('diff-total-deleted');
        const diffTotalModified = document.getElementById('diff-total-modified');
        const diffFilesContainer = document.getElementById('diff-files-container');
        const teammatesCard = document.getElementById('teammates-card');
        const teammatesBreakdown = document.getElementById('teammates-breakdown');

        // Feature 3 Team Chat UI
        const tabChatBtn = document.getElementById('tab-chat-btn');
        const chatBadge = document.getElementById('chat-badge');
        const chatMessagesContainer = document.getElementById('chat-messages-container');
        const chatInput = document.getElementById('chat-input');
        const chatSnippetInput = document.getElementById('chat-snippet-input');
        const chatSendBtn = document.getElementById('chat-send-btn');
        const snippetToggleBtn = document.getElementById('snippet-toggle-btn');
        const snippetContainer = document.getElementById('snippet-container');
        
        // Feedback & Console
        const llmFeedbackSection = document.getElementById('llm-feedback-section');
        const llmFeedbackText = document.getElementById('llm-feedback-text');
        const consoleOutputSection = document.getElementById('console-output-section');
        const consoleOutputText = document.getElementById('console-output-text');
        
        // Buttons
        const checkProgressBtn = document.getElementById('check-progress-btn');
        const submitBtn = document.getElementById('submit-btn');
        const exitBtn = document.getElementById('exit-btn');
        
        let timerVal = 0;
        let timerInterval = null;
        let isCountDown = true;
        let isIntegrityValid = true;

        // Tabs Logic
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                const targetId = btn.getAttribute('data-tab');
                const targetEl = document.getElementById(targetId);
                if (targetEl) targetEl.classList.add('active');

                // Notify extension about chat tab state
                const isChat = targetId === 'tab-chat';
                vscode.postMessage({ type: 'setChatTabActive', active: isChat });
                if (isChat) {
                    chatBadge.style.display = 'none';
                    chatBadge.innerText = '0';
                }

                // Fetch leaderboard on tab focus
                if (targetId === 'tab-leaderboard') {
                    vscode.postMessage({ type: 'getLeaderboard' });
                }
            });
        });

        const leaderboardRefreshBtn = document.getElementById('leaderboard-refresh-btn');
        if (leaderboardRefreshBtn) {
            leaderboardRefreshBtn.addEventListener('click', () => {
                vscode.postMessage({ type: 'getLeaderboard' });
            });
        }

        regenerateFilesLink.addEventListener('click', (e) => {
            e.preventDefault();
            vscode.postMessage({ type: 'retryFileGeneration' });
        });

        snippetToggleBtn.addEventListener('click', () => {
            snippetContainer.style.display = snippetContainer.style.display === 'none' ? 'block' : 'none';
        });

        connectBtn.addEventListener('click', () => {
            const backendUrl = backendUrlInput.value.trim();
            const sessionId = sessionIdInput.value.trim();
            const apiToken = apiTokenInput.value.trim();
            
            if (!backendUrl || !sessionId) {
                alert('Please enter both Backend URL and Session ID.');
                return;
            }
            
            actionStatus.innerText = 'Connecting to backend...';
            actionStatus.style.display = 'block';
            
            vscode.postMessage({
                type: 'connect',
                backendUrl,
                sessionId,
                apiToken: apiToken || null
            });
        });

        checkProgressBtn.addEventListener('click', () => {
            vscode.postMessage({ type: 'checkProgress' });
        });

        submitBtn.addEventListener('click', () => {
            if (!isIntegrityValid) {
                alert('Submission blocked: One or more required starter files are missing from your workspace.');
                return;
            }
            vscode.postMessage({ type: 'submit' });
        });

        exitBtn.addEventListener('click', () => {
            vscode.postMessage({ type: 'exit' });
        });

        chatSendBtn.addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });

        function sendChatMessage() {
            const text = chatInput.value.trim();
            const snippet = chatSnippetInput.value.trim();
            if (!text && !snippet) return;

            vscode.postMessage({
                type: 'sendChat',
                message: text || 'Code snippet shared:',
                codeSnippet: snippet || null
            });

            chatInput.value = '';
            chatSnippetInput.value = '';
            snippetContainer.style.display = 'none';
        }

        // Handle incoming extension messages
        window.addEventListener('message', event => {
            const message = event.data;
            switch (message.type) {
                case 'prefill':
                    backendUrlInput.value = message.backendUrl;
                    sessionIdInput.value = message.sessionId;
                    apiTokenInput.value = message.apiToken || '';
                    break;
                    
                case 'status':
                    actionStatus.innerText = message.message;
                    actionStatus.style.display = 'block';
                    checkProgressBtn.disabled = true;
                    submitBtn.disabled = true;
                    break;
                    
                case 'error':
                    actionStatus.style.display = 'none';
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = !isIntegrityValid;
                    alert(message.message);
                    break;

                case 'integrityStatus':
                    isIntegrityValid = message.valid;
                    if (message.valid) {
                        integrityAlert.style.display = 'none';
                        submitBtn.disabled = false;
                    } else {
                        integrityAlert.style.display = 'flex';
                        integrityText.innerHTML = \`<strong>Missing:</strong> \${message.missing.join(', ')} — this file is required for submission.\`;
                        submitBtn.disabled = true;
                    }
                    break;

                case 'diffUpdate':
                    const ds = message.diffStats || {};
                    const add = ds.lines_added || 0;
                    const del = ds.lines_deleted || 0;
                    const mod = ds.lines_modified || 0;

                    pillAdded.innerText = \`+\${add}\`;
                    pillDeleted.innerText = \`-\${del}\`;
                    diffTotalAdded.innerText = \`+\${add}\`;
                    diffTotalDeleted.innerText = \`-\${del}\`;
                    diffTotalModified.innerText = \`~\${mod}\`;

                    if (ds.files && ds.files.length > 0) {
                        diffFilesContainer.innerHTML = '';
                        ds.files.forEach(f => {
                            const item = document.createElement('div');
                            item.className = 'file-item';
                            item.innerHTML = \`
                                <span>\${f.name}</span>
                                <span style="font-size:0.85em;">
                                    <span class="diff-added">+\${f.added}</span> / <span class="diff-deleted">-\${f.deleted}</span>
                                </span>
                            \`;
                            diffFilesContainer.appendChild(item);
                        });
                    }
                    break;

                case 'chatUpdate':
                    const chats = message.chats || [];
                    if (chats.length > 0) {
                        chatMessagesContainer.innerHTML = '';
                        chats.forEach(c => {
                            const el = document.createElement('div');
                            el.className = 'chat-msg';
                            el.innerHTML = \`
                                <div class="chat-header">
                                    <div class="chat-author">
                                        <span class="chat-avatar" style="background-color: \${c.avatar_color || '#3ecf8e'};"></span>
                                        <span>\${c.user_name}</span>
                                    </div>
                                    <span class="chat-time">\${c.time || ''}</span>
                                </div>
                                <div class="chat-text">\${c.message}</div>
                                \${c.code_snippet ? \`<pre class="chat-snippet">\${c.code_snippet}</pre>\` : ''}
                            \`;
                            chatMessagesContainer.appendChild(el);
                        });
                        chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
                    }

                    if (message.unreadCount > 0) {
                        chatBadge.innerText = message.unreadCount;
                        chatBadge.style.display = 'inline-block';
                    }
                    break;

                case 'leaderboardData':
                    const list = message.leaderboard || [];
                    const leaderboardContainer = document.getElementById('leaderboard-container');
                    if (leaderboardContainer) {
                        leaderboardContainer.innerHTML = '';

                        // Feature 9: Live Lab shared clock indicator on leaderboard
                        if (message.availability_mode === 'live') {
                            const clockBanner = document.createElement('div');
                            clockBanner.style.cssText = 'padding: 6px 10px; background: rgba(62, 207, 142, 0.1); border: 1px solid rgba(62, 207, 142, 0.3); border-radius: 6px; font-size: 0.78em; color: #3ecf8e; font-weight: bold; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;';
                            clockBanner.innerHTML = \`
                                <span>⏱️ Live Lab Shared Clock</span>
                                <span style="font-family: monospace; font-size: 1.1em;">\${message.shared_time_remaining_formatted || '00:00'} remaining</span>
                            \`;
                            leaderboardContainer.appendChild(clockBanner);
                        }

                        if (list.length === 0) {
                            const emptyEl = document.createElement('div');
                            emptyEl.style.cssText = 'text-align: center; color: var(--vscode-descriptionForeground); padding: 16px; font-size: 0.85em;';
                            emptyEl.innerText = 'No active competitors or teams ranked yet.';
                            leaderboardContainer.appendChild(emptyEl);
                        } else {
                            list.forEach(item => {
                                const row = document.createElement('div');
                                row.className = 'leaderboard-row' + (item.is_current ? ' current-user' : '');
                                const rankClass = item.rank <= 3 ? (' rank-' + item.rank) : '';
                                row.innerHTML = \`
                                    <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                        <span class="rank-badge\${rankClass}">#\${item.rank}</span>
                                        <div style="min-width: 0;">
                                            <div style="font-weight: 600; color: var(--vscode-foreground); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                \${item.name}\${item.is_current ? ' (You)' : ''}
                                            </div>
                                            <div style="font-size: 0.75em; color: var(--vscode-descriptionForeground);">
                                                \${item.is_team && item.group_name ? item.group_name + ' • ' : ''}\${item.elapsed_time}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0;">
                                        <span style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: bold; background: rgba(62, 207, 142, 0.15); color: #3ecf8e;">
                                            \${item.tasks_completed} done
                                        </span>
                                    </div>
                                \`;
                                leaderboardContainer.appendChild(row);
                            });
                        }
                    }
                    break;

                case 'liveLabBlocked':
                    if (liveLabAlert && liveLabAlertText) {
                        liveLabAlert.style.display = 'flex';
                        liveLabAlert.style.background = 'rgba(239, 68, 68, 0.15)';
                        liveLabAlert.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                        liveLabAlert.style.color = '#fca5a5';
                        liveLabAlertText.innerText = message.message || 'Access blocked for this Live Lab.';
                    }
                    if (checkProgressBtn) checkProgressBtn.disabled = true;
                    if (submitBtn) submitBtn.disabled = true;
                    if (timerInterval) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                    }
                    timerLabel.innerText = message.error === 'live_not_started' ? 'Live Lab Locked' : 'Live Lab Expired';
                    break;

                case 'reconnecting':
                    statusBanner.className = 'connection-status status-reconnecting';
                    statusText.innerText = 'Reconnecting...';
                    break;
                    
                case 'disconnected':
                    if (timerInterval) clearInterval(timerInterval);
                    connectionScreen.style.display = 'flex';
                    sessionScreen.style.display = 'none';
                    actionStatus.style.display = 'none';
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = false;
                    break;
                    
                case 'update':
                    actionStatus.style.display = 'none';
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = !isIntegrityValid;
                    
                    connectionScreen.style.display = 'none';
                    sessionScreen.style.display = 'flex';
                    
                    statusBanner.className = 'connection-status status-connected';
                    statusText.innerText = 'Connected';
                    
                    const session = message.data;
                    labTitle.innerText = session.laboratory.title;
                    labDesc.innerText = session.laboratory.description;

                    // Render Starter Files
                    starterFilesList.innerHTML = '';
                    const starterFiles = session.laboratory.starter_files || [];
                    starterFiles.forEach(f => {
                        const row = document.createElement('div');
                        row.className = 'file-item';
                        row.title = 'Click to open ' + f.name;
                        row.onclick = () => {
                            vscode.postMessage({ type: 'openFile', name: f.name });
                        };
                        row.innerHTML = \`
                            <span style="display:flex; align-items:center; gap:6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                <span>\${f.name}</span>
                            </span>
                            <span>
                                \${f.is_primary ? '<span class="file-badge badge-complete">Primary</span>' : ''}
                                \${f.is_readonly ? '<span class="file-badge" style="background:#333;">Read-Only</span>' : ''}
                            </span>
                        \`;
                        starterFilesList.appendChild(row);
                    });

                    // Teammate breakdown for team labs
                    if (session.is_group_lab && session.teammates && session.teammates.length > 0) {
                        teammatesCard.style.display = 'block';
                        teammatesBreakdown.innerHTML = '';
                        session.teammates.forEach(tm => {
                            const row = document.createElement('div');
                            row.style.cssText = 'display:flex; justify-content:space-between; font-size:0.85em; padding:3px 0;';
                            row.innerHTML = \`
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <span style="width:8px; height:8px; border-radius:50%; background-color:\${tm.avatar_color};"></span>
                                    <span>\${tm.name}</span>
                                </div>
                                <span style="font-weight:bold; color:#3ecf8e;">\${tm.contribution_score || 0}%</span>
                            \`;
                            teammatesBreakdown.appendChild(row);
                        });
                    } else {
                        teammatesCard.style.display = 'none';
                    }
                    
                    // Render Tasks list
                    tasksContainer.innerHTML = '';
                    const tasksDef = session.laboratory.tasks_definition || [];
                    const completedIds = session.completed_tasks || [];
                    
                    tasksDef.forEach(task => {
                        const isCompleted = completedIds.includes(task.id);
                        const taskItem = document.createElement('div');
                        taskItem.className = 'task-item';
                        
                        taskItem.innerHTML = \`
                            <div class="task-header">
                                <span class="task-title">\${task.task}</span>
                                <span class="task-badge \${isCompleted ? 'badge-complete' : 'badge-pending'}">
                                    \${isCompleted ? 'Completed' : 'Pending'}
                                </span>
                            </div>
                            <div class="task-feedback" id="feedback-task-\${task.id}" style="display:none;"></div>
                        \`;
                        tasksContainer.appendChild(taskItem);
                    });
                    
                    // Feature 9: Live Lab state evaluation & alerts
                    const isLive = session.availability_mode === 'live';
                    const isLiveExpired = session.is_live_expired || (isLive && session.live_status === 'closed');
                    const isLiveNotStarted = isLive && session.live_status === 'not_started';

                    if (liveLabAlert && liveLabAlertText) {
                        if (isLiveExpired) {
                            liveLabAlert.style.display = 'flex';
                            liveLabAlert.style.background = 'rgba(239, 68, 68, 0.15)';
                            liveLabAlert.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                            liveLabAlert.style.color = '#fca5a5';
                            liveLabAlertText.innerText = '⏱️ Live Lab Expired: The shared countdown has ended. Submissions are locked.';
                            checkProgressBtn.disabled = true;
                            submitBtn.disabled = true;
                        } else if (isLiveNotStarted) {
                            liveLabAlert.style.display = 'flex';
                            liveLabAlert.style.background = 'rgba(245, 158, 11, 0.15)';
                            liveLabAlert.style.borderColor = 'rgba(245, 158, 11, 0.35)';
                            liveLabAlert.style.color = '#fcd34d';
                            liveLabAlertText.innerText = '🔒 Live Lab Locked: Waiting for instructor to manually open the session window.';
                            checkProgressBtn.disabled = true;
                            submitBtn.disabled = true;
                        } else {
                            liveLabAlert.style.display = 'none';
                        }
                    }

                    // Setup timer
                    if (session.status === 'completed' || isLiveExpired) {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                            timerInterval = null;
                        }
                        if (session.time_limit_minutes > 0 || isLive) {
                            timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || 0));
                        } else {
                            timerVal = Math.max(0, Math.floor(Number(session.elapsed_seconds) || 0));
                        }
                        updateTimerDisplay();
                        timerLabel.innerText = isLiveExpired ? 'Live Lab Expired' : 'Session Completed';
                    } else if (isLiveNotStarted) {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                            timerInterval = null;
                        }
                        timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || (session.live_duration_minutes * 60) || 0));
                        updateTimerDisplay();
                        timerLabel.innerText = 'Live Lab Locked';
                    } else {
                        if (isLive || session.time_limit_minutes > 0) {
                            timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || 0));
                            isCountDown = true;
                            timerLabel.innerText = isLive
                                ? (timerVal > 0 ? 'Live Lab Countdown (Shared)' : 'Live Lab Expired')
                                : (timerVal > 0 ? 'Time Remaining' : 'Time Expired');
                        } else {
                            timerVal = Math.max(0, Math.floor(Number(session.elapsed_seconds) || 0));
                            isCountDown = false;
                            timerLabel.innerText = 'Session Elapsed';
                        }
                        startLocalTimer(isLive);
                    }
                    break;
                    
                case 'checkResult':
                case 'submitResult':
                    actionStatus.style.display = 'none';
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = !isIntegrityValid;
                    
                    if (message.type === 'submitResult') {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                            timerInterval = null;
                        }
                        timerLabel.innerText = 'Session Completed';
                    }
                    
                    const evalData = message.data.evaluation || {};
                    const execData = message.data.execution || {};
                    
                    // Display task feedback from LLM evaluation
                    if (evalData.tasks && Array.isArray(evalData.tasks)) {
                        evalData.tasks.forEach(taskEval => {
                            const feedbackEl = document.getElementById(\`feedback-task-\${taskEval.id}\`);
                            if (feedbackEl) {
                                feedbackEl.innerText = taskEval.feedback || 'Evaluated successfully.';
                                feedbackEl.style.display = 'block';
                            }
                        });
                    }
                    
                    // Display Overall feedback
                    if (evalData.overall_feedback) {
                        llmFeedbackSection.style.display = 'block';
                        llmFeedbackText.innerHTML = \`
                            <div style="font-weight:bold; margin-bottom:4px;">Score: \${evalData.correctness_score ?? 0}%</div>
                            <div>\${evalData.overall_feedback}</div>
                            <div style="margin-top:6px; font-size:0.9em; border-top:1px solid rgba(255,255,255,0.05); padding-top:4px;">
                                <strong>Code Quality:</strong> \${evalData.code_quality_feedback || 'N/A'}
                            </div>
                        \`;
                    }
                    
                    // Display Console output if available
                    if (execData && (execData.output || execData.errors)) {
                        consoleOutputSection.style.display = 'block';
                        consoleOutputText.innerText = execData.errors 
                            ? \`Compilation/Runtime Error:\\n\${execData.errors}\` 
                            : execData.output || 'Execution completed with no output.';
                    } else {
                        consoleOutputSection.style.display = 'none';
                    }
                    
                    break;
            }
        });
        
        function startLocalTimer(isLive = false) {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            updateTimerDisplay();
            
            if (isCountDown && timerVal <= 0) {
                timerLabel.innerText = isLive ? 'Live Lab Expired' : 'Time Expired';
                if (isLive) {
                    if (liveLabAlert && liveLabAlertText) {
                        liveLabAlert.style.display = 'flex';
                        liveLabAlert.style.background = 'rgba(239, 68, 68, 0.15)';
                        liveLabAlert.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                        liveLabAlert.style.color = '#fca5a5';
                        liveLabAlertText.innerText = '⏱️ Live Lab Expired: The shared countdown has ended. Submissions are locked.';
                    }
                    if (checkProgressBtn) checkProgressBtn.disabled = true;
                    if (submitBtn) submitBtn.disabled = true;
                }
                return;
            }
            
            timerInterval = setInterval(() => {
                if (isCountDown) {
                    if (timerVal > 0) {
                        timerVal--;
                    } else {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        timerLabel.innerText = isLive ? 'Live Lab Expired' : 'Time Expired';
                        if (isLive) {
                            if (liveLabAlert && liveLabAlertText) {
                                liveLabAlert.style.display = 'flex';
                                liveLabAlert.style.background = 'rgba(239, 68, 68, 0.15)';
                                liveLabAlert.style.borderColor = 'rgba(239, 68, 68, 0.35)';
                                liveLabAlert.style.color = '#fca5a5';
                                liveLabAlertText.innerText = '⏱️ Live Lab Expired: The shared countdown has ended. Submissions are locked.';
                            }
                            if (checkProgressBtn) checkProgressBtn.disabled = true;
                            if (submitBtn) submitBtn.disabled = true;
                        }
                    }
                } else {
                    timerVal++;
                }
                updateTimerDisplay();
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const totalSeconds = Math.max(0, Math.floor(Number(timerVal) || 0));
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            const paddedSeconds = seconds.toString().padStart(2, '0');
            const paddedMinutes = minutes.toString().padStart(2, '0');

            if (hours > 0) {
                const paddedHours = hours.toString().padStart(2, '0');
                timerDisplay.innerText = \`\${paddedHours}:\${paddedMinutes}:\${paddedSeconds}\`;
            } else {
                timerDisplay.innerText = \`\${paddedMinutes}:\${paddedSeconds}\`;
            }
        }
    </script>
</body>
</html>
`;
    }
}
