---
title: 'Large libraries fold rare labels'
type: 'feature'
created: '2026-09-28'
status: 'done'
baseline_commit: '3d8d28144eb3412019a56c6e9045b5d4892a1fd1'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The plugin sends every distinct label, and the service refuses more than 3,000 (`too_many_labels`, shown raw after the button already named a price). Above about 1,800 labels the answer no longer fits its 16,000-token budget. The inventory and the draft read every picture's row in one query. A 500,000-picture library would be refused, or would run the site out of memory, before any of that.

**Approach:** Before pricing and sending, the plugin folds rare labels into one label per broader class until the inventory fits 1,500. It reads the index in pages, prices the folded inventory, and files a folded label's pictures through its class's folder. The service is unchanged: it is never sent more than the budget.

## Boundaries & Constraints

**Always:** No library is refused for its size, and no "too many labels" message exists. A library under the budget is sent exactly as today, so its tree, its price, its label map, its dry run and the shop and tech proofs do not change. The button's price is the price of what is sent, counted on the site. The label map stays keyed by label text. Folders stay `media_category` terms.

**Never:** Nothing new is sent to the service, so readme.txt and the outbound audit stay as they are. No change to the service's route, its prompt, its rules or MAX_LABELS. No model call in the fold. No change to the filing matcher's thresholds. The fill at 500,000 pictures is not part of this story.

**Decisions (Nathan, 2026-09-28):** The fold budget is 1,500 labels. A folded library is priced on the folded count, so it pays at most 100 credits. The proof puts 500,000 synthetic described pictures on a throwaway box site and runs one real, charged plan through the draft.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Small library | 464 labels (the shop) | sent unfolded; identical inventory, price, map and dry run | N/A |
| Over budget | labels > 1,500 | labels under the fold count merge into their class's fold label; the count rises until ≤ 1,500 | N/A |
| Classes alone over budget | fold labels > 1,500 | the rarest fold labels are left out of the call; their pictures are not in the map, so the matcher decides them as today | N/A |
| Picture with a folded label | "platform sneaker; footwear", map holds "various footwear; footwear" | filed in that folder | N/A |
| Exact label in map | exact text in map | exact wins over the fold label | N/A |
| Unfolded site, label missing from map | a new or unfiled label on the shop | the matcher decides, as today; no fold label exists in its map | N/A |

</frozen-after-approval>

## Code Map

- `core/plan-tree.php` -- `vergeml_plan_inventory` (:48):
  - It runs one unpaged `get_results` (:61). Page it like `vergeml_plan_label_sums` (:116): 500 rows at a time, by `attachment_id`.
  - The cache key (:53-54) must add a schema version and the budget, or a pre-upgrade unfolded cache survives the upgrade.
  - The fold goes after counting and before the ids (:84-90). Store only counts in the cached option (:93).
  - `vergeml_plan_label_sums` keys by label text (:121). A picture whose label is not in the inventory adds into its fold label's sum.
  - `vergeml_plan_draft`'s `$label_terms` (:440-452) reads `vergeml_guide_rule_rows` whole and keys by exact text. Page it, and key a folded picture by its fold label. A folded entry's `class` (:488) is the broader class.
  - `vergeml_plan_ask` shows `too_many_labels` raw (:393).
  - The state key `left_out` (:336) already means unlabelled pictures. Name the new counts differently.
- `core/guide.php` -- `vergeml_guide_rule_rows` (:2697). Give it optional `$after` and `$limit` arguments; with them left out, it behaves exactly as today. `vergeml_plan_facts` runs on every Folders page render (:117).
- `core/filing.php` -- `vergeml_filing_label_folders` (:1678) matches by exact text. Its second lookup is the picture's fold label, which exists in a map only when that plan folded. Reuse `vergeml_filing_classes_of_object` (:262).
- The other label-map readers are `core/guide.php` :1964 and :2439, `core/folder-talk.php` :902 and :1163, and `core/auto-file.php` :295. Every one of them resolves through `vergeml_filing_label_folders`, or gets the same second lookup.
- `tools/box-scale-fixture.php` already creates and removes 250k marked rows (`vgmlscale-%`). Extend it with index rows carrying `filing` and clustered vectors.
- Service `lib/plan-tree.ts` reads each label as "object; class [kind]" (:67), which is why a fold label is written as "various <class>; <class>". Read only.

## Tasks & Acceptance

**Execution:**
- [x] `core/plan-tree.php`:
  - Page the inventory. Add the fold, a fold-label helper `vergeml_plan_fold_label($kind,$filing)` (''-safe), and a cache key version.
  - The inventory carries `folded_labels` (how many labels were merged) and `left_out_pictures` (pictures in dropped fold labels).
  - Sums use the fold label.
  - The draft pages its rows and keys them by fold label.
  - Remove the `too_many_labels` message path.
- [x] `core/guide.php` -- page arguments on `vergeml_guide_rule_rows`.
- [x] `core/filing.php` -- the fold-label second lookup in `vergeml_filing_label_folders`. Route the direct map readers through it.
- [x] `tests/tree/plan-fold.php` (register it in `tools/verify.mjs`), with rows for:
  - under the budget: untouched;
  - over the budget: at most 1,500 labels, with picture counts conserved;
  - fold-label collision: counts and audience merged;
  - the kind is kept;
  - fold labels over the budget: the rarest are dropped and counted;
  - a folded picture resolves through its fold label, and an exact label beats the fold label;
  - an unfolded map with a missing label returns nothing;
  - the draft's existing-folder match counts folded pictures;
  - a pre-version cache is recomputed.
- [x] `tools/box-scale-fixture.php` -- `VGML_SCALE_LABELS=1` writes index rows with stated shapes: about 40,000 distinct labels over about 1,200 classes, Zipf-like counts, 3 % non-photo kinds, and 512-dimension vectors clustered by class. Removal stays by marker and uses literal SQL.

**Acceptance Criteria:**
- Given 500,000 synthetic pictures on a throwaway site activated with the box's test licence, when the Folders page facts are read cold, then the inventory is ≤ 1,500 labels, the price is ≤ 100 credits, and the time and PHP peak memory are printed. If the cold read takes over 15 s, the facts serve the last cached inventory and a single cron event refreshes it. **Met on the box, 2026-09-28** (see Proof).
- Given that library planned for real through the draft, then the call is accepted (no 400), at least 3 runs are valid, the charge equals the button's price, and the plan finishes inside its 340 s lock. The draft is built, with its time and memory printed; what the fit job does at this size is recorded. Afterwards the synthetic rows and site are removed. **Not run**: the test licence has 5 of 5 seats taken (see Proof).
- Given the shop and tech, when their inventory, price and dry run are dumped before deploying and again after, then they are identical. **Met**: old (3d8d281) and new inventory byte-identical on both, before and after the scale run (see Proof).

## Verification

**Commands:**
- `node tools/verify.mjs plan-fold plan-choose sticky guide auto-file filing` -- expected: all passed (auto-file's known row red as on main).
- `node tools/deploy.mjs --check` -- expected: box up to date before any box run.

## Implementation Notes

- Built by a fresh implementation agent from this spec and judged against the diff from 3d8d281, not against its report.
- After the diff read, three fixes:
  - While a refresh is booked, the page serves the held inventory rather than scanning again for 15 s.
  - A partial count is held under the key `partial`, so renders serve it until the refresh lands.
  - A fold label equal to a kept label's own text merges with it instead of overwriting it.
- `too_many_labels` never had its own branch: it fell to the generic "The service answered" message. That message is now unreachable, because nothing over 1,500 labels is ever sent.
- The label-map readers named in the Code Map already resolve through `vergeml_filing_label_folders`, so they got the fold-label lookup with no change of their own.

## Review Triage Log

Pass 1 (2026-09-28; blind hunter, edge-case hunter, verification gap):

- **medium, patch:** the draft's paged `vergeml_guide_rule_rows` loop was never driven past one page. The test stub ignored `$after`/`$limit`. Fix: a paging stub and a row over 500.
- **medium, patch:** no row ran an over-budget library through `vergeml_plan_inventory` end to end.
- **low, patch:** the unreachable `'' === fold` guard in `vergeml_plan_fold` is deleted.
- **low, patch:** the test docblock was stale, and its rows ran out of order.
- **low, rejected:** the button's price and the charge could differ when a stale or partial count is served. A count is only served stale when the scan takes over 15 s, which on the box meant about 450,000 pictures. At that size the distinct labels far exceed 1,500, so both reads price at 100.
- **false:** the timeout branch is untested. It ran on the box at 500,000 rows: the cold read took 19.6 s, the refresh was booked, and cron wrote the cache in about 70 s.
- **low, rejected:** the transient's 10-minute TTL could expire during a refresh. The forced read took 21 s at 500,000 rows.
- **low, rejected:** concurrent cold renders have no lock. This only applies to a first-ever render on a very large library, and a lock adds state.
- **low, rejected:** `DISABLE_WP_CRON` hosts never refresh. The plan job already depends on cron, and the plan's forced read writes the full cache.
- **low, rejected:** `folded_labels` and `left_out_pictures` are not shown on screen. Screen copy is Nathan's to write; this is proposed to him.
- **low, rejected:** the fixture's kind roll adds labels beyond "about 40,000". It produced 41,794, which is about right.
- **false:** the `too_many_labels` task claims a removal. There was no path to remove (see Implementation Notes).
- **waived (Nathan, 2026-09-28): AC 2, one real charged plan at 500,000 pictures.** The test licence had no free seat (see Proof). The service's limits are covered by its own tests, and 1,500 labels is under the 3,000 wall and inside the token budget.

## Proof (box, 2026-09-28, plugin at this diff, live service)

- **Shop and tech (AC 3).** The old algorithm (3d8d281) and the new one give byte-identical labels, before and after the scale run:
  - shop: 464 labels, md5 566872c9…, price 38;
  - tech: 678 labels, md5 acbbf3b5…, price 51.
- **The synthetic library.** Throwaway subsite `scale500k` (blog 5): 500,000 described pictures in 194 s. That is a 1.1 GB index, with 41,794 labels over 1,200 classes and 15,000 non-photo pictures.
- **Facts (AC 1).**

  | Read | Time | Peak memory | Labels | Price | What happened |
  |---|---|---|---|---|---|
  | Cold | 19.6 s | 94.8 MB | 1,500 | 100 | Partial scan; refresh booked; cron wrote the cache in about 70 s |
  | Forced | 20.7 s | 94.8 MB | 1,500 | 100 | folded_labels 41,794, left_out_pictures 3,253 |
  | Warm | 4.55 s | 59.3 MB | 1,500 | 100 | Cache hit |

  With 1,200 classes and five kinds there are more fold labels than the budget, so every label folded and the rarest fold labels were left to the matcher.
- **Measured without the call.** `vergeml_plan_label_sums` took 62 s at 83 MB, and the draft's paged scan 11 s. Both leave room inside the 340 s lock alongside the service's runs.
- **Not run: the real plan (AC 2).** The box's test licence has 5 of 5 seats taken, so the throwaway site got none. The service answered 403 before any charge, and the balance stayed at 23,633.
- **Found, outside this story.** `vergeml_ai_activate_site()` reports success on a 200 `{valid:false, reason:'seat_limit'}`.
- **Clean-up.** The synthetic rows are removed and the subsite is deleted (0 `wp_5_` tables left). Blogs 1-4 are untouched.
