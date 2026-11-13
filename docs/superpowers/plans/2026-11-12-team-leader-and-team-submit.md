# Team Leader and Team Submit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On a group lab, one team leader submits once and every teammate who took part gets the same grade; the leader can be changed by the teacher, by hand-over, or by a vote.

**Architecture:** Leader state lives on the team (`groups.leader_id`, `group_members.leader_vote`) with all rules in `App\Services\LabTeams`. `LabSessionController::submitSession` refuses non-leaders, takes a per-team grading lock, grades once and copies the result onto every teammate's session through the model. The extension reads leader state from the session payload and gates its Submit button.

**Tech Stack:** Laravel (PHP, PHPUnit feature tests on in-memory SQLite), Blade, VS Code extension in TypeScript (`node --test` with `src/test/vscodeStub.ts`).

**Spec:** `docs/superpowers/specs/2026-11-12-group-lab-coediting-design.md` (part 1 of 3: sections "Leader", "Team submit", "Joining", and the leader parts of "Teacher view"). Shared files and the teacher diff are later plans.

## Global Constraints

- Commit or push only when the owner asks. Steps say "Checkpoint", not "Commit". No Claude attribution in any commit or PR.
- The repo is public. No secrets as config defaults.
- Production migrations are run by hand on Supabase. Task 8 lists them for the owner. Never run a migration or a write against production; the Supabase MCP is read-only use.
- `.env` `DB_URL` points at production and beats `DB_CONNECTION=sqlite`. Run PHP tests only through `php artisan test` (phpunit.xml sets `DB_URL=""` and SQLite `:memory:`).
- Run extension `npm` commands in PowerShell. `npm run build` fails silently in Git Bash.
- Solo labs and a student with no team on a group lab behave exactly as today.
- A vote passes at more than half of the teammates other than the current leader. The leader hands over directly.
- A teammate who never opened the lab gets no grade.
- Error code for a non-leader submit: `not_team_leader`, HTTP 403.
- Extension version after this plan: `1.9.0`.
- User-facing copy is plain English, no jargon, matching CHANGELOG.md's voice.

## Review Focus

1. Instructor removes the leader from the team mid-attempt: the team must not be stuck. Expect `leader_id` cleared and the next teammate to open or poll the lab made leader. (Task 1)
2. A teammate's "Check progress" is being graded when the leader submits: expect `409 grading_in_progress`, never two gradings or a half-closed team. (Task 4)
3. A teammate's attempt was closed for inactivity while the leader's attempt was open: expect them to get the team grade. One abandoned before the leader started must stay untouched. (Task 4)
4. A vote for someone outside the team, or by someone outside the team: expect 422 / 403 and no state change. (Task 3)
5. A student on a group lab with no team submits: expect today's solo grading, no leader check. (Task 4)

---

### Task 1: Leader rules in `LabTeams`

**Files:**
- Create: `database/migrations/2026_11_12_000000_add_leader_to_groups.php`
- Modify: `app/Models/Group.php`, `app/Services/LabTeams.php`, `app/Http/Controllers/LabTeamController.php:57-74` (update), `app/Http/Controllers/LabTeamPickController.php:53-56,84`, `app/Models/Laboratory.php:457-493` (findOrStartAttemptFor)
- Test: `tests/Feature/LabTeamLeaderTest.php` (same `setUp` as `tests/Feature/LabTeamsTest.php`)

**Interfaces:**
- Produces:
  - Columns: `groups.leader_id` nullable FK users, null on delete; `group_members.leader_vote` nullable FK users, null on delete.
  - `Group::leader()` belongsTo User; `leader_id` fillable.
  - `LabTeams::ensureLeader(Group $team, User $opener): User` — returns the leader, making `$opener` leader when there is none.
  - `LabTeams::setLeader(Group $team, User $leader): void` — sets leader, clears every `leader_vote`. Throws `ValidationException` (key `leader`) when `$leader` is not a member.
  - `LabTeams::chooseLeader(Group $team, User $actor, User $candidate): bool` — true when the leader changed. Actor is leader: hand over. Otherwise record the actor's vote and promote the candidate once votes for them exceed half of the non-leader members. Throws `ValidationException` when either is not a member.
  - `LabTeams::leaderVotes(Group $team): array<int,int>` — voter id to candidate id.

- [ ] **Step 1: Write the failing tests**

```php
public function test_the_student_who_starts_a_team_is_its_leader()
{
    $this->laboratory->update(['team_formation' => 'students']); // use the value studentsPickTeams() checks
    $this->actingAs($this->alice)->post(route('laboratories.my-team.store', $this->laboratory->id), ['members' => [$this->bob->id]]);
    $this->assertSame($this->alice->id, $this->laboratory->teamOf($this->alice)->leader_id);
}

public function test_the_first_teammate_to_open_the_lab_leads_a_team_without_a_leader()
{
    $team = $this->team([$this->alice, $this->bob, $this->carol]);
    $this->laboratory->findOrStartAttemptFor($this->bob);
    $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    $this->laboratory->findOrStartAttemptFor($this->alice);
    $this->assertSame($this->bob->id, $team->fresh()->leader_id);
}

public function test_the_leader_hands_over_directly()
{
    $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
    $this->assertTrue(app(LabTeams::class)->chooseLeader($team, $this->alice, $this->carol));
    $this->assertSame($this->carol->id, $team->fresh()->leader_id);
}

public function test_a_vote_needs_more_than_half_of_the_other_teammates()
{
    $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
    $teams = app(LabTeams::class);
    $this->assertFalse($teams->chooseLeader($team, $this->bob, $this->bob));   // 1 of 2
    $this->assertSame($this->alice->id, $team->fresh()->leader_id);
    $this->assertTrue($teams->chooseLeader($team, $this->carol, $this->bob));  // 2 of 2
    $this->assertSame($this->bob->id, $team->fresh()->leader_id);
    $this->assertSame([], $teams->leaderVotes($team->fresh()));
}

public function test_in_a_pair_the_other_teammate_can_take_over_alone()
{
    $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
    $this->assertTrue(app(LabTeams::class)->chooseLeader($team, $this->bob, $this->bob));
}

public function test_nobody_outside_the_team_can_vote_or_be_chosen()
{
    $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
    $this->expectException(ValidationException::class);
    app(LabTeams::class)->chooseLeader($team, $this->alice, $this->carol);
}

public function test_removing_the_leader_from_the_team_clears_the_leader()
{
    $team = $this->team([$this->alice, $this->bob, $this->carol], leader: $this->alice);
    $this->actingAs($this->instructor)->put(route('laboratories.teams.update', [$this->laboratory->id, $team->id]),
        ['name' => $team->name, 'members' => [$this->bob->id, $this->carol->id]]);
    $this->assertNull($team->fresh()->leader_id);
    $this->laboratory->findOrStartAttemptFor($this->carol);
    $this->assertSame($this->carol->id, $team->fresh()->leader_id);
}
```

`team(array $members, ?User $leader = null): Group` is a private helper in the test class.

- [ ] **Step 2: Run** `php artisan test --filter=LabTeamLeaderTest` — expect FAIL (unknown column `leader_id`).
- [ ] **Step 3: Write the migration and the `Group` changes.**
- [ ] **Step 4: Implement the four `LabTeams` methods.** Each runs inside `locked($team->laboratory, ...)`. Votes cast for a removed member, and the removed member's own vote, are cleared wherever members are detached or synced.
- [ ] **Step 5: Wire the callers.** `LabTeamPickController::store` sets the creator as leader when it creates the team; `destroy` clears `leader_id` when the leaver led. `LabTeamController::update` clears `leader_id` when the leader is no longer in `$memberIds`. `Laboratory::findOrStartAttemptFor` calls `ensureLeader` when the user has a team, for both a resumed and a new attempt.
- [ ] **Step 6: Run** `php artisan test --filter="LabTeamLeaderTest|LabTeamsTest"` — expect PASS.
- [ ] **Step 7: Checkpoint.**

### Task 2: Team notices in chat

**Files:**
- Create: `database/migrations/2026_11_12_000001_add_is_system_to_lab_session_chats.php`
- Modify: `app/Models/LabSessionChat.php`, `app/Services/LabTeams.php`, `app/Http/Controllers/Api/LabSessionController.php` (`getChats`, `sendChat` payloads; `startSession`), `app/Http/Controllers/LabAttemptController.php:28` (startSession)
- Test: `tests/Feature/TeamNoticesTest.php`

**Interfaces:**
- Consumes: `LabTeams::ensureLeader`.
- Produces:
  - `lab_session_chats.is_system` boolean default false; cast and fillable.
  - `LabTeams::notice(Group $team, string $message, ?User $about = null): void` — writes one `is_system` chat row on an open session of the team (the `$about` student's when they have one) and broadcasts `ChatMessageSent` to every `teamSessionIds()`. Does nothing when the team has no open session. Never throws.
  - Chat payloads gain `is_system: bool`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_teammates_see_a_notice_when_someone_opens_the_lab()
{
    // alice opens first, then bob through the same route the lab page uses
    $this->actingAs($this->alice)->post(route('laboratories.start', $this->laboratory->id));
    $this->actingAs($this->bob)->post(route('laboratories.start', $this->laboratory->id));
    $chats = $this->actingAs($this->alice)->getJson("/api/vscode/sessions/{$aliceSession->id}/chat")->json('chats'); // use the real API prefix from routes/api.php
    $this->assertContains('Bob joined the lab.', array_column($chats, 'message'));
    $this->assertTrue(collect($chats)->firstWhere('message', 'Bob joined the lab.')['is_system']);
}

public function test_resuming_an_open_attempt_posts_no_second_notice() { /* start twice as bob, assert one 'Bob joined the lab.' row */ }

public function test_a_solo_lab_posts_no_notice() { /* is_group_lab false, assert LabSessionChat::count() === 0 */ }

public function test_teammates_see_a_notice_when_someone_closes_vs_code() { /* bob posts to /vscode-disconnect on his open session: chats contain 'Bob left the lab.' with is_system true */ }
```

`vscodeDisconnect` posts `"{name} left the lab."` for an in-progress team session.

- [ ] **Step 2: Run** `php artisan test --filter=TeamNoticesTest` — expect FAIL.
- [ ] **Step 3: Implement.** Exact copy: `"{name} joined the lab."` posted only when the attempt was newly created (`$session->wasRecentlyCreated`). Both start routes call it.
- [ ] **Step 4: Run** the filter again — expect PASS. Then `php artisan test --filter="Chat|Broadcasting"` — expect PASS.
- [ ] **Step 5: Checkpoint.**

### Task 3: Changing the leader (teacher and students)

**Files:**
- Modify: `routes/web.php` (after lines 199, 203 and 336), `routes/api.php` (after line 44), `app/Http/Controllers/LabTeamController.php`, `app/Http/Controllers/LabTeamPickController.php`, `app/Http/Controllers/Api/LabSessionController.php`, `resources/views/laboratories/teams.blade.php`, `resources/views/laboratories/_team-picker.blade.php`, `resources/views/laboratories/show.blade.php` (team card)
- Test: `tests/Feature/LabTeamLeaderTest.php` (extend)

**Interfaces:**
- Consumes: `LabTeams::setLeader`, `chooseLeader`, `leaderVotes`, `notice`.
- Produces:
  - `PUT /laboratories/{id}/teams/{team}/leader` → `LabTeamController::setLeader`, route `laboratories.teams.leader`, body `leader_id`. Lab managers only. Allowed at any time, including mid-attempt.
  - `POST /laboratories/{id}/my-team/leader` → `LabTeamPickController::chooseLeader`, route `laboratories.my-team.leader`, body `candidate_id`. Any student in a team on the lab, whatever `team_formation` is.
  - `POST {session prefix}/sessions/{sessionId}/leader` → `LabSessionController::chooseLeader`, body `candidate_id`, in both the web and API session groups (same middleware as `/chat`). Returns `{status, leader_id, changed, votes}`.
  - Every change posts `LabTeams::notice`: `"{name} is now the team leader."` and sends `ClassActivityNotification` titled `"New team leader"` to the team. A vote that does not pass posts `"{voter} voted for {candidate} as team leader."`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_the_instructor_can_reassign_the_leader_mid_attempt()
{
    $team = $this->team([$this->alice, $this->bob], leader: $this->alice);
    $this->laboratory->findOrStartAttemptFor($this->alice);
    $this->actingAs($this->instructor)
        ->put(route('laboratories.teams.leader', [$this->laboratory->id, $team->id]), ['leader_id' => $this->bob->id])
        ->assertRedirect();
    $this->assertSame($this->bob->id, $team->fresh()->leader_id);
}

public function test_the_instructor_cannot_pick_a_leader_from_outside_the_team()   // assertSessionHasErrors('leader_id'), leader unchanged
public function test_a_student_cannot_use_the_instructor_route()                    // assertForbidden
public function test_a_teammate_votes_from_the_lab_page()                           // carol + bob vote bob in a team of three led by alice; leader becomes bob
public function test_a_teammate_votes_from_the_extension()                          // postJson to the session route; assertJson(['changed' => true, 'leader_id' => bob])
public function test_a_student_outside_the_team_cannot_vote_through_someone_elses_session() // assertForbidden, leader unchanged
public function test_a_leader_change_notifies_the_team()                            // Notification::fake(); assertSentTo([$alice,$bob], ClassActivityNotification::class)
```

- [ ] **Step 2: Run** `php artisan test --filter=LabTeamLeaderTest` — expect the new tests FAIL (route not defined).
- [ ] **Step 3: Add routes and controller actions.** The session route resolves the actor as `$request->user() ?? $session->user` like `sendChat`, and requires that actor to be a member of `$session->group`.
- [ ] **Step 4: Add the UI.** Teams page: a "Leader" select per team card, saved on its own form. Lab page team card and team picker: leader badge, "Make leader" (leader) or "Vote as leader" (others) beside each teammate, and the current votes as "Bob: 1 vote". Follow the existing components in `design-system/`.
- [ ] **Step 5: Run** `php artisan test --filter="LabTeamLeaderTest|LabTeamsTest"` — expect PASS.
- [ ] **Step 6: Checkpoint.**

### Task 4: Team submit

**Files:**
- Modify: `app/Models/LabSession.php` (`gradingLock`, `acceptsWork`, new methods), `app/Http/Controllers/Api/LabSessionController.php:972-1099` (`submitSession`, `gradeSubmission`), `app/Models/Laboratory.php:461-470`, `app/Models/LabSession.php:708-744` (`autoExpireStaleSessions`)
- Test: `tests/Feature/TeamSubmitTest.php` (fake the grader the way `tests/Feature/SubmitTimeBudgetTest.php` does)

**Interfaces:**
- Consumes: `LabTeams::ensureLeader`.
- Produces:
  - `LabSession::teamAttemptSessions(): Collection<LabSession>` — for a session with a `group_id`: sessions of the same lab and team that are `in_progress`, or `abandoned` with `ended_at >= $this->started_at`. Includes `$this`. A solo session returns only itself.
  - `LabSession::clockStartedAt(): ?Carbon` — earliest `started_at` among the team's in-progress sessions on the lab; the session's own `started_at` when solo.
  - `LabSession::gradingLock()` — key `lab_team_{group_id}_{lab_id}_grading` for team sessions, unchanged key for solo.
  - Submit response gains `team: {submitted_by: int, graded_user_ids: int[]}` on team sessions.
  - Refusal body: `{"error":"not_team_leader","message":"Only your team leader, {name}, can submit this lab. Update the CertiCode Labs extension if you don't see who leads your team."}`, status 403.
  - `session_completed` telemetry payload gains `submitted_by` on every closed team session.

- [ ] **Step 1: Write the failing tests**

```php
public function test_only_the_leader_can_submit_a_team_lab()
{
    [$alice, $bob] = $this->openTeamSessions(leader: $this->alice);   // returns the two LabSessions
    $this->submitAs($this->bob, $bob)->assertStatus(403)->assertJson(['error' => 'not_team_leader']);
    $this->assertSame('in_progress', $bob->fresh()->status);
    $this->assertSame('in_progress', $alice->fresh()->status);
}

public function test_the_leaders_submit_grades_every_teammate_the_same()
{
    [$alice, $bob] = $this->openTeamSessions(leader: $this->alice);
    $this->fakeGrade(score: 80.0, completedTaskIds: [1]);
    $this->submitAs($this->alice, $alice)->assertOk()->assertJsonPath('team.graded_user_ids', [$this->alice->id, $this->bob->id]);
    foreach ([$alice->fresh(), $bob->fresh()] as $s) {
        $this->assertSame('completed', $s->status);
        $this->assertSame(80.0, (float) $s->performance_score);
        $this->assertSame([1], $s->completed_tasks);
        $this->assertSame($alice->fresh()->submitted_code, $s->submitted_code);
        $this->assertEquals($alice->fresh()->ended_at, $s->ended_at);
    }
    $this->assertSame(1, $this->gradeCalls);   // the AI ran once
}

public function test_a_teammate_who_never_opened_the_lab_gets_nothing()            // carol in team, no session: LabSession::where('user_id', carol)->count() === 0 after submit
public function test_a_teammate_closed_for_inactivity_during_the_attempt_is_graded() // bob abandoned with ended_at after alice started: completed, 80.0
public function test_an_attempt_abandoned_before_the_leader_started_is_left_alone()   // bob abandoned yesterday: still abandoned, score unchanged
public function test_submit_waits_for_a_teammates_check_progress()
{
    [$alice, $bob] = $this->openTeamSessions(leader: $this->alice);
    $lock = $bob->gradingLock(); $lock->get();
    $this->submitAs($this->alice, $alice)->assertStatus(409)->assertJson(['error' => 'grading_in_progress']);
    $lock->release();
    $this->assertSame('in_progress', $alice->fresh()->status);
}
public function test_a_student_without_a_team_on_a_group_lab_submits_alone()         // group_id null: 200, completed, no 'team' key
public function test_each_teammates_competency_scores_follow_the_team_grade()        // assert the same rows the existing competency tests assert, for alice and bob
public function test_the_team_shares_one_clock()
{
    // time_limit 45. alice started 50 minutes ago, bob (leader) 10 minutes ago.
    $this->assertFalse($bob->acceptsWork());
    $this->assertEquals($alice->started_at, $bob->clockStartedAt());
}
public function test_a_solo_attempt_keeps_its_own_clock()                             // clockStartedAt() equals started_at
```

- [ ] **Step 2: Run** `php artisan test --filter=TeamSubmitTest` — expect FAIL.
- [ ] **Step 3: Implement the `LabSession` methods and the lock key.**
- [ ] **Step 4: Use `clockStartedAt()`** wherever a self-paced time limit is measured: `acceptsWork()`, `Laboratory::findOrStartAttemptFor`, `autoExpireStaleSessions` (limit check only; the 30-minute inactivity rule stays per session), and the elapsed/remaining figures in `getSession`.
- [ ] **Step 5: Implement the leader check** at the top of `submitSession`, after `acceptsWork()`: for a session with a `group_id`, `ensureLeader($session->group, $session->user)` then refuse unless that leader is `$session->user_id`.
- [ ] **Step 6: Close the team in `gradeSubmission`.** After the leader's `updateIfInProgress` succeeds, apply the same attributes to each other session from `teamAttemptSessions()` with `updateIfInProgress($mate, $mate->ended_at, ...)`-style acceptance so in-progress and the listed abandoned ones are both taken. Each save goes through the model (the competency hook depends on it). Write one `session_completed` telemetry row per session. Leaderboard broadcast, similarity refresh and notifications stay once per submit.
- [ ] **Step 7: Run** `php artisan test --filter="TeamSubmitTest|SubmitTimeBudgetTest|LabAttemptLifecycleGuardTest|LabExecuteGradeForgeryTest|EndToEndLabLifecycleTest"` — expect PASS.
- [ ] **Step 8: Checkpoint.**

### Task 5: Grade override for the whole team, and "Did not take part"

**Files:**
- Modify: `app/Http/Controllers/InstructorMonitoringController.php:318-390` (`overrideGrade`), `:260-290` (monitoring data), `resources/views/instructor/monitoring/_lab-panel.blade.php`, the override form in the same folder
- Test: `tests/Feature/TeamGradeOverrideTest.php`

**Interfaces:**
- Consumes: sessions closed by Task 4 share `group_id`, `lab_id` and `ended_at`.
- Produces:
  - `overrideGrade` accepts `apply_to_team` (boolean; default true when the session has a `group_id`). When true it overrides every session with the same `group_id`, `lab_id` and `ended_at`, each through the model, each with its own telemetry row and notification.
  - Monitoring data gains per team: `leader_id`, and `absent: [{id, name}]` for members with no session on the lab.

- [ ] **Step 1: Write the failing tests**

```php
public function test_an_override_applies_to_the_whole_team_by_default()   // post override_score 90 on alice's session: alice and bob effective_score 90.0
public function test_an_override_can_target_one_student()                 // apply_to_team false: alice 90.0, bob 80.0
public function test_an_override_on_a_solo_attempt_touches_only_it()
public function test_monitoring_lists_teammates_who_did_not_take_part()   // carol in team, no session: data has absent [{id: carol}] and assertSee('Did not take part')
```

- [ ] **Step 2: Run** `php artisan test --filter=TeamGradeOverrideTest` — expect FAIL.
- [ ] **Step 3: Implement.** Checkbox copy: "Apply to the whole team", checked by default, shown only for team attempts.
- [ ] **Step 4: Run** `php artisan test --filter="TeamGradeOverrideTest|InstructorPlagiarismTest|Monitoring"` — expect PASS.
- [ ] **Step 5: Checkpoint.**

### Task 6: Leader state in the session payload

**Files:**
- Modify: `app/Http/Controllers/Api/LabSessionController.php:724-808` (`getSession`)
- Test: `tests/Feature/VSCodeExtensionApiTest.php` (extend)

**Interfaces:**
- Consumes: `LabTeams::ensureLeader`, `leaderVotes`.
- Produces, in the `getSession` JSON:
  - `team: null` for a session without a team, else `{id, name, leader_id, is_leader, can_submit, votes: [{voter_id, candidate_id}]}`.
  - Each entry of `teammates` gains `is_leader: bool` and `in_lab: bool` (has an in-progress session on this lab).
  - `can_submit` is true for the leader and for any session without a team.

- [ ] **Step 1: Write the failing tests**

```php
public function test_the_session_payload_names_the_leader()
{
    // alice leads, bob reads his own session
    $json = $this->getSessionAs($this->bob, $bobSession)->assertOk()->json();
    $this->assertSame($this->alice->id, $json['team']['leader_id']);
    $this->assertFalse($json['team']['is_leader']);
    $this->assertFalse($json['team']['can_submit']);
    $this->assertTrue(collect($json['teammates'])->firstWhere('id', $this->alice->id)['is_leader']);
}
public function test_a_teammate_who_has_not_opened_the_lab_is_marked_not_in_lab()
public function test_a_solo_session_has_no_team_and_can_submit()   // 'team' === null
```

- [ ] **Step 2: Run** `php artisan test --filter=VSCodeExtensionApiTest` — expect the new tests FAIL.
- [ ] **Step 3: Implement.** Reading the session also runs `ensureLeader`, so a team whose leader was removed gets one on the next poll.
- [ ] **Step 4: Run** the filter — expect PASS.
- [ ] **Step 5: Checkpoint.**

### Task 7: Extension 1.9.0

**Files:**
- Modify: `certicode-labs-extension/src/sidebarProvider.ts` (`submit()` at 2134, message handler near 331, webview markup near 2872-2921 and script near 3014, 3140, 3547), `certicode-labs-extension/package.json` (version)
- Test: `certicode-labs-extension/src/test/teamLeader.test.ts` (build the provider like `connectedProvider()` in `grading.test.ts`)

**Interfaces:**
- Consumes: Task 6 payload (`team`, `teammates[].is_leader`, `teammates[].in_lab`); `POST {API_PREFIX}/sessions/{id}/leader` with `{candidate_id}`; `403 not_team_leader`; submit response `team`.
- Produces: webview messages `chooseLeader {candidateId}` (webview to extension) and `leaderResult {changed, leader_id, votes}` (extension to webview).

- [ ] **Step 1: Write the failing tests**

```ts
test('a teammate who is not the leader cannot submit', async () => {
    const { provider, requests } = connectedProvider();
    provider._team = { leader_id: 2, is_leader: false, can_submit: false, leader_name: 'Alice' };
    await provider.submit();
    assert.strictEqual(requests.length, 0);
    assert.match(shown.errors.at(-1), /Only your team leader, Alice, can submit/);
});
test('the leader submits as before', async () => { /* can_submit true: one request to /submit */ });
test('a refused submit shows the server message and keeps the session open', async () => { /* answer 403 {error:'not_team_leader', message:'M'}: shown.errors has 'M', stopSessionTimers not called */ });
test('choosing a leader posts the candidate and relays the result', async () => { /* onMessage {type:'chooseLeader', candidateId: 3}: request path ends /leader, body {candidate_id: 3}; answer {changed:true, leader_id:3, votes:[]}: webview got leaderResult */ });
test('a session without a team can submit', async () => { /* _team null: one request */ });
```

- [ ] **Step 2: Run** in PowerShell, from `certicode-labs-extension`: `npm test` — expect the new tests FAIL.
- [ ] **Step 3: Implement.** Store `team` from each session read. In `submit()`, check `can_submit` before collecting files. In the webview: disable Submit for non-leaders with the title "Only your team leader can submit"; in the Team card show a "Leader" badge, an "In the lab" dot from `in_lab`, "Make leader" for the leader and "Vote as leader" for others, and vote counts. Teammates' sessions closing after the leader's submit already arrive through the existing poll; show the result with "Submitted by {leader}". Escape every server value with `esc()`.
- [ ] **Step 4: Run** `npm test` — expect all PASS. Run `npm run compile` — expect exit code 0 and no output in the error stream.
- [ ] **Step 5: Bump** `version` to `1.9.0`; update the minimum/recommended extension version constant the server reports in `extensionVersionStatus` if it pins one.
- [ ] **Step 6: Checkpoint.**

### Task 8: Docs, full run, hand-over

**Files:**
- Modify: `CHANGELOG.md`, `README.md` (Team Mode section, line ~60), `database/ERD_INSTRUCTIONS.md`, `certicode-labs-extension/README.md`, `resources/views/legal/privacy.blade.php` only if it describes what teammates can see

- [ ] **Step 1: Write the CHANGELOG entry** in the file's existing voice: leader, hand-over and vote, one submit for the team, same grade, "Did not take part", team-wide override, shared clock, extension 1.9.0.
- [ ] **Step 2: Update README and ERD** for `groups.leader_id`, `group_members.leader_vote`, `lab_session_chats.is_system`.
- [ ] **Step 3: Run everything.** `php artisan test` — expect exit code 0. PowerShell in `certicode-labs-extension`: `npm test` — expect exit code 0.
- [ ] **Step 4: Run the app locally and check by hand** (SQLite; assert the connection is not production before any write): two students in one team, non-leader Submit disabled, vote changes the leader, leader submit closes both with one score, teacher override hits both.
- [ ] **Step 5: List for the owner** the two migrations to run by hand on Supabase, in order, and state that the server change is safe to deploy before students update the extension (old extensions get the `not_team_leader` message).
- [ ] **Step 6: Checkpoint.**
