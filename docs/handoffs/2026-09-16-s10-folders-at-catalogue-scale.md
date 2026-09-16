# Handover — 2026-09-16, S10: folders at catalogue scale, stories S10.0–S10.3 (Opus 5)

Card: `.harness/active.json` (S10). Plan: `plans/every-picture-a-home.md`
Phase D. Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(each built story carries a **Built** paragraph now). Evidence before: S9's
handoff. Lean held: test first, one mutation run per story (each one seen
red and the code put back), both sites measured, one question round
(the S10.0 mock and the S10.1 rule — both yes).

**Spend:** nothing described; one planner call, 0 credits (folders.spec's
confirm test on ms2, three planted folders, as in S9). The guide suite's
job fit and the sticky suite's pass answer the service from a stub. Box
licence unchanged at 24,208. The one press that would spend is
listed under *Not done*.

Commits, plugin (all on `main`, deployed to the box after each — the shop
site reads the same deployed copy): `3727f6a` S10.1 rule + card, `944c49e`
S10.1 reads the shape, `b6fb0ff` S10.2, `fc9ed4d` S10.3, `246959d` S10.0,
the shop band re-taken, the registers. Service: untouched.

## What was built

- **S10.0 — one progress row** (`js/vergeml-folders.js` `renderProgress`,
  `css/vergeml-folders.css .g-progress`; mock
  `docs/superpowers/mocks/2026-09-16-progress-row.html`, four boards, Nathan's
  yes). Under the button that started the work: the verb and the count, the
  shell's import bar (6 px, accent, radius 44), "about 2 min left" from the
  batches already done, an open sliding sliver with the elapsed time when
  there is no total, and a yellow `nothing moved for 48 s` pill once nothing
  has moved for 30 s — measured on the server's clock (`ticked` and `now`
  on the report). The confirm's countdown left the button; the fill's button
  keeps "Fill 626 pictures" and breathes. Shot on the real screen:
  `tests/ui/shots/folders-progress-row.png`.
- **S10.1 — the confirm asks only what a planner can add.** The spec's rule
  (name in the vocabulary) sent 177 of the shop's 322 folders (14 credits):
  most catalogue leaves are empty, so no picture says their name yet. The
  rule that reads the shape (`vergeml_filing_ask_split`, pure): a leaf under
  a parent is profiled from its name; a parent or top-level folder goes
  unless its name is a library word (equal, or a head noun either way round).
  **Shop 322 → 37 asked, one batch, 0 credits** (was 308 / 37 credits);
  **tech 21 → 3**. Measured read-only: `node tools/box-eval.mjs
  tools/box-ask-split.php --site shop`.
- **S10.2 — a fill that cannot stall.** The cause, found in the code: the
  fill's nudge posted a key to wp-cron.php *without taking the lock* — the
  bug the describe run fixed on 09-03 — so the tick's own chain held the
  lock under a key nothing carried. Now the same nudge as the describe run.
  On top, the guarantee: a poll that finds the event 10 s past due runs one
  50-picture pass inline and books it again already late, so the run goes
  on at the poll's pace whatever cron does; a 120 s pass lock keeps a tick
  and a kick off one slice. Proven in `sticky.php` E1–E4 (30/30).
- **S10.3 — the dry run at any shape.** Pairs = pictures × folders;
  `VERGEML_GUIDE_FIT_PAIRS = 250000` (626 × 319 = 199,694 took 15 s on ms2).
  Above it the turn answers in 38 ms with a pending fit and books a job; the
  job (240 s budget) writes back only to the draft it was asked about; the
  poll re-books a job cron left standing; the row says "Counting 1,000
  pictures against 260 folders · 12 s". `guide.php` F1–F5 (36/36): the job
  counted 260,000 pairs in 12 s with stub vectors.

## The numbers

- Baselines: **tech 4/4 identical**. **Shop re-taken**: Nathan's answers to
  the 45 questions made *Illustrations* (322) and *Diagrams* (323) after S9's
  band, so five gated pictures have a home and one floor picture lands —
  `fits 509 (sure 484, likely 31) + siblings 6 + nothing 111 (floor 42,
  margin 64, gated 5)`; then 4/4. The engine did not move (tech proves it).
- Suites: `filing` 26/26 + 30/30 (row 24 new), `sticky` 30/30 (E new),
  `guide` 36/36 (F new), `surface` 27/27, `roles`, `db-calls`, `hosts`
  13/13, `copy`, `tree-view`, `seed-shop` green; `escaping` 9/10 (the known
  ratio row).
- Mutations run, each seen red then restored: head-noun match removed → pick
  24; leaf rule removed → pick 24; the kick removed → sticky E1; the deferral
  made false → guide F1, F2 (the turn took 12.6 s); `total` dropped from the
  confirm's row → folders.spec width assertion.
- **`folders.spec` on ms2** (vgmls9, both sizes inside the suite):
  **13 passed** (S9: 12 — the progress row is the new one), 1 skipped (GUIDE_WALK), **4 failed on the same fixture preconditions as S9**, none on the screen: three plants refused (no picture on the shop carries an alt; only 44 described pictures unfiled or in To sort where 60 and 268 are needed) and the profile row (no earlier profile before the second confirm). Test 4 pressed a real confirm on ms2 through the fixture, as S9 did (three planted folders, 0 credits, one planner call). The fixture restored the site. Same class as S8/S9: fixtures written for a library in another state — S11 could give fill-fixture.php a pool from any folder.
- Deploy tool: `deploy.mjs` said "unreachable" when php -l refused a file;
  it prints the complaint now.

## Not done, and why

- **The real Unconfirm → confirm on ms2** (the S10.1 gate as a press): the
  session's draft would ask 19 folders, **0 credits**, one planner call
  (~$0.05 on the OpenRouter ledger), and it re-profiles those 19, which
  moves the shop band — a re-take with the reason after. Not pressed: the
  read-only measure is in, and the rule said every credit is said first.
- The service's planner prompt still sees only the batch it is given (the
  "whole tree's paths as context" half of S10.1); with one batch of 37 it
  sees everything that goes.
- Playground's query-count row for the fit (S10.3's mutation as written):
  the fit needs packed embeddings Playground refuses; the box row (F4)
  prints the queries instead.
- S10.4, S10.6 (mock-first, one canvas), S10.5's three rules, S10.5b — S11.
- `vgmls10` (a session admin on the tech site) was deleted at the end;
  `vgmls9`'s password was reset this session and lives in this session's
  scratchpad only — delete the user when C.5 closes.

## Next — S11

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S11: the tree at 300 folders, the media list opens on the pictures, the sheet's rules (S10.4, S10.6, S10.5, S10.5b)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S11)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "js/vergeml-tree-view.js, css/vergeml-tree-view.css (S10.4: the fold control where the eye starts, a hover card on a closed parent)",
    "js/vergeml-tree.js, css/vergeml-tree.css, core/media-list.php (S10.6: one row above the list, the sidebar full height and sticky, the tree first)",
    "core/folder-talk.php (S10.5: vergeml_talk_question_text carries the path when two named folders share a leaf; either/ors of one picture fold into one card per folder pair)",
    "core/filing.php (S10.5 candidate: a head-noun hit never outranks a full hit of the second phrase — judged on both sheets before it moves)",
    "tests/filing/residue.php, tests/filing/pick.php, tests/tree/tree-view.mjs, tests/ui/modes.spec.mjs, tests/ui/folders.spec.mjs",
    "tools/box-seed-shop-tree-b.txt (S10.5b: a retailer's own catalogue, marketing names kept)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-16-s10-folders-at-catalogue-scale.md (this: what S10 built, the re-taken band, the press not made)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.4, S10.5, S10.5b, S10.6 and the Built paragraphs above them)",
    "memory: hetzner-box-fixtures (ms2, vgmls9; box-eval.mjs and box-ui-admin.php), tests-never-touch-live-state, no-pills-brand-square-marks, ui-less-text-pills"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "S10.4 and S10.6 are mock-first on one canvas: one concept line each, tools/shoot-mock.mjs at 1600 and 1280 with --words, Nathan's yes, then the build; every long step in them renders through S10.0's row",
    "S10.5: the path-in-the-question and the per-pair fold change no constant; the head-noun candidate touches the tech baseline — judged on both sheets, and a re-take carries the reason",
    "S10.5b: say the credits before the confirm (parents only now: expect one batch, 0 credits) and before any describe (none: the same 626 pictures); a second band file and a second sheet",
    "Every change measured on both sites: tech 4/4 unchanged, the shop within 3 % or re-taken with the reason",
    "Delete vgmls9 when C.5 closes; make a session admin with tools/box-ui-admin.php and delete it at the end"
  ],
  "gates": [
    "S10.4: tree-view.mjs — hover on a closed parent renders its children as chips with counts and leaves the open state alone; folders.spec at 1600 and 1280 on ms2, the word budget ≤ 80 without the tree",
    "S10.6: modes.spec — the first row's top ≤ 240 px at 1280×800 with no filter set; the sidebar's height ≥ the viewport's; the tree's first folder visible without scrolling; mutation: the sticky removed → red",
    "S10.5: residue.php — a question naming two same-leaf folders carries their paths (mutation: the path dropped → red); the either/or cards of one picture fold per folder pair, the cap counting pairs",
    "S10.5b: filing-baseline-shop-b.txt taken, the second sheet marked by Nathan, the pair of numbers beside the first shop sheet and the tech sheet",
    "node tools/verify.mjs filing surface roles escaping copy tree-view seed-shop → green (escaping 9/10 known); both baselines 4/4"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-16-s10-folders-at-catalogue-scale.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.4,
S10.5, S10.5b, S10.6). State which model you are. This session is S11 of
every-picture-a-home: the tree at 300 folders and the media list, mock-first
on one canvas, then the sheet's rules and the foreign tree. Write the card
from the handoff to .harness/active.json before anything else. Lean: test
first, one mutation check per story, both sites measured, say every cost
before it is spent. End with a handoff carrying the S12 card.
```
