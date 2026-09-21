---
title: 'A single row drags into a folder beside FileBird'
type: 'defect'
created: '2026-09-21'
status: 'review'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: '94146ad'
context:
  - '{project-root}/plans/finish-the-suite.md'
  - '{project-root}/docs/handoffs/2026-09-20-s27-the-matrix-on-4-0-1.md'
  - '{project-root}/_bmad-output/implementation-artifacts/deferred-work.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Beside FileBird 6.5.8 on the box's MariaDB network, one unselected list row dragged onto a folder lights the folder and files nothing (matrix 2026-09-20, `with=filebird,shape=multisite-subdirectory` ✗ 7/10, three runs, mock on and off). The 09-11 bytes (`c85cde6`) passed the same cell. FileBird users are the migration audience; a person who installs this beside FileBird and drags one picture sees nothing happen.

**Cause (established before this spec, probe on `/var/www/ms` 2026-09-21):** not the arming. Our `th.column-title` instance is armed and would win the press. The press never reaches it: `core/media-list.php:283-285` (from `e68bbcd`, 2026-09-14) writes `.wp-list-table.media .column-title { width: 30% }` whenever a column beyond core's is visible — FileBird's `fb_filesize` is one — and with every column then sized (`cb` 2.5em, title 30 %, author 10 %, date 14 %, `fb_filesize` 10 %) the fixed-layout table has 36 % of slack, which Chrome hands to the only fixed-length column: the checkbox. `td.check-column` measured **324 px of 900**; core's `.check-column label` is `position: absolute; width: 100%; height: 100%`, so that whole band is the checkbox label — and beside FileBird the label is FileBird's drag handle (`handle: '.check-column'` on the `<tr>`). FileBird's `<tr>` instance starts the drag (`fb-v-dragging` helper), our droppable lights for it, our drop handler finds no `dragging` and no checked rows, files nothing. The same slack lands on the checkbox without FileBird when a user shows our own columns (`40 %` rule: **137 px of 982** measured), only there our `<tr>` instance takes the label press and the drag still works. `e68bbcd`'s own comment records the symptom ("a blank to its left") and made the rule conditional to dodge it; one sized third-party column brings it back.

**Approach:** File takes the leftover, as core intends; the floor is applied only when File would otherwise be squeezed. PHP keeps deciding the share (30 % beside another plugin's columns, 40 % among ours) but emits it under a body class, `body.vgml-file-share`, and hands the number to the script. `js/vergeml-media-list.js` measures once at load: File narrower than its share of the table → the class goes on the body (the other unsized columns absorb; the checkbox never does); File at or above its share → nothing, File is the sink. The class is on `body`, so the table a folder click swaps in (`swapTable()`) keeps it. No change to the drag layer: with the checkbox at 2.5em the press lands on the title cell and our instance runs, beside FileBird or not.

## Boundaries & Constraints

**Always:** the shipped files that change are `core/media-list.php` and `js/vergeml-media-list.js` only; every other change is a suite, a tool or a document. The proof is the failing cell itself, `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` → ✓ 10/10, plus `tests/tree/drag.mjs` run beside FileBird on `/var/www/ms` through a throwaway administrator and an uploaded file, and `modes.spec`'s row test on the box with a set that reproduces the slack without FileBird (two of our columns on). Mock on for every box cell; the box put back at HEAD (`deploy.mjs --check --box` up to date) at the end. The bisect stands on file history (`c85cde6` has no title-width rule; `e68bbcd` added it) and a runtime toggle, not on deploying old trees to the box: the plugin directory is one symlinked copy under the tech site, the ms network and — through ms2 — the shop.

**Never:** ms2 or the real shop; a describe on any library (no key on `/var/www/ms`, mock on for the cell); a version bump (the train cuts 4.0.2); a user-facing string (none is expected — a string that turns out necessary is proposed in the handoff, not written); a change to FileBird or to the drag layer's arming; a change to the five-minute script's steps or assertions (a detail line on failure is allowed); a push without saying what goes out.

## I/O & Edge-Case Matrix

| Screen state (columns visible) | Today (4.0.1) | After | Proof |
|---|---|---|---|
| core's three only (ours hidden, the shipped default) | File auto, checkbox 2.5em | the same — no share emitted, nothing measured | modes.spec set 1 |
| FileBird's `fb_filesize` beside core's three | File 30 %, **checkbox 324 px**, single drag files nothing | File auto ≈ 62 %, checkbox 2.5em, the drag files | the matrix cell; drag.mjs beside FileBird |
| two of ours on, nothing else (82 % sized) | File 40 %, **checkbox 137 px**, drag works only because our `<tr>` takes the label | File auto ≈ 55 %, checkbox 2.5em | modes.spec new set 4: checkbox < 60 px |
| five of ours on (109 % sized: Chrome scales the percentages) | File ≈ 37 %, checkbox 2.5em | File auto ≈ 28 % < 40 % → class on → the same 37 % | modes.spec set 2 (unchanged ceiling) |
| another plugin's columns without widths (the `e68bbcd` case) | File 30 % floor, theirs share the rest | File auto shares with them → below 30 % → class on → 30 % floor, theirs share the rest | reasoning; no such column on any site of ours — recorded as an assumption |
| many sized third-party columns (sum > 100 %) | percentages scaled, File 30/sum | File auto → 0 → below share → class on → identical to today | reasoning |
| a column shown or hidden live through Screen Options (no reload) | rule follows the PHP decision at load | the class was decided at load; the worst case is today's behaviour until the next load | open question |
| RTL | unchanged | unchanged (nothing directional) | the `ar` cell is not rerun here |

</frozen-after-approval>

## Code Map

- `core/media-list.php:279-287` — the share decision (`$core`, `$beyond`, `$theirs`) and the inline rule; `:297-306` `wp_localize_script( 'vergeml-media-list', 'vergemlList', … )` where the share number travels.
- `js/vergeml-media-list.js:96-108` — `draw()` on `DOMContentLoaded`; the measurement is one more call there. Mirror: `titles()` (`:70-94`) already reads `scrollWidth`/`clientWidth` after layout.
- `js/vergeml-tree.js:1578-1643` `swapTable()` — replaces the whole `.wp-list-table` (head included) on a folder click; why the class lives on `body`. `:2048-2150` `armDraggables()`/`armOne()` and `:2310` the drop handler — unchanged, the cause is not there.
- `tests/compat/five-minutes.mjs:194-213` `drag()` — the press at `row.x + 40` (the comma selector returns the `<tr>`, not the title cell: the row's first 40 px are where the thumbnail is when the layout is right — the step is a layout sentinel); `:357-366` the step. A `pressedOn` detail on failure only.
- `tests/tree/drag.mjs:106-137` `dragFileTo()` — the same press; runs standalone against a base URL and clears the first file's folders, so never against the tech library: `tools/box-drag-beside.mjs` (new) preps `/var/www/ms` like `runBox()`, uploads one file, runs it with `--with filebird` or without, cleans up.
- `tests/ui/modes.spec.mjs:534-622` — the row test's three sets; a fourth set and a checkbox-width assertion on every set.
- `tools/matrix.mjs:352-443` `runBox()` — the cell's prep and restore; unchanged.
- FileBird 6.5.8 (`Pluginexamples/filebird.zip`, `assets/dist/main.tsx-*.js`): `register()` puts `.draggable({ handle: '.check-column', helper: dragHelper })` on `#wpbody-content .wp-list-table tbody tr`; `style.css` `#the-list .check-column label:hover { cursor: move }`.

## Tasks & Acceptance

**Execution:**
- [x] T1 `core/media-list.php`: the title rule becomes `body.vgml-file-share .wp-list-table.media .column-title { width: 30%|40% }`; `vergemlList.share` = 30, 40 or 0. Comment says why the class.
- [x] T2 `js/vergeml-media-list.js`: `share()` — with `cfg.share` > 0, compare the head title cell's width with `share / 100` of the table's; below → `document.body.classList.add( 'vgml-file-share' )`. Called from `draw()`.
- [x] T3 Proof harness `tools/box-drag-beside.mjs`: prep as `runBox()` (throwaway administrator, FileBird linked in with `--with filebird`, mock on, snapshot restored in `finally`), one uploaded PNG, `node tests/tree/drag.mjs <ms url> <user> <pass>`, then the file, the two `Drag Target` folders, the link and the user removed. Run before the fix beside FileBird (the red line on record) and after, with and without FileBird.
- [x] T4 `tests/ui/modes.spec.mjs`: set 4 "two of ours on, the other plugins' columns off" (ROW_CEILING); every set also asserts the head checkbox cell is narrower than 60 px. `tests/compat/five-minutes.mjs`: the drag step's failure detail names what was under the press.
- [x] T5 Deploy to the box (`deploy.mjs --box`, `--check`), run the cell, drag.mjs both ways, `pnpm test:ui -- -g "the rows on the media list"`; `sprint-status.yaml` 4.4 → `review`; `deferred-work.md` entry closed with the cause; `bmad-code-review`; commit; handoff.

**Acceptance Criteria:**
- Given FileBird 6.5.8 linked into `/var/www/ms`, when `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` runs, then the row reads ✓ 10/10 with "drag one into the folder: the folder lit up; file N is in [F]" and the restore step green.
- Given the same network with FileBird, when `tests/tree/drag.mjs` runs through the harness, then every check is `ok`, `assign` is called in move mode from a plain drag, and the file, folders, link and user are gone after.
- Given the box's tech site with two of our columns on, when modes.spec's row test runs, then the head checkbox cell is narrower than 60 px and the tallest row is under the ceiling; the three existing sets keep their ceilings.
- Given a page with no column beyond core's, when it loads, then no `vgml-file-share` class is on the body and no title rule is emitted (the shipped default is untouched).
- Given the end of the session, when `node tools/deploy.mjs --check --box` runs, then the box is up to date with HEAD and `/var/www/ms` holds no `vgmlmatrix`/`vgmlprobe` user, no FileBird link, no `Probe run`/`Drag Target` folder and `vergeml_ai` reads `{"site_profile":""}`.

## Decisions taken

One question per step, investigation first (the cause was measured before a line of spec was written); the defaults below recorded with their reason. Advanced Elicitation offered once — the one design decision is put to Nathan as a single proposal with the alternative dismissed in a line.

- **Where the fix lives.** In the width scheme, not the drag layer. The drag layer is correct: our title-cell instance wins any press that reaches it. Widening the arming (e.g. claiming the checkbox label from FileBird) would fight FileBird for its own handle and still leave a 324 px checkbox on the screen.
- **Measure in the script rather than size the other plugins' columns.** The alternative — File always `auto` and a `:where()` fallback width for every third-party column that has none — needs no script and no measurement, but it loses File's floor whenever several third-party columns carry their own widths (three at 20 % + core's 24 % + ours: File auto falls to nothing, where today it keeps 30/sum). The measured class equals today's behaviour in that case and beats it in every other; the cost is one measurement at load and, only in the squeezed case, a reflow after first paint.
- **The class on `body`.** `swapTable()` replaces the table, head included; the column set changes only on a reload, so one decision at load, kept on `body`, is right for every swap.
- **Proof geometry stays.** The five-minute step's press at `row.x + 40` caught this; it is a layout sentinel as much as a drag test and is not moved to the title cell.
- **drag.mjs never against the tech library.** It clears the first file's folders and does not put them back (`tests-never-touch-live-state`); the harness runs it on `/var/www/ms` with its own upload.
- **No version bump, nothing pushed** without the go; the train cuts 4.0.2.

## Open questions

- **Live Screen Options.** A column shown or hidden without a reload leaves the class as decided at load; the worst case is today's layout until the next load. A `columns.js` listener would re-measure. Nathan's call whether it is worth a listener; recorded, not built.
- **A foreign drag over our folder lights it.** Beside FileBird, its own drag (from the checkbox) over our tree lights our folder and drops nothing. An `accept` on our droppables that takes only our helper would keep the folder honest. Visible behaviour, so a decision, not a patch here.

## Implementation Notes

- **The probe** (a scratch script, the runner's prep, jQuery UI instrumented from inside the page): the press at `row.x + 40` landed on `label` in `td.check-column` — `td.check-column` 324 × 81 px, its label `position: absolute; inset: 0`, the title `th` starting at x = 1141 of a 900 px table at x = 817; the drag that lit the folder was FileBird's (`_createHelper` on the `<tr>`, helper `fb-v-dragging`, handle `.check-column`), no `assign` request. Our `th.column-title` was armed (`vgml-drag`, our helper, `distance` 15). Without FileBird: checkbox 34 px, our helper, `assign` sent. With our two columns shown and no FileBird: checkbox **137 px of 982** (the `40 %` rule) — the drag survives only because our `<tr>` instance takes the label press. The width rules matched by the head title cell: `inline :: .wp-list-table.media .column-title { width: 30% }` (ours, `core/media-list.php`); by the checkbox: core's `2.2em` and `2.5em`.
- **The bisect.** `git show c85cde6:core/media-list.php` has no `column-title` rule; `git log -S"'30%' : '40%'"` → `e68bbcd` (2026-09-14) alone. Old trees were not deployed to the box: `/var/www/wp/wp-content/plugins/vergelabs-media-library` is one directory the tech site, `/var/www/ms` and ms2 (the shop) all run through symlinks.
- **Before the fix, on record:** the cell ✗ 7/10 (S27, three runs) and `drag.mjs` beside FileBird through the harness **7/19** — "the drag started and reached the folder" ✓, "the folder showed it was a target" ✓, "assign was called" ✗ `{}`, every filing check after it ✗.
- **After (final bytes, box digest of this tree):** `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` **✓ 10/10 · 44 s** ("drag one into the folder: the folder lit up; file 251113 is in [2092]"; "restored: {"site_profile":""}"); `shape=multisite-subdirectory` **✓ 10/10 · 42 s**; `node tools/box-drag-beside.mjs --with filebird` **21/21**, without **21/21**, the option restored and the plugins listing clean both times; modes.spec "the rows on the media list" **2 passed (3.9 m)** — checkbox **34 px** in all eight measurements, `body.vgml-file-share` off / on / – / off as predicted, File 433 px = 40 % of 1082 with five of ours on (the class on), 325 px = 30 % with the tech site's three third-party columns on (`qode-optimizer`, `seopress_alt_text`, `aioseo-details`, unsized: the class on), the press 40 px into the first row on the title cell in every set; tallest rows 81 / 98 / 211 / 81 px, as S10.6 had them.
- **Where the class fires, measured on the tech site** (a throwaway administrator, the hidden columns put back): arriving with theirs on → on, File 325/1082; five of ours → on, 433/1082; ours + theirs → on, 325; theirs on, ours off → on, 325. On `/var/www/ms` beside FileBird: off, File 560/900 (62 %); FileBird plus our two → off, File 358/900 (40 %, above the 30 % share, so nothing to do).
- **`wp_localize_script` hands `share` over as `"30"`**, a string; the script parses it, so `"0"` reads as no share.
- **The measurement's frame.** `share()` runs in the `requestAnimationFrame` after `DOMContentLoaded` (this script's listener runs before `js/vergeml-tree.js`'s, which narrows the table); `titles()` follows it in the same frame because the tooltips read what the widths cut short.
- **Not proof of this story, on record:** an unintended run of the whole UI suite against the tech site for 21 minutes — my wrapper passed `-g "the rows on the media list"` through a shell, which split it into file filters. 25 tests ran (brief, crawl, dashboard, folders) and test 26 (`folders.spec` "the word on a picture", which plants `fill-fixture.php` over SSH) was killed mid-way. Put right: `fill-fixture.php` `VGML_MODE=restore` → `restored`; the `vgmluirun` administrator removed; the two `vgml` users left on the tech site (`vgml-smoke` 09-14, `vgmls20` 09-19) predate today. Credits on the box licence: 24,144 now against 24,180 at S19's start on 09-18 (S19 spent on the shop in between) — the run cost at most a few (one search embedding on the AI screen; the folders tests answer their model routes themselves; the fill fixture spends nothing). The wrapper now calls Playwright's `cli.js` without a shell and was listed (`--list`) before it ran again: 2 tests.
- **Pre-existing, not this story's:** `node tools/verify.mjs surface escaping` fail on `94146ad` without this diff (`docs/security-surface.md` and `docs/security-escaping.md` have drifted from the code; "text sinks outnumber HTML sinks four to one": 244 against 65).

## Spec Change Log

- 2026-09-21 written from the S27 finding after the probe established the cause; the bisect is file history plus a runtime toggle, for the reason in Always.
- 2026-09-21 (build) T3's harness plants two `Drag Scratch` folders and a `vgmldrag` user the frozen AC 5 does not name; the end-of-session check covers them (Verification). The five-minute drag step reads `elementFromPoint` before every press and prints it only on failure — within "a detail line on failure", noted.

## Review Triage Log

2026-09-21, `bmad-code-review` over the working tree against `94146ad` (12 files, 657 lines), four lenses: blind hunter 13, edge-case hunter 9, verification gap 2 + 2, acceptance auditor 8. Verdicts and routes (`patch` = done in this tree before the reruns above):

| # | Finding | Verdict | Route |
|---|---|---|---|
| 1 | `share()` measures at `DOMContentLoaded`, before `vergeml-tree.js`'s listener narrows the table; the ratio survives percent columns but not the em checkbox or a px-sized third-party column (blind, acceptance, edge) | medium | patch: measured in the `requestAnimationFrame` after; `titles()` moved after it; `toggle` not `add` |
| 2 | nothing asserts the body class — AC 4 and the "five of ours → on" row are proved by reading the code; a broken `share()` leaves File squeezed with only a 98/100 row ceiling in the way (blind, VG, acceptance) | medium | patch: each modes.spec set carries the class it expects (off / on / – / off) and, with the class on, File ≥ its share |
| 3 | modes.spec `:567` `File < 50 % of the table` encodes the old scheme; File is the sink now (blind, VG, acceptance) | medium | patch: File < table − checkbox − 200 (its own width was the 759 px failure; the sideways-scroll check is that failure's mark) and the checkbox < 60 px on arrival |
| 4 | the 60 px checkbox threshold is wider than the 40 px press; a 50 px checkbox passes and puts the press on the label again (VG) | medium | patch: every set asserts `elementFromPoint( row.x + 40, mid )` is inside `.column-title` |
| 5 | harness `finally`: a REST call that throws (browser dead, sign-in lost) skips the ssh restore — mock on, FileBird linked, the user left; `chromium.launch()` outside the `try` does the same (blind, edge ×2) | medium | patch: launch inside the `try`, the sweep in its own `try`, the ssh line unconditional |
| 6 | `--with` without a value runs "beside nothing" and reports it; a failed sign-in surfaces as a 60 s selector timeout; a folder create without an id → `NaN` and a 60 s wait; a companion still linked after the run exits 0 (blind, edge ×3) | low | patch: exit 2 on a missing slug; the URL checked after sign-in; the id checked; the listing checked and ✗ when the companion is still there |
| 7 | `.harness/active.json` described the pre-probe story (fix in `vergeml-tree.js`, gates on suites that do not exist) (blind, acceptance) | low | patch: phase, scope and gates as built |
| 8 | the deferred entry's closing line buries the "read the companion's version" ask (blind) | low | patch: its own entry, with the shared-prep module (finding 9) |
| 9 | sixty lines of `runBox()` copied into the harness; the snapshot logic has drifted once before (blind, acceptance) | medium (developer) | defer: `matrix.mjs` runs its cells on import — a `boxPrep()/boxRestore()` module is a runner change of its own; `deferred-work.md` |
| 10 | the harness does not check the first row is its own upload; a leftover attachment would have its folders cleared (blind, acceptance) | low | reject: the list is newest first and the upload is made seconds before; nothing on the network can be newer |
| 11 | "core sizes it 2.5em" unverified (blind) | false | reject: the probe listed both rules on the head cell — `.widefat .check-column { 2.2em }` and `.wp-list-table td.check-column, th.check-column { 2.5em }`; the second wins |
| 12 | only the FileBird cell rerun; a Playground cell has a drag step and costs nothing (blind) | false | reject: the matrix installs `playground/vergelabs-media-library.zip` — the 4.0.1 bytes — so a Playground cell cannot see this tree; the other box cell was rerun (✓ 10/10) |
| 13 | a 4.0.2 changelog line is missing (blind) | — | reject (Nathan's copy): proposed in the handoff |
| 14 | AC 5 / the spec's end check do not name `vgmldrag`, `Drag Scratch`, `drag-beside.png` (blind, acceptance) | — | reject (spec edit); logged in the change log, covered by the end-of-session check |
| 15 | Implementation Notes empty, T5 unticked at review time (blind, acceptance) | — | reject (spec edit; build hygiene — filled now) |
| 16 | table not laid out at `DOMContentLoaded` → `0 < 0` → no floor (edge) | low | reject: the list table is never hidden on its own screen; the frame after (finding 1) is where it is measured |
| 17 | no re-measure on resize or a live Screen Options change (edge, VG) | low | reject: the ratio does not change with the window; the live toggle is the spec's open question, on record |
| 18 | fewer than two of ours on a site → set 4 mislabelled (edge) | low | reject: the plugin ships more than two columns; the suite already asserts ours > 0 |
| 19 | the CSS-only floor is gone: a script that does not run leaves no floor (edge) | — | reject: the spec's decision — the script is the screen's own and `line()`/`titles()` fail with it |
| 20 | the FileBird cell runs by hand, not in CI (VG) | medium | defer: the companion is linked per run from the box's copy — a matrix concern, on record with the ms2 flag |
| 21 | `elementFromPoint` runs before every press, printed on failure only (acceptance) | — | reject: no harm; the step and its assertion are unchanged |

After the patches: the cell **✓ 10/10 · 44 s**, the subdirectory cell **✓ 10/10 · 42 s**, the harness **21/21** both ways, modes.spec **2 passed (3.9 m)** with the new assertions — the lines in Implementation Notes.

## Verification

**Commands:**
- `node tools/deploy.mjs --box` then `--check --box` → `box up to date`.
- `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` → `✓ … 10/10`.
- `node tools/box-drag-beside.mjs --with filebird` and `node tools/box-drag-beside.mjs` → every line `ok`.
- `pnpm test:ui -- -g "the rows on the media list"` (the box) → 2 passed (list, grid).
- `node tools/deploy.mjs --check --box` at the end → up to date; the ms network clean (AC 5, plus the harness's own `vgmldrag`, `Drag Scratch 1/2`, `drag-beside.png` and the probe's `vgmlprobe`, `Probe run`, `probe-1.png`).
- Cost: nothing on this story — no key on `/var/www/ms`, mock on for the cells; the harness uploads one 146-byte PNG and deletes it. The unintended UI-suite run on the tech site (Implementation Notes) cost at most a few credits. Time: about 25 minutes of runs, plus the 21 minutes of that run.

**Results (2026-09-21):** every command above green — the lines are in Implementation Notes.
