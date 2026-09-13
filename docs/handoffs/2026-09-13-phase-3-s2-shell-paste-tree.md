# Handover — Phase 3, S2: 3.2 the app shell, 3.3 the draft as tree rows, 3.4 paste paths (Fable 5.1)

2026-09-13. One session, two commits, on the 2026-09-12 decision (one way in
beside the conversation: paste paths with `>`). Built to the approved mock
`docs/superpowers/mocks/2026-09-10-folders-ways-in.html`, the S1 handoff's copy
verbatim, the tree's own rows, no change to how pictures are filed.

## What shipped — commits `09db0e4`, `6df3d07`, on `main`, deployed to the box

- **The paste reader** `js/vergeml-structure.js`: one reading of a paste (split
  on `>`, trim, drop empty segments, whitespace never depth, every folder on a
  path made once, the same path once, a name matched case-insensitively to a
  live folder at that place and reused); the three refusals by line; `toDraft`
  (every live folder kept, the new ones added, origin `talk`) and `toPreview`.
  Decides nothing about pictures.
- **The Paste tab** in `js/vergeml-folders.js`: the sentence, the box, beside it
  the tree component's rows (a folder that exists reads as itself, a new one
  wears the tree's `new` tag, no count until the dry run has looked), the fact
  line, the refusal list. A clean paste is the draft at once, and goes to
  `/guide/turn` once typing settles (600 ms), so every number on it is the
  matcher's; until the session holds it, Move reads "Move the draft" and is
  disabled. A refusal makes nothing: the draft stays as it was. A hand edit or
  a Move drops a paste answer still in flight.
- **The Rules tab is gone** from the screen (spec: "Gone with this decision").
  Its routes and PHP stay in `core/guide.php`; a persisted rule draft still
  applies. The two rules specs are replaced.
- **The app shell** (`css/vergeml-folders.css`, under `body.vgml-app-folders`
  from `core/guide.php`, this screen only): the content pane is sticky at the
  viewport's height; the flex chain runs through WordPress's own `#wpbody` and
  `#wpbody-content` to the two columns; the thread, the paste and the tree list
  scroll inside their regions; the composer and Move stay on screen at the cap.
  Below 641 px tall or 1181 px wide the page flows as before. At 1280 wide the
  columns close up (`max-width: 1366px` rule) so nothing overflows sideways.
- **Tree component** `js/vergeml-tree-view.js`: three options for the preview
  beside the box — `head: false`, `fold: false`, `openAll: true`. The Folders
  tree and the Library panel are untouched; `tree-view.mjs` stays 43/43.
- `core/guide.php`: enqueues the reader; adds the body class. Nothing else.

## Evidence

- `node tests/tree/structure.mjs` → **31/31 passed**. Registered in
  `tools/verify.mjs` as `structure` (env local).
- `node tests/tree/tree-view.mjs` → **43/43 passed**. `tests/tree/copy.mjs` →
  52/52. `node tools/rtl.mjs --check` → up to date.
- `npx playwright test --config tests/ui/playwright.config.mjs tests/ui/folders.spec.mjs`
  on the box → **7 passed, 1 skipped (the walk), 6.1 m**. Shots in
  `tests/ui/shots/`: `folders-shell-1600x1000.png`, `folders-shell-1280x800.png`
  (composer and Move inside the viewport at the 25-turn cap, the thread
  scrolled to its end), `folders-paste.png`, `folders-paste-refused.png`.
- Mutations, each run against the suite and put back:
  - whitespace read as depth → `structure.mjs` red at B2;
  - the 500 cap removed → red at C3;
  - `overflow-y: auto` off the thread → `folders.spec` red: "the region
    scrolled: expected > 0, received 0", the last turn at 3,729 px;
  - one preview branch as a `<li>` → red: "no bullet in the preview: expected 0,
    received 1";
  - **`min-height: 0` removed (the thread's, then the columns') → stayed green.**
    Measured, not guessed: a scroll container's automatic minimum is already
    zero, so those declarations carry no load; the chain of flex columns and the
    thread's overflow do. The CSS comment says so now. The plan's named
    mutation was wrong about which declaration.
- `node tools/filing-baseline-check.mjs` → **cannot be read on this box**: "the
  library is not the one the baseline was taken over — 641 pictures gone, 1000
  new" (the 2026-09-12 reseed). The two placement checks pass over an empty
  overlap. The proof that filing is untouched is the diff: `core/` changes are
  the enqueue and the body class in `guide.php` only (`git diff --stat HEAD~2 -- core/`).

## Decisions taken (routine, mine)

- The shell rules live in `css/vergeml-folders.css` (enqueued on this screen
  alone), not `vergeml-shell.css`: the sheet itself is the fence.
- `#wpfooter` is hidden and `#wpbody-content`'s bottom padding zeroed on this
  screen, so the pane fits the window. WordPress's admin menu is taller than
  the window on the box (thirty plugins, 1,624 px); the page scrolls that and
  the pane stays. The spec's "the page does not scroll at all" cannot hold on
  such a site; the test asserts the composer and Move stay put after scrolling
  the page to its end.
- The two viewports are asserted inside `folders.spec` (a loop), not as two
  Playwright projects: a second project doubles every suite's time on the
  shared box.
- A paste keeps every live folder (adds structure, removes nothing). The
  conversation's `resolveTree` marks unnamed live folders gone; a paste does not.
- Keys for pasted folders are a hash of the lowercased path (`p…`), stable
  across re-reads, ASCII-safe for the server's key cleaner.
- On reload the draft comes back (persisted by the turn route); the paste box
  is empty. The text is not persisted.
- The hand-edit spec now renames a leaf row. On a branch the first click of
  a double-click toggles it, the row re-renders, and no rename opens (see
  Found, not done).

## Stop points for Nathan (copy)

1. `leadNote` for a site with no licence still reads **"Rules work without
   one. The conversation needs one."** — Rules no longer exist on the screen.
   Proposed, not built: "Paste folders works without one. The conversation
   needs one."
2. The fact line's plural forms were needed for singular counts: "1 folder",
   "1 level deep", "1 already exists and will be reused." — grammatical
   variants of the approved line, in `_n()`.
3. Should a paste leave a line in the conversation, as a rule did ("You ·
   applied a rule")? Nothing is written today; a line would need copy.
4. The refusal line for a failed turn route (network) reuses the existing
   "That did not go through. Try again." under the refusal list.

## Found, not done

- **Double-click rename fails on a branch row** in `js/vergeml-tree-view.js`:
  the row's click toggles and re-renders on the first click. Pre-existing;
  visible now because the reseeded library's first folder ("2026") has
  children. F2 works. A fix is to defer the toggle past the double-click
  window or to ignore the second click's toggle.
- **`tests/ui/folders.spec.mjs` "when the dry run gives up"** is timing-bound:
  `tests/perf/mu-fit-cold.php` sets the budget to 0 and
  `vergeml_guide_draft_fit()` floors it at 1 s; a warm run over 20 folders
  now finishes inside that second and the test reads `counted: true`. It
  failed once and passed once today. The floor or the fixture needs a decision.
- **`.harness/active.json` for the plugin is untracked**: tracking it would put
  it in the wordpress.org zip (`tools/deploy.mjs --check` flagged it as a zip
  file when staged). Pro tracks its card. Either add `.harness/` to the
  deploy's exclusion list and track it, or leave it untracked.
- The tree's hover card (`.vgml-tv-hover`, absolute, below the row) is clipped
  by the list's `overflow-y: auto` on the last visible rows.
- At 1280 wide the paste box and its preview share a 410 px column (two
  narrow halves). Usable; the mock is at 1600.
- The dry run's "The counts are not worked out yet / The next turn should have
  them" appears under a paste on a library with no described pictures or no
  licence; the second line promises a turn that will not come there.
- `tests/ui/shots/folders-rules.png` and `folders-rules-failed.png` are
  orphaned by the Rules removal.
- Playwright empties `test-results/` at the start of a run: a log or a
  password file put there is gone by the end (lost one of each today).
- 3.5's Conversation sentence is not on the screen yet (its task). The Paste
  sentence is, verbatim.

## Cost

- Each paste on the box runs one dry run: one `/embed` call per new folder
  path, cached a week; no model turn. The suite's paste is five paths.
- No conversation was opened: every spec plants a capped session first.
- The throwaway admin `vgml-ui` was created for the runs and deleted after.

## Reordered, 2026-09-13 (Nathan: "manage that") — pushed as `b29e26c`

Fable credits are at 10 % with two days to the reset, and nothing before the
reset needs Fable. Yes 1 and Yes 2 are done; Yes 3 cannot close in two days
on any model (five strangers, a newcomer timed, an eighteen-state design to
approve). The plan's own order when time is short is **2, 4, 5, 1, 3**. So:

1. **Nathan sends the wordpress.org form** (`docs/wordpress-org-submission.md`,
   the S4 Phase 1 handoff). The review queue is the longest pole.
2. **Phase 4 S1 on Opus, in `../service`, next** — 4.1 prove the health
   endpoint's five 503 paths, 4.3 prove the release check against a stale
   channel. Needs nothing from Nathan. Card below; the session writes it to
   `service/.harness/active.json` before its first edit.
3. **Phase 5's first session on Opus** after that, while the queue sits.
4. **Phase 3 S3 after the reset** — the card for it is kept further down,
   unchanged, for that session.

```json
{
  "phase": "Four yesses — Phase 4, S1: 4.1 health endpoint proven + 4.3 release check proven",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-4.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "app/api/health/route.ts",
    "app/api/cron/release-check/route.ts",
    "lib/updates.ts",
    "lib/health.test.ts",
    "lib/release-check.test.ts",
    "docs/**"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-3-s2-shell-paste-tree.md",
    "../plugin/plans/four-yesses/phase-4.md",
    "app/api/health/route.ts",
    "app/api/cron/release-check/route.ts",
    "lib/updates.test.ts"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "No check reads a value into the health body; the five check names stay as they are",
    "The test never calls the live provider: the relay check is mocked",
    "The release check must compare the zip itself (Version header, or size and digest), never the version string alone",
    "Production is not broken in this session (that is 4.2's rehearsal, with the monitor in place)"
  ],
  "gates": [
    "pnpm test → green with lib/health.test.ts (five 503s, each naming its own check and no other) and lib/release-check.test.ts (stale 3.0.0 → reported; correct JSON → passes; a 404 zip → reported)",
    "pnpm typecheck",
    "the live /api/health 200 body pasted in the handoff, through a node fetch script (the hook blocks curl)",
    "mutation: the stripe check swallowing its error → its test red",
    "the cron's last run in Vercel's log; if the release check only logs on failure, wire it to the releases health check and say so"
  ]
}
```

Opener to paste into a fresh session, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-3-s2-shell-paste-tree.md
("Reordered" section), then ../plugin/plans/four-yesses/phase-4.md tasks 4.1
and 4.3. State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is Phase 4 S1. Write the
card from the handoff to .harness/active.json before the first edit. Stop
points and gates are in the card. End with a handoff in
../plugin/docs/handoffs/.
```

## Next, after the reset — Phase 3 S3

Phase 3 S3 on Opus, per `plans/four-yesses/phase-3.md`: **3.5** (the method
sentences — now two: the Conversation sentence from the spec table, the Paste
one is built; `tests/tree/copy.mjs` asserts both by exact text) and **3.9**
(destructive actions; its copy is a stop point drafted first). Stop point 1
above belongs in 3.5's copy table.

```json
{
  "phase": "Four yesses — Phase 3, S3: 3.5 method sentences + 3.9 destructive actions",
  "model": "opus",
  "plan": "plans/four-yesses/phase-3.md",
  "spec": "docs/superpowers/specs/2026-09-10-folders-ways-in.md",
  "scope": [
    "js/vergeml-folders.js",
    "js/vergeml-tree-view.js",
    "js/vergeml-media-list.js",
    "core/options-pages.php",
    "tests/**",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-13-phase-3-s2-shell-paste-tree.md",
    "plans/four-yesses/phase-3.md",
    "docs/superpowers/specs/2026-09-10-folders-ways-in.md"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "3.5: the two sentences verbatim from the spec table; the no-licence note's replacement (stop point 1 in the S2 handoff)",
    "3.9: every confirmation string is drafted as a table first and built only after approval; the renamer stays gated",
    "Nothing changes in how pictures are filed"
  ],
  "gates": [
    "node tools/verify.mjs copy → N/N with the two sentences asserted",
    "npx playwright test --config tests/ui/playwright.config.mjs tests/ui/folders.spec.mjs → 7 passed on the box",
    "tests/ui/destructive.spec.mjs: each confirmation carries its own count read from the DOM; mutation: a hard-coded count goes red on a different selection"
  ]
}
```
