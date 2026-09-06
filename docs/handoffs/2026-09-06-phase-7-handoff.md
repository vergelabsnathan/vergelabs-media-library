# Session handover — 2026-09-06, Phase 7 done (Fable 5.1)

For the next session. Read in this order, then open Phase 8 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 to 7 are done. Phase 8 (the
   migration page: one card per importer source, detected or not, folder
   count on this site, one button; names as text, logos only on Nathan's
   say) is next and is written for the Opus profile: one task, a check-in,
   the gates.
2. `docs/superpowers/specs/2026-09-06-grid-list-modes.md` — this phase's
   table: twenty-one actions against both modes, the seven fills, what was
   left and why. Spec §14 points at it.
3. This handoff's "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.

Plugin on `main`, one feature commit plus this handoff. The service was not
touched. The nightly watch commits to `main` around 05:17 UTC: `git pull
--rebase` before pushing.

## Still Nathan's

- Migration 018 (`library_counts`) on the production database, from the
  Phase 0 handoff. Nothing in Phases 1–7 depends on it.
- **A remove control on a Folders row** — unchanged since Phase 3. The
  proposal is `docs/superpowers/mocks/2026-09-05-folders-remove-control.html`.
- **Alt text that is present but junk** and **no language sent with a
  describe** — from the Phase 5 handoff, product calls.
- **The "Files you have taken out of the library" card** at the foot of
  Duplicates — from the Phase 6 handoff, a copy row for a later pass.
- **Which of our list columns earn a place by default.** Beside the tree the
  list has room for four or five columns; ours add three (Media Categories,
  Colour, Used on) to core's, and this box's other plugins add five more.
  The walk hides the third-party ones for its own admin; a product default
  is Nathan's.
- **FileBird Pro is active on the box** (since at least 2026-09-05; the
  fixtures memory said inactive, now corrected). This phase makes the drag
  survive it; whether it stays active as a fixture is Nathan's.

## What landed

**The table**, `docs/superpowers/specs/2026-09-06-grid-list-modes.md`,
approved by Nathan before code with the seven fills as written and nothing
struck. Each "today" cell that mattered was measured on the box by a
read-only probe before the table was shown: Unfiled in list showing 645
items under a row saying 272; the filter bar's dropdown reading 0 after a
tree click; a bare list arrival with the tree on Workspace over a table of
645; the grid's dropdown following a folder but not Unfiled.

**The fills**, `js/vergeml-tree.js` unless said:

- F1 — the list's edit modal gets the page's rows as its library, so core's
  own ‹ › walk the list as they walk the grid. Core fires `refresh` with the
  model the arrow landed on and draws it at once; our handler replaces
  core's and fetches a row's file before the first draw.
- F2 — `bumpGrid()` empties the frame's selection when a filtered grid
  re-queries after a move: tiles no longer shown had stayed selected, "N
  selected" stayed, and Delete selected would have acted on them.
- F3 — `bumpGrid()`'s list branch: `swapTable( location.href )` while a
  folder, Unfiled or a smart folder is showing, after a move or an undo. The
  rows leave, the ticks with them, "N items" is fresh.
- F4 — `th.column-title` is armed as a cell of ours (WordPress made the
  primary column a `<th>`; the cell arming was `td` only). Innermost, it
  takes the press before FileBird's row instance. After `swapTable()` the
  rows are re-armed and the observer follows the new `#the-list`.
- F5 — `core/taxonomies.php`: the list query and the dropdown's selected
  state read `uncategorized=1` as they read the dropdown's own
  `attachment-filter=uncategorized`. And Unfiled means "in no folder":
  `vergeml_is_folder_taxonomy()` scopes the NOT EXISTS to hierarchical media
  taxonomies, in the grid's query too. It had meant "no term in any media
  taxonomy" — 56 files on a site with a flat Colour taxonomy, under a row
  saying 272.
- F6 — `syncListBar()`: `select()` and `smartSelect()` in list set the
  bar's folder dropdown to the term id, the kind dropdown to "All
  Uncategorized" for Unfiled and back to its first option after, and a
  hidden `vgml_smart` input for a smart folder — so the next search or
  Filter press submits what the tree shows. In grid, `select( -1 )` sends
  `uncategorized: true`, the value the grid's own option was built with, so
  that option shows.
- F7 — `listArrival()`: in list the tree follows the URL when it names a
  folder (slug or id), Unfiled or a smart folder, and remembers it; a URL
  with a search, filter or page on it is followed and not remembered; a
  bare URL re-selects the remembered folder, as the grid does, one swap.
  `followUrl()` also runs on popstate, and popstate no longer pushes a
  second history entry.

Two more, found by the walk:

- The M key was dead in the grid after a file had been opened once: the
  guard skipped on any `.media-modal` in the page, and the grid keeps its
  closed modal in the DOM. The guard now asks whether a modal is visible.
- A click on a folder while the arrival's own swap was still fetching
  compared its URL with the bare address, saw no difference, and did
  nothing; then the arrival swap landed with the other folder. `select()`
  compares against the swap in flight (`swapHref`).

**Copy.** No user-facing string changed. `tests/tree/copy.mjs` untouched.

**Suite.** `tests/ui/modes.spec.mjs`, under `MODES_WALK=1`. One test per
mode, the same steps: arrival (remembered, and in list by URL), folder and
Unfiled with the dropdowns and the crumbs, search within the folder, sort
(list), open a file with the arrows and Escape, select two and a third from
the keyboard, M → the dialog → the move → the view, counts and selection →
Undo, drag to a folder, Ctrl-drag, drag to Unfiled, each undone, the tree's
keys. It uploads one canvas JPEG and makes two folders per mode and deletes
them in `finally`; it hides the box's third-party list columns for its
admin through core's `hidden-columns` request and puts them back. Errors
from another plugin's script are printed, not counted — Elementor's
`ai-media-library.min.js` throws on any attachment modal whose frame is not
its own (the list's is a stub), in the grid's frame it is quiet.

## Evidence

- `node tools/deploy.mjs --box`: `php -l: every file parses`, 144 files
  re-hashed on 46.225.66.194; `node --check` on the two scripts.
- `MODES_WALK=1 npx playwright test modes.spec` → **2 passed (8.9 m)**:
  grid 4.3 m, list 4.5 m, every step. Screenshots
  `tests/ui/shots/modes-grid.png`, `modes-list.png`, shown in the
  conversation.
- Mutation checks, each applied on the box by `sed`, the real build shipped
  back after: the `uncategorized=1` read dropped from the list query (F5) →
  **red** at *file … shown under Unfiled has no folder* (list); the list
  branch of `bumpGrid()` dropped (F3) → **red** at *the moved file leaves the
  folder's view* (list); the selection reset dropped (F2) → **red** at
  *nothing is selected once the file has left the view* (grid).
- `node tools/verify.mjs copy` → passed.
- `npx playwright test shell.spec shots.spec` on the box → **25 passed
  (6.2 m)**: the shell sweep over the eleven slugs and the three collapsed
  settings screens, eleven screenshots with no JavaScript error.
- `node tools/verify.mjs health health-keep journey guide` → **passed** all
  four (journey 62/62).
- **Credits: 0 spent.** 25,971 at the start, **25,971** at the end
  (`vergeml_ai_refresh_credits( true )` on the box).
- Box left as found: the walk's pictures and folders deleted, the throwaway
  admin `vgml-p7` removed, FileBird Pro as it was (active).

## What Phase 8 inherits

- `vergeml_is_folder_taxonomy( $name )` in `core/taxonomies.php`.
- In `js/vergeml-tree.js`: `syncListBar()`, `urlSelection()`, `urlIsBare()`,
  `followUrl()`, `listArrival()`, `watchLists()`; `swapTable( href, push )`
  with `swapHref` naming the swap in flight.
- `tests/ui/modes.spec.mjs`'s helpers: `openMode`, `shownIds`, `marked`,
  `treeCount`, `selectedCount`, `clickFolder` (waits for the mode's own
  response), `barValues`, `dragFileTo` (with Ctrl), `undo`, `selectFile`.
- The probe technique: a read-only Playwright script against the box with
  `elementFromPoint` under every press point, before any conclusion is drawn
  from a drag that "did nothing".

## Decisions taken here, for Nathan to overrule

- **Unfiled means in no folder.** A colour or a tag does not file a
  picture. With two hierarchical media taxonomies, Unfiled means in neither.
- **In list the URL is the truth**; a bare arrival re-selects the remembered
  folder as the grid does. A URL with a search or a page on it is followed
  and not remembered.
- **Errors from other plugins' scripts do not fail the walk**; they are
  printed with their file.
- **The four furniture differences stay** (grid sidebar, grid "N selected",
  list "N items", list column headers), as approved.
- **Core's "Add to selected term" bulk action stays** in list; its "term"
  wording is a copy row for a later pass.

## Found, not done

- Elementor's AI button throws in the list's edit modal (stub controller).
  A real controller for the list frame, or a stub with what Elementor reads,
  would silence it.
- The list beside the tree with this box's thirteen columns is unusable;
  see "Still Nathan's".
- A folder in the grid's URL (row 12), a meaning link in the grid's toolbar
  (row 16), an order control in the grid (row 17): each a new shape, not
  built.
- `tests/tree/drag.mjs` and `one-folder.mjs` press on `.column-title` at the
  cell's vertical middle; on a list collapsed into towers that point is off
  screen. They are not in `tools/verify.mjs`'s box set and were not run.
- Everything on the Phase 6 handoff's list that this phase did not touch
  still stands.

## Phase 8 opener, to paste

```
Read docs/handoffs/2026-09-06-phase-7-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 8, task 23: the migration page — one card per source
the importer reads (FileBird, HappyFiles, Folders by Premio, Real Media
Library, Wicked Folders, WP Media Folder, CSV): detected or not, folder count
on this site, one button. Names as text; logos only on Nathan's say.
Stop points: a mock of the card before code; any logo.
Gates: tests/ui/shell.spec.mjs, tests/ui/shots.spec.mjs, tools/verify.mjs
copy, journey and guide still green. Say the cost of any test that spends
credits before it runs; never open the Folders screen on an empty guide session.
End with a handoff in docs/handoffs/.
```
