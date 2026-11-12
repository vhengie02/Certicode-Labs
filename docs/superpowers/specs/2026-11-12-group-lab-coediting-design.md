# Group labs: shared editing, team leader and team submit

Status: draft for approval. Nothing here is built.
Date: 2026-11-12

## Why

A group lab today is three students working alone who happen to share a chat. Each teammate
codes in their own copy, submits their own copy and is graded on their own copy
(`LabSessionController::submitSession` grades one `LabSession`). Teammates can get different
scores for "the same" work, and one who never submits has no grade at all while the leaderboard
shows the team as done.

The owner wants a group lab to be one piece of work: the team types in the same files at the
same time, one leader hands it in, everyone gets the result, and the teacher can still see what
each student did.

## What was decided, and by whom

Decided by the owner:

- Each team has a **leader**. Only the leader can submit.
- Teammates **join one by one**. Nobody waits for the full team. The team chat says when someone joins.
- **Each student's own machine** records that student's behaviour (typing, pastes, focus). No machine records for another student.
- The leader submits once. The **AI grades, then the teacher reviews**.
- **Every teammate gets the same grade** from that submit.
- The teacher can see **the diff and every student's stats**.
- If the leader is absent or drops, **the teacher can reassign the leader, or the teammates vote a new one**.
- Build **our own shared editing** instead of using VS Code Live Share, so the leader does not have to host and the app controls the behaviour.

Decided by the designer (open to change):

- Nobody hosts. Shared files live on the server side, so the "pick the student with the best computer" advice is dropped. The leader's only power is Submit.
- Merging of simultaneous edits uses **Yjs** (open-source library). Edits travel over **Supabase Realtime Broadcast**. Laravel stores the document and stays ignorant of the Yjs format.
- First leader: on "Students pick" labs, the student who starts the team. On instructor-made or random teams, whoever the instructor marks on the Teams page; if nobody is marked, the first teammate to open the lab. This way a team is never stuck without a leader.
- The leader can **hand leadership over** to a teammate directly. Otherwise a vote passes when **more than half of the teammates other than the current leader** name the same teammate (1 of 1 in a pair, 2 of 2 in a team of three, 2 of 3 in a team of four). The leader is left out of the count because the vote exists for when the leader is gone; counting them would leave a pair stuck.
- A teammate whose attempt was closed for inactivity while the leader's attempt was open still **gets the team grade**. They took part.
- A teammate who **never opens the lab gets no grade** and is shown to the teacher as "Did not take part".
- The teacher's grade override applies to the whole team by default, with an "Only this student" choice.
- The team shares one clock on timed self-paced labs: it starts when the first teammate opens the lab.
- If no leader submits before time runs out, the attempt ends ungraded, the same as a solo lab today. There is no automatic submit.

## Goals

1. Teammates on a group lab edit the same files live, each in their own VS Code.
2. One leader submit grades and closes the attempt for every teammate who took part, with one score.
3. Per-student telemetry stays exact: a student is credited and flagged only for what they typed or pasted themselves.
4. A team is never blocked by an absent leader.
5. The teacher sees the team's files, the diff against the starter files, and each student's stats.

## Non-goals

- Shared terminals, shared debugging, voice or following a teammate's view.
- Shared editing on solo labs, or between teams.
- A web editor. Labs stay in VS Code.
- Changing how the AI grades, or how solo labs behave.
- Per-line authorship ("who wrote line 12"). Contribution stays a count of lines per student, as the README says today.
- Re-enabling the Low typing effort check for team attempts. It compares one student's keystrokes with the whole submitted code, which does not fit shared code.

## What would make this a failure

- Text differs between teammates, or typed characters vanish.
- A student is flagged for a paste or credited for lines a teammate made.
- A teammate ends up with no grade, or a different grade, after the leader submits.
- A team cannot submit because one person is missing.
- A secret (Supabase JWT secret, service key) lands in the repo or in the packaged extension. The repo is public.

## Evidence from the throwaway test

A discarded test bound a real VS Code 1.145.0 document to Yjs and let the editor and a simulated
teammate type and delete at random at the same time. Four rounds (calm, non-stop, human pace
with 60 ms lag, slow network with 120 ms lag): text matched on all sides every time, and the
editor counted exactly its own characters (162/162, 430/430, 126/126, 315/315).

It taught four rules the real build must keep:

1. The shared text uses LF line endings. A CRLF editor never equals it. Shared files are forced to LF.
2. `workspace.applyEdit` returns false when the user typed in the same instant. The edit must be recomputed and retried, never assumed applied.
3. VS Code may split one applied replacement into several smaller changes. An incoming change is recognised as "ours" by the resulting text, not by its shape.
4. A user's own change that arrives while a remote edit is still on its way must be shifted past that remote edit before it is written to the shared text.

Not covered by the test: Supabase Realtime as the carrier (a local relay was used), several
files, cursors, undo, reconnects, and two real people.

## Design

### 1. Leader

- `groups.leader_id` (nullable, user id, null on delete).
- `group_members.leader_vote` (nullable, user id): the teammate this member currently votes for.
- Rules live in `App\Services\LabTeams`, inside `locked()` like every other team change:
  - `leaderOf(Group)`, `setLeader(Group, User, by)`, `voteLeader(Group, voter, candidate)`.
  - The leader choosing a teammate hands over at once. A vote that gives a candidate more than half of the non-leader teammates makes them leader. Either way all votes are cleared.
  - The leader must be a team member. Removing the leader from the team clears `leader_id`.
- Instructor: a "Leader" control per team on the Teams page (`LabTeamController`), usable at any time, including mid-attempt.
- Students: "Change leader" in the extension sidebar and on the lab page team card. It shows who voted for whom.
- Every leader change posts a line in the team chat and notifies the team.
- If a team has no leader when a teammate opens the lab, that teammate becomes leader.

### 2. Team submit (server)

`POST /sessions/{id}/submit` on a session with a `group_id`:

- Refused with `403 not_team_leader` unless the session's student is the team leader. The message names the leader.
- The grading lock is taken per team (`group_id` + lab), not per session, so two submits cannot both grade.
- The submitted files are graded once, exactly as today.
- Then every in-progress session of that team on that lab is closed with the same `status`, `ended_at`, `completed_tasks`, `performance_score`, `ai_grade_summary`, `submitted_code` and `submitted_files`. Each is saved through the model, so the competency hook runs per student.
- One `session_completed` telemetry row per closed session, marked with who submitted.
- A teammate with no session on this lab gets nothing. Monitoring lists them under the team as "Did not take part".
- A student on a group lab who has no team still works and submits alone, as today.
- "Check progress" stays open to every teammate.
- A team attempt has an identity: `lab_sessions.team_started_at`, the moment its first teammate opened the lab, copied onto every teammate's session in that attempt. It is the team's clock on timed self-paced labs (`acceptsWork()` measures from it), and it decides which sessions a submit grades: the same team, lab and `team_started_at`, one per teammate. Earlier team attempts are never touched. (Added after review: without it a submit could overwrite an earlier attempt, and the clock moved when a teammate's session closed.)
- Only the session's own student can have it graded (submit or check progress). A teammate cannot use the leader's session.
- A team attempt cannot be finished from the website (`sessions.complete`); only the leader's submit ends it.
- Check progress locks one session; submit also takes a team-wide lock, so a teammate's check never holds up the leader.
- Grade override (`InstructorMonitoringController::overrideGrade`) gains `apply_to_team` (default true on team sessions).
- An extension older than this release cannot know about leaders. The server-side refusal covers it; the message tells the student to update the extension.

### 3. Shared files

**Model.** One Yjs document per team attempt: a map of file name to shared text. Only code files
(`LabSession::isCodeFile`) inside the lab workspace are shared, with the existing size limits.
Read-only starter files stay read-only. Creating, deleting and renaming a file is shared too.

**Carrying edits.** Supabase Realtime Broadcast, one channel per team attempt. Decided by the
owner on 2026-11-12, replacing the private-channel design first approved: Laravel gives each
team attempt a random, unguessable channel name and a 256-bit key, and hands both only to that
team's members. Every message is encrypted and authenticated with the key (AES-256-GCM) before
it is sent, so someone who learned the channel name can neither read edits nor forge them; the
most they can do is add noise, which is dropped. The extension joins with the project's
publishable key, which is public by design. Nothing new is secret on Vercel, and the production
database gains no access rules. Why not private channels: they need the project's JWT signing
secret on Vercel, which can mint a token for any role including full admin, and a rule on a
Supabase system table in production. They can be added later without changing the rest.

Each client merges its pending edits and sends at most one message per send interval. The
interval is a setting, 400 ms by default: the Free plan counts every send once for the sender
and once per teammate who receives it, against 2 million messages a month.

**Keeping the document.** Laravel stores opaque update blobs, it never decodes them:

- `team_docs(id, group_id, lab_id, team_started_at, topic, key)`: one row per team attempt, holding its channel name and key (the key encrypted at rest). Created once, when the first teammate asks.
- `team_doc_updates(id, team_doc_id, user_id, payload, created_at)`.
- `POST /sessions/{id}/team-doc/updates` appends one. `GET` returns all since an id.
- Any client may post a compacted snapshot that replaces everything up to an id it has applied.
- A teammate who joins first loads the stored updates, then catches up live from peers. A document with no updates starts from the lab's starter files; the first client creates it, guarded by a server-side "create once" so two first joiners do not both seed it.
- On team submit the rows are deleted. The submitted files on each session are the permanent record.

**In the editor (extension).** A new `teamSync` module, separate from `sidebarProvider.ts`,
owns the binding and follows the four rules above. It exposes one thing to the telemetry code:
"this change event was a teammate's edit". `handleDocumentChange`, paste detection, diff
counting and `guardReadonlyFile` ignore those events. Everything else in telemetry is unchanged,
which is what keeps behaviour tracking on each student's own machine.

**Submit.** The leader's extension waits until it has no unsent or unapplied edits, then sends
its files as today.

**Dropped connection.** The student keeps typing; edits merge on reconnect. If Realtime is not
configured at all, a group lab falls back to separate copies with leader submit, and the sidebar
says shared editing is off.

**Packaging.** The extension has no runtime dependencies and no bundler today. This adds `yjs`
and the Supabase Realtime client, so the build gains a bundling step.

### 4. Joining

When a teammate's session starts or reconnects on a team, the team chat gets a system line
("Ana joined"). Leaving or going offline for more than the existing offline window posts one
too. The sidebar lists who is in the lab now and marks the leader.

### 5. Teacher view

On the monitoring page, per team: the current shared files, a diff against the starter files,
the leader, who is in, and who never joined. Per-student stats (typing, pastes, focus, share of
lines) stay where they are. After submit, the results page shows the same diff.

## Edge cases (designer's calls)

- **Another attempt.** Where a lab allows a new attempt, the team starts again from the starter files with an empty document. The earlier attempt keeps its submitted files.
- **Student moved to another team mid-attempt.** `LabTeams::moveOpenAttempts` already moves the open session. The student's extension leaves the old channel and joins the new team's document; their local copy is replaced by the new team's files.
- **Live lab ended by the instructor, or one session ended by hand.** Sessions close as they do today. Stored document updates for a team are deleted once the team has no open session on the lab.
- **One teammate's session reopened by the instructor.** It reopens alone, as a solo attempt would; that student submits it themselves.

## Order of work

Each part is usable on its own and ships with tests, docs and a CHANGELOG entry.

1. **Leader and team submit.** Fixes the grading problem even before shared editing exists.
2. **Shared files.** Transport, binding, storage, fallback.
3. **Teacher diff view and teammate cursors.**

Production migrations are run by hand on Supabase, so each part lists its migration for the owner to apply.

## Limits and risks

- The project is on the Supabase Free plan (read from the organization record, 2026-11-12): 200 connections, 100 messages per second for the whole project, 256 KB per message, 2 million messages a month. A broadcast counts once for the sender and once per receiver (docs, 2026-11-12).
- Estimate, not a measurement: a student typing for 10 of 45 minutes at one send per 400 ms, in a team of three, costs about 4,500 messages a lab; a class of 40 about 180,000. That is about 11 class-sized group labs a month on Free. Pro is 5 million a month, then $2.50 per million.
- At the 100 per second limit, about 40 students can be sending in the same instant at 400 ms.
- The extension needs a WebSocket. Newer VS Code provides one; the bundle carries a fallback for older builds.
- Same character typed at the same spot in the same instant by two teammates: text stays right, one character may be credited to the wrong student.
- Under non-stop machine-speed typing, teammates' edits wait until a pause. At human speed they landed live in the test. Two real people have not tried it.

## Left to the builder

- Exact table and column types, route names, and request shapes beyond what is named here.
- The look of the leader controls, the vote list and the teacher diff, following the existing design system.
- The bundler choice for the extension.
- How teammate cursors are drawn.
