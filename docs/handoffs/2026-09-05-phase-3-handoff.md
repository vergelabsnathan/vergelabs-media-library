# Session handover — 2026-09-05, Phase 3 done (Fable 5.1)

For the next session. Read in this order, then open Phase 4 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 to 3 are done; Phase 4 (the shell in
   normal flow, settings under chevrons, the removals, the copy pass) is next
   and can run on Opus 5.1 or Sonnet 5 under the Opus profile. It still needs
   its tasks written in the Opus shape (Files, Behaviour, Proof, Mirror, Copy,
   Do not) before a session starts on it; Phase 0 in the plan is the reference.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §3, §6, §7 and
   the copy standard §4, §5, §11; the mock's board 3 (settings collapsed).
3. This handoff's "What Phase 4 inherits" and "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.

Both repos on `main`. Plugin: this handoff's commit on top of the feature
commit. Service: `407e025`. The nightly watch commits to `main` around 05:17
UTC: `git pull --rebase` before pushing.

## Still Nathan's

- Migration 018 (`library_counts`) on the production database, from the
  Phase 0 handoff. Nothing in Phases 1–4 depends on it.
- **A remove control on a row** — the approved mock shows none, so none was
  built: a folder leaves the draft by Delete or Backspace on a focused row, or
  by saying so. A mouse user has no affordance. The proposal is
  `docs/superpowers/mocks/2026-09-05-folders-remove-control.html` (the word
  REMOVE at the row's end on hover or focus, nothing at rest). Approve or
  strike; it is an hour to draw.
- The credits the first test run spent by accident (below, "Evidence").

## What landed (plugin, one feature commit; service `407e025`)

**Task 11 — `js/vergeml-folders.js` replaces `vergeml-sort.js` (and the dead
`vergeml-guide.js`).** The segmented switch Conversation | Rules at the head of
the left column, changing only that column. The composer: grows with what is
typed, Enter sends, Shift + Enter breaks a line, the arrow inside it is Stop
while a reply streams; at the cap its own label reads "25 of 25 turns used.
Edit the tree by hand, or start over." with a Start over button in the bar.
Turns stream straight from the service (`fetch` + server-sent events, parsed
by hand) with a token from `POST /vergeml/v1/guide/token`; a 401 re-mints once.
"- " lines in a reply render as the brand-mark list; `**name**` as bold; never
through innerHTML. Chips sit under the last reply. A hand edit on the tree
(rename in place, drag to reparent, Delete) writes "You · edited the tree /
Moved X under Y" and, when the conversation can talk, streams the assistant's
reaction as `input.edit`. Move is one button in three states with Stop beside
it while moving and "Undo until tomorrow 14:20" after; the done line goes into
the conversation as three facts. The screen paints from the data that comes
with the page (`vgmlFolders`: session, tree nodes, version stamp, facts) and
polls the version stamp; the poll is held while a Move runs.

**Task 12 — the Rules tab.** By kind, By month and year, By subject, Into
today's folders; one open at a time with its options as the mock has them
(scope radios naming the numbers, date source and levels, the smallest-folder
stepper, the two fit options), a folder count beside each rule, the preview
list under the rules. Choosing a rule or changing an option replaces the draft
and writes "You · applied a rule / By kind: 4 folders, 265 pictures" — over the
same rule's previous line, not beside it. No model, no credits; asserted by the
spec (no request leaves for the service). A rule's Move carries an explicit
assignment recomputed from the rule and its options at apply time, so a hand
edit after a rule (a rename) keeps the assignment by key. The Sort into
folders screen, its menu entry and its assets are gone; the slug
`media-librarian` stays so every link still lands; the nav reads "Folders ·
Build the folder tree".

**Task 13 — `core/guide.php`.** Session version 2: turns (`role`, `kind` in
said | choice | edit | rule | say | moved, `text`, `choices`), the draft keyed
by term id (`clean_draft` makes it safe: no slash in a name, an unknown parent
key becomes the top level, a rule's options fall to a closed list), the cached
token, the Move in progress. Routes: `GET/POST guide/session` (boot payload;
draft patch; `reset`), `POST guide/token` (mints at the browser-facing service
URL — `VERGEML_AI_STREAM`, else the service URL unless it is loopback, then
`https://ai.vergelabs.nl/v1` — cached for its hour and re-minted when the
summary's fingerprint changes), `POST guide/turn` (one turn or `turns[]`, in
order), `GET guide/rules`, `POST guide/rule`, `POST guide/apply`, `GET
guide/progress` (writes the done line and clears the draft when the run
ends), `POST guide/stop`, `POST guide/undo`. The old `propose`, `summary` and
PHP-side `turn` routes are gone. `core/folder-talk.php`: `vergeml_talk_apply(
$folders, $tags, $opts )` addresses an existing folder by `term_id` (renamed or
re-parented in place, never deleted and remade), removes exactly the live
folders the tree did not claim, takes `assign` (a rule's picture → folder) and
`fallback` (a removed folder's pictures go to the folder that absorbed it when
the evidence abstains), records the folders it made and an `until`; the
re-filing bumps the version stamp per pass so open trees fill; Stop keeps what
moved and does not delete the folders that were to go; undo refuses after its
day, renames back by id, and takes away the folders the Move made once they
are empty.

**Service `407e025`.** `GuideFolder.id` (optional, positive); the tree-shape
prompt asks the model to keep a folder's id whatever it renames or moves it
to and to give a new folder none; "Folders that exist now" carries `id N`; the
stream route keeps `id` on `current`. The plugin resolves a reply by id first,
then by exact path, then as new; a live folder the reply drops is `gone` to
its nearest kept ancestor or to no folder.

## Evidence

- `node tests/tree/tree-view.mjs` → `43/43 passed` after the component gained
  `setProgress()`, `summary().moving` and the surface-scoped stylesheet.
- `node tools/verify.mjs folders-version` → `24/24 passed`;
  `node tools/verify.mjs guide` → `30/30 passed`. Mutation check: with the cap
  check removed from `vergeml_guide_turn_apply()` and shipped to the box →
  `28/30`, red at E2 and at E4 (the turn that should have been refused is
  counted); the real build back → `30/30`.
- `tests/ui/folders.spec.mjs` on the box: `4 passed, 1 skipped` (3.6 m), and
  with `GUIDE_WALK=1` the walk `1 passed` (4.2 m): the opener streamed, the
  arrow was Stop and stopped it with the words kept, "1 of 25 turns"; By kind
  applied and moved (265 pictures into four folders) with slow passes on, the
  button counted "Moving 12 of 265" and the folders filled ("9 of 240"), then
  "Undo until tomorrow 03:56 PM", the done line "265 pictures moved into 4
  folders / 376 stayed where they were / Undo until…", then Undo: 265 put back,
  the four folders gone again. Mutation check: with `persistDraft` made a
  no-op → the hand-edit test red at "the renamed folder keeps its term id in
  the persisted draft".
- Gate 5, written into both suites: the Sort screen measured today rendered
  in 1 query and made 1 REST request (1 query) before its first paint, which
  came at 12.2 s on the box. The Folders screen makes 0 requests before paint
  (asserted in the spec, `TODAY = { restRequests: 1, restQueries: 1 }`) and
  its boot data costs at most 9 queries (A1 in `tests/tree/guide.php`: today's
  render 1 + request 1 + the tree's 7).
- `pnpm test:ui shots.spec` on the box: nine of nine screens rendered without
  a JavaScript error, `shot-folders.png` among them. The spec plants one turn
  on an empty session first and empties it after, so a push spends nothing.
- Screenshots: `tests/ui/shots/folders-moving.png`, `folders-done.png` (the
  walk), `test-results/folders-resting.png`, `folders-rules.png` (the resting
  run; `test-results/` is emptied by the next run, `tests/ui/shots/` is not and
  is ignored by git).
- Deploy: `node tools/deploy.mjs --box`, `php -l: every file parses`. Service:
  `pnpm typecheck` clean, `pnpm test` 343 passed, pushed to Vercel.
- The box was left as found: 19 folders, 268 unfiled, no undo record, the
  guide session empty, `vergeml_walk_slow` unset, the session-only admin
  `vgml-p3` removed. `wp-content/mu-plugins/mu-walk-slow.php` stays (inert).
- Credits: the walk ran three times (once failing at the fill bars, once
  before the screenshots were lost to `test-results/`, once for the record):
  six calls' worth, plus about six calls' worth the first spec run spent by
  opening the screen on an empty session before the ordering was fixed. Say
  it: roughly 120 credits of the box's licence went on this phase's tests.

## What Phase 4 inherits

- The shell's sticky rules (`vergeml-shell.css` ~138, 863, 1229, 2189, 2396)
  are untouched; the Folders screen is in normal flow inside them.
- Every user-facing string on the Folders screen follows the copy standard;
  Phase 4's pass covers the other screens. `core/admin-menu.php`'s home card
  and `core/nl-commands.php`'s undo line already say Folders.
- The stylesheet `css/vergeml-tree-view.css` is now scoped to
  `.vgml-tv[data-surface="folders"]`: the media library's `vergeml-tree.css`
  and the shell's own `.vgml-row` and `.vgml-rule` are on the same page and
  outranked the component's rules by order. `.vgml-folders-move` and
  `.vgml-rule-row` are the screen's names for the same reason.

## Decisions taken here, for Nathan to overrule

- **A hand edit costs a turn.** The mock shows the assistant reacting to
  "Moved Edison bulb lighting under Workspace", so a hand edit streams
  `input.edit` when the conversation can talk (not at the cap, not while a
  Move runs, not while another reply streams). Each is a metered call and a
  turn of the 25.
- **A rule does not call the model.** Its line goes into the conversation;
  the assistant sees it on the next turn the person takes.
- **"Move N pictures" counts what folders gain over what they hold today**,
  summed over the draft (the mock's 170 = 61 + 109). A draft that only renames
  or moves folders reads "Move 0 pictures" and is enabled.
- **A removed folder's pictures go to its nearest kept ancestor**, or to no
  folder at the top level; the evidence matcher may still send some elsewhere.
  The model's tree block does not say where a dropped folder's pictures go.
- **The token is minted where the browser streams.** On the box the plugin's
  service URL is loopback (`127.0.0.1:3100`); the browser cannot reach it, and
  a token signed there would only be valid there. `site` is `home_url()` as
  the licence has it: a site whose admin origin differs from its home URL
  will fail the service's origin check (`bad_token · site`) — the service's
  concern, noted, not fixed.
- **Counts on a conversation draft are the model's.** A kept folder whose
  count the model echoes unchanged is shown unchanged; the old class-matching
  estimate is not run, because it repartitioned the whole library and marked
  every folder changed.
- **Stop-while-streaming keeps the partial reply as a turn** and counts it; the
  service metered it already.
- **The Move's `by_term` is joined to the draft by key on the server**
  (`vergeml_guide_landed_by_key`), because a folder the Move makes has no term
  id until it does.
- **The draft carries the model's tags for the apply**; the tree does not draw
  them (the mock shows none).

## Found, not done

- **By subject finds no folder on the box's unfiled pictures** at the default
  smallest size of 10 (0 folders, 0 move); on every picture the mock's data
  showed seven. The rule is right on the numbers; the default may want to be
  lower, or the description may want to say so. The eval that would tune the
  opener's length (Phase 1's "819 characters") has not been run either.
- **The service's turn cap counts the conversation it is sent**; the plugin
  sends every persisted turn (bounded at sixty). A long session's `moved`
  lines and hand edits ride along as text. Fine at 25; worth a look if the cap
  ever rises.
- **`tests/ui/screens.spec.mjs`'s "sort into folders" test** skips itself (its
  selectors are the pre-redesign talk panel's). Delete or rewrite in Phase 4's
  removals.
- **The dashboard's "Put files in folders" and the rail badge** point at the
  right slug already; their copy is Phase 4's.
- **`tools/box-guide-walk.*` were deleted** (they drove routes that no longer
  exist); `tools/box-guide-reset.sh` still works (it deletes the option).
- **The undo record from before this phase had no `until`**; the button then
  reads "Undo the last Move". Any Move from now on carries one.
- **Playwright's `check()` on a radio the app re-renders** works but re-resolves
  the locator; if it ever flakes, click the label instead.
- FileBird Pro is still active on the box's media screen (memory says
  inactive).

## Phase 4 opener, to paste

```
Read docs/handoffs/2026-09-05-phase-3-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 4, tasks 14 to 17 (plugin: the shell in normal flow -- every sticky and fixed rule out of vergeml-shell.css, save bars at the end of their forms; the three settings screens' sections under chevrons, closed by default, the open one remembered per person; the removals (the guide's old states, the wizard beside the chat card, the "Recently described" strip, screens.spec's talk-panel test); the copy pass to the standard on every screen). The plan's Phase 4 tasks must first be written in the Opus shape (Files, Behaviour, Proof, Mirror, Copy, Do not) as Phase 0's are; do that before the first task.
Stop points: any visible shape the mock does not show; the remove control on a Folders row waits for Nathan's word on docs/superpowers/mocks/2026-09-05-folders-remove-control.html.
Gates: tests/ui/shell.spec.mjs (new: no element in .vgml-shell computes to sticky or fixed, the document grows with content, the rail is static); the settings sections closed by default and remembered; tests/ui/shots.spec.mjs; tests/tree/journey.php still green.
End with a handoff in docs/handoffs/.
```
