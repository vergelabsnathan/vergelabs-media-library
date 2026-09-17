# Handover — 2026-09-17, S12: the three answers, the sweep as a button, the last word, the tree at 300 folders, the list on the pictures (Opus 5)

Card: `.harness/active.json` (S12, as S11 wrote it). Plan:
`plans/every-picture-a-home.md` Phase D (S11 and S12 lines added, S13–S15
renumbered). Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(S10.4 and S10.6 are built; their Built paragraphs are this handoff).
Evidence before: S11's handoff. Lean held: test first, one mutation per
story (each seen red on the old code or with the mutation in), both sites
measured, ms2 touched only after Nathan said he was off it.

**Spend:** 0 credits on either library; one planner call (the round's second confirm on ms2 profiled one folder, inside the free hundred). The suites ran
in demo mode (`ai-background`), against stubbed services (`sticky`,
`guide`), or planted through the routes (`folders.spec`, `modes.spec`);
the two baselines are dry. The only writes outside the repo: `vgmls12`
made and deleted on the tech site, `vgmls9`'s password reset on ms2
(scratchpad only), and the shop's session/fixture as the specs restore them.

Commits, plugin (`main`, deployed to the box after each; ms2 runs the same
copy through the symlink): `9bb198f` the sweep as a button, `ff39894` the
last word, `dc566d7` the two mocks, `9baaa0f` S10.4, `808acb4` S10.6,
`7b111ef` the registers, `2037294` the shop band re-taken, `ffe3edd` the two
things ms2 showed, then the plan and this handoff.

## Nathan's three answers (taken at the start)

- **The profiles file is back:** `~/.claude/harness/model-profiles.md` (115
  lines, copied from the home tar, no hooks). It profiles Fable 5.1, Opus
  5.1 and Sonnet 5; this session ran the Opus profile by hand.
- **BMAD:** a pilot session later, not now. `bmad-*` in the global
  CLAUDE.md still names skills that do not exist on this machine.
- **The prompt sweep is the button only.** Built as story 1 below.

## Story 1 — nothing re-describes a library by itself (`9bb198f`)

Two auto-starts were removed: the run's end
(`vergeml_ai_run_sweep_stale`, `core/ai-background.php`) and the describe
step's own start when a description landed under a new prompt hash
(`core/ai.php`, reason `prompt_changed`). A prompt change now leaves the
stale count on the dashboard's "Re-describe N images · Costs N credits"
button and waits; a library half on the old prompt ranks a little wrong
until the owner presses, with the number in front of them. The brief's
adopt keeps its own `stale` run — that is a press with the number on it
(`core/brief.php`, docblock corrected).

`tests/ai/background.php` G2 (a run's end under a prompt change: 1,000
stale, no run, nothing booked) and G3 (a describe step with the hash moving
under it — the row planted from the alt-text write, between the step's two
stamp reads) — both red on the old code (`scope: stale, reason:
prompt_changed, booked: true`), 37/37 after. The suite now ticks only the
run it started (a `stale` run the end starts is left where it is — under
the old code the loop would have mocked over the real library, the
2026-09-17 accident) and declines the nudge through
`vergeml_ai_run_should_nudge` as well as answering wp-cron.php. The tech
library after: 1,000 rows on one hash, stale 0, nothing booked (read-only
check).

## Story 2 — × on a folder's last word means no words (`ff39894`)

The draft carries an explicit empty, `nowords`, set by the × that empties
the list and cleared by a word added back; a bare `[]` still means "the
draft says nothing" (the 09-16 scare: a restored draft carries `[]` on
every folder). The row says **no words** (a quiet pill); the confirm
writes a name-only profile (`vergeml_filing_profile_build` with the flag,
kept a day like a plan so Unconfirm → Restore brings the words back); the
planner is never asked about it; the dry run scores it from the name.

`tree-view.mjs` C7 rewritten — **the old C7 never exercised the ×**: the
view was made without `editable`, so no × was rendered and `if ( x )`
skipped it, and `onEdit` was a no-op, so "the draft is empty after ×" held
because the draft had been set empty by hand. Red before (the stored word
came straight back), 62/62 after. `sticky.php` C11–C13 (confirm → a
name-only profile with the plan kept, Restore, confirm again) red on the
old code, 38/38 after.

## S10.4 — the tree at 300 folders (`dc566d7`, `9baaa0f`)

Mock `2026-09-17-tree-fold-hover.html`, Nathan's yes. The fold is an icon
pair **first** in the tree's head: the row's own twist glyph, open and
closed, in the state switch's box; the pressed half is the state the tree
is in; no words (the labels are the tooltips). A closed parent under the
pointer (or with focus) shows its children as the tree's sibling chips in
the hover card — ten with counts, "+n more", empty ones in grey — a
preview that leaves the tree closed; the wiring waits 250 ms on an
unchanged row so a pointer crossing the tree does not open a card on every
row. `showHover` alone decides (closed, never open): the first build had
the guard in two places and the mutation survived, so the wiring now
listens on every parent. `tree-view.mjs` H1–H3 red before, the open-parent
mutation red, 65/65; `folders.spec`'s sizes test asserts the pair and the
card inside the viewport at 1600 and 1280 (green on the tech site; the
1280 shot: the card on Data centres, the row still closed).

## S10.6 — the media list opens on the pictures (`dc566d7`, `808acb4`, `ffe3edd`)

Mock `2026-09-17-list-on-the-pictures.html`, Nathan's yes. One row at the
form's top: the folder that is showing as a chip — the trail, parents
clickable, the count, × back to every file through the tree's own
`select()` — core's search form, a **Filter** chip whose card holds core's
selects (Apply; Reset only when something is set; "Filter · n" on the chip),
the view switch and the pages at the right. Bulk actions (with the term
select `core/bulk-terms.php` put among the filters) take the row while a
row is checked; the pages may drop under them then. Core's two rows are
emptied and hidden; **core's controls are moved, never rebuilt** (they stay
inside `#posts-filter`, so a select still submits and a page link still
pages). The panel is the viewport's height, `position: fixed` beside the
list and set from the wrap's edge (`placeListPanel`, a ResizeObserver on
the wrap — the admin menu's width is not ours to know); the tree first, the
Filters and AI folders groups after it, closed until opened and remembered
(`core/rest-tree.php` defaults 0). Not a wrapper: the file's rule is that
nothing of core's is reparented but the table strip.

Measured on `modes.spec`'s new test (the other plugins' notices above the
form taken off, because the tech site is buried under 979 px of them):
first row **429 → 201 px** on the tech site, **453 → 180 px on ms2**; the
panel 748 px fixed in an 800 px viewport. The fixed rule's mutation: red
(absolute, the panel back to 748 in flow). The folder-filter test opens the
card first, and holds Unfiled to the library's own count (the tech site has
0 unfiled; the old row asserted the library's state). The row-height and
"nothing covers" gates green in both modes.

**Two things only ms2 could show** (`ffe3edd`): with a folder remembered,
the tree re-fetches the list and swaps in fresh `.tablenav`s, so a full
nav reappeared under the row and the row's pages read 627 against 5 — the
row now takes the fresh nav's parts on every swap; and the crumbs the tree
drew above the form doubled the chip — they fold into it, as the mock had
it.

## The gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy
tree-view seed-shop ai-background` → green, `escaping` 9/10 (the known
row); the registers regenerated (one new sink: the fold pair's
`chevron()`, read by hand). Tech baseline **4/4 identical**.

**The shop band moved, and not by the code** (tech proves it): the
baseline was re-taken at 16:40 UTC on 09-16; at **17:23 UTC** a confirm
re-profiled **66 folders** through the planner (Cameras: `camera,
photography equipment, cameras`; Beauty, Make-up, Audio…), and on 09-17
at 06:07–06:15 UTC the tree was confirmed again and filled (510 of 626),
with 31 hand placements since — Nathan's own use of the shop. Re-taken
with that reason (`2037294`): `fits 493 · sure 467 · likely 51 · siblings
25 · nothing 108 (floor 46, margin 60, either 60, gated 2)`. **A finding
for S10.7:** *Cameras* now claims `camera` as a plan class while its child
*Digital cameras* is name-only with the head noun `cameras`, so twelve
camera pictures that were `ok` in the child come back as `siblings`
questions (6 → 25). A parent's planner profile that claims the child's head
noun is a profile problem, not a threshold one.

## On ms2, with Nathan off it

- `modes.spec` (list): the new test, the folder filter, "nothing covers" —
  green; first row 180 px.
- `folders.spec`: **9 of 17 green, 8 red, the walk skipped** (14 min).
  The screen itself renders (the fold pair, the shop's tree, Nathan's five
  open questions in the rail — one of them the Cameras finding above:
  "9 pictures fit both Digital cameras and Action cameras"). The reds are
  the spec's assumptions about the tech site meeting the shop's live state,
  not S12's code — the same standing as S10's "folders.spec on ms2 — see
  the handoff":
  - `fill-fixture.php` exits: "only 0 described pictures carry an alt on
    the file; 4 needed" — the shop's alts were never applied to files
    (Step 4 there). Three tests fall with it.
  - **"fill at 1600×1000: 92 words without the tree"** — a real finding:
    the Fill step with three either/or cards visible (the S10.5 fold:
    "9 pictures fit both Digital cameras and Action cameras" + Keep / Split
    / Let me look, three times, "2 more", "Leave the rest") reads 92 words
    on a shop with five open questions; the tech site's planted state had
    no cards on screen for that test. Copy or budget, S13's call before
    the next fill on ms2.
  - "opens on the session's step" and "a confirmed tree can be filled
    again": the shop's session is confirmed and filled by Nathan (five
    questions open), and the spec's `plant()` writes a draft over it and
    expects its own step; `restore()` puts Nathan's session back (read after
    the round below: confirmed, six turns, the five questions).
  - "the words a folder takes" (5.1 min): the Restore offer on a tree
    whose profiles Nathan's own confirm replaced within the day.
  A `--library shop` shape for `folders.spec` (its fixture pooling from To
  sort, its expectations read off the session) is S13's, if ms2 stays the
  second gate; the alternative is to hold `folders.spec` to the tech site
  and keep `modes.spec` + the baseline as ms2's gates.
- `round.spec`: **green, 3.0 min** — snapshot 627 relationships, 323 folders, 31 marks; confirm 0 folders profiled; fill 626 looked at, 510 placed, 5 questions, 26 s; "Keep them in Phones" moved 1; the second fill 485 placed, 0 questions, none about the answered picture; a word off, confirm (1 folder profiled), the third fill 479 placed, 1 question, 12 not placed; restored 627 (627), 323 (323). After it the shop's session reads confirmed, six turns, Nathan's fill state with the five questions (read-only check; only confirmed_at is the restore's).

## Not done, and why

- S10.5 rule 3 (the head noun) and S10.5b — untouched, S13 (rule 3 moves
  the tech baseline; both sheets first).
- The fill's progress row at 1600×1000 with every parent open (S11's
  "seen, not fixed", design, mock-first) — not reached.
- The shop's number for the S10.5 fold — waits for Nathan's next fill on
  ms2 (`tools/box-question-grain.php`, read-only).
- `vgmls12` on the tech site: deleted at the end of this session. `vgmls9`
  on ms2 stays until C.5 closes (password in this session's scratchpad
  only; reset it with `box-session-admin.php` → `tools/box-ui-admin.php`).

## Next — S13

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S13: the fill learns from its own placements (S10.7), the picture's own words (S10.9), the head noun on both sheets (S10.5 rule 3), the foreign tree (S10.5b)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S13)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "core/filing.php (S10.7: a folder with ≥ 3 described members profiled from them — top object words, centroid, source: members — outranking plan and name; rebuilt when the members change; S10.9: a third phrase list from filename, title and alt at 0.85; S10.5 rule 3: a head-noun hit never outranks a full hit of the second phrase, 1.0 → 0.9)",
    "core/folder-talk.php, core/guide.php (S10.7 rounds: round 1 from the plan, round 2 from members; the Fill step's rounds line; the pick's tie by the third list)",
    "tests/filing/pick.php (the member profile row, the filename tie row, the head-noun row; a mutation each)",
    "tools/box-filing-baseline.php, tools/filing-baseline-check.mjs (both bands re-taken with the reason after rule 3 and S10.7; the sheets first)",
    "tools/box-seed-shop-tree.txt or a second catalogue file (S10.5b: a real retailer's tree, ~300 folders, pasted on ms2 after Unconfirm; filing-baseline-shop-b.txt and a second sheet)",
    "js/vergeml-folders.js (the fill's progress row visible at 1600×1000 with every parent open — design, mock-first, if reached)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-17-s12-the-tree-and-the-list.md (this: the three answers, the sweep as a button, the last word, S10.4, S10.6, the shop band re-taken and why, the Cameras finding)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.7, S10.9, S10.5 rule 3, S10.5b)",
    "memory: hetzner-box-fixtures (ms2, vgmls9, measure list changes on ms2), tests-never-touch-live-state, model-spend-discipline, shared-classes-break-the-matcher"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "S10.5b's catalogue and its confirm (~36 credits, 0 after S10.1's split — say the number) are Nathan's yes before the paste",
    "Rule 3 and S10.7 move both baselines: the sheets are judged before a re-take, and the re-take carries the reason",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire",
    "Every change measured on both sites: tech 4/4 unchanged or re-taken with the reason, the shop within 3 % or re-taken with the reason"
  ],
  "gates": [
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background → green (escaping 9/10 known)",
    "S10.7: on the tech library, round 2's sure ≥ round 1's by 10 points or the handoff says why (the sheet re-taken, Nathan's marks)",
    "both baselines 4/4 or re-taken with the reason; folders.spec and modes.spec green on both sites"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-17-s12-the-tree-and-the-list.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S13 of
every-picture-a-home. Write the card from the handoff to
.harness/active.json before anything else. S10.7 first (the member profile,
then rounds), then S10.9, then S10.5 rule 3 on both sheets, then S10.5b
with Nathan's yes on the catalogue. Test first, one mutation per story,
both sites measured, say every cost before it is spent, never touch ms2
while Nathan is on it. Talk plainly. End with a handoff carrying the S14
card.
```
