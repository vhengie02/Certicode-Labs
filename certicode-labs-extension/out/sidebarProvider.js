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
exports.SidebarProvider = void 0;
const vscode = __importStar(require("vscode"));
const http = __importStar(require("http"));
const https = __importStar(require("https"));
const url_1 = require("url");
const crypto = __importStar(require("crypto"));
const API_PREFIX = '/api/v1';
// Sent with every request so the server can tell students running an outdated build.
const EXTENSION_VERSION = vscode.extensions.getExtension('certicode.certicode-labs')?.packageJSON?.version ?? '0.0.0';
// Check progress and submit run the code and then grade it with Claude on the server
// (up to ~25 s + 45 s, capped at 60 s on Vercel), so they get more than the default 15 s.
const GRADING_TIMEOUT_MS = 90000;
const TRUSTED_BACKENDS_KEY = 'certicode.trustedBackends';
const STARTER_OWNERS_KEY = 'certicode.starterFileOwners';
// Chat text shorter than this never excuses a paste (a one-letter message would match almost anything).
const MIN_CHAT_SNIPPET_LENGTH = 20;
class SidebarProvider {
    constructor(_extensionUri, _globalState) {
        this._extensionUri = _extensionUri;
        this._globalState = _globalState;
        this._backendUrl = 'http://localhost';
        this._isReconnecting = false;
        this._lastSessionData = null;
        // A check-progress or submit is waiting on the grader; further clicks are ignored until it answers
        this._gradingInFlight = false;
        // Feature 1: Starter Files & Integrity
        this._starterFileSnapshots = new Map();
        // Read-only starter files (document URI -> original content) and a re-entrancy flag for restoring them
        this._readonlyFiles = new Map();
        this._restoringReadonly = false;
        this._filesGenerated = false;
        this._missingFiles = [];
        this._lastDiffStats = {
            lines_added: 0,
            lines_deleted: 0,
            lines_modified: 0,
            files: []
        };
        // Feature 3: Team Chat
        this._unreadChatCount = 0;
        this._isChatTabActive = false;
        this._recentChatSnippets = [];
        // Feature 4: WPM Baseline & Paste Anomaly Interceptor
        this._keystrokeTimestamps = [];
        this._totalKeystrokes = 0;
        this._currentWpm = 0;
        // Feature 11: Continuous Idle Detection & Overall Session WPM
        this._lastActivityTime = Date.now();
        this._lastIdleAlertMinutes = 0;
        this._sessionStartTime = Date.now();
        this._wsConnected = false;
        // Polling is only paused once the server confirms a channel subscription.
        this._wsSubscribed = false;
        this._wsRetryDelayMs = 10000;
        this._seenChatCount = 0;
        this._lastChatCount = 0;
        // Workspace-wide listeners, registered once and disposed with the provider.
        this._workspaceDisposables = [];
        /** Sync failures in a row; each one doubles the gap between syncs, up to 2 minutes. */
        this._syncFailures = 0;
    }
    dispose() {
        this.stopSessionTimers();
        this._workspaceDisposables.forEach(d => d.dispose());
        this._workspaceDisposables = [];
        if (this._windowStateListener) {
            this._windowStateListener.dispose();
            this._windowStateListener = undefined;
        }
    }
    /**
     * Stop every timer, listener, and socket tied to the active session.
     */
    stopSessionTimers() {
        if (this._pingInterval) {
            clearInterval(this._pingInterval);
            this._pingInterval = undefined;
        }
        if (this._chatPollInterval) {
            clearInterval(this._chatPollInterval);
            this._chatPollInterval = undefined;
        }
        if (this._leaderboardInterval) {
            clearInterval(this._leaderboardInterval);
            this._leaderboardInterval = undefined;
        }
        if (this._wpmSyncTimer) {
            clearTimeout(this._wpmSyncTimer);
            this._wpmSyncTimer = undefined;
        }
        if (this._diffDebounceTimer) {
            clearTimeout(this._diffDebounceTimer);
            this._diffDebounceTimer = undefined;
        }
        if (this._idleCheckInterval) {
            clearInterval(this._idleCheckInterval);
            this._idleCheckInterval = undefined;
        }
        this.disposeActivityListeners();
        this.disconnectWebSocket();
    }
    /**
     * Ask before talking to a backend the student has not connected to before. Deep links can
     * name any server, and a connected server decides which files are written to the workspace.
     */
    async ensureTrustedBackend(rawUrl) {
        let url;
        try {
            url = new url_1.URL(rawUrl.trim());
        }
        catch {
            vscode.window.showErrorMessage(`CertiCode: "${rawUrl}" is not a valid backend URL.`);
            return undefined;
        }
        const isLocal = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
        if (url.protocol !== 'https:' && !(isLocal && url.protocol === 'http:')) {
            vscode.window.showErrorMessage('CertiCode: The backend must use https:// (http:// is only allowed for localhost).');
            return undefined;
        }
        const origin = url.origin;
        const trusted = this._globalState.get(TRUSTED_BACKENDS_KEY, []);
        if (!trusted.includes(origin)) {
            const choice = await vscode.window.showWarningMessage(`Connect to the CertiCode lab server at ${origin}? It will be able to create files in your workspace and receive your code and activity.`, { modal: true }, 'Connect');
            if (choice !== 'Connect') {
                return undefined;
            }
            await this._globalState.update(TRUSTED_BACKENDS_KEY, [...trusted, origin]);
        }
        return origin;
    }
    /**
     * Resolve a server-provided relative file name inside the workspace root.
     * Rejects absolute paths and anything that would escape the root (e.g. "../../.bashrc").
     */
    resolveWorkspaceFile(rootUri, name) {
        if (typeof name !== 'string' || name.trim() === '') {
            return undefined;
        }
        const normalized = name.replace(/\\/g, '/');
        const segments = normalized.split('/');
        if (normalized.startsWith('/') || /^[a-zA-Z]:/.test(normalized) || segments.some(s => s === '..')) {
            return undefined;
        }
        return vscode.Uri.joinPath(rootUri, ...segments.filter(s => s !== '' && s !== '.'));
    }
    /**
     * Read a lab file as the student currently sees it: the open editor buffer (including
     * unsaved edits) when the file is open, otherwise the file on disk.
     */
    async readWorkspaceText(fileUri) {
        const openDoc = vscode.workspace.textDocuments.find(d => d.uri.toString() === fileUri.toString());
        if (openDoc) {
            return openDoc.getText();
        }
        return new TextDecoder().decode(await vscode.workspace.fs.readFile(fileUri));
    }
    /**
     * Whether an editor event comes from the student's own code, as opposed to Output panels,
     * logs, settings, or files outside the lab workspace.
     */
    isStudentDocument(doc) {
        if (doc.uri.scheme === 'untitled') {
            return true;
        }
        return doc.uri.scheme === 'file' && vscode.workspace.getWorkspaceFolder(doc.uri) !== undefined;
    }
    resolveWebviewView(webviewView, context, _token) {
        this._view = webviewView;
        webviewView.webview.options = {
            enableScripts: true,
            localResourceRoots: [this._extensionUri]
        };
        webviewView.webview.html = this._getHtmlForWebview(webviewView.webview);
        if (this._sessionId) {
            this.startMonitoring();
            if (this._lastSessionData) {
                webviewView.webview.postMessage({
                    type: 'update',
                    data: this._lastSessionData,
                    isReconnecting: false
                });
            }
        }
        // Listen for postMessages from Webview
        webviewView.webview.onDidReceiveMessage(async (data) => {
            switch (data.type) {
                case 'webviewReady': {
                    if (this._extensionRejected) {
                        this.postExtensionOutdated();
                    }
                    else if (this._sessionId) {
                        if (this._lastSessionData) {
                            this._view?.webview.postMessage({
                                type: 'update',
                                data: this._lastSessionData,
                                isReconnecting: false
                            });
                        }
                        else {
                            await this.syncSessionState();
                        }
                    }
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
                    this.handleExit(data.skipConfirm ? false : true);
                    break;
                }
                case 'sendChat': {
                    if (this.isGroupLab()) {
                        this.handleSendChat(data.message, data.codeSnippet);
                    }
                    break;
                }
                case 'openUpdateDownload': {
                    if (this._extensionRejected?.downloadUrl) {
                        vscode.env.openExternal(vscode.Uri.parse(this._extensionRejected.downloadUrl));
                    }
                    break;
                }
                case 'setChatTabActive': {
                    this._isChatTabActive = data.active;
                    if (data.active) {
                        this._unreadChatCount = 0;
                        this._seenChatCount = this._lastChatCount;
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
                    const fileUri = workspaceFolders && workspaceFolders.length > 0
                        ? this.resolveWorkspaceFile(workspaceFolders[0].uri, data.name)
                        : undefined;
                    if (fileUri) {
                        try {
                            const doc = await vscode.workspace.openTextDocument(fileUri);
                            await vscode.window.showTextDocument(doc, { preview: false });
                            break;
                        }
                        catch { }
                    }
                    const sfile = (this._lastSessionData?.laboratory?.starter_files || []).find((f) => f.name === data.name);
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
                case 'telemetry':
                case 'cameraTelemetry': {
                    await this.sendTelemetry(data.eventType, data.payload);
                    break;
                }
            }
        });
        // Catch up on chat and leaderboard skipped or slowed while the view was hidden
        webviewView.onDidChangeVisibility(() => {
            if (webviewView.visible && this._sessionId) {
                this.fetchChatMessages();
                this.fetchLeaderboard();
            }
        });
        // Setup FileSystemWatcher and Document change tracking
        this.setupWorkspaceWatchers();
    }
    /**
     * Connect directly to a session (called via deep-linking custom URI handler).
     * This is the only way in: students start labs from the website, never by typing session details.
     */
    async connectToSession(backendUrl, sessionId, apiToken) {
        const origin = await this.ensureTrustedBackend(backendUrl);
        if (!origin) {
            return false;
        }
        // Another lab or attempt: forget the previous one's starter baseline, read-only files
        // and session data, so diffs and paste checks compare against this lab's files
        if (this._sessionId !== sessionId) {
            this._starterFileSnapshots.clear();
            this._readonlyFiles.clear();
            this._lastSessionData = null;
            this._filesGenerated = false;
        }
        this._backendUrl = origin;
        this._sessionId = sessionId;
        this._apiToken = apiToken;
        this._extensionRejected = undefined;
        // If view already exists, re-render its HTML immediately so the webview launches into session mode
        if (this._view) {
            this._view.webview.html = this._getHtmlForWebview(this._view.webview);
        }
        this.startMonitoring();
        // Send immediate ping to notify backend that VS Code extension is active
        this.sendVsCodePing();
        // Focus the sidebar view in VS Code
        vscode.commands.executeCommand('workbench.view.extension.certicode-explorer');
        vscode.commands.executeCommand('certicode-labs.sidebar.focus');
        return true;
    }
    async sendVsCodePing() {
        if (!this._sessionId) {
            return;
        }
        try {
            const prefix = API_PREFIX;
            await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/ping`, {
                source: 'vscode',
                client: 'vscode_extension',
                timestamp: new Date().toISOString()
            });
        }
        catch (err) {
            // Heartbeat warning ignored
        }
    }
    /**
     * Tell the server this VS Code window is closing, so the browser page leaves
     * camera mode now instead of waiting for the heartbeat to go stale.
     */
    async notifyVsCodeClosed() {
        if (!this._sessionId) {
            return;
        }
        try {
            await this.makeRequest('POST', `${API_PREFIX}/sessions/${this._sessionId}/vscode-disconnect`, {
                source: 'vscode',
                client: 'vscode_extension',
                timestamp: new Date().toISOString()
            }, 3000);
        }
        catch (err) {
            // Best effort: the browser falls back to the stale heartbeat
        }
    }
    startMonitoring() {
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
        this._seenChatCount = 0;
        this._lastChatCount = 0;
        this._wsRetryDelayMs = 10000;
        // Feature 11: Initialize Idle Tracking & Activity Listeners
        this._lastActivityTime = Date.now();
        this._lastIdleAlertMinutes = 0;
        this._sessionStartTime = Date.now();
        if (this._idleCheckInterval) {
            clearInterval(this._idleCheckInterval);
        }
        this._idleCheckInterval = setInterval(() => {
            this.checkIdleState();
        }, 20000);
        this.setupActivityListeners();
        // Setup window focus tracking (Feature 5)
        this.setupFocusTracking();
        // Live updates connect after the first session sync (see syncSessionState), which carries the config.
        this.disconnectWebSocket();
        // Sync immediately. The session read is also the heartbeat: the server records an
        // extension ping for it (X-Client: vscode), so there is no separate /ping call.
        this._syncFailures = 0;
        this.syncSessionState();
        this.fetchLeaderboard();
        // Sync every 15 seconds to detect dropped connections / keepalive. While the server
        // keeps failing, back off: every 30 s, 60 s, then 2 min until a sync succeeds.
        let syncTick = 0;
        this._pingInterval = setInterval(() => {
            syncTick++;
            if (syncTick % this.syncEveryTicks() === 0) {
                this.syncSessionState();
            }
        }, 15000);
        // Chat sync every 4 seconds while the sidebar is visible, every 16 seconds while hidden.
        // It keeps running when hidden because recent chat snippets feed paste suppression rule B.
        // Only team labs have a chat, and nothing is polled while the server is unreachable.
        let chatTick = 0;
        this._chatPollInterval = setInterval(() => {
            chatTick++;
            if (this._wsSubscribed || this._isReconnecting || !this.isGroupLab()) {
                return;
            }
            if (this._view?.visible || chatTick % 4 === 0) {
                this.fetchChatMessages();
            }
        }, 4000);
        // Leaderboard sync every 10 seconds, only while visible (display only)
        this._leaderboardInterval = setInterval(() => {
            if (!this._wsSubscribed && !this._isReconnecting && this._view?.visible) {
                this.fetchLeaderboard();
            }
        }, 10000);
    }
    syncEveryTicks() {
        return Math.min(8, 2 ** this._syncFailures);
    }
    isGroupLab() {
        return this._lastSessionData?.is_group_lab === true;
    }
    /**
     * Live updates over Pusher Channels, when the server advertises it in the session payload
     * (`realtime`). Private channels are signed by the backend using this session's token.
     * Without that config the extension simply keeps polling.
     */
    connectWebSocket() {
        this.disconnectWebSocket();
        const realtime = this._lastSessionData?.realtime;
        const WSClass = globalThis.WebSocket || global.WebSocket;
        if (!WSClass || !this._sessionId || !realtime?.key || !realtime?.ws_host) {
            return;
        }
        const sessionId = this._sessionId;
        const channels = [`private-lab-session.${sessionId}`, `private-lab-session.${sessionId}.chat`];
        const subscribed = new Set();
        try {
            const ws = new WSClass(`wss://${realtime.ws_host}/app/${encodeURIComponent(realtime.key)}?protocol=7&client=js&version=8.4.0&flash=false`);
            this._ws = ws;
            ws.onopen = () => {
                this._wsConnected = true;
            };
            ws.onmessage = async (event) => {
                let payload;
                try {
                    payload = JSON.parse(typeof event.data === 'string' ? event.data : event.data.toString());
                }
                catch {
                    return; // Ignore frame parsing errors
                }
                const data = typeof payload.data === 'string' ? this.safeJson(payload.data) : payload.data;
                switch (payload.event) {
                    case 'pusher:connection_established':
                        for (const channel of channels) {
                            try {
                                const res = await this.makeRequest('POST', `${API_PREFIX}/sessions/${sessionId}/broadcasting/auth`, {
                                    socket_id: data?.socket_id,
                                    channel_name: channel
                                });
                                const auth = res.status === 200 ? this.safeJson(res.body)?.auth : undefined;
                                if (auth && this._ws === ws) {
                                    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth } }));
                                }
                            }
                            catch {
                                // Stay on polling for this channel
                            }
                        }
                        return;
                    case 'pusher_internal:subscription_succeeded':
                        subscribed.add(payload.channel);
                        // Only stop polling once both channels deliver (chat also feeds paste suppression)
                        this._wsSubscribed = channels.every(c => subscribed.has(c));
                        this._wsRetryDelayMs = 10000;
                        return;
                    case 'pusher:ping':
                        ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                        return;
                    case 'chat.message':
                        if (data?.chat) {
                            this.handleIncomingWsChat(data.chat);
                        }
                        return;
                    case 'diff.updated':
                        if (data?.diff_stats) {
                            this._lastDiffStats = data.diff_stats;
                            this._view?.webview.postMessage({ type: 'diffUpdate', diffStats: this._lastDiffStats });
                        }
                        return;
                    case 'leaderboard.updated':
                        if (data?.leaderboard) {
                            this._view?.webview.postMessage({
                                type: 'leaderboardData',
                                leaderboard: data.leaderboard.leaderboard || [],
                                availability_mode: data.leaderboard.availability_mode || 'open',
                                shared_time_remaining_formatted: data.leaderboard.shared_time_remaining_formatted,
                                live_status: data.leaderboard.live_status
                            });
                        }
                        return;
                }
            };
            ws.onerror = () => {
                this._wsConnected = false;
            };
            ws.onclose = () => {
                if (this._ws !== ws) {
                    return; // a newer socket replaced this one; let it manage reconnects
                }
                this._wsConnected = false;
                this._wsSubscribed = false;
                if (this._sessionId && !this._wsReconnectTimer) {
                    // Back off: 10s, 20s, ... up to 5 min.
                    const delay = this._wsRetryDelayMs;
                    this._wsRetryDelayMs = Math.min(this._wsRetryDelayMs * 2, 300000);
                    this._wsReconnectTimer = setTimeout(() => {
                        this._wsReconnectTimer = undefined;
                        this.connectWebSocket();
                    }, delay);
                }
            };
        }
        catch {
            this._wsConnected = false;
        }
    }
    safeJson(text) {
        try {
            return JSON.parse(text);
        }
        catch {
            return undefined;
        }
    }
    disconnectWebSocket() {
        if (this._wsReconnectTimer) {
            clearTimeout(this._wsReconnectTimer);
            this._wsReconnectTimer = undefined;
        }
        if (this._ws) {
            try {
                this._ws.close();
            }
            catch { }
            this._ws = undefined;
        }
        this._wsConnected = false;
        this._wsSubscribed = false;
    }
    handleIncomingWsChat(chat) {
        if (!chat) {
            return;
        }
        if (chat.message && typeof chat.message === 'string') {
            this._recentChatSnippets.push(chat.message.trim());
        }
        if (chat.code_snippet && typeof chat.code_snippet === 'string') {
            this._recentChatSnippets.push(chat.code_snippet.trim());
        }
        this._lastChatCount++;
        if (this._isChatTabActive) {
            this._seenChatCount = this._lastChatCount;
        }
        else {
            this._unreadChatCount++;
        }
        this._view?.webview.postMessage({
            type: 'chatAppend',
            chat: chat,
            unreadCount: this._unreadChatCount
        });
    }
    /**
     * Ask once per session to install the latest build when the server says this one is too old.
     */
    warnIfOutdated(data) {
        if (!data?.extension_outdated || this._outdatedWarnedSessionId === this._sessionId) {
            return;
        }
        this._outdatedWarnedSessionId = this._sessionId;
        const required = data.extension_min_version ? ` ${data.extension_min_version}` : '';
        vscode.window.showWarningMessage(`CertiCode: this extension (v${EXTENSION_VERSION}) is outdated. Install version${required} or newer so your lab works correctly.`, 'Download update').then(choice => {
            if (choice === 'Download update' && data.extension_download_url) {
                vscode.env.openExternal(vscode.Uri.parse(data.extension_download_url));
            }
        });
    }
    /**
     * The server refused this build (426 extension_outdated). Retrying cannot help, so stop
     * every timer and tell the student to install the latest build.
     */
    handleExtensionRejected(body) {
        if (this._extensionRejected) {
            return;
        }
        let data = {};
        try {
            data = JSON.parse(body);
        }
        catch { }
        this._extensionRejected = {
            minVersion: typeof data.min_version === 'string' ? data.min_version : '',
            downloadUrl: typeof data.download_url === 'string' ? data.download_url : `${this._backendUrl}/downloads/certicode-labs.vsix`,
        };
        this._isReconnecting = false;
        this.stopSessionTimers();
        this.postExtensionOutdated();
        const required = this._extensionRejected.minVersion ? ` ${this._extensionRejected.minVersion} or newer` : '';
        vscode.window.showErrorMessage(`CertiCode: this extension (v${EXTENSION_VERSION}) is no longer supported. Install version${required} to continue your lab.`, 'Download update').then(choice => {
            if (choice === 'Download update' && this._extensionRejected?.downloadUrl) {
                vscode.env.openExternal(vscode.Uri.parse(this._extensionRejected.downloadUrl));
            }
        });
    }
    postExtensionOutdated() {
        this._view?.webview.postMessage({
            type: 'extensionOutdated',
            minVersion: this._extensionRejected?.minVersion || ''
        });
    }
    async syncSessionState() {
        if (!this._sessionId) {
            return;
        }
        try {
            const prefix = API_PREFIX;
            const result = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}`);
            if (!this._sessionId) {
                return;
            }
            if (result.status === 200) {
                this._isReconnecting = false;
                this._syncFailures = 0;
                const data = JSON.parse(result.body);
                this._lastSessionData = data;
                this.warnIfOutdated(data);
                if (data.realtime && !this._ws && !this._wsReconnectTimer) {
                    this.connectWebSocket();
                }
                if (data.started_at) {
                    const startedMs = new Date(data.started_at).getTime();
                    if (!isNaN(startedMs) && startedMs > 0) {
                        this._sessionStartTime = startedMs;
                    }
                }
                // Feature 1: Provision starter files on first connect
                if (!this._filesGenerated && data.laboratory?.starter_files && Array.isArray(data.laboratory.starter_files)) {
                    await this.provisionStarterFiles(data.laboratory.starter_files);
                }
                else {
                    await this.verifyWorkspaceFileIntegrity();
                }
                this._view?.webview.postMessage({
                    type: 'update',
                    data: data,
                    isReconnecting: false
                });
                // Fetch chats if group lab
                if (this.isGroupLab()) {
                    this.fetchChatMessages();
                }
            }
            else if (result.status === 401) {
                // Missing or wrong session token (e.g. connected by typing the ID instead of using the link).
                this._isReconnecting = false;
                this.stopSessionTimers();
                this._view?.webview.postMessage({
                    type: 'error',
                    message: 'This lab needs to be opened from the website. Open it on Certicode and click "Continue in VS Code".'
                });
                vscode.window.showErrorMessage('CertiCode: Not authorized for this lab session. Open the lab on the Certicode website and click "Continue in VS Code".');
            }
            else if (result.status === 426) {
                // makeRequest already stopped syncing and told the student to update
                return;
            }
            else if (result.status === 403) {
                this._isReconnecting = false;
                try {
                    const errData = JSON.parse(result.body);
                    this._view?.webview.postMessage({
                        type: 'liveLabBlocked',
                        error: errData.error || 'live_blocked',
                        message: errData.message || 'Access blocked for this Live Lab.'
                    });
                }
                catch {
                    this._view?.webview.postMessage({
                        type: 'liveLabBlocked',
                        error: 'live_blocked',
                        message: 'Access blocked for this Live Lab.'
                    });
                }
            }
            else {
                throw new Error(`Server returned status code: ${result.status}`);
            }
        }
        catch (err) {
            this._isReconnecting = true;
            this._syncFailures++;
            this._view?.webview.postMessage({
                type: 'reconnecting',
                isReconnecting: true
            });
        }
    }
    /**
     * Helper to detect document language from file extension.
     */
    detectLanguage(filename) {
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
     * Make the lab's read-only starter files read-only in the editor (files.readonlyInclude,
     * VS Code 1.79+) and remember their original content so guardReadonlyFile can restore
     * them if they change anyway (older VS Code, or edits that slip through).
     */
    async applyReadonlyStarterFiles(rootUri, starterFiles) {
        this._readonlyFiles.clear();
        const patterns = {};
        for (const file of starterFiles) {
            if (!file.is_readonly) {
                continue;
            }
            const fileUri = this.resolveWorkspaceFile(rootUri, file.name);
            if (!fileUri) {
                continue;
            }
            this._readonlyFiles.set(fileUri.toString(), { name: file.name, content: file.content || '' });
            patterns[String(file.name).replace(/\\/g, '/').replace(/^\.?\//, '')] = true;
        }
        if (Object.keys(patterns).length === 0) {
            return;
        }
        try {
            const config = vscode.workspace.getConfiguration('files', rootUri);
            const current = config.get('readonlyInclude') || {};
            await config.update('readonlyInclude', { ...current, ...patterns }, vscode.ConfigurationTarget.Workspace);
        }
        catch (e) {
            // Older VS Code doesn't know the setting; the guard below still protects the files
            console.warn('CertiCode: could not set files.readonlyInclude', e);
        }
    }
    /**
     * Undo any change to a read-only starter file by restoring its original content.
     */
    async guardReadonlyFile(event) {
        if (this._restoringReadonly || event.contentChanges.length === 0) {
            return;
        }
        const entry = this._readonlyFiles.get(event.document.uri.toString());
        if (!entry || event.document.getText() === entry.content) {
            return;
        }
        this._restoringReadonly = true;
        try {
            const doc = event.document;
            const edit = new vscode.WorkspaceEdit();
            edit.replace(doc.uri, new vscode.Range(doc.positionAt(0), doc.positionAt(doc.getText().length)), entry.content);
            await vscode.workspace.applyEdit(edit);
            if (doc.isDirty) {
                await doc.save();
            }
        }
        finally {
            this._restoringReadonly = false;
        }
        vscode.window.showWarningMessage(`CertiCode: "${entry.name}" is read-only for this lab, so your change was undone.`);
    }
    /**
     * The session that wrote each starter file in a workspace folder (file name -> session id).
     */
    starterFileOwners(rootUri) {
        return { ...this._globalState.get(`${STARTER_OWNERS_KEY}:${rootUri.toString()}`, {}) };
    }
    async saveStarterFileOwners(rootUri, owners) {
        await this._globalState.update(`${STARTER_OWNERS_KEY}:${rootUri.toString()}`, owners);
    }
    /**
     * Feature 1: Generate starter files in active workspace and open primary file.
     */
    async provisionStarterFiles(starterFiles) {
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
                    }
                    catch (e) {
                        console.error('Failed to open untitled document', e);
                    }
                }
            }
            vscode.window.showInformationMessage('CertiCode Labs: Starter code opened in editor! Open a workspace folder (File > Open Folder) to save files directly to disk.', 'Open Folder').then(selection => {
                if (selection === 'Open Folder') {
                    vscode.commands.executeCommand('vscode.openFolder');
                }
            });
            return;
        }
        const rootUri = workspaceFolders[0].uri;
        let primaryUri = null;
        const enc = new TextEncoder();
        const sessionId = this._sessionId;
        // Which session wrote each starter file here, so a returning student keeps their work
        // while a different lab (or a new attempt) in the same folder gets its own files
        const owners = this.starterFileOwners(rootUri);
        const fromElsewhere = [];
        for (const file of starterFiles) {
            const fileUri = this.resolveWorkspaceFile(rootUri, file.name);
            if (!fileUri) {
                if (file.name) {
                    vscode.window.showWarningMessage(`CertiCode: Skipped starter file "${file.name}" because it points outside the workspace.`);
                }
                continue;
            }
            // Cache snapshot for diff engine (cleared when the session changes)
            this._starterFileSnapshots.set(file.name, file.content || '');
            let exists = false;
            try {
                await vscode.workspace.fs.stat(fileUri);
                exists = true;
            }
            catch {
                exists = false;
            }
            if (!exists) {
                await vscode.workspace.fs.writeFile(fileUri, enc.encode(file.content || ''));
                owners[file.name] = sessionId;
            }
            else if (owners[file.name] !== sessionId) {
                const current = new TextDecoder().decode(await vscode.workspace.fs.readFile(fileUri));
                if (current === (file.content || '')) {
                    owners[file.name] = sessionId;
                }
                else {
                    fromElsewhere.push({ name: file.name, uri: fileUri, content: file.content || '' });
                }
            }
            if (file.is_primary) {
                primaryUri = fileUri;
            }
        }
        if (fromElsewhere.length > 0) {
            const names = fromElsewhere.map(f => f.name).join(', ');
            const choice = await vscode.window.showWarningMessage(`This folder already has ${names} from another lab or attempt. Replace ${fromElsewhere.length === 1 ? 'it' : 'them'} with this lab's starter files? The current ${fromElsewhere.length === 1 ? 'copy is' : 'copies are'} saved in .certicode/previous first.`, { modal: true }, 'Replace files', 'Keep my files');
            if (choice === 'Replace files') {
                const backupRoot = vscode.Uri.joinPath(rootUri, '.certicode', 'previous', String(Date.now()));
                for (const file of fromElsewhere) {
                    const old = await vscode.workspace.fs.readFile(file.uri);
                    const backupUri = this.resolveWorkspaceFile(backupRoot, file.name);
                    if (backupUri) {
                        await vscode.workspace.fs.createDirectory(vscode.Uri.joinPath(backupUri, '..'));
                        await vscode.workspace.fs.writeFile(backupUri, old);
                    }
                    await vscode.workspace.fs.writeFile(file.uri, enc.encode(file.content));
                    owners[file.name] = sessionId;
                }
            }
            else if (choice === 'Keep my files') {
                // The student chose to work on what is there; don't ask again for this session
                fromElsewhere.forEach(f => { owners[f.name] = sessionId; });
            }
        }
        await this.saveStarterFileOwners(rootUri, owners);
        await this.applyReadonlyStarterFiles(rootUri, starterFiles);
        // Fallback to first file if none marked primary
        if (!primaryUri && starterFiles.length > 0) {
            primaryUri = this.resolveWorkspaceFile(rootUri, starterFiles[0].name) ?? null;
        }
        // Auto-open primary starter file
        if (primaryUri) {
            try {
                const doc = await vscode.workspace.openTextDocument(primaryUri);
                await vscode.window.showTextDocument(doc, { preview: false });
            }
            catch (e) {
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
    async verifyWorkspaceFileIntegrity() {
        if (!this._lastSessionData?.laboratory?.starter_files) {
            return;
        }
        const starterFiles = this._lastSessionData.laboratory.starter_files;
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) {
            return;
        }
        const rootUri = workspaceFolders[0].uri;
        const missing = [];
        for (const file of starterFiles) {
            const fileUri = this.resolveWorkspaceFile(rootUri, file.name);
            if (!fileUri) {
                continue;
            }
            try {
                await vscode.workspace.fs.stat(fileUri);
            }
            catch {
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
    setupWorkspaceWatchers() {
        // Registered once for the provider's lifetime; resolveWebviewView can run again when the
        // view is moved or reopened, and re-registering would double-count every keystroke and paste.
        if (this._workspaceDisposables.length > 0) {
            return;
        }
        this._fileWatcher = vscode.workspace.createFileSystemWatcher('**/*');
        this._workspaceDisposables.push(this._fileWatcher, this._fileWatcher.onDidCreate(() => this.verifyWorkspaceFileIntegrity()), this._fileWatcher.onDidDelete(() => this.verifyWorkspaceFileIntegrity()), 
        // Watch document changes for live diff tracking, WPM baseline, and paste anomaly detection
        vscode.workspace.onDidChangeTextDocument(event => this.handleDocumentChange(event)), vscode.workspace.onDidChangeTextDocument(event => { void this.guardReadonlyFile(event); }), vscode.workspace.onDidSaveTextDocument(doc => {
            if (this._sessionId && this._lastSessionData && this.isStudentDocument(doc)) {
                this.computeAndSyncDiffs();
            }
        }));
    }
    handleDocumentChange(event) {
        if (this._restoringReadonly) {
            return;
        }
        if (!this._sessionId || !this._lastSessionData) {
            return;
        }
        // Output panels, logs, settings and files outside the lab are not the student typing.
        if (!this.isStudentDocument(event.document) || event.contentChanges.length === 0) {
            return;
        }
        this.recordActivity();
        const fileName = event.document.fileName;
        const isTracked = this._lastSessionData.laboratory?.starter_files?.some((f) => typeof f.name === 'string' && fileName.replace(/\\/g, '/').endsWith(f.name.replace(/\\/g, '/')));
        // Undo/redo re-applies text the student already had; it is neither typing nor a paste.
        const isUndoRedo = event.reason === vscode.TextDocumentChangeReason.Undo
            || event.reason === vscode.TextDocumentChangeReason.Redo;
        // Feature 4: Keystroke velocity & Paste anomaly interceptor
        for (const change of isUndoRedo ? [] : event.contentChanges) {
            if (change.text.length === 1) {
                this.recordKeystroke(1);
            }
            else if (change.text.length > 1 && change.text.length < 25) {
                this.recordKeystroke(change.text.length);
            }
            else if (change.text.length >= 25 && (change.text.includes('\n') || change.text.split(/\s+/).length > 3)) {
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
                // Suppression Rule B: Permitted Collaboration / Team Chat match.
                // Short chat text is ignored so a one-word message cannot excuse arbitrary pastes.
                let isSuppressedB = false;
                if (!isSuppressedA && this._recentChatSnippets.length > 0) {
                    isSuppressedB = this._recentChatSnippets.some(snippet => snippet.length >= MIN_CHAT_SNIPPET_LENGTH
                        && (snippet.includes(trimmedPaste) || trimmedPaste.includes(snippet)));
                }
                // Flagging Rule: External paste without internal/chat justification. Snippets,
                // quick fixes, completions and reloads from disk also insert big blocks, so only
                // flag text that is really on the clipboard, in a document the student edited.
                if (!isSuppressedA && !isSuppressedB && event.document.isDirty) {
                    const file = vscode.workspace.asRelativePath(event.document.uri);
                    const wpm = this._currentWpm;
                    void this.matchesClipboard(change.text).then(fromClipboard => {
                        if (fromClipboard) {
                            this.sendTelemetry('paste_anomaly', {
                                pasted_length: change.text.length,
                                snippet: change.text.slice(0, 2000),
                                file,
                                wpm
                            });
                        }
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
    }
    /**
     * Whether inserted text is what is on the clipboard, ignoring whitespace (pasting re-indents).
     */
    async matchesClipboard(inserted) {
        let clipboard = '';
        try {
            clipboard = await vscode.env.clipboard.readText();
        }
        catch {
            return false;
        }
        const squash = (text) => text.replace(/\s+/g, '');
        const pasted = squash(inserted);
        return pasted.length > 0 && squash(clipboard) === pasted;
    }
    /**
     * Feature 5: OS-Level Focus Loss Sensor
     */
    setupFocusTracking() {
        if (this._windowStateListener) {
            this._windowStateListener.dispose();
        }
        this._windowStateListener = vscode.window.onDidChangeWindowState((state) => {
            if (!this._sessionId) {
                return;
            }
            if (!state.focused) {
                // OS Focus lost (switched away from VS Code)
                this.sendTelemetry('focus_lost', {
                    timestamp: new Date().toISOString()
                });
            }
        });
    }
    /**
     * Feature 4 & 11: Record typing keystrokes and calculate overall session average WPM.
     * Idle time is not excluded from WPM calculation — overall pace is maintained across whole session.
     */
    recordKeystroke(charsCount = 1) {
        this.recordActivity();
        const now = Date.now();
        this._totalKeystrokes += charsCount;
        // Overall session average: (totalKeystrokes / 5) / totalElapsedMinutes
        // Idle time is NOT excluded, giving a true picture of pace across the whole session
        const elapsedMinutes = Math.max((now - this._sessionStartTime) / 60000, 1 / 6);
        const words = this._totalKeystrokes / 5;
        this._currentWpm = Math.round(words / elapsedMinutes);
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
     * Feature 11: Record user activity to reset idle timer.
     * Activity that resets the timer: keystrokes, cursor movement/scrolling, file switching, running code, submitting, or sending a team chat message.
     */
    recordActivity() {
        this._lastActivityTime = Date.now();
        this._lastIdleAlertMinutes = 0;
    }
    /**
     * Feature 11: Setup activity event listeners across editor and workspace.
     */
    setupActivityListeners() {
        this.disposeActivityListeners();
        // 1. Cursor movement / selection changes
        this._cursorListener = vscode.window.onDidChangeTextEditorSelection(e => {
            if (this._sessionId && this.isStudentDocument(e.textEditor.document)) {
                this.recordActivity();
            }
        });
        // 2. Visible ranges / scrolling
        this._scrollListener = vscode.window.onDidChangeTextEditorVisibleRanges(e => {
            if (this._sessionId && this.isStudentDocument(e.textEditor.document)) {
                this.recordActivity();
            }
        });
        // 3. File switching (active text editor changed)
        this._activeEditorListener = vscode.window.onDidChangeActiveTextEditor(editor => {
            if (this._sessionId && editor && this.isStudentDocument(editor.document)) {
                this.recordActivity();
            }
        });
        // 4. Running code via tasks
        if (vscode.tasks && typeof vscode.tasks.onDidStartTask === 'function') {
            this._taskListener = vscode.tasks.onDidStartTask(() => {
                if (this._sessionId) {
                    this.recordActivity();
                }
            });
        }
        // 5. Terminal opened / executed
        this._terminalListener = vscode.window.onDidOpenTerminal(() => {
            if (this._sessionId) {
                this.recordActivity();
            }
        });
    }
    disposeActivityListeners() {
        if (this._cursorListener) {
            this._cursorListener.dispose();
            this._cursorListener = undefined;
        }
        if (this._scrollListener) {
            this._scrollListener.dispose();
            this._scrollListener = undefined;
        }
        if (this._activeEditorListener) {
            this._activeEditorListener.dispose();
            this._activeEditorListener = undefined;
        }
        if (this._taskListener) {
            this._taskListener.dispose();
            this._taskListener = undefined;
        }
        if (this._terminalListener) {
            this._terminalListener.dispose();
            this._terminalListener = undefined;
        }
    }
    /**
     * Feature 11: Periodic check for continuous idle inactivity.
     * Fires at 10 minutes of continuous inactivity, then recurs every additional 10 minutes (20, 30, ...).
     * Idle time is not excluded from WPM calculation — overall session average decays naturally.
     */
    checkIdleState() {
        if (!this._sessionId) {
            return;
        }
        const now = Date.now();
        const idleMs = now - this._lastActivityTime;
        const idleMinutes = Math.floor(idleMs / 60000);
        // Update overall session average WPM (idle time not excluded)
        const totalMinutes = Math.max((now - this._sessionStartTime) / 60000, 1 / 6);
        const words = this._totalKeystrokes / 5;
        this._currentWpm = Math.round(words / totalMinutes);
        // Continuous inactivity threshold: 10 minutes, recurring every 10 min
        if (idleMinutes >= 10) {
            const idleBucket = Math.floor(idleMinutes / 10) * 10;
            if (idleBucket > this._lastIdleAlertMinutes) {
                this._lastIdleAlertMinutes = idleBucket;
                this.sendTelemetry('idle_timeout', {
                    idle_minutes: idleBucket,
                    timestamp: new Date().toISOString()
                });
                this._view?.webview.postMessage({
                    type: 'idleAlert',
                    idleMinutes: idleBucket
                });
            }
        }
    }
    /**
     * Helper to dispatch telemetry logs & anomalies to backend
     */
    async sendTelemetry(eventType, payload) {
        if (!this._sessionId) {
            return;
        }
        try {
            const prefix = API_PREFIX;
            await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/telemetry`, {
                event_type: eventType,
                payload: payload
            });
        }
        catch (e) {
            console.error('Failed to send telemetry', e);
        }
    }
    /**
     * Feature 5: Fetch live session leaderboard
     */
    async fetchLeaderboard() {
        if (!this._sessionId) {
            return;
        }
        try {
            const prefix = API_PREFIX;
            const res = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}/leaderboard`);
            if (!this._sessionId) {
                return;
            }
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
        }
        catch (e) {
            console.error('Failed to fetch leaderboard', e);
        }
    }
    /**
     * Feature 2: Compute line diffs and sync with backend
     */
    async computeAndSyncDiffs() {
        if (!this._sessionId || !this._lastSessionData) {
            return;
        }
        const workspaceFolders = vscode.workspace.workspaceFolders;
        if (!workspaceFolders || workspaceFolders.length === 0) {
            return;
        }
        const starterFiles = this._lastSessionData.laboratory?.starter_files || [];
        let totalAdded = 0;
        let totalDeleted = 0;
        let totalModified = 0;
        const filesSummary = [];
        for (const sfile of starterFiles) {
            const initialContent = this._starterFileSnapshots.get(sfile.name) ?? (sfile.content || '');
            const fileUri = this.resolveWorkspaceFile(workspaceFolders[0].uri, sfile.name);
            if (!fileUri) {
                continue;
            }
            let currentContent = '';
            try {
                currentContent = await this.readWorkspaceText(fileUri);
            }
            catch {
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
            const prefix = API_PREFIX;
            await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/diff`, {
                lines_added: totalAdded,
                lines_deleted: totalDeleted,
                lines_modified: totalModified,
                files: filesSummary
            });
        }
        catch (e) {
            console.error('Failed to sync diff to backend', e);
        }
    }
    /**
     * Calculate line additions, deletions, and modifications relative to snapshot.
     */
    calculateLineDiff(initial, current) {
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
    async fetchChatMessages() {
        if (!this._sessionId || !this.isGroupLab()) {
            return;
        }
        try {
            const prefix = API_PREFIX;
            const res = await this.makeRequest('GET', `${prefix}/sessions/${this._sessionId}/chat`);
            if (!this._sessionId) {
                return;
            }
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
                // Unread = messages that arrived since the chat tab was last open, not the total.
                this._lastChatCount = chats.length;
                if (this._isChatTabActive) {
                    this._seenChatCount = chats.length;
                }
                this._unreadChatCount = Math.max(0, chats.length - this._seenChatCount);
                this._view?.webview.postMessage({
                    type: 'chatUpdate',
                    chats: chats,
                    unreadCount: this._unreadChatCount
                });
            }
        }
        catch (e) {
            console.error('Failed to fetch chat messages', e);
        }
    }
    /**
     * Feature 3: Send ephemeral chat message
     */
    async handleSendChat(message, codeSnippet) {
        if (!this._sessionId) {
            return;
        }
        this.recordActivity();
        try {
            const prefix = API_PREFIX;
            const res = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/chat`, {
                message,
                code_snippet: codeSnippet || null
            });
            if (res.status === 200) {
                await this.fetchChatMessages();
            }
        }
        catch (err) {
            vscode.window.showErrorMessage(`Send Chat Error: ${err.message}`);
        }
    }
    /**
     * Gather the files to evaluate for Check Progress and Submit. Lab starter files are read
     * from their open editors when open, so unsaved edits are what gets graded.
     */
    async collectSubmissionFiles() {
        const workspaceFolders = vscode.workspace.workspaceFolders;
        let primaryCode = '';
        let primaryFileName = '';
        const filesPayload = [];
        const fileUris = [];
        if (workspaceFolders && workspaceFolders.length > 0) {
            const starterFiles = this._lastSessionData?.laboratory?.starter_files || [];
            for (const sfile of starterFiles) {
                const fileUri = this.resolveWorkspaceFile(workspaceFolders[0].uri, sfile.name);
                if (!fileUri) {
                    continue;
                }
                try {
                    const content = await this.readWorkspaceText(fileUri);
                    filesPayload.push({ name: sfile.name, content, is_primary: !!sfile.is_primary });
                    fileUris.push(fileUri);
                    if (sfile.is_primary || !primaryCode) {
                        primaryCode = content;
                        primaryFileName = sfile.name;
                    }
                }
                catch { }
            }
        }
        const isNonCodeDoc = (name) => {
            return /\.(md|txt|json|env|log|lock|ya?ml)$/i.test(name) || name.includes('.git');
        };
        // Fallback to the active editor only if it is the student's own code file
        if (!primaryCode) {
            const activeEditor = vscode.window.activeTextEditor;
            if (activeEditor && this.isStudentDocument(activeEditor.document) && !isNonCodeDoc(activeEditor.document.fileName)) {
                primaryCode = activeEditor.document.getText();
                primaryFileName = vscode.workspace.asRelativePath(activeEditor.document.uri);
                fileUris.push(activeEditor.document.uri);
            }
        }
        // Finally, search the workspace for source files
        if (!primaryCode && workspaceFolders && workspaceFolders.length > 0) {
            try {
                const codeFiles = await vscode.workspace.findFiles('**/*.{c,cpp,h,java,py,js,ts,go,rs,cs,php}', '**/{node_modules,vendor,.git,build,out,dist}/**', 10);
                for (const uri of codeFiles) {
                    const content = await this.readWorkspaceText(uri);
                    const relPath = vscode.workspace.asRelativePath(uri);
                    filesPayload.push({ name: relPath, content, is_primary: !primaryCode });
                    fileUris.push(uri);
                    if (!primaryCode) {
                        primaryCode = content;
                        primaryFileName = relPath;
                    }
                }
            }
            catch { }
        }
        return { primaryCode, primaryFileName, filesPayload, fileUris };
    }
    detectSubmissionLanguage(primaryFileName) {
        if (primaryFileName) {
            return this.detectLanguage(primaryFileName);
        }
        const firstStarter = this._lastSessionData?.laboratory?.starter_files?.[0]?.name;
        return firstStarter ? this.detectLanguage(firstStarter) : 'c';
    }
    /**
     * Compiler/language-server errors for the submitted files only. Errors in other documents
     * (settings, other projects) must not count against the student's submission.
     */
    collectDiagnostics(fileUris) {
        const collected = [];
        for (const uri of fileUris) {
            for (const d of vscode.languages.getDiagnostics(uri)) {
                if (d.severity === vscode.DiagnosticSeverity.Error) {
                    collected.push({
                        file: vscode.workspace.asRelativePath(uri),
                        line: d.range.start.line + 1,
                        message: d.message,
                        source: d.source || 'compiler'
                    });
                }
            }
        }
        return collected;
    }
    /**
     * Run one grading request at a time. Grading can take a minute, and a second click used to
     * start another full grading run on the server.
     */
    async runGrading(grade) {
        if (this._gradingInFlight) {
            vscode.window.showInformationMessage('Your code is still being graded. The result will appear here when it is ready.');
            return;
        }
        this._gradingInFlight = true;
        try {
            await grade();
        }
        finally {
            this._gradingInFlight = false;
        }
    }
    /**
     * The server's explanation for a failed request: its message, else its error code.
     */
    failureReason(body, fallback) {
        try {
            const data = JSON.parse(body);
            return data.message || data.error || fallback;
        }
        catch {
            return fallback;
        }
    }
    async handleCheckProgress() {
        return this.runGrading(() => this.checkProgress());
    }
    async checkProgress() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }
        this.recordActivity();
        const { primaryCode, primaryFileName, filesPayload, fileUris } = await this.collectSubmissionFiles();
        if (!primaryCode && filesPayload.length === 0) {
            vscode.window.showErrorMessage('No code files found in workspace. Please open or create your solution file (e.g. main.c, Solution.java) to check progress.');
            this._view?.webview.postMessage({
                type: 'checkResult',
                data: {
                    status: 'empty',
                    completed_tasks: [],
                    performance_score: 0,
                    evaluation: {
                        tasks: (this._lastSessionData?.laboratory?.tasks_definition || []).map((t) => ({
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
        const detectedLang = this.detectSubmissionLanguage(primaryFileName);
        const collectedDiagnostics = this.collectDiagnostics(fileUris);
        this._view?.webview.postMessage({ type: 'status', message: 'Analyzing code with AI evaluator...' });
        try {
            const prefix = API_PREFIX;
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/check-progress`, {
                code: primaryCode,
                files: filesPayload,
                language: detectedLang,
                diagnostics: collectedDiagnostics
            }, GRADING_TIMEOUT_MS);
            if (result.status === 200) {
                this._isReconnecting = false;
                const responseData = JSON.parse(result.body);
                vscode.window.showInformationMessage('Check Progress: AI evaluation completed.');
                this._view?.webview.postMessage({
                    type: 'checkResult',
                    data: responseData
                });
                this.syncSessionState();
            }
            else {
                const reason = this.failureReason(result.body, 'Check progress failed.');
                vscode.window.showErrorMessage(`Check Progress Failed: ${reason}`);
                this._view?.webview.postMessage({ type: 'error', message: reason });
                if (result.status === 409) {
                    this.syncSessionState();
                }
            }
        }
        catch (err) {
            vscode.window.showErrorMessage(`Check Progress Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }
    async handleSubmit() {
        return this.runGrading(() => this.submit());
    }
    async submit() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }
        this.recordActivity();
        // Feature 1 Client-Side Filename Integrity Check
        if (this._missingFiles && this._missingFiles.length > 0) {
            vscode.window.showErrorMessage(`Submission blocked: Missing required file(s): ${this._missingFiles.join(', ')}. Please restore or recreate the file to submit.`);
            return;
        }
        const { primaryCode, primaryFileName, filesPayload, fileUris } = await this.collectSubmissionFiles();
        if (!primaryCode && filesPayload.length === 0) {
            vscode.window.showErrorMessage('No runnable code files found in workspace. Please create or open your solution file before submitting.');
            return;
        }
        const confirm = await vscode.window.showWarningMessage('Submit Lab Solution: Are you sure you want to finalize your submission? This compiles, runs test cases, evaluates competencies, and completes your session.', { modal: true }, 'Yes, Submit');
        if (confirm !== 'Yes, Submit') {
            return;
        }
        const detectedLang = this.detectSubmissionLanguage(primaryFileName);
        const collectedDiagnostics = this.collectDiagnostics(fileUris);
        this._view?.webview.postMessage({ type: 'status', message: 'Running final submission & compilation...' });
        try {
            const prefix = API_PREFIX;
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/submit`, {
                code: primaryCode,
                files: filesPayload,
                language: detectedLang,
                diagnostics: collectedDiagnostics
            }, GRADING_TIMEOUT_MS);
            if (result.status === 200) {
                const responseData = JSON.parse(result.body);
                const score = responseData.performance_score ?? 0;
                this._view?.webview.postMessage({
                    type: 'submitResult',
                    data: responseData
                });
                this.stopSessionTimers();
                vscode.window.showInformationMessage(`Lab Session Completed! Final Score: ${score}%`, 'Exit Lab Workspace', 'Stay in Editor').then(selection => {
                    if (selection === 'Exit Lab Workspace') {
                        this.handleExit(false);
                    }
                });
            }
            else {
                const reason = this.failureReason(result.body, 'Submission failed.');
                vscode.window.showErrorMessage(`Submission Failed: ${reason}`);
                this._view?.webview.postMessage({ type: 'error', message: reason });
                if (result.status === 409) {
                    this.syncSessionState();
                }
            }
        }
        catch (err) {
            vscode.window.showErrorMessage(`Submission Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }
    async handleExit(confirmExit = true) {
        if (confirmExit) {
            const confirm = await vscode.window.showWarningMessage('Are you sure you want to exit this workspace? Your attempt stays open: come back from the lab page to continue and submit before time runs out.', { modal: true }, 'Confirm Exit');
            if (confirm !== 'Confirm Exit') {
                return;
            }
        }
        const sid = this._sessionId;
        if (sid) {
            try {
                const prefix = API_PREFIX;
                await this.makeRequest('POST', `${prefix}/sessions/${sid}/end`);
            }
            catch (err) {
                console.warn('Failed to notify backend on session exit:', err);
            }
        }
        this.stopSessionTimers();
        if (this._windowStateListener) {
            this._windowStateListener.dispose();
            this._windowStateListener = undefined;
        }
        this._sessionId = undefined;
        this._lastSessionData = null;
        this._filesGenerated = false;
        this._starterFileSnapshots.clear();
        this._missingFiles = [];
        this._keystrokeTimestamps = [];
        this._totalKeystrokes = 0;
        this._currentWpm = 0;
        this._recentChatSnippets = [];
        this._lastActivityTime = Date.now();
        this._lastIdleAlertMinutes = 0;
        if (this._view) {
            this._view.webview.html = this._getHtmlForWebview(this._view.webview);
        }
        vscode.window.showInformationMessage('Exited CertiCode Labs session.');
    }
    /**
     * Native HTTP/HTTPS client requests.
     */
    makeRequest(method, path, bodyData, timeoutMs = 15000) {
        return new Promise((resolve, reject) => {
            try {
                const targetUrl = new url_1.URL(`${this._backendUrl}${path}`);
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Client': 'vscode',
                    'X-Extension-Version': EXTENSION_VERSION,
                };
                if (this._apiToken) {
                    // Per-session secret from the vscode:// deep link; scoped to this session only.
                    headers['X-Session-Token'] = this._apiToken;
                }
                const options = {
                    method: method,
                    hostname: targetUrl.hostname,
                    port: targetUrl.port ? parseInt(targetUrl.port, 10) : (targetUrl.protocol === 'https:' ? 443 : 80),
                    path: targetUrl.pathname + targetUrl.search,
                    headers: headers
                };
                const handleResponse = (res) => {
                    let responseData = '';
                    res.on('data', (chunk) => {
                        responseData += chunk;
                    });
                    res.on('end', () => {
                        if (res.statusCode === 426 && responseData.includes('extension_outdated')) {
                            this.handleExtensionRejected(responseData);
                        }
                        resolve({
                            status: res.statusCode || 0,
                            body: responseData
                        });
                    });
                };
                const req = targetUrl.protocol === 'https:'
                    ? https.request(options, handleResponse)
                    : http.request(options, handleResponse);
                req.setTimeout(timeoutMs, () => {
                    req.destroy(new Error(`Request timed out after ${Math.round(timeoutMs / 1000)} seconds`));
                });
                req.on('error', (err) => {
                    reject(err);
                });
                if (bodyData) {
                    req.write(JSON.stringify(bodyData));
                }
                req.end();
            }
            catch (err) {
                reject(err);
            }
        });
    }
    _getHtmlForWebview(webview) {
        const faceApiScriptUri = webview.asWebviewUri(vscode.Uri.joinPath(this._extensionUri, 'media', 'js', 'face-api.min.js'));
        const modelsUri = webview.asWebviewUri(vscode.Uri.joinPath(this._extensionUri, 'media', 'models'));
        const initialSessionId = this._sessionId ? Number(this._sessionId) : 'null';
        const initialBackendUrl = JSON.stringify(this._backendUrl || '');
        const initialApiToken = JSON.stringify(this._apiToken || '');
        const initialSessionData = this._lastSessionData
            ? JSON.stringify(this._lastSessionData).replace(/<\/script/gi, '<\\/script')
            : 'null';
        const showSessionDirectly = !!this._sessionId;
        const connScreenStyle = showSessionDirectly ? 'display: none;' : '';
        const sessionScreenStyle = showSessionDirectly ? 'display: flex;' : 'display: none;';
        const initialStatusText = this._lastSessionData ? 'Connected' : (this._sessionId ? `Connecting to Session #${this._sessionId}...` : 'Connected');
        const initialStatusClass = this._lastSessionData ? 'status-connected' : 'status-reconnecting';
        const extensionVersion = String(vscode.extensions.getExtension('certicode.certicode-labs')?.packageJSON?.version ?? '').replace(/[^0-9A-Za-z.+-]/g, '') || 'dev';
        const nonce = crypto.randomBytes(16).toString('base64');
        const csp = [
            "default-src 'none'",
            `script-src 'nonce-${nonce}'`,
            `style-src ${webview.cspSource} 'unsafe-inline'`,
            `img-src ${webview.cspSource} data: blob:`,
            `font-src ${webview.cspSource}`,
            `connect-src ${webview.cspSource}`,
        ].join('; ');
        return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="${csp}">
    <title>CertiCode Labs IDE</title>
    <script nonce="${nonce}" src="${faceApiScriptUri}"></script>
    <style>
        /* Everything maps to VS Code theme tokens, so the panel follows light, dark and high-contrast themes. */
        :root {
            --fg: var(--vscode-foreground);
            --muted: var(--vscode-descriptionForeground);
            --bg: var(--vscode-sideBar-background, var(--vscode-editor-background));
            --surface: var(--vscode-editorWidget-background, var(--vscode-editor-background));
            --hover: var(--vscode-list-hoverBackground);
            --border: var(--vscode-widget-border, var(--vscode-panel-border, rgba(128, 128, 128, 0.28)));
            --hc: var(--vscode-contrastBorder, transparent);
            --focus: var(--vscode-focusBorder);
            --mono: var(--vscode-editor-font-family, ui-monospace, monospace);
            --ok: var(--vscode-charts-green, #3ecf8e);
            --danger: var(--vscode-charts-red, #f14c4c);
            --warn: var(--vscode-charts-yellow, #cca700);
            --changed: var(--vscode-gitDecoration-modifiedResourceForeground, #e2c08d);
            --ok-soft: rgba(62, 207, 142, 0.12);
            --danger-soft: rgba(241, 76, 76, 0.12);
            --warn-soft: rgba(204, 167, 0, 0.14);
            --track: rgba(128, 128, 128, 0.22);
            --r-sm: 3px;
            --r-md: 6px;
        }
        @supports (color: color-mix(in srgb, red 50%, transparent)) {
            :root {
                --ok-soft: color-mix(in srgb, var(--ok) 14%, transparent);
                --danger-soft: color-mix(in srgb, var(--danger) 14%, transparent);
                --warn-soft: color-mix(in srgb, var(--warn) 16%, transparent);
                --track: color-mix(in srgb, var(--fg) 12%, transparent);
            }
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            padding: 0;
            font-family: var(--vscode-font-family, system-ui, sans-serif);
            font-size: var(--vscode-font-size, 13px);
            line-height: 1.45;
            color: var(--fg);
            background: var(--bg);
        }
        [hidden] { display: none !important; }
        .muted { color: var(--muted); }
        .num { font-family: var(--mono); font-variant-numeric: tabular-nums; }
        svg.icon { width: 14px; height: 14px; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }

        :focus { outline: none; }
        :focus-visible { outline: 1px solid var(--focus); outline-offset: 1px; }

        /* Screens */
        .screen { min-height: 100%; flex-direction: column; }
        #connection-screen { display: flex; padding: 20px 14px; gap: 14px; }
        #session-screen { display: flex; }

        /* Connect screen */
        .brand-mark { width: 28px; height: 28px; color: var(--ok); }
        .brand-mark svg { width: 100%; height: 100%; fill: currentColor; }
        .connect-title { margin: 2px 0 0; font-size: 1.25em; font-weight: 600; letter-spacing: -0.01em; }
        .connect-lede { margin: 0; color: var(--muted); max-width: 34em; text-wrap: pretty; }
        .steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .steps li { display: flex; gap: 10px; align-items: flex-start; }
        .steps strong, .field-hint strong { font-weight: 600; }
        .step-num {
            width: 20px; height: 20px; flex-shrink: 0; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.85em; font-weight: 600; font-variant-numeric: tabular-nums;
            border: 1px solid var(--border); color: var(--muted);
        }
        .field-hint { font-size: 0.88em; color: var(--muted); }
        input, textarea {
            width: 100%;
            background: var(--vscode-input-background);
            color: var(--vscode-input-foreground);
            border: 1px solid var(--vscode-input-border, var(--border));
            border-radius: var(--r-sm);
            padding: 6px 8px;
            font: inherit;
        }
        input::placeholder, textarea::placeholder { color: var(--vscode-input-placeholderForeground); }
        input:focus, textarea:focus { outline: 1px solid var(--focus); outline-offset: -1px; border-color: var(--focus); }
        textarea { resize: vertical; font-family: var(--mono); font-size: 0.92em; }

        /* Buttons */
        button { font: inherit; cursor: pointer; }
        button:disabled { cursor: not-allowed; opacity: 0.5; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid var(--vscode-button-border, transparent);
            border-radius: var(--r-sm);
            font-weight: 500;
            transition: background-color 120ms ease, transform 80ms ease;
        }
        .btn:active:not(:disabled) { transform: translateY(1px); }
        .btn-block { width: 100%; }
        .btn-primary { background: var(--vscode-button-background); color: var(--vscode-button-foreground); }
        .btn-primary:hover:not(:disabled) { background: var(--vscode-button-hoverBackground); }
        .btn-secondary { background: var(--vscode-button-secondaryBackground); color: var(--vscode-button-secondaryForeground); }
        .btn-secondary:hover:not(:disabled) { background: var(--vscode-button-secondaryHoverBackground); }
        .btn-ghost { background: transparent; color: var(--muted); border-color: transparent; }
        .btn-ghost:hover:not(:disabled) { background: var(--hover); color: var(--fg); }
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; padding: 0;
            background: transparent; color: var(--muted);
            border: 1px solid transparent; border-radius: var(--r-sm);
        }
        .icon-btn:hover:not(:disabled), .icon-btn[aria-pressed="true"] { background: var(--vscode-toolbar-hoverBackground, var(--hover)); color: var(--fg); }
        .link-btn { background: none; border: 0; padding: 0; color: var(--vscode-textLink-foreground); text-decoration: underline; text-underline-offset: 2px; }
        .link-btn:hover { color: var(--vscode-textLink-activeForeground); }

        /* Session header */
        .session-head { padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid var(--border); }
        .connection-status { display: flex; align-items: center; gap: 8px; font-size: 0.9em; min-height: 20px; }
        .status-left { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--muted); flex-shrink: 0; }
        .status-connected .status-dot { background: var(--ok); box-shadow: 0 0 0 3px var(--ok-soft); }
        .status-reconnecting .status-dot { background: var(--warn); animation: pulse 1.4s ease-in-out infinite; }
        .status-reconnecting .status-left { color: var(--warn); }
        .tag {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.85em; padding: 0 6px; line-height: 18px;
            border-radius: var(--r-sm);
            background: var(--vscode-badge-background); color: var(--vscode-badge-foreground);
            white-space: nowrap;
        }
        .tag-quiet { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .tag-ok { background: var(--ok-soft); color: var(--ok); }
        .diff-pill { margin-left: auto; display: inline-flex; gap: 6px; font-family: var(--mono); font-size: 0.88em; font-variant-numeric: tabular-nums; }
        .diff-added { color: var(--ok); }
        .diff-deleted { color: var(--danger); }
        .diff-modified { color: var(--changed); }

        .lab-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
        .lab-heading-text { min-width: 0; }
        .eyebrow { display: block; font-size: 0.85em; color: var(--muted); }
        .lab-title { margin: 1px 0 0; font-size: 1.15em; font-weight: 600; letter-spacing: -0.01em; line-height: 1.3; overflow-wrap: anywhere; }
        .timer { text-align: right; flex-shrink: 0; }
        .timer-value { display: block; font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 1.55em; font-weight: 600; line-height: 1.15; letter-spacing: -0.02em; }
        .timer.timer-low .timer-value { color: var(--warn); }
        .timer.timer-done .timer-value { color: var(--muted); }

        /* Notices */
        .notices { display: flex; flex-direction: column; gap: 8px; padding: 0 12px; }
        .notices:not(:empty) { padding-top: 10px; }
        .notice {
            display: flex; gap: 8px; align-items: flex-start;
            padding: 8px 10px;
            border-radius: var(--r-md);
            border: 1px solid var(--hc);
            border-left: 3px solid var(--muted);
            background: var(--surface);
            font-size: 0.95em;
        }
        .notice .icon { margin-top: 2px; }
        .notice-body { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .notice-body strong { font-weight: 600; }
        .notice-close { margin: -3px -4px 0 0; width: 22px; height: 22px; }
        .notice[data-tone="danger"] { border-left-color: var(--danger); background: var(--danger-soft); }
        .notice[data-tone="danger"] > .icon { color: var(--danger); }
        .notice[data-tone="warn"] { border-left-color: var(--warn); background: var(--warn-soft); }
        .notice[data-tone="warn"] > .icon { color: var(--warn); }
        .notice[data-tone="ok"] { border-left-color: var(--ok); background: var(--ok-soft); }
        .notice[data-tone="ok"] > .icon { color: var(--ok); }

        /* Tabs, styled like VS Code's own panel tabs */
        .tabs-nav {
            display: flex;
            gap: 0;
            padding: 0 6px;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            scrollbar-width: none;
            position: sticky; top: 0; z-index: 2;
            background: var(--bg);
        }
        .tabs-nav::-webkit-scrollbar { display: none; }
        .tab-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 8px 5px 7px;
            background: none; border: 0; border-bottom: 1px solid transparent; margin-bottom: -1px;
            color: var(--vscode-panelTitle-inactiveForeground, var(--muted));
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--fg); }
        .tab-btn.active { color: var(--vscode-panelTitle-activeForeground, var(--fg)); border-bottom-color: var(--vscode-panelTitle-activeBorder, var(--focus)); }
        .tab-badge {
            min-width: 16px; padding: 0 4px; line-height: 16px; text-align: center;
            border-radius: 8px; font-size: 0.8em; font-weight: 600;
            background: var(--vscode-activityBarBadge-background, var(--vscode-badge-background));
            color: var(--vscode-activityBarBadge-foreground, var(--vscode-badge-foreground));
        }

        .tab-content { display: none; flex-direction: column; gap: 16px; padding: 12px; flex: 1; }
        .tab-content.active { display: flex; }

        .section { display: flex; flex-direction: column; gap: 6px; }
        .section-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 22px; }
        .section-title { margin: 0; font-size: 0.92em; font-weight: 600; }
        .section-note { font-size: 0.88em; color: var(--muted); }

        .lab-desc { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--fg); max-width: 60em; }
        .lab-desc.is-clamped { max-height: 9.5em; overflow: hidden; mask-image: linear-gradient(to bottom, #000 70%, transparent); -webkit-mask-image: linear-gradient(to bottom, #000 70%, transparent); }

        /* List rows (files, diffs, leaderboard) */
        .list { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden; }
        .list > * + * { border-top: 1px solid var(--border); }
        .row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; min-height: 30px; }
        .row-main { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .row-sub { display: block; font-size: 0.86em; color: var(--muted); overflow: hidden; text-overflow: ellipsis; }
        button.row { width: 100%; background: none; border: 0; color: inherit; text-align: left; transition: background-color 120ms ease; }
        button.row:hover { background: var(--hover); }
        button.row:focus-visible { outline-offset: -1px; }
        .file-name { font-family: var(--mono); font-size: 0.95em; }
        .empty { padding: 18px 12px; text-align: center; color: var(--muted); font-size: 0.95em; text-wrap: balance; }
        .list > .empty { border: 0; }

        /* Tasks */
        .progress { display: flex; flex-direction: column; gap: 6px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 0.92em; }
        .progress-track { height: 4px; border-radius: 2px; background: var(--track); overflow: hidden; }
        .progress-fill { height: 100%; width: 0; background: var(--ok); border-radius: 2px; transition: width 300ms ease; }
        .task-list { display: flex; flex-direction: column; gap: 2px; }
        .task-item { display: flex; gap: 8px; padding: 7px 8px; border-radius: var(--r-md); }
        .task-item:hover { background: var(--hover); }
        .task-check { width: 16px; height: 16px; margin-top: 1px; color: var(--muted); flex-shrink: 0; }
        .task-check svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
        .task-check .tick { display: none; }
        .task-item.is-done .task-check { color: var(--ok); }
        .task-item.is-done .task-check .tick { display: inline; }
        .task-body { flex: 1; min-width: 0; }
        .task-title { overflow-wrap: anywhere; }
        .task-item.is-done .task-title { color: var(--muted); }
        .task-badge { font-size: 0.82em; color: var(--muted); white-space: nowrap; margin-top: 1px; }
        .badge-complete { color: var(--ok); }
        .task-feedback { margin-top: 4px; font-size: 0.92em; color: var(--muted); overflow-wrap: anywhere; }

        .feedback-box { padding: 10px 12px; border-radius: var(--r-md); background: var(--surface); border: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; }
        .score { font-size: 1.35em; font-weight: 600; font-family: var(--mono); font-variant-numeric: tabular-nums; }
        .feedback-sub { padding-top: 8px; border-top: 1px solid var(--border); font-size: 0.95em; }
        .console-output {
            margin: 0;
            padding: 8px 10px;
            border-radius: var(--r-md);
            border: 1px solid var(--border);
            background: var(--vscode-terminal-background, var(--vscode-editor-background));
            color: var(--vscode-terminal-foreground, var(--fg));
            font-family: var(--mono); font-size: 0.92em;
            white-space: pre-wrap; overflow-wrap: anywhere;
            max-height: 180px; overflow-y: auto;
        }
        .console-output.has-error { border-left: 3px solid var(--danger); }

        /* Leaderboard */
        .rank { width: 22px; text-align: right; font-family: var(--mono); font-variant-numeric: tabular-nums; color: var(--muted); flex-shrink: 0; }
        .rank-top { color: var(--fg); font-weight: 600; }
        .leaderboard-row.current-user { background: var(--vscode-list-inactiveSelectionBackground, var(--hover)); box-shadow: inset 2px 0 0 var(--ok); }
        .score-chip { font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 0.92em; flex-shrink: 0; }
        .live-clock { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; font-size: 0.92em; background: var(--ok-soft); }

        /* Changes */
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); border: 1px solid var(--border); border-radius: var(--r-md); }
        .stat { padding: 8px 10px; }
        .stat + .stat { border-left: 1px solid var(--border); }
        .summary-grid { grid-template-columns: repeat(auto-fit, minmax(64px, 1fr)); margin-bottom: 8px; }
        .stat-value { display: block; font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 1.25em; font-weight: 600; line-height: 1.25; }
        .stat-label { font-size: 0.86em; color: var(--muted); }
        .diff-bar { display: flex; width: 48px; height: 4px; border-radius: 2px; overflow: hidden; background: var(--track); flex-shrink: 0; }
        .diff-bar span { height: 100%; }
        .diff-bar .add { background: var(--ok); }
        .diff-bar .del { background: var(--danger); }
        .diff-nums { font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 0.9em; display: inline-flex; gap: 6px; flex-shrink: 0; }
        .avatar {
            width: 18px; height: 18px; border-radius: 5px; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.72em; font-weight: 700; color: #fff; text-transform: uppercase;
        }

        /* Chat */
        #tab-chat { gap: 8px; }
        .chat-feed { flex: 1; min-height: 200px; max-height: 50vh; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 4px 2px; }
        .chat-msg { display: flex; gap: 8px; }
        .chat-body { flex: 1; min-width: 0; }
        .chat-header { display: flex; align-items: baseline; gap: 6px; font-size: 0.92em; }
        .chat-author { font-weight: 600; }
        .chat-time { color: var(--muted); font-size: 0.92em; }
        .chat-text { overflow-wrap: anywhere; white-space: pre-wrap; }
        .chat-snippet {
            margin: 4px 0 0; padding: 6px 8px;
            border-radius: var(--r-sm); border: 1px solid var(--border);
            background: var(--vscode-textCodeBlock-background, var(--surface));
            font-family: var(--mono); font-size: 0.9em;
            white-space: pre; overflow: auto; max-height: 140px;
        }
        .composer { display: flex; flex-direction: column; gap: 6px; padding-top: 8px; border-top: 1px solid var(--border); }
        .chat-input-row { display: flex; gap: 4px; align-items: center; }
        .chat-input-row input { flex: 1; min-width: 0; }

        /* Status line and submission result */
        .loader-status { margin: 0 12px; padding: 6px 0; display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 0.95em; }
        .loader-status::before {
            content: ''; width: 12px; height: 12px; flex-shrink: 0; border-radius: 50%;
            border: 1.5px solid var(--track); border-top-color: var(--focus);
            animation: spin 800ms linear infinite;
        }
        .result-panel { margin: 0 12px 4px; padding: 12px; border-radius: var(--r-md); background: var(--ok-soft); border: 1px solid var(--hc); display: flex; flex-direction: column; gap: 10px; }
        .result-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .result-title { font-weight: 600; display: inline-flex; align-items: center; gap: 6px; color: var(--ok); }
        .result-score { font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 1.4em; font-weight: 600; }

        /* Actions stay pinned to the bottom of the view */
        .actions {
            position: sticky; bottom: 0; z-index: 2;
            margin-top: auto;
            padding: 10px 12px 12px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
            background: var(--bg);
            border-top: 1px solid var(--border);
        }
        .actions .btn-ghost { grid-column: 1 / -1; padding: 3px 8px; font-size: 0.95em; }
        @media (max-width: 220px) { .actions { grid-template-columns: 1fr; } }
        .version-tag { font-family: var(--mono); font-size: 0.8em; color: var(--muted); text-align: left; opacity: 0.8; }
        .actions .version-tag { grid-column: 1 / -1; }
        #connection-screen .version-tag { margin-top: auto; }

        /* High-contrast themes: draw every edge with the contrast border */
        body.vscode-high-contrast .list,
        body.vscode-high-contrast .stat-grid,
        body.vscode-high-contrast .feedback-box,
        body.vscode-high-contrast .notice { border-color: var(--hc); }
        body.vscode-high-contrast .tab-btn.active { border-bottom-width: 2px; }

        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <div class="notices" id="update-notices" hidden>
        <div class="notice" data-tone="danger" role="alert">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17v.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <div class="notice-body">
                <strong>Update the Certicode extension to continue</strong>
                <div id="extension-outdated-text">This version is no longer supported, so your lab cannot sync.</div>
                <button type="button" id="extension-update-link" class="link-btn">Download the latest version</button>
            </div>
        </div>
    </div>

    <main id="connection-screen" class="screen" style="${connScreenStyle}">
        <div class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 256 256"><path d="M156 39.84A96 96 0 1 0 156 216.16L156 163.50A52 52 0 1 1 156 92.50Z"/><rect x="170" y="198" width="66" height="26"/></svg>
        </div>
        <div>
            <h1 class="connect-title">Start your lab in the browser</h1>
            <p class="connect-lede">Labs open from the Certicode website. This panel fills in once your lab starts.</p>
        </div>

        <ol class="steps">
            <li><span class="step-num">1</span><span>Open your lab on the Certicode website.</span></li>
            <li><span class="step-num">2</span><span>Click <strong>Start lab</strong> and finish the camera check.</span></li>
            <li><span class="step-num">3</span><span>When your browser asks, allow it to open VS Code. Your starter files appear here.</span></li>
        </ol>

        <p class="field-hint">Keep the lab tab open while you code. If VS Code doesn't connect, click <strong>Continue in VS Code</strong> on the lab page.</p>

        <div id="connect-error" class="notice" data-tone="danger" role="alert" hidden>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
            <div class="notice-body" id="connect-error-text"></div>
        </div>

        <div class="version-tag">v${extensionVersion}</div>
    </main>

    <div id="session-screen" class="screen" style="${sessionScreenStyle}">
        <header class="session-head">
            <div id="status-banner" class="connection-status ${initialStatusClass}" role="status">
                <span class="status-left"><span class="status-dot"></span><span id="status-text">${initialStatusText}</span></span>
                <span class="diff-pill" id="diff-counter-pill" title="Lines added and deleted since the starter files">
                    <span class="diff-added" id="pill-added">+0</span><span class="diff-deleted" id="pill-deleted">-0</span>
                </span>
            </div>

            <div class="lab-heading">
                <div class="lab-heading-text">
                    <span class="eyebrow">Lab</span>
                    <h1 class="lab-title" id="lab-title">Loading lab…</h1>
                </div>
                <div class="timer" id="timer-box">
                    <span id="timer-label" class="eyebrow">Time remaining</span>
                    <span id="timer-display" class="timer-value" role="timer">00:00</span>
                </div>
            </div>
        </header>

        <div class="notices" id="notices">
            <div id="integrity-alert" class="notice" data-tone="danger" role="alert" hidden>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17v.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <div class="notice-body">
                    <div id="integrity-text">A required starter file is missing.</div>
                    <button type="button" id="regenerate-files-link" class="link-btn">Restore missing files</button>
                </div>
            </div>

            <div id="live-lab-alert" class="notice" data-tone="danger" role="alert" hidden>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <div class="notice-body" id="live-lab-alert-text">Live lab alert</div>
            </div>

            <div id="app-notice" class="notice" role="alert" hidden>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                <div class="notice-body" id="app-notice-text"></div>
                <button type="button" class="icon-btn notice-close" id="app-notice-close" aria-label="Dismiss">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </div>

        <nav class="tabs-nav" role="tablist" aria-label="Lab sections">
            <button class="tab-btn active" role="tab" aria-selected="true" data-tab="tab-instructions">Overview</button>
            <button class="tab-btn" role="tab" aria-selected="false" data-tab="tab-tasks">Tasks <span class="tab-badge" id="tasks-count-badge" hidden></span></button>
            <button class="tab-btn" role="tab" aria-selected="false" data-tab="tab-leaderboard" id="tab-leaderboard-btn">Standings</button>
            <button class="tab-btn" role="tab" aria-selected="false" data-tab="tab-diff">Changes</button>
            <button class="tab-btn" role="tab" aria-selected="false" data-tab="tab-chat" id="tab-chat-btn" hidden>Chat <span class="tab-badge" id="chat-badge" hidden>0</span></button>
        </nav>

        <!-- Overview: brief and starter files -->
        <section id="tab-instructions" class="tab-content active" role="tabpanel">
            <div class="section">
                <div class="section-head"><h2 class="section-title">Brief</h2><button type="button" class="link-btn" id="lab-desc-toggle" hidden>Show all</button></div>
                <p class="lab-desc" id="lab-desc"></p>
            </div>

            <div class="section">
                <div class="section-head"><h2 class="section-title">Starter files</h2><span class="section-note">Click to open</span></div>
                <div class="list" id="starter-files-list">
                    <div class="empty">Starter files appear here once the lab loads.</div>
                </div>
            </div>
        </section>

        <!-- Tasks and evaluation -->
        <section id="tab-tasks" class="tab-content" role="tabpanel">
            <div class="progress">
                <div class="progress-label"><span id="task-progress-text">No tasks yet</span><span class="muted num" id="task-progress-pct"></span></div>
                <div class="progress-track"><div class="progress-fill" id="task-progress-bar"></div></div>
            </div>

            <div class="task-list" id="tasks-container"></div>

            <div id="llm-feedback-section" class="section" hidden>
                <h2 class="section-title">AI feedback</h2>
                <div class="feedback-box" id="llm-feedback-text"></div>
            </div>

            <div id="console-output-section" class="section" hidden>
                <h2 class="section-title">Console output</h2>
                <pre class="console-output" id="console-output-text"></pre>
            </div>
        </section>

        <!-- Live leaderboard -->
        <section id="tab-leaderboard" class="tab-content" role="tabpanel">
            <div class="section">
                <div class="section-head">
                    <h2 class="section-title">Standings</h2>
                    <button type="button" id="leaderboard-refresh-btn" class="icon-btn" title="Refresh standings" aria-label="Refresh standings">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66M20 4v7h-7"/></svg>
                    </button>
                </div>
                <span class="section-note">Ranked by tasks done. Ties go to whoever took less time.</span>
            </div>
            <div class="list" id="leaderboard-container">
                <div class="empty">Loading standings…</div>
            </div>
        </section>

        <!-- Code changes -->
        <section id="tab-diff" class="tab-content" role="tabpanel">
            <div class="stat-grid">
                <div class="stat"><span class="stat-value diff-added" id="diff-total-added">+0</span><span class="stat-label">Added</span></div>
                <div class="stat"><span class="stat-value diff-deleted" id="diff-total-deleted">-0</span><span class="stat-label">Deleted</span></div>
                <div class="stat"><span class="stat-value diff-modified" id="diff-total-modified">~0</span><span class="stat-label">Modified</span></div>
            </div>

            <div class="section">
                <h2 class="section-title">By file</h2>
                <div class="list" id="diff-files-container">
                    <div class="empty">No changes yet. Edits to the starter files show up here.</div>
                </div>
            </div>

            <div class="section" id="teammates-card" hidden>
                <h2 class="section-title">Team contributions</h2>
                <div class="list" id="teammates-breakdown"></div>
            </div>
        </section>

        <!-- Team chat -->
        <section id="tab-chat" class="tab-content" role="tabpanel">
            <div class="chat-feed" id="chat-messages-container" aria-live="polite">
                <div class="empty">Only your teammates in this session can see these messages.</div>
            </div>

            <div class="composer">
                <div id="snippet-container" hidden>
                    <textarea id="chat-snippet-input" placeholder="Paste a code snippet" rows="3" spellcheck="false"></textarea>
                </div>
                <div class="chat-input-row">
                    <button type="button" id="snippet-toggle-btn" class="icon-btn" title="Attach a code snippet" aria-label="Attach a code snippet" aria-pressed="false">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 8l-4 4 4 4M15 8l4 4-4 4"/></svg>
                    </button>
                    <input type="text" id="chat-input" placeholder="Message your team" aria-label="Message">
                    <button type="button" id="chat-send-btn" class="icon-btn" title="Send (Enter)" aria-label="Send">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </section>

        <div id="action-status" class="loader-status" role="status" hidden></div>

        <div id="submission-complete-banner" class="result-panel" hidden>
            <div class="result-head">
                <span class="result-title">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    Lab submitted
                </span>
                <span class="result-score" id="submission-final-score">0%</span>
            </div>
            <div id="submission-summary" class="stat-grid summary-grid" hidden>
                <div class="stat"><span class="stat-value" id="summary-time">--:--</span><span class="stat-label">Time</span></div>
                <div class="stat"><span class="stat-value" id="summary-tasks">0/0</span><span class="stat-label">Tasks</span></div>
                <div class="stat"><span class="stat-value" id="summary-wpm">0</span><span class="stat-label">WPM</span></div>
                <div class="stat" id="summary-place-cell" hidden><span class="stat-value" id="summary-place">-</span><span class="stat-label">Place</span></div>
            </div>
            <button id="post-submit-exit-btn" class="btn btn-secondary btn-block">Close lab workspace</button>
        </div>

        <footer class="actions">
            <button id="check-progress-btn" class="btn btn-secondary" title="Have the AI grader check your work without submitting">Check progress</button>
            <button id="submit-btn" class="btn btn-primary" title="Submit your final work for grading">Submit lab</button>
            <button id="exit-btn" class="btn btn-ghost">Leave session</button>
            <div class="version-tag">v${extensionVersion}</div>
        </footer>
    </div>

    <script nonce="${nonce}">
        const vscode = acquireVsCodeApi();

        // Every server-, teammate- or AI-provided value goes through esc() before innerHTML.
        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        }

        // Colors land in style attributes, so only plain hex colors are allowed.
        function safeColor(value) {
            return /^#[0-9a-fA-F]{3,8}$/.test(String(value || '')) ? value : '#2f9e6e';
        }

        function initial(name) {
            const ch = String(name || '?').trim().charAt(0);
            return esc(ch || '?');
        }

        const MODELS_BASE_PATH = "${modelsUri}";
        const INITIAL_SESSION_ID = ${initialSessionId};
        const INITIAL_BACKEND_URL = ${initialBackendUrl};
        const INITIAL_API_TOKEN = ${initialApiToken};
        const INITIAL_SESSION_DATA = ${initialSessionData};

        const $ = (id) => document.getElementById(id);
        const show = (el, visible = true) => { if (el) el.hidden = !visible; };

        const connectionScreen = $('connection-screen');
        const sessionScreen = $('session-screen');

        // Labs start from the website's deep link, so the connect screen only shows instructions and errors
        const connectError = $('connect-error');
        const connectErrorText = $('connect-error-text');

        // Session status & timer
        const statusBanner = $('status-banner');
        const statusText = $('status-text');
        const timerBox = $('timer-box');
        const timerDisplay = $('timer-display');
        const timerLabel = $('timer-label');
        const labTitle = $('lab-title');
        const labDesc = $('lab-desc');
        const labDescToggle = $('lab-desc-toggle');
        const starterFilesList = $('starter-files-list');
        const tasksContainer = $('tasks-container');
        const tasksCountBadge = $('tasks-count-badge');
        const taskProgressText = $('task-progress-text');
        const taskProgressPct = $('task-progress-pct');
        const taskProgressBar = $('task-progress-bar');
        const actionStatus = $('action-status');

        // Notices
        const integrityAlert = $('integrity-alert');
        const integrityText = $('integrity-text');
        const regenerateFilesLink = $('regenerate-files-link');
        const liveLabAlert = $('live-lab-alert');
        const liveLabAlertText = $('live-lab-alert-text');
        const appNotice = $('app-notice');
        const appNoticeText = $('app-notice-text');

        // Diff UI
        const pillAdded = $('pill-added');
        const pillDeleted = $('pill-deleted');
        const diffTotalAdded = $('diff-total-added');
        const diffTotalDeleted = $('diff-total-deleted');
        const diffTotalModified = $('diff-total-modified');
        const diffFilesContainer = $('diff-files-container');
        const teammatesCard = $('teammates-card');
        const teammatesBreakdown = $('teammates-breakdown');

        // Team chat UI
        const chatBadge = $('chat-badge');
        const chatMessagesContainer = $('chat-messages-container');
        const chatInput = $('chat-input');
        const chatSnippetInput = $('chat-snippet-input');
        const chatSendBtn = $('chat-send-btn');
        const snippetToggleBtn = $('snippet-toggle-btn');
        const snippetContainer = $('snippet-container');

        // Feedback & console
        const llmFeedbackSection = $('llm-feedback-section');
        const llmFeedbackText = $('llm-feedback-text');
        const consoleOutputSection = $('console-output-section');
        const consoleOutputText = $('console-output-text');

        // Buttons
        const checkProgressBtn = $('check-progress-btn');
        const submitBtn = $('submit-btn');
        const exitBtn = $('exit-btn');
        const submissionBanner = $('submission-complete-banner');

        let timerVal = 0;
        let timerInterval = null;
        let isCountDown = true;
        let isIntegrityValid = true;
        let taskFeedbackMap = {};
        let chatHasMessages = false;

        const ICON_TICK = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path class="tick" d="M8.5 12.25l2.5 2.5 4.5-5"/></svg>';
        const ICON_FILE = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5z"/><path d="M13.5 3v5.5H19"/></svg>';

        // Webviews block window.alert(), so errors show as an inline notice instead.
        function showNotice(text, tone = 'danger') {
            appNoticeText.textContent = text;
            appNotice.dataset.tone = tone;
            show(appNotice);
        }
        $('app-notice-close').addEventListener('click', () => show(appNotice, false));

        function setLiveAlert(tone, text) {
            if (!liveLabAlert || !liveLabAlertText) return;
            if (!tone) { show(liveLabAlert, false); return; }
            liveLabAlert.dataset.tone = tone;
            liveLabAlertText.textContent = text;
            show(liveLabAlert);
        }

        // Set from the 'status' message until the grade (or an error) comes back, so the
        // periodic session refresh doesn't turn the buttons back on mid-grade
        let gradingInFlight = false;
        // Set once the server refuses this build; actions stay locked until the next connect
        let extensionOutdated = false;

        function setActionsLocked(locked) {
            checkProgressBtn.disabled = locked;
            submitBtn.disabled = locked || !isIntegrityValid;
        }

        function setStatus(state, text) {
            statusBanner.className = 'connection-status ' + (state === 'connected' ? 'status-connected' : 'status-reconnecting');
            statusText.textContent = text;
        }

        function setChatBadge(count) {
            const n = Number(count) || 0;
            chatBadge.textContent = String(n);
            show(chatBadge, n > 0);
        }

        // Tabs
        const tabButtons = document.querySelectorAll('.tab-btn');
        function activateTab(btn) {
            tabButtons.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            const targetId = btn.getAttribute('data-tab');
            const targetEl = $(targetId);
            if (targetEl) targetEl.classList.add('active');

            // Tell the extension whether chat is on screen, so it can count unread messages
            const isChat = targetId === 'tab-chat';
            vscode.postMessage({ type: 'setChatTabActive', active: isChat });
            if (isChat) {
                setChatBadge(0);
                chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
            }

            if (targetId === 'tab-leaderboard') {
                vscode.postMessage({ type: 'getLeaderboard' });
            }
        }
        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => activateTab(btn));
            btn.addEventListener('keydown', (e) => {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                e.preventDefault();
                // Hidden tabs (Chat in solo labs) are skipped
                const visibleTabs = Array.from(tabButtons).filter(b => !b.hidden);
                const i = visibleTabs.indexOf(btn);
                const next = visibleTabs[(i + (e.key === 'ArrowRight' ? 1 : visibleTabs.length - 1)) % visibleTabs.length];
                next.focus();
                activateTab(next);
            });
        });

        $('extension-update-link').addEventListener('click', () => {
            vscode.postMessage({ type: 'openUpdateDownload' });
        });

        $('leaderboard-refresh-btn').addEventListener('click', () => {
            vscode.postMessage({ type: 'getLeaderboard' });
        });

        regenerateFilesLink.addEventListener('click', () => {
            vscode.postMessage({ type: 'retryFileGeneration' });
        });

        labDescToggle.addEventListener('click', () => {
            const clamped = labDesc.classList.toggle('is-clamped');
            labDescToggle.textContent = clamped ? 'Show all' : 'Show less';
        });

        snippetToggleBtn.addEventListener('click', () => {
            const open = snippetContainer.hidden;
            show(snippetContainer, open);
            snippetToggleBtn.setAttribute('aria-pressed', String(open));
            if (open) chatSnippetInput.focus();
        });

        checkProgressBtn.addEventListener('click', () => {
            vscode.postMessage({ type: 'checkProgress' });
        });

        submitBtn.addEventListener('click', () => {
            if (!isIntegrityValid) {
                showNotice('You can\\'t submit yet: a required starter file is missing from your workspace.');
                return;
            }
            vscode.postMessage({ type: 'submit' });
        });

        exitBtn.addEventListener('click', () => {
            vscode.postMessage({ type: 'exit' });
        });

        $('post-submit-exit-btn').addEventListener('click', () => {
            vscode.postMessage({ type: 'exit', skipConfirm: true });
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
            show(snippetContainer, false);
            snippetToggleBtn.setAttribute('aria-pressed', 'false');
        }

        function renderChatMessage(c) {
            const el = document.createElement('div');
            el.className = 'chat-msg';
            el.innerHTML = \`
                <span class="avatar" style="background-color: \${safeColor(c.avatar_color)};" aria-hidden="true">\${initial(c.user_name)}</span>
                <div class="chat-body">
                    <div class="chat-header">
                        <span class="chat-author">\${esc(c.user_name)}</span>
                        <span class="chat-time">\${esc(c.time)}</span>
                    </div>
                    <div class="chat-text">\${esc(c.message)}</div>
                    \${c.code_snippet ? \`<pre class="chat-snippet">\${esc(c.code_snippet)}</pre>\` : ''}
                </div>
            \`;
            return el;
        }

        function appendChat(c) {
            if (!chatHasMessages) {
                chatMessagesContainer.innerHTML = '';
                chatHasMessages = true;
            }
            chatMessagesContainer.appendChild(renderChatMessage(c));
            chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
        }

        function markTask(id, done) {
            const badgeEl = $(\`badge-task-\${id}\`);
            if (!badgeEl) return;
            badgeEl.className = 'task-badge ' + (done ? 'badge-complete' : 'badge-pending');
            badgeEl.textContent = done ? 'Done' : 'To do';
            const item = badgeEl.closest('.task-item');
            if (item) item.classList.toggle('is-done', done);
        }

        function updateTaskProgress() {
            const total = tasksContainer.querySelectorAll('.task-item').length;
            const done = tasksContainer.querySelectorAll('.task-item.is-done').length;
            const pct = total ? Math.round((done / total) * 100) : 0;
            taskProgressText.textContent = total ? \`\${done} of \${total} tasks done\` : 'This lab has no tasks';
            taskProgressPct.textContent = total ? pct + '%' : '';
            taskProgressBar.style.width = pct + '%';
            tasksCountBadge.textContent = total ? \`\${done}/\${total}\` : '';
            show(tasksCountBadge, total > 0);
        }

        // Handle incoming extension messages
        window.addEventListener('message', event => {
            const message = event.data;
            switch (message.type) {
                case 'status':
                    gradingInFlight = true;
                    actionStatus.textContent = message.message;
                    show(actionStatus);
                    checkProgressBtn.disabled = true;
                    submitBtn.disabled = true;
                    break;

                case 'error':
                    gradingInFlight = false;
                    show(actionStatus, false);
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = !isIntegrityValid;
                    if (connectionScreen.style.display !== 'none') {
                        connectErrorText.textContent = message.message;
                        show(connectError);
                    } else {
                        showNotice(message.message);
                    }
                    break;

                case 'idleAlert':
                    showNotice(\`No activity for \${Number(message.idleMinutes) || 0} minutes. Idle time is visible to your instructor.\`, 'warn');
                    break;

                case 'unreadReset':
                    setChatBadge(0);
                    break;

                case 'integrityStatus':
                    isIntegrityValid = message.valid;
                    if (message.valid) {
                        show(integrityAlert, false);
                        submitBtn.disabled = false;
                    } else {
                        integrityText.innerHTML = \`<strong>Missing \${esc(message.missing.join(', '))}.</strong> You need this file to submit.\`;
                        show(integrityAlert);
                        submitBtn.disabled = true;
                    }
                    break;

                case 'diffUpdate': {
                    const ds = message.diffStats || {};
                    const add = ds.lines_added || 0;
                    const del = ds.lines_deleted || 0;
                    const mod = ds.lines_modified || 0;

                    pillAdded.textContent = \`+\${add}\`;
                    pillDeleted.textContent = \`-\${del}\`;
                    diffTotalAdded.textContent = \`+\${add}\`;
                    diffTotalDeleted.textContent = \`-\${del}\`;
                    diffTotalModified.textContent = \`~\${mod}\`;

                    if (ds.files && ds.files.length > 0) {
                        diffFilesContainer.innerHTML = '';
                        ds.files.forEach(f => {
                            const a = Number(f.added) || 0;
                            const d = Number(f.deleted) || 0;
                            const sum = a + d;
                            const addPct = sum ? Math.round((a / sum) * 100) : 0;
                            const item = document.createElement('div');
                            item.className = 'row';
                            item.innerHTML = \`
                                <span class="row-main file-name" title="\${esc(f.name)}">\${esc(f.name)}</span>
                                <span class="diff-nums"><span class="diff-added">+\${a}</span><span class="diff-deleted">-\${d}</span></span>
                                <span class="diff-bar" aria-hidden="true"><span class="add" style="width: \${addPct}%;"></span><span class="del" style="width: \${sum ? 100 - addPct : 0}%;"></span></span>
                            \`;
                            diffFilesContainer.appendChild(item);
                        });
                    }
                    break;
                }

                case 'chatUpdate': {
                    const chats = message.chats || [];
                    if (chats.length > 0) {
                        chatMessagesContainer.innerHTML = '';
                        chatHasMessages = true;
                        chats.forEach(c => chatMessagesContainer.appendChild(renderChatMessage(c)));
                        chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
                    }
                    if (message.unreadCount > 0) setChatBadge(message.unreadCount);
                    break;
                }

                case 'chatAppend':
                    if (message.chat) appendChat(message.chat);
                    if (message.unreadCount > 0) setChatBadge(message.unreadCount);
                    break;

                case 'leaderboardData': {
                    const list = message.leaderboard || [];
                    const leaderboardContainer = $('leaderboard-container');
                    if (!leaderboardContainer) break;
                    leaderboardContainer.innerHTML = '';

                    // Live labs share one clock across the class
                    if (message.availability_mode === 'live') {
                        const clock = document.createElement('div');
                        clock.className = 'live-clock';
                        clock.innerHTML = \`
                            <span>Shared live clock</span>
                            <span class="num">\${esc(message.shared_time_remaining_formatted || '00:00')} left</span>
                        \`;
                        leaderboardContainer.appendChild(clock);
                    }

                    if (list.length === 0) {
                        const emptyEl = document.createElement('div');
                        emptyEl.className = 'empty';
                        emptyEl.textContent = 'Nobody is ranked yet. Standings appear once someone finishes a task.';
                        leaderboardContainer.appendChild(emptyEl);
                    } else {
                        list.forEach(item => {
                            const row = document.createElement('div');
                            row.className = 'row leaderboard-row' + (item.is_current ? ' current-user' : '');
                            const sub = (item.is_team && item.group_name ? esc(item.group_name) + ' · ' : '') + esc(item.elapsed_time);
                            row.innerHTML = \`
                                <span class="rank\${item.rank <= 3 ? ' rank-top' : ''}">\${esc(item.rank)}</span>
                                <span class="row-main">
                                    \${esc(item.name)}\${item.is_current ? ' <span class="muted">(you)</span>' : ''}
                                    <span class="row-sub num">\${sub}</span>
                                </span>
                                <span class="score-chip" title="Tasks done">\${esc(item.tasks_completed)} done</span>
                            \`;
                            leaderboardContainer.appendChild(row);
                        });
                    }
                    break;
                }

                case 'liveLabBlocked':
                    setLiveAlert('danger', message.message || 'This live lab is closed to you right now.');
                    checkProgressBtn.disabled = true;
                    submitBtn.disabled = true;
                    if (timerInterval) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                    }
                    timerLabel.textContent = message.error === 'live_not_started' ? 'Not started' : 'Live lab ended';
                    timerBox.className = 'timer timer-done';
                    break;

                case 'reconnecting':
                    setStatus('reconnecting', 'Reconnecting…');
                    break;

                case 'extensionOutdated':
                    extensionOutdated = true;
                    $('extension-outdated-text').textContent = message.minVersion
                        ? 'This version is no longer supported, so your lab cannot sync. Install version ' + message.minVersion + ' or newer, then open the lab again from the website.'
                        : 'This version is no longer supported, so your lab cannot sync. Install the latest version, then open the lab again from the website.';
                    show($('update-notices'));
                    setStatus('reconnecting', 'Update required');
                    setActionsLocked(true);
                    break;

                case 'disconnected':
                    if (timerInterval) clearInterval(timerInterval);
                    timerInterval = null;
                    connectionScreen.style.display = 'flex';
                    sessionScreen.style.display = 'none';
                    show(actionStatus, false);
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit lab';
                    show(submissionBanner, false);
                    break;

                case 'update':
                    handleSessionUpdate(message.data);
                    break;

                case 'checkResult':
                case 'submitResult': {
                    gradingInFlight = false;
                    show(actionStatus, false);
                    checkProgressBtn.disabled = message.type === 'submitResult';
                    submitBtn.disabled = message.type === 'submitResult' || !isIntegrityValid;

                    if (message.type === 'submitResult') {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                            timerInterval = null;
                        }
                        timerLabel.textContent = 'Submitted';
                        timerBox.className = 'timer timer-done';
                        submitBtn.textContent = 'Submitted';
                        $('submission-final-score').textContent = (message.data.performance_score || 0) + '%';
                        renderSubmissionSummary(message.data.summary);
                        show(submissionBanner);
                    }

                    const evalData = message.data.evaluation || {};
                    const execData = message.data.execution || {};

                    // Task feedback and status from the evaluation
                    if (evalData.tasks && Array.isArray(evalData.tasks)) {
                        evalData.tasks.forEach(taskEval => {
                            taskFeedbackMap[taskEval.id] = taskEval.feedback || 'Evaluated.';
                            const feedbackEl = $(\`feedback-task-\${taskEval.id}\`);
                            if (feedbackEl) {
                                feedbackEl.textContent = taskFeedbackMap[taskEval.id];
                                show(feedbackEl);
                            }
                            markTask(taskEval.id, !!taskEval.completed);
                        });
                    }

                    if (Array.isArray(message.data.completed_tasks)) {
                        message.data.completed_tasks.forEach(cid => markTask(cid, true));
                    }
                    updateTaskProgress();

                    if (evalData.overall_feedback) {
                        llmFeedbackText.innerHTML = \`
                            <div><span class="eyebrow">Correctness</span><span class="score">\${esc(evalData.correctness_score ?? 0)}%</span></div>
                            <div>\${esc(evalData.overall_feedback)}</div>
                            <div class="feedback-sub"><span class="eyebrow">Code quality</span>\${esc(evalData.code_quality_feedback || 'No notes.')}</div>
                        \`;
                        show(llmFeedbackSection);
                    }

                    if (execData && (execData.output || execData.errors)) {
                        consoleOutputText.textContent = execData.errors
                            ? \`Compile or runtime error:\\n\${execData.errors}\`
                            : execData.output || 'Ran with no output.';
                        consoleOutputText.classList.toggle('has-error', !!execData.errors);
                        show(consoleOutputSection);
                    } else {
                        show(consoleOutputSection, false);
                    }
                    break;
                }
            }
        });

        function renderSubmissionSummary(summary) {
            if (!summary) return;
            const secs = Math.max(0, summary.time_spent_seconds || 0);
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            const pad = n => String(n).padStart(2, '0');
            $('summary-time').textContent = h > 0 ? \`\${h}:\${pad(m)}:\${pad(s)}\` : \`\${pad(m)}:\${pad(s)}\`;
            $('summary-tasks').textContent = \`\${summary.tasks_completed || 0}/\${summary.tasks_total || 0}\`;
            $('summary-wpm').textContent = String(summary.wpm || 0);

            const hasPlace = !!summary.is_live && summary.place;
            if (hasPlace) {
                $('summary-place').textContent = \`#\${summary.place}/\${summary.participants || summary.place}\`;
            }
            show($('summary-place-cell'), !!hasPlace);
            show($('submission-summary'));
        }

        function handleSessionUpdate(session) {
            if (!session || !session.laboratory) return;
            if (connectionScreen.style.display !== 'none' && !INITIAL_SESSION_ID) return;

            // Mid-grade, keep the "grading" status and the locked buttons
            if (!gradingInFlight) {
                show(actionStatus, false);
            }
            show(connectError, false);
            const attemptClosed = session.status !== 'in_progress';
            setActionsLocked(gradingInFlight || attemptClosed || extensionOutdated);

            // Chat is for teammates, so solo labs have none
            const chatTabBtn = $('tab-chat-btn');
            show(chatTabBtn, session.is_group_lab === true);
            if (chatTabBtn.hidden && chatTabBtn.classList.contains('active')) {
                activateTab(tabButtons[0]);
            }

            connectionScreen.style.display = 'none';
            sessionScreen.style.display = 'flex';

            setStatus('connected', 'Connected');

            labTitle.textContent = session.laboratory.title || 'Lab';
            labDesc.textContent = session.laboratory.description || 'Your instructor did not add a brief for this lab.';
            labDesc.classList.add('is-clamped');
            labDescToggle.textContent = 'Show all';
            requestAnimationFrame(() => {
                const overflows = labDesc.scrollHeight > labDesc.clientHeight + 2;
                show(labDescToggle, overflows);
                if (!overflows) labDesc.classList.remove('is-clamped');
            });

            // Starter files
            starterFilesList.innerHTML = '';
            const starterFiles = session.laboratory.starter_files || [];
            if (starterFiles.length === 0) {
                starterFilesList.innerHTML = '<div class="empty">This lab has no starter files.</div>';
            }
            starterFiles.forEach(f => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'row';
                row.title = 'Open ' + f.name;
                row.onclick = () => {
                    vscode.postMessage({ type: 'openFile', name: f.name });
                };
                row.innerHTML = \`
                    \${ICON_FILE}
                    <span class="row-main file-name">\${esc(f.name)}</span>
                    \${f.is_primary ? '<span class="tag tag-ok">Main</span>' : ''}
                    \${f.is_readonly ? '<span class="tag tag-quiet">Read-only</span>' : ''}
                \`;
                starterFilesList.appendChild(row);
            });

            // Teammate breakdown for team labs
            if (session.is_group_lab && session.teammates && session.teammates.length > 0) {
                teammatesBreakdown.innerHTML = '';
                session.teammates.forEach(tm => {
                    const row = document.createElement('div');
                    row.className = 'row';
                    row.innerHTML = \`
                        <span class="avatar" style="background-color: \${safeColor(tm.avatar_color)};" aria-hidden="true">\${initial(tm.name)}</span>
                        <span class="row-main">\${esc(tm.name)}</span>
                        <span class="num">\${esc(tm.contribution_score || 0)}%</span>
                    \`;
                    teammatesBreakdown.appendChild(row);
                });
                show(teammatesCard);
            } else {
                show(teammatesCard, false);
            }

            // Tasks
            tasksContainer.innerHTML = '';
            const tasksDef = session.laboratory.tasks_definition || [];
            const completedIds = session.completed_tasks || [];

            tasksDef.forEach(task => {
                const isCompleted = completedIds.some(id => String(id) === String(task.id));
                const taskItem = document.createElement('div');
                taskItem.className = 'task-item' + (isCompleted ? ' is-done' : '');
                const cachedFeedback = taskFeedbackMap[task.id];

                taskItem.innerHTML = \`
                    <span class="task-check">\${ICON_TICK}</span>
                    <div class="task-body">
                        <div class="task-title">\${esc(task.task)}</div>
                        <div class="task-feedback" id="feedback-task-\${esc(task.id)}" \${cachedFeedback ? '' : 'hidden'}>\${esc(cachedFeedback)}</div>
                    </div>
                    <span class="task-badge \${isCompleted ? 'badge-complete' : 'badge-pending'}" id="badge-task-\${esc(task.id)}">\${isCompleted ? 'Done' : 'To do'}</span>
                \`;
                tasksContainer.appendChild(taskItem);
            });
            updateTaskProgress();

            // Live lab state
            const isLive = session.availability_mode === 'live';
            const isLiveExpired = session.is_live_expired || (isLive && session.live_status === 'closed');
            const isLiveNotStarted = isLive && session.live_status === 'not_started';

            if (isLiveExpired) {
                setLiveAlert('danger', 'This live lab has ended. The shared clock ran out and submissions are closed.');
                setActionsLocked(true);
            } else if (isLiveNotStarted) {
                setLiveAlert('warn', 'This live lab hasn\\'t started. It unlocks when your instructor starts the clock.');
                setActionsLocked(true);
            } else {
                setLiveAlert(null);
                setActionsLocked(gradingInFlight || attemptClosed || extensionOutdated);
            }

            // Timer
            timerBox.className = 'timer';
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
                isCountDown = false;
                updateTimerDisplay();
                timerLabel.textContent = isLiveExpired ? 'Live lab ended' : 'Submitted';
                timerBox.className = 'timer timer-done';
            } else if (isLiveNotStarted) {
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || (session.live_duration_minutes * 60) || 0));
                isCountDown = false;
                updateTimerDisplay();
                timerLabel.textContent = 'Not started';
                timerBox.className = 'timer timer-done';
            } else {
                if (isLive || session.time_limit_minutes > 0) {
                    timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || 0));
                    isCountDown = true;
                    timerLabel.textContent = timerVal > 0 ? (isLive ? 'Shared clock' : 'Time remaining') : (isLive ? 'Live lab ended' : 'Time is up');
                } else {
                    timerVal = Math.max(0, Math.floor(Number(session.elapsed_seconds) || 0));
                    isCountDown = false;
                    timerLabel.textContent = 'Elapsed';
                }
                startLocalTimer(isLive);
            }
        }

        function onCountdownEnded(isLive) {
            timerLabel.textContent = isLive ? 'Live lab ended' : 'Time is up';
            timerBox.className = 'timer timer-done';
            if (isLive) {
                setLiveAlert('danger', 'This live lab has ended. The shared clock ran out and submissions are closed.');
                checkProgressBtn.disabled = true;
                submitBtn.disabled = true;
            }
        }

        function startLocalTimer(isLive = false) {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }

            updateTimerDisplay();

            if (isCountDown && timerVal <= 0) {
                onCountdownEnded(isLive);
                return;
            }

            timerInterval = setInterval(() => {
                if (isCountDown) {
                    if (timerVal > 0) {
                        timerVal--;
                    } else {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        onCountdownEnded(isLive);
                    }
                } else {
                    timerVal++;
                }
                updateTimerDisplay();
            }, 1000);
            updateTimerDisplay();
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
                timerDisplay.textContent = \`\${paddedHours}:\${paddedMinutes}:\${paddedSeconds}\`;
            } else {
                timerDisplay.textContent = \`\${paddedMinutes}:\${paddedSeconds}\`;
            }

            // Last five minutes of a running countdown turn the clock amber
            timerBox.classList.toggle('timer-low', !!timerInterval && isCountDown && totalSeconds <= 300);
        }

        // Auto-connect and state restoration on load
        if (INITIAL_SESSION_DATA) {
            handleSessionUpdate(INITIAL_SESSION_DATA);
        } else if (INITIAL_SESSION_ID && INITIAL_BACKEND_URL) {
            connectionScreen.style.display = 'none';
            sessionScreen.style.display = 'flex';
            setStatus('reconnecting', 'Connecting…');
        }

        // Inform extension host that webview is mounted and ready for session sync
        vscode.postMessage({ type: 'webviewReady' });
    </script>
</body>
</html>
`;
    }
}
exports.SidebarProvider = SidebarProvider;
//# sourceMappingURL=sidebarProvider.js.map