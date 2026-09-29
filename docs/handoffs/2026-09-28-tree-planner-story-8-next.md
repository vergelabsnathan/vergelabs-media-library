# Session handoff — 2026-09-28 — tree planner: story 7 done, story 8 next

## Goal
Story 8: tech recovers ≥ 9 of 11 real folders at ≥ 78 % purity (now 7/11, 75 %). Order after that: 5, 6, free re-plan, PR and release.

## State
- Repo: Media Plugin/wt/tree-planner, branch tree-planner, clean. Story 7 closed at d0d6109 (AC 2 waived by Nathan: no free licence seat).
- Service live on Vercel at a3d3eea, unchanged. Tech points at the box copy of the service (tools/box-vps-point.sh).
- Box runs d3c092b. Admin: admin / VgmlTest7pass. Git Bash: prefix box-eval with MSYS_NO_PATHCONV=1.
- Session guard fixed: ~/.claude/hooks/vl-session-guard.mjs `norm` lowercases the drive letter (tested c: then C: cwd, no false block).

## Rule (Nathan, 2026-09-28)
- No paid calls for testing: no OpenRouter, no charged service calls, no box suites that reach the real service, unless Nathan says yes for that run. Test with Claude itself (generate candidate trees in-session, score them free with tools/tree-lab.mjs score).
- Lean process: build inline, self-test, one review.

## Open
- Should the screen say labels were merged? Data exists (folded_labels, left_out_pictures); the wording is Nathan's.
- Untraced: where the ~$25 went (do the guide/sticky/auto-file box suites call the real service?), the 158 credits from the tech proof, the 20,000-pack price, "TREPLY".
- vergeml_ai_activate_site() says "ok" on seat_limit; check /licence.

## Read first
1. _bmad-output/specs/spec-tree-planner/SPEC.md + stories.yaml (story 8), story 4's "Proof, tech"
2. _bmad-output/implementation-artifacts/deferred-work.md (last three entries)

## Next step
Start story 8 lean: find why the planner misses tech's real folders, testing with Claude-generated trees and free scoring. No paid calls without Nathan's yes.
