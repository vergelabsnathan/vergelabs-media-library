# Security findings

Phase 5.9 of `plans/four-yesses.md`. One row per finding from the independent
review briefed in `docs/security-brief.md`. Every finding gets its own task
with a proof and a commit that names it; a row is `closed` only when the
reviewer agrees it is, and their word is quoted in the row.

Brief sent: not yet (the date is in `docs/security-brief.md`).

## The shape

| column | holds |
|---|---|
| finding | what the reviewer found, in one line, with the file and line or the route |
| status | `open` — reported, not yet worked; `fixing` — a task exists, in `plans/four-yesses/phase-5.md` S4; `fixed` — the commit is in, the reviewer has not yet said; `closed` — the reviewer agrees it is closed, quoted |
| task | the task that owns it (`5.9.<n>` in the phase plan) |
| commit | the hash of the commit that names the finding, once there is one |

Severity is the reviewer's to say, in the finding's text, and is not a column
here because it changes nothing about what happens to the row: every one is
worked.

## The rows

| finding | status | task | commit |
|---|---|---|---|

None yet: the brief has not gone.
