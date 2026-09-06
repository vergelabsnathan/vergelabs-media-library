# Session handover — 2026-09-06, Phase 8 stopped at the mock (Opus 5)

Phase 8, task 23. The session ran to the stop point the opener named — "a
mock of the card before code" — and stopped there. **No plugin code was
written.** What exists is the diagnosis, the mock, the spec and the copy
table, all waiting on Nathan.

For the next session, read in this order:

1. `docs/superpowers/specs/2026-09-06-import-cards.md` — the contract:
   what today's screen does wrong, the card, the three decisions, the copy
   table, the defect, and what changes in which file.
2. `docs/superpowers/mocks/2026-09-06-import-cards.html` — four boards.
   Board shots are in `docs/superpowers/mocks/shots/`.
3. This handoff's "Waiting on Nathan" and "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the
   profile. Phase 8 is written for the Opus profile.

Plugin on `main`, nothing committed yet by this session (the files are
untracked; see "What is on disk"). The service was not touched. The nightly
watch commits to `main` around 05:17 UTC: `git pull --rebase` before pushing.

## Waiting on Nathan

Three are drawn and need a yes or a no. One is a defect and needs a call.

1. **The six sources with nothing on this site: a card each, or one line.**
   Board 3 draws both side by side. Shape A is the brief read literally and
   spends six rows on the same sentence above the one card that can be
   pressed. Shape B says it in a line and puts the actionable card first.
   Recommended: **B**.
2. **A seventh plugin.** The brief names six; `vergeml_import_sources()`
   also reads **WP Media Folders** by Damien Barrère (`feml-folder`). Drawn
   in. Say if it should go.
3. **The spreadsheet is split.** Reading a CSV in becomes a card in the
   source list; writing one out moves to its own section below, because
   exporting is not importing. Today the two sit side by side above
   everything else.
4. **The plan and the run disagree, and the card's outcome line depends on
   the plan.** Fix it inside Phase 8, or ship the card without the outcome
   line until it is fixed. Detail below.

**No logo was drawn and none is proposed.** Names as text, as the plan says.

## The defect

`vergeml_import_plan()` (`core/import.php:39`) and `vergeml_import_run()`
(`:123`) both key a folder as `name|our parent id` and look it up among the
folders already here. The run inserts each parent before its children, so
the key holds a real term id. The plan cannot insert, so it stores the
string `new:<source id>` — and `vergeml_import_key()` (`:516`) casts the
parent with `(int)`, which turns `new:4` into `0`. Every folder whose parent
is about to be created is keyed as though it sat at the top of the tree.

Measured on the box against a FileBird tree of 14 folders:

| | plan says | run would do |
|---|---|---|
| Apparel, under Products | merges into our top-level Apparel (term 1515) | creates it under the new Products |
| new folders | 11 | 12 |
| merged | 3 | 2 |
| files into folders that already existed | 88 | 43 |

Evidence: a read-only replay of the plan's own loop on the box printed
`CREATE Homepage slot=homepage|0` and `MERGE Apparel slot=apparel|0 -> term
1515` — the `|0` is the bug, visible in the key itself. The run's branch was
read, not executed; no import was run and no folder of ours was touched.

Two live consequences: the preview under-counts what an import creates, and
two source folders with the same name under different parents collide with
each other in the preview. The file's own header says a preview that drifts
from the import is the failure to avoid; this is that.

## What is on disk

Untracked, nothing shipped (`tools/` and `docs/` are both excluded from the
deploy):

- `docs/superpowers/specs/2026-09-06-import-cards.md`
- `docs/superpowers/mocks/2026-09-06-import-cards.html`
- `docs/superpowers/mocks/shots/` — `import-today.png` (the box as it
  stands, with the fixture in place) and the four board shots.
- `tools/box-filebird-fixture.php` + `.sh`, `tools/box-filebird-clear.php`
  + `.sh` — make and remove the FileBird fixture.
- `plans/folders-one-tree.md` — Phase 8's entry updated with where this got
  to.

## The FileBird fixture

FileBird Pro 6.5.8 is active on the box and holds **zero** folders, so
there was nothing to draw a detected card from. `tools/box-filebird-fixture.php`
writes 14 folders over 394 of the site's own 641 pictures straight into
`wp_fbv` and `wp_fbv_attachment_folder`: a tree by site area and campaign,
the way a customer's FileBird looks, with Apparel, Workspace and Portrait
chosen to collide with our tree. Nothing of ours is touched; our importer
only reads those tables.

It was removed at the end of the session — the box is as it was found, both
tables empty — so **the next session must put it back** before it can walk
the screen:

```
scp tools/box-filebird-fixture.php root@46.225.66.194:/tmp/vgml-filebird-fixture.php
scp tools/box-filebird-fixture.sh  root@46.225.66.194:/tmp/
ssh root@46.225.66.194 'bash /tmp/box-filebird-fixture.sh'
```

The fixture script refuses to run if `wp_fbv` already holds rows, so it
cannot overwrite a real tree.

## The numbers, all the box's own

| | |
|---|---|
| files · pictures | 645 · 641 |
| folders · filed · in no folder | 19 · 373 · 272 |
| FileBird fixture | 14 folders · 394 pictures |
| of those 394, in no folder now | 156 |
| after an import | 30 folders · 116 in no folder |
| the three names that collide | Apparel 45 · Workspace 25 · Portrait 18 |
| imports on record | 5, all CSV, 1–2 September |
| credits | 25,971 before, 25,971 after |

## Evidence

- `node tools/verify.mjs copy journey guide` → **passed** all three
  (journey 62/62).
- `npx playwright test shell.spec shots.spec` on the box → **25 passed
  (5.4 m)**.
- **Credits: 0 spent.** 25,971 at the start, 25,971 at the end
  (`vergeml_ai_refresh_credits( true )` on the box). No gate here reaches a
  model: the guide suite posts persisted turns through REST without a
  planner call, and the shots spec plants a turn before it opens Folders.
- The four board shots were rendered and read before the spec was written;
  three faults the render exposed were fixed in the mock (the board's own
  `h3` rule uppercased the card name, the shell's `min-height:100vh` blew a
  hole through the close-up boards, and two lines explained rather than
  stated).
- Box left as found: the FileBird fixture removed (14 folders, 394 links),
  the throwaway admin `vgml-p8` deleted, the probe scripts removed from
  `/tmp`.

## Found, not done

- **`tests/ui/shots.spec.mjs` screenshots the Import screen before it has
  any content.** It fires as soon as `.vgml-shell-content` is visible, which
  is before the two REST calls land, so every import shot on file is the
  spinner and the words "Looking for folders to import…". The screen has
  never actually been reviewed from a shot. The same risk applies to any
  screen that paints from a fetch. One line, and it belongs with the code.
- **A one-off Playwright script against the box must solve Jetpack's login
  sum**, exactly as `tests/ui/fixtures.mjs` does, or it silently lands back
  on the login page and every later assertion times out on a selector that
  is simply not there. Cost two runs here.
- Everything on the Phase 7 handoff's "Still Nathan's" list still stands,
  unchanged: migration 018 on the production database, a remove control on a
  Folders row, junk alt text, no language on a describe, the "Files you have
  taken out of the library" card, which list columns earn a place, and
  whether FileBird Pro stays active as a fixture.

## Phase 8 opener, to paste once the mock is approved

```
Read docs/handoffs/2026-09-06-phase-8-mock-handoff.md, then
docs/superpowers/specs/2026-09-06-import-cards.md and its mock.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session builds Phase 8, task 23 to the approved mock: core/import-ui.php
(every source returned, with its outcome numbers), js/vergeml-import.js (the
card, one button, progress in the button, the history named and dated),
css/vergeml-shell.css (the card rules from the mock's style block).
Decisions Nathan has taken: <the four from "Waiting on Nathan">.
Put the FileBird fixture back first: tools/box-filebird-fixture.sh.
Gates: tests/ui/shell.spec.mjs, tests/ui/shots.spec.mjs, tools/verify.mjs
copy journey guide, plus the new tests/ui/import.spec.mjs and
tests/tree/import-plan.php with a mutation check each.
Say the cost of any test that spends credits before it runs; never open the
Folders screen on an empty guide session.
End with a handoff in docs/handoffs/.
```
