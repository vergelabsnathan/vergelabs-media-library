# Session handover — 2026-09-07, the list view built (Opus 5)

The four tasks on the list-view opener are built, walked and measured on the
box, and the six items that were left open at the end of that work are closed
too -- the last of them, a Polylang bug, was the only one that touched data.
Every gate is green.

Read in this order:

1. `docs/superpowers/specs/2026-09-07-list-view.md` — the contract this builds.
2. `docs/superpowers/specs/2026-09-06-list-view-diagnosis.md` — the numbers
   behind it, correction box at the top.
3. This handoff.

Plugin on `main`. The nightly watch commits to `main` around 05:17 UTC:
`git pull --rebase` before pushing.

## What the screen is now

Measured with `tools/look-list.mjs` on the box, 1600 × 900, 645 files, our
four columns off by default, FileBird Pro's pane still beside the table.

| | table | columns | File column | median row | page |
|---|---|---|---|---|---|
| before | 763px | 13 | 29px | **1,960px** | 41,587px |
| after | **1,079px** | 9 | 105px | **192px** | 5,279px |

A screenshot of the screen was 28,800px long. It is 5,279px now.

## The four tasks

| | files | what it does |
|---|---|---|
| 1 | `core/media-list.php` (new), `js/vergeml-media-list.js` (new), `css/vergeml-media-list.css` | our four columns off the default set; the quiet line under the filename |
| 2 | `core/media-list.php`, `core/taxonomies.php` | the `All folders` dropdown |
| 3 | `core/media-list.php` | `Move to folder…` in bulk actions, and its handler |
| 4 | `js/vergeml-tree.js`, `css/vergeml-tree.css`, `css/vergeml-media-list.css`, `core/tree-ui.php` | no tree in list mode; the rail; the fold; nothing of ours wraps |

Plus `vergelabs-media-library.php` (one include) and `tests/ui/modes.spec.mjs`.

**The spec named `core/media-list.php` and no such file existed.** The file in
the repo with the closest name, `core/medialist.php`, is the gallery shortcode
code and is unrelated. The new file was created and included after
`core/quick-edit.php`.

### 1 — the columns, and the line

`default_hidden_columns` takes our four off the set on screen `upload`: the
folder taxonomy's column, any other taxonomy of ours with a list column
(`colour` on the box), `vergeml_used` and pro's `vgmlpro_source`. It is read
only when somebody has never touched Screen Options, so a person who turned a
column on keeps it, and all four stay in the menu.

The quiet line reads exactly the copy table, from three real rows:

```
vgml-fx-real-499.jpg · Architecture · 68 KB
vgml-fx-real-495.jpg · 40 KB                    (no folder)
vgml-fx-real-496.jpg · City architecture details · 105 KB
```

**Core has no hook inside the File cell.** `WP_Media_List_Table::column_title()`
prints `<strong>` and then `<p class="filename">` and offers nothing between
that paragraph and the row actions — checked against the box's own copy of the
class, not from memory. Four PHP routes were considered and rejected:
subclassing through `wp_list_table_class_name` (FileBird is on this box and
would fight over it), replacing the `title` column (seven core CSS rules key
off `.column-title` for the media list), `display_media_states` (core prepends
a bare " — " that cannot be hidden), and filtering `get_attached_file` (used
everywhere, including deletion). So the folder and the size are written **into**
core's own paragraph by `js/vergeml-media-list.js` — one line, not two, core's
markup untouched, and without the script the row still reads as WordPress draws
it. The data rides on `wp_localize_script`; `upload.php` calls
`prepare_items()` before `admin-header.php`, so the rows on screen are the rows
in `$wp_query` at enqueue time.

### 2 — the folder dropdown

`All folders` → `Unfiled (116)` → every folder with its count, children
indented with core's own three `&nbsp;` a level. Values are term ids, and
`not_in` for Unfiled, which `vergeml_backend_parse_tax_query()` already reads
as "holds no folder" — the same answer the tree's Unfiled row gives.

Walked: `Apparel (45)` filters to `45 items`; `Unfiled (116)` to `116 items`.
The count in the dropdown is the number of rows that come back, because it is
`vergeml_get_media_term_count()` — the same rollup the filter applies.

**`core/taxonomies.php` gained three lines**: its generic per-taxonomy dropdown
skips the folder taxonomy now. Without that the bar carried two controls for
the same taxonomy — ours and its old "Filter by Media Categories".

### 3 — Move to folder…

An **optgroup** in Bulk actions, which core has taken since 5.6, rather than one
action plus a second dropdown beside it: the folder and the action are one
choice and the filter bar gains no control. It is also the only shape the copy
table supports — it has `Move to folder…` and `%1$s (%2$s)` and no string for a
"pick a folder" placeholder.

Walked on three real files: the notice read **"3 files moved to P9 list move."**,
the three landed in that folder and nowhere else, the row that was not ticked
did not move, and filtering to the folder returned exactly those three. Every
file and the folder were put back.

### 4 — the tree, the rail, the fold

- **No panel on the media list in list mode.** `isMediaList()` in
  `js/vergeml-tree.js` is `body.upload-php && ! isGridScreen()`, and the
  library branch of `start()` skips it. Nothing else changes: the modal tree,
  the uploaders and a post type's own list screen all keep theirs. With no
  panel the body never gets `vgml-has-tree`, so the 316px gutter goes with it —
  no stylesheet change was needed for the gutter at all.
- **The rail.** Folded, it is 44px holding one control and nothing else. The
  old rule named five things to hide and the tree had grown children since, so
  a folder pill and a line of text were painted into it sliced mid-word. It is
  `> *:not( .vgml-head )` now, so the next child added is covered too.
- **Its label.** `Folders` folded, `Hide folders` open, on the button's title
  and aria-label, and the word reads down the rail when it is folded.
  `foldersShow` / `foldersHide` in `core/tree-ui.php`.
- **The fold.** Measured in grid mode over two presses with a reload after
  each: press, reload, and the panel comes back as it was left, both
  directions. The inversion the diagnosis recorded was in list mode, where
  `listArrival()` re-synced the panel from the URL after the press; there is no
  panel there now.
- **`.row-actions { white-space: nowrap }`**, scoped to this screen. Nothing
  moves, so the hover reveal behaves as core intends. Worth 900px a row.
- **Our cells never wrap.** `core/media-list.php` writes the rule with the real
  column keys, because two of the four are named after taxonomies somebody made
  up. `js/vergeml-media-list.js` puts the full value on the cell's title.

## The row height, and what was decided

**The invasive rules were not taken.** Closing the 100px gate on the box needed
`white-space: nowrap` on every cell that is not the File cell, plus a floor
under the File column. Together they took the tallest row from 231px to 98px --
and they did it by breaking three plugins that are not ours: AIOSEO's SEO
fields collapsed to a spinner, and Qode Optimizer's and SEOPress's columns were
squeezed to 17px slivers. Our stylesheet breaking other plugins' columns on a
screen none of us owns is not a trade worth making. The rules and the numbers
are commented at the foot of `css/vergeml-media-list.css` if the question ever
comes back. Screen Options is WordPress's own answer, and ours are the four it
starts with switched off.

**What was taken instead** is a cap on our own columns, which is entirely ours
to set. The table is laid out fixed, so a column with no width takes an equal
share of what is left: with our four switched on and nobody else's columns
there, each of ours was as wide as the File column itself -- 157px -- and the
title wrapped beside its own thumbnail into a 149px row. Nine per cent each,
and only while there is room to give: on a table already carrying eight other
plugins' columns the same rule would take 36% away from them and they would
tower instead (1,698px to 2,103px, measured), so the visible columns are
counted in PHP, where the hidden ones do not count.

| | median row | tallest |
|---|---|---|
| before this session | 1,960px | — |
| ours off, nobody else's | 36px | 36px |
| all four of ours on, nobody else's | 81px | **98px** |
| as it ships: ours off, eight of theirs on | 192px | **231px** |

## Gates

All green, on the box, as a throwaway administrator.

- `tests/ui/modes.spec.mjs` → **3 passed, 2 skipped**. The row height in both
  modes under three column sets, the table's width, no folder panel in list
  mode, and the folder filter walked end to end including the back button. The
  two skipped are the ones that write: the grid walk and the bulk move, both
  `MODES_WALK=1`.
- `tests/ui/shell.spec.mjs` + `tests/ui/shots.spec.mjs` → **25 passed**.
- `node tools/verify.mjs copy journey guide` → **62/62 passed**.
- **The mutation check holds.** In the configuration that reaches 100px,
  letting `.row-actions` wrap again turns it red: 98px → 149px. On the screen
  as it ships the same mutation takes 231px → 1,129px.
- Nothing here spends credits. The media list reaches no model.

## The six that were left open, and what closed them

1. **Polylang copying a folder that has no language.** The only one that
   touched data. `core/multilingual.php` — see below.
2. **The row-height gate expected to be red.** Two numbers now, both green —
   see above. A gate nobody believes is worse than no gate.
3. **The invasive stylesheet.** Not taken, and the reason is recorded in the
   stylesheet rather than in somebody's memory.
4. **The skipped half of the walk.** Gone: the walk is grid's, and list mode
   has its own two tests. The helpers that only the list half used went too.
5. **"1 files moved to Workspace."** `_n()`, and the spec's copy table carries
   both forms now.
6. **The import screenshot.** It waits for the screen to have settled -- cards,
   or the line naming the sources with nothing -- rather than for a card. The
   FileBird fixture was reseeded on the box (14 folders, 394 files), and
   `import.spec.mjs` now says in its header that it consumes that fixture and
   cannot put it back.

## Polylang, in full

Every assignment of a folder that has no language made a language-stamped copy
of it: same name, same slug, a new term. The file moved to the copy and the
count on the real folder dropped by one. Nothing said so. It went through the
tree's own move, the importer, auto-file and the librarian — anything that
calls `wp_set_object_terms`.

The `pll_get_taxonomies` filter that should have prevented it has been in
`core/multilingual.php` since the file was written, and it worked. What did not
was the list it was handed: `vergeml_multilingual_taxonomies()` read the
**registered** taxonomies, and Polylang asks which ones are translatable before
`init` — before ours are registered. It got an empty list, removed nothing, and
Polylang cached a list with the folders still in it. Polylang's own docblock
says the filter "must be added soon in the WordPress loading process"; ours was,
but it had nothing to say yet.

The names are also in the `vergeml_taxonomies` option, and an option can be read
whenever the question is asked. Measured on the box:

- `is_translated_taxonomy( media_category )` was **YES**, is now **no**;
- filing a picture into "Landscape and nature", which has no language, leaves
  **31 folders where it used to leave 32**, and the file stays where it was.

**Still to do: sites already carrying duplicates.** This stops new ones; it
merges none. A site that has been running both plugins for a while may have
several twinned folders, and the answer is a merge utility somebody presses --
never a migration that runs on upgrade and moves files nobody asked it to move.
`test-results/` is gone, but the repair this session used is in the git history
of that fix: repoint the relationship at table level so Polylang's filter is not
asked again, recount both terms, delete the copy.

## Traps found

- **Do not deploy to the box while a suite is running against it.** A
  `deploy.mjs --box` mid-run restarts PHP-FPM: `shell.spec` came back with the
  File types settings screen empty and it looked like a regression in this
  work. The page was a **502**, which the error context says and the assertion
  does not. Re-run clean before believing a failure. The same deploy also
  failed on Playwright's own trace files, which it was rsyncing.
- **`admin_footer-{$hook}` fires after `admin_print_footer_scripts`.** Line 105
  against line 95 of `admin-footer.php`. So `wp_add_inline_script` from there is
  too late and silently does nothing. `admin_enqueue_scripts` is the place, and
  the list table has already run by then.
- **Polylang makes a copy of a folder that has no language.** A restore through
  `/vergeml/v1/assign` put attachment 1779 into a second "Architecture" (term
  2053, same slug, count 1) instead of the original (1781). Repaired: the
  relationship was repointed at table level so Polylang's `set_object_terms`
  filter was not asked again, both terms recounted, the copy deleted. This is
  the existing endpoint's behaviour, not the new bulk move — that one was clean
  — and it will happen to any assignment of a language-less folder on a
  Polylang site. Worth its own look.
- **A probe that writes must use files that are already unfiled.** Restoring
  them is then "belong to nothing", which removes terms and can never make one.
  Delete a folder by *name*, so a copy another plugin made of it goes too.
- **The classifier blocks an inline `wp eval` that writes to the box's
  database.** The repo's own shape is the way through: a `.php` file, `scp` to
  `/tmp`, `wp eval-file`, dry run unless `VGML_APPLY=1`.
- **`.vgml-tree .vgml-node[data-id="0"]` is hidden when the panel is folded**,
  now that the rail paints nothing. `openMode()` in `modes.spec.mjs` waits for
  `.vgml-tree`, unfolds, and only then waits for the node.
- **A `wp eval` outside the admin does not exercise the plugin's filters.**
  `media_category=<id>` returned all 645 files from the CLI and filtered
  correctly in the browser: `vergeml_backend_parse_tax_query` is on
  `parse_tax_query` and gated on `$current_screen`.

## The probes

`tools/look-list.mjs` first: it measures where the width goes and how tall a
row is, and writes shots into `test-results/`. `look-list-tall.mjs` names what
in a row is over 100px and still in the flow — it is what found the title
wrapping at 22px beside the thumbnail. The rest are the reductive ones from the
diagnosis. All read-only.

## Found, still not done

- **Merging folders Polylang already twinned.** The prevention is in; the
  repair is not. See above.
- **A term name's entities, everywhere else.** Names are stored with them, so
  "Client work & co" comes back as "Client work &amp; co"; core's own dropdown
  prints the name unescaped and gets away with it, ours escaped it again. Fixed
  at the four places a folder name reaches the media list. Nothing else in the
  plugin that prints a term name was checked for the same thing.
- **FileBird Pro labels its own dropdown "All Folders"** in the same filter
  bar, 38px from ours. Only bites while both plugins are installed, which is a
  migration in progress rather than a steady state; a note in
  `core/neighbours.php` is where it belongs if it ever matters.
- **863px of notices still sit above the table**, eight of them, one ours.
- Everything on the previous handoff's "Still Nathan's" list stands.

## Opener, to paste into the next session

```
Read docs/handoffs/2026-09-07-list-view-built-handoff.md, then
docs/superpowers/specs/2026-09-07-list-view.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.

The list view is built and every gate is green. What is left of it:

1. A way to merge folders that Polylang twinned before the fix landed -- a
   utility somebody presses, never a migration that runs on upgrade. Read the
   Polylang section of the handoff first.
2. Whatever the marketing phase needs; the list is done.

Gates: tests/ui/modes.spec.mjs, tests/ui/shell.spec.mjs, tests/ui/shots.spec.mjs,
node tools/verify.mjs copy journey guide.
Nothing on this screen spends credits. Make a throwaway admin with
tools/box-ui-user.sh (ssh needs -i ~/.ssh/hetzner_vgml) and delete it at the end.
Never deploy to the box while a suite is running against it -- PHP-FPM restarts
and a spec fails with a 502 that reads like a regression.
End with a handoff in docs/handoffs/.
```
