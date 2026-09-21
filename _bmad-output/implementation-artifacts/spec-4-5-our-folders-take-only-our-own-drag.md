---
title: "Our folders take only our own drag, and File's share follows a live column toggle"
type: 'defect'
created: '2026-09-21'
status: 'review'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: '06d75dd'
context:
  - '{project-root}/_bmad-output/implementation-artifacts/spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md'
  - '{project-root}/docs/handoffs/2026-09-21-s28-a-single-row-beside-filebird.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Two things 4.4 left open, Nathan's go on 2026-09-21 ("3 what's best?" → both, before 4.0.2). (1) Beside FileBird, its own drag — from the checkbox column, its handle — over our tree lights our folder (`is-drop`) and drops nothing: our droppables accept any jQuery UI drag. A FileBird user reads that as our folder refusing their file. (2) File's share is decided once at load from the columns PHP saw; a column shown or hidden through Screen Options without a reload leaves the class as it was — today's layout until the next load, and when no column beyond core's was on at load there is no share rule on the page at all.

**Approach:** (1) Every droppable of ours (`dropTarget()`, `unfileTarget()`) takes `accept`: the drag under way carries our helper (`.vgml-drag-helper` — the file drag's and the folder drag's both). A foreign drag gets no hover, no drop, and its own plugin's tree keeps it. (2) The share decision moves to the script: PHP emits both rules, `body.vgml-file-share-30` and `body.vgml-file-share-40`, unconditionally; `share()` reads the head cells that are not hidden — a column beyond core's and ours → 30, beyond core's but ours → 40, none → 0 — clears both classes, measures File against the share, and puts the one class on when File is below it. It runs in the frame after load and again after a `change` on core's `.hide-column-tog` checkboxes. The 9 % rule for our columns stays in PHP (it counts the visible columns; a live toggle of that is out of scope).

## Boundaries & Constraints

**Always:** shipped changes in `js/vergeml-tree.js` (the two `accept`s and one helper), `js/vergeml-media-list.js` (`share()`), `core/media-list.php` (the two rules, the `share` value gone) only. Proof: `tools/box-drag-beside.mjs --with filebird` with a new step of its own — a press on the checkbox label beside FileBird does not light our folder, and alone it does (our `<tr>` instance) — then `drag.mjs` 21/21 both ways; the FileBird matrix cell ✓ 10/10; modes.spec's row test with one live toggle: from "two of ours on" show one of the tech site's third-party columns through the Screen Options checkbox, no reload — `vgml-file-share-30` goes on and File holds 30 %. Mock on for the box cells; the box at HEAD at the end; ms clean.

**Never:** ms2 or the real shop; a describe; a version bump; a user-facing string; a change to FileBird or to the arming (`armOne()`); a change to the five-minute script's steps.

## I/O & Edge-Case Matrix

| Case | Today | After | Proof |
|---|---|---|---|
| FileBird's drag (from the checkbox) over our folder, beside FileBird | folder lights, drop files nothing | no hover, no drop; FileBird's tree takes it | harness step "a press on the checkbox label beside filebird" → our folder not lit |
| our drag from the checkbox label, no companion (our `<tr>` instance) | lights, files | the same | harness step alone → lit, filed |
| our file drag from the title cell; our folder drag | accepted | accepted (both helpers are `.vgml-drag-helper`) | drag.mjs 21/21; reparent by drag untouched (folder helper `is-folder`) |
| a jQuery UI drag from any other plugin's widget over our tree | lights | ignored | reasoning (the helper class) |
| load with core's three only | no rule, File auto | no class, File auto | modes.spec set 1: class off |
| load with five of ours | 40 % (class on) | `-40` on, File 40 % | modes.spec set 2 |
| load with theirs (unsized) | 30 % on | `-30` on, File 30 % | modes.spec set 3 |
| set 4 (two of ours) → show one third-party column live | class stays off; File shares with the new auto column, squeezed | `change` → re-measure → `-30` on, File 30 % | modes.spec: the live toggle |
| hide the last beyond-core column live | class stays as it was | share 0 → both classes off, File auto | reasoning; covered by the toggle's reverse in the same test |
| RTL | — | unchanged | — |

</frozen-after-approval>

## Code Map

- `js/vergeml-tree.js:2228-2262` `unfileTarget()`, `:2264-2339` `dropTarget()` — the two `droppable()` calls; `:2111-2117` the file helper (`vgml-drag-helper`), `:2429-2434` the folder helper (`vgml-drag-helper is-folder`). jQuery UI sets `$.ui.ddmanager.current` before `prepareOffsets()` evaluates `accept`, so the helper is readable there.
- `js/vergeml-media-list.js:96-142` `share()` and `draw()`; `cfg.columns` is our column list (`vergeml_list_our_columns()`); head cells are `thead th[id], thead td[id]`, hidden ones carry `.hidden`.
- `core/media-list.php:279-300` the share decision and the rule → two static rules; `:316` `'share'` in the localize → removed.
- Core: `wp-admin/js/common.js` `columns.init()` binds `.hide-column-tog` (`click`), toggling `.column-<id>` `hidden` — the `change` that follows sees the new state.
- `tests/ui/modes.spec.mjs:534-660` the row test — reads `share` from the class now; the live toggle after the sets: `#show-settings-link`, then `#<id>-hide`.
- `tools/box-drag-beside.mjs` — the checkbox-label step, expected per `companion`.

## Tasks & Acceptance

- [x] T1 `js/vergeml-tree.js`: `ownDrag()` — `$.ui.ddmanager.current.helper` has `vgml-drag-helper`; read in `over` and `drop` on both droppables (not `accept` — see the triage log).
- [x] T2 `core/media-list.php`: the two rules unconditionally; `share` out of the localize. `js/vergeml-media-list.js`: `share()` decides from the head, clears, measures, toggles; bound to `change` on `.hide-column-tog` (delegated on `document`).
- [x] T3 `tools/box-drag-beside.mjs`: after drag.mjs, the checkbox-label press (label x + 4, row middle — left of the input), expected lit-and-filed alone, not-lit-and-not-filed beside a companion; the file put back unfiled. `tests/ui/modes.spec.mjs`: `share` read from the class; the live toggle test after the sets.
- [x] T4 Deploy, run: the FileBird cell, the harness both ways, the row test; `deploy.mjs --check`; ms clean; `bmad-code-review`; sprint 4.5 → review; commit; handoff.

**Acceptance Criteria:**
- Given FileBird linked into `/var/www/ms`, when a press on a row's checkbox label is dragged onto our folder, then our folder never carries `is-drop` and the file's folders are unchanged; alone, the same press lights the folder and files the file.
- Given the FileBird cell, then ✓ 10/10; drag.mjs 21/21 with and without FileBird.
- Given the tech site with two of ours on and no reload, when one third-party column is shown through Screen Options, then `body.vgml-file-share-30` is on and File ≥ 30 % of the table; when it is hidden again, the class is off.
- Given no column beyond core's, then neither class is on and File is auto.

## Decisions taken

- **The helper, not `dragging`.** `dragging` is set by our file helper only; the folder helper sets `draggingFolder`. The helper class covers both in one test and says what it means: the drag is ours.
- **The share decided in the script.** A listener alone leaves the share PHP chose at load; when it chose 0, no rule is on the page and a toggle has nothing to switch on. Two static rules and a decision from the head cells make the toggle honest; PHP keeps the 9 % rule.
- **Clear before measuring.** With a class on, File measures at its share, never below; measuring with both classes off is the only reading that says whether the floor is needed.

## Implementation Notes

- **Final bytes on the box** (`deploy.mjs --check`: up to date). `node tools/box-drag-beside.mjs --with filebird`: drag.mjs **21/21**, then the label step **ok** — "pressed on label; over the folder the drag's helper was `fb-v-dragging ui-draggable-dragging`; lit false, filed false". Alone: **21/21**, "helper `vgml-drag-helper ui-draggable-dragging`; lit true, filed true". `--spec reparent`: **12/12** (folder drags still accepted). The option restored and the plugin listing clean after each. FileBird cell **✓ 10/10 · 36 s**. modes.spec "the rows on the media list" **2 passed (3.2 m)**: the four sets with the class expected (none / `-40` / – / none), the press on the title cell everywhere; the Screen Options tab clicked open in both modes; the live tick of the tech site's three third-party columns → **`-30` on, File 322 px of 1082**, checkbox 34; unticked → **no class, File 554 px** (the leftover's column).
- **Found on the way, fixed, outside the Files line:** since `30653e9` (2026-09-10) `body.vgml-has-tree .wrap { position: relative }` painted over core's floated Screen Options and Help tabs — `elementFromPoint` at the button's centre answered `div.wrap`, a Playwright click waited four minutes — so neither tab could be clicked on the list screen beside the tree, in the served 4.0.0 and 4.0.1. One rule in `css/vergeml-tree.css` lifts `#screen-meta-links` / `#screen-meta` (`position: relative; z-index: 2`), the way `eml-admin-media.css:209` does beside the grid. `vergeml-tree-rtl.css` is a corrections sheet, nothing directional here. A 4.0.2 changelog line is proposed in the handoff.
- **The floor's tolerance.** With two of ours and the tech site's three third-party columns, File measured 322 px = 29.76 % under the `-30` class: the sized percentages sum past 100 and the browser scales them (4.4's 325 px = 30.04 % had ours hidden). The per-set and live assertions allow two points; the number and the reason are in the test.
- **`accept` → `over`/`drop`.** The first build used `accept: ownDrag`; the review found jQuery UI resets `isover` only for accepted droppables, so a refused drag released over folder X left X "hovered" and our next drag entering X got no highlight once. The guard in `over` (hover class removed at once) and `drop` (returns `false`) keeps the state consistent; the harness shows the same dark folder either way.
- **The tick ticks all three third-party columns**, not one: one alone is the borderline (File auto ≈ 29 % beside one unsized column) and which of the three carry widths is not known; the class assertion needs the certain case. The `share = 0` branch is observable only as "no class", which the untick and set 1 both show; it is not separately provable live.
- **Sprint key** shortened to the spec's slug (`4-5-our-folders-take-only-our-own-drag`).

## Spec Change Log

- 2026-09-21 written from 4.4's open questions on Nathan's go.
- 2026-09-21 (build) `css/vergeml-tree.css` changed outside the Files line — the Screen Options tab under the wrap (Implementation Notes); the guard is in `over`/`drop`, not `accept`; the live test ticks every third-party column; the matrix's "share 0 → covered by the reverse" claim corrected (observable as "no class" only); the harness runs `reparent.mjs` too.

## Review Triage Log

2026-09-21, `bmad-code-review` over the working tree against `06d75dd` (582 lines), four lenses: blind hunter 10, edge-case hunter 8, verification gap 3, acceptance auditor 8.

| # | Finding | Verdict | Route |
|---|---|---|---|
| 1 | `css/vergeml-tree.css` outside the Files line, no record, no proof of its own, no changelog line (blind, acceptance) | medium | patch: change log + notes; modes.spec clicks the tab open in both modes regardless of columns; the line proposed in the handoff |
| 2 | checked rows dragged by FileBird's handle onto our folder no longer file (the `selectionIds()` fallback was reachable through a foreign drag) (blind) | low | reject: that drag is FileBird's and FileBird's tree takes it — the premise Nathan approved; recorded here and in the handoff |
| 3 | the `share = 0` live branch is not what the untick exercises (blind, acceptance) | low | reject (spec claim): corrected in the change log — the branch is observable only as "no class" |
| 4 | the live tick is skipped silently where no third-party column exists (blind) | low | patch: a printed line; the tab click runs regardless |
| 5 | the harness's negative case passes when no drag starts at all (blind, VG, acceptance) | medium | patch: the helper under way is read over the folder and must be the companion's (beside) or ours (alone); the press point printed |
| 6 | tooltips do not follow a live tick (blind, edge) | low | patch: `measure()` = share + titles, on load and on the tick |
| 7 | spec bookkeeping, stale `body.vgml-file-share` in a message, stale PHP comment (blind, acceptance ×3) | low | patch: message names the class; the two PHP comments merged; notes filled |
| 8 | the tolerance loosened without a record (blind, acceptance) | low | patch: recorded with the numbers (Implementation Notes); the reason stands — the auditor's "unsized, nothing to scale" is contradicted by the 322 px measurement |
| 9 | `accept` leaves a refused droppable's `isover` stale (blind) | medium | patch: guard in `over`/`drop`; `accept` dropped |
| 10 | sprint key and spec slug drifted (blind) | low | patch: the key is the slug |
| 11 | a helper that is not a jQuery object throws in `hasClass` (edge) | low | patch: `hasClass` checked |
| 12 | `readWidths` on an empty list throws before the rows assertion (edge) | low | patch: an empty reading |
| 13 | column ids with CSS-significant characters break `#id-hide` (edge) | low | patch: `[id="…"]` |
| 14 | the suite's fixed `OURS` list vs `vergemlList.columns` (edge) | low | reject: the suite's convention since S10.6; a fourth taxonomy is a suite edit either way |
| 15 | the file already in the scratch folder before the label press (edge) | low | patch: unfiled first |
| 16 | `assign` landing after the 1.5 s read (edge) | low | patch: polled up to 4 s, as `termsOf` does |
| 17 | 4.4's AC 4 "no title rule is emitted" now false (edge) | — | patch (a change-log line in 4.4's spec) |
| 18 | the 40 % share never pinned — set 2 asserted only "a class is on" (VG) | medium | patch: each set carries the class expected; set 2 → `-40` |
| 19 | folder drags gated too and no folder-drag suite in the gates (VG) | medium | patch: `--spec reparent` in the harness, run: 12/12; in the gates |
| 20 | the spec said "one third-party column", the test ticks all (acceptance) | — | reject (spec wording): the reason in Implementation Notes and the change log |

After the patches every command in Verification was rerun on the final bytes — the lines in Implementation Notes.

## Verification

- `node tools/box-drag-beside.mjs --with filebird` → 21/21 and the checkbox step "not lit, not filed"; `node tools/box-drag-beside.mjs` → 21/21 and "lit, filed".
- `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` → ✓ 10/10.
- modes.spec "the rows on the media list" → 2 passed, the live toggle lines printed.
- `node tools/box-drag-beside.mjs --spec reparent` → 12/12 (folder drags still ours).
- `node tools/deploy.mjs --check --box` → up to date; ms clean. Cost: nothing.

**Results (2026-09-21):** all green on the final bytes — Implementation Notes.
