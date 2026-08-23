# T1 — the folder tree in the library screens

Ships in 3.1 with T0 and T2. Grid view and list view only.

## Problem

The plugin already stores everything a folder tree needs: hierarchical taxonomies on
attachments, with counts. What it exposes is a dropdown filter — `AttachmentFilters.Taxonomy`
in `js/vergeml-media-views.js:254`. A dropdown cannot show hierarchy, cannot show where a file
sits, and cannot be dragged onto. So users who want folders install FileBird (custom tables,
765KB of React) or Premio Folders (a real taxonomy, but it replaces `AttachmentsBrowser`
outright and breaks whenever core moves).

T0 built the substrate: `vergeml/v1/tree` and `vergeml/v1/assign`, 4 queries flat from 200 to
2,000 folders, and media taxonomies are now visible to REST. T1 is the visible half.

## User story

As someone with a few thousand images, I want a folder tree beside my media library that I can
click to filter and drag files onto, so that I can organise a library that has outgrown a
dropdown — without giving up the fact that my folders are ordinary WordPress terms.

## Decisions taken

From the interview. Each one is a constraint, not a preference:

1. **Plain drag ADDS the term. Ctrl/Alt-drag MOVES** (removes other terms in that taxonomy,
   adds the target). A file can therefore appear in several folders. The UI must make that
   legible — "in 2 folders" — rather than leaving it as a surprise.
2. **A "one folder per file" setting makes plain drag behave as move.** Default off. This is
   the single toggle that makes FileBird converts feel at home.
3. **The tree replaces the dropdown filter for hierarchical taxonomies.** Non-hierarchical
   taxonomies (tags) keep their dropdown — they cannot be a tree.
4. **One tree at a time, with a taxonomy switcher at the top.** Remembered per user.
5. **Clicking a folder always includes files in its descendants.**
6. **The existing `include_children` lib option keeps governing the dropdown filters** and is
   left alone. The tree is defined as always-inclusive. No existing behaviour changes for
   anyone; do not repurpose or retire that setting.
7. **Deleting a folder deletes that term only; its children move up to its parent.** Files are
   never touched, and the confirm dialog says so in those words.
8. **Folders re-parent by drag in T1.**
9. **`manage_categories` gates create / rename / colour / delete.** Filing a file into a folder
   is governed by `edit_post` on that attachment, which `/assign` already checks per file.
10. **Dragging an unselected file files only that file, and clears the selection.**
11. **Fixed palette of ~8 colours**, chosen to hold contrast in light and dark admin schemes.
    No free colour picker.
12. **Tree search filters the tree only**, auto-expanding to matches. It never touches the
    library query; the library changes when a result is clicked.
13. **Per-user state in user meta, per site**: open branches, selected folder, panel width,
    active taxonomy.
14. **Undo toast lasts 10 seconds and dies on navigation.** It calls `/assign` with the inverse.
15. **Send the whole tree; virtualise rendering past ~500 nodes.** The endpoint is 4 queries at
    any size, so payload is not the constraint — painting DOM nodes is. Search and counts must
    keep working across the entire tree, which rules out lazy-loading children.
16. **Keyboard operation and RTL are in T1, not deferred.** ARIA tree roles, arrow-key
    navigation, Enter to filter, a context-menu key for actions.
17. **Counts are dual and computed in one pass.** Every node carries `count` (files directly in
    that folder) and `total` (including descendants). The tree displays `total`, because
    decision 5 says clicking always includes descendants, and a node that reads 0 while showing
    files when clicked is a bug report. One `GROUP BY` for direct counts, then a single
    post-order roll-up in PHP. No extra queries — the 4-query budget holds.
18. **Skins are a free option, not a paid one, and are pure CSS.** Four of them, plus an
    independent density control. Implemented as `data-skin` / `data-density` on the tree root
    driving CSS custom properties — one stylesheet, no per-skin file, no JS colour injection.
    Stored per user alongside the rest of the tree state.
    - **Native** (default) — derives its accent from the user's own WordPress admin colour
      scheme via `get_user_option( 'admin_color' )`. The tree looks like part of the admin
      rather than bolted onto it. FileBird has no `admin_color` awareness anywhere.
    - **Classic** — filled folder icons, tighter rows, file-manager feel.
    - **Minimal** — no folder icons, colour dots and labels only, generous spacing.
    - **High contrast** — heavier weights, larger hit targets, strong focus rings. An
      accessibility choice, not a decoration.
    - **Density**: comfortable / compact, orthogonal to skin.


## Out of scope

- **The media modal.** That is T2 and its seven contexts. Do not touch modal code.
- Importers from FileBird / Premio / RML (T3).
- Tree *ordering* drag (`vergeml_order`) and colour chips in filters (T4).
- Bulk UI in Pro.
- Any change to the Pro plugin.

## Context

**Read first:**
- `CLAUDE.md` — the constraints, especially PHP 7.4, no build step, and safe-mode load order
- `js/vergeml-media-views.js:1-35` — the doctrine comment. Three patching styles, only two safe.
  `_.extend(prototype)` replaces core and is what broke the toolbar in WordPress 7.0.
- `js/vergeml-media-views.js:254-325` — `AttachmentFilters.Taxonomy`. **This is the filter
  pipeline.** A filter sets `props[taxonomy] = term_id` on the library. A tree click is the
  same one line. Reuse it; do not build a second query path.
- `core/rest-tree.php` — the endpoints T1 consumes, and where new ones go
- `vergelabs-media-library.php:635-780` — enqueue and `wp_localize_script` patterns
- `vergelabs-media-library.php` safe-mode guard — where `core/rest-tree.php` is loaded

**Changes:**
- `core/rest-tree.php` — add folder CRUD (create / rename / colour / delete / reparent) and
  per-user tree state read+write
- `vergelabs-media-library.php` — load the new UI file inside the safe-mode guard
- `core/taxonomies.php` — the "one folder per file" setting

**Created:**
- `core/tree-ui.php` — enqueue, localize, user-meta state, capability checks
- `js/vergeml-tree.js` — the tree; plain JS, no framework, no build step
- `css/vergeml-tree.css` and `css/vergeml-tree-rtl.css` — RTL is a separate file here, matching
  the existing `eml-admin.css` / `eml-admin-rtl.css` pairing
- `tests/tree/t1-ui.js` — the UI test

**Prior art to follow rather than reinvent:** `js/eml-media-grid.js` for how the grid is hooked,
`js/eml-enhanced-medialist.js` for the list view, and the existing RTL stylesheet pairs.

## Tasks

Ordered. Each verifiable on its own.

1. **Fix the T0 undo inverse.** `/assign` currently returns an inverse that removes the term
   from files that already had it, so undo destroys prior state. The inverse must name only the
   files actually changed. T1's undo toast depends on this being right — do this first.
2. **Install Playwright in the repo** (`npm i -D playwright`, add `package.json`). It is not
   currently installed anywhere, which is why the T0 test had to be run by hand. No build step
   is added — this is a devDependency only and ships in nothing.
3. **Extend `core/rest-tree.php`:** `POST vergeml/v1/folder` (create/rename/colour/delete/
   reparent, all `manage_categories`), and `GET/POST vergeml/v1/tree-state` for per-user state.
   Deleting re-parents children to the deleted term's parent. Reject a reparent that would make
   a folder its own descendant.
4. **`core/tree-ui.php`:** enqueue on `upload.php` only (grid and list), localize the palette,
   capabilities, taxonomy list and the "one folder per file" flag. Load it inside the safe-mode
   guard so a crash here can be switched off.
5. **Render the tree.** Sidebar panel, resizable, per-taxonomy switcher, "All" and
   "Uncategorised" pseudo-nodes, counts, colour dots, collapsible branches. ARIA `tree` /
   `treeitem` / `group` roles from the start — retrofitting these is far more expensive.
6. **Click filters the library** via `props.set(taxonomy, term_id)`. Always includes
   descendants. "Uncategorised" uses the existing `uncategorized` prop.
7. **Keyboard:** up/down through visible nodes, left/right collapse/expand, Enter filters,
   context-menu key opens actions, and a visible focus ring. Test with the mouse unplugged.
8. **Drag files onto folders.** Add by default, Ctrl/Alt to move, "one folder per file" turns
   plain drag into move. Multi-select drags the selection; dragging an unselected file drags
   only it and clears the selection. Count badge on the drag ghost.
9. **Drag folders onto folders** to re-parent, with the self-descendant guard from task 3.
10. **Undo toast**, 10s, dies on navigation, calls `/assign` with the inverse from task 1.
11. **Folder actions** — new, rename, colour, delete — from a kebab and the context-menu key.
    The delete confirm names the child count and states that files are not deleted.
12. **Tree search**, filtering the tree only and auto-expanding to matches.
13. **Per-user state** persisted to user meta, restored on load.
14. **Virtualise past ~500 nodes** while keeping search and counts whole-tree.
15. **RTL stylesheet** and a pass in an RTL locale.
16. **The "one folder per file" setting** in the taxonomy options UI, default off.
17. **Dual counts** in `vergeml/v1/tree`: one aggregate query for direct counts, one post-order
    pass to roll descendants up. Assert the 4-query budget still holds afterwards.
18. **Skins and density.** CSS custom properties on the tree root; the Native skin reads the
    user's admin colour scheme. Ship all four free.


## Architecture borrowed from FileBird

Read from their source, reimplemented, not copied:

- **Dual counts with a PHP roll-up** (`includes/Model/Folder.php`). One aggregate query plus an
  in-memory roll-up over the nested map, returning `display` and `actual`. This is the right
  shape and we take it. **Their version calls the roll-up once per folder in a loop, so deep
  trees recompute the same subtrees repeatedly.** Ours does one post-order pass — strictly
  fewer operations, and the harness can prove it.
- **Model / Controller / Rest separation.** Their `includes/` split is cleaner than one large
  class. `core/rest-tree.php` should grow the same seams as it takes on folder CRUD.
- **An isolated integration shim per third party** (`Support/WPML.php`). Whatever we integrate
  with later goes in its own file, not into the tree.
- **A generalisation seam for post types** (`Addons/PostType/`). We are attachments-only in T1,
  but the endpoints should not hard-code that assumption where avoiding it is free.

What we deliberately do not take:

- **10 MutationObservers and a 1.6MB JS bundle.** They observe the DOM because they fight core
  instead of hooking it. We hook.
- **Custom tables.** The lock-in is theirs; portability is our line.
- **Gating three hex values behind a licence.** Their entire Pro "folder tree themes" feature is
  a three-value constant, with a nag in free saying the switcher is preview-only.

## Validation strategy

Gates 1–5 of `.claude/skills/validate` all apply. Gate 7 (upgrade path) applies because task 16
adds a setting. Specifically:

- **Query-count budget is the regression gate.** `vergeml/v1/tree` stays at **4 queries**. The
  new folder endpoints get their own budget: create/rename/colour ≤ 6, delete ≤ 10 (it
  re-parents children). Dual counts must not add a query — measure before and after
  task 17 and show both numbers. Measure with `tests/perf/bench.mjs`. A rise means an N+1 — hard fail,
  even if it still feels fast.
- **New test `tests/tree/t1-ui.js`**, driven through Playwright against Playground: tree
  renders, click filters, drag adds, Ctrl-drag moves, undo restores exactly the prior state,
  delete re-parents children and leaves files alone, keyboard reaches and activates every node,
  self-descendant reparent is refused.
- **Scale run on the VPS at 2,000 folders** — Playground floors every request at ~2.4s and
  cannot tell you anything about rendering 2,000 nodes.
- **Screenshots, required before this is called done:** grid and list, light and dark admin
  scheme, LTR and RTL, **and each of the four skins**. Nathan asked for these explicitly. They catch the class of bug the tests
  do not — the clipping and the empty popup were both found by eye, not by assertion.
- **Nathan drives it himself** before merge.

## Risks

- **Replacing core views.** The one genuine architectural trap in this repo. Hook and wrap; never
  `_.extend` over a core prototype. Premio does exactly that and it is why it breaks on core
  updates.
- **Safe-mode load order.** If `core/tree-ui.php` loads outside the guard, a fatal in the tree
  cannot be switched off and the user is back to the FTP trick the watchdog exists to prevent.
- **PHP 7.4.** `php -l` on the test box runs 8.3 and will accept syntax that breaks users.
  Gate 2 exists for this.
- **The multi-folder count.** Sum-of-counts exceeds the file total once files live in several
  folders. That is correct, and it will read as a bug unless the copy says "in 2 folders". Get
  the wording right in T1 rather than patching it after the first support thread.
- **Undo across a page load.** The toast dies on navigation by decision. Ensure a pending undo
  cannot fire against stale ids after the library has refreshed.
- **Existing installs.** Anyone already using the dropdown filters gets a tree instead. Nothing
  in the database changes, but the screen does.

---

**Execute this in a NEW session:** `/execute plans/t1-folder-tree.md`. Continuing in the session
that wrote it would build from a context already full of exploration — which is the exact thing
the split exists to prevent.
