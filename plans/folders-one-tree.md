# Folders as one conversation and one tree — and the nine things pending

Ticket: `tickets/folders-one-tree-and-the-nine.md`. Spec:
`docs/superpowers/specs/2026-09-05-folders-screen-design.md` (sections 1–11).
Mock: `docs/superpowers/mocks/2026-09-05-folders-screen.html`. Read all three
before the first task; the spec is the contract, this file is the order and
the gates.

## Problem

The guided-sorting surface built on 2026-09-04 is a state machine with a
screen per state, a request/response text box through WordPress REST, and
data panels standing in for the tree. Nathan walked it on the box and could
not use it: the assistant's tree landed in a state he had to decode, the
Sort screen and the media library's folder panel did not agree with each
other, the copy was vague and chatty, and the shell's sticky rail scrolled
apart from WordPress's menu. Around it, nine smaller things on the dashboard,
duplicates, AI and import screens are wrong in the same way: composite
numbers, rows with nothing behind them, a textarea where a conversation is
needed, copy that says folders when it means files.

## User story

As the person who owns a library of thousands of pictures, I want one screen
where I say what folders I want, or pick a rule, and watch the tree answer,
then press one button — and I want every other screen in the plugin to tell
me a fact I can act on, in a line I can scan — so that organising the library
is something I do in minutes, not something I decode.

## Decisions taken

- **One screen, two methods, one draft.** Conversation and Rules are a
  segmented switch at the head of the left column; the tree and Move are
  shared; switching never resets the draft; an applied rule is one line in
  the conversation.
- **The browser streams from the service.** A short-lived token minted by
  the plugin with the licence key; SSE from `/v1/guide/stream`; the browser
  never holds the key; each finished turn is persisted to WordPress. The
  reason is five PHP workers and no streaming through `wp.apiFetch`.
- **One tree component** for the media library panel and Folders, keyed by
  `term_id`, with a folders version stamp polled by every open surface.
- **Tree at scale**: changes first while a draft exists; the whole tree by
  branch, collapsed at the top level, changed branches open, unchanged
  siblings folded into one line; a find box past twenty folders.
- **Normal page flow.** Nothing sticky or fixed in the shell. WordPress's
  menu untouched.
- **Copy standard and feedback contract** apply plugin-wide (spec §4, §5,
  §11).
- **Sonnet 5 stays the guide model**, through OpenRouter.
- **The librarian's schemes become the Rules tab**; the Sort into folders
  screen goes.
- **Logos of competitors**: names as text by default; logos only on Nathan's
  explicit say, because they are trademarks and the directory rejects them.
- **Size counts**: kept, made true. A sender on the service; the switch moves
  to Library settings; the copy names the eight numbers.

## Out of scope

- The date/type schemes' internals; they are reused as rules, not rewritten.
- Multi-user editing of one draft; the version stamp is the hook for it.
- Tag creation from the guide's tags (already landed 2026-09-04).
- Dark mode. The admin is light.

## Context

- Plugin: `core/guide.php`, `js/vergeml-sort.js`, `css/vergeml-sort.css`
  (replaced); `js/vergeml-tree.js` (the media library panel; keep, refactor
  its tree rendering out into the shared component); `core/filing.php`,
  `core/librarian.php` (schemes reused as rules; the apply and undo stay);
  `core/journey.php` (dashboard score, to-do, size counts card);
  `core/instrument.php` (the counts); `core/ai.php` (AI screen, demo-mode
  row); `core/health.php` + `js/vergeml-health.js` (duplicates, related
  groups); `core/import.php`, `core/admin-menu.php` (importer sources);
  `css/vergeml-shell.css` (the rail's sticky rules at lines ~138, 863,
  1229, 2189, 2396).
- Service: `lib/guide.ts`, `app/api/ai/guide/route.ts`, `lib/limits.ts`;
  `evals/guide/*.json`; `next.config.mjs` rewrites for `/v1/*`.
- Box: `46.225.66.194`, `/var/www/wp`, plugin at
  `wp-content/plugins/vergelabs-media-library`, service on `127.0.0.1:3100`.
  The deploy (`tools/deploy.mjs`) excludes `tests/`, `plans/`, `tickets/`,
  `docs/`, `tools/`: run a PHP suite on the box from `/tmp` with
  `wp eval-file`, as `tools/verify.mjs` does.
- Real numbers used in the mock, from the box on 2026-09-05: 641 pictures,
  19 folders, 268 unfiled; unfiled by kind photo 240, illustration 13,
  screenshot 6, diagram 6, none 3; the evidence matcher's dry run files 0 of
  the unfiled (265 below floor, 8 margin, 15 gated).

## Tasks

**Model per phase.** Phases 1, 2, 3, 5, 6 and 7 (the stream and token, the
shared tree component and version stamp, the Folders screen, the AI screen's
conversation, the similar-pictures flow with post rewrites, the grid/list
alignment) want Fable 5.1: architecture, streaming, a shared component, or
data that can be lost. Phases 0, 4 and 8 (dashboard fixes, shell flow and
settings and copy pass, migration cards) can run on Opus 5.1 or Sonnet 5
under the Opus profile: one task at a time, a check-in after each, gates
after every task. The session states its model at the start of the phase and
follows the profile in the global rules.

Phase 0 — half a day, first, so the dashboard stops lying. Written for the
Opus profile: each task carries Files, Behaviour, Proof, Mirror, Copy, Do not.

1. **Four progress rows replace the score.**
   - Files: `core/journey.php` (`vergeml_journey_score()` and the dashboard
     band that renders it), `css/vergeml-shell.css` for the row style.
   - Behaviour: four rows in this order: Alt text, Described, Filed, Checked
     for copies. Each row: label, "N of M", a bar at N/M, one action link.
     A row at M of M shows no action. No total, no percentage, no weights.
   - Proof: `tests/tree/journey.php`: four rows present; no element with the
     old score; each row's N ≤ M; a full row has no action. Mutation check:
     make the renderer print a total and the suite goes red.
   - Mirror: the bar is `.vgml-import-bar` + `.vgml-import-fill` in
     `vergeml-shell.css`; a row is `vergeml_pg_row()` in `core/ai.php`.
   - Copy: labels "Alt text", "Described", "Filed", "Checked for copies";
     count "412 of 641"; actions "Write alt text" (AI screen), "Describe the
     rest" (AI screen), "Put files in folders" (the Sort page today, Folders
     after Phase 3), "Check for copies" (Duplicates).
   - Do not: add an option; add a query beyond the helpers the score already
     calls (Gate 5); write any sentence under a row.

2. **To-do rows only when there is something to do.**
   - Files: `core/journey.php`, `vergeml_journey_todo()`.
   - Behaviour: a row appears only when its count > 0 and its action can run
     now. A row whose action is blocked (no key and demo mode off) appears
     once, with the blocker as its line, without a count. The order of rows
     is unchanged.
   - Proof: `tests/tree/journey.php`: with alt text complete, no alt-text
     row; with no key and demo off, the describe row is present once and its
     line names the blocker; with 268 unfiled, the folders row shows 268.
     Mutation check: drop the count filter and the suite goes red.
   - Mirror: the `blocked` handling already in `vergeml_journey_todo()`.
   - Copy: blocked line "Add a licence key or switch on demo mode first."
   - Do not: reorder rows; add a "nothing to do" row (the section hides when
     empty, as it does today).

3. **Files, not folders.**
   - Files: `core/journey.php`, the "Files in no folder" item.
   - Behaviour: the title carries the number; the action names what happens
     to the files; the link goes to the Sort page today and Phase 3 re-points
     it to Folders.
   - Proof: `tests/tree/journey.php` asserts the two strings below.
   - Mirror: the item's own array.
   - Copy: title "268 files in no folder" (`%s files in no folder`); action
     "Put them in folders".
   - Do not: mention folders in the action; keep "Work out the folders"
     anywhere.

4. **Demo mode moves to the Licence screen.**
   - Files: `core/ai.php` (remove the "Try it free" row), `core/licence-page.php`
     (add the row), `js/vergeml-ai.js` if the checkbox id is read there.
   - Behaviour: the AI screen has no demo-mode control. The Licence screen
     shows one, under the connect controls, only while no key is present.
     With `VERGEML_AI_MOCK` defined the control is on and disabled and its
     line says so. The option written is the same `mock` setting as today.
   - Proof: `box-fix.yml job=connect-check` renders the Licence screen with
     and without a key: the row is present without, absent with.
     `tests/ui/shots.spec.mjs` screenshots both; the AI screen has no
     `#vgml-ai-mock`. Mutation check: leave the row on the AI screen and the
     spec goes red.
   - Mirror: the "Or paste a key" section in `core/licence-page.php`.
   - Copy: kicker "Demo mode"; check label "Invent captions here. Send
     nothing, spend nothing."; forced variant "Demo mode is forced on by
     VERGEML_AI_MOCK in this site's configuration."
   - Do not: write "free" or "trial" anywhere; change the option key.

5. **Size counts made true and moved.**
   - Files: service `migrations/009_library_counts.sql`,
     `app/api/counts/route.ts` (+ `/v1/counts` rewrite), `lib/stripe.ts`
     store method; plugin `core/instrument.php` (send on refresh when
     opted), `core/journey.php` (card removed), `core/options-pages.php`
     (the switch in Library settings).
   - Behaviour: with the switch on, the plugin posts the snapshot
     `vergeml_stats_snapshot()` returns, once a day, with the licence key
     and site as every `/v1/*` call. The service upserts one row per
     licence per day. Off by default. The dashboard card is gone.
   - Proof: plugin `tests/tree/counts.php`: opted off sends nothing (mock
     `pre_http_request`); opted on sends exactly the snapshot's keys and no
     value that is a string from the database except the locale; the card
     is absent from the dashboard; the switch is present in Library
     settings. Service `tests/counts.test.ts`: a valid key stores a row and
     a second post the same day updates it; a bad key is 403. Mutation
     check: add a folder name to the payload and the plugin suite goes red.
   - Mirror: key auth and store pattern in `app/api/ai/guide/route.ts`;
     migration shape in `migrations/008_*.sql`; apply it on the prod DB
     file-by-file as `vgml_media` (see memory `the-watch`: `schema_migrations`
     is not usable there).
   - Copy: section "Share library counts"; switch "Send the counts"; three
     bullets: "Once a day: files, folders, how deep they nest", "Plugin,
     WordPress and PHP versions, and the site language", "Never a file name,
     a title, a folder name or a picture".
   - Do not: send anything the snapshot builder does not return; autoload
     the option; leave the old card or its copy anywhere.

Phase 1 — service, 1 day.

6. `POST /v1/guide/session` → signed token (HS256, one hour, claims
   licence_id, site, summary_hash). Metered as a guide turn.
7. `POST /v1/guide/stream` → SSE `say` deltas, one `tree` event, `done` /
   `error`. CORS for the token's site origin only. Prompt: talk, then a
   fenced tree block; Zod on the block; one silent re-ask on a bad block.
8. Evals run against the stream (assemble SSE, score as today).

Phase 2 — plugin tree, 1 day.

9. `js/vergeml-tree-view.js`: one tree component used by the media library
   panel and Folders; draft overlay keyed by `term_id`; changes-first and
   all-folders states; find box; collapse and auto-open rules from the spec.
10. Folders version stamp: option bumped on the taxonomy hooks, on reparent,
    on Move and undo; `GET /vergeml/v1/folders/version`; polling every 5 s
    and on `visibilitychange` in both surfaces.

Phase 3 — the Folders screen, 1 day.

11. `js/vergeml-folders.js` replaces `vergeml-sort.js`: switch, composer
    (grows, Enter sends, Shift+Enter newline, send becomes Stop while
    streaming), suggestion chips on the last assistant message, streamed
    turns, hand edits as messages, Move in its three states, undo.
12. Rules tab: by kind, by month and year, by subject, into today's folders;
    options per the mock; instant preview; an applied rule is one line in
    the conversation. The Sort into folders screen and its nav entry go.
13. `core/guide.php`: session token relay, turn persistence, summary,
    apply through the resumable re-filing; the 25-turn cap in the composer's
    label.

Phase 4 — shell, settings, removals, copy, half a day. Written for the Opus
profile: each task carries Files, Behaviour, Proof, Mirror, Copy, Do not.

14. **The shell in normal flow.**
    - Files: `css/vergeml-shell.css` (the five rules opening at 136, 862,
      1228, 2221, 2429, and the two the change drags in below),
      `css/vergeml-shell-rtl.css` (generated — run `node tools/rtl.mjs`, never
      edit it by hand), new `tests/ui/shell.spec.mjs`.
    - Behaviour:
      - `.vgml-shell-nav` loses `position`, `top`, `height` and `overflow`.
        The rail is a 250px column in normal flow; the page scrolls as one
        and the rail scrolls with it.
      - `.vgml-savebar`, `.vgml-shell .vgml-savebar`, `.vgml-shell
        .vgml-ds-savebar` and `.vgml-shell .vgml-pg-actions` lose `position`,
        `bottom` and `z-index`. They keep the top rule, the padding and the
        background, and stay the last thing inside their form.
      - `.vgml-shell .vergeml-mime-type-list thead th` loses `position`,
        `top` and `z-index`.
      - `.vgml-health-bulk` (the Duplicates bulk bar) keeps its sticky. It is
        the one exception, taken by Nathan on 2026-09-05: the list it acts on
        runs to two hundred sets and a bulk control that scrolls away from
        what it controls is not used twice. Written into spec §3 and named in
        the gate, so it reads as a decision and not as a miss.
      - No other element under `.vgml-shell` computes to `sticky` or `fixed`
        on any of the nine screens.
      - Two rules the sticky was propping up, found by looking at the screen
        after the change and fixed in the same task: `.vgml-shell-body` goes
        from `align-items: flex-start` to `stretch`, so the divider and the
        rail's ground run the length of the page instead of stopping where
        the menu ends; and `.vgml-shell-list` goes from `flex: 1` to
        `flex: 0 0 auto`, so the rail's foot sits under the last menu item
        instead of being pushed to the bottom of a page four thousand pixels
        long.
    - Proof: `pnpm test:ui shell.spec` on the box, over the nine slugs of
      `tests/ui/shots.spec.mjs`. (a) The descendants of `.vgml-shell` whose
      computed `position` is `sticky` or `fixed` are exactly one,
      `.vgml-health-bulk`, and only on Duplicates; none on the other eight
      screens. (b)
      `.vgml-shell-nav`'s computed `position` is `static` and it does not
      scroll on its own (`scrollHeight - clientHeight <= 1`). (c) On File
      types the document is taller than the viewport, and scrolled to the
      bottom the rail's `getBoundingClientRect().top` has moved by the same
      pixels as the content's. (d) Every `.vgml-savebar` / `.vgml-pg-actions`
      inside a `<form>` has no visible element after it in that form.
      Mutation check: put `position: sticky` back on `.vgml-shell-nav`,
      deploy, the spec goes red at (a) — the rail is itself a pinned element,
      and (b) and (c) do not get to run because the assertions are in order;
      the real build back, green.
    - Mirror: every rule edited in `css/vergeml-shell.css` has a twin in
      `css/vergeml-shell-rtl.css` two lines lower — edit both.
      `tests/ui/shots.spec.mjs` is the spec's shape: the `SLUGS` map, `open()`
      from `fixtures.mjs`, `page.on( 'pageerror' )`.
    - Copy: none. This task changes no user-facing string.
    - Do not: touch `css/vergeml-tree.css`'s `.vgml-move` or
      `css/vergeml-gallery.css`'s lightbox overlay — neither renders inside
      `.vgml-shell`; remove a save bar's `border-top` or its background;
      change a property beyond `position`, `top`, `bottom`, `height`,
      `overflow`, `z-index` and the two the change drags in
      (`.vgml-shell-body`'s `align-items`, `.vgml-shell-list`'s `flex`);
      hand-edit `css/vergeml-shell-rtl.css` instead of regenerating it;
      unstick `.vgml-health-bulk`; open the Folders screen without planting a
      turn first.

15. **The settings sections under chevrons.**
    - Files: `core/options-pages.php` —
      `vergeml_print_media_library_options()` (2102–2512),
      `vergeml_print_taxonomies_options()` (2513–2966) and
      `vergeml_print_mimetypes_options()` (2967–3152);
      `css/vergeml-shell.css` (generated into the RTL twin); new
      `js/vergeml-settings.js`; `vergeml_acc_start()`, `vergeml_acc_end()` and
      `vergeml_acc_facts()` in `core/admin-shell.php`; the enqueue in
      `vergeml_medialibrary_options_page_scripts()`,
      `vergeml_taxonomies_options_page_scripts()` and
      `vergeml_mimetype_options_page_scripts()`; `core/instrument.php` (the
      counts section prints its own `<details>`); `js/eml-mimetype-options.js`
      (where a new row is cloned to); `tests/ui/shell.spec.mjs`.
    - Behaviour:
      - Each section on the three screens becomes `<details class="vgml-acc">`
        with a `<summary>`: the title, a `<small>` line stating that section's
        current values, and a chevron at the right that turns when open.
      - Every section is closed on first load. No `open` attribute is
        printed.
      - Opening a section adds its id to
        `localStorage['vgml-settings-open:<slug>']`; closing it removes it. On
        load exactly those sections open. Any number may stand open: nothing
        closes a section the person opened.
      - Library settings' first section is Order; Folders and categories'
        first section is Media taxonomies. Library settings has seven
        sections, not six: Phase 0's "Share library counts" is one of them,
        printed by `core/instrument.php` into the same accordion.
      - File types splits its one table into five sections by the kind its
        own filter already names — Images, Video, Audio, Documents, Other —
        each holding that kind's rows, one table per section. The kind filter
        above them opens the section it selects and closes the rest; a text
        filter opens every section that still has a row; with neither on, the
        sections are the person's own. A new type has no kind until it has a
        MIME type, so "Add a file type" clones into Other (`.vgml-ft-new`) and
        opens that section — `js/eml-mimetype-options.js` prepended into
        `.vergeml-mime-type-list tbody`, which with five tables would have put
        a copy in every one. The buttons and the save bar stay outside the
        accordion, at the end of the form.
      - The save bar stays outside the accordion, at the end of the form.
    - Proof: `pnpm test:ui shell.spec` on the box, a second describe block.
      On each of the three slugs: `details[open]` has count 0 on a first load
      with `localStorage` cleared; clicking two summaries leaves both open;
      reloading opens those two and no other; closing both and reloading
      opens none. On File types, choosing Video opens the Video section and
      closes the rest. Mutation check: print `open` on the first section and
      the "closed by default" assertion goes red.
    - Mirror: the mock's board 3
      (`docs/superpowers/mocks/2026-09-05-folders-screen.html`, lines
      3606–3655) is the markup and the summary's `<small>` line; `.mk-acc`
      there is the style to carry into `vergeml-shell.css` as `.vgml-acc`.
      `js/vergeml-health.js` is the shape for a small screen script.
    - Copy: section titles unchanged and in this order — Library settings:
      "Order", "Filters", "Scrolling", "Search", "Grid Mode", "Share library
      counts", "Media Shortcodes"; Folders and categories: "Media
      taxonomies", "Also show on media", "Options"; File types: "Images",
      "Video", "Audio", "Documents",
      "Other", the words its own filter already uses. The `<small>` line is
      the section's current values joined by " · ", from the fields it holds,
      no label and no sentence; on File types it is the count, "12 types · 9
      may be uploaded".
    - Do not: add a section beyond the five kinds, rename one, or move a
      field between sections; invent a File types section the kind filter
      does not already name; write a sentence under a summary; use a
      `<button>` and hand-rolled ARIA where `<details>` does the job.

16. **The removals.**
    - Files: `core/librarian.php` (`vergeml_librarian_page_legacy()`,
      `vergeml_librarian_assets()`), `core/folder-talk.php`
      (`vergeml_talk_card()`, `vergeml_talk_assets()`), `core/journey.php`
      (the "Recently described" strip, 1371–1398), `js/vergeml-librarian.js`,
      `js/vergeml-folder-talk.js`, `css/vergeml-librarian.css`,
      `css/vergeml-librarian-rtl.css`, the `.vgml-seen` block in
      `css/vergeml-journey.css`, the `vergeml-librarian` entry in
      `tools/rtl.mjs`'s `SHEETS`, `tests/ui/screens.spec.mjs` (the
      "sort into folders" describe, 59–88), `tests/tree/journey.php` (the new
      assertion, and a stale `vgml-seen` boundary in `jn_between`).
    - Behaviour:
      - The dead renderers and enqueuers go with their assets. None has a
        caller in either repo: `vergeml_librarian_assets()` and
        `vergeml_talk_assets()` are never hooked,
        `vergeml_librarian_page_legacy()` and `vergeml_talk_card()` are never
        called, and `vergeml_librarian_steps()` is reached only from the
        enqueuer. `vergeml_librarian_stage()` stays — `core/journey.php`
        calls it.
      - `vergeml_talk_routes()`, `vergeml_talk_apply()`,
        `vergeml_talk_undo()`, `vergeml_librarian_routes()` and everything
        the Move and the suites reach stay untouched.
      - The dashboard no longer renders the "Recently described" strip; the
        `recent` key its builder returns may stay dormant.
      - `tests/ui/screens.spec.mjs` loses the describe that skips itself on
        the pre-redesign talk panel's selectors.
    - Proof: `php -l` on every touched file; `node tools/verify.mjs guide`
      and `node tools/verify.mjs folders-version` unchanged;
      `node tests/tree/journey.php` on the box green with a new assertion
      that no `.vgml-seen` element renders; `pnpm test:ui shots.spec` nine of
      nine with no JavaScript error; `grep -rn` for each deleted symbol
      returns nothing outside its own removal. Mutation check: leave
      `.vgml-seen` on the dashboard and the journey suite goes red.
    - Mirror: Phase 3 deleted `js/vergeml-sort.js` and `js/vergeml-guide.js`
      the same way — the asset, its enqueue, its localize array and its
      stylesheet in one commit.
    - Copy: none removed from a live screen except the strip's own two
      strings, "Recently described" and "Open the library ↗".
    - Do not: delete `core/folder-talk.php` or `core/librarian.php`; delete a
      function a REST route or a suite still calls (grep before each);
      remove `VERGEML_TALK_STATE` or anything `vergeml_guide_*` reads; touch
      `js/vergeml-folders.js` or `core/guide.php`.

17. **The copy pass on every screen.**
    - Files: a new `docs/superpowers/copy/2026-09-05-phase-4-copy.md` first;
      then only the files that table names, across `core/journey.php`,
      `core/ai.php`, `core/health.php`, `core/import-ui.php`,
      `core/licence-page.php`, `core/options-pages.php`,
      `core/admin-menu.php`.
    - Behaviour:
      - The table is written first: one row per string, screen, file and
        line, the string today, the string after, and the rule from spec §5
        it fails. Nathan approves the table; no file is edited before that.
      - The pass covers each screen's page-level copy: the `h1`, the facts
        line under it, section titles and their value lines, button labels,
        save-bar notes, empty states and blocked lines. Per-control help text
        is out of this phase and goes into "found, not done".
      - Every replacement states a fact, or an action with its consequence.
        Numbers where they inform. No question the person did not ask, no
        "Let's", no "You are here", no sentence beneath a button.
      - Lists use the brand-mark bullet, as the Folders screen and Library
        settings' three counts lines already do.
    - Proof: `node tools/verify.mjs copy` — a new local suite,
      `tests/tree/copy.mjs`, one row per struck string with the rule it
      failed, plus three rows asserting the replacement is present so a row
      cannot be satisfied by deleting the message. `pnpm test:ui shots.spec`
      on the box, nine screenshots shown in the conversation;
      `node tools/verify.mjs journey` green. Mutation check: restore one
      struck string and its row goes red.
    - Mirror: the Folders screen's strings, written to the standard in Phase
      3, and spec §5's four-row table of before and after.
    - Copy: the table is the copy. It is written in this task and approved
      before any edit; nothing is written straight into a screen.
    - Do not: touch a string inside `js/vergeml-folders.js` or
      `core/guide.php` beyond the table's rows; rewrite a code comment;
      change a translator comment's placeholders; edit a string the table
      does not list.

Phase 5 — AI screen, 2 days, mock first. **Done 2026-09-06 (Fable 5.1),
one session.** Spec §12 records the four decisions; the handoff is
`docs/handoffs/2026-09-06-phase-5-handoff.md`.

18. Mock of three tabs (Describe, How it describes, Search) in the shell's
    grammar, Nathan approves before code. — Approved as
    `docs/superpowers/mocks/2026-09-06-ai-screen.html` (six boards, the box's
    numbers, one real 5-picture test).
19. "How it describes": the site brief as a conversation using the Folders
    conversation component; the brief in bullets on the right; "Test on 5
    pictures" (5 credits, say so) re-runs with the brief; corrections update
    the brief. The brief is the describe prompt's context. — `core/brief.php`,
    `js/vergeml-brief.js`, the conversation lifted into `js/vergeml-talk.js`
    (Folders uses it too); service `/v1/brief/stream` on the guide's token.
    Suites: `tests/tree/brief.php` (box, stands in for the service),
    `tests/ui/brief.spec.mjs` (the walk under `BRIEF_WALK=1`, ~25 credits).
20. Search tab: what search matches; try a query and see why each hit
    matched. — `core/search-try.php`; `tests/tree/search-try.php`.

Phase 6 — similar pictures, 1 day. **Done 2026-09-06 (Fable 5.1), one
session.** Spec §13 records the decisions; the handoff is
`docs/handoffs/2026-09-06-phase-6-handoff.md`.

21. A pair view: dimensions, size, date, where used; keep both (retires the
    pair), keep this one (bin the other, rewrite its uses to the kept one),
    open both. Nothing binned while in use unless the use is rewritten. —
    Mock `docs/superpowers/mocks/2026-09-06-duplicates-pairs.html` (the
    box's 22 real sets), approved. "Bin" is the plugin's own Set aside
    (`core/quarantine.php`): `core/health-keep.php` rewrites every page to
    the kept file at its nearest size, verifies with the usage scan's own
    extractor, then sets the other aside; a use it cannot rewrite leaves the
    file where it is and says so. Suites: `tests/tree/health-keep.php` (box,
    on two copies it makes), `tests/ui/health.spec.mjs` (the buttons, under
    `HEALTH_WALK=1`).

Phase 7 — grid and list, 1 day. **Done 2026-09-06 (Fable 5.1), one
session.** The table is `docs/superpowers/specs/2026-09-06-grid-list-modes.md`;
the handoff is `docs/handoffs/2026-09-06-phase-7-handoff.md`.

22. A table of every action against both modes (open, select, bulk move,
    drag to folder, folder filter, counts, search, sort, keyboard). Fill the
    gaps in `js/vergeml-tree.js` and the list hooks. Nathan sees the table
    before code. — Twenty-one rows, approved; seven fills in
    `js/vergeml-tree.js` and `core/taxonomies.php` (the list's modal walks the
    page's rows; the selection follows the view; the list re-fetches after a
    move; the title cell is armed as ours; Unfiled by URL, scoped to folder
    taxonomies; the filter bar follows the tree; on arrival the tree follows
    the list's URL). Walked by `tests/ui/modes.spec.mjs` under `MODES_WALK=1`,
    both modes, spending nothing.

Phase 8 — migration page, half a day. **Built 2026-09-06 (Opus 5).** Spec:
`docs/superpowers/specs/2026-09-06-import-cards.md`; mock:
`docs/superpowers/mocks/2026-09-06-import-cards.html`, four boards; handoffs:
`docs/handoffs/2026-09-06-phase-8-mock-handoff.md` (the mock) and
`docs/handoffs/2026-09-06-phase-8-task-23-handoff.md` (the build).

23. One card per source the importer reads (FileBird, HappyFiles, Folders by
    Premio, Real Media Library, Wicked Folders, WP Media Folder, WP Media
    Folders, CSV): detected or not, folder count on this site, one button.
    Names as text; logos only on Nathan's say. — **Done.** Built to the mock
    Nathan approved: found sources first, the six with nothing as one line,
    one button carrying its own number and its own progress, no preview step,
    the spreadsheet split into a card that reads in and a section that writes
    out, and the history named and dated. The plan/run defect was fixed inside
    the phase, so the card's outcome line is the number the import produces:
    the preview said 11 new and 3 merged where the import made 12 and merged 2,
    and now both say 12 and 2. Suites: `tests/tree/import-plan.php` (22/22,
    mutation checked) and `tests/ui/import.spec.mjs` under `IMPORT_WALK=1`
    (3 passed), plus the import shot in `shots.spec.mjs`, which had been
    photographing the spinner on every push.

## Validation strategy

**Gates.** 1, 2, 3 always (PHP 7.4 lint on a real binary; static checks; the
suites in `tools/verify.mjs`). Gate 4 (functional, Playwright on the box via
`box-ui.yml`) for every screen touched. Gate 5 (query budget): the folders
version poll is one option read, and the Folders screen's first paint must
not exceed the guide screen's budget as measured today; write the number
into the suite. Gates 6 and 7 for the new option, the removed screen and the
removed card: packaging and the upgrade path.

**Suites.**

- `tests/ui/folders.spec.mjs` replaces `guide.spec.mjs`: walks the screen on
  the box with `guide_walk=1`, both methods, screenshots resting, moving,
  done; asserts the composer's Stop while streaming; restores the session it
  finds. A planner call is ten describes' worth; say so in the log.
- `tests/tree/folders-version.php`: the stamp bumps on create, rename,
  delete, reparent, Move and undo; a draft survives a rename by id; a draft
  folder whose live folder was deleted becomes a new folder. Mutation check:
  stop bumping on rename and the suite goes red.
- `tests/ui/shell.spec.mjs`: no element in `.vgml-shell` computes to sticky
  or fixed; the document grows with content; the rail's position is static.
- `tests/tree/journey.php` extended: no to-do row with count 0; four
  progress rows and no total; the counts switch absent from the dashboard
  and present in Library settings; the sender posts eight integers and
  nothing that came from the database (assert on the payload keys).
- Service: `evals/guide` against the stream; a token test (expired, wrong
  site, wrong licence → 401); a CORS test (other origin → no header).
- Phase 6: a pair "keep this one" on a picture used in a post rewrites the
  post and bins the other; on an unused picture bins it; "keep both" never
  shows the pair again.
- Phase 7: the action table becomes `tests/ui/modes.spec.mjs`, every row
  asserted in both modes.

**Cost note.** The Folders walk spends planner calls; the AI screen's test
spends 5 credits per run. Both are said in the log line before they run.

## Risks

- **Streaming through Vercel.** Node runtime, `dynamic = 'force-dynamic'`,
  a `ReadableStream`; verify on prod by deployment age and a streamed byte
  count, never a status code.
- **Token leakage.** One hour, scoped to one site and licence; the browser
  gets nothing else. Revoke by rotating the secret.
- **Two trees during the transition.** Land Phase 2 before Phase 3 so the
  library panel and Folders never disagree in a shipped build.
- **PHP 7.4 floor.** No arrow functions, `match`, named arguments.
- **The watch commits to `main` nightly**: `git pull --rebase` before every
  push.
- **Context.** Each phase is a session. Write a handoff at the end of every
  phase; do not compact.
