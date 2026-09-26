---
title: 'The service plans a tree from a label inventory'
type: 'feature'
created: '2026-09-26'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** No service route turns a library's labels into a tree. The lab's bottom-up planner exists only as a script.

**Approach:** A new `/v1/plan-tree` route takes the distinct labels with their counts (and audience counts where known) and asks Sonnet 5 five times at once, with thinking off and structured output. The rules from rules.md run in code on each answer, the answer the others agree with most is kept, and it comes back as folders with their labels and picture counts. The route charges `planPrice(labels)` = 10 + ⌈6 × labels / 100⌉ once and refunds it when fewer than 3 answers are usable.

</frozen-after-approval>

## Implementation Notes

- Decision: the consensus, the rules and the charge live in the service, not in the plugin. That means one request, one charge counted from the label count here (never trusted from the site), a refund on failure, and one tested copy of the rules. This moves story 3's five runs and rules into this story; story 3 keeps the inventory, the button and the draft.
- Files (service worktree `wt/tree-planner-service`, branch `tree-planner`): `lib/plan-tree.ts`, `lib/plan-tree.test.ts` (12 cases), `app/api/ai/plan-tree/route.ts`, `next.config.mjs` (the /v1 rewrite, caught by `lib/v1-rewrites.test.ts`). Service suite: 595 passed; tsc and eslint clean.
- The top level is not capped at 12 in code: the best lab tree (run 32, 39 of 47 recovered) had 23 top-level folders. The limit of 2–12 children applies in the prompt. The rules in code are the minimum, the one-child collapse and the depth. This departs from rules.md, pending the measurement in story 3.
- Service commit 7a4fa3f. After the review: 14 cases, service suite 597 passed.

## Review Triage Log

- medium, patched: checkLimits saw a notional 10 while a plan can move up to 190 credits; it now sees the price.
- medium, patched: a failed refund threw; now logged as REFUND FAILED and the 502 still answers.
- low, patched: the comment claimed the daily cap was pooled with /folders; it is its own counter of the same size.
- low, patched: ids were cut to 16 characters before the de-duplication; an id over 16 is now refused.
- low, patched: no test of the defensive input parsing, or of the 3-of-5 boundary; both added.
- low, kept: the prompt says three pictures while the code folds below five; that is the combination the lab measured, commented as such.
- low, rejected: the rate-limit and daily-cap branches are untested here; the same code is tested on /file and /folders.
- maybe-false medium, deferred to story 7: max_tokens 16000 may not fit 3,000 labels.
