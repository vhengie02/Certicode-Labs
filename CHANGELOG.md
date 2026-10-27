# Changelog

## 2026-10-27

- Students now get a results page when a lab attempt ends. It shows their grade, the lab grade that counts (their best attempt), which tasks they finished with the grader's feedback on each, and their stats: time spent, typing speed, lines changed, focus lost and flagged pastes. The lab page opens it as soon as the attempt is submitted, and lists every past attempt with its grade.
- When an instructor regrades a lab, the notification now opens that results page. It shows the old and new grade, who changed it and when, and the full reason. Earlier regrades of the same attempt are listed too. Saving the same grade (to confirm a provisional one) now says so instead of "changed from 70% to 70%".

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
