# Session handover — 2026-09-06, Phase 5 done (Fable 5.1)

For the next session. Read in this order, then open Phase 6 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 to 5 are done. Phase 6 (similar
   pictures: a pair view with keep both / keep this one / open both, nothing
   binned while in use unless its uses are rewritten) is next and wants
   Fable 5.1: it rewrites posts and bins files. Its task 21 is one line; a
   Fable phase needs the stop points and the cost lines written before the
   session starts — the pair view is a visible shape, so a mock first.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §12 — the AI
   screen's four decisions, recorded this phase; §4 and §5 are the contract
   every screen is held to.
3. This handoff's "What Phase 6 inherits" and "Found, not done".
4. `~/.claude/harness/model-profiles.md` — state the model, follow the profile.

Plugin on `main`, one feature commit plus this handoff. Service on `main` at
`d1bd430` (two commits this phase, both deployed to prod and verified by the
route answering). The nightly watch commits to `main` around 05:17 UTC:
`git pull --rebase` before pushing.

## Still Nathan's

- Migration 018 (`library_counts`) on the production database, from the
  Phase 0 handoff. Nothing in Phases 1–5 depends on it.
- **A remove control on a Folders row** — unchanged since Phase 3. The
  proposal is `docs/superpowers/mocks/2026-09-05-folders-remove-control.html`.
  Approve or strike; it is an hour to draw.
- **Alt text that is present but junk.** On the box 620 of 641 alt texts are
  fixture rubbish ("q" ×226, "qode interactive strata" ×74, "a" ×57); the
  plugin writes alt only when the field is empty, so the model's alt never
  reaches them. The Describe tab says so ("641 of 641 have one · 620 kept
  what was there") and offers nothing. A real site with a theme that filled
  alt with the file name is the same case. Whether a "replace what is
  there" control exists is a product call.
- **No language is sent with a describe.** The service writes alt, caption
  and title in English whatever the site's locale; the How tab states it as
  a fact ("Alt text, caption and title in English"). Sending `get_locale()`
  is a small change on both sides and a re-describe of every library.

## What landed

**Task 18 — the mock**, `docs/superpowers/mocks/2026-09-06-ai-screen.html`,
six boards, every number the box's own, one real five-picture test (5
credits) so the before/after rows are real answers. Approved by Nathan with
the four decisions as drawn; spec §12 records them.

**Task 19 — How it describes.**

- `js/vergeml-talk.js` (new): the conversation component — turns, chips, the
  composer, the token, the streamed turn, the SSE reader — lifted out of
  `js/vergeml-folders.js`, which now hands it the tree as the request body
  and takes the tree block back as the draft. Every class the Folders spec
  asserts is unchanged; the four Folders tests pass on it. Its CSS moved
  to `css/vergeml-talk.css` (+ rtl; `tools/rtl.mjs` knows it), enqueued by
  `vergeml_talk_assets()` in `core/guide.php` on both screens.
- `core/brief.php` (new): the session option `vergeml_brief_session`; the
  catalogue (`vergeml_brief_catalogue()`, cached an hour against the
  described count and the brief: kinds, the 25 most used tags, how many
  pictures carry each word of the brief, plurals folded onto singulars);
  the opener built from it, free, written into an empty session by
  `vergeml_brief_boot()`; routes `GET/POST brief/session`, `POST brief/token`
  (bound to the catalogue, minted through `vergeml_guide_mint()` which the
  guide's token now shares), `POST brief/turn`, `POST brief/test`,
  `POST brief/adopt`, `POST brief/discard`. All `manage_options`.
- **Test on 5 pictures**: one from each of the largest folders, then the
  newest described — the same five every time; each described straight
  against `/describe` with the draft as the profile; the answers held in
  the session, nothing written. `vergeml_brief_describe` (a filter) lets a
  suite stand in for the service.
- **Adopt = re-describe**: the draft becomes the site profile; the held
  answers are written through `vergeml_ai_index_store()` (factored out of
  `vergeml_ai_index_step()`, which now calls it) so the stamp reads the new
  prompt; with none held, one picture is described (1 credit); then the
  stale run starts with reason `brief_changed`. `vergeml_ai_run_nudge()`
  gained the `vergeml_ai_run_should_nudge` filter so a suite can start a run
  without a pass being spawned.
- `js/vergeml-brief.js` (new): the tab. The brief panel on the right — the
  draft in ink, the brief in use in grey under it, `Re-describe N pictures
  with this brief` / `Test on 5 pictures · 5 credits` / `Discard the draft`,
  the run watched after adopting with Stop beside it. The test's answer
  turn renders as five before/after rows under the assistant's lines.
- Service: `lib/stream-split.ts` (the say/block splitter, factored out of
  the guide's turn), `lib/stream-route.ts` (token, origin, body, licence,
  SSE — shared by both stream routes), `lib/brief-stream.ts` (the rules,
  the prompt, the `brief` block: whole brief as lines, at most 500
  characters joined, two or three chips), `app/api/ai/brief/stream/route.ts`,
  the `/v1/brief/stream` rewrite. Metered as `brief`, 10 per turn.

**Task 20 — Search.** `core/search-try.php`: `GET search-try?s=` runs the
word pass the library runs (every word has to match, in any of seven
fields; per-word counts; "with this plugin off" is WordPress's three
columns) and the meaning pass (`vergeml_meaning_search()`, which now leaves
its scores in `$GLOBALS['vergeml_meaning_meta']['scores']`), and answers per
hit with the fields that held the word, a snippet, the score, and which
words of the query the picture carries. `js/vergeml-ai.js` draws it.

**The screen.** `core/ai-screen.php` (new) holds the page and its assets;
`core/ai.php` keeps the engine and the menu entry. Three tabs by
`&tab=`, the facts line under the title, the Describe tab's run in the Move
shape (`Describe 24 new pictures` / `Describe · nothing new`, `Describing 9
of 24` + Stop, done as one line), the "What is written where" table with
counts, the two switches saving on change. `js/vergeml-ai.js` rewritten;
`js/vergeml-ai-background.js` puts the background run's progress in the
button too and hands the face back when it ends.

**Copy.** `docs/superpowers/copy/2026-09-06-phase-5-copy.md`, 21 rows;
`tests/tree/copy.mjs` gained ten struck strings and four kept ones. Four
"image" strings outside the screen went with it: the rail's credits line
(every screen), the Licence screen's, two dashboard to-do titles, the menu
line.

## Evidence

- Service: `npx tsc --noEmit` clean; `npx vitest run` → **357 passed, 12
  skipped** (26 files; the new `brief-stream.test.ts` 7 and
  `brief/stream/route.test.ts` 7 among them). Prod: deployment 29 s old at
  the check, `OPTIONS /v1/brief/stream` from the box's origin → 204 with the
  allow header (it had none before the rewrite landed).
- `node tools/verify.mjs brief` → **40/40**; `search-try` → **13/13**;
  `guide` **30/30**; `journey` **62/62**; `folders-version` **24/24**;
  `copy` **35/35** (local); `node tools/rtl.mjs --check` up to date;
  `php -l` on the box: every changed file parses (PHP 8.5.4 there; nothing
  above 7.4 syntax was used).
- `npx playwright test shell.spec shots.spec folders.spec library.spec` on
  the box → **37 passed, 1 skipped** (the Folders walk), 10.9 m. The shell
  sweep now covers `ai`, `ai-how` and `ai-search`; nothing pinned.
- `BRIEF_WALK=1 npx playwright test brief.spec` → the walk **passed** (1.4
  m): the reply streamed with Stop in the arrow, the draft landed on the
  right (224 of 500 characters), `Test on 5 pictures · 5 credits` went into
  the log before the request, five before/after rows came back, "Nothing is
  written until the brief is used", Discard dropped the draft and its
  controls. The Search test (`cosy winter evening`: 0 by word, the line
  says "cosy" in 0, "winter" in 23, "evening" in 7; 60 shown of 340 by
  meaning; each hit with its words or "No word from the query") **passed**
  (34 s) once `tests/ui/fixtures.mjs` logged in twice when the first try
  did not land — the first login of a run had twice typed the password into
  the username box, WordPress's own focus script firing between the fills.
- Mutation checks: see the section below.
- Screenshots: the three tabs and the walk's two, shown in the conversation.
- **Credits: 10 spent, both said before they ran.** 25,981 at the start;
  5 for the mock's real test rows; 5 for the walk's test. The balance read
  **25,971** at the end (`vergeml_ai_refresh_credits( true )` on the box).
  The walk's token and its one turn did not move it: the service meters
  guide and brief turns against the daily limits (`meterCall`, 10 each),
  not against the balance — which is not what the 2026-09-05 note "20
  credits a visit" assumed. The How tab was opened by the shots and shell
  specs many times at no cost.
- Box left as found: profile, catalogue rows, run state and both sessions
  restored by the suites; the session-only admin `vgml-p5` removed.

## Mutation checks

One per new suite, each shipped to the box, run, and put back (the real
build re-shipped and verified after):

- **brief, the test writes its rows as they arrive** (`vergeml_ai_index_store()`
  called inside `vergeml_brief_test()`): **38/40** — red at *C3 the catalogue
  rows are exactly as they were* and *C4 the stamp did not move* (the stamp
  read `stubhash…`).
- **brief, adopt saves the brief but starts no run** (`vergeml_ai_run_start()`
  dropped from `vergeml_brief_adopt()`): **38/40** — red at *D5 the stale run
  started, and says why* (`active: false`) and *D6 the answer carries the run*.
- **search-try, any word matches instead of every word** (`AND` → `OR` across
  the terms): **12/13** — red at *B1 a word nobody has makes the pair match
  nothing*.

The real build back: brief 40/40, search-try 13/13.

## What Phase 6 inherits

- `js/vergeml-talk.js` is the conversation for any screen that wants one:
  `vergemlTalk.create( opts )` with the hooks documented at its head. Folders
  and the brief are its two callers.
- `vergeml_ai_index_store( $id, $described, $apply_alt )` is the one way a
  described answer is written.
- `vergeml_guide_mint( $licence, $summary )` mints a token bound to any
  context; the service verifies the hash of whatever was minted for.
- The Describe tab's table is built by `vergeml_ai_screen_counts()`: counted
  from the tables, never summed from another screen's figures.
- `tests/tree/copy.mjs` is still where a struck string goes.

## Decisions taken here, for Nathan to overrule

- **The catalogue is cached for an hour** (a transient keyed on the
  described count and the brief). The one expensive read is the tags
  column, once; on a 20,000-picture library that is a second, not per visit.
- **The test's five pictures are one per largest folder, then newest.**
  The mock said "5 pictures from Apparel"; five from one folder show the
  brief's effect on one kind of picture only.
- **Adopt with no held test describes one picture first** (1 credit) so
  the stamp moves; the alternative was a sweep that could never start.
- **A changed draft drops the held answers**; they were for another brief.
- **The How tab is `manage_options` only**; an editor sees Describe and
  Search. The tab link is not drawn for them.
- **The Search tab's rail carries no example counts** ("leather": 25 by
  word) as the mock did — those were the box's; a site has its own query.

## Found, not done

- The alt-text and language items under "Still Nathan's".
- `core/journey.php`'s `Finished. Refreshing the numbers…` (the dashboard's
  own run line) was left; the copy table says so.
- The `tests/ui/shots.spec.mjs` un-plant from Phase 4's list is done: a
  planted session is reset after the screenshot.
- Everything on the Phase 4 handoff's list that this phase did not touch
  still stands: the `confirm()` dialogs, `core/help.php`, the mock's five
  invented Library settings sections, and the Phase 3 items behind them.

## Phase 6 opener, to paste

```
Read docs/handoffs/2026-09-06-phase-5-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 6, task 21: similar pictures as a pair view (dimensions,
size, date, where used; keep both, keep this one, open both), nothing binned
while in use unless the use is rewritten. A mock in the shell's grammar with
the box's real pairs comes first and I approve it before any code.
Stop points: the mock, before code. Any visible shape the mock does not show.
Anything that bins a file or rewrites a post is walked on a copy first.
Gates: tests/ui/shell.spec.mjs, tests/ui/shots.spec.mjs, tools/verify.mjs
copy, health, journey and guide still green. Say the cost of any test that
spends credits before it runs; never open the Folders screen on an empty
guide session.
End with a handoff in docs/handoffs/.
```
