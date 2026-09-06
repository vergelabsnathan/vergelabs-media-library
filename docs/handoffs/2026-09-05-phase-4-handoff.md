# Session handover — 2026-09-05, Phase 4 done (Opus 5, 1M)

For the next session. Read in this order, then open Phase 5 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 to 4 are done. Phase 5 (the AI
   screen, three tabs, **mock first**) is next and wants Fable 5.1:
   architecture, a conversation component reused, and two days of work. Its
   tasks 18 to 20 are still one line each; a Fable phase does not need the
   six-field shape, but it does need the stop points and the cost lines
   written before the session starts.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` — §3 and §7
   gained a recorded exception each this phase; §5 is the copy standard the
   pass ran against.
3. This handoff's "What Phase 5 inherits" and "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.

Plugin on `main`, one commit `18901b8` plus this handoff. Service untouched
this phase, still `407e025`. The nightly watch commits to `main` around 05:17
UTC: `git pull --rebase` before pushing. **Nothing has been pushed** — the
commit is local.

## Still Nathan's

- Migration 018 (`library_counts`) on the production database, from the
  Phase 0 handoff. Nothing in Phases 1–4 depends on it.
- **A remove control on a Folders row** — unchanged from the Phase 3 handoff.
  The proposal is `docs/superpowers/mocks/2026-09-05-folders-remove-control.html`.
  Approve or strike; it is an hour to draw.
- **The copy table's out-of-scope half.** `core/help.php`'s per-control help
  text is about two hundred entries and was not touched. And spec §4.4 says
  confirmation is the button, not a dialog — this phase fixed the wording of
  the `confirm()` dialogs and left the dialogs standing.

## What landed (one commit, `18901b8`)

**Task 14 — the shell in normal flow.** Every sticky rule out of
`vergeml-shell.css`: the rail (`position`, `top`, `height`, `overflow`), both
save bars, `.vgml-pg-actions`, and the mime table's head. Measured on File
types: the page scrolled 4,632px and the rail moved 4,632px with it.

Two rules the sticky had been propping up were found by looking at the
screenshot and fixed in the same task, and both are written into the plan:
`.vgml-shell-body` went `align-items: flex-start` → `stretch`, because a rail
with no height left the divider stopping a third of the way down an empty
band; then `.vgml-shell-list` went `flex: 1` → `flex: 0 0 auto`, because
stretch had pushed the credits card to the bottom of a 4,600px page.

One exception, Nathan's on 2026-09-05 and now in spec §3 and named in the
gate: **`.vgml-health-bulk` keeps its sticky.** The Duplicates list runs to two
hundred sets and a bulk control that scrolls away from what it controls is not
used twice. It is built by JS and exists only once there are groups, so the
suite proves the exception on the CSS rule rather than on the DOM.

**Task 15 — settings under chevrons.** `vergeml_acc_start()`,
`vergeml_acc_end()` and `vergeml_acc_facts()` in `core/admin-shell.php`;
`.vgml-acc` in `vergeml-shell.css` with the geometry copied from the approved
mock's board 3; `js/vergeml-settings.js` for the memory. Every section is
printed **closed by the server**, so the screen is short whether or not the
script loads. **Any number may stand open** (Nathan's call when asked which:
it is what `<details>` does natively, the browser gives role, keyboard and a
find-in-page that opens the section the match is in, and a settings screen is
where two things get changed at once).

- Library settings: **seven** sections led by Order (spec §7 — the setting
  that matters most is first). Phase 0's "Share library counts" is one of
  them, printing its own `<details>` from `core/instrument.php`.
- Folders and categories: three, led by Media taxonomies.
- File types: **five, by the kind its own filter already names** (Nathan's
  call; recorded in spec §7). 98 types in one table was a 4,632px screen.

The mock's board 3 shows five Library settings sections — Ordering, Uploads,
Image sizes, Embeds — that **do not exist on the real screen**. It is
illustrating the chevron pattern with invented content. The real six were
kept and only reordered; Nathan confirmed.

**Task 16 — the removals.** 672 lines and four files, none with a caller in
either repo: `vergeml_librarian_page_legacy()`, `vergeml_librarian_assets()`
(never hooked), `vergeml_librarian_steps()` (reached only from the enqueuer),
`vergeml_talk_card()`, `vergeml_talk_assets()` (never hooked),
`js/vergeml-librarian.js`, `js/vergeml-folder-talk.js`,
`css/vergeml-librarian.css` + rtl, the dashboard's "Recently described" strip
with its `.vgml-seen` CSS, the `vergeml-librarian` entry in `tools/rtl.mjs`'s
`SHEETS`, and `screens.spec`'s self-skipping talk-panel test.
`vergeml_librarian_stage()` **stays** — `core/journey.php:663` calls it.

**Task 17 — the copy pass.** The table came first and is
`docs/superpowers/copy/2026-09-05-phase-4-copy.md`: 21 rows, each with the
rule from spec §5 that it failed. The audit found **less than the phase
assumed** — Phases 0 and 3 had already written every screen's title and the
line beneath it to the standard, and there was no "Let's", no "You are here",
no smiley and no exclamation on any screen. What was left: the fork-era
taxonomy and MIME dialogs ("Please", `chose`, `taxomonies`, a question asked
under three lines that had already answered it), the counts notes, the `?`
button's aria-label, and three error messages.

## Evidence

- `pnpm test:ui shell.spec shots.spec screens.spec dashboard.spec` on the box:
  **30 passed (6.8 m)**, no skips. `shell.spec.mjs` is new: nine screens
  swept for sticky/fixed under `.vgml-shell`, the rail static and not
  self-scrolling, the rail travelling with the page, save bars last in their
  form; then three settings screens closed, two open at once, both remembered
  across a reload, both closed and nothing remembered, and File types' Video
  filter opening Video alone.
- `node tools/verify.mjs copy` → **21/21** (new, `tests/tree/copy.mjs`,
  env `local`); `journey` **62/62** (one new assertion); `guide` **30/30**;
  `folders-version` **24/24**; `counts` **26/26**.
- Mutation checks, one per task. 14: sticky back on `.vgml-shell-nav` → red at
  assertion (a), *pinned in .vgml-shell on filetypes*. 15: `<details open>` in
  `vergeml_acc_start()` → red, *closed by default, Expected 0, Received 7*.
  16: `.vgml-seen` back on the dashboard → *FAIL the "Recently described"
  strip is gone*, 61/62. 17: "Saved. Thank you." back → red at that row and at
  the row asserting its replacement. Real build back, green, every time.
- `node tools/rtl.mjs --check` up to date; `php -l: every file parses`;
  deploy verified, 142 files re-hashed.
- Screenshots: `test-results/shot-{library,taxonomies,filetypes}.png` and the
  two rail shots shown in the conversation. `test-results/` is emptied by the
  next run.
- **Credits: none.** The rail read 25,981 in the first screenshot of the
  session and 25,981 in the last. The Folders screen was opened only behind
  the planted-turn guard.
- Box left as found: 19 folders, `vergeml_guide_session` deleted, the
  session-only admin `vgml-p4` removed.

## What Phase 5 inherits

- `vergeml_acc_start()` / `vergeml_acc_end()` / `vergeml_acc_facts()` are the
  way a settings section is written now. The AI screen does not use them yet.
- The shell is in normal flow everywhere. A new screen that wants something
  pinned has to argue for it and be named in `tests/ui/shell.spec.mjs`, or the
  gate goes red.
- `tests/tree/copy.mjs` is where a struck string goes when the pass strikes
  one. It reads `core/` and `js/` from disk and runs without a site.
- The Folders conversation component that Phase 5's "How it describes" tab
  reuses is `js/vergeml-folders.js`, untouched this phase.

## Decisions taken here, for Nathan to overrule

- **The Duplicates bulk bar keeps its sticky.** Asked, and answered: better UI
  for a two-hundred-set list. Everything else in the shell is in flow.
- **Any number of settings sections may stand open**, not one at a time.
  Asked, and Nathan asked back for the best answer; the reasoning is above.
- **File types is five sections by kind**, and a new type lands in Other.
- **`tax_deletion_confirm_text_p4` is now an empty string** rather than a
  removed key, because `js/vergeml-taxonomies-options.js` concatenates the
  four paragraphs and a missing key would print `undefined`.
- **One commit for the phase, not one per task.** Nathan's rule is a commit
  per logical unit; `core/options-pages.php` is touched by tasks 15 and 17 and
  `tests/ui/shell.spec.mjs` by 14 and 15, so splitting would have been a
  fiction. Said here rather than quietly done.

## Found, not done

- **`tests/ui/shots.spec.mjs` never un-plants its turn.** It sets `planted` and
  then never reads it; the Phase 3 handoff says the spec "empties it after"
  and it does not. Harmless — a planted turn costs nothing — but it leaves
  state on the box, which `tests-never-touch-live-state` says a spec must not.
  Two lines to fix.
- **The `confirm()` dialogs survive.** Spec §4.4 wants the button to be the
  confirmation. `core/tree-ui.php`'s folder delete, `core/journey.php`'s
  re-describe, and the taxonomy and cleanup dialogs in `core/options-pages.php`
  are all still dialogs, now with better wording.
- **`core/help.php`'s help text was not audited** — roughly two hundred
  entries, its own task.
- **The mock's board 3 invents five Library settings sections** that the real
  screen does not have (Uploads, Image sizes, Embeds). If those are wanted,
  they are new settings, not a collapse.
- **The copy table missed a third `cannot be canceled!`** (`sync_warning_text`,
  `core/options-pages.php:660`); `tests/tree/copy.mjs` caught it on its first
  run. The table has been corrected.
- Everything on the Phase 3 handoff's "Found, not done" list that Phase 4 did
  not touch still stands: By subject finding no folder at the default smallest
  size, the service's turn cap counting the conversation it is sent, the undo
  record from before Phase 3 having no `until`, and FileBird Pro still active
  on the box's media screen.

## Phase 5 opener, to paste

```
Read docs/handoffs/2026-09-05-phase-4-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 5, tasks 18 to 20: the AI screen as three tabs (Describe,
How it describes, Search). Task 18 is a mock in the shell's grammar with the
box's real numbers, and I approve it before any code is written -- send the
screenshots into the conversation, do not only link them.
Stop points: the mock, before code. Any visible shape the mock does not show.
The remove control on a Folders row still waits on
docs/superpowers/mocks/2026-09-05-folders-remove-control.html.
Gates: tests/ui/shell.spec.mjs (nothing new may be sticky or fixed in the
shell), tests/ui/shots.spec.mjs, tools/verify.mjs copy, journey and guide still
green. "Test on 5 pictures" spends 5 credits -- say the cost in the log line
before it runs, and never open the Folders screen on an empty guide session.
End with a handoff in docs/handoffs/.
```
