# The migration page: one card per source

Phase 8, task 23 of `plans/folders-one-tree.md`. The mock is the stop point:
nothing below "What changes in the code" is built until Nathan has looked at
it. Mock: `docs/superpowers/mocks/2026-09-06-import-cards.html`, four boards.

Written on 2026-09-06 from `core/import-sources.php`, `core/import.php`,
`core/import-ui.php` and `js/vergeml-import.js`, against the box: 645 files,
641 pictures, 19 folders, 373 filed, 272 in no folder, and a FileBird tree
of 14 folders over 394 of those pictures, made for this phase (see "The
FileBird fixture").

## What the screen does wrong today

Screenshot of the box as it stands: `docs/superpowers/mocks/shots/`
`import-today.png`, taken with the FileBird fixture below in place. The
fixture has since been removed, so the box today finds nothing at all and
the screen shows only the spreadsheet halves and the history.

1. **Only what was found is shown.** `vergeml_import_found()` skips a source
   with no folders, so six of the seven never appear. The file's own comment
   says the point of listing them all is to say "nothing to import from
   FileBird" rather than read as unsupported; the code does the opposite.
2. **The number is the source's, not this site's.** "14 folders, 394 files"
   says what FileBird holds. It does not say what pressing the button does
   here: how many folders are made, how many merge into folders that already
   exist, where the files land.
3. **Two presses for one act.** Preview import, then Import. The feedback
   contract (spec §4.4) says confirmation is the button itself.
4. **The button does not say what it does.** "Preview import" carries no
   number. Contract §4.1.
5. **The history says `csv`** — a source key, not a name — and "4d" instead
   of a date. Five rows of "csv — 5 folders and 3 files" that cannot be told
   apart.
6. **The destination is never named.** One hierarchical taxonomy means the
   "Import into" select is not rendered at all, so the person is not told
   the folders land in Media Categories.
7. **The spreadsheet is above the plugins** and reads as the main road. It
   is the fallback.
8. **Nobody has reviewed this screen.** `tests/ui/shots.spec.mjs`
   screenshots it as soon as `.vgml-shell-content` is visible, which is
   before the two REST calls land: every shot on file is the spinner and the
   words "Looking for folders to import…".

## The card

One card per source, in one list, in this order: the sources with folders
here, then the sources without, then the spreadsheet in its own section.
Names as text. **No logos** — trademarks, and the directory rejects them.

**A source with folders** carries four things and one button:

- name, and its author in the muted grey beside it
- what it holds here: `14 folders · 394 files`
- what the button will do: `12 new folders, 2 merged into folders you
  already have, and 394 files filed into Media Categories.`
- the button: `Import 14 folders`

**While it runs**, the button carries its own progress and nothing else
moves: `Importing · 214 of 394 files`, with a fill behind the label. The
line above it counts the folders as they are made. The Folders count in the
rail counts down at the same time, because that is the thing changing.

**When it is done**, the card states the fact where the person was looking:
`12 folders made · 394 files filed`, then `30 folders now, 116 files in no
folder. Apparel and Workspace took 43 of them into folders you already had.`
The button becomes `Undo · 12 folders`.

**A source with nothing** is the name, the author, and `No folders found`.
No button — contract §4.1: a control that cannot be used is not shown.

## Three decisions for Nathan

1. **The six empty sources: a card each, or one line.** Board 3 draws both.
   Shape A is the brief read literally and costs six rows of the same
   sentence above the one card that can be acted on. Shape B keeps the
   promise in a sentence — "Also read, with no folders on this site: Folders
   by Premio, HappyFiles, …" — and puts the card that matters first.
   **Recommended: B**, and the sentence ends with the fact a person with a
   deactivated plugin needs: a card appears for any of them that has folders
   here, whether or not the plugin is still switched on.
2. **There are seven plugin sources, not six.** The brief names FileBird,
   HappyFiles, Folders by Premio, Real Media Library, Wicked Folders and WP
   Media Folder. The registry also reads **WP Media Folders** by Damien
   Barrère (`feml-folder`). Drawn in; say if it should go.
3. **The spreadsheet is one card with one button** (`Choose a CSV…`), and
   writing one out moves to its own section below (`Write out your
   folders`), because exporting is not importing. Today the two sit side by
   side above everything else.

## The defect the card cannot be built on

`vergeml_import_plan()` and `vergeml_import_run()` disagree, so the outcome
line the card needs is not a number the plugin can state today.

Both walk the source's folders parent-before-child and key each one as
`name|our parent id`. The run inserts each folder as it goes, so a child's
parent is a real term id. The plan cannot insert, so it puts the string
`new:<source id>` in the map — and `vergeml_import_key()` casts the parent
with `(int)`, which turns `new:4` into `0`. **Every folder whose parent is
about to be created is keyed as if it sat at the top of the tree.**

Measured on the box, FileBird → Media Categories:

| | plan | run |
|---|---|---|
| Apparel, under Products | `apparel\|0` → **merges** into our top-level Apparel (term 1515) | `apparel\|<new Products id>` → **creates** |
| new folders | 11 | 12 |
| merged | 3 | 2 |
| files into folders that already existed | 88 | 43 |

Two consequences, both live today: the preview under-counts what an import
creates, and two source folders with the same name under different parents
collide with each other in the preview. The mock states the run's numbers,
because those are the true ones; the box prints the plan's.

**This is not in Phase 8's task.** It is named here because task 23's card
puts the outcome line on the screen before the press, which makes the plan
load-bearing. Nathan's call: fix it inside this phase, or leave the card at
`14 folders · 394 files` with no outcome line until it is fixed.

## Copy

Every new string, for approval. Nothing else on the screen changes wording.

| where | now | after |
|---|---|---|
| under the title | Bring folders over from another plugin or a CSV. The other plugin is left exactly as it is, and every import can be undone from here. | `645 files · 641 pictures · 19 folders · 272 in no folder`, and under the first section head: `The other plugin keeps its folders. Every import can be undone here.` |
| section head | Found | `From another plugin` |
| a source with folders | %1$s folders, %2$s files | `%1$s folders · %2$s files` |
| what it will do | — | `%1$s new folders, %2$s merged into folders you already have, and %3$s files filed into %4$s.` |
| its button | Preview import → then Import | `Import %s folders` |
| while it runs | Importing… / %1$s of %2$s files | `Importing · %1$s of %2$s files` |
| folders so far | — | `%s folders made so far.` |
| when it is done | Imported. | `%1$s folders made · %2$s files filed` and `%1$s folders now, %2$s files in no folder.` |
| a source with nothing | *not shown at all* | `No folders found` |
| nothing anywhere | No folders were found in any other plugin on this site. If your structure lives in a spreadsheet, read it in here — otherwise folders can be worked out from your own pictures. | *gone — every source says for itself whether it has anything* |
| the spreadsheet | A file, instead of another plugin / Read in / Write out | `From a spreadsheet` / `A CSV file` / `Write out your folders` |
| history head | Recent imports | `Imports you have made` |
| a history row | csv — 5 folders and 3 files · 4d · Undo this import | `A CSV file · 5 folders, 3 files` · `2 September, 08:00` · `Undo · %s folders` |
| after an undo | Undone. The folders we made are gone, and anything you sorted yourself is exactly where you left it. | `%1$s folders removed · %2$s files back where they were` |

Kept as they are: the screen's title, the CSV format sentence, the merge
sentence, and every error string.

## What changes in the code

Not built. This is the shape the next session implements once the mock is
approved.

- `core/import-ui.php` — `vergeml_import_found()` returns every source with
  a `folders` count of 0 rather than skipping it, and adds the plan's four
  numbers for a source that has folders, so the card can state the outcome
  without a second request. The `l10n` block takes the strings above.
- `core/import.php` — `vergeml_import_plan()`'s parent map, if Nathan takes
  the fix in this phase.
- `js/vergeml-import.js` — `render()` orders found before not-found;
  `sourceCard()` grows the outcome line and loses the preview step;
  `showPlan()` goes; the progress moves into the button; `historyBox()`
  names the source and dates the row.
- `css/vergeml-shell.css` — the card rules from the mock's own style block.

## Proof

- `tests/ui/shots.spec.mjs` — the import shot waits for the cards before it
  fires, so the screenshot on every push is the screen and not the spinner.
- `tests/ui/import.spec.mjs`, new, under `IMPORT_WALK=1`: seven cards
  present with FileBird first; the six others carry no button; the outcome
  line's numbers equal what the import then does; the import runs, the
  card's done line matches the tree, and the undo puts it back. Spends
  nothing. It makes and removes its own FileBird rows.
- `tests/tree/import-plan.php`, new: the plan and the run agree on a tree
  three levels deep with a repeated folder name. Mutation check: restore the
  `(int)` cast and the suite goes red.

## The FileBird fixture

The box's FileBird Pro 6.5.8 is active with **zero** folders, so there was
nothing to draw a detected card from. A fixture of 14 folders over 394 of
the site's own 641 pictures was written straight into `wp_fbv` and
`wp_fbv_attachment_folder` — a tree by site area and campaign, the way a
customer's FileBird looks, with Apparel, Workspace and Portrait chosen to
collide with ours. Nothing of ours was touched; our importer only reads
those tables.

The scripts are `tools/box-filebird-fixture.php` and
`tools/box-filebird-clear.php`. The clear script empties both tables, which
is right only because the probe read 0 rows in each before the fixture went
in.
