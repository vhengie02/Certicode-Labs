# Changelog

## 2026-11-05

- VS Code extension 1.8.1 is out. It stops the false paste flag when a lab's starter files open, and sends up to 10,000 characters of pasted text so instructors can read the whole paste.
- The landing and sign-in pages have a new animated ASCII background, and the extension screenshots on the landing page are up to date.

## 2026-11-04

- The top navigation moved into a new animated Menu button at the top right. Dashboard, Classes, the admin pages, Settings and Sign out all live there now, and it shows who you are signed in as. It works the same on phones, closes with Esc or a click outside, and respects the "reduce motion" setting.
- Settings has new sections. Date and time sets a time zone and date format for lab times and class end dates. Active sessions lists signed-in browsers and can sign out the others. Your data explains what Certicode records and downloads it as a JSON file. Delete account erases an account, and students with certificates or group labs keep their name and grades so those records still work.
- Email notifications add an "All alert emails" switch, a Grades toggle for students, and opt-in camera flag emails for instructors.
- Success, warning and error messages now pop up as notifications in the corner instead of banners across the top of the page. Errors stay until you close them; the rest fade on their own. The "waiting for instructor approval" reminder shows once per session instead of on every page.
- Opening a lab's starter files no longer raises a false paste flag, on any extension version. The check missed starter code on Windows because of its line endings, and when VS Code had no folder open.
- Instructors can read the whole pasted text on a paste flag, up to 10,000 characters, with "See all" on longer pastes. If only part was saved, the flag says how much. Older extension builds save only the first 100 characters.
- Notifications show their full title and message instead of cutting them off.
- Read notifications can now be deleted from the bell: tick the ones to remove, or "Select all read" to clear every read notification, including older ones the list doesn't show. Unread ones stay until they're read.
- The notification panel now animates: it opens and closes smoothly, new notifications slide in, and deleted ones collapse away. This respects the "reduce motion" system setting.

## 2026-11-01

- Reloading the VS Code window no longer kicks a student out of their lab. Extension 1.8.0 reconnects to the same attempt by itself, and the lab page waits a few seconds instead of leaving. Students on older builds still leave the lab when VS Code closes, about 30 seconds later than before.
- Instructors now get an in-app notification when the camera flags a student, even with the monitoring page closed. Only the first camera flag in a lab after 15 quiet minutes notifies, so a noisy camera doesn't flood the bell.
- The lab monitoring list marks students running an extension older than the minimum version with an "Old ext" badge.
- The monitoring page stops asking the server for updates while it's in a background tab and catches up when you come back to it.
- Extension 1.8.0 ships with this release. It also includes the "update the Certicode extension" message for builds the server no longer accepts.

## 2026-10-27

- Students now get a results page when a lab attempt ends. It shows their grade, the lab grade that counts (their best attempt), which tasks they finished with the grader's feedback on each, and their stats: time spent, typing speed, lines changed, focus lost and flagged pastes. The lab page opens it as soon as the attempt is submitted, and lists every past attempt with its grade.
- When an instructor regrades a lab, the notification now opens that results page. It shows the old and new grade, who changed it and when, and the full reason. Earlier regrades of the same attempt are listed too. Saving the same grade (to confirm a provisional one) now says so instead of "changed from 70% to 70%".
- Instructors can now set up teams for a group lab: the new Teams page (from the lab page) creates teams, picks members from the class and renames or deletes them. Students start their attempt in their team, see it on the lab page, and work alone until they have one. Moving a student between teams moves their attempt in progress too.
- Group labs: team chat now reaches every teammate, and each student's contribution share is counted against the whole team's lines instead of always showing 100%. The lab page's added/removed line counts no longer stay at 0, and the leaderboard highlights your team's row even when a teammate's attempt is the one shown.
- Solo labs no longer show a Chat tab in VS Code, and the server refuses chat for them.
- Task checks are easier to set up: in the lab form, "Add an automatic check" lets you pick "contain this text" or "match this pattern" instead of typing a `regex:` prefix, and flags a pattern that isn't valid. Plain-text checks are now enforced by the grader too (ignoring case and spacing), not just patterns.
- Extension builds the server no longer supports are refused with a clear "update the Certicode extension" message in VS Code, with a download button, instead of failing to sync. The lab page also suggests updating when VS Code opens but never connects.

## 2026-10-19

- Module files up to 20 MB now upload and download reliably on the live site. They are kept in cloud storage instead of disappearing.
- A lab's grade is final once the attempt ends. Leaving a lab no longer submits it ungraded: the attempt stays open to resume and submit.
- Fewer false integrity flags: normal snippets and quick fixes no longer count as pastes, and keeping the lab page behind VS Code no longer counts as tab switching. Valid code no longer fails the syntax check (long numbers, Python comments).
- Sign-in and Gmail linking are harder to abuse, lesson content is cleaned of scripts when saved, and a class with certificates or labs can't be deleted by accident.
- Extension 1.7.1 ships with this release: each lab gets its own starter files, and Check/Submit stay disabled while grading runs. Students on 1.6.0 can keep using it, but should update.

## 2026-10-18

- Instructors can sort the class integrity flags table by any column and resolve several flags at once.
- Resolved flags older than 90 days are deleted automatically. Open flags are always kept.
- Shadows, posters and people in the background no longer raise "multiple faces" flags.
- After submitting a lab, the VS Code sidebar shows time spent, score, tasks and typing speed, plus the student's place in a live lab. The sidebar also shows which version of the Certicode extension is installed. Extension 1.7.0 ships with this release. Students on 1.6.0 can keep using it.

## 2026-10-16

- Instructors get a redesigned student monitoring dashboard: one compact row per student, filters by status, sorting by who needs attention, and an "Open flags" count.
- Webcam snapshots from camera flags now load on the live site. Only the class's instructor can open them, and a new flag's live alert shows the student, the flag type and the picture.
- An unplugged or failing camera raises one flag instead of a new one every 10 seconds.
- Closing VS Code during a lab now turns off the webcam and takes the student back to the lab page. Their attempt stays open, so they can pick up where they left off.
- Students with an outdated Certicode extension see an update banner on the lab page and a warning in VS Code. Extension 1.6.0 ships with this release.
