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

1. Mock: `docs/superpowers/mocks/2026-09-05-folders-screen.html`, in the
   shell's own stylesheet, with the box's real folders, the real summary
   numbers, the copy above, the Move in its three states, and one settings
   screen collapsed. Nathan judges it before any code.
2. Service: session token, stream route, prompt change, evals.
3. Plugin: tree component and version stamp, then the Folders screen.
4. Shell flow, settings collapse, removals, copy pass.
5. Suites and the box walk.
