# Team Shared Typing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Teammates on a group lab type in the same files at the same time, each in their own VS Code, with every student still credited and flagged only for what they typed themselves.

**Architecture:** Each team attempt has one Yjs document (a map of file name to shared text). The extension binds it to the open editors, sends merged edits over a Supabase Realtime Broadcast channel, encrypted with a per-attempt key, and stores them through Laravel so a late joiner or a lone teammate can load the document. Laravel hands the channel name and key only to the team's members and never decodes an edit.

**Tech Stack:** Laravel (PHPUnit on in-memory SQLite, CI also on Postgres 17), VS Code extension in TypeScript (`node --test` with `src/test/vscodeStub.ts`), new extension libraries `yjs`, `@supabase/realtime-js`, `ws`, bundled with `esbuild`.

**Spec:** `docs/superpowers/specs/2026-11-12-group-lab-coediting-design.md`, section 3 "Shared files" and "Evidence from the throwaway test". Part 1 (leader, team submit, `team_started_at`) is already built on `feat/team-leader-submit`; this branch, `feat/team-shared-typing`, is cut from it. Teacher diff view and teammate cursors are part 3.

## Global Constraints

- Commit or push only when the owner asks. Steps say "Checkpoint". No Claude attribution in any commit or PR.
- The repo is public. Supabase URL and publishable key come from environment variables with no default. No key, token or channel secret in code, tests, docs or logs.
- Nothing in this plan touches Vercel settings or the production database. Task 8 lists what the owner must set and run.
- Run PHP tests only through `php artisan test` (phpunit.xml forces in-memory SQLite). Before any checkpoint run `php vendor/bin/pint --test`: CI blocks on it. CI also runs the suite on Postgres; a test that holds a database cache lock and lets the app contend for it must give locks their own connection (pattern in `tests/Feature/TeamSubmitTest.php`).
- Run extension `npm` commands in PowerShell.
- The shared text uses LF line endings. A shared file is LF on disk and in the editor before it is bound.
- `workspace.applyEdit` returning false means the user typed in the same instant: recompute and retry, never assume it applied.
- An incoming change is recognised as a teammate's by the resulting text, never by its shape (VS Code splits edits).
- A user's own change that arrives while a remote edit is still on its way is shifted past that edit before it is written to the shared text.
- Encryption: AES-256-GCM, 12-byte random nonce per message, wire form `base64(nonce || ciphertext || tag)`. A message that fails to open is dropped silently.
- Send interval: setting `services.team_sync.min_send_interval_ms`, default `400`.
- Only code files (`LabSession::isCodeFile`) inside the lab workspace are shared. Read-only starter files are not.
- Without the two Supabase settings, or without a WebSocket, a group lab works as in part 1 (separate copies, leader submits) and the sidebar says "Shared typing is off".
- Solo labs and students with no team are untouched.
- Extension version after this plan: `1.10.0`.

## Review Focus

1. Two teammates open the lab in the same second on an empty document: exactly one seeds it from the starter files; the other loads that seed. Expect no duplicated starter text. (Tasks 2, 5)
2. A teammate types while a remote edit is in flight and `applyEdit` is rejected: expect identical text everywhere and the typed characters counted once, for the right student. (Task 4)
3. A message that is garbage, truncated, or sealed with another team's key arrives on the channel: expect it dropped, the document unchanged, no error shown to the student. (Tasks 3, 5)
4. A student is moved to another team mid-lab, or the leader submits: expect the old channel left, no further edits applied or sent, and the stored updates of a submitted attempt deleted. (Tasks 2, 6)
5. A teammate is offline for a minute and keeps typing: expect their edits merged on reconnect, nothing lost on either side. (Task 5)

---

### Task 1: Team document secrets (server)

**Files:**
- Create: `database/migrations/2026_11_12_000003_create_team_docs_tables.php`, `app/Models/TeamDoc.php`, `app/Models/TeamDocUpdate.php`, `app/Support/TeamSync.php`
- Modify: `config/services.php`, `app/Http/Controllers/Api/LabSessionController.php` (`getSession` payload; new `teamDoc`), `routes/api.php`, `routes/web.php`
- Test: `tests/Feature/TeamDocTest.php` (setup as in `tests/Feature/TeamSubmitTest.php`)

**Interfaces:**
- Produces:
  - `team_docs`: `id`, `group_id` FK cascade, `lab_id` FK cascade, `team_started_at` timestamp, `topic` string(64) unique, `key` text (cast `encrypted`), timestamps; unique (`group_id`, `lab_id`, `team_started_at`).
  - `team_doc_updates`: `id`, `team_doc_id` FK cascade, `user_id` nullable FK null on delete, `payload` longText (base64 of a Yjs update), `created_at`.
  - Config `services.team_sync`: `supabase_url` (`env('TEAM_SYNC_SUPABASE_URL')`), `publishable_key` (`env('TEAM_SYNC_SUPABASE_KEY')`), `min_send_interval_ms` (`(int) env('TEAM_SYNC_MIN_SEND_INTERVAL_MS', 400)`).
  - `App\Support\TeamSync::enabled(): bool` — both URL and key non-empty.
  - `TeamDoc::forSession(LabSession $session): ?TeamDoc` — null unless the session is `in_progress`, has a `group_id` and a `team_started_at`; otherwise the row for that team attempt, created once inside `LabTeams::locked()` with `topic` = `'td-'.bin2hex(random_bytes(24))` and `key` = `base64_encode(random_bytes(32))`.
  - `GET {session prefix}/sessions/{sessionId}/team-doc` → `LabSessionController::teamDoc`. 200 `{enabled: true, topic, key, supabase_url, publishable_key, min_send_interval_ms, last_update_id}`; 200 `{enabled: false}` when `TeamSync::enabled()` is false or `forSession` is null. Refused with the existing `not_your_attempt` 403 for a signed-in student who is not the session's own.
  - `getSession` JSON gains `team_sync: {enabled: bool}` (true when `TeamSync::enabled()` and the session has a team attempt).

- [ ] **Step 1: Write the failing tests**

```php
public function test_teammates_get_the_same_channel_and_key_for_one_team_attempt()   // alice and bob each GET team-doc: equal topic and key; topic matches /^td-[0-9a-f]{48}$/; base64_decode(key) is 32 bytes
public function test_another_team_gets_a_different_channel_and_key()
public function test_a_new_team_attempt_gets_a_new_channel_and_key()                  // same team, later team_started_at
public function test_the_key_is_not_stored_in_plain_text()                             // DB::table('team_docs')->value('key') !== the key returned
public function test_a_teammate_cannot_read_the_secrets_through_another_teammates_session() // 403 not_your_attempt
public function test_a_student_outside_the_team_gets_nothing()                         // 403 from lab.session middleware
public function test_a_solo_attempt_has_no_team_document()                             // {enabled: false}, TeamDoc::count() === 0
public function test_shared_typing_is_off_without_the_supabase_settings()              // config null: {enabled: false}; getSession team_sync.enabled false
public function test_a_finished_attempt_gets_no_secrets()                              // status completed: {enabled: false}
public function test_the_session_payload_says_whether_shared_typing_is_on()
```

Tests set `config(['services.team_sync.supabase_url' => 'https://example.supabase.co', 'services.team_sync.publishable_key' => 'sb_publishable_test'])`.

- [ ] **Step 2: Run** `php artisan test --filter=TeamDocTest` — expect FAIL (no table).
- [ ] **Step 3: Implement** the migration, models, `TeamSync`, config, route and action.
- [ ] **Step 4: Run** the filter — expect PASS. Run `php vendor/bin/pint --test` — expect `passed`.
- [ ] **Step 5: Checkpoint.**

### Task 2: Stored updates (server)

**Files:**
- Modify: `app/Http/Controllers/Api/LabSessionController.php` (new `teamDocUpdates`, `storeTeamDocUpdate`; `gradeSubmission`), `app/Models/TeamDoc.php`, `routes/api.php`, `routes/web.php`
- Create: `app/Console/Commands/PruneTeamDocs.php`; register its call in `app/Http/Controllers/CronController.php`
- Test: `tests/Feature/TeamDocTest.php` (extend)

**Interfaces:**
- Consumes: `TeamDoc::forSession`.
- Produces:
  - `GET .../sessions/{sessionId}/team-doc/updates?after={id}` → `{updates: [{id, payload}], last_update_id}`, oldest first, `after` default 0.
  - `POST .../sessions/{sessionId}/team-doc/updates` body `{payload: string, seed?: bool, replaces_through?: int}` → 201 `{id}`.
    - `payload`: required base64 string, at most 700,000 characters; else 422.
    - `seed: true`: stored only when the document has no update yet, checked inside `LabTeams::locked()`; otherwise 409 `{error: 'already_seeded'}`.
    - `replaces_through: N`: in one transaction, store the payload and delete this document's updates with `id <= N`. For compaction.
    - A document holding more than 400 updates or 8,000,000 payload characters answers 409 `{error: 'compaction_needed', last_update_id}` to a plain append.
  - Both routes refuse with `{enabled: false}` → 409 `{error: 'team_sync_off'}` when the session has no team document, and `not_your_attempt` 403 as in Task 1.
  - `gradeSubmission`: after the team is closed, delete the attempt's `TeamDoc`.
  - Command `certicode:prune-team-docs`: deletes `team_docs` whose team attempt has no `in_progress` session. Called from the cron tick.

- [ ] **Step 1: Write the failing tests**

```php
public function test_updates_come_back_in_order_to_every_teammate()                // alice posts A, bob posts B: carol's GET returns [A, B]; ?after=A.id returns [B]
public function test_only_the_first_seed_is_kept()                                 // alice seeds 201; bob seeds 409 already_seeded; one row
public function test_a_seed_is_refused_once_anyone_has_written()                   // append then seed: 409
public function test_compaction_replaces_what_it_covers_and_keeps_later_updates()  // rows 1,2,3; post replaces_through=2: rows are [3, new]
public function test_an_oversized_update_is_refused()                              // 700,001 chars: 422
public function test_a_document_that_grew_too_long_asks_for_compaction()           // 401st append: 409 compaction_needed
public function test_another_teams_updates_are_never_returned()
public function test_a_teammate_cannot_post_through_another_teammates_session()    // 403
public function test_the_leaders_submit_deletes_the_teams_stored_document()        // after submit TeamDoc::count() === 0 and TeamDocUpdate::count() === 0
public function test_stored_documents_of_ended_team_attempts_are_pruned()          // attempt with no in_progress session: gone after the command; a live one stays
```

- [ ] **Step 2: Run** `php artisan test --filter=TeamDocTest` — expect the new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="TeamDocTest|TeamSubmitTest"` — expect PASS. Run `php vendor/bin/pint --test` — expect `passed`.
- [ ] **Step 5: Checkpoint.**

### Task 3: Extension bundling and message sealing

**Files:**
- Modify: `certicode-labs-extension/package.json` (dependencies `yjs`, `@supabase/realtime-js`, `ws`; devDependencies `esbuild`, `@types/ws`; scripts; `main`), `certicode-labs-extension/.vscodeignore`, `certicode-labs-extension/.gitignore`
- Create: `certicode-labs-extension/src/teamSync/seal.ts`
- Test: `certicode-labs-extension/src/test/seal.test.ts`

**Interfaces:**
- Produces:
  - Scripts: `"bundle": "esbuild src/extension.ts --bundle --platform=node --target=node16 --external:vscode --outfile=dist/extension.js"`, `"vscode:prepublish": "npm run compile && npm run bundle"`. `main` becomes `./dist/extension.js`. `dist/` is git-ignored; `.vscodeignore` gains `out/**` so the package ships the bundle only. `npm test` still compiles with `tsc` and runs `out/test/*.test.js`.
  - `seal(key: Buffer, plain: Uint8Array): string` and `open(key: Buffer, sealed: string): Uint8Array | null` (null on any failure: bad base64, short input, wrong key, altered bytes).

- [ ] **Step 1: Write the failing tests**

```ts
test('a sealed message opens to the same bytes');
test('two seals of the same bytes differ');                    // random nonce
test('a message sealed with another key does not open');       // open(...) === null
test('an altered message does not open');                      // flip one byte of the base64-decoded buffer
test('garbage does not open and does not throw');              // '', 'not base64!', 'AAAA'
```

- [ ] **Step 2: Run** `npm test` — expect FAIL (module missing).
- [ ] **Step 3: Install the libraries, add the scripts, implement `seal.ts`** with Node's `crypto` (`aes-256-gcm`).
- [ ] **Step 4: Run** `npm test` — expect PASS. Run `npm run bundle` — expect exit code 0 and `dist/extension.js` present.
- [ ] **Step 5: Checkpoint.**

### Task 4: Text binding

**Files:**
- Create: `certicode-labs-extension/src/teamSync/textBinding.ts`
- Test: `certicode-labs-extension/src/test/textBinding.test.ts`

**Interfaces:**
- Produces:

```ts
export interface EditorPort {
    getText(): string;
    /** Replace [offset, offset+length) with text. False when the editor changed first. */
    replace(offset: number, length: number, text: string): Promise<boolean>;
}
export interface LocalChange { offset: number; length: number; text: string; }
export class TextBinding {
    constructor(ytext: Y.Text, editor: EditorPort, remoteOrigin: unknown);
    /** Call for every change event on the document, after the editor text changed.
     *  Returns true when the event was a teammate's edit arriving (not the user's). */
    handleEditorChange(changes: LocalChange[]): boolean;
    /** Resolves when the editor shows the shared text and nothing is in flight. */
    settled(): Promise<void>;
    readonly localChars: number;   // characters the user inserted, for tests
    dispose(): void;
}
```

  - Local changes are written to `ytext` in a transaction with origin `'local'`. Remote transactions are those whose origin is `remoteOrigin`.
  - The algorithm is the one proven in the throwaway test (spec, "Evidence"): on a remote change, replace the smallest differing span, retry on `false`; recognise the echo by `editor.getText() === target`; shift local offsets through remote operations not yet shown.

- [ ] **Step 1: Write the failing tests** with a `FakeEditor` that applies `replace` after a random delay, rejects it when a local change happened since it was requested, splits a replacement into two change events half the time, and reports each change to the binding.

```ts
test('a remote insert appears in the editor and is not counted as local');
test('a local insert reaches the shared text and is counted');
test('a rejected remote edit is retried until the editor matches');
test('a local change during an in-flight remote edit lands at the right place');
test('an echo split into several change events is still recognised');
test('random typing on both sides converges and counts only local characters', async () => {
    // 3 seeds x 400 rounds: local insert/delete on the fake editor, remote insert/delete on a second Y.Doc
    // assert: editor text === ytext === remote ytext; binding.localChars === characters the test typed locally
});
test('settled resolves only when nothing is in flight');
test('dispose stops applying remote edits');
```

- [ ] **Step 2: Run** `npm test` — expect FAIL.
- [ ] **Step 3: Implement `textBinding.ts`.**
- [ ] **Step 4: Run** `npm test` — expect PASS.
- [ ] **Step 5: Checkpoint.**

### Task 5: Team document

**Files:**
- Create: `certicode-labs-extension/src/teamSync/teamDoc.ts`, `certicode-labs-extension/src/teamSync/transport.ts`
- Test: `certicode-labs-extension/src/test/teamDoc.test.ts`

**Interfaces:**
- Consumes: `seal`, `open`.
- Produces:

```ts
export interface Transport {                       // carries sealed strings between teammates
    send(message: string): void;
    onMessage(listener: (message: string) => void): void;
    onOpen(listener: () => void): void;            // also fires after every reconnect
    close(): void;
}
export interface DocStore {                        // Laravel, Task 1 and 2
    load(after: number): Promise<{ updates: { id: number; payload: string }[]; lastUpdateId: number }>;
    append(payload: string, options?: { seed?: boolean; replacesThrough?: number }): Promise<{ id: number } | { error: 'already_seeded' | 'compaction_needed'; lastUpdateId?: number }>;
}
export class TeamDoc {
    constructor(options: { key: Buffer; transport: Transport; store: DocStore; minSendIntervalMs: number; now?: () => number });
    /** Load stored updates; seed from `starter` only if the document is still empty. */
    start(starter: { name: string; content: string }[]): Promise<void>;
    readonly files: Y.Map<Y.Text>;                 // file name -> shared text
    readonly remoteOrigin: unknown;                // origin of transactions that came from teammates or the store
    flush(): Promise<void>;                        // everything local is sent and stored
    stop(): void;
}
```

  - Wire messages, sealed: byte 0 is the kind. `1` + Yjs update; `2` + state vector ("hello", sent on every open); `3` + Yjs update answering a hello (the difference for that state vector), sent after a random 0–300 ms wait and skipped when empty.
  - Outgoing local updates are merged (`Y.mergeUpdates`) and sent at most once per `minSendIntervalMs`; the first after a quiet spell goes at once.
  - The same merged update is appended to the store, at most once every 5 s and on `flush()`. On `compaction_needed`, or once 200 updates were loaded or appended since the last compaction, append `Y.encodeStateAsUpdate(doc)` with `replacesThrough` set to the last id this client has applied.
  - Starter content is normalised to LF before seeding.

- [ ] **Step 1: Write the failing tests** with an in-memory `Hub` (transports that deliver to each other, with an `offline` switch and an injectable delay) and a `FakeStore` implementing the Task 2 rules.

```ts
test('two teammates see each other\'s edits');
test('the first teammate seeds the starter files and the second loads them');     // both files.get('Main.java').toString() === starter, no duplication
test('two teammates starting at once seed only once');                              // FakeStore answers already_seeded to the loser
test('a late joiner alone loads the stored document');                              // no peers online
test('edits made offline merge on reconnect');
test('local edits are merged into one message per send interval');                  // 10 edits within the interval: 1 send
test('the first edit after a quiet spell is sent at once');
test('a message that does not open is ignored');                                    // garbage, and one sealed with another key
test('a file added by one teammate appears for the other, and a deleted one goes');
test('flush stores what was typed');
test('a long document is compacted');                                               // after 200 appends the store holds 1 row + later ones
test('stop sends and applies nothing more');
```

- [ ] **Step 2: Run** `npm test` — expect FAIL.
- [ ] **Step 3: Implement `transport.ts` (the interface only) and `teamDoc.ts`.**
- [ ] **Step 4: Run** `npm test` — expect PASS.
- [ ] **Step 5: Checkpoint.**

### Task 6: Wiring into VS Code and the sidebar

**Files:**
- Create: `certicode-labs-extension/src/teamSync/supabaseTransport.ts`, `certicode-labs-extension/src/teamSync/workspaceSync.ts`
- Modify: `certicode-labs-extension/src/sidebarProvider.ts` (`syncSessionState` ~789, `handleDocumentChange` 1371, `guardReadonlyFile` 963, `submit` ~2170, `stopSessionTimers` 194, webview Team card), `certicode-labs-extension/src/test/vscodeStub.ts`
- Test: `certicode-labs-extension/src/test/workspaceSync.test.ts`, `certicode-labs-extension/src/test/teamSyncProvider.test.ts`

**Interfaces:**
- Consumes: `TeamDoc`, `TextBinding`, `Transport`, `DocStore`; `GET/POST .../team-doc` routes; session payload `team_sync.enabled`, `team.id`, `status`.
- Produces:
  - `SupabaseTransport implements Transport`: `new RealtimeClient(url + '/realtime/v1', { params: { apikey }, transport })` where `transport` is `globalThis.WebSocket` or, without one, the `ws` package; one channel named by `topic` with broadcast `self: false`; event name `m`, payload `{ d: sealedString }`. `onOpen` fires on every `SUBSCRIBED`.
  - `WorkspaceSync`: owns a `TeamDoc` and one `TextBinding` per shared file.
    - `start(root: vscode.Uri, shared: {name, content}[])`: sets workspace setting `files.eol` to `"\n"`; rewrites any shared file on disk that contains CRLF to LF when it is not open and dirty; opens each as a document (not shown) and binds it.
    - `handleChange(event: vscode.TextDocumentChangeEvent): boolean` — true when the event was a teammate's edit. The only entry point for change events.
    - A document changed by a teammate is saved 1 s after the last remote edit, unless the user has unsaved edits of their own in it.
    - A file added to or removed from `TeamDoc.files` is created or deleted in the workspace, and the reverse for a code file the student creates or deletes inside the lab folder.
    - `flush(): Promise<void>` — every binding settled, then `TeamDoc.flush()`.
    - `stop()`.
  - `SidebarProvider`:
    - Starts `WorkspaceSync` after starter files are provisioned when `team_sync.enabled`; stops it when the session ends, the attempt closes, or `team.id` changes (then starts again for the new team).
    - `handleDocumentChange` and `guardReadonlyFile` return early when `workspaceSync.handleChange(event)` is true, after `trackDocumentText(event)` has run.
    - `submit()` awaits `workspaceSync.flush()` before `collectSubmissionFiles()`.
    - Posts `{ type: 'teamSync', state: 'on' | 'connecting' | 'off', reason?: string }` to the webview; the Team card shows "Typing together" / "Connecting…" / "Shared typing is off" with the reason.

- [ ] **Step 1: Write the failing tests**

```ts
// workspaceSync.test.ts — with a fake TeamDoc transport hub and the vscode stub extended with documents that record applyEdit and save
test('a CRLF file on disk is rewritten to LF before it is bound');
test('a read-only starter file is not shared');
test('a notes file (README.md) is not shared');
test('a teammate\'s edit is reported as remote and the document is saved a second later');
test('the user\'s own unsaved edits stop the automatic save');
test('a file a teammate adds is created in the workspace');
test('flush waits for bindings and the store');
// teamSyncProvider.test.ts
test('a teammate\'s edit is not counted as typing, a paste or a line change');     // keystroke count, paste flags and diff stats unchanged
test('the user\'s own typing is still counted with shared typing on');
test('submit waits for shared edits to settle before collecting files');
test('shared typing does not start on a solo lab or when the server says it is off');
test('shared typing stops when the attempt closes and restarts when the team changes');
test('the sidebar is told when shared typing is off, with the reason');
```

- [ ] **Step 2: Run** `npm test` — expect FAIL.
- [ ] **Step 3: Implement.** Escape every value shown in the webview with `esc()`.
- [ ] **Step 4: Run** `npm test` — expect PASS. Run `npm run bundle` — expect exit code 0.
- [ ] **Step 5: Checkpoint.**

### Task 7: Check in a real VS Code

**Files:**
- Create: `certicode-labs-extension/scripts/sync-check/extension.js`, `certicode-labs-extension/scripts/sync-check/run.js`, `certicode-labs-extension/scripts/sync-check/package.json`, `certicode-labs-extension/scripts/sync-check/README.md`; add `scripts/**` to `.vscodeignore`

**Interfaces:**
- Consumes: the compiled `out/teamSync/*` modules.
- Produces: `npm run sync-check` — starts an isolated VS Code (own user-data and extensions folders), runs the real `WorkspaceSync` against a headless teammate through a local WebSocket relay implementing `Transport`, on two files, with machine-speed and human-pace rounds, and writes `result.json`.

- [ ] **Step 1: Write the check** so that it fails when the editor text, the editor-side shared text and the teammate's shared text differ, or when the characters counted as local differ from the characters typed locally.
- [ ] **Step 2: Prove it can fail:** run it once with remote-echo detection disabled by an environment flag read only by the check's own wiring; expect `ok: false`.
- [ ] **Step 3: Run** `npm run sync-check` — expect `ok: true` for every round, exit code 0.
- [ ] **Step 4: Checkpoint.**

### Task 8: Docs, version, hand-over

**Files:**
- Modify: `CHANGELOG.md`, `README.md` (section 3 and the extension install section), `database/ERD_INSTRUCTIONS.md`, `certicode-labs-extension/README.md`, `certicode-labs-extension/package.json` and lock (`1.10.0`), `config/services.php` (`min_version` to `1.10.0`), `public/downloads/certicode-labs.vsix`

- [ ] **Step 1: Write the CHANGELOG entry and update README and ERD** (`team_docs`, `team_doc_updates`). State plainly what a student sees, the Free-plan message budget, and that shared typing is off until the owner adds the two settings.
- [ ] **Step 2: Run everything.** `php artisan test` — exit code 0. `php vendor/bin/pint --test` — `passed`. PowerShell in `certicode-labs-extension`: `npm test` — exit code 0; `npm run sync-check` — `ok: true`.
- [ ] **Step 3: Build the package:** `npm run distribute`. Install the built `.vsix` into the isolated VS Code from Task 7 and confirm the extension activates (the bundle carries its libraries).
- [ ] **Step 4: Sweep for secrets:** grep the diff, the bundle and the `.vsix` contents for the Supabase project host and key shapes; expect none.
- [ ] **Step 5: List for the owner, not done by the builder:**
  - Migration `2026_11_12_000003_create_team_docs_tables` to run on Supabase before deploying (after the three from part 1).
  - Two Vercel settings for Production: `TEAM_SYNC_SUPABASE_URL` (the project URL) and `TEAM_SYNC_SUPABASE_KEY` (the publishable key). Optional `TEAM_SYNC_MIN_SEND_INTERVAL_MS`.
  - Supabase Realtime setting "Allow public access to channels" must stay enabled (it is by default).
  - Not verified by this plan: the real Supabase channel. It needs the owner's go-ahead to try against the live project, or two people in a real lab.
- [ ] **Step 6: Checkpoint.**
