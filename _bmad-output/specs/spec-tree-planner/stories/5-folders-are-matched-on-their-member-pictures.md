---
title: 'Folders are matched on their member pictures'
type: 'feature'
created: '2026-09-29'
status: 'in-progress'
baseline_commit: '82e9855'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

## Intent

**Problem:** CAP-3. A planned folder's profile, the thing the matcher compares a picture against, is made from the draft's words: its members' objects plus the folder's own name, and its vector is the embedding of that text. The bar is a fill into a planned tree that scores at least the lab's bottom-up result: shop pair F1 leaf ≥ 68 %. Today's reference is story 8's shop fill from scratch (2026-09-29): F1 leaf 66 % (P 58 %, R 76 %), 41/47, purity 75 %, unfiled 9 %.

**Approach (planned):** give each planned folder its members' vector centroid and its members' objects as classes. A parent with no labels of its own would take its members' broader classes. That way no word the model wrote decides where a picture goes. Measure first and for free, and build only what moves the fill.

## Measured (free, 2026-09-29, shop box, read-only)

1. **Profiles do not decide a fill into a planned tree.** Since story 4 the frozen label map files every picture whose label the plan holds. The matcher only sees the rest: 12-14 % of pictures are unplaced in the plan and 9 % after the fill. On three live shop plans (2026-09-27), with each picture in its label's folder, F1 leaf is 64, 67 and 64 %. The 2026-09-29 fill scored 66 %, so the fill is the plan.
2. **A perfect matcher on the leftovers would not reach the bar either.** Putting each leftover in the folder that holds most of its truth folder gives 63, 67 and 63 %. Recall goes up and precision comes down, because the folders they join are the mixed ones.
3. **Member profiles file new pictures no better than today's.** `tools/box-profile-heldout.php` splits the labels into four folds. Each fold's pictures are filed by `vergeml_filing_pick` against profiles built from the other three folds. The run used the same three plans, 625 pictures each:

   | Profile | Filed | Filed with its truth folder (plan 1 / 2 / 4) | F1 leaf, every picture filed this way |
   |---|---|---|---|
   | A, today: members' objects plus folder name, vector from that text | 74-76 % | 377 / 365 / 384 (79-80 %) | 60 / 61 / 60 % |
   | V: vector is the members' centroid | 74-77 % | 383 / 365 / 387 (79-81 %) | 59 / 60 / 60 % |
   | H: a parent takes its members' broader classes | 73-77 % | 377 / 359 / 382 (78-80 %) | 59 / 60 / 59 % |
   | B: V and H together | 74-78 % | 382 / 360 / 384 (78-79 %) | 59 / 59 / 58 % |
   | C: members only, no folder name | 72 % | 318 on plan 2 (71 %) | 51 % on plan 2 |

   Member vectors move the right-folder count by 0 to 6 pictures out of 625, which is noise. Dropping the folder name, the only model-written word left, costs 8 points. For comparison, the label map alone scores 64-67 %.
4. **The gap to the bar is in the live planner's answers, not in the plugin.** The lab's own cached answers (runs 41-55 and 61-75) go through today's code (the service's rules in `tools/plan-sim.mjs`, the plugin's tightest-of-15 and its placement). They keep runs at F1 leaf 71 and 75 % with 41 and 43 of 47 recovered (`tools/lab-answers.mjs`). Tightness ranks those 30 runs the way F1 does: the five tightest score 69-75 %. The live plans' kept trees are equally tight (0.762-0.768) but score 64-67 %. They place 76-81 labels the model left out, where the lab placed 60-62, and they group more coarsely. In plan 1, `Audio equipment` holds turntables, speakers and headphones (32 pictures, 378 of the plan's 1,501 false pairs). In plan 2, `Boots` holds both work boots and hiking boots.
5. **Splitting a leaf by its members' head nouns** (each group at least 5 pictures) moves the three plans 64 → 67, 67 → 66 and 64 → 68 %. It is not robust, and it names folders after describer words ("Player"), so it was not built.

## Decisions (2026-09-29, in this authorised run)

- **No CAP-3 code.** Member vectors and member classes file new pictures no better than today's profile (within 1 point), and dropping the folder name makes it clearly worse (71 vs 79 %). A change measured as no gain is not built. Today's code already meets two parts of the intent: a planned folder's classes are its members' objects (`vergeml_plan_draft`), and once a folder holds three described pictures its profile is read over them (`vergeml_filing_members_*`, S10.7).
- **No paid proof.** Nothing in `core/` changed, so a plan would only re-measure 82e9855, whose shop fill is today's reference (66 %). 0 credits spent, and the shop was not touched.
- **Status stays in-progress.** CAP-3's bar is not met, and profiles cannot meet it. The shortfall comes from the planner's answers (CAP-1): the live service's trees are coarser than the lab's.
- **Kept as tools** (export-ignored): `tools/box-profile-heldout.php` (the fold test) and `tools/lab-answers.mjs` (the lab's cached answers in the service's schema for `tools/plan-sim.mjs`).

## Open for Nathan

- **Why are the live `/plan-tree` answers coarser than the lab's on the same prompt?** These differences remain:
  - Label order: the service sorts by count, then text; the lab sorted by count, then first picture.
  - Wording: "labels" versus "phrases".
  - Output format: Anthropic structured output versus OpenRouter's json_schema.
  - Validity: one live plan had 9 of 15 answers valid. If a length cut caused that, it would drop the longest, finest answers first.

  Running the service prompt (`tools/plan-sim.mjs … prompt`) fifteen times through the lab, and comparing with the lab's cached runs, would separate these causes. That costs about $0.75 of OpenRouter and no credits.
- **Or restate CAP-3's bar.** Since story 4 the fill is the plan, so the fill's F1 is CAP-1's number, and a matcher bar belongs on new pictures (item 3's test).

## Commands

- `node tools/box-eval.mjs tools/box-plan-export.php --site shop > shop-all.json`: every described picture with its label, vector and truth.
- `node tools/lab-answers.mjs shop-all.json shop0927 41-75 lab/` and then `node tools/plan-sim.mjs shop-all.json score lab/a41.json … lab/a55.json`: item 4.
- From the directory holding the plan: `MSYS_NO_PATHCONV=1 node <repo>/tools/box-eval.mjs <repo>/tools/box-profile-heldout.php --site shop --copy plan.json:/tmp/vgml-plan.json --env VGML_ASSIGN=/tmp/vgml-plan.json`: item 3, about 2.5 minutes, no credits.
