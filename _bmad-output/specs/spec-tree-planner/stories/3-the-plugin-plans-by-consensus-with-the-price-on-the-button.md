---
title: 'The plugin plans with the price on the button'
type: 'feature'
created: '2026-09-27'
status: 'in-progress'
route: 'oneshot'
context: []
---

## Intent

**Problem:** The service plans a tree from a label inventory (story 2), but nothing on the site counts the inventory, shows the price, calls the service or turns its answer into the Folders screen's draft.

**Approach:** The plugin counts every described picture's label into an inventory, prices it with the service's own sum, and puts that one number on the button. The press books a job (fifteen runs can outlast a proxy's sixty seconds) that calls `/v1/plan-tree`; the service sends back every valid tree; the plugin keeps the tightest by the pictures' own vectors, places the labels the model left out, and makes the result the draft with every existing folder kept in place.

## Implementation Notes

- Plugin: `core/plan-tree.php` (inventory, price, job, `/guide/plan`, `vergeml_plan_choose`, `vergeml_plan_draft`), `core/guide.php` (the plan in the session and on the page), `js/vergeml-folders.js` (the button, the poll). Commits 7909ea6, 52bf62e, 235a147.
- Service: fifteen runs, every valid tree returned (0d1f5b8, e7b369d). The price is unchanged at 10 + 6 per 100 labels pending Nathan's pack price.
- Decisions, Nathan 2026-09-27: vector placement at a cosine of 0.5; unfiled target 10 %; fifteen runs with the tightest kept. Measurements: `lab-results.md`, 2026-09-27.
- The first real plan (5 runs, most-agreed, before this story's changes) scored 27/47, purity 70 %, 31 % unfiled on the shop; 38 credits.

## Review Triage Log (2026-09-27, the fifteen-run change, both repos)

- medium, patched: `vergeml_plan_choose` and placement had no test -- `tests/tree/plan-choose.php`, 8 rows, registered as `plan-choose`; flipping the comparison turns rows 1, 2, 3 and 5 red.
- medium, patched: a re-embed in progress could sum vectors of two sizes -- only the library's most common `embedding_dims` is summed.
- medium, patched: SPEC CAP-1 and CAP-5, rules.md and stories.yaml still said five runs, most-agreed, 20 % unfiled -- updated to what ships; lab-results.md carries the measurements.
- low, patched: the service test and the lab's usage line still said "five".
- decision, open: the price at fifteen runs (three times the model cost) -- Nathan, once the pack price is known.
- false: fifteen trees for 3,000 labels exceed a response limit -- about 300 KB, under Vercel's 4.5 MB.
- low, rejected: the three vector helpers duplicate `vergeml_filing_cosine` -- that takes norms separately; the shapes differ.
- low, rejected: the library may change during the call -- vectors are read by label text after it; a picture described mid-plan shifts a centre by one picture.
- low, rejected: `kept` and `placed` are not shown on screen -- no copy was asked for.
- low, rejected: 0.5 is fixed for every site -- tuned on both truth sites; the proof runs check it.

## Proof (shop, 2026-09-27, service e7b369d, plugin 235a147)

Four real plans through what the job runs (charged call, `vergeml_plan_choose`, draft, fit); scored with `tools/tree-lab.mjs assign`.

| Plan | Runs valid | Recovered | Purity | Unfiled (plan) | Unfiled after the matcher's dry run |
|---|---|---|---|---|---|
| 1 | 15/15 | 39/47 | 75 % | 12 % | -- |
| 2 | 9/15 | 39/47 | 77 % | 14 % | 22 % (133 margin, 4 floor) |
| 3 | fewer than 3 | failed, refunded | | | |
| 4 | 15/15 | 39/47 | 75 % | 12 % | 17 % (105 margin, 1 floor) |

Agreement between plans: 69, 79 and 68 %.

Against the bars: recovered ≥ 35 met; purity ≥ 75 % met; unfiled ≤ 10 % not met (12-14 % in the plan, 17-22 % after the matcher); agreement ≥ 80 % not met.

- Runs dropped when plans ran back to back: the service's client times a call out at 40 s with one retry, and a run takes 18-26 s unloaded. Fifteen calls straight to OpenRouter all answered (26 s). Fix: a longer timeout for the planner's calls, inside the route's 300 s.
- The matcher leaves more unfiled than the plan: it re-decides each picture from the draft's class words, and 105-133 pictures fall between two folders. The plan already knows each label's folder; filing by that is CAP-2/CAP-3 (stories 4 and 5).
- Tech not yet run. Credits spent on the shop proof: 114 (plan 3 refunded).
