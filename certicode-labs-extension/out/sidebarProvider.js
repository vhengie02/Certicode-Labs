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
class SidebarProvider {
    constructor(_extensionUri) {
        this._extensionUri = _extensionUri;
        this._backendUrl = 'http://localhost';
        this._isReconnecting = false;
        this._lastSessionData = null;
    }
    resolveWebviewView(webviewView, context, _token) {
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
            }
        });
    }
    /**
     * Shows a input prompt to connect from VS Code commands palette
     */
    async connectSessionPrompt() {
        const url = await vscode.window.showInputBox({
            prompt: 'Enter CertiCode Backend URL',
            value: this._backendUrl
        });
        if (!url) {
            return;
        }
        const session = await vscode.window.showInputBox({
            prompt: 'Enter Lab Session ID',
            placeHolder: 'e.g. 1'
        });
        if (!session) {
            return;
        }
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
    connectToSession(backendUrl, sessionId, apiToken) {
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
    startMonitoring() {
        if (this._pingInterval) {
            clearInterval(this._pingInterval);
        }
        // Run sync immediately
        this.syncSessionState();
        // Run sync every 15 seconds to detect dropped connections / keepalive
        this._pingInterval = setInterval(() => {
            this.syncSessionState();
        }, 15000);
    }
    async syncSessionState() {
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
                this._view?.webview.postMessage({
                    type: 'update',
                    data: data,
                    isReconnecting: false
                });
            }
            else {
                throw new Error(`Server returned status code: ${result.status}`);
            }
        }
        catch (err) {
            this._isReconnecting = true;
            this._view?.webview.postMessage({
                type: 'reconnecting',
                isReconnecting: true
            });
        }
    }
    async handleCheckProgress() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }
        const activeEditor = vscode.window.activeTextEditor;
        if (!activeEditor) {
            vscode.window.showErrorMessage('Please open your Java code file to check progress.');
            return;
        }
        const code = activeEditor.document.getText();
        const docName = activeEditor.document.fileName;
        if (!docName.endsWith('.java')) {
            vscode.window.showWarningMessage('Warning: The active editor is not a Java source file (.java).');
        }
        this._view?.webview.postMessage({ type: 'status', message: 'Analyzing code with AI evaluator...' });
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/check-progress`, {
                code: code,
                language: 'java'
            });
            if (result.status === 200) {
                const responseData = JSON.parse(result.body);
                vscode.window.showInformationMessage('Check Progress: AI evaluation completed.');
                this._view?.webview.postMessage({
                    type: 'checkResult',
                    data: responseData
                });
                this.syncSessionState();
            }
            else {
                const responseData = JSON.parse(result.body);
                vscode.window.showErrorMessage(`Check Progress Failed: ${responseData.error || 'Server error'}`);
                this._view?.webview.postMessage({
                    type: 'error',
                    message: responseData.error || 'Check progress failed.'
                });
            }
        }
        catch (err) {
            vscode.window.showErrorMessage(`Check Progress Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }
    async handleSubmit() {
        if (!this._sessionId) {
            vscode.window.showErrorMessage('No active CertiCode lab session.');
            return;
        }
        const activeEditor = vscode.window.activeTextEditor;
        if (!activeEditor) {
            vscode.window.showErrorMessage('Please open your Java code file to submit.');
            return;
        }
        const code = activeEditor.document.getText();
        const confirm = await vscode.window.showWarningMessage('Submit Lab Solution: Are you sure you want to finalize your submission? This compiles, runs test cases, evaluates competencies, and completes your session.', { modal: true }, 'Yes, Submit');
        if (confirm !== 'Yes, Submit') {
            return;
        }
        this._view?.webview.postMessage({ type: 'status', message: 'Running final submission & compilation...' });
        try {
            const prefix = this._apiToken ? '/api' : '/api/v1';
            const result = await this.makeRequest('POST', `${prefix}/sessions/${this._sessionId}/submit`, {
                code: code,
                language: 'java'
            });
            if (result.status === 200) {
                const responseData = JSON.parse(result.body);
                vscode.window.showInformationMessage(`Lab Session Completed! Score: ${responseData.performance_score}%`);
                this._view?.webview.postMessage({
                    type: 'submitResult',
                    data: responseData
                });
                if (this._pingInterval) {
                    clearInterval(this._pingInterval);
                }
            }
            else {
                const responseData = JSON.parse(result.body);
                vscode.window.showErrorMessage(`Submission Failed: ${responseData.error || 'Server error'}`);
                this._view?.webview.postMessage({
                    type: 'error',
                    message: responseData.error || 'Submission failed.'
                });
            }
        }
        catch (err) {
            vscode.window.showErrorMessage(`Submission Connection Error: ${err.message}`);
            this._view?.webview.postMessage({ type: 'error', message: `Connection Error: ${err.message}` });
        }
    }
    async handleExit() {
        const confirm = await vscode.window.showWarningMessage('Are you sure you want to exit this workspace? Any unsaved editor content or unsubmitted progress will remain pending.', { modal: true }, 'Confirm Exit');
        if (confirm !== 'Confirm Exit') {
            return;
        }
        if (this._pingInterval) {
            clearInterval(this._pingInterval);
        }
        this._sessionId = undefined;
        this._lastSessionData = null;
        this._view?.webview.postMessage({ type: 'disconnected' });
        vscode.window.showInformationMessage('Exited CertiCode Labs session.');
    }
    /**
     * Native HTTP/HTTPS client requests.
     */
    makeRequest(method, path, bodyData) {
        return new Promise((resolve, reject) => {
            try {
                const targetUrl = new url_1.URL(`${this._backendUrl}${path}`);
                const headers = {
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
                const handleResponse = (res) => {
                    let responseData = '';
                    res.on('data', (chunk) => {
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
    _getHtmlForWebview() {
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
            padding: 10px;
            margin: 0;
            box-sizing: border-box;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .header {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 5px;
            border-bottom: 1px solid var(--vscode-panel-border);
            padding-bottom: 5px;
            color: var(--vscode-sideBarTitle-foreground);
        }
        .sub-header {
            color: var(--vscode-descriptionForeground);
            font-size: 0.95em;
            line-height: 1.35;
        }
        .connection-status {
            padding: 6px 10px;
            font-size: 0.9em;
            border-radius: 3px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 5px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .status-connected {
            background-color: rgba(74, 186, 120, 0.15);
            color: #4aba78;
            border: 1px solid rgba(74, 186, 120, 0.3);
        }
        .status-connected .status-dot {
            background-color: #4aba78;
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
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        label {
            font-weight: bold;
            font-size: 0.9em;
        }
        input {
            background-color: var(--vscode-input-background);
            color: var(--vscode-input-foreground);
            border: 1px solid var(--vscode-input-border, transparent);
            padding: 6px;
            border-radius: 2px;
            font-family: inherit;
        }
        input:focus {
            outline: 1px solid var(--vscode-focusBorder);
        }
        button {
            background-color: var(--vscode-button-background);
            color: var(--vscode-button-foreground);
            border: none;
            padding: 8px 12px;
            border-radius: 2px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        button:hover {
            background-color: var(--vscode-button-hoverBackground);
        }
        .btn-secondary {
            background-color: var(--vscode-button-secondaryBackground, #5f5f5f);
            color: var(--vscode-button-secondaryForeground, #ffffff);
        }
        .btn-secondary:hover {
            background-color: var(--vscode-button-secondaryHoverBackground, #777777);
        }
        .btn-danger {
            background-color: #d9534f;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c9302c;
        }
        .timer-box {
            font-size: 1.3em;
            font-weight: bold;
            text-align: center;
            padding: 10px;
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
            padding: 10px;
        }
        .task-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .task-item {
            display: flex;
            flex-direction: column;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid var(--vscode-panel-border);
            background-color: var(--vscode-sideBar-background);
        }
        .task-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }
        .task-title {
            font-weight: 500;
            line-height: 1.3;
        }
        .task-badge {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            white-space: nowrap;
        }
        .badge-pending {
            background-color: rgba(245, 166, 35, 0.15);
            color: #f5a623;
            border: 1px solid rgba(245, 166, 35, 0.3);
        }
        .badge-complete {
            background-color: rgba(74, 186, 120, 0.15);
            color: #4aba78;
            border: 1px solid rgba(74, 186, 120, 0.3);
        }
        .task-feedback {
            font-size: 0.88em;
            color: var(--vscode-descriptionForeground);
            margin-top: 6px;
            border-top: 1px dashed var(--vscode-panel-border);
            padding-top: 6px;
        }
        .section-title {
            font-weight: bold;
            margin-top: 5px;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
            color: var(--vscode-descriptionForeground);
        }
        .console-output {
            background-color: #1e1e1e;
            color: #e0e0e0;
            font-family: var(--vscode-editor-font-family, monospace);
            font-size: 0.88em;
            padding: 8px;
            border-radius: 3px;
            white-space: pre-wrap;
            max-height: 140px;
            overflow-y: auto;
            border: 1px solid var(--vscode-panel-border);
        }
        .feedback-box {
            font-style: italic;
            color: var(--vscode-descriptionForeground);
            padding: 8px;
            border-radius: 3px;
            background-color: rgba(255, 255, 255, 0.03);
            border-left: 3px solid var(--vscode-focusBorder);
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 5px;
        }
        .loader-status {
            font-style: italic;
            color: var(--vscode-descriptionForeground);
            text-align: center;
            margin-top: 10px;
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
        <div id="status-banner" class="connection-status status-connected">
            <div class="status-dot"></div>
            <span id="status-text">Connected</span>
        </div>

        <div class="timer-box">
            <span id="timer-display">00:00</span>
            <span id="timer-label" class="timer-label">Time Remaining</span>
        </div>

        <div class="card">
            <div class="header" id="lab-title">Lab Title</div>
            <div class="sub-header" id="lab-desc" style="max-height: 100px; overflow-y: auto;">Lab description goes here...</div>
        </div>

        <div class="section-title">Requirements Checklist</div>
        <div class="task-list" id="tasks-container">
            <!-- Tasks populated dynamically -->
        </div>

        <div id="llm-feedback-section" style="display: none;">
            <div class="section-title">AI Rubric Feedback</div>
            <div class="feedback-box" id="llm-feedback-text">
                No feedback received yet. Run "Check Progress" to get feedback.
            </div>
        </div>

        <div id="console-output-section" style="display: none;">
            <div class="section-title">Execution Console Output</div>
            <div class="console-output" id="console-output-text"></div>
        </div>

        <div id="action-status" class="loader-status" style="display: none;"></div>

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
        
        // Session fields
        const statusBanner = document.getElementById('status-banner');
        const statusText = document.getElementById('status-text');
        const timerDisplay = document.getElementById('timer-display');
        const timerLabel = document.getElementById('timer-label');
        const labTitle = document.getElementById('lab-title');
        const labDesc = document.getElementById('lab-desc');
        const tasksContainer = document.getElementById('tasks-container');
        const actionStatus = document.getElementById('action-status');
        
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
            vscode.postMessage({ type: 'submit' });
        });
        
        exitBtn.addEventListener('click', () => {
            vscode.postMessage({ type: 'exit' });
        });
        
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
                    submitBtn.disabled = false;
                    alert(message.message);
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
                    submitBtn.disabled = false;
                    
                    connectionScreen.style.display = 'none';
                    sessionScreen.style.display = 'flex';
                    
                    statusBanner.className = 'connection-status status-connected';
                    statusText.innerText = 'Connected';
                    
                    const session = message.data;
                    labTitle.innerText = session.laboratory.title;
                    labDesc.innerText = session.laboratory.description;
                    
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
                    
                    // Setup timer
                    if (session.status === 'completed') {
                        if (timerInterval) {
                            clearInterval(timerInterval);
                            timerInterval = null;
                        }
                        if (session.time_limit_minutes > 0) {
                            timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || 0));
                        } else {
                            timerVal = Math.max(0, Math.floor(Number(session.elapsed_seconds) || 0));
                        }
                        updateTimerDisplay();
                        timerLabel.innerText = 'Session Completed';
                    } else {
                        if (session.time_limit_minutes > 0) {
                            timerVal = Math.max(0, Math.floor(Number(session.time_remaining_seconds) || 0));
                            isCountDown = true;
                            timerLabel.innerText = timerVal > 0 ? 'Time Remaining' : 'Time Expired';
                        } else {
                            timerVal = Math.max(0, Math.floor(Number(session.elapsed_seconds) || 0));
                            isCountDown = false;
                            timerLabel.innerText = 'Session Elapsed';
                        }
                        startLocalTimer();
                    }
                    break;
                    
                case 'checkResult':
                case 'submitResult':
                    actionStatus.style.display = 'none';
                    checkProgressBtn.disabled = false;
                    submitBtn.disabled = false;
                    
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
        
        function startLocalTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            updateTimerDisplay();
            
            if (isCountDown && timerVal <= 0) {
                timerLabel.innerText = 'Time Expired';
                return;
            }
            
            timerInterval = setInterval(() => {
                if (isCountDown) {
                    if (timerVal > 0) {
                        timerVal--;
                    } else {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        timerLabel.innerText = 'Time Expired';
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
exports.SidebarProvider = SidebarProvider;
//# sourceMappingURL=sidebarProvider.js.map