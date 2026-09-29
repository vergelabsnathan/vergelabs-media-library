# Session handoff — 2026-09-29 — story 8: new bar, never-worse guard built, box check pending

## Goal
Story 8 (new bar, Nathan 2026-09-29): tech after the fill ≥ 75 % purity and never below its own before (67 %); a plan not clearly tidier is not offered. Shop keeps its bar. No paid calls without Nathan's yes.

## State
- Repo: Media Plugin/wt/tree-planner, branch tree-planner, last commit 7b91412 (docs(spec): story 8's bar…).
- Uncommitted (the guard — the session guard blocked further build; commit it first, `feat(tree):`):
  core/plan-tree.php (VERGEML_PLAN_GAIN = 0.02; vergeml_plan_gain_add / _gain_of / _gain; plan job sets state 'kept' + message when after < before + 0.02, draft not set),
  core/guide.php (vergeml_guide_rule_rows accepts 'embedding'), js/vergeml-folders.js (note shown for 'kept' too),
  tests/tree/plan-gain.php (5/5 pass) + registered in tools/verify.mjs. plan-choose and plan-fold still pass.
- Box: deployed with these changes, `deploy --check` up to date.
- Scratchpad (previous session's, readable): C:/Users/viete/AppData/Local/Temp/claude/c--Users-viete-Desktop----Claude-Projects-Media-Plugin-wt-tree-planner/312abff7-f971-4f44-884d-02b6f58c0cd9/scratchpad — tech-all.json (export), answers-existing/run1-5.json, trees.json (10 plans in service shape, label text), box-gain.php, sim-tight.mjs. Yesterday's answers: …/c378600c-dbd1-423a-b13b-67656953af09/scratchpad/answers/.

## Decisions made this session
- Committed 3c0963a (export + plan-sim) and d48ddaf (plan-sim --existing).
- Sending existing folder names: tested, rejected. Five Sonnet runs scored 3–7/11 at 49–77 % (worse than without). readme/outbound audit untouched.
- 9/11 at 78 % dropped: oracle plan gives 9/11 only at 71 %. Spec + stories.yaml rewritten (7b91412).
- Guard measures the SAME pictures (those already in a non-To-sort folder), mean cosine to folder centre, now vs after fill (user-placed and unmapped labels stay). Offline on tech: before 0.693; yesterday's plans after 0.701 (62 %), 0.711 (73 %), 0.718, 0.720, 0.736 (kept, 79 %); folder-name plans ≤ 0.696. Margin 0.02 = Nathan's "clearly better".
- Message (Nathan approved): "Your folders are already well organised — a new plan wouldn't improve them." Credits stay charged.

## Open questions / blockers
- Carried over: merged-labels wording; untraced ~$25 / 158 credits; activation "ok" on seat_limit.
- AGENTS.md says 49 suites; verify.mjs has 54 entries (pre-existing drift).

## Files to read first
1. core/plan-tree.php — vergeml_plan_gain* and vergeml_plan_event
2. tests/tree/plan-gain.php
3. _bmad-output/specs/spec-tree-planner/stories.yaml — story 8

## Next concrete step
Commit the guard. Then from the scratchpad dir (scp fails on the emoji path, so relative paths): `cd <scratchpad> && MSYS_NO_PATHCONV=1 node "<repo>/tools/box-eval.mjs" box-gain.php --copy trees.json:/tmp/vgml-trees.json --env VGML_TREES=/tmp/vgml-trees.json` — expect PHP before ≈ 0.693, kept run2 after ≈ 0.736 OFFERED, the five folder-name plans not offered. Then ask Nathan for the paid proof (two real plans on tech, shop at its bar).

## What NOT to redo
- Folder names in the prompt (measured worse). Merge-rule variants (half/mutual/none) alone.
- Comparing tightness over all filed pictures: a To-sort pile makes before look tidy; use same-pictures.
- No Workflow without Nathan asking.
