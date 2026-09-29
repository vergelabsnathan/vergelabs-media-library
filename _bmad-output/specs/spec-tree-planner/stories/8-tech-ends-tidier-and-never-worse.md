---
title: 'Tech ends tidier than it started, and never worse'
type: 'feature'
created: '2026-09-29'
status: 'done'
baseline_commit: '7b91412'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

## Intent

**Problem:** 9/11 at 78 % on tech is out of reach at 1,000 pictures (the oracle plan gives 9/11 at 71 %), and a plan could leave a site less tidy than it was.

**Approach:** After the plan and before the draft is offered, measure the pictures already in a folder (To sort left out) as mean cosine to their folder's centre, now and after the fill. Under a gain of 0.02 (`VERGEML_PLAN_GAIN`) the draft is not set and the job ends `kept` with Nathan's message: "Your folders are already well organised — a new plan wouldn't improve them."

**Bar (Nathan, 2026-09-29):** tech after the fill ≥ 75 % purity and never below its own before (67 %); the shop at its CAP-1 bar.

## Proof (2026-09-29, box, commit 96e04d9)

- Free replay of ten saved plans: before 0.693; the five folder-name plans 0.645–0.696, none offered; yesterday's five 0.701–0.736, three offered.
- Tech before: purity 67 %, 7/11, unfiled 4 %.
- Tech plan 1: guard 0.693 → 0.718 offered; after the fill purity 75 %, 8/11, unfiled 0 %. 51 credits.
- Tech plan 2: guard 0.693 → 0.730 offered; purity 76 %, 7/11, unfiled 0 %. 51 credits.
- Shop from scratch (tree cleared, snapshot restored after): 41/47, purity 75 %, unfiled 9 %. 38 credits.
- Shop on its existing 125-folder tree (34/47, 76 %): guard 0.823 → 0.757, kept; 38 credits charged. The measure favours many small folders; fixed with the charge-on-refusal story.
- Suites: plan-gain 5/5, plan-choose, plan-fold pass.
