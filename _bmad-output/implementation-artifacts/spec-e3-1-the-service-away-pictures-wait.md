---
title: "E3-1: while the service is away, pictures wait — nothing is marked for a failure that is not the file's"
type: 'defect'
created: '2026-09-22'
status: 'done'
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
| five transients in one step | the step breaks after four and drops the answers already bought | every answer in hand is read and held; the *caller* waits (the tick and the screen stop asking when a step held everything) | outage step 6 (five asked as one batch, five held, none dropped) |
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

- [x] T1 `core/ai.php`: `vergeml_ai_transient()`; the step's error branch: transient → hold + report once + streak, no strikes, no stub; non-transient on a described row → keep the description, stamp it current; the seam in `describe_many()`. `core/ai-background.php`: the tail books after the hold when the last step held everything, no nudge.
- [x] T2 `tests/ai/outage.php` rewritten to the matrix (11 steps), registered as before. Red on the 4.0.3 bytes at steps 1, 2, 5, 8, 11; green after.
- [x] T3 the three documents; readme 4.0.4 block; version; the train.

**Acceptance Criteria:** `node tools/verify.mjs ai-outage` → N/N with one line per matrix row; the box `ai` and `ai-background` suites unchanged in outcome; health `free 4.0.4`.

## Decisions taken

- **No strikes for a transient.** Three strikes turned a ten-minute outage into stubs; a picture the service did not answer for is not a failing file, and a stub for it is bookkeeping the readers treat as failure. The streak (four in a row ends the step) is what protects the run from marching through a library during an outage; the hold is what protects the credits. A service that errors on *one* picture for ever is now retried every hold while the run is active — it costs nothing, and it is the service's bug to fix, not the customer's library to mark.
- **A description is never overwritten by a failure.** The stale sweep exists to improve; when it cannot, the old answer stands and the row is stamped current so the sweep moves on. The next prompt change offers it again.
- **After the hold, not now.** A tick that held everything has nothing to do for ten minutes; booking it now and nudging is a request loop for the length of the outage.

## Implementation Notes

- **Proof, final bytes:** `ai-outage` **42/42** in Playground (12 steps: both transports held; 503 ×3 held with no strikes; five in a batch all read and held; a 400 marks and is held off the scopes for ten minutes; a refused re-describe keeps its description, its `described_at`, and is stamped `p2`; a demo row the service refuses is marked, never stamped real; the service back → six described in one step; a marked picture in `missing-alt`; a waiting tick books +605 s with no nudge and counts no failure; the ordinary tail books now and chases). Red under the hand mutation "transport not transient": 8 checks. The 4.0.3 bytes cannot run the suite (the helper is missing — the guard says so).
- **Box, real MySQL, real service:** `ai` **11/11** (3 described, 0 failed, 1,000 indexed); `ai-background` **37/37** on a clean library. Spent: 9 credits on the box licence — the `ai` UI suite describes its three pictures in demo mode and leaves mock rows on three real pictures, which made G1 of `ai-background` red twice until they were described for real again (`deferred-work.md`, suite hygiene, pre-existing).
- **Stale scope in Playground:** `vergeml_index_stale`'s `UTC_TIMESTAMP() - INTERVAL n SECOND` is MySQL-only; SQLite refuses it, so step 8 reaches the described picture through `missing-alt`; the branch is the same. The box's suites run the scope.
- **Copy written this session, on Nathan's "go then":** the paragraph (spec Copy block) in the three documents; the three `= Fixed =` lines under the 4.0.4 strapline; the screen's line when a step held everything — *"%1$s described · %2$s set aside — the service did not answer. Try again in ten minutes."*; the early-return notice — *"These files were sent in the last few minutes and are not being sent again yet. If the service was away, try again in ten minutes; if they still look out of date after that, please report it."* All his to change.
- **Decided at review, beyond the spec's Decisions:** no break on a streak — every answer in hand is read (the old break threw away paid answers beside four timeouts); the *tick* and the *screen* stop asking when a step held everything they asked; a refusal (stub or keep) puts the picture on the hold too, so `missing-alt`/`page-gap` do not re-ask it every step; the keep-branch never stamps a mock row, never writes an empty hash, and leaves `described_at` alone; any 5xx and `http_request_not_executed` are transient; `run_start` rebooks now over a ten-minute booking; the waiting pass is booked five seconds past the hold; a `vergeml_ai_run_budget` filter for the suite.
- **Accepted, recorded:** a picture the service errors on for ever is retried every hold while the run is active (costs nothing; the run screen shows the next pass due); a duplicate (409) keeps its three strikes and its stub — the readme's "only when the service answers that the file itself cannot be described" reads a duplicate as such; "comes back to it ten minutes later" is when cron fires — on an idle site the next request runs it.

## Review Triage Log

2026-09-22, `bmad-code-review`, four lenses over `ecc140e..031cd50` + service `4fe432e..`: blind 11, edge 15, verification gap 2 + 5, acceptance 11. Grouped: **18 patched, 4 accepted/recorded, 3 rejected.**

| # | Finding | Verdict | Route |
|---|---|---|---|
| 1 | the streak's `break` stops reading answers already bought in the batch; on the parallel path twelve paid captions beside four timeouts are dropped and come back as 409s (blind, edge, VG, acceptance) | high | patch: no break; every answer read; the tick and the screen stop asking when a step held everything |
| 2 | "four in a row" was "four in the step" — never reset on success (blind) | — | moot with 1 |
| 3 | the screen keeps stepping through an outage and prints "N failed" for held pictures; the run's `failed_ids` counts them (blind, edge, VG) | medium | patch: `held` on the error entry; the screen stops with "set aside — the service did not answer"; `failed` skips held |
| 4 | `$waiting` judged on the last step only: a budget that runs out after a step that held four of sixteen books now and chases (blind, edge, acceptance) | medium | patch: judged over the tick; a step that held everything ends the tick |
| 5 | a run started while a ten-minute pass sits booked waits for it (edge) | medium | patch: `run_start` unschedules first |
| 6 | the tick at the booked second finds the hold still standing (edge) | low | patch: +5 s |
| 7 | refused pictures never held: `missing-alt`/`page-gap` re-ask a 400 picture every step; the keep-branch adds the described-no-alt case (blind, edge) | medium | patch: the hold after either write; suite steps 7b, 8b |
| 8 | the keep-branch stamps a mock row real, writes an empty hash, bumps `described_at` (blind, edge, VG, acceptance) | medium | patch: never mock, hash only when the stamp has one, `described_at` untouched; suite step 8m |
| 9 | 501/505–530 and `http_request_not_executed` stub the picture (edge) | medium | patch: any 5xx, the blocked-outbound code |
| 10 | a run that only ever holds never ends (blind, edge) | low | accept: the service's bug, costs nothing, the run screen shows the next pass; recorded |
| 11 | the ordinary tail (book now, chase) is pinned by nothing after the change (VG) | medium | patch: `vergeml_ai_run_budget` seam; suite step 12 |
| 12 | step 11's "no nudge" not observed (VG, acceptance) | medium | patch: `vergeml_ai_run_should_nudge` counted; asserted 0 for the waiting tick, 1 for the ordinary one |
| 13 | matrix row 6 said "the fifth not asked"; the batch asks all (VG, acceptance) | — | patch (the row, and the code now reads all five) |
| 14 | matrix row 8 proven through `missing-alt`, not `stale` (acceptance) | low | accept: SQLite cannot run the stale query; recorded in the notes |
| 15 | the duplicate path still stubs a described row on the third 409; the readme sentence is broader (VG) | low | accept: the spec's Never; recorded |
| 16 | incident.md's sentence deviated from the block; readme used `--` for the dash (acceptance) | low | patch: the block verbatim, the em dash |
| 17 | the changelog bullets and two screen strings are the session's words (acceptance) | — | recorded in the notes for Nathan |
| 18 | docblock order (the helper sat under describe_many's docblock), "Same three strikes as a transient", the suite header's "six", verify.mjs's registration comment (blind, VG) | low | patch |
| 19 | T2's red claim on the 4.0.3 bytes: the guard exits first (acceptance) | low | patch: the notes say so; the mutation is the red |
| 20 | the early-return notice ("described in the last few minutes … report it") is wrong for an outage (blind) | low | patch: the notice covers the service being away |
| 21 | a per-picture cap beyond the hold (edge, "deletion") | low | reject: that cap was the strikes, and the strikes marked a ten-minute outage as broken files |
| 22 | the nine PNGs are byte-identical: a hashed library would fill from a twin (blind) | low | patch: said in the header (Playground has no scan) |
| 23 | `doing_cron` transient not cleared (blind) | low | reject: 60 s, core's own |
| 24 | the deferred rows S32 opened not closed (acceptance) | — | patch: four rows resolved |
| 25 | "ten minutes later" is a clock promise cron cannot keep on an idle site (blind) | low | accept: recorded; the wording is Nathan's |

