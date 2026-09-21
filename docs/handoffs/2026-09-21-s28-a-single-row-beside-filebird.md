# S28 — story 4.4: a single row drags into a folder beside FileBird

**Date:** 2026-09-21, morning. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-20-s27-the-matrix-on-4-0-1.md`. **Plan:**
`plans/finish-the-suite.md` — "Story 4.4 — done" is the one-paragraph record.
**Spec:** `_bmad-output/implementation-artifacts/spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md`.
Plugin `94146ad` → the build commit → this handoff's commit. **Nothing pushed, no
version bump** (4.0.2 is the train's). **Spent on this story: nothing** — no key on
`/var/www/ms`, mock on for every box cell. Spent by mistake: at most a few credits
(below).

## What Nathan said

"bmad-spec the FileBird drag story (…armOne() and the drop handler at :2310), bisect
between c85cde6 and 4.0.1 on the box's ms network, fix, prove with the FileBird cell
and the tree drag specs, bmad-code-review. Stop points: nothing on ms2 or the real
shop; mock mode on the box cell; no version bump; every user-facing string is mine;
say what goes out before any push." And "Go: measured floor" on the one design
question.

## The cause — not where S27 pointed

The arming is fine: our `th.column-title` instance was armed and would have won
the press. The press never reached it. A probe on `/var/www/ms` (jQuery UI
instrumented from inside the page, the suite's exact drag) showed the press at
`row.x + 40` landing on the **label in `td.check-column`**, which was **324 px of
the 900 px table**, with core's label stretched over the whole cell and FileBird's
draggable handle on that label. FileBird's `<tr>` instance started the drag, our
droppable lit for it, our drop handler found no `dragging` and no checked rows.

Why the checkbox was 324 px: `core/media-list.php` (from `e68bbcd`, 2026-09-14)
wrote `.column-title { width: 30% }` once any column beyond core's was visible —
FileBird's `fb_filesize` is one. With every column then sized (2.5em + 30 % + 10 %
+ 14 % + 10 %), the fixed-layout table had 36 % of slack and Chrome hands slack to
auto columns first, then to fixed-length ones — the checkbox was the only one.
`e68bbcd`'s own comment records the symptom ("a blank to its left") and made the
rule conditional to dodge it. The same slack lands on the checkbox without FileBird
when a user shows our own columns (**137 px of 982**); the drag survives there only
because our `<tr>` instance takes the label press. So a visible layout defect on
any site with one sized third-party column (optimisers, SEO, FileBird), not a
FileBird one.

Bisect: `c85cde6` has no title rule; `git log -S` names `e68bbcd` alone. Old trees
were not deployed to the box — the plugin directory is one symlinked copy under
the tech site, ms and ms2 (the shop).

## The fix (two shipped files)

- `core/media-list.php`: the share (30 % beside another plugin's columns, 40 %
  among ours) is emitted under `body.vgml-file-share` and passed as
  `vergemlList.share`; File is `auto` otherwise, as core has it.
- `js/vergeml-media-list.js`: `share()` measures once, in the frame after load
  (after `vergeml-tree.js` has narrowed the table): File below its share → the class
  goes on and the unsized columns beside it give way; at or above → File is the
  column that takes the leftover. On `body`, so the table a folder click swaps in
  keeps it. `titles()` follows in the same frame.

Equal to today wherever File was squeezed (five of ours: File 40 %; the tech
site's three unsized third-party columns: 30 %), a 34 px checkbox everywhere else.

## The proof (all on the final bytes, box up to date)

| | before | after |
|---|---|---|
| `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` | ✗ 7/10 | **✓ 10/10 · 44 s** — "file 251113 is in [2092]" |
| `node tools/box-drag-beside.mjs --with filebird` (new: `tests/tree/drag.mjs` on `/var/www/ms`, throwaway admin, one upload, FileBird linked in, all put back) | **7/19** — reached the folder, `assign` never called | **21/21** |
| `node tools/box-drag-beside.mjs` (alone) | — | **21/21** |
| `shape=multisite-subdirectory` cell | ✓ | **✓ 10/10 · 42 s** |
| modes.spec "the rows on the media list" (the box; a fourth set "two of ours on"; per set: checkbox < 60 px, the press 40 px into the first row on the title cell, `body.vgml-file-share` off / on / – / off, File ≥ its share when on) | 137 px checkbox with two of ours | **2 passed (3.9 m)**, checkbox **34 px** in all eight measurements, rows 81 / 98 / 211 / 81 px |

`docs/compatibility.md`'s FileBird row is ✓ "(rerun 2026-09-21)". The ms network
is as it was after every run (users 0, folders none, attachments none, option
`{"site_profile":""}`, no FileBird link); `deploy.mjs --check --box` up to date.

## Review

Four lenses, 21 rows in the spec's triage log; 8 patched before the reruns
(measure in the frame after load; the class and File's share asserted per set;
the arrival assertion restated for File as the sink; the press-point assertion;
the harness's `finally` made unconditional and its edges named), 2 deferred
(`runBox()`'s prep as a module both tools share; the FileBird cell in CI —
Nathan's call), 11 rejected with reasons.

## On record: a mistake, put right

My wrapper for the row test passed `-g "the rows on the media list"` through a
shell; Windows split it into file filters and Playwright ran the **whole UI suite
against the tech site for 21 minutes** (25 tests: brief, crawl, dashboard, folders;
test 26 killed mid-way). Put right: `fill-fixture.php` restore → `restored`; the
`vgmluirun` administrator removed (the two `vgml` users still there are from
09-14 and 09-19). Credits on the box licence: **24,144** now against 24,180 at
S19's start on 09-18, and S19 spent on the shop in between — the run cost at most a
few (one search embedding; the folders tests answer their model routes
themselves; the fill fixture spends nothing). The wrapper now calls Playwright's
`cli.js` without a shell and is `--list`ed first. Note for `tools/`: the repo has
no wrapper that makes the throwaway UI administrator around a local run; mine is a
scratch file, and CI makes its own.

## Strings that are yours

None shipped. One proposal for `readme.txt`'s 4.0.2 changelog, since a FileBird
user will notice: "Dragging one file into a folder works again beside FileBird
and other plugins that add a column to the media list; the checkbox column no
longer takes the table's spare width." And `docs/wordpress-org-submission.md:39`
still counts the 09-20 run (15 / 2 / 1); it is right for the served 4.0.1 and is
your evidence table — it changes when 4.0.2 ships.

## Found, not done

1. The spec's two open questions: a live Screen Options toggle keeps the class as
   decided at load (today's layout until the next load); a foreign drag (FileBird's,
   from the checkbox) over our tree lights our folder and drops nothing — an `accept`
   on our droppables would keep it honest. Both visible behaviour, your call.
2. `node tools/verify.mjs surface escaping` fail on `94146ad` already:
   `docs/security-surface.md` and `docs/security-escaping.md` have drifted from the
   code, and the sink ratio reads 244 / 65. Not this story's; a doc regeneration.
3. The runner still carries FileBird's version as a label; the shared prep module
   — `deferred-work.md`.
4. Your untracked `docs/superpowers/mocks/shots/2026-09-21-site-scenes-*.png` are
   left out of the commits.

## Next — S29

Wave 4 (the €0.50 walk on 4.0.1 + Pro 1.0.3) on your go, or the train cuts 4.0.2
first with this fix so the walk runs on the bytes that ship — S27's reasoning; I
would cut 4.0.2 first. Before either: say whether the two commits here go out.

```
Read docs/handoffs/2026-09-21-s28-a-single-row-beside-filebird.md, then
plans/finish-the-suite.md ("Waves" and "The release train"). State which model
you are and follow that profile in ~/.claude/harness/model-profiles.md. This
session: the release train for 4.0.2 from this tree — my changelog copy first,
then readme, version, Plugin Check on git archive, archive-hygiene, the zip,
tag, GitHub release, shelf, PLUGIN_RELEASES, redeploy, health, release-check.
Stop points: nothing on ms2 or the real shop; every user-facing string is
mine; say what goes out before any push or env change. End with a handoff in
docs/handoffs/.
```
