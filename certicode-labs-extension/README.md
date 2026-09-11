# CertiCode Labs IDE - VS Code Extension

This extension integrates the **CertiCode Labs** certification-based Java learning platform directly into VS Code. It replaces the browser-based IDE, allowing students to write, run, and evaluate their Java lab exercises without leaving their primary development environment.

## Features

-   **Session Timer**: Automatically displays elapsed time or time remaining for the active lab session (synced with the backend database).
-   **Core UI / Sidebar**: Rich sidebar panel that contains the lab title, description, tasks checklist, execution console, and feedback fields.
-   **AI Check Progress**: Sends the current code to the backend for an AI-based check against the exercise's reference solution, rubric, and test cases using OpenAI GPT. Task completion statuses (Done/Pending) and specific task comments are updated instantly in the panel.
-   **One-Click Submit**: Compiles and executes code, performs a final AI evaluation, logs completion telemetry, maps competencies for student certification, and ends the session.
-   **Connection Resilience**: Monitors connectivity and gracefully handles network disconnects by showing a prominent "Reconnecting..." state, protecting students from inactivity logs on the server side.

## Getting Started & Installation

### Prerequisite

Ensure the CertiCode Labs Laravel backend is running.

```bash
# In the Laravel root directory:
php artisan serve
```

Ensure you have seeded the database to create the default student and Java exercise:

```bash
php artisan migrate:fresh --seed
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

## Roadmap & Planned Features

See [TODO.md](../TODO.md) in the project root for full architectural details and progress tracking:
- **Auto-Generated Starter Files & Client-Side Filename Integrity**: Workspace generation with `FileSystemWatcher` validation to prevent submission of missing/renamed files.
- **Code Change Tracking (Diff View)**: Real-time sidebar diff summary (`+added/-deleted`) and team line-level blame attribution.
- **Team Chat**: Private, ephemeral peer-to-peer chat for collaborative lab sessions.
- **Architecture Split**: REST protocol for solo exercises, persistent WebSockets (Laravel Reverb) for team exercises.

