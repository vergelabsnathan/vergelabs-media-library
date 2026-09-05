# Folders: one conversation, one tree — design

Approved by Nathan on 2026-09-05. Replaces the guided-sorting surface of
2026-09-04 (`2026-09-04-guided-sorting-design.md`). What stays from that
work: the service's guide rules and schemas, the evidence matcher
(`core/filing.php`), the resumable Move and its undo, the 25-turn cap.

## Why the old surface is replaced, not patched

- It is a state machine (library → proposal → shaping → review → applying
  → done) with a screen per state and a steps strip narrating the machine.
- Every turn is a WordPress REST round trip: one PHP worker held for ~5 s,
  the answer arriving whole, the screen re-rendering. The first proposal
  takes ~38 s behind "Reading the library…". Streaming cannot pass through
  `wp.apiFetch`.
- "The endpoint on screen" was built as data panels. The endpoint is the
  tree.
- There are two trees: the media library's folder panel reads the taxonomy;
  the Sort screen reads a summary taken at session start and matches draft
  folders to live ones by lowercased name. A rename in the library reads as
  a removal plus an addition in the draft.
- The shell's rail is sticky at viewport height while WordPress's menu on a
  long-menu site is not, so the two rails scroll apart.
- The copy is vague and conversational where it should state facts.

## 1. The Folders screen

Nav entry: **Folders**, sub "Say it, edit the tree, press Move". Page slug
stays `media-sort`. Two columns on one page in normal flow: the
conversation left, the tree right, Move under the tree. Nothing else: no
steps, no summary, no diff panel, no evidence rows.

**Conversation.** Opens by itself when the library is described: two
sentences on what was read, then one question. Every reply streams word by
word. One question per turn. At most three suggestion chips for the current
question under the input; typed text always allowed. Persisted in the
session; reload shows the same conversation. When the assistant-turn cap is
reached the input's own label says so ("25 of 25 turns used. Edit the tree
by hand, or start over.").

**Tree.** Today's folders in grey, the draft in ink. Added folders slide in
and removed ones fade at the moment the reply names them. Rename in place,
drag to reparent, remove — no `window.prompt`. A hand edit appears in the
conversation as something the person said ("Moved Boots under Shoes") so
the assistant can react. Hover a folder: how many pictures would land there
and three of them. That is all the data on the screen.

**Move.** One button, three states, in place:

| state | button | elsewhere |
|---|---|---|
| resting | `Move 354 pictures` (disabled with label `Move · no changes yet` until the draft differs) | a folder the Move removes carries, on the folder itself, `removed · 15 pictures go to Apparel` |
| moving | `Moving 120 of 354` + `Stop` beside it | the folders in the tree fill as pictures land |
| done | `Undo until tomorrow 14:20` | one line in the conversation: `354 pictures moved into 12 folders. 287 stayed where they were.` |

Undo is the existing 24-hour undo. Stop leaves what moved and says so in
the same one-line form.

## 2. One tree component

`js/vergeml-tree-view.js` renders the folder tree for both the media
library's folder panel and the Folders screen. Same data, same markup, same
interactions. The Folders screen adds the draft overlay.

- The draft is keyed by `term_id` for existing folders; new folders have
  `term_id: null` and a client key. Matching by name is gone.
- A folders version stamp, option `vergeml_folders_version` (integer), is
  bumped on `created_term`, `edited_term`, `delete_term` for the folder
  taxonomy, on a reparent, and when a Move completes or is undone.
- `GET /vergeml/v1/folders/version` returns `{ version }`. Every open
  surface polls it every 5 s and on `visibilitychange`; on a change it
  re-reads the tree. A draft rebased onto a changed tree keeps its edits by
  id; a draft folder whose live folder was deleted becomes a new folder.

## 3. The shell in normal flow

`.vgml-shell-nav` loses `position: sticky`, `height` and `overflow`. The
page scrolls as one, WordPress's menu untouched, content taking the height
it needs. No element in the shell is sticky or fixed; the save bar sits at
the end of its form. Audit and remove the other sticky rules in
`vergeml-shell.css`.

## 4. Feedback contract

1. A control says what it does, with the number. A control that cannot be
   used says why in its own label in a few words, or is not shown. No
   explanatory line beneath a button.
2. Progress happens where the work happens, in the control that started it
   and in the thing changing.
3. Done is one factual line in the place the person was looking.
4. Confirmation is the button itself. Anything a Move removes is written on
   the folder that goes, with where its pictures end up. No dialogs.

The assistant's own actions follow the same rules: changed folders animate
as it names them; its sentence says what changed in numbers.

## 5. Copy standard

Every sentence states a fact, or an action with its consequence. Numbers
where they inform, none where they decorate. No "Let's", "Shape it
together", "You are here", no smileys, no questions the person did not
ask. The assistant speaks the same way: short, specific, names folders and
counts, one question. The tree block carries one line per folder stated as
evidence (`footwear · 88 pictures`), not prose.

| now | after |
|---|---|
| Shape it together. You are here — say it, or set folders aside | Say what to change, or edit the tree. Nothing moves until you press Move. |
| A first proposal. Say what you want, or wait for a suggestion | I read 641 pictures. 175 are landscapes, 127 are portraits, 69 are desk and phone shots. Folders by subject, by use, or both? |
| Done — undo is one click for 24 hours | 354 pictures moved into 12 folders. 287 stayed where they were. Undo until tomorrow 14:20. |
| Reading the library… | Reading 641 pictures. The first folders appear as I go. |

The pass covers every screen, not only Folders.

## 6. Removals

The guide's states and screens, the steps strip, the wizard beside the chat
card, the summary and diff panels, the old chat box. The dashboard's
"Recently described" strip. The "Size counts" card and its switch in
`core/instrument.php` (the numbers never leave the site; there is no
endpoint). The snapshot builder may stay dormant.

## 7. Settings collapsed

On the three settings screens every section shows its title and a chevron,
closed by default. Opening one is remembered per person (`localStorage`).
The setting that matters most on each screen is in the first section.

## 8. Service: the stream

- `POST /v1/guide/session` — body `{ key, site }` from the plugin
  server-side, as every `/v1/*` call today. Returns
  `{ token, expires_at }`: a signed token (HS256, service secret), one hour,
  claims `licence_id`, `site`, `summary_hash`. Metered like a guide turn.
- `POST /v1/guide/stream` — `Authorization: Bearer <token>`, body
  `{ conversation, tree, input }`. Response `text/event-stream`:
  `say` events with text deltas, one `tree` event with the validated tree
  block, `done` with usage, or `error` (`provider_busy` with a retry
  seconds value, `turn_cap`, `bad_token`). CORS allows exactly the `site`
  origin in the token. Same daily site limits as the guide route today.
- Prompt: talk first, then the tree in a fenced ` ```tree ` block at the end.
  Zod validates the block; a bad block gets one silent re-ask for the block
  only; the words already streamed stay.
- Model: Sonnet 5, `ANTHROPIC_GUIDE_MODEL` override, through OpenRouter as
  everything else.
- The plugin persists each finished turn: `POST /vergeml/v1/guide/turn`
  with `{ said, say, tree }` into the session option. The browser never
  holds the licence key.

## 9. Testing

- `evals/guide/*.json` run against the stream route (assemble the SSE, then
  score as today).
- `tests/ui/folders.spec.mjs` replaces `guide.spec.mjs`: walks the screen on
  the box with `guide_walk=1`, screenshots resting, moving and done.
- `tests/tree/folders-version.php`: the stamp bumps on create, rename,
  delete, reparent, Move, undo; the draft survives a rename by id.
- `tests/ui/shell.spec.mjs`: the rail's computed position is `static`; the
  document grows with content; no element in `.vgml-shell` is sticky or
  fixed.
- Settings: sections closed by default; the open one remembered.

## 10. Order of work

The order and the gates live in `plans/folders-one-tree.md`. The mock was
judged and approved on 2026-09-05 after three rounds; §11 records what those
rounds added.

## 11. Approved additions, 2026-09-05 afternoon

**The composer.** A real one: grows with what is typed, the send arrow inside
it at the bottom right, Enter sends, Shift+Enter breaks a line, the arrow
becomes Stop while a reply streams. Suggestion chips stay attached to the
assistant's last message, above the composer.

**Two methods, one draft.** A segmented switch at the head of the left
column: Conversation | Rules. It changes only the left column; the tree and
Move are shared; switching never resets the draft. An applied rule writes
one line into the conversation ("You applied By kind: 4 folders, 265
pictures") so the conversation is the full history of the tree and the
assistant can react to a rule as it reacts to a hand edit. Not tabs across
the page: tabs say different content; this is the same result built two ways.

**Rules.** Deterministic, instant, no model, no credits; the tree and Move
answer as an option changes. Four rules, one open at a time, each with a
folder count beside it:

- By kind: one folder per kind of picture. Scope: only unfiled pictures
  move (default) / every picture moves and today's folders are removed.
- By month and year: date source (upload date / date taken, upload date
  when missing), levels (year then month / one folder per month), scope.
- By subject: smallest folder (stepper, default 10), levels (one / two),
  scope.
- Into today's folders: when nothing fits (stay unfiled / a folder named
  Unsorted), how sure (only sure matches / close calls too).

The preview under the open rule is a list: folders made, pictures that move,
pictures that stay and why, what happens to today's folders. The librarian's
schemes are these rules; the Sort into folders screen goes.

**The tree at scale.** Two states, a small switch at the tree's head:

- Changes (default while a draft exists): only the folders that change,
  grouped under their parent path.
- All folders: top-level folders collapsed to one row with subfolder count
  and picture count; a branch holding a change opens by itself; a collapsed
  branch holding changes carries a mark ("2 changes"); unchanged siblings
  inside an open branch fold into one line ("8 more folders, unchanged").

A find box appears past twenty folders. Hover on a folder: pictures after
Move, where they come from, three of them.

**Scannable.** Every statement with more than one fact is a list. The bullet
is the brand mark, the rail's rounded square, at 7px in the accent colour.
Applies to the assistant's replies, rule previews, hover cards, the Move
states, settings notes, and every screen in the copy pass.

**Conversational only in the conversation.** The assistant may ask a
question. Everything functional is fragments: "265 pictures move", "3 stay
unfiled: no kind", "Cannot be pressed".

**Copy, as approved on the mock** (verbatim where it matters): nav "Folders ·
Build the folder tree"; facts line "641 pictures · 19 folders · 268 in no
folder · described yesterday 13:52"; kicker "3 of 25 turns"; assistant
opener "641 pictures, described yesterday." then a list, then "Folders by
subject, by use, or both?"; replies "In the draft:" then a list then the
question; hand edit "You · edited the tree / Moved Edison bulb lighting under
Workspace"; composer "Describe the change" with "Enter sends · Shift + Enter
for a new line"; tree head "Folders · 19 now, 15 after Move"; removed folder
"removed · 39 pictures go to Landscape and nature"; moved folder "moved from
Objects, by you"; Rules kicker "Uses no credits".

**The nine pending items**, decided 2026-09-05; the plan carries the tasks:

1. Grid and list aligned: an action table for both modes, gaps filled,
   both modes walked by Playwright. Table before code.
2. Migration page: one card per importer source; names as text, logos only
   on Nathan's say (trademarks).
3. Similar pictures: a per-pair view (dimensions, size, date, where used)
   with keep both / keep this one / open both; nothing binned while in use
   unless its uses are rewritten.
4. Dashboard score: four progress rows with their own counts; no total.
5. To-do rows only with count > 0 and a runnable action; a blocked action
   shows once with its blocker.
6. "Work out the folders" becomes "N files in no folder · Put them in
   folders", pointing at Folders. Files means files.
7. Size counts: made true (a service endpoint receives eight integers once
   a day), moved to Library settings as "Share library counts", off by
   default, copy in three bullets naming the numbers and what never goes.
8. AI screen: three tabs. Describe (the run, what is written where,
   credits). How it describes (the site brief as a conversation with the
   Folders conversation component; the brief in bullets on the right is the
   describe prompt's context; "Test on 5 pictures" re-runs and corrections
   update the brief). Search (what search matches; try a query and see why
   each hit matched). Mock first.
9. Demo mode leaves the AI screen; it sits on the Licence screen only while
   no key is present, labelled "Demo mode", never "Try it free".
