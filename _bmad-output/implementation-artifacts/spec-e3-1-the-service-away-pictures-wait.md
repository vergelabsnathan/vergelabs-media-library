---
title: "E3-1: while the service is away, pictures wait — nothing is marked for a failure that is not the file's"
type: 'defect'
created: '2026-09-22'
status: 'in-progress'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: 'plugin ecc140e · service 4fe432e'
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-3-retro-2026-09-21.md'
  - '{project-root}/_bmad-output/implementation-artifacts/spec-4-0-3-retro-fixes.md'
  - '{project-root}/docs/handoffs/2026-09-21-s32-4-0-3-retro-fixes.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A describe run marks pictures as failed for failures that are the service's, not the file's. (1) A service that cannot be reached at all (`http_request_failed` on the sequential path, `vergeml_ai_transport` on the parallel one) is not in the transient set (`core/ai.php:1569-1570`), so every picture the run gets to is stubbed on the first miss and the run marches on. (2) A 5xx/429 is held twice and stubbed on the third strike; the stub is skipped by `unindexed` for good. (3) A stub written during a *Re-describe* (`stale`) run is merged onto the picture's described row (`ai-index.php:390`), and every reader filters `error = ''`: the picture leaves search, filing and the counts. (4) A held picture is reported twice in the step's `errors` (`:1541`, `:1584`). (5) A run in the background whose remaining pictures are all held reschedules itself *now* and nudges (`ai-background.php:420-430`): a spin for the length of the hold. Six agencies are about to run fills on client libraries; Nathan's go 2026-09-22 ("go then"), 4.0.4 the same day.

**Approach:** One notion, `vergeml_ai_transient( WP_Error )`: the service did not answer for this picture — no answer at all (`http_request_failed`, `vergeml_ai_transport`, `vergeml_ai_no_answer`) or a temporary status (0/408/425/429/500/502/503/504). A transient answer holds the picture for ten minutes and writes nothing, every time — no strikes, no stub: the picture is offered again when the hold lapses, for as long as the service is away. Four transients in a row still end the step. A non-transient answer for a picture that already holds a description keeps the description: the row's `prompt_hash` is set to the current stamp so the stale sweep moves on, `error` stays empty, the failure is in the run's report only. A held picture is reported once. A background tick that held everything books its next pass after the hold and does not nudge. A seam — `vergeml_ai_describe_answers` filter at the top of `vergeml_ai_describe_many()` — so a suite can hand the step a parallel-path answer.

## Boundaries & Constraints

**Always:** `core/ai.php`, `core/ai-background.php`, `tests/ai/outage.php`, `readme.txt` + `service/docs/manual/credits.md` + `service/docs/runbooks/incident.md` (the paragraph, Nathan's words applied as proposed on his "go"), the 4.0.4 train after the review. Proof: `ai-outage` in Playground on every matrix row; `ai` and `ai-background` on the box (mock, nothing spent); Plugin Check 0 errors. Say what goes out before the push.

**Never:** ms2 or the real shop; a real describe; a schema change; a change to the duplicate path (`vergeml_ai_duplicate` keeps its three strikes: the service's own ten-minute window); a change to the fatal set.

## I/O & Edge-Case Matrix

| Case | Today | After | Proof |
|---|---|---|---|
| unreachable (`http_request_failed`), unindexed | stubbed first miss, run marches on | held, no row, reported once, non-fatal; still in `unindexed` | outage step 1 |
| unreachable on the parallel path (`vergeml_ai_transport`, through the seam) | stubbed first miss | held, no row | outage step 2 |
| 503 three times running | held, held, stubbed | held every time, no row, strikes untouched | outage steps 3–5 |
| four transients in one step | step breaks | unchanged | outage step 6 (four pictures, one call each, then the break: the fifth not asked) |
| 400 (a file the service refuses) | stubbed first answer | unchanged | outage step 7 |
| a described picture, `stale` run, 400 | described row overwritten with the error; gone from search | description kept, `prompt_hash` = current stamp, `error` empty, out of `stale`, reported | outage step 8 |
| the service back, `unindexed` step after the hold lapses | held pictures were stubbed, not offered | A, B, E described, alt written | outage step 9 |
| a marked picture (400) with no alt | in `missing-alt` | unchanged | outage step 10 |
| a background run, service unreachable | tick holds all, reschedules now, nudges | the next pass booked ≥ hold − 5 s away, no nudge, run active | outage step 11 (`run_start`, `run_tick`, `wp_next_scheduled`) |
| the run's report | a held picture twice | once | outage steps 1, 3 |
| a 5xx that is the *file's* (the service errors on one picture for ever) | stubbed after three strikes | held every pass while the run is active; costs nothing (a failed call is not charged) | reasoning; recorded in Decisions |

</frozen-after-approval>

## Copy (applied on "go then", 2026-09-22 — Nathan's to change)

`readme.txt` FAQ "What happens when the AI service is down?"; `credits.md` after its bold lead-in; `incident.md` "ai relay":

> Your plugin keeps working without the AI features: filing, search and everything already described keep working, and no credits are taken for a picture that was not described. A picture the service could not answer for — a temporary error, or no answer at all — is set aside and tried again: a run in the background comes back to it ten minutes later, a run you are watching leaves it for your next press. A picture is marked as failed only when the service answers that the file itself cannot be described. Searching by meaning falls back to the ordinary word search, and your credit balance shows the last number it read until the service answers again.

The 4.0.4 changelog line: *"While the AI service is away, pictures wait instead of being marked as failed; a re-describe that fails keeps the description the picture already had."*

## Tasks & Acceptance

- [ ] T1 `core/ai.php`: `vergeml_ai_transient()`; the step's error branch: transient → hold + report once + streak, no strikes, no stub; non-transient on a described row → keep the description, stamp it current; the seam in `describe_many()`. `core/ai-background.php`: the tail books after the hold when the last step held everything, no nudge.
- [ ] T2 `tests/ai/outage.php` rewritten to the matrix (11 steps), registered as before. Red on the 4.0.3 bytes at steps 1, 2, 5, 8, 11; green after.
- [ ] T3 the three documents; readme 4.0.4 block; version; the train.

**Acceptance Criteria:** `node tools/verify.mjs ai-outage` → N/N with one line per matrix row; the box `ai` and `ai-background` suites unchanged in outcome; health `free 4.0.4`.

## Decisions taken

- **No strikes for a transient.** Three strikes turned a ten-minute outage into stubs; a picture the service did not answer for is not a failing file, and a stub for it is bookkeeping the readers treat as failure. The streak (four in a row ends the step) is what protects the run from marching through a library during an outage; the hold is what protects the credits. A service that errors on *one* picture for ever is now retried every hold while the run is active — it costs nothing, and it is the service's bug to fix, not the customer's library to mark.
- **A description is never overwritten by a failure.** The stale sweep exists to improve; when it cannot, the old answer stands and the row is stamped current so the sweep moves on. The next prompt change offers it again.
- **After the hold, not now.** A tick that held everything has nothing to do for ten minutes; booking it now and nudging is a request loop for the length of the outage.

## Implementation Notes

_(at build)_

## Review Triage Log

_(at review)_
