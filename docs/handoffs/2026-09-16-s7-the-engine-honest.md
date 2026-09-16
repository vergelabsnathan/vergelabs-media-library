# Handover — 2026-09-16, S7: C.1 + C.2, the engine made honest on the box's evidence (Opus 5)

Card: `.harness/active.json` (S7). Plan: `plans/every-picture-a-home.md`
Phase C. Evidence: `2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md`.
Lean: test first, one mutation check per task, **no fill on the box** —
every number below is a dry run (`VGML_FRESH=1`: a hand placement scored
like any other, nothing written) or a fixture. Spend: nothing. Nathan's walk
stands on the box (61 in Server racks by hand, 327 in To sort).

Commits: `591508f` (C.1), `8b60e3f` (the dry-run tools), `85d1692` (C.2 +
the baseline gate). Deployed and verified on the box after each
(`tools/deploy.mjs --box`, 137 files re-hashed).

## What changed

### C.1 · the matcher tells folders apart (`core/filing.php`)

- `vergeml_filing_settle_claims()` counts, once for the set, how many
  folders hold each class (`'shared' => [key => k]`, plural folded, on every
  profile). Every caller reaches pick through it (profiles, the draft fit).
- In `vergeml_filing_pick()`: a class on k folders is worth **1/k**; the
  picture's second phrase weighs **0.85** (first 1.0); the folder's own leaf
  name, matched exactly, weighs **1.0 at any rank** and is never diluted;
  a margin between two folders that are not siblings under a folder returns
  `children => [best, runner]` and is an **either/or** question, not
  residue; a pick gated everywhere carries `kind` out.
- The tally gains `either` (a sub-count of `nothing`); the run keeps
  `state.either["a:b"] = { ids: picture => its best, children: term => n }`;
  `vergeml_filing_questions()` emits `e:a:b` (kind `either`, answers
  `put-in:a` · `put-in:b` · `split` · `leave` · `show-me`); the sentence is
  "%1$s pictures: %2$s or %3$s?". `answer_plan` treats it like a sibling
  question (a map picture ⇒ best). "Leave the rest" already answers it.
- FLOOR 0.55, MARGIN 0.08, SURE 0.70 did not move.

### C.2 · the residue grouped and labelled honestly (`core/filing.php`, `core/folder-talk.php`)

- `vergeml_filing_residue_groups()`: kind first (a screenshot never joins a
  photo group; each non-photo kind is its own group, named for its kind, no
  naming call); small photo groups merge only into a near-identical one —
  `class_match ≥ 0.95` **or** centroid cosine **≥ 0.8** (was 0.5), the
  centroid recomputed over the merged vectors; after merging `class` is the
  **majority** phrase with its `share`; groups under 5 and beyond the
  **K = 8** largest are one card (`more`); pictures with no class are the
  last (`unreadable`). `group_key` also folds `switches` → `switch`.
- `vergeml_filing_group_nearest()`: "Put in X" only when X is the
  per-picture nearest for **≥ 60 %** of the group and takes the group's
  kind, is not locked, and is not for an audience the group does not name.
  `state.residue` is now `picture => nearest` (a list from an in-flight run
  is read as nearest 0). The centroid-nearest function is gone.
- Sentences (`vergeml_talk_question_text`, pinned in `tests/tree/copy.mjs`):
  "%1$s look like %2$s" from share 0.7; "%1$s mixed, mostly %2$s" under it;
  "%s more, in small groups"; "%s with nothing to go on" (the old "I can't
  read" is struck).

### The gates, as run

- `node tools/verify.mjs filing` → pick 16/16, residue 30/30. Mutations
  run and restored: the 1/k weight off → pick row 15 red alone;
  `GROUP_NEAR` back to 0.5 → residue rows 1, 1b, 1d, 4e, 14, 15 red (the
  forklifts merge at 0.5). The other mutations the plan names have their
  rows (13 for the second phrase and the leaf lift, 4 for the cross-parent
  branch, 1 for the kind key, 1e for the cap, 5a/5b for the majority); not
  all were run this session — one per task, as briefed.
- `copy` 58/58, `roles` 19/19, `surface` 26/26 (register regenerated: line
  drift only), `escaping` 9/10 (the known text/HTML ratio row).
- `fill-walk` **not run**: it runs a fill on the box. Its tally compare
  gained `either`.
- **The baseline gate** (`tools/filing-baseline-check.mjs`) grew the outcome
  band: `# tally …` from `tools/box-filing-baseline.php` (fresh picks),
  any outcome moving more than 3 % of the pictures fails; `--retake
  "<reason>"` writes the reason into the record. Re-taken once, after
  C.1+C.2, over the 1000-picture library (the old record was 641 pictures
  that no longer exist); then run again → 4/4. **Frozen from here**: C.3,
  C.4, every service prompt or describer change leaves it green or re-takes
  it with the reason.

## The numbers, beside yesterday's line

Fresh dry run over the box's 1000 (`tools/box-refile-all.php`, `VGML_FRESH=1`;
the "before" line was taken with the old engine the same way and reproduces
the walk exactly):

```
before: looked 1000 = fits 558 (sure 499, likely 113) + siblings 54 + nothing 388 (floor 245, margin 109, gated 34)
after:  looked 1000 = fits 512 (sure 264, likely 262) + siblings 14 + nothing 474 (floor 401, margin  39 of which either 39, gated 34)
```

- **Margin 109 → 39**, and all 39 are now seven either/or questions
  ("15: Components or Batteries?", "13: Phones or Batteries?", …).
- **Sure halved** (499 → 264), floor up (245 → 401). The sample says which
  kind of caution that is (below). Server racks now takes its racks at
  0.91–0.94 (they were "Hardware 0.87 / Server racks 0.83" margins).
- The 61 is gone: 31 racks placed; the towers, fibre and cable drums sit in
  their own small groups; the robots in Robotics.

**The residue, grouped (10 cards over 435):**

```
 29  electric vehicle charging station  share 0.38  put-in Energy / Batteries
 24  illustration  [illustration]       share 1.00  put-in 2026 / September      <- the date folders are the only ones taking illustrations
 21  screenshot    [screenshot]         share 1.00  (no folder takes them)
 21  home office setup                  share 0.19  nearest Space 7/21 -> no put-in
 14  network switch                     share 0.79  put-in Data centres / Server racks
 14  quadcopter drone                   share 0.50  put-in Robotics
 14  vr gaming setup                    share 0.21  put-in Hardware / Phones
 14  electronic waste                   share 0.79  put-in Hardware / Components
280  (more, in small groups)            nearest People / Conference talks 48/280 -> no put-in
  4  (nothing to go on)
```

**Where the design bites:** with today's profiles the residue is 435 and
the K = 8 cap folds **280 of them** into one leave/show-me card — the
"conference panel 9", "exhibition booth 6", "3d printer …" groups are
readable but not among the eight largest. K is Nathan's number (plan stop
point). Two honest options for S8/S9, neither built: split the small-groups
card by the pictures' nearest folder when a 60 % majority exists inside a
sub-cluster (the 280 hold 48 nearest Conference talks); or cap on pictures
rather than groups. The real fix is upstream: C.4 gives Conference talks the
describer's own words and those 48 never reach the residue.

## The sample, re-taken on seed 133 without a fill

`tools/box-folder-quality.php` gained `VGML_DRY=1 VGML_SEED=133`: fresh
picks, pools from the picks, the same seed, each card with "same folder as
the last fill" or "last fill: X", Nathan's 2026-09-16 marks carried over
where the folder is unchanged (the verdict table is in the tool). Sheet:
`docs/superpowers/mocks/shots/2026-09-16-quality-sample-c1-dry.html` —
30 sure of 264, 30 likely of 262, **only 3 marks carry over** (the pools
changed), so **the new sample's two percentages are Nathan's to give: 57
cards await a mark.** Until then, the honest pair on the *same 60 he marked*:

| | sure (was 14/30 = 47 %) | likely (was 12/30 = 40 %, 11 too broad) |
|---|---|---|
| same folder, was right | 9 | 4 |
| same folder, was wrong | 11 | 5 |
| moved to another folder (needs a look) | 3 | 16 |
| no longer placed | 7 (5 right, 2 wrong) | 5 (3 right, 2 wrong/broad) |

- Of the 16 moved likely, **8 are the C.1 fix the handoff predicted**: the
  wind turbines marked *too broad* in Energy land in Energy / Wind at
  0.85–0.87. Six go one deeper, Data centres → Server racks (0.68–0.94);
  two PC towers Hardware → Components.
- Of the 11 wrong still sure, **10 are C.4's**: Components' profile
  (106075, 106729, 106728, 106074, 106061), Satellites' `diagram` class
  (106798, 106441, 105969), Conference talks 106209, Space 106535; Hardware
  106027 the eleventh. C.1 could not move these and did not; same-folder
  precision stays at 45 %.
- The 3 moved sure: 106603 solar panel Energy → **Solar** (the handoff's own
  example, fixed); 106224 attendees Interviews → People; 106029 graphics
  card Laptops → Hardware (Components would be right; the profile again).
- **The cost**: 5 right Conference talks placements fell to the floor
  (0.47–0.55). `people` sits on three folders and now counts 1/3, and
  Conference talks' profile has no word of its own in the describer's
  vocabulary — its pictures are "conference panel", "conference keynote
  presentation", "conference hall". Withdrawn: 12 (8 right, 4 wrong). A
  withdrawn right one costs a press in the residue; a kept wrong one costs a
  wrong folder that looks right. That is the trade the floor makes, and it
  is C.4 that makes it unnecessary.

The per-picture lines (was / now / fate) are in the sheet's leading HTML
comment.

## Not done, and why

- `fill-walk` not run (a fill on the box; the brief said none).
- The `either` card is not yet exercised on the screen: the JS renders
  answers from the labelled map, so it should render; not proven with a
  shot. C.3's spec is where it gets one.
- The date folders (`2026 / September`, `2026 / …`) take illustrations by
  their name-derived kinds and so become the illustrations' "Put in";
  whether those folders should exist at all is Nathan's.

## Next — S8: C.3 the screen + C.4 the service seam, fresh session

Card, to `plugin/.harness/active.json` before anything:

```json
{
  "phase": "Every picture a home — Phase C, S8: C.3 one press, one honest answer (the screen) + C.4 the planner speaks the describer's words (the service seam)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase C: C.3, C.4)",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "js/vergeml-folders.js",
    "js/vergeml-tree-view.js",
    "css/vergeml-folders.css",
    "core/guide.php",
    "core/folder-talk.php",
    "core/filing.php (profile_ask sends the vocabulary; class_match canon fold; the kind-word guard)",
    "core/post-folders.php (deferred term counting)",
    "tests/ui/folders.spec.mjs",
    "tests/ui/fill-fixture.php",
    "tests/filing/pick.php",
    "../service/lib/anthropic.ts",
    "../service/lib/describe.ts",
    "../service/lib/*.test.ts",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-16-s7-the-engine-honest.md (this: the numbers, the 280, the Conference talks cost)",
    "docs/handoffs/2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md (seams 3 and 4: the screen, the describer → planner)",
    "plans/every-picture-a-home.md (C.3, C.4 and their proofs)",
    "core/folder-talk.php vergeml_talk_answer, vergeml_talk_questions; core/guide.php vergeml_guide_rest_answer",
    "js/vergeml-folders.js onAnswer / renderQuestions / refreshTree",
    "memory: hooks-block-curl-and-key-paths, tests-never-touch-live-state, model-spend-discipline, ui-less-text-pills"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The box is Nathan's after his walk (61 in Server racks by hand, 327 in To sort); no fill by script; folders.spec plants its own questions and restores",
    "Time C.3 on both sites — the box (36 plugins, 2.9 s a round trip) and Playground (the plugin's own cost) — and print both rows; the plugin's answer round trip under 400 ms at 30 questions on Playground",
    "C.4 touches the service: say the cost before any planner or describer call; re-profiling the box's folders is a planner call and Nathan's to press through the screen, not a script",
    "The baseline gate is frozen: node tools/filing-baseline-check.mjs green after every change, or re-taken with --retake and the reason in the handoff",
    "The 60-picture sample: Nathan marks the dry sheet (57 cards), the two numbers go in S8's handoff beside 47 % / 40 %; after C.4 the sheet is re-taken dry on seed 133 again",
    "The 280 in one card is K = 8 on a 435 residue: Nathan's number; propose, do not move it"
  ],
  "gates": [
    "folders.spec: 30 planted questions incl. one either and one more card; a 409 intercepted → its text on the card; the answer response has no questions[].sample; one childList batch on .vgml-list per answer; the answered card is the same DOM node; timing rows for both sites",
    "modes.spec: the Placed by hand filter lists exactly the _vergeml_placed_by = user rows; a drag out clears the mark",
    "vitest: the profile and plan prompts carry the top object terms and the one-folder rule; describe.test.ts: the schema description says 'specific; class'",
    "tests/filing/pick.php: class_match('data centre','data center') === 1.0; a planner answer with a neighbour's class is dropped and logged; 'diagram' as a class moves to kinds",
    "folders.spec: class pills on a confirmed-tree row match the stored profile; × removes; #word adds; Unconfirm → Restore puts the previous profile back",
    "node tools/verify.mjs filing surface roles escaping copy tree-view → green (escaping 9/10 known); node tools/filing-baseline-check.mjs → 4/4 or re-taken with reason"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-16-s7-the-engine-honest.md, then
plans/every-picture-a-home.md C.3 and C.4. State which model you are. This
session is S8 of every-picture-a-home: the screen made honest (C.3) and
the planner made to speak the describer's words (C.4). Write the card from
the handoff to .harness/active.json before anything else. Lean: test first,
one mutation check per task, no fill on the box, measure on both sites.
End with a handoff carrying the S9 card (C.5 the second library, or the
Folders polish card if Nathan does not buy C.5).
```

Held behind S8: **the new sample's verdict** (Nathan marks the dry sheet;
the two numbers into S8's handoff), the K question (280 in one card), the
date folders, then **C.5** (a second library, Nathan pays), the Folders
polish card, B.6.
