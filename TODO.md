# CertiCode Labs — Project To-Do & Feature Roadmap

This document outlines upcoming feature specifications, implementation tasks, and technical requirements across the CertiCode Labs platform (Web Dashboard, Laravel Backend, and VS Code Extension).

---

## 📋 Feature Roadmap Summary

| Feature | Scope | Status | Protocol |
| :--- | :--- | :--- | :--- |
| **1. Auto-Generated Starter Files & Filename Integrity** | Extension & Instructor Form | 📝 Pending | REST |
| **2. Code Change Tracking (Diff View & Blame Attribution)** | Extension, Backend & Dashboard | 📝 Pending | WebSocket / Reverb |
| **3. Team Chat (Student Collaboration)** | Extension & Web Platform | 📝 Pending | WebSocket / Reverb |
| **4. Architecture Protocol Split** | System Architecture | 📝 Pending | REST + WebSocket |

---

## 1. Auto-Generated Starter Files per Exercise & Client-Side Filename Integrity

### Description
When an exercise is created or edited in the instructor dashboard, the instructor can configure starter files — either single-file or multi-file (e.g., a main class, a helper/utility class, and a test file or interface). When a student launches the exercise from the VS Code extension, those files are automatically generated in their workspace with the specified boilerplate code and comments. The instructions panel in the extension displays the problem description, requirements, and hints, while the code editor opens directly to the primary starter file.

### Filename Integrity & Client-Side Validation
Client-side validation is prioritized over backend error responses. The VS Code extension actively monitors the workspace directory for the required files. If any required file is missing, renamed, or deleted:
- An inline warning is displayed in the sidebar (e.g., `Missing: TaskManager.java — this file is required for submission`).
- The **Submit** button is disabled until the filename issue is resolved.
- This avoids unnecessary server round-trips and provides instant, explicit guidance to the student.

### Technical Requirements
- [ ] **Exercise Schema Updates**:
  - Update exercise data model to support multiple starter files:
    ```json
    {
      "files": [
        {
          "name": "TaskManager.java",
          "content": "// Main class boilerplate...",
          "is_primary": true,
          "is_readonly": false
        },
        {
          "name": "Task.java",
          "content": "// Model definition...",
          "is_primary": false,
          "is_readonly": false
        },
        {
          "name": "TaskInterface.java",
          "content": "// Interface contract...",
          "is_primary": false,
          "is_readonly": true
        }
      ]
    }
    ```
  - Preserve backwards compatibility with existing single-file exercises (auto-map existing `starter_code` to a single primary file).
- [ ] **Instructor Dashboard Exercise Editor**:
  - Multi-tab or file-list UI allowing instructors to add, remove, and rename starter files.
  - Checkbox to designate the `is_primary` file (auto-opens when student starts).
  - Checkbox to designate `is_readonly` files (e.g., unit test suites or immutable interfaces).
- [ ] **VS Code Extension Workspace File Generation**:
  - On session initialization, iterate through `files` array and generate files in the student's active workspace directory.
  - Conflict detection: If a file already exists in the workspace, prompt the student before overwriting.
  - Automatically open the file marked `is_primary: true` in the active editor tab.
- [ ] **Client-Side FileSystemWatcher**:
  - Register a `vscode.workspace.createFileSystemWatcher` to track file additions, deletions, and renames.
  - Verify presence of all non-optional exercise files against the expected manifest.
  - Emit status to the sidebar webview to render warning banners and toggle the submission CTA.
- [ ] **Separation of Concerns**:
  - Problem overview, rubric, and task checklists remain strictly in the sidebar webview instructions.
  - Starter code, docstrings, and inline task markers remain strictly in workspace files.

---

## 2. Code Change Tracking (Diff View & Attribution)

### Description
The VS Code sidebar displays a real-time summary of code changes made during the current lab session, similar to GitHub's diff indicators. For solo exercises, it shows lines added/removed by the student since the starter file was generated. For team exercises, changes are attributed per teammate at the line level — like `git blame` — so the sidebar shows not just how much was written, but who wrote what. Changes stream over the session's WebSocket connection and feed directly into the instructor's live session analytics.

### Technical Requirements
- [ ] **Diff Calculation Engine (Extension)**:
  - Cache initial snapshot of starter files upon generation.
  - Compute line diffs (`+added`, `-deleted`, `~modified`) relative to the original starter snapshot on document change/save.
- [ ] **Sidebar Diff Stat Widget**:
  - Display compact diff counter in the sidebar header/telemetry section (e.g., `+42 / -11`).
  - Provide an expandable accordion or popover displaying line-by-line diff changes.
- [ ] **Team Attribution & Line-Level Blame**:
  - For team sessions, tag each change block with the student's authenticated user ID, initials, and assigned avatar color.
  - Render color-coded gutter bars or inline attribution indicators showing which teammate authored each line.
- [ ] **WebSocket Broadcast & Debounce**:
  - Debounce diff calculation and transmission (e.g., 2-second idle delay after typing stops or on file save).
  - Transmit diff payloads over WebSocket channel `team-session.{id}` to sync teammate views without flooding network traffic.
- [ ] **Backend Persistence & Aggregation**:
  - Store periodic aggregated metrics per student per session (total lines added, modified, deleted, edit frequency).
- [ ] **Instructor Dashboard "Code Contribution" Tab**:
  - Visual breakdown per student in team sessions:
    - Bar chart / percentage split of contributions.
    - Timeline of edits throughout the session duration.
    - Individual student breakdown of lines added vs. lines deleted.

---

## 3. Team Chat (Student Collaboration)

### Description
Real-time peer-to-peer chat embedded within both the VS Code extension sidebar and the web laboratory view for team-configured exercises.

### Technical Requirements
- [ ] **Private & Peer-to-Peer**:
  - Chat messages are shared exclusively among active teammates in the session.
  - Private channel: Excluded from instructor monitoring, proctoring feeds, and grading evaluations.
- [ ] **Ephemeral Lifecycle**:
  - Chat history is stored in memory / temporary Redis cache for the duration of the active lab session.
  - Automatically flushed when the team lab session is submitted or ended.
- [ ] **VS Code Sidebar Chat Component**:
  - Tabbed interface in extension sidebar: `Instructions` | `Tasks` | `Diff` | `Team Chat`.
  - Unread message badge counter.
  - Rich chat feed with sender name, avatar/initials badge, timestamp, and code-block snippet support.
- [ ] **Web Laboratory Sync**:
  - Teammates accessing the session via web browser receive and send messages through the identical WebSocket channel.

---

## 4. Architecture & Protocol Split

To optimize server resource consumption, latency, and reliability:

### Solo Exercises (REST Architecture)
- **Primary Transport**: Standard HTTP/REST API endpoints (`/api/v1/vscode/...`).
- **Telemetry & Proctoring**: Periodic debounced REST heartbeats (inactivity detection, focus changes, anomaly logging).
- **Submissions & Progress Checks**: On-demand HTTP POST calls to AI grading and compilation services.

### Team Exercises (WebSocket Architecture)
- **Primary Transport**: Persistent WebSocket connection (Laravel Reverb / Pusher protocol).
- **Channels**:
  - `presence-team.{sessionId}`: Tracks teammate join/leave states, cursor/active file indicators.
  - `team-diff.{sessionId}`: Broadcasts line-level diff attribution streams.
  - `team-chat.{sessionId}`: Delivers private, ephemeral teammate communications.
- **Failover / Reconnection**: Graceful fallback and auto-reconnect logic if WebSocket connection drops during a live lab.
