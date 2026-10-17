# Changelog

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
