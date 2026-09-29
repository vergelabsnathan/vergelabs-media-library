# Session handoff — 2026-09-29 — tree planner built end to end, merge pending

## State
- Plugin: branch tree-planner at f0342b5 (+ this handoff), all committed; the box runs it (`deploy --check` up to date).
- Service: `wt/tree-planner-service`, branch tree-planner, 13 commits ahead of main, NOT merged (merging main deploys to Vercel). Migration `db/migrations/022_plan_refunds.sql` written, not applied.
- Stories 5, 6, 8, 9, 10 done (files under `_bmad-output/specs/spec-tree-planner/stories/`). Licence seat_limit fix 2d0bff2.
- Tests at f0342b5: every local suite passes (escaping: known ratio red); 27 box suites, run with outbound calls refused, pass except the reds already on main (auto-file row, ai-background G1 box rows, naming "Electronics", voice's two guide.php strings). Service: tsc clean, vitest 618 passed / 14 skipped. Shop and tech boot equal (tech 14 on the suite's A1).
- Spend this run: 0 credits after Nathan's cap; ~$2.49 OpenRouter (story 5 diagnosis). Before the cap: 178 credits on story 8's proof.

## Blocked on Nathan
1. **Merge**: the auto-mode classifier refused merging tree-planner into main. Plan was: in `Media Plugin/plugin` (main), `git pull --ff-only origin main` (5 watch commits), `git merge --no-ff tree-planner` with `[skip ci]` in the message (box-ui would hit the nearly empty production OpenRouter key; the box already runs this build), push.
2. **OpenRouter balance ~$2.40**, shared by production and the box copy (same key, same DB). Top up / auto top-up; give the box its own key.
3. **Go-live order**: apply 022 → deploy the service (merge its tree-planner to main, `tools/promote.mjs`) → only then release the plugin. Against today's live service the plan button answers 404.

## For Nathan's review (decided in this run on his instruction)
- **Price**: a plan earns €0.46 (shop) / €0.61 (tech) at the €240 pack against €0.65–0.74 model cost; the 35 % margin needs about double the per-label rate.
- **Guard is strict**: the shop's curated 125-folder tree refuses every plan (refunded once 022 is live); tech offers runs 2, 3, 5, refuses 1 and 4. Alternatives: offer with a note when only average-link fails, or let the owner choose.
- **Story 5 bar restated**: CAP-3 now measures new pictures filed into a planned tree (≥ 79 %, today 79–80 %); pair F1 ≥ 68 % moved to CAP-1 as a stretch line. The lab's 71–75 % came from picture-id label order leaking the truth.
- **Story 6 thresholds**: ask when < 15 % of pictures carry audience; keep an audience folder only on majority evidence; each picture needs its own evidence.
- **Refund abuse bound**: one free plan per activated site per month for an edited client.
- db-calls hand-read budget raised 11 → 14 (reason beside it).
- Open: $256 on other OpenRouter keys (Jev router?); "TREPLY" unclear.

## Copy for Nathan (drafts, all in the code now)
- "This licence is already in use on as many sites as it allows. Disconnect it from another site, or use another licence, to connect this one."
- "Should your folders split by who they are for — men, women, kids?" · "Yes, split by audience" · "No"
- "Your folders are already well organised — a new plan wouldn't improve them. Nothing was charged."
- "The plan did not come back, and it may have been charged. Your balance is on the Licence screen."
- "The plan now costs %s credits. Nothing was charged."
- "A plan needs at least five described pictures."
- "Plan my folders · free" · "No new folders to add."
- readme.txt External services additions (plan id, refund) — full text in story 9's file.

## Not done
- Paid proofs pending the top-up: story 6 (optional), story 9 refund end to end (after 022 + deploy).
- Pre-existing: a rule or conversation fill still replaces the frozen map; folder names from the free re-plan are weaker than a model's.
