# Session handover — 2026-09-06, Phase 6 done (Fable 5.1)

For the next session. Read in this order, then open Phase 7 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 to 6 are done. Phase 7 (grid and
   list aligned: a table of every action against both modes, gaps filled in
   `js/vergeml-tree.js` and the list hooks, both modes walked) is next and
   wants Fable 5.1. Its task 22 says the table comes before code: that table
   is the phase's stop point, as the mock was this phase's.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §13 — the
   look-alike decisions, recorded this phase; §4 and §5 are the contract
   every screen is held to.
3. This handoff's "What Phase 7 inherits" and "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.

Plugin on `main`, one feature commit plus this handoff. The service was not
touched this phase. The nightly watch commits to `main` around 05:17 UTC:
`git pull --rebase` before pushing.

## Still Nathan's

- Migration 018 (`library_counts`) on the production database, from the
  Phase 0 handoff. Nothing in Phases 1–6 depends on it.
- **A remove control on a Folders row** — unchanged since Phase 3. The
  proposal is `docs/superpowers/mocks/2026-09-05-folders-remove-control.html`.
- **Alt text that is present but junk** and **no language sent with a
  describe** — both from the Phase 5 handoff, both product calls.
- **The "Files you have taken out of the library" card** at the foot of
  Duplicates keeps its paragraph and its "Show them" button. Files set aside
  by Keep this one land there, with the reason "Look-alike of 01.jpg, kept on
  6 September 2026". The done line links to it. Whether the card is rewritten
  to the standard (a facts line, the list drawn on load) is a copy row for a
  later pass; approved as left this phase.

## What landed

**The mock**, `docs/superpowers/mocks/2026-09-06-duplicates-pairs.html`: two
boards, the box's own 22 look-alike sets with their real pictures, titles,
dimensions, sizes, dates and folders; a set in six states. Approved by Nathan
with two additions taken on advice — the band's third cell reads `held by the
extra copies`, and the done line carries `Set aside ↓`. Spec §13 records it.

**Bin is Set aside.** No new bin, no WordPress trash. "Keep this one" rewrites
every page that shows the other picture to the kept file and then marks the
other with `core/quarantine.php`'s set-aside: on disk and at its URL for 30
days, out of the library, taken back with one press.

- `core/health-keep.php` (new): `vergeml_health_keep( $keep, $drop )` —
  refuses without the usage scan and for any file the stored hashes do not
  pair with the kept one (same md5, or dhash within `VERGEML_HEALTH_LOOSE`);
  maps each URL of the file that goes to the kept file's nearest size by width
  (`vergeml_health_url_map()`), rewrites the page's content and every meta row
  in plain and JSON-escaped form plus the block id and image class
  (`vergeml_health_rewrite_text()`, sharing `vergeml_health_repoint_ids()`),
  moves the featured image and the parent; then reads the page again with the
  usage scan's own extractor (`vergeml_health_still_uses()` →
  `vergeml_refs_in()`) and sets the file aside only when it is no longer
  found. A use it cannot reach — the site's own settings (source 0), a
  serialised layout whose text length would change — leaves the file where it
  is and the line names the use. The used-in marks move with the pages so the
  cards stay right without a rescan. Undo: a record per keep for a day
  (`vergeml_health_keep_undo`, last twenty), pages put back only while they
  still read as this wrote them, files released. `vergeml_health_retire()`
  writes every pair key among the ids into `vergeml_health_kept`; the report
  skips them; undo removes them. Routes `POST health-keep`, `health-keep-undo`,
  `health-retire` (`manage_options`).
- `core/health.php`: the report's files carry `width`, `height`, `date`,
  `folder` (one terms query for the set) and `large`; every item carries
  `used_in` (the pages, titled, typed, linked); `uses_scanned` on the report;
  the look-alike list leaves out files set aside and pairs kept; the band's
  third cell; the l10n block for the cards.
- `js/vergeml-health.js`: `drawPairs()` / `drawPair()` / `drawSide()` — the
  card, the facts list, the buttons with their consequence in the label, the
  done line with Undo (until, in the site's words: today / tomorrow HH:MM) and
  `Set aside ↓` (which presses the card's "Show them"); `Open both ↗` opens
  one tab per file and, when the browser allows one tab per click, becomes
  `Open <file> ↗` for the second press; `usageControl()` runs the usage scan
  from the list when it has not run. The exact-duplicates list is untouched.
- `css/vergeml-shell.css` (+ rtl): the `.vgml-pair*` rules, the mock's numbers.

**Copy.** `docs/superpowers/copy/2026-09-06-phase-6-copy.md`; five struck
strings and five kept ones in `tests/tree/copy.mjs`.

**Suites.**

- `tests/tree/health-keep.php` (box): makes two copies of its own from a
  library JPEG — flipped and cropped, so alike to nothing there; the second
  scaled and re-saved, so alike to the first — and refuses to go on unless the
  scan pairs exactly those two (B4). Then: a keep while a draft post shows the
  other (the URL at its full and its nearest sized copy, the block id, the
  image class, the featured image, the marks, the scan's own reading; undo
  puts every one back; an undo is spent once); a keep on an unused file; a
  use by the site's settings and a serialised layout, both of which leave the
  file where it is; keep both and its undo; the three refusals; and that
  nothing is left behind.
- `tests/ui/health.spec.mjs` (`HEALTH_WALK=1`): draws two pictures in the
  browser, uploads them, finds them as one set and presses Keep this one,
  Set aside ↓, Undo, Keep both, Undo on that set and no other; deletes both
  uploads after. Spends nothing.
- `tests/tree/health.mjs` repaired: it logged in as `admin/VgmlTest7pass`,
  which the box does not have, and had been red at the door for as long as
  that was so; it now takes `UI_USER`/`UI_PASS` and solves Jetpack's sum like
  `tests/ui/fixtures.mjs`. Its assertions follow the screen: the two lists'
  kickers, the band's numbers, the look-alike cards; the delete-control checks
  run only on a box that has an exact set (this one has 0).
- `tests/ui/shots.spec.mjs`: the Duplicates screenshot waits for the report
  and asserts the card's shape.

## Evidence

- `node tools/deploy.mjs --box`: `php -l: every file parses` (PHP 8.5.4 on the
  box; nothing above 7.4 syntax used); `node tools/rtl.mjs --check` up to
  date; `node --check` on the two changed scripts.
- `node tools/verify.mjs health-keep` → **33/33**. Mutation checks, each
  applied on the box by `sed`, run, and the real build re-shipped: the
  verification dropped from `vergeml_health_rewrite_source()` (`return true`)
  → **32/33**, red at *E2 a serialised layout is not rewritten, so the file
  stays*; the set-aside exclusion dropped from `vergeml_health_report()` →
  **32/33**, red at *C7 the other is set aside and the set is off the report*.
  The real build back: 33/33.
- `HEALTH_WALK=1 npx playwright test health.spec` → **passed** (1.5 m): the
  two uploads one set, `Keep this one · vgml-p6-ui-walk-b.jpg set aside`
  pressed, the line `vgml-p6-ui-walk-one.jpg kept · vgml-p6-ui-walk-b.jpg set
  aside for 30 days · Undo until today 11:58 · Set aside ↓`, the Set aside list
  showing "Phase 6 UI walk B — Look-alike of vgml-p6-ui-walk-one.jpg, kept on
  6 September 2026", Undo, `Both kept · not shown again`, Undo. Mutation
  check: `links.push( asideLink() )` dropped from the done line on the box →
  **1 failed** at `Set aside ↓`; the real build back → **1 passed**.
- `node tools/verify.mjs copy` → **45/45**; `health` → **27/27** (it was
  failing at login before this phase; see above); `journey` → **62/62**;
  `guide` → passed.
- `npx playwright test shell.spec shots.spec` on the box → **25 passed**
  (5.6 m): the shell sweep over the eleven slugs and the three collapsed
  settings screens, and eleven screenshots with no JavaScript error; the
  Duplicates one now waits for the report and asserts the card.
- Screenshots: the mock's two boards and the built screen, shown in the
  conversation.
- **Credits: 0 spent.** 25,971 at the start, **25,971** at the end
  (`vergeml_ai_refresh_credits( true )` on the box). Nothing describes an
  upload; the walks' pictures came and went undescribed.
- Box left as found: 0 files set aside, 0 kept pairs, 0 undo records, no walk
  file on disk, no walk post; the session-only admin `vgml-p6` removed.

## What Phase 7 inherits

- `vergeml_health_uses_of( $id )` names where a file is used, as the
  attachment screen words it; `vergeml_health_still_uses( $post, $id )` is the
  scan's own reading of a page, for anything that claims to have rewritten one.
- `vergeml_health_rewrite_text( $text, $map, $from, $to )` moves every
  reference from one file to another inside a blob; `vergeml_health_url_map()`
  builds the map for two files of different sizes.
- The pair card is `drawPair()` in `js/vergeml-health.js`; its grammar is the
  `.vgml-pair*` block in the shell stylesheet.
- `tests/tree/health-keep.php`'s `hk_make_file()` is how a suite makes a
  picture of its own on the box, hashed as the scan would.
- The mock generator that drew the boards from the box's data is not in the
  repo (a scratchpad script); the mock is.

## Decisions taken here, for Nathan to overrule

- **Undo lasts a day and holds the last twenty keeps.** A record carries page
  content, so it is bounded. Set aside itself has no window: the file is on
  the Set aside list until taken back.
- **Pages are written the way the delete route writes them** — `$wpdb->update`
  and a cache clear, no revision, no `save_post` hooks — so a builder's
  save-time handlers do not run on a rewrite they did not ask for.
- **A use by the site's own settings is never rewritten** (the logo, a
  widget, the customiser): the file stays and the line says "1 use in Site
  settings could not be rewritten".
- **Keep this one needs the usage scan**, as the delete does. Without it the
  buttons say so and `Scan usage` sits above the cards.
- **No side is proposed as the keeper** on a look-alike set; the exact list
  keeps its radio and its proposal.
- **Open both is tabs**, one per file, with the second-press fallback. The
  fallback is written for Chrome's one-popup-per-click rule and was not
  exercised by the walk (headless Chromium allowed both).
- **A kept pair is a pair key**, `a-b`. A set of four retired is six keys. A
  member later deleted leaves its keys behind; they match nothing and cost
  nothing.

## Found, not done

- The Set aside card, under "Still Nathan's".
- `tests/tree/health.mjs` presses Scan again and re-hashes the whole library
  every run (about a minute on 641). It always did; it is now green, so it
  now costs that minute.
- The shell stylesheet's `.vgml-health-groups` / `.vgml-health-files` rules
  for the old look-alike grid are dead for look-alikes and still serve the
  exact list; left in place.
- The `careful` l10n string in `core/health.php` has no reader in the script;
  left.
- The uploads a walk makes count as "new pictures" on the AI screen's badge
  while they exist (about 90 seconds); nothing describes them.
- Everything on the Phase 5 handoff's list that this phase did not touch
  still stands: the `confirm()` dialogs, `core/help.php`, the Phase 4 and 3
  items behind them.

## Phase 7 opener, to paste

```
Read docs/handoffs/2026-09-06-phase-6-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 7, task 22: grid and list aligned — a table of every
action against both modes (open, select, bulk move, drag to folder, folder
filter, counts, search, sort, keyboard), the gaps filled in js/vergeml-tree.js
and the list hooks, both modes walked by Playwright as tests/ui/modes.spec.mjs.
Stop points: the table, before code. Any visible shape the table does not name.
Gates: tests/ui/shell.spec.mjs, tests/ui/shots.spec.mjs, tools/verify.mjs
copy, health, health-keep, journey and guide still green. Say the cost of any
test that spends credits before it runs; never open the Folders screen on an
empty guide session.
End with a handoff in docs/handoffs/.
```
