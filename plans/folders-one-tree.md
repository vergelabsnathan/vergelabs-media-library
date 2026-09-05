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

Phase 4 — shell, settings, removals, copy, half a day.

14. Shell in normal flow: remove every sticky/fixed rule; save bars at the
    end of their forms.
15. Settings screens: sections under chevrons, closed by default, the open
    one remembered per person.
16. Removals: the guide's states and screens, the wizard beside the chat
    card, the "Recently described" strip, the size-counts card.
17. Copy pass on every screen to the standard: facts, numbers, lists with
    the brand-mark bullet; conversational phrasing only in conversations.

Phase 5 — AI screen, 2 days, mock first.

18. Mock of three tabs (Describe, How it describes, Search) in the shell's
    grammar, Nathan approves before code.
19. "How it describes": the site brief as a conversation using the Folders
    conversation component; the brief in bullets on the right; "Test on 5
    pictures" (5 credits, say so) re-runs with the brief; corrections update
    the brief. The brief is the describe prompt's context.
20. Search tab: what search matches; try a query and see why each hit
    matched.

Phase 6 — similar pictures, 1 day.

21. A pair view: dimensions, size, date, where used; keep both (retires the
    pair), keep this one (bin the other, rewrite its uses to the kept one),
    open both. Nothing binned while in use unless the use is rewritten.

Phase 7 — grid and list, 1 day.

22. A table of every action against both modes (open, select, bulk move,
    drag to folder, folder filter, counts, search, sort, keyboard). Fill the
    gaps in `js/vergeml-tree.js` and the list hooks. Nathan sees the table
    before code.

Phase 8 — migration page, half a day.

23. One card per source the importer reads (FileBird, HappyFiles, Folders by
    Premio, Real Media Library, Wicked Folders, WP Media Folder, CSV):
    detected or not, folder count on this site, one button. Names as text;
    logos only on Nathan's say.

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
