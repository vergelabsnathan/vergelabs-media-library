# Session handover — 2026-09-05, Phase 2 done (Fable 5.1)

For the next session. Read in this order, then open Phase 3 of the plan:

1. `plans/folders-one-tree.md` — Phases 0, 1 and 2 are done; Phase 3 (the
   Folders screen: `js/vergeml-folders.js`, the Rules tab, `core/guide.php`
   relay and persistence) is next and wants Fable 5.1.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §1, §2, §4,
   §5, §8 and §11, and the mock `docs/superpowers/mocks/2026-09-05-folders-screen.html`.
3. This handoff's "What Phase 3 gets" — the tree component's contract, so
   the screen draws the tree rather than rebuilding it.
4. `docs/ai-service.md`, the last section — the stream contract from Phase 1;
   `docs/handoffs/2026-09-05-phase-1-handoff.md` for its "For Phase 3" list.
5. `~/.claude/harness/model-profiles.md` — state the model, follow the
   profile.

Both repos on `main`. Plugin: `952d2bf` + this handoff's commit. Service:
`ef6f592`, untouched this phase. The nightly watch commits to `main` around
05:17 UTC: `git pull --rebase` before pushing.

## Still Nathan's

Migration 018 (`library_counts`) on the production database, from the Phase
0 handoff. Nothing in Phases 1–3 depends on it.

## What landed (plugin `952d2bf`)

**Task 9 — `js/vergeml-tree-view.js`, one tree component.** The model
(index by id and parent, siblings by remembered order then name,
descendant-inclusive totals), the flatten walk, the row markup (`li.vgml-node
> .vgml-row > twist, icon, name, count`) and the two glyphs now live here.
`js/vergeml-tree.js` — the media library panel — builds its pseudo rows,
dragging, menus, uploads and windowing around them; its DOM and behaviour
are unchanged, and the media modal draws the same rows. The component
carries the draft overlay for the Folders screen, keyed by term id, with the
two states, the find box, the fold rule, hover, in-place rename, drag to
reparent and Delete to remove. `css/vergeml-tree-view.css` (and its RTL
twin) is the approved mock's grammar for the Folders surface; the panel
keeps `vergeml-tree.css`.

**Task 10 — the folders version stamp.** `core/folders-version.php`: option
`vergeml_folders_version`, an integer, not autoloaded. Bumped on
`created_term`, `edited_term`, `delete_term` for the hierarchical media
taxonomies; once more at the end of every `/vergeml/v1/folder` write (a
re-order or colour touches only term meta and fires no hook), and the write's
answer carries `version`; when the guide's re-filing finishes
(`vergeml_talk_refile_finish`) and is undone (`vergeml_talk_undo`); when a
Librarian batch is done and undone. `GET /vergeml/v1/folders/version` →
`{ version }`, `Cache-Control: no-store`, permission `vergeml_can_read_tree`.
The panel polls it every 5 s while the tab is visible and once more on
`visibilitychange`, re-reads the tree on a change (deferred while a name is
being typed or a drag is in progress), and records the version its own
writes return so they cost no reload.

## Evidence

- `node tests/tree/tree-view.mjs` → `43/43 passed` (from disk, no site).
  Mutation checks: rebase without the "deleted live folder becomes a new
  folder" line → `42/43`, red at D2; the fold rule off → red at "inside
  Women the renamed folder shows and the five unchanged siblings fold".
- `node tools/verify.mjs folders-version` on the box → `24/24 passed`. With
  the `edited_term` hook removed and deployed → `22/24`, red at A2 (rename)
  and A3 (re-parent); restored → `24/24`. Gate 5 is D1–D3: the route's
  handler costs exactly 1 query cold, 0 warm, and the option's autoload is
  `off`.
- `pnpm test:ui` on the box, `library.spec.mjs` + `shots.spec.mjs`: 17 of
  17 (the one first failure was a selector in the new spec picking the
  Filters head; fixed and re-run, `1 passed`). The second-writer walk: a
  folder made through the REST route outside the panel's own code path
  appeared in the panel within a poll, no reload, then was deleted again.
  The nine shell screenshots rendered with no JavaScript errors.
- Screenshots: `tests/tree/shots/tree-view-changes.png` and
  `tests/tree/shots/tree-view-all.png` (the harness, both states, with the
  hover card); `test-results/shot-library-list.png` (the panel on
  `upload.php`, unchanged). The `tests/tree/shots` folder is ignored by git;
  the suite regenerates them.
- Deploy: `node tools/deploy.mjs --box` → `152 files re-hashed on
  46.225.66.194`, `php -l: every file parses`.

## What Phase 3 gets

`window.vergemlTreeView` (enqueued as `vergeml-tree-view`; the Folders
screen enqueues `css/vergeml-tree-view.css` itself):

- `create({ surface: 'folders', root, nodes, indent: { step: 22, base: 0 },
  editable: true, l10n, accent, onEdit, onToggle, onHover })` → view.
  `setTree(nodes)` (the `/tree` payload's `nodes`; rebases a draft),
  `setDraft(draft)`, `getDraft()`, `setMode('changes'|'all')`,
  `setFilter(text)`, `summary()` → `{ now, after, changes }` for the kicker
  "Folders · 19 now, 15 after Move", `switchEl` and `findEl` if the screen
  wants to place them.
- A draft is `{ folders: [ { key, term_id, name, parent, count?, by?, from?,
  samples? } ], gone: { term_id: key } }`. `key` is `'t' + term_id` for a
  folder that exists, any unique string for one that does not; `parent` is a
  key or `''`; `count` is the folder's own pictures after Move (absent =
  unchanged); `gone` says where a dropped folder's pictures go, for the line
  "removed · 39 pictures go to Landscape and nature"; `from` (`[{ key |
  term_id, count }]`) and `samples` (up to three thumbnail URLs) feed the
  hover card; `by: 'you'` adds ", by you" to moved and renamed lines.
- `fromLive(nodes)` → every live folder as kept. `applyEdit(draft, edit)` →
  a new draft; `edit` is what `onEdit` emits: `{ type: 'rename', key, from,
  to }`, `{ type: 'reparent', key, parent }`, `{ type: 'remove', key, to? }`,
  `{ type: 'add', name, parent }`. The component never mutates the draft
  itself: the screen applies the edit, writes the line into the
  conversation, calls `setDraft`.
- `watchVersion({ onChange, every?, version?, fetch? })` → `{ known(v),
  current(), tick(), stop() }`.
- l10n keys with English defaults are at the top of the file
  (`DEFAULT_L10N`); the screen passes translated strings.
- The count rule, read off the approved mock: an open row or a leaf shows
  the folder's own pictures, a collapsed branch shows the branch total
  with "N folders" beside it; "was N" compares the same kind. A change of
  count is a change of the folder's own pictures, so a parent whose child
  moves in is not itself marked.

## Decisions taken here, for Nathan to overrule

- The stamp is not autoloaded: an autoloaded option that has never been
  written costs a query on every request of every site (docs/testing.md),
  and the poll is exactly one query either way. The `/tree` budget of 7 is
  untouched because `/tree` does not carry the version; only `/folder`
  writes do.
- Bumps follow the spec's list. Filing pictures by drag (`/assign`) and
  uploads into a folder change counts and do not bump; another tab's
  counts wait for the next bump, as they waited for a reload before.
- The poll pauses while the tab is hidden and fires the moment it is
  visible again; a hidden tab is not a surface anyone is looking at.
- The fold rule: inside an open branch that holds a change, unchanged
  *leaves* fold into "N more folders, unchanged"; an unchanged branch stays,
  collapsed, because it is the way into the rest of the tree. Never at the
  top level, never while finding, and not once the fold has been opened.
  The mock's Women showed two leaves before its "8 more"; there was no rule
  behind which two.
- The switch reads "Changes N" and "All M" with M the folders now, as the
  mock has it. Changes is the default while a draft has at least one
  change; with a draft and no change, All.
- A removed folder with no destination in `gone` reads "removed · N
  pictures go to no folder".
- Rebase adopts a folder made in the library since the last look as kept,
  so a Move never deletes a folder the draft never saw.
- Interactions on the Folders surface: double-click the name (or F2) to
  rename in place, drag the handle to reparent (drop on a row, or on the
  list background for the top level), Delete or Backspace on a focused row
  to remove. There is no visible remove control on a row; the mock shows
  none, so that affordance is Phase 3's to draw and Nathan's to approve.
- `tests/ui/fixtures.mjs` answers Jetpack's "prove your humanity" sum. The
  box runs Jetpack without an API key, and its brute-force fallback
  answered 401 to every login, right password or wrong.

## Found, not done

- **The model speaks names; the draft is keyed by id.** The service's tree
  block has no `id`. Phase 3 must resolve a reply to term ids before
  `setDraft` — either send `id` with each existing folder in `tree` /
  `current` and ask the model to keep it (a service prompt and schema
  change, one line each), or match by exact parent path once at adoption
  (`fromLive` + `applyEdit` exist; a `resolve()` helper does not). The
  first is the honest one: a folder the person renamed by hand keeps its
  identity through the next turn.
- The Sort screen (`js/vergeml-sort.js`) still draws its own name-matched
  draft until Phase 3 replaces it; the shipped build therefore has one tree
  in the panel and the modal, and the old one on the Sort screen.
- "The folders in the tree fill as pictures land" during a Move wants a
  bump per chunk, not only at the finish; `vergeml_talk_refile_run` is the
  place, and `vergeml_folders_moved( 'chunk' )` would do it.
- `vergeml_talk_undo` and the Librarian's undo are asserted by their call
  sites (B2–B4 read the source) plus B5 on the function they call; a live
  undo needs an undo record, which a suite must not fabricate on the box.
- FileBird Pro was active on the box's media screen today (the panel's own
  notice says so); the fixtures memory has it inactive.
- Playwright empties `test-results/` at the start of every run. Nothing
  that must survive a run belongs there.

## Phase 3 opener, to paste

```
Read docs/handoffs/2026-09-05-phase-2-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 3, tasks 11 to 13 (plugin: js/vergeml-folders.js replaces vergeml-sort.js — the switch, the composer, streamed turns through the token, hand edits as messages, Move in its three states, undo; the Rules tab; core/guide.php relay, turn persistence and apply; the Sort screen and its nav entry go). The tree is js/vergeml-tree-view.js — draw it, do not rebuild it; see "What Phase 3 gets" in the handoff. Resolve the model's tree to term ids before setDraft.
Stop points: any visible shape the mock does not show (a remove control on a row, the Rules previews' layout) is shown as a mock first; anything that moves files runs only with guide_walk=1 on the box and restores the session it finds.
Gates: tests/ui/folders.spec.mjs on the box with its screenshots (resting, moving, done) and the Stop-while-streaming assertion; tests/tree/tree-view.mjs and tests/tree/folders-version.php still green; Gate 5 (the Folders screen's first paint within the guide screen's budget as measured today, the number written into the suite); the shell screenshots. A planner call is ten describes' worth; say so in the log before it runs.
End with a handoff in docs/handoffs/.
```
