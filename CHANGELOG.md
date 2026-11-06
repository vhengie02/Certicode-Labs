# Changelog

## 2026-11-06

- The camera check now finds faces in much darker rooms. The old brightening step pushed dark faces back to black, so a student in a dim room could be flagged "No face" while sitting at the screen. A face in near-total shadow can still be missed. During a lab, a student in a dark room now sees "Too Dark, Add Light" instead of "Face Searching", so they know how to clear the warning before it reaches 4/4.
- A dark room with movement on camera now raises a softer flag. When no face can be found but the picture keeps moving, instructors see a low-severity "Too dark" flag with the picture instead of "No face", and it doesn't send a notification. A still dark room keeps the "No face" flag. A moving curtain or TV in an empty room can also raise "Too dark", so the picture is there to check.

## 2026-11-05

- VS Code extension 1.8.6 adds a "Large insert" flag. It appears when 100 or more characters of code reach a file without being typed or pasted, such as from an AI tool. An answer written in parts within a few seconds counts as one insert. Instructors can read the code in the session timeline. Generated code and large snippets can also raise it, so it is medium severity at most, and it doesn't change a grade. The Your data section in Settings now mentions it. Older extension builds don't send this flag.
- The "Low typing effort" flag now covers short labs. It used to skip submissions with under 300 characters of new code, so a short answer written by an AI tool with no typing behind it raised no flag. The limit is now 100 characters. The flag still doesn't change a grade.
- The camera check now always needs a detected face. The "Continue in low-light mode" button is gone, because it let an empty dim room pass. A dim room gets a stronger check on a brightened picture, and if no face is found the student is asked to add light and retry. During a lab, a dim room no longer counts as present without a face, so students in very dark rooms may see more absence warnings. Faces too small to be someone at the screen no longer count.
- Three failed camera checks in a row now lock that lab for 15 minutes. The retry count used to run past its limit, such as 4/3, and reloading the page reset it. The server now keeps the count. A lockout flags the student's open attempt with the last picture, or notifies the instructor if there is no attempt yet.
- The Glyphs and Diamonds backgrounds are bolder in light mode. Glyphs is now a terracotta and teal block print on warm sand, and Diamonds is crimson on rose with gold gems.
- A class's page now shows its card background behind the title and key facts, with the same fade as the card.
- Class cards now have pattern backgrounds. Instructors choose one of eight patterns, such as contour lines, moons and stars, or waves, when creating or editing a class, or upload their own picture. Classes without a choice get a pattern automatically. Every pattern has its own colours for light and dark mode, and backgrounds fade out before the text starts. Large pictures are resized before they upload, which also removes their location data.
- Pop-up notifications now wait for the page to finish loading, slide in from the right edge of the screen and slide back out after 3 seconds, or longer for a long message. Error pop-ups still stay until closed. With the "reduce motion" system setting they fade instead.
- VS Code extension 1.8.5 no longer flags a student for moving their own code. Text copied or cut from the same file or another open lab file is not reported as a paste. Code from any other file still is.
- Instructors can now write a rubric on a lab. The AI grader reads it, and students never see it.
- The landing page, sign-in pages and READMEs now describe the product accurately: live and self-paced labs, how certificates are issued, what a paste is compared with, team sizes and instructor approval. The READMEs also name the right AI key and provider.
- Confirmations now open a pop-up in the app's own style instead of the browser's alert box. This covers deleting classes, modules, labs, teams, competencies and accounts, and ending classes, live labs and student sessions. Each pop-up names what it affects, and Cancel is selected first.
- Low-light mode no longer passes a blocked camera. A black or blank picture now fails the pre-lab camera check with a message to uncover the camera or turn on a light, and the low-light option only appears in a dim room. During a lab, the status shows "Camera Blocked", and the warning count stops at 4/4.
- The lab page now leaves camera mode about 12 to 17 seconds after VS Code closes, down from about 40. A window reload that takes longer than 12 seconds now counts as closed: the attempt stays open, and the student starts again from the lab page.
- VS Code extension 1.8.3 opens the starter file under its real name, such as `Strings.java`, when no folder is open. It used to open as "Untitled-1", and a new copy opened on every session sync. The minimum extension version is now 1.8.3, so students on older builds see the update message and instructors see the "Old ext" badge. Those builds still connect.
- Instructors can now read a student's submitted files. A Submitted files section on the attempt's results page lists every file, and the monitoring page links to it from each student. Code that was saved but never submitted is marked as possibly not final. The similarity Compare view now shows each student's full code instead of the first 250 characters.
- The similarity check now compares all of a student's submitted code files, not only the main one, so a copied helper file is caught. Notes and data files like .md, .txt and .json don't count. Existing results update on the next submit or when you click Compare again.
- VS Code extension 1.8.4 also sends code files a student adds or changes in the lab folder during an attempt, so instructors can read them and the similarity check compares them. Older files in the folder, and notes, data and config files, stay on the student's computer. The Your data section in Settings now mentions them.
- The grader now reads all of a submission's code files, not only the main one, so work done in a helper file counts. A file the rest of the code doesn't use doesn't count against the student. If a submission is over 100,000 characters, the files that don't fit are left out and named in the feedback. Existing grades don't change.
- The dashboard's Get started card is now a step-by-step guide. It opens on the first step you haven't done, you can move through the steps with Back and Continue or by clicking a step, and each step is still ticked off by what you actually do. This respects the "reduce motion" system setting.
- The grade on a lab's results page and the "Your progress" percentage on a class page now count up from 0 when the page opens, and their bars, now drawn as a row of small bars, fill up alongside. This respects the "reduce motion" system setting.
- VS Code extension 1.8.1 is out. It stops the false paste flag when a lab's starter files open, and sends up to 10,000 characters of pasted text so instructors can read the whole paste.
- The landing and sign-in pages have a new animated ASCII background, and the extension screenshots on the landing page are up to date.
- The class syllabus shows the date each module and lab was created, in the date format and time zone set in Settings.
- Open labs can have an optional due date. On the syllabus, students see "Due in" before it and "Late by" after it until the lab is done. Late work is still accepted. Live labs show "Locked" until the instructor starts them and "Ongoing" while they run.
- Clicking a section in the Settings menu now scrolls smoothly to it instead of jumping. This respects the "reduce motion" system setting.
- Instructors get three new integrity flags. "Low typing effort" appears when a submission is much larger than what was typed or flagged as pasted. "External file change" appears when a lab file changes outside the editor. "Starter files kept" appears when a lab starts from existing files instead of the starter code. These are prompts to look closer: none of them changes a grade, and group labs skip the typing check.
- VS Code extension 1.8.2 sends these signals, tightens how team chat excuses a paste, and sends the latest typing and diff figures just before a submit.
- Keystroke counts no longer drop back to zero when a student reloads VS Code during a lab.
- Submissions over 100,000 characters or 50 files are now refused with a clear message, and the attempt stays open. A long list of editor errors is shortened for the grader instead of failing the submission.
- The pre-lab camera check now needs a real camera image. A check that sends no image or no face count no longer passes.
- Certificates now depend on grades. A lab counts only when a student reaches the class's new Lab pass score, 75% by default and set in the class settings, and the passing threshold is now the share of labs a student must pass. Grades waiting for an instructor's review don't count until the instructor confirms them. The class page shows labs passed instead of labs done. Certificates already issued stay valid.
- Global search now plays a scramble animation while it loads, then decodes each result into place. It shows a message if the search fails, and respects the "reduce motion" system setting.

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
