# CertiCode Labs IDE - VS Code Extension

This extension integrates the **CertiCode Labs** certification-based Java learning platform directly into VS Code. It replaces the browser-based IDE, allowing students to write, run, and evaluate their Java lab exercises without leaving their primary development environment.

## Features

-   **Session Timer**: Automatically displays elapsed time or time remaining for the active lab session (synced with the backend database).
-   **Core UI / Sidebar**: Rich sidebar panel that contains the lab title, description, tasks checklist, execution console, and feedback fields.
-   **AI Check Progress**: Sends the current code to the backend for an AI-based check against the exercise's tasks and rubric using Claude. Task completion statuses (Done/Pending) and specific task comments are updated instantly in the panel.
-   **One-Click Submit**: Compiles and executes code, performs a final AI evaluation, logs completion telemetry, maps competencies for student certification, and ends the session.
-   **Connection Resilience**: Monitors connectivity and gracefully handles network disconnects by showing a prominent "Reconnecting..." state, protecting students from inactivity logs on the server side.

## Getting Started & Installation

### Prerequisite

Ensure the CertiCode Labs Laravel backend is running.

```bash
# In the Laravel root directory:
php artisan serve
```

Seed a local database to create the default student and Java exercise.

**Warning:** First confirm that `DB_URL` is empty and `DB_CONNECTION` points at a local database. `DB_URL` overrides `DB_CONNECTION`, so a production `DB_URL` in `.env` sends this to production. Seeding creates accounts with a known password.

```bash
php artisan migrate --seed
```

This creates a default student:
-   **Email**: `student@example.com`
-   **Password**: `password`
-   **Lab Session ID**: `1` (associated with the Java OOP custom exception lab)

### Running the Extension in VS Code

1.  Open the folder `certicode-labs-extension` in VS Code.
2.  Install dependencies:
    ```bash
    npm install
    ```
3.  Compile the TypeScript code:
    ```bash
    npm run compile
    ```
4.  Press `F5` to open a new **Extension Development Host** window.
5.  In the Activity Bar, click on the **CertiCode Labs** tab.
6.  Connect to your active lab session using:
    -   **Backend Endpoint**: `http://localhost:8000` (or `http://localhost` if running on port 80)
    -   **Lab Session ID**: `1` (or your active session ID)
    -   **API Token**: Leave blank for public prototyping routes, or use your API token.
7.  Open or create a Java file, start writing, and use the panel controls to check your progress!

## Shared typing on group labs

When the server has it set up, teammates type in the same files at the same time (`src/teamSync/`):

- Each code file is a [Yjs](https://yjs.dev) document. `TextBinding` keeps one editor document and its shared file the same; `TeamDoc` sends and stores edits; `WorkspaceSync` ties them to the lab folder.
- Edits travel over a Supabase Realtime Broadcast channel, sealed with AES-256-GCM (`seal.ts`) using the team attempt's key, and are stored on the server for teammates who join later.
- A teammate's edit arriving in the editor is reported as such, so it is never counted as the student's typing or paste. Line counts credit each line to the student whose editor typed it.

The extension is bundled with esbuild (`npm run bundle`, `dist/extension.js`) because it ships these libraries. `npm test` runs the unit tests; `npm run sync-check` runs shared typing in a real VS Code window (see `scripts/sync-check/README.md`).

## Roadmap & Planned Features

See [TODO.md](../TODO.md) in the project root for full architectural details and progress tracking:
1. **Diff Tracking**: Live line-level diff engine with team blame attribution, streaming via WebSocket.
2. **Team Chat**: Private, ephemeral peer-to-peer team chat with syntax-highlighted code snippets.
3. **Auto-Generated Starter Files**: Multi-file workspace provisioning with continuous `FileSystemWatcher` integrity validation.
4. **Paste Anomaly Detection**: Keystroke/WPM baseline with starter-file, own-code and team-chat suppression rules and a clipboard check. A large block of code that was neither typed nor pasted, such as from an AI tool, is reported as a large insert.
5. **In-Lab Sidebar**: All live UI (Live Leaderboard, Task Checklist, Timer, and OS-level window focus tracking) centralized in the VS Code sidebar.
6. **Instructor Live Monitoring Panel**: Real-time web dashboard displaying live WPM, task counters, anomaly image timeline, and team contribution drill-downs.
7. **Session Closure vs. Course Completion & Certification**: Per-lab session auto-submissions and cleanup vs. course-wide competency accumulation with configurable passing thresholds and automated e-diploma issuance.
8. **Camera Presence Check**: Hard-blocking pre-lab permission gate with continuous, non-interruptive proctoring and snapshot evidence logging.

