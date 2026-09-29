# Session handoff — 2026-09-28 20:30 — story 8: why tech misses its folders, diagnosed offline

## Goal
Story 8: tech recovers ≥ 9/11 real folders at ≥ 78 % purity in two real plans, shop still at its bar. No paid calls without Nathan's yes. Lean process.

## State
- Repo: Media Plugin/wt/tree-planner, branch tree-planner, last commit d109030.
- Uncommitted (commit them first, `test:`/`chore:`): `tools/box-plan-export.php` (read-only export of every described picture, literal SQL, outbound HTTP blocked), `tools/plan-sim.mjs` (everything after the model, offline: assignmentOf + applyRules from the service, vergeml_plan_choose, vergeml_plan_draft's merge, the fill; `prompt` and `score` modes; `--map half|mutual|none`; `--each`).
- The `--existing` edit to plan-sim.mjs was NOT applied (the guard blocked it). Add it in `planTreePrompt()`: a rule 0 plus the list of existing folder paths (To sort excluded) put ahead of the labels.
- Data (session scratchpad, may be gone; re-export in about 1 min): `.../scratchpad/tech-all.json` is `MSYS_NO_PATHCONV=1 node tools/box-eval.mjs tools/box-plan-export.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json`. `answers/run1-5.json` are five Sonnet-subagent answers to the service's exact prompt.
- Box up to date with the tree (deploy --check). Tech is pointed at the box copy of the service.

## Findings (all free)
- plan-sim reproduces the proof's "before" line exactly: 7/11, 67 %, F1 20 %, 4 % unfiled, 17 folders. The scorer is faithful.
- The tech inventory is 678 labels over 1,000 pictures (the lab had 200), and 558 labels occur once. Tech's existing folders already carry the truth's names; 327 pictures sit in To sort; 61 were placed by a user.
- Five Sonnet runs: **the plan alone scores 7–8/11 at 69–87 %; after the merge and fill, 5–8/11 at 62–79 %.**
- The plan misses Hardware (30) in every run: with 1,000 pictures the model splits it into graphics cards, desktops and motherboards. People (6) has mixed labels and no tree recovers it. So 9/11 needs Hardware plus all the rest.
- The merge (vergeml_plan_draft: a planned folder becomes an existing one when half its pictures sit there) copies old mistakes: Smartwatches → Phones, telecom towers → Server racks, diagrams → Space > Satellites. The mutual and none variants raise purity (77–85 %) but lose recovery (5–7), because user-placed and unmapped pictures stay in the old folders and split the real ones.
- The oracle (each label in its truth majority) gives 9/11 at 71 % as a plan and 8/11 after the fill. The 72 no-folder pictures only stay pure in folders of their own.

## Next concrete step
Add `--existing` to plan-sim's prompt, build `prompt-existing`, get five Sonnet answers from **plain Agent subagents** (model sonnet, not Workflow), and score them with `--map half` and `--map none`. If the result is ≥ 9/11 at ≥ 78 %, take it to Nathan: sending existing folder names to the service is new outbound data (readme External services and the outbound audit in the same commit) plus a service prompt change.

## Open questions for Nathan
- May the planner send existing folder names to the service? That's what the fix needs.
- Carried over: the merged-labels wording on screen; the untraced ~$25 and 158 credits; activation reports "ok" on seat_limit.

## What NOT to redo
- Don't use tools/box-filing-export.php for planner data: it runs the matcher, which can call /file. Use box-plan-export.php.
- Merge rules alone (half, mutual, none) do not reach 9/11. Already measured.
- Don't launch Workflow without Nathan asking; five Sonnet agents took 16 min and 620k tokens.
