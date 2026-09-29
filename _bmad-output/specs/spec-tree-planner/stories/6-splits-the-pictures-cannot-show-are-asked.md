---
title: 'Splits the pictures cannot show are asked'
type: 'feature'
created: '2026-09-29'
status: 'in-progress'
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
- **The owner's "yes" is a blanket confirmation, not an axis picker.** CAP-4 asks for the split to come from the owner "as one question" -- one question is what got built. A finer axis picker (which values, which folders) would touch the second-ask filing work on branch `filing-max`, an explicit SPEC non-goal, so it was not built. "Yes" means: trust the model's own audience folders for this plan; "No" means: keep stripping them. Both are stored so the question is asked at most once a session.
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
- `readme.txt` (External services) and `docs/outbound-audit-2026-09-19.md`: the `/v1/plan-tree` entry now names the new `audienceConfirmed` flag.

## Copy for Nathan (verbatim, draft)

- Tree step question: "Should your folders split by who they are for — men, women, kids?"
- Chip: "Yes, split by audience"
- Chip: "No"

## Tests run

- Service: `npx vitest run lib/plan-tree.test.ts` -- 20 passed (was 18; two added). `npx tsc --noEmit -p .` -- clean.
- Plugin: `node tools/verify.mjs plan-audience` -- see Proof.
- Plugin, related suites re-run for regressions: `node tools/verify.mjs plan-choose plan-gain plan-fold` -- see Proof.

## Proof (free, box read-only)

See the "Proof" section below, filled after the box run.

## Paid proof, pending

Not required for this story's bar (CAP-4's success is stated in terms of what the service returns and what the conversation offers, both provable without a model call: the guard is pure code on fixtures, and the question's trigger is the inventory's own audience share, read-only). If Nathan wants to see it on a live plan:

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
