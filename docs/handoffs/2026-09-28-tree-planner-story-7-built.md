# Session handoff — 2026-09-28 — tree planner: story 7 built, one proof open

## Goal
Finish story 7 (large libraries fold rare labels): one real charged plan at 500,000 pictures, then mark it done. After that: stories 8, 5, 6, the free re-plan, then the PR and release.

## State
- Repo: Media Plugin/wt/tree-planner
- Branch: tree-planner
- Last commit: d3c092b — feat(tree): a large library folds rare labels so no plan is refused for its size
- Uncommitted: clean (except this handoff)
- Service: live on Vercel at a3d3eea, unchanged. Tech still points at the box copy of the service.
- Box: runs d3c092b (`deploy.mjs --check` up to date). Admin login: admin / VgmlTest7pass. In Git Bash, prefix box-eval calls with MSYS_NO_PATHCONV=1.

## Decisions made this session (Nathan, 2026-09-28)
- The fold budget is 1,500 labels, so the answer fits the 16k-token cap (1,500 + 8 × 1,500 = 13,500).
- A folded library is priced on the folded count, which caps it at 100 credits.
- The proof is 500,000 synthetic pictures on a throwaway box site, with one real charged plan up to the draft. The fill at that size is out of scope.
- A fold label is written "various <class>; <class>", keeping the [kind]. An exact label beats its fold label.
- When there are more fold labels than the budget, the rarest are left to the matcher. Vector placement can't reach them, because label sums only cover the labels sent.
- A cold count over 15 s serves the cached count, or a partial one when none exists yet, and a cron event refreshes it. The plan job always does a full count.

## Open questions / blockers
- AC 2 is not run. Test licence VGML-9… has 5 of 5 seats taken, so the throwaway site was refused with 403 before any charge. Freeing a seat, or getting a licence with one, is Nathan's call.
- Should the screen say that labels were merged? The data is there (`folded_labels`, `left_out_pictures`), but the wording is Nathan's.
- The guard hook fix is unapplied, because auto mode refused it as self-modification. The hook stores paths with the drive letter's case as-is, and cwd flipped c:→C: mid-session, so the guard fired falsely. The fix: `~/.claude/hooks/vl-session-guard.mjs:23`, have `norm` lowercase the drive letter.
- Carried over: 158 credits dropped during the tech proof, untraced. The 20,000-credit pack price. What "TREPLY" meant.

## Files to read first (priority order)
1. _bmad-output/specs/spec-tree-planner/stories/7-large-libraries-fold-rare-labels.md — status in-review; its Proof and Triage Log
2. core/plan-tree.php — vergeml_plan_inventory, vergeml_plan_fold, vergeml_plan_effective_label
3. _bmad-output/implementation-artifacts/deferred-work.md — the last three entries

## Next concrete step
Once Nathan has freed a seat: create the subsite `scale500k`, activate it (check that /licence shows seat_free), and run tools/box-scale-fixture.php with VGML_SCALE_LABELS=1 VGML_SCALE_N=500000. Run one plan through the draft (the scratchpad's plan5.php pattern, the same as tools/box-plan-proof.php but stopping at the draft), record the charge against the price, the runs, the time and the memory, then remove everything. Set the spec to done.

## What NOT to redo
- The count, the fold, AC 1 and AC 3 are proven at 500,000 pictures: 1,500 labels, 100 credits, 19.6 s cold. Shop and tech are byte-identical to 3d8d281.
- The paged rule_rows reads match the unpaged ones on shop (626 rows) and tech (1,000 rows).
- The review is done: three layers, patches applied, suites green. auto-file's 1 red row also fails on main.
- Don't trust vergeml_ai_activate_site()'s "ok": it reports success even on seat_limit. Check /licence.
