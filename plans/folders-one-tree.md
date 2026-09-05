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

Phase 0 — half a day, first, so the dashboard stops lying.

1. Dashboard score → four progress rows (alt text, described, filed,
   copies), each count + bar + one action; no total. `core/journey.php`.
2. To-do rows show only with count > 0 and an action that can run now; a
   blocked action shows once with its blocker. `vergeml_journey_todo()`.
3. To-do "Files in no folder" reads "N files in no folder", action "Put
   them in folders", target the Folders screen. Copy says files.
4. Demo mode: remove the "Try it free" row from the AI screen; add it to the
   Licence screen, shown only while no key is present, labelled "Demo mode".
5. Size counts: service `POST /v1/counts` (eight integers + locale, keyed by
   licence, one row a day); plugin sends once a day when opted in; the card
   leaves the dashboard; the switch sits in Library settings as "Share
   library counts", off by default, copy in three bullets.

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
