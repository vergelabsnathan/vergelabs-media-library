# Grid and list: every action against both modes

Phase 7, task 22 of `plans/folders-one-tree.md`. The table is the stop
point: nothing below "Fills" is built until Nathan has read it. Written on
2026-09-06 from `js/vergeml-tree.js`, `core/taxonomies.php`,
`core/tree-ui.php`, `js/eml-media-grid.js`, `js/vergeml-media-views.js` and
`core/bulk-terms.php`, against the box (645 files, 19 folders, 272 unfiled,
80 per page, the folder dropdown on, one-folder-per-file off).

"Same" means the person gets the same result from the same gesture; core's
furniture that differs by design (a sidebar in grid, checkboxes in list) is
named and left. Every row becomes an assertion in `tests/ui/modes.spec.mjs`,
in both modes.

## The table

| # | Action | Grid today | List today | Fill |
|---|---|---|---|---|
| 1 | Open a file | click a tile → details in the sidebar; the pencil → the edit modal with ‹ › over the loaded files | click the title or thumbnail → the same edit modal, **no ‹ ›** (a library of one); Escape closes; middle/ctrl-click still opens the edit page | **F1** the list's modal gets ‹ › over the page's rows, each file fetched as the arrow lands |
| 2 | Select files | Bulk select → click toggles, shift-click ranges, Select all, "N selected" in the toolbar | checkboxes, shift-click ranges, the header box; no count | none — the count is core's absence; the Move dialog says "N selected — choose a folder" at the moment it matters |
| 3 | Selection after a move that takes the files off the screen | the tiles leave, **the selection keeps them**: "N selected" stays and Delete selected would act on files no longer shown | the ticks go with the rows once the table is re-fetched (F3) | **F2** when the view is re-fetched after a move, the selection is emptied |
| 4 | Move selected without dragging | M or Move selected… → dialog → folder → filed; toast with Undo | same | none |
| 5 | WordPress's bulk action "Add to selected term" / "Remove from selected term" with a term select | none — core has no bulk menu in grid | present; page reload; notice | none — row 4 is the road in both; the wording ("term") goes on the copy list |
| 6 | Drag a file onto a folder | moves; onto Unfiled unfiles; Ctrl-drag adds; helper says "1 file" / "N files"; on a filtered view the tile leaves at once | same drag; **on a filtered view the row stays and "N items" is stale** | **F3** the list re-fetches its table after a move or undo while a folder, Unfiled or a smart folder is showing |
| 7 | Drag from the title, and after a folder click | the tile is armed; measured on the box with FileBird Pro active: the helper appears | the title cell is a `<th>` since WordPress made the primary column one, so the cell arming (`td:not(.check-column)`) misses it and the row's instance handles the press. **With FileBird Pro active the row's instance is FileBird's**: it re-initialises `.draggable()` on the same `tr`, jQuery UI merges its options into the one instance, `_mouseCapture` answers false and no drag of ours starts from the title (measured; with FileBird's scripts blocked the same press starts our helper). After a swap the observer watches a table that is gone; the mouseover net re-arms rows on first hover | **F4** arm `th.column-title` as a cell of ours — innermost, so it takes the press before FileBird's row — and re-arm and re-observe after every swap |
| 8 | Filter by a folder, Unfiled, a smart folder, an AI folder | props on the frame; the grid re-queries | a URL var; the table is swapped in place. Folder by slug or id: resolved. **`uncategorized=1` is not read** — only the dropdown's `attachment-filter=uncategorized` is — measured: Unfiled shows **645 items** under a tree row saying 272, six of the first eight rows filed | **F5** the list query reads `uncategorized=1` as it reads the dropdown's spelling |
| 9 | The folder dropdown in the filter bar agrees with the tree | measured: a folder click puts the folder in the dropdown (1800); **Unfiled leaves both dropdowns on "all"** — the tree sends `uncategorized: 1`, the "All Uncategorized" option matches on `true` | measured: after a tree click the dropdown **still reads 0** — the bar is outside the swapped table — and it is inside the form, so the next search or Filter press submits the old folder | **F6** `select()` and `smartSelect()` set the dropdown (and `attachment-filter` for Unfiled) to what the tree shows; the grid sends `uncategorized: true` so its own option matches |
| 10 | Arriving at the screen | the remembered folder is re-selected 500 ms after boot; the grid follows the tree (measured: prop 1800, 25 tiles) | measured: `upload.php?mode=list` arrives with the tree on **Workspace** and **645 items** in the table; `upload.php?mode=list&media_category=food` shows Food's 8 items and the dropdown on Food while the tree still marks Workspace | **F7** in list the tree follows the URL when it names a folder; a bare arrival re-selects the remembered folder, as the grid does (one table swap) |
| 11 | Crumbs above the files | All files › Landscape › Mountains, each a button | same (`#posts-filter`) | none |
| 12 | Back and forward | the router carries `?search=` only; a folder is not in the URL | popstate re-fetches the table for the URL | none — a folder in the grid's URL is a new URL scheme; found, not done |
| 13 | Tree counts after a move or undo | from the assign response, no reload | same | none |
| 14 | How many files are on screen | none (core) | "N items" in the tablenav (core), refreshed by F3 | none |
| 15 | Search | the toolbar box sets a prop; **the folder is kept**; the URL gets `?search=`; the "list" switcher carries `s=` | the box submits the form; **the folder is not in the form**, so it is dropped while the tree keeps highlighting it | F6 + F7: the form carries the dropdown's folder and the tree follows the URL |
| 16 | Search by meaning | none | "Search by meaning" beside the box, on a search | none — a grid affordance is a new shape; found, not done |
| 17 | Order | Library settings › Order, for every filter; natural sort through `the_posts`; no control in the toolbar (core) | the same default; core's Title / Author / Date headers, kept through folder clicks and pages | none — the settings own the order in both; a control in the grid toolbar is a new shape, say so if wanted |
| 18 | Tree keys | arrows, Home, End, Enter/Space, F2, Alt+arrows, Shift+F10, M | same code | none |
| 19 | M from anywhere on the screen with files selected | the dialog; Escape closes; Tab stays inside | same | none |
| 20 | Escape | leaves Bulk select; closes the modal (core) | closes the modal (added) | none |
| 21 | Keyboard selection | Enter/Space on a tile (core) | Space on a checkbox (core) | none |

## Fills

Seven, all in `js/vergeml-tree.js` unless said. No new string on any screen.

- **F1 — ‹ › in the list's modal.** The frame the title link opens gets a
  library of the page's rows (their ids are in the table), so core's own
  previous/next buttons appear as they do in grid; a file whose details are
  not loaded yet is fetched when the arrow lands on it. The visible shape is
  core's arrows, unchanged. One request per open, one per arrow.
- **F2 — the selection follows the view.** `bumpGrid()` empties the frame's
  selection when it re-queries a filtered grid after a move; on All files
  nothing leaves and the selection stays.
- **F3 — the list's own bump.** `bumpGrid()` gains the list branch:
  `swapTable( location.href )` when a folder, Unfiled or a smart folder is
  showing. The rows leave, the ticks with them, "N items" is fresh. Undo
  takes the same road.
- **F4 — the title cell is ours, and arming survives the swap.** The cell
  selector gains `th.column-title`; a press on the thumbnail or the title
  lands on our cell instance before any row instance another plugin put on
  the `tr`. After `swapTable()` replaces the table: `armDraggables()`, and
  the observer watches the new `#the-list`.
- **F5 — Unfiled by URL** (`core/taxonomies.php`).
  `vergeml_backend_parse_tax_query()` and `vergeml_restrict_manage_posts()`
  read `uncategorized=1` as well as `attachment-filter=uncategorized`.
- **F6 — the bar agrees.** `select()` and `smartSelect()` in list set
  `select[name="media_category"]` to the term id (0 for none) and
  `select[name="attachment-filter"]` to `uncategorized` for Unfiled and back
  to `all` after; a hidden `vgml_smart` input carries a smart folder. In grid
  `select( -1 )` sends `uncategorized: true`, the value the "All
  Uncategorized" option was built with, so that option shows.
- **F7 — arrival.** In list, `begin()` reads the URL: a folder var, slug or
  id, Unfiled or a smart folder sets the tree's selection (and persists it);
  a bare URL with a remembered folder re-selects it, which is one
  `swapTable()`, as the grid re-selects with one query.

## Left, and why

- Row 5: core's bulk action with a term select stays; its copy says "term".
- Rows 12, 16, 17: each would be a new visible shape in the grid (a folder
  in its URL, a meaning link in its toolbar, an order control). Not built;
  Nathan says if wanted.
- Row 2: no count in list.

## Found on the box, not this phase's

- FileBird Pro 6.5.8 is active on the box's media screen, as the Phase 2, 3
  and 4 handoffs each recorded; the fixtures memory has it inactive. F4 makes
  our list drag survive it; nothing here deactivates it.
- The list beside the tree: with thirteen columns the title cell is 24–55 px
  wide and a row 1,400–2,100 px tall at 1500 and at 2200 px. Three of the
  columns are ours (Media Categories, Colour, Used on). Which of ours earn a
  column by default is a product call.
- The grid's "All Uncategorized" option and the tree's Unfiled are the same
  question asked in two spellings (`true` / `1`); F6 settles the grid's.

## Proof: `tests/ui/modes.spec.mjs`

Runs on the box under `MODES_WALK=1`. **Spends nothing**: no describe, no
planner call, the Folders screen is never opened. It uploads one picture of
its own (a canvas JPEG, as `health.spec.mjs` does), makes two folders "P7
modes A" and "P7 modes B", files the picture into A, and deletes all three
at the end whatever happened. FileBird Pro stays active on the box — row 7
is measured with it there. The box's list carries thirteen columns (FileBird,
Qode, SEOPress, AIOSEO, ours) and beside the tree they collapse into towers,
so the walk hides the third-party columns for its own admin through core's
Screen Options request (`hidden-columns`), as a person would. Every row
above is one assertion run twice, under `?mode=grid` and `?mode=list`:

- open: the modal appears; ‹ › present in both; › shows the next file (list).
- select: two selected in both; after a move off a filtered view the
  selection is empty in both (3).
- bulk move: M → dialog → B → the assign request; toast; Undo puts it back (4).
- drag: the picture dragged from A's view onto B leaves the view within 5 s
  in both; B's count +1, A's −1 in the tree; Undo (6). In list, the drag is
  made after a folder click, from the title cell (7).
- folder filter: click Unfiled → every file shown has no folder, and in list
  "N items" equals the tree's Unfiled count (8); the dropdown's value equals
  the tree's folder after a click, in both (9); arrival with
  `media_category=<slug>` highlights that folder, a bare arrival with a
  remembered folder shows that folder's files, in both (10); crumbs (11).
- search: on A, search the picture's title → the folder is still in effect
  and the tree still highlights A, in both (15).
- sort (list): the Title header keeps `media_category` (17).
- keyboard: Enter on a focused folder row filters; Escape closes the modal;
  M opens the dialog, in both (18–20).

Mutation checks, each on the box by `sed` and shipped, the real build back
after: drop the `uncategorized=1` read (F5) → the Unfiled row goes red in
list; drop the list branch of `bumpGrid()` (F3) → the drag row goes red in
list; drop the selection reset (F2) → row 3 goes red in grid.

Gates after: `tests/ui/shell.spec.mjs`, `tests/ui/shots.spec.mjs`,
`node tools/verify.mjs copy health health-keep journey guide`.
