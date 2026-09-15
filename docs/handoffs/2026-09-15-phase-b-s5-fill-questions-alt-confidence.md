# Handover — 2026-09-15, every-picture-a-home Phase B, S5: B.4 + B.5 (Opus 5)

One session in `plugin`, lean, no subagents. Both tasks on Opus 5 (B.4 was
the Fable candidate; Fable was not used). Deployed to the box after every
change (`node tools/deploy.mjs --box`, every file re-hashed there). Spend:
**nothing** — no model route fires anywhere in this work; the specs plant
state and the alt step is a copy from the catalogue.

> **One thing to know first.** A mutation run wiped the file alt text of
> every picture on the box (details under *What Nathan should know*). The
> 100 that had one were put back from the catalogue — a reconstruction
> from the record, not a restore of a snapshot. The box reads exactly as
> found (900 without alt text · 100 have one, 513 in no folder, no To sort),
> but if any of those 100 alts had been typed by hand, that text is gone.

## What the screen is now

**Step 3 · Fill** (`js/vergeml-folders.js`, `renderFill()`), four states as
the approved fill mock (`docs/superpowers/mocks/2026-09-15-fill-questions.html`):

| state | pill row | the tree | under it |
|---|---|---|---|
| running | `placed · sure · likely · to sort` from the report's `tally`, climbing | every row `landed of total` with its bar (`view.setProgress`, from S2) | `Filling 128 of 1,000` · Stop |
| asking | `placed · N questions (yellow) · to sort` | the same tree, open | `.g-qs` to the right at 1600 (one column under 1000px): three cards, the answered one kept with its result line until the next answer, `N more` · *Leave the rest*; Undo when a Move's record exists |
| done | `N in folders · 0 to sort` | parents closed (`view.openAll` off), what the answers made marked `new` (`view.setNewIds`) | `Next: Alt text` · Undo |
| else | as B.2 | | `Fill N pictures`, or `This is my tree` when the tree is not confirmed |

A card is `.g-card.g-q[data-q][data-kind] > .g-q-text + .g-q-strip + .g-q-answers > .g-answer[data-answer](.is-first)`;
eight thumbnails at most; *Show me* answers nothing and opens the strip on
the group's pictures (`.g-q-strip.is-open`, 48 at most, each a link to
`upload.php?item=ID`, where *Why is it here* answers for it). An answer
posts `/guide/answer`; the response carries the questions, the fill's
state, `made` (term ids the answers made) and undo; the tree is re-read so
it gains the folder. *Leave the rest* posts `id: "rest"`: the route answers
every open question with `leave` — `keep-parent` for a sibling question,
which offers no leave and whose pictures are in the parent already. Done is
`fillDone()`: 0 open, 0 in no folder, not running (the server's `done` is
the same rule, asserted by the fill walk's F4). The page lands on Fill when
questions are open; the questions are read once it has painted, only then.

**Step 4 · Alt text**: the pill is every image without alt text
(`alt_missing`); the button `Write alt text for N` is the pictures the
catalogue can fill (`alt_pending`, new on the boot: twelve queries now). It
presses the plugin's own `/ai-alt`, two hundred a request until none is
left, and stops on a request that brings the number down by nothing. Never
overwrites — that is `vergeml_ai_alt_pending_from()`, the one condition the
list and the count now share. **Step 5 · Rename** was already on the rail,
gated, from S4.

**The word on a picture** (`vergeml_filing_confidence()` in core/filing.php):
`by you` from the placed-by mark or a by-hand row, else the move's `why` —
`ok` is `sure` from 0.70 and `likely` below, `siblings` is `likely`, anything
else says nothing. `/librarian-why/{id}` answers `confidence` and `word`;
the modal's section (`js/vergeml-why-view.js`) shows it as `.vgml-why-word`
in the facts column beside the label; a dragged picture with no moves row
now answers `Put here by hand · nothing scored it` with `by you` (it
answered nothing before). The media list's line carries it after the folder
(`.filename .vgml-word`, `core/media-list.php`, one query a page for the
moves rows, on the one line — no row grows).

**Also fixed on the way:** the Folders tree with no draft showed every
parent closed (the S4 empty-session shot) — `entries()` ignored `openAll`
on the no-draft path. Open now, as the approved mocks have it. And Undo
closes the questions with the Move: an answer after an undo would have
moved pictures the undo had just put back.

## Gates

- `npx playwright test --config tests/ui/playwright.config.mjs tests/ui/folders.spec.mjs`
  on the box → **11 passed, 1 skipped (the walk)**; the asking state 46 words without the tree (89 with) and the done state 24 (45) at both sizes (the mock: 76/107 and 25/68). New: the Fill step asks (two planted
  questions → two cards with their buttons, the engine's first and tinted,
  the thumbnails loaded, the questions to the tree's right; New folder →
  `Spec probe made · 5 moved` on the card and `Spec probe` `new` in the
  tree; Leave the rest → 0 open, 0 in no folder, `1,000 in folders · 0 to
  sort`, Fill ticked, parents closed, `Next: Alt text`; a reload reads the
  same); three left in no folder and no question is not done; Step 4 on
  three cleared alts and one of the person's own → the three written, the
  fourth word for word, `/ai-alt` remaining 0, no model route, `Next:
  Rename`; Step 5 disabled with its line and pill; the word on a picture:
  `by you` from the route and on the list row after the folder.
- **Mutations** (run, red, restored): `unfiled === 0` dropped from
  `fillDone()` → "three in no folder is not done" red (`.g-cols` wore
  `is-done`). The never-overwrites condition dropped from
  `vergeml_ai_alt_pending_from()` → "the alt somebody wrote is untouched"
  red, with the catalogue's sentence where the person's was.
- `tests/ui/modes.spec.mjs -g "grid modal"` → green; the fulfilled route
  carries `confidence: sure` and the pill reads `sure` with `is-sure`; the
  real route's `confidence` is one of the four values.
- `node tools/verify.mjs guide roles filing-trail fill-walk` (box) → 31/31,
  19/19, 119/119, 22/22 (A1 is twelve queries as measured).
- `node tools/verify.mjs surface escaping` → 26/26; 9/10 (four-to-one,
  known); `docs/security-*.md` regenerated.
- `node tests/tree/tree-view.mjs` → 50/50.
- `node tools/rtl.mjs --check` → up to date; `eml-admin-media(-rtl).css`
  edited by hand, both.
- `node tools/deploy.mjs --check` after the last deploy: box up to date.

Screenshots (the built screen beside the mock's):
`docs/superpowers/mocks/shots/2026-09-15-built-fill-asking-{1600x1000,1280x800}.png`,
`…-built-fill-done-{1600x1000,1280x800}.png`, `…-built-alt-planted-1600x1000.png`,
`…-built-why-modal.png` — from `tests/ui/shots/` written by the suites on
the box's real data.

## What Nathan should know

- **The box's alt text, and what happened.** The alt fixture recorded "the
  pictures with no file alt" through `vergeml_ai_alt_pending()` — the
  function the next run mutated. Under the mutation it answered every
  picture; the test's press wrote 1,000 alts; the restore deleted 1,000,
  the 100 real ones included. No backup exists on the box. The 100 were the
  rows described on 2026-09-12 with auto alt on, so their file alt was the
  catalogue's; `vergeml_ai_apply_alt` logic put those 100 back from the
  index. A second slip: the earlier mutation run's page kept writing while
  the restore ran, and left 181 catalogue alts on pictures that had none;
  those are off again. Counts now read as found. The fixture reads with its
  own SQL now, the loop stops on a write that changes nothing, and the spec
  closes the page before it restores. Memory:
  `fixtures-never-read-through-the-code-under-test`.
- **Undo on the done state** shows only when a Move's undo record exists:
  answers write into that record, and there is none after a plain plant, so
  the built done shot has no Undo. After a real fill it is there.
- **No picture on the box carries a word today**: every fill was undone, so
  no moves row names a folder a picture is in, and nothing is marked placed
  by hand. `sure` / `likely` will appear after A.5's fill; the modal test
  proves them with a fulfilled route, the reader in `filing-trail`.
- **The word on the list row sits after the folder**, on the same line —
  which on the box, with thirteen third-party columns on, is past the
  ellipsis (so is the folder). `modes.spec` hides those columns for its
  measurements; a person on the box sees the pill when the File column has
  room.
- **`Upload probe`** is a real folder on the box (with `Alpha`, `Beta`) — a
  leftover from an earlier session's upload test, not this one's. Delete
  by hand or leave; the fixture avoids it.
- **The Find box** (`.vgml-tv-find`) appears in the Fill card once the tree
  passes twenty folders — the component's own rule, and To sort took the
  box to 21. It sits under the pill row; the mock has none.

## Found, not done

- The card just answered stays until the next answer; the mock says "the
  rest follow as these are answered". Three cards a screen either way.
- `guideRules` in the service: one line plus chips (from S4, still open).
- `.vgml-tv-find` in the Fill card's head above twenty folders (above).
- The list row's pill past the ellipsis on a crowded table (above).
- The asking pill row after a reload reads `placed` as described − unfiled,
  not the run's own tally (the report is not kept past the run).

## Next — S6: A.5, the walk by Nathan, from a fresh session

Model: Opus. Nathan does the walk; the session prepares, watches and takes
the quality sample. Card, to `plugin/.harness/active.json` before anything:

```json
{
  "phase": "Every picture a home — S6: A.5 the walk, by Nathan — describe, propose, edit, confirm, fill, answer every question through the screen; the 60-picture quality sample",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "tools/box-folder-quality.php",
    "tools/box-*.php",
    "docs/handoffs/**",
    "docs/superpowers/mocks/shots/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-phase-b-s5-fill-questions-alt-confidence.md",
    "plans/every-picture-a-home.md (A.5, and the acceptance in the spec §5)",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md (§5)",
    "tools/box-folder-quality.php (the sample: 60 pictures, 30 sure, 30 likely, as a contact sheet)",
    "tools/box-fill-walk.php (what the scripted walk asserts; the snapshot/restore it keeps)",
    "memory: walk-the-purchase-as-a-buyer, model-spend-discipline, tests-never-touch-live-state"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Nathan presses every button; the session never answers a question for him and never runs a fill by script",
    "Say the cost before each step that spends: describe is done (1,000 of 1,000); Propose folders is 10 credits; confirm may ask the planner about folders with no classes (metered); the fill and the answers spend nothing",
    "Watch the counts as they climb and note them; the session's job while the fill runs is the record, not the screen",
    "After 0 in no folder: the 60-picture sample as a contact sheet with each picture's folder and word; Nathan marks right/wrong; sure >= 95 %, likely >= 80 %",
    "If a threshold fails, the handoff names which, with the sheet as evidence — the next card tunes that, not the floor by feel",
    "Do not call it done on the counts alone; do not undo Nathan's fill unless he says so",
    "Before the walk: the box is as this handoff leaves it (513 in no folder, no To sort, no open question); Upload probe is his to delete or keep"
  ],
  "gates": [
    "0 pictures in no folder on the box, every question answered by Nathan, the answers as folders in the tree (the done state on screen, shot beside the mock)",
    "Preview count = run count on the same confirmed tree (the fit's tally against the report's)",
    "tools/box-folder-quality.php: the sample's precision, sure and likely, with the sheet's path in the handoff",
    "The numbers in the handoff: propose cost, confirm's profiled count, the fill's tally (fits / siblings / nothing, sure / likely), questions asked and how answered",
    "node tools/deploy.mjs --check before the walk: box up to date"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-phase-b-s5-fill-questions-alt-confidence.md,
then plans/every-picture-a-home.md A.5 and the spec's §5. State which model
you are. This session is S6 of every-picture-a-home: A.5, the walk by
Nathan through the Folders screen on the box — you prepare, watch and take
the quality sample; he presses. Write the card from the handoff to
.harness/active.json before anything else. Say each step's cost before he
presses. End with a handoff in docs/handoffs/ carrying the next card (B.6,
or the tuning card if a threshold fails).
```

What S6 inherits, concretely: the screen's done state needs 0 open and 0 in
no folder; "Leave the rest" is the fast way through the residue and puts
everything left into To sort (locked, so no fill files into or out of it);
Undo covers the run and every answer for a day when the Move's record
exists; the word on a picture reads from the moves rows the fill writes,
so after his fill the list row and the modal show `sure` / `likely`, and
`by you` on anything he drags. `tools/box-fill-walk.php` is the scripted
twin: its snapshot/restore is the model for putting the box back if the
walk has to be abandoned half-way.
