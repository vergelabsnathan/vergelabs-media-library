# S28b — story 4.5: our folders take only our own drag; File's share follows a live tick

**Date:** 2026-09-21, midday. **Model:** Opus 5 (the S28 session, continued on
"go"). **From:** `docs/handoffs/2026-09-21-s28-a-single-row-beside-filebird.md`.
**Plan:** `plans/finish-the-suite.md` — "Story 4.5 — done". **Spec:**
`_bmad-output/implementation-artifacts/spec-4-5-our-folders-take-only-our-own-drag.md`.
Plugin `06d75dd` → the build commit → this handoff's commit. **Spent: nothing**
(no key on `/var/www/ms`, mock on for the cell). No version bump.

## What Nathan said

"deploy 2 agreed 3 whats best?" → the two commits pushed; the train cuts 4.0.2
first; my proposal for the two open questions — both, as one short story before
the train — and "go".

## What changed (four shipped files)

- `js/vergeml-tree.js`: `ownDrag()` reads `$.ui.ddmanager.current.helper`; both
  droppables (`dropTarget`, `unfileTarget`) take the hover class off in `over` and
  return `false` from `drop` for any drag that is not ours (file or folder — both
  carry `.vgml-drag-helper`). Beside FileBird, its drag from the checkbox column
  neither lights our folder nor drops; FileBird's tree takes it. Consequence on
  record: ticked rows dragged by FileBird's handle onto *our* folder no longer file
  through our fallback — that drag is FileBird's, the premise you approved.
- `core/media-list.php` + `js/vergeml-media-list.js`: PHP emits both share rules
  (`body.vgml-file-share-30` / `-40`) unconditionally; the script decides which
  from the head cells (a column beyond core's and ours → 30, ours only → 40, none →
  0), clears both classes, measures File and puts one on only when File is below
  its share — in the frame after load and again after a `change` on core's
  `.hide-column-tog`. Tooltips re-read in the same frame.
- `css/vergeml-tree.css` — **found on the way, outside the Files line, fixed:**
  since `30653e9` (09-10) the positioned `.wrap` painted over core's floated
  Screen Options and Help tabs; neither could be clicked on the list screen beside
  the tree, in the served 4.0.0 and 4.0.1 (`elementFromPoint` at the button said
  `div.wrap`; a Playwright click waited four minutes). One rule lifts
  `#screen-meta-links` / `#screen-meta`, the way `eml-admin-media.css` does beside
  the grid.

## The proof (final bytes, box up to date, ms clean after every run)

| | result |
|---|---|
| `node tools/box-drag-beside.mjs --with filebird` | drag.mjs **21/21**; the label step **ok** — "pressed on label; over the folder the drag's helper was `fb-v-dragging`; lit false, filed false" |
| `node tools/box-drag-beside.mjs` (alone) | **21/21**; "helper `vgml-drag-helper`; lit true, filed true" |
| `node tools/box-drag-beside.mjs --spec reparent` (new option) | **12/12** — folder drags still ours |
| `node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory` | **✓ 10/10 · 36 s** |
| modes.spec "the rows on the media list" | **2 passed (3.2 m)**: the class per set (none / `-40` / – / none), the press on the title cell everywhere, Screen Options clicked open in both modes, the live tick of three third-party columns → `-30` on, File 322 of 1082 px, checkbox 34; unticked → no class, File 554 |

## Review

Four lenses, 20 rows in the spec's triage log; 14 patched before the reruns —
the one that changed the shape: `accept` leaves a refused droppable's `isover`
stale in jQuery UI (our next drag entering that folder got no highlight once), so
the guard moved to `over`/`drop`. Also: the harness's negative case now requires a
foreign drag to be under way (the helper read over the folder), set 2 pins `-40`,
`reparent.mjs` is in the gates, tooltips follow the tick. 6 rejected with reasons.

## Strings that are yours

None shipped. Two proposed 4.0.2 changelog lines, with 4.4's:
1. "Dragging one file into a folder works again beside FileBird and other plugins
   that add a column to the media list; the checkbox column no longer takes the
   table's spare width." (4.4)
2. "Screen Options and Help open again on the media list beside the folder
   panel; a folder no longer lights up for another plugin's drag." (4.5)

## Found, not done

1. The FileBird cell and `drag.mjs` still run by hand (`deferred-work.md`, from
   4.4); `reparent.mjs` likewise — `tools/box-drag-beside.mjs --spec reparent` is
   the one-line way now.
2. The 9 % rule for our own columns is still PHP's and counts the columns at
   load; a live tick does not change it (out of scope, harmless: it is a ceiling).
3. `surface` / `escaping` doc drift (from S28), the runner's shared-prep module
   (from 4.4's review) — unchanged.

## Next — S29: the train cuts 4.0.2

Say whether the two commits here go out (the box already holds the bytes). Then
the S29 opener from the S28 handoff stands, with both changelog lines above as
the copy to approve first.
