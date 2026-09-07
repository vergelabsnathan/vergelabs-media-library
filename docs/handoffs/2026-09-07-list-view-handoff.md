# Session handover — 2026-09-07, the list view diagnosed and drawn (Opus 5)

Two pieces of work. Phase 8 task 23 was built, walked and pushed — its own
handoff is `docs/handoffs/2026-09-06-phase-8-task-23-handoff.md`. Then Nathan
sent a 28,800px screenshot of the media library in list mode and said the
surface is not right and that opening the left pane clips the list.

**No plugin code was written for the list.** What exists is the diagnosis, the
spec and the mock. The stop point is Nathan's approval of the mock.

Read in this order:

1. `docs/superpowers/specs/2026-09-06-list-view-diagnosis.md` — why it fails,
   measured. **Read the correction box at the top**: the first version of this
   document blamed the column count and five later measurements disprove it.
2. `docs/superpowers/specs/2026-09-07-list-view.md` — the contract.
3. `docs/superpowers/mocks/2026-09-07-list-view.html` — five boards. Shots in
   `docs/superpowers/mocks/shots/list-view-*.png`.
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.
   This is written for the Opus profile: four tasks, each with its files,
   behaviour, proof, mirror and copy.

Plugin on `main`, three commits from this half of the session: the diagnosis,
the spec and first mock, then the redraw. All pushed. The nightly watch commits
to `main` around 05:17 UTC: `git pull --rebase` before pushing.

## The finding, in one paragraph

A row is as tall as the tallest thing in it that is still in the flow, and that
is core's own `.row-actions` — hidden with `position: relative; left: -9999em`,
which keeps it in the flow. In a 42px column it wraps to 1,153px and sets the
height of every row. But the deeper cause is that the table is starved of width
before any of that: at 1600px, WordPress's menu takes 160, FileBird Pro's own
pane 319 and our gutter 316, leaving 763px for thirteen columns; at 1280 it
leaves 457px. With **all four of our columns gone and FileBird's pane gone** a
row is still 348px. No stylesheet fixes that, which is why this is a design
change.

## What Nathan decided

- **2026-09-07: "keep it close to the original WordPress setup with maybe more
  aesthetics."** The drawer the first draft proposed is gone. The folder filter
  is a dropdown in core's own filter bar — the pattern the Posts screen has
  used for categories for years — and `Move to folder…` joins bulk actions.
- Still **not** decided: whether losing drag-a-row-onto-a-folder in list mode
  is acceptable. It is the only capability the change removes; grid mode keeps
  both the tree and the drag. The alternative, if he wants the drag kept, is
  the tree staying and the table scrolling sideways. Not drawn.

## The four tasks, once the mock is approved

Each is a task in the Opus shape; the spec carries the detail.

1. `core/media-list.php` — our four columns come off the default set, and the
   quiet line goes under the filename in core's File cell.
2. `core/media-list.php` — the `All folders` dropdown on
   `restrict_manage_posts`, hierarchical with counts and `Unfiled` second.
3. `core/media-list.php` — `Move to folder…` in bulk actions, and its handler.
4. `js/vergeml-tree.js` + the two stylesheets — no tree in list mode, the rail
   in grid mode carries one labelled control, the fold saves what is on screen,
   `.row-actions` may not wrap, our cells may never wrap.

## Gates

- `tests/ui/modes.spec.mjs`, extended — **no row on the media list is over
  100px**, grid and list, with our columns off and with all four on. Mutation
  check: let `.row-actions` wrap again and it goes red. This is the assertion
  the suite has never had; it walks this screen today and passed throughout
  while rows were 1,960px.
- `tests/ui/shell.spec.mjs`, `tests/ui/shots.spec.mjs`
- `node tools/verify.mjs copy journey guide`
- Nothing here spends credits. The media list reaches no model.

## The probes, kept

`tools/look-list.mjs` is the one to run first — it measures where the width
goes, what the fold does, and how tall a row is, and writes shots into
`test-results/`. The others are the reductive ones: `look-list-floor.mjs`
(sweeps a width floor), `look-list-cell.mjs` (takes each column away),
`look-list-each.mjs` (gives each column the table to itself),
`look-list-tall.mjs` (names what in a row is over 100px and is still in the
flow), `look-list-rules.mjs` and `look-list-fix.mjs` (candidate rules against
each other). All read-only. `tools/shoot-mock.mjs` renders a mock's boards to
PNG so they can be sent into the conversation rather than linked.

## Traps found

- **`[\d,.]+` is not a number.** It matches the comma between clauses and the
  full stop at the end. `\d[\d,.]*` is what `numbersOn()` in
  `tests/ui/fixtures.mjs` already uses. Cost a red run and a hand-undo.
- **The box's `admin` password is not `VgmlTest7pass` any more.** Make a
  throwaway administrator the way CI does — `ACTION=create UI_USER=… UI_PASS=…
  bash tools/box-ui-user.sh` over ssh — and delete it at the end.
- **ssh to the box needs `-i ~/.ssh/hetzner_vgml`.** `~/.ssh/config` sends
  `id_ed25519` to every host and the box refuses it.
- **A Python edit script must not mix a literal `·` with `\uXXXX` escapes.**
  Running the body through `unicode_escape` mangles the literal ones to `Â·`
  and leaves the escaped ones right, so half the file looks correct.
- **Heredocs eat `\\`.** A script with a regex in it goes through the Write
  tool, not `cat <<'EOF'`.
- **`plans/folders-one-tree.md` is CRLF.** A Python replacement built with
  `\n` will not match it.
- **A mock's boards clip.** `.mk-wp` has `overflow: hidden`, so a dropdown
  drawn open is cut off; and a grid item does not shrink below its content
  unless told `min-width: 0`. Both cost a re-render here.

## Found, not done

- **`.row-actions` wrapping is core's, on every list table.** We fix it only on
  the media list. Whether it is worth telling WordPress about is Nathan's.
- **883px of notices sit above the table**, eight of them, one ours. The table
  starts 1,291px down the page. Not ours to fix, but it is what a customer with
  a normal plugin set sees.
- **`tests/ui/modes.spec.mjs` asserts what is on the screen and never a
  geometry.** The row-height gate above closes it for this screen; the same
  blind spot applies to every other screen that draws a table.
- Everything on the Phase 7 handoff's "Still Nathan's" list stands, minus the
  list-columns question, which this spec answers.

## Opener, to paste once the mock is approved

```
Read docs/handoffs/2026-09-07-list-view-handoff.md, then
docs/superpowers/specs/2026-09-06-list-view-diagnosis.md and
docs/superpowers/specs/2026-09-07-list-view.md with its mock at
docs/superpowers/mocks/2026-09-07-list-view.html.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.

This session builds the list view to the approved mock. Four tasks, a check-in
and the gates after each:

1. core/media-list.php -- our four columns off the default set; the quiet line
   under the filename in core's File cell.
2. core/media-list.php -- the All folders dropdown on restrict_manage_posts,
   hierarchical, counts, Unfiled second.
3. core/media-list.php -- Move to folder... in bulk actions, and its handler.
4. js/vergeml-tree.js, css/vergeml-media-list.css, css/vergeml-tree.css -- no
   tree in list mode; the rail in grid mode with one labelled control; the fold
   saves what is on screen; .row-actions may not wrap; our cells may never wrap.

Decisions taken, do not re-ask: the WordPress filter bar, not a drawer; drag to
a folder goes from list mode and Move to folder replaces it; grid mode is
untouched. Copy comes from the spec's table, verbatim.

Gates after every task: tests/ui/modes.spec.mjs (extended with the row-height
assertion and its mutation check), tests/ui/shell.spec.mjs,
tests/ui/shots.spec.mjs, node tools/verify.mjs copy journey guide.
Nothing here spends credits. Make a throwaway admin with tools/box-ui-user.sh
and delete it at the end.
End with a handoff in docs/handoffs/.
```
