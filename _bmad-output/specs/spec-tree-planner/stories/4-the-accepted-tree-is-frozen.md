---
title: 'The accepted tree is frozen'
type: 'feature'
created: '2026-09-28'
status: 'in-review'
baseline_commit: '4397b155b5b181ad8dc706efcd5bd99488c0107e'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The plan decides which folder every label belongs in, then the fill throws that away and re-decides each picture with the matcher: on the shop 141-179 of 626 pictures would stay unfiled, and on a site that already has folders the plan proposes near-copies beside them ("Bags and luggage" beside "Bags & Luggage", mostly empty).

**Approach:** Keep the plan's label → folder decision. A planned folder whose pictures already sit mostly in one existing folder becomes that folder. When the owner fills an accepted plan, the label map is frozen, and every picture whose label is in it is filed there — by the same rule in the dry run, the fill and later filing — with the matcher only for labels the map does not hold.

## Boundaries & Constraints

**Always:** A hand-placed picture and a locked folder win over the map, as they win over the matcher today. A product category wins over the map (the product rule). Folders stay `media_category` terms; an existing folder keeps its name and place. The map is keyed by label text ("platform sneaker; footwear"), never by the inventory's `l0` ids, which change. The dry run's counts come from the same rule the fill uses. Undo clears the map with the rest of the run.

**Never:** No filing before the owner fills. No rename, move or delete of an existing folder. No model call added to the fill or the count. Nothing new sent to the service.

**Decisions (Nathan, 2026-09-28):** New pictures: the Auto-file sweep files a picture whose label is in the map without waiting for the folder to have earned it; a label not in the map keeps today's earned rule. Planning again on an accepted tree stays refused (today's 409); a free re-plan that keeps the frozen folders and proposes only growth is its own story. The spec is kept whole.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Label in map | picture labelled "chelsea boot; footwear", map says Boots | filed in Boots, why `label` | N/A |
| Label not in map | a label described after the fill | the matcher decides, as today | N/A |
| Hand-placed / locked | picture placed by the owner, or folder locked | untouched | N/A |
| Product picture | picture of a WooCommerce product | its product category, not the map | N/A |
| Map target gone | the owner deleted the mapped folder | the matcher decides | stale entry ignored |
| Existing folder holds most | ≥ half of a planned folder's pictures sit in "Bags & Luggage" | the draft uses "Bags & Luggage", no new folder | N/A |
| Two planned → one existing | both hold mostly "Kitchen" pictures | both map onto "Kitchen" | N/A |

</frozen-after-approval>

## Code Map

- `core/plan-tree.php` -- `vergeml_plan_draft` (:403) turns labels into `classes` and merges by name (:444); the existing-folder mapping replaces the merge by name. Keep the label → draft key map (label text) on the session draft's side, since `vergeml_guide_clean_draft` (guide.php:618) whitelists fields.
- `core/guide.php` -- `vergeml_guide_rule_rows($tax,'all',['filing','terms'])` (:2638) gives kind, filing and `in_terms` per picture in one query: the source for "where do this label's pictures sit now". `vergeml_guide_apply_plan` (:2330) builds `$talk_key` (:2375) and passes `opts`; the draft fit (:1769) runs `product_folders` (:1930) then `vergeml_filing_count` (:1931) -- the label step goes between.
- `core/folder-talk.php` -- `vergeml_talk_apply` (:623) resolves talk keys to term ids in `$ids` (:947) as it does `fallback` (:881); store the resolved label → term_id map there and in its own option. `vergeml_talk_refile_run` (:1007): the label step beside `product_folders` (:1127); backfill the new state key (:1038). `vergeml_talk_undo` (:1925) clears it.
- `core/filing.php` -- copy the product path: a row helper beside `vergeml_filing_product_folders`; `vergeml_filing_facts` (:1816) carries `label_folder`; `pick_rules` (:1926) returns `fits`/`label` after placed and locked, after product; add `label` to the no-override check in `pick_model` (:1882) and skip such rows in `ask_model` (:1741). Guard: the target folder is not locked and still exists.
- `core/auto-file.php` -- `vergeml_autofile_suggest` (:241, pick at :283) and the `earned` gate (:573): a label hit bypasses it.
- Tests that pin today's behaviour and must stay green: `tests/filing/sticky.php` D1-D7, I1-I5, J1-J8 (they write the talk state by hand: the new key defaults to empty); `tools/box-fill-walk.php` E3, F1, G9; `tests/tree/auto-file.php`; `tests/tree/guide.php` C1-C5, F4b, J1-J3.

## Tasks & Acceptance

**Execution:**
- [x] `core/plan-tree.php` -- map each planned folder onto the existing folder holding ≥ half its pictures (from `in_terms`), else a new folder; keep the label text → draft key map in the session beside the draft -- the near-copies and the map's source.
- [x] `core/guide.php` -- pass the label map to `apply_plan`'s opts; the draft fit applies the label step -- counts match the fill.
- [x] `core/folder-talk.php` -- resolve and store the frozen label → term_id map in its own option; the refile run applies the label step; undo clears it.
- [x] `core/filing.php` -- the label row helper and the `pick_rules` branch, in the product path's shape.
- [x] `core/auto-file.php` -- `vergeml_autofile_suggest` takes the label step before the pick; a label hit files without the `earned` gate.
- [x] `tests/filing/sticky.php` -- new rows: label wins over matcher, loses to hand-placed, locked and product, falls back when the target is gone.
- [x] `tests/tree/plan-choose.php` -- the existing-folder mapping, two-onto-one, and the map's label keys.

**Acceptance Criteria:**
- Given the shop planned and filled, when the fill ends, then at most 10 % of described pictures are unfiled and the dry run's "would stay unfiled" equals the fill's.
- Given the shop's existing tree, when planned, then no new folder is proposed whose pictures sit ≥ half in one existing folder.
- Given a filled plan, when the fill runs again with nothing changed, then no picture moves (CAP-2).
- Given the undo, when it runs, then the map is gone and filing is the matcher's again.
- Given a filled plan, when a new picture with a mapped label is described and Auto-file runs, then it is filed in its label's folder.

## Verification

**Commands:**
- `node tools/verify.mjs plan-choose filing sticky guide fill-walk auto-file` -- expected: all passed (auto-file red on main before this story; its three rows unchanged).
- `node tools/deploy.mjs --check` -- expected: box up to date before any box suite.

**Manual checks:**
- Press Plan my folders on the shop in a browser, fill, and score with `tools/tree-lab.mjs current`: unfiled ≤ 10 %, recovered ≥ 35/47, purity ≥ 75 %. Restore the shop from `tools/box-tree-snapshot.php` afterwards.

## Implementation Notes

- Built by a fresh implementation agent from this spec; judged against the diff from 4397b15, not its report.
- The exact-name merge is kept as the fallback when no existing folder holds half a planned folder's pictures (row 9, the `&amp;` fix, relies on it).
- Added after the diff review: "To sort" (slug `to-sort`) is never a mapping target -- its pictures are what a plan is for (`plan-choose` row 13; turns red without the guard). View folders are not excluded: they are derived from the tree's shape and rare; recorded as a known limit.
- Label-filed pictures are not marked placed (unlike product): a stale target must stay able to fall back to the matcher; every fill re-resolves the map.
- Suites: plan-choose 15/15; filing, sticky (K1-K6 new), fill-walk, guide passed on the box at this tree; auto-file 22/23, the one row red on main as well.
- After review: nine patches (the JS carries label_map through withOrigin; locked folders and views are not targets; ties go to the lower term id; Auto-file reads the map per call; tests through apply_plan, talk_apply, clean_draft and Auto-file). Suites at this tree: plan-choose 15/15, sticky 75/75, guide 59/59, filing, fill-walk, tree-view, talk-chips, folders-shop, structure, hosts, roles, surface passed; auto-file 24/25 and db-calls red as on main.
- Status stays in-review, not done: AC 1-2 need the spec's manual check (a real plan and fill on the shop, restored from a snapshot after); no dedicated test for Auto-file's label hit (AC 5) or a second fill moving nothing (AC 3).

## Spec Change Log

## Review Triage Log

Pass 1 (2026-09-28; blind hunter, edge-case hunter, verification gap):
- high, patch: js/vergeml-folders.js withOrigin() rebuilt the draft from five named fields and dropped label_map on every edit, paste and answer -- the map survived only an untouched accept.
- medium, patch: nothing drove the map through apply_plan -> talk_apply -> the stored map (sticky K wrote the state by hand) -- tests in guide C and sticky.
- medium, patch: the real clean_draft's label_map handling ran in no test (plan-choose stubs it) -- guide B.
- medium, patch: Auto-file's label hit and its earned bypass had no test -- auto-file.
- medium, patch: the majority mapping could fold a planned folder onto a locked folder that the fill then refuses -- locked folders are not targets.
- low, patch: a tie between two existing folders fell to row order -- lower term id.
- low, patch: the label pick refused a locked target but not a view, which the model pick refuses -- same guard.
- low, patch: Auto-file cached the map in a static for the whole process, stale after an undo -- read per call.
- low, patch: auto-file.php's header rule "filing is earned" no longer held for label hits -- one line.
- medium, defer: the dry run's label step has no test -- AC 1's manual check compares the dry run with the fill; logged in deferred-work.md.
- false: the same label text in two planned folders overwrites the map -- the service assigns each label one path (assignmentOf), so a label is in one folder.

## Proof (shop, 2026-09-28, plugin 3d8ed8f, service a3d3eea)

Plan -> dry run -> confirm -> fill through the routes the screen calls; the shop snapshotted first (/var/tmp and /root: vgml-shop-before-story4.json) and restored exactly after (323 terms, 627 relationships, 31 placed_by; the label map option deleted).

- Plan: 15/15 runs; the tightest kept, 76 labels placed; the draft has 332 folders of which 9 new (the near-copies are gone), 391 labels in the map.
- Fill: 33 s, 481 moved, 4 described pictures in no folder.
- Not met: 85 pictures stay in "To sort" -- 14.2 % unfiled against the 10 % bar -- and the dry run said 1. To sort is locked by design ("the fill never files into it or out of it", `vergeml_talk_to_sort`), so a picture in it is `in_locked` and kept; 65 of the 85 have a label in the frozen map. The dry run does not count pictures kept in To sort as unfiled, so it and the fill disagree on exactly these.
- Open for Nathan: may a frozen plan take a labelled picture out of To sort (a hand placement staying where it is)?
