# Session handover — 2026-09-06, Phase 8 task 23 built (Opus 5)

Task 23 is built to the approved mock and pushed. Four tasks, all four gates
green, credits untouched. Three commits on `main`, `e0999bd` at the top.

The nightly watch commits to `main` around 05:17 UTC: `git pull --rebase`
before pushing.

## What was built

| task | file | what changed |
|---|---|---|
| 1 | `core/import.php` | the plan keys a folder against the parent the run gives it |
| 2 | `core/import-ui.php` | every source returned, with the plan's numbers; the copy table's strings; the facts line; the history named and dated |
| 3 | `js/vergeml-import.js`, `css/vergeml-shell.css` | the card as drawn |
| 4 | `tests/ui/shots.spec.mjs` | the import shot waits for the cards |

One extra file: `core/import-sources.php` gains one field, `'qualify' => true`
on Premio, so the also-read line reads "Folders by Premio" as the mock draws
it without a plugin name hard-coded in the JS.

Screenshot of the built screen: `test-results/shot-import.png`, taken by
shots.spec with the fixture in place. It matches board 1 with the shape B
line, which is what Nathan took.

## The defect, closed

`vergeml_import_key()` cast the parent with `(int)`, which turned the plan's
`new:<source id>` placeholder into `0`. Every folder whose parent was about to
be created was keyed as though it sat at the top of the tree.

Measured on the box after the fix, FileBird → Media Categories:

| | before | after | the import |
|---|---|---|---|
| new folders | 11 | **12** | 12 |
| merged | 3 | **2** | 2 |
| files filed | 394 | 394 | 394 |

The preview now says exactly what the import does, which is what lets the card
state an outcome before the button is pressed.

## Evidence

- `tests/tree/import-plan.php` (new) → **22/22 passed** on the box.
  Mutation check run: `(int)` put back → **19/22**, red on exactly the three
  rows that compare the plan with the run (plan 2 new / 3 merged against the
  import's 4 / 1).
- `node tools/verify.mjs copy journey guide` → **passed copy, guide, journey**
  (journey 62/62).
- `npx playwright test shell.spec shots.spec` → **25 passed (5.9 m)**.
- `IMPORT_WALK=1 npx playwright test import.spec` (new) → **3 passed (1.8 m)**.
- **Credits: 0 spent.** 25,971 before, 25,971 after. Nothing on this screen
  reaches a model.
- Read-only probe on the box: of the 394 pairs the import would file, **0**
  were already in the folder they map to, so the plan's assignment count and
  the run's agree on this library. That is the second way the two could drift
  and it is not drifting here — see "Found, not done".
- Box left as found: the FileBird fixture removed (14 folders, 394 links, both
  tables at 0), the throwaway admin `vgml-p8b` deleted, `/tmp` scripts removed,
  library at 19 folders and 272 in no folder.

## Decisions taken in the session

Nathan's four were settled before it started. Three more came up; all three are
his to overrule.

1. **Two strings dropped rather than rewritten.** `mergeNote` ("If you already
   have a folder with the same name in the same place…") and `stillThere`
   ("Your folders in the other plugin have not been touched") lived in the
   preview step, which is gone. The mock draws neither. The section note "The
   other plugin keeps its folders. Every import can be undone here." says what
   `stillThere` said, and the outcome line states the merge in numbers. The
   spec listed the merge sentence under "kept as they are", so this is the one
   place the mock was read over the spec.
2. **One string extended.** `planPlain`, used when nothing merges, needed the
   destination the four-placeholder form gained:
   `%1$s new folders and %2$s files filed into %3$s.`
3. **`tests/tree/copy.mjs` takes a Phase 8 block** — seven struck strings,
   following the Phase 4, 5 and 6 pattern, so the old wording cannot rot back.

## What this session cost the box

**One import came off the record and cannot be put back.** The first run of
`import.spec.mjs` went red on a regex in the spec itself — `[\d,.]+` matches a
bare comma and a full stop, so the last "number" it read off the outcome line
was the sentence's period. The import it had already run was correct (12
folders made, 394 files filed), and it was undone by hand straight after.

But `vergeml_import_run()` keeps the last five imports and drops the oldest to
make room, and the undo goes with the record. Writing the FileBird record
pushed the CSV import of 1 September 11:02 (20 folders, 367 files) off the log,
so its 20 folders are now permanent on the box. Product behaviour, not
something this phase introduced, and only the test box — but it was avoidable.

`import.spec.mjs` now reads the log first and **stops** rather than run against
a full one, and asserts the record count is unchanged after its undo. The box
holds four imports where it held five.

## Traps found

- **`[\d,.]+` is not a number.** It matches the punctuation between clauses.
  `\d[\d,.]*` is what `numbersOn()` in `tests/ui/fixtures.mjs` already uses.
  Cost one red run and one hand-undo.
- **The box's `admin` password is not `VgmlTest7pass` any more.** A run mid-way
  through the session started answering "the password you entered for the
  username admin is incorrect". Make a throwaway administrator the way CI does
  — `ACTION=create UI_USER=… UI_PASS=… bash tools/box-ui-user.sh` over ssh —
  and delete it at the end.
- **ssh to the box needs `-i ~/.ssh/hetzner_vgml`.** `~/.ssh/config` sends
  `id_ed25519` to every host and the box refuses it.
- **A Python edit script must not mix literal `·` with `\uXXXX` escapes.**
  Running the whole body through `unicode_escape` mangles the literal ones into
  `Â·` and leaves the escaped ones correct, so half the file looks right.

## Found, not done

- **The plan and the run can still drift a second way.** The plan counts every
  (folder, file) pair it would file; the run skips a pair whose file is already
  in that folder. On this box the difference is zero, so the card is honest
  here — but on a library where somebody has already filed by hand into a
  folder the import merges into, the card would promise more files than it
  files. The fix is for `vergeml_import_plan()` to exclude pairs that already
  exist, which is one NOT EXISTS query.
- **Dead CSS.** `.vgml-import-card`, `.vgml-import-head`, `.vgml-import-actions`
  and `.vgml-import-plan` no longer exist in the DOM but still carry rules in
  `css/vergeml-shell.css`, grouped with `.vgml-ai-card` rules that Duplicates
  still uses. Left alone rather than untangled inside this task.
- **`tests/tree/import-ui.mjs` is stale.** It drives the screen through
  `options-general.php?page=media-import-folders`, which the screen left when it
  moved into the plugin's own menu, and it waits on `.vgml-import-card`, which
  is gone. It is in no gate. `tests/ui/import.spec.mjs` replaces it; deleting
  it is a one-line decision nobody has taken.
- **The mock's third done sentence is not built.** Board 2 draws "Apparel and
  Workspace took 43 of them into folders you already had." The copy table's
  "when it is done" row has only the two lines that are built, and the run
  returns no per-folder attribution, so it would need `vergeml_import_run()` to
  record which merged folders received files.
- **Only one destination gets its numbers up front.** A site with two or more
  hierarchical tree taxonomies keeps the "Import into" select, and changing it
  asks the server for that destination's plan. The card's first paint carries
  the primary taxonomy's numbers.
- Everything on the Phase 7 handoff's "Still Nathan's" list stands, unchanged.

## The FileBird fixture

Removed at the end of this session, both tables at 0. The next session that
wants to walk the screen must put it back:

```
scp -i ~/.ssh/hetzner_vgml tools/box-filebird-fixture.php root@46.225.66.194:/tmp/vgml-filebird-fixture.php
scp -i ~/.ssh/hetzner_vgml tools/box-filebird-fixture.sh  root@46.225.66.194:/tmp/
ssh -i ~/.ssh/hetzner_vgml root@46.225.66.194 'bash /tmp/box-filebird-fixture.sh'
```

## The numbers, all the box's own

| | |
|---|---|
| files · pictures | 645 · 641 |
| folders · in no folder | 19 · 272 |
| FileBird, with the fixture | 14 folders · 394 files |
| what the card promised | 12 new, 2 merged, 394 filed into Media Categories |
| what the import did | 12 made · 394 filed → 31 folders, 116 in no folder |
| imports on record | 4 (was 5 — see above) |
| credits | 25,971 before, 25,971 after |
