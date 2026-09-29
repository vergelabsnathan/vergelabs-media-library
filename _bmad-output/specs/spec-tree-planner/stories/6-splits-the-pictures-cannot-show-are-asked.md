---
title: 'Splits the pictures cannot show are asked'
type: 'feature'
created: '2026-09-29'
status: 'done'
baseline_commit: 'aaec3bd'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

## Intent

**Problem (CAP-4):** An audience split (men/women/kids or an owner's own axis) must come from the owner as one question, or from the shop's product categories -- never guessed from pictures. Rule 4 of the planner's own prompt already says so ("Split by audience only where the counts support it on both sides"), but nothing after the model's answer checks it. Deferred from story 2's review (`_bmad-output/implementation-artifacts/deferred-work.md`): "nothing in applyRules strips an audience split the counts do not support." A model that ignores rule 4 -- or a library where 3 of 88 real folders carry the right audience (SPEC non-goal) -- can still send back "Men" and "Women" folders, and the plugin's own draft-building (`vergeml_plan_draft`) has no opinion on the matter: it takes whatever path the service sends.

**Where a split can enter a tree, checked:**
- **The model's answer** -- the only real entry point today. `assignmentOf` (service) turns the answer into paths verbatim; `applyRules` folds under-minimum folders and collapses one-child parents, but never reads a folder's name.
- **The plugin's own rules** (`vergeml_plan_choose`, `vergeml_plan_draft`) -- neither reads a folder's name either; they place left-out labels by vector and fold planned folders onto existing ones by member overlap. No audience logic anywhere in `core/plan-tree.php`.
- **Existing folders** -- kept exactly as they stand (SPEC constraint); an existing "Men" folder is the owner's business, not the planner's, and this story does not touch it.
- **Product categories** -- read already (`vergeml_folders_product_paths`) for the "Use my product categories" way in, but never scanned for what they say about audience, and never sent to the service.

**Approach:**
1. **Service-side guard, always on** (`lib/plan-tree.ts`, `stripUnsupportedAudience`): after `assignmentOf` and before `applyRules`, fold any folder named for an audience into its parent unless the labels placed under it (at any depth) carry that audience on at least `MIN_PICTURES` (5) pictures *and* on at least half of the folder's own pictures. This is the place that holds for every client, old plugin or new: a request that says nothing new gets the guard by default, because the flag below defaults to false.
2. **`audienceConfirmed` request flag** -- when true, the guard is skipped entirely for that run. Set by the plugin only when the split is no longer a guess: the owner answered "yes" to the one question, or the site's product categories already name an audience.
3. **The one question** (plugin, Tree step): shown only when the library's own audience evidence is thin (`vergeml_plan_audience_share` under `VERGEML_PLAN_AUDIENCE_ASK`) and product categories show none, and only until answered. A yes/no answer is stored on the session (`audience_split`) and never asked again that session.
4. **Product categories as evidence**: `vergeml_plan_product_audience_evidence()` scans the site's product category paths with the same word list `core/filing.php` already uses to read a folder name's audience (`vergeml_filing_audience_of`) -- Dutch compounds included, for free. A hit skips the question and confirms the split without asking.

## Decisions (this run, Nathan pre-authorised)

- **Evidence bar for the guard, `min = MIN_PICTURES` and `evidence * 2 >= total`:** at least 5 pictures carry the audience, and they are a majority of the folder's own pictures -- not just present. Matches rules.md's own minimum and reads "the counts support it" as a real majority, not a trace. A softer bar (any evidence at all) would let a folder with 1 of 20 pictures tagged survive, which is closer to noise than to a split.
- **Audience word list is English-only** (`AUDIENCE_WORDS` in `lib/plan-tree.ts`): men/women/kids and their common synonyms, matched case-insensitively against a folder's own name segment. `core/filing.php`'s `vergeml_filing_audience_of` also reads Dutch compounds (dameskleding, herenkleding) by prefix; that finer reader stays where it already runs (plugin-side, `vergeml_plan_product_audience_evidence` reuses it directly). The service's guard is deliberately narrower and literal: a false negative here (an audience folder the service does not recognise as one) only means an unusual name is judged by the ordinary minimum-pictures rule instead, never that a split gets guessed and kept.
- **"No audience evidence" threshold for asking the question, `VERGEML_PLAN_AUDIENCE_ASK = 0.15`:** under 15% of described pictures carrying any audience tag. The SPEC's own non-goal cites 3 of 88 (about 3% of pictures, and far under 15% site-wide on the shop) as "no evidence"; 15% is a deliberately loose ceiling so the question is offered whenever it could plausibly help, not only on a library with none at all. No real library was available to tune this without a paid plan, so it is a judgement call, documented here rather than in the prompt.
- **The owner's "yes" is a blanket confirmation, not an axis picker.** CAP-4 asks for the split to come from the owner "as one question" -- one question is what got built. A finer axis picker (which values, which folders) would touch the second-ask filing work on branch `filing-max`, an explicit SPEC non-goal, so it was not built. "Yes" means: let the model's own audience folders stand in the *tree*; "No" means: keep stripping them from the tree. Both are stored so the question is asked at most once a session. (Fix round, below: "yes" was never licence to guess a *picture* into one of those folders -- only the tree's shape moved with the owner's answer, never a single fill decision. That gap is closed at fill time in `core/filing.php`, not by changing what "yes" means here.)
- **Product-category evidence is a blanket confirmation too, not a source of new folders.** SPEC says the split may come "from the shop's product categories" -- reading category names for an audience word and setting `audienceConfirmed` accordingly is the minimal honest reading that fits in this story's scope; building a category-driven audience tree is the deeper "Use my product categories" mechanism already in the Tree step (`onUseCategories`) and was not extended, since it is a distinct, larger change with its own drafting path.
- **No change to `vergeml_plan_choose` or `vergeml_plan_draft`.** Both are blind to folder names already; once the service never sends back an unsupported audience folder, there is nothing left for the plugin's own placement code to do differently. Keeping the guard in one place (the service) is also what "holds for every client" means literally: every plugin version, including ones that predate this story, gets the default-safe behaviour with no plugin change at all.

## I/O matrix

| Site's audience evidence | Product categories name an audience | Owner answered the question | Service sees `audienceConfirmed` | Question shown | Audience folders in the result |
|---|---|---|---|---|---|
| Thin (< 15%) | No | Not yet | `false` (default) | Yes, once | Stripped unless a folder's own labels clear the evidence bar anyway |
| Thin (< 15%) | No | Yes ("yes") | `true` | No (answered) | Kept as the model proposed them |
| Thin (< 15%) | No | Yes ("no") | `false` | No (answered) | Stripped unless the evidence bar is cleared |
| Thin (< 15%) | Yes | n/a | `true` | No (evidence already exists) | Kept as the model proposed them |
| Not thin (≥ 15%) | any | n/a | `false` (unaffected by the question path) | No | Stripped unless the evidence bar is cleared, folder by folder -- a library with real audience data still only keeps the folders their own labels support |

## Built

**Service** (`tree-planner-service`, branch `tree-planner`):
- `lib/plan-tree.ts`: `AUDIENCE_WORDS`, `stripUnsupportedAudience(assign, labels, min)`.
- `app/api/ai/plan-tree/route.ts`: reads `audienceConfirmed` from the request body; runs the guard on every kept run unless it is `true`.
- `lib/plan-tree.test.ts`: unit tests for the strip function (evidence kept, thin evidence folded, non-audience names untouched) and a route test proving the default strips an unsupported "Women" folder while `audienceConfirmed: true` keeps it.

**Plugin** (`tree-planner`, branch `tree-planner`):
- `core/plan-tree.php`:
  - `VERGEML_PLAN_AUDIENCE_ASK` (0.15).
  - `vergeml_plan_audience_share( $labels )`: the inventory's own audience-tagged share, from the label counts already gathered -- no extra query.
  - `vergeml_plan_product_audience_evidence()`: true when a product category path names an audience (`vergeml_filing_audience_of`), false with no WooCommerce or no hit.
  - `vergeml_plan_facts()`: adds `audience_ask` (bool, whether to show the question) and `audience_evidence` (bool, whether product categories already answered it).
  - `vergeml_plan_ask( $labels, $audience_confirmed )`: sends `audienceConfirmed` in the outbound body.
  - `vergeml_plan_event()`: works out `$audience_confirmed` from the session's stored answer or from product-category evidence, and passes it through.
  - `vergeml_plan_rest_audience_split()` + REST route `POST guide/audience-split`: stores the owner's yes/no on the session.
- `core/guide.php`: `vergeml_guide_fresh()` and `vergeml_guide_session_out()` carry the new `audience_split` session field.
- `js/vergeml-folders.js`: `renderAudienceAsk()` / `onAudienceSplit()`, shown in the Tree step beside the Plan button when `cfg.plan.audience_ask` is true and the session has no answer yet.
- `css/vergeml-folders.css`: a two-chip row for the question, reusing the existing `.g-chip` look.
- `tests/tree/plan-audience.php` (local suite, registered in `tools/verify.mjs` as `plan-audience`): `vergeml_plan_audience_share` and `vergeml_plan_product_audience_evidence` on fixtures, no WordPress, no box.
- `tools/box-audience-check.php`: read-only, `pre_http_request` blocked, prints `vergeml_plan_facts()`'s audience fields for any site -- the proof tool for this story, kept for the next time the thresholds are revisited.
- `readme.txt` (External services) and `docs/outbound-audit-2026-09-19.md`: the `/v1/plan-tree` entry now names the new `audienceConfirmed` flag.

## Copy for Nathan (verbatim, draft)

- Tree step question: "Should your folders split by who they are for — men, women, kids?"
- Chip: "Yes, split by audience"
- Chip: "No"

## Tests run

- Service: `npx vitest run lib/plan-tree.test.ts` -- 20 passed (was 18; two added, both green, including the route-level "strips without evidence / keeps with `audienceConfirmed`" case). `npx tsc --noEmit -p .` -- clean.
- Plugin, new suite: `node tools/verify.mjs plan-audience` -- 9/9 passed (`vergeml_plan_audience_share`, `vergeml_plan_product_audience_evidence`, the ask threshold).
- Plugin, related suites re-run for regressions: `node tools/verify.mjs plan-choose plan-gain plan-fold` -- 15/15, 5/5, 27/27, all still passing; nothing in `applyRules`'s callers or the fold/gain paths reads audience, so none was expected to move.
- Plugin, `core/guide.php`'s session shape (`audience_split` added to `vergeml_guide_fresh()`/`vergeml_guide_session_out()`): `node tools/verify.mjs guide` (box, read-only, no service call) -- 61/61 passed, including the "the session is as it was found" check.
- `node --check js/vergeml-folders.js` -- clean. `php -l` on every shipped file, via `deploy.mjs --box` -- "every file parses".

## Proof (free, box read-only, commit c8d9296)

`node tools/deploy.mjs --box` then `--check`: box up to date, 139 files, digest `4cb56d4c828c`.

`node tools/box-eval.mjs tools/box-audience-check.php --site shop` (the C.5 Commons library, 626 pictures, 47 real folders -- outbound HTTP blocked for the run):

```
{ "labels": 464, "pictures": 626, "audience_share": 0.061, "ask_threshold": 0.15, "product_evidence": false, "audience_ask": true }
```

Thin picture evidence (6.1%, near the SPEC non-goal's "3 of 88"), no product-category evidence -- `audience_ask` is `true`: the Tree step would show the one question. This is CAP-4's success line on the site the SPEC states its numbers against.

`node tools/box-eval.mjs tools/box-audience-check.php` (tech, 1,000 pictures):

```
{ "labels": 678, "pictures": 1000, "audience_share": 0, "ask_threshold": 0.15, "product_evidence": true, "audience_ask": false }
```

Zero picture evidence, but tech's own WooCommerce product categories include "Men's" and "Women's" -- `vergeml_plan_product_audience_evidence()` finds them (confirmed by reading the paths directly: 2 of 4 category paths carry an audience word) and `audience_ask` is correctly `false`: the split already has a real source, so nothing is guessed and nothing is asked either.

`node tools/box-eval.mjs tools/box-audience-check.php --site realshop` (the small WooCommerce shop, 33 pictures):

```
{ "labels": 29, "pictures": 33, "audience_share": 0.152, "ask_threshold": 0.15, "product_evidence": false, "audience_ask": false }
```

Just over the 15% ask threshold on picture evidence alone (5 of 33) -- correctly not asked; a useful boundary case, not tuned to produce it.

All three box reads are through `vergeml_plan_facts()`/`vergeml_plan_audience_share()`/`vergeml_plan_product_audience_evidence()` exactly as the Tree step calls them, with `add_filter( 'pre_http_request', ... )` refusing any outbound call for the run -- so this proves the trigger side of CAP-4 on real libraries without touching the AI service. The strip itself (`stripUnsupportedAudience`) is proven in the service's own unit tests (`lib/plan-tree.test.ts`), which is a complete proof of that half: it is a pure function of the model's answer shape and the label counts, so a fixture answer naming an unsupported audience folder proves the guarantee for every answer of that shape, not only one a live model happened to produce.

## Fix round: the "yes without evidence" gap (2026-09-29, second run)

**Found by review.** `audienceConfirmed: true` skips `stripUnsupportedAudience` entirely, so the service can hand back an audience folder with no evidence behind it at all. That is the tree the owner sees and can be fine with -- CAP-4 lets the owner ask for the split. The bug was one level down: once the owner accepted that tree, the frozen label map (`core/filing.php`'s `vergeml_filing_label_folders`, spec-tree-planner story 4) files *every* picture carrying a label the plan put in that folder, with no audience check at all -- `vergeml_filing_pick_rules`'s label branch (the "sure, before any scoring" shortcut) returned the folder outright, before the ordinary matcher's own audience gate (a few lines further down the same function) ever ran. A picture the describer never said was anyone's still got filed into "Men" because its *label* was in the map, whether or not that one picture carried the evidence. The service cannot see this at all -- it only ever sees labels and counts, never pictures -- so the fix could only be a plugin one, at fill time.

**Design (Nathan's option (a), plugin-side, in code):** `audienceConfirmed` still lowers the *folder*-level bar (service, unchanged from the first run) -- the tree the owner asked for is allowed to exist. But the label map's shortcut in `vergeml_filing_pick_rules` (`core/filing.php`) now reads the target folder's own `audience` profile field (already computed for every folder, from its name, by the matcher's existing machinery -- nothing new to store) and only takes the label's word when the picture's own audience matches or the target is not audience-gated at all. A picture that says nothing, or says a different audience, falls through to the ordinary matcher below -- the same audience gate every other pick already meets (hard-gated on a stated mismatch, held as a shadow/runner-up on silence), so nothing new was built there, only reused. `vergeml_filing_product_folders`' own branch (source `product`) needed no change: a product's own category assignment is per-picture evidence in itself, exactly what CAP-4 allows, and it already outranks the label branch.

**What this means in practice, both real libraries checked:**
- **Shop** (6.1% audience share, no product categories): "Yes" lets the tree show Men/Women/Kids if the model proposes them, but at fill time only the ~6% of pictures that actually carry that audience tag get filed there -- everything else falls to the ordinary matcher (gated, shadowed, or placed elsewhere on its own evidence). The audience folders end up sparse rather than falsely full, which is exactly Nathan's option (b) as a side effect of option (a), with no extra mechanism built for it.
- **Realshop** (WooCommerce, real product categories): most audience-relevant pictures are a product's own featured image or gallery image, so they are filed by `vergeml_filing_product_folders` on that product's own category path -- direct per-picture evidence, never the label guess. Any picture *not* tied to a product falls to the same label-map gate as the shop's.
- **Tech** (no picture evidence, but real "Men's"/"Women's" product categories, confirmed on the box below): `audienceConfirmed` is already set from product-category evidence, not a "yes" answer, and the same per-picture gate applies regardless of which of the two set the flag.

**Built:** `core/filing.php`, `vergeml_filing_pick_rules`'s label-map branch -- one added check, no new function, no new stored field (reuses the folder's existing `audience` profile). No service change was needed; `stripUnsupportedAudience`/`audienceConfirmed` (first run) stand as built.

**Tests:** `tests/filing/sticky.php`, section K, rows K4b-K4e (box, real WP, `vergeml_filing_pick_rules` and `vergeml_filing_label_folders` directly): a picture whose own audience matches the label's target still wins by the label (K4b); one that says nothing (K4c), one that says a different audience (K4d), and one with no audience key on the row at all -- an older caller -- (K4e) are all refused by the label branch (`why !== 'label'`), proven against a real audience-gated profile (`audience: 'men'`). `node tools/verify.mjs sticky` -- 87/87 passed (was 82; five added, K4b positive control plus K4c/d/e negative controls -- see per-row detail above).

**Regression, all free:** `node tools/verify.mjs plan-audience plan-choose plan-gain plan-fold guide folders-shop` -- all passed (summary: "passed folders-shop, plan-choose, plan-gain, plan-fold, plan-audience, guide"). Service: `npx vitest run` (tree-planner-service, full suite, no service code changed this round) -- 616 passed, 14 skipped (pre-existing), 50 files. `node tools/deploy.mjs --box` then `--check` -- up to date, `php -l` clean on all 139 files.

**Status: done.** Both halves of CAP-4's guarantee are now proven free and on real WP code: the folder-level guard (service unit tests, first run) and the picture-level guard (`sticky` suite K4b-K4e, box, real `vergeml_filing_pick_rules`, this round) are each a deterministic function of their inputs, so the proof holds for every answer of that shape -- not only one a live model happened to produce. The "Paid proof, pending" command below is left for Nathan's own curiosity, not because anything is unproven.

## Paid proof, pending

Not required for this story's bar (CAP-4's success is stated in terms of what the service returns, what the conversation offers, and what a picture is filed into, all three provable without a model call: both guards are pure code on fixtures/real WP, and the question's trigger is the inventory's own audience share, read-only). If Nathan wants to see it on a live plan:

```
node tools/box-eval.mjs tools/box-plan-export.php --site shop > shop-all.json
# then a paid plan-tree run via the plugin's own Plan my folders button on the shop,
# or the service's own test harness against a *.tmp.ts script as story 5 used --
# expected cost: one plan at the shop's own price (about 40 credits / $0.25-0.30
# model cost for ~465 labels), same order as story 5's paid runs.
```
Expected result: no folder in the returned tree named for an audience unless `audienceConfirmed` was sent, since the shop's own audience share is about 3-5% (SPEC non-goal, well under the 15% ask threshold and far under the guard's 50% evidence bar).

## Open for Nathan

- The 15% "ask" threshold and the "majority of a folder's own pictures" evidence bar are both judgement calls made in this run, without a live library to tune them against (HARD LIMIT: no paid calls). Accept, or say the number you want.
- "Yes" is a blanket confirmation, not an axis picker -- accept, or ask for a follow-up story to let the owner say *which* axis (their own, not just men/women/kids), which would touch the prompt's rule 4 as well.
