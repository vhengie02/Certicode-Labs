# Shared typing check

Runs the shared-typing code in a real VS Code window: one student types in real editors, a
teammate types into the same two files from the same process, at the same time. The check passes
when every copy of every file ends up the same, line endings are LF, the characters counted as the
student's are exactly the ones they typed, and a teammate who joins afterwards loads the same files.

```bash
npm run compile
npm run sync-check
```

Opens and closes its own VS Code window, with its own settings and extensions folders.

- `npm run sync-check -- --break` counts the teammate's edits as the student's. It must fail; it
  shows the check can catch that mistake.
- With `SYNC_CHECK_SUPABASE_URL` and `SYNC_CHECK_SUPABASE_KEY` (the project URL and its
  publishable key) set, edits travel over the live Supabase Realtime channel instead of a local
  relay. It uses a random channel name and key, and sends only test text. Each run costs a few
  hundred Realtime messages.

Not shipped with the extension (`.vscodeignore` excludes `scripts/**`).

Set `SYNC_CHECK_ONLY` to one round name (`burst`, `human`, `laggy`) to run only that round.
