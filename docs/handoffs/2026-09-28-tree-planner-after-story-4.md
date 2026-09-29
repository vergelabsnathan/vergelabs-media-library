# Session handoff — 2026-09-28 — tree planner: stories 3-4 done, finish the rest one story per session

## Goal
Finish spec-tree-planner and ship it: tech proof + large-library guard, story 5 (profiles), story 6 (audience question), free re-plan (deferred story), then PR `tree-planner` → `main` and a plugin release. Nathan: run it all, don't rush, one story per session (vl-session-guard now enforces this).

## State
- Repo: `Media Plugin/wt/tree-planner` (plugin, branch `tree-planner`, last commit d12d21c, clean). Service: `Media Plugin/wt/tree-planner-service` (branch `tree-planner`, pushed to main at a3d3eea, live on Vercel).
- Box: runs this branch (`node tools/deploy.mjs --check`). Shop restored after both proofs (snapshots in /var/tmp and /root: `vgml-shop-before-story4*.json`). Tech's WordPress is pointed at the box service copy (127.0.0.1:3100, no /plan-tree): unpoint with `tools/box-vps-unpoint.sh`, re-point with `tools/box-vps-point.sh` (over ssh, key `~/.ssh/hetzner_vgml`).
- Box admin (tech and shop): admin / VgmlTest7pass; run UI suites with `UI_PASS=VgmlTest7pass`.

## Decisions made (all recorded in the story files)
- 15 runs; the plugin keeps the tightest tree by the site's vectors and places left-out labels at cosine ≥ 0.5 (story 3). Token budget per run = 1,500 + 8 per label, max 16,000; 100 s per call (service).
- Story 4: the plan's label → folder map is frozen at the fill and files by rule in dry run, fill and Auto-file; a planned folder whose pictures sit ≥ half in one existing folder becomes it; a frozen label files a picture out of the locked To sort unless a person placed it; the dry run counts what stays in To sort.
- CAP-1 bars: shop ≥ 35/47, purity ≥ 75 %, unfiled ≤ 10 %; no agreement bar. Shop proof: 38/47, 75 %, 3.4 %.

## Open questions / blockers
- Nathan: the 20,000-credit pack price (sets the per-100-labels rate at 15 runs; price is still 10 + 6 per 100 labels).
- Nathan: tech's recovery bar (9/11) if the rerun stays at 7-8.
- Nathan's message ended "I NEED TO SHOW YOU TREPLY" — ask what he meant.

## Files to read first
1. `_bmad-output/specs/spec-tree-planner/SPEC.md` + `stories.yaml` — the contract and the remaining stories.
2. `_bmad-output/specs/spec-tree-planner/stories/4-the-accepted-tree-is-frozen.md` — what just shipped, proofs, triage.
3. `_bmad-output/implementation-artifacts/deferred-work.md` — the re-plan story and known reds (auto-file row, db-calls: red on main too).

## Next concrete step
New session: tech proof + large-library guard as one small story via `bmad-build` — unpoint tech, run two real plans with the proof script pattern (plan → confirm → fill → `tools/tree-lab.mjs current` → restore snapshot), re-point; and on the Folders screen refuse a plan above 3,000 labels with a clear message instead of the service's 400 (copy: propose to Nathan). Then story 5, 6, re-plan — each its own session — then the PR.

## What NOT to redo
- Temperature 0 is not the cause of weak plans (the lab runs at 0 too); votes across runs gained nothing.
- The 402s were OpenRouter reservations, fixed by the token budget; auto top-up works.
- Box UI suites failing at login were the admin password, now fixed.
