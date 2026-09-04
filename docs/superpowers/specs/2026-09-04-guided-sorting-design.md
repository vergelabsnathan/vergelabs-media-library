# Guided sorting — design

**Date:** 2026-09-04 · **Status:** approved in conversation, spec for review · **Scope:** plugin (`vergelabs-media-library`) and service (`vergelabsmedia`)

## What it is

A full-screen, step-by-step way to arrive at a folder structure that fits the site, with an assistant that proposes, listens, asks back when something is unclear, and revises until the user confirms. Then the library is filed by evidence and the outcome reported honestly.

It sits beside the existing chat card and the "Sort into folders" wizard; neither is removed. It is optional. It only opens on a library whose pictures have been described (the catalogue records are what it reasons from).

Three rules govern every screen:

1. **Conversational.** The assistant speaks in short messages and always ends with either an updated draft tree or a question with two to four concrete choices.
2. **The endpoint is always visible.** From screen 2 onward the current draft tree is on screen and updated live. The user is never asked to imagine where this is going.
3. **Nothing happens without a confirm.** Every transition is a button whose label says what it does. The assistant never touches the library; it only edits the draft. The single action, filing, is the last click and it is undoable.

## The four screens

Reached from the Folders page and the dashboard as **Sort with a guide**. One admin page, `media-guide`, rendered full width inside the plugin's admin shell (no wp-admin sidebar, a top bar with the four steps and a way out).

### 1 · What you have

Purpose: tell the user what the describer saw, so the proposal makes sense.

Content, all computed from existing data (no model call):

- Total described pictures; when they were described; how many folders exist now and how they look (by year, by hand, by an earlier plan).
- The library's groups: the k-means clusters `vergeml_talk_groups()` already produces, each shown as a count, a two-line description made from the most common object classes and kinds in the group, and up to six thumbnails.
- Evidence lines the assistant will reason from later, computed with SQL over `filing` JSON and the `kind` column: share of records naming a brand, a size, an audience; the share of each kind (photo, screenshot, logo, diagram, document, illustration).
- A free-text field: "Tell it your goal first" (optional; becomes the session's `goal`).

Confirm: **This is my library, show me a proposal →**

### 2 · A first proposal

Purpose: two starting structures, drawn and explained.

- Two trees from one `/v1/guide` call in `propose` mode: *by what the pictures are* (follows the groups) and *by how the site publishes* (follows the goal, if given, else the assistant's reading of the site from the profile text). Each folder carries a one-line reason and an estimated count.
- Counts are real: for each proposed folder the plugin estimates membership by matching the folder's classes against the catalogue with the same class-match rule the matcher uses, on a sample of up to 2,000 records scaled to the library size.
- Both trees are drawn as indented trees with counts; a reason shows on hover or tap.

Confirm: **Start from the first** / **Start from the second** / **Neither, let me explain** (goes to screen 3 with an empty draft and the assistant's opening question).

### 3 · Shape it together

Purpose: converge on the structure. This is where the user spends the time.

Layout A: draft tree on the left, conversation on the right, the top bar shows step 3 and "version N · M folders · about K pictures placed".

- The tree is editable in place: rename, add a child, remove, drag to move, and a per-folder "what goes here" line (the plan's `matches`, editable). Every hand edit is written into the conversation as a message from the user ("I renamed Monitors to Displays") so the assistant reasons about it on the next turn.
- The conversation shows the assistant's messages with their choice chips, the user's messages, and a text field. Sending a message, or clicking a chip, is one turn.
- Each assistant turn returns the whole draft tree, not a diff; the screen redraws it and highlights what changed since the last version. The user can step back to any earlier version of the draft.
- Domain rules the assistant applies and explains (see *The assistant*): one nesting axis, extra axes become tags; folders need pictures behind them; audience splits need audience evidence; ask rather than guess.

Confirm: **This is the structure I want →** (goes to a review of the final tree with counts and the list of tags it will create, then **File N pictures now**).

### 4 · Apply

Purpose: do it, show it, and say what did not happen.

- Creates folders and tags through `vergeml_talk_apply()` (which already builds profiles from the plan and asks the planner to profile pre-existing folders), then runs the resumable re-filing job in slices, driven by cron and nudged as today.
- Progress bar and "you can leave this page". Live counts: filed, and left where they were by reason (did not fit, too close to call, wrong kind, evicted), using `$state['unfiled']`.
- On finish: the outcome sentence, the tree with real counts, and **Undo** for 24 hours (the existing undo record).

## The session

One record per site, option `vergeml_guide_session`, autoload off:

```
{
  version: 1,
  state: 'library' | 'proposal' | 'shaping' | 'review' | 'applying' | 'done',
  started_at, updated_at,
  goal: string,                       // the user's words, screen 1
  summary: { total, described_at, groups: [...], evidence: {...} },   // screen 1, cached
  proposals: [ tree, tree ],          // screen 2
  draft: { version: n, folders: [ { name, parent, matches, classes, kinds, audience, count } ], tags: [ { name, values: [] } ] },
  history: [ { version, draft } ],    // last 10 versions, for stepping back
  turns: [ { role: 'user'|'assistant', text, choices?: [..], at } ],  // capped at 60 entries kept
  assistant_turns: n,                 // counts toward the cap of 25
  apply: { run_id, started_at }       // screen 4, points at the talk re-filing state
}
```

Saved on every change from the screen (debounced 500 ms) through `POST /vergeml/v1/guide/session`. Closing the tab loses nothing; reopening resumes at `state`.

## REST routes (plugin)

All under `VERGEML_REST_NS`, `manage_categories`, with the same admin-ajax fallback the tree already uses when REST is blocked.

| route | does |
|---|---|
| `GET /guide/session` | the session, or a fresh one if none |
| `POST /guide/session` | save (whole object; the server keeps `assistant_turns` and `apply` authoritative) |
| `POST /guide/summary` | compute screen 1 (cached in the session; recomputed when the library's described count changes) |
| `POST /guide/propose` | screen 2: calls the service in `propose` mode with the summary and goal, estimates counts, stores both trees |
| `POST /guide/turn` | one conversation turn: `{ text?, choice?, edit? }` → calls the service in `turn` mode with the session context → returns `{ message, choices?, draft? }`; increments `assistant_turns`; refuses past the cap with a message the screen shows |
| `POST /guide/apply` | validates the draft, hands it to `vergeml_talk_apply()`, records `apply`, returns the re-filing state |
| `GET /guide/progress` | the re-filing report (`vergeml_talk_report()`) |

Count estimation lives in the plugin (`vergeml_guide_estimate( $folders )`): for each folder, the share of a sample of catalogue records whose object class matches one of the folder's classes (exact, substring, plural) — no embeddings, so it is fast and deterministic. The sample is the newest 2,000 described records; counts are scaled to the total and rounded.

## The service: `/v1/guide`

One route, two modes, one model: `claude-opus-5` through OpenRouter with the same ZDR routing as everything else (`ANTHROPIC_GUIDE_MODEL` overrides). No extended thinking (`thinking: disabled`) — measured on 4 September 2026, the planner model spent every output token thinking and returned no text. `max_tokens` 6000.

Request (both modes): licence key, site, `summary`, `goal`, `current` folders, and in `turn` mode also `draft`, the last 20 `turns`, and the new input (`text` or `choice` or `edit`).

`propose` returns `{ proposals: [ { name: 'By what the pictures are', tree }, { name: 'By how you publish', tree } ] }`.

`turn` returns `{ message: string, choices?: string[], draft?: tree }` — a message always; a question with choices when the assistant is unsure; a full draft when it changed anything. Validated with Zod (the folder shape is the existing `FolderShape`: name, parent, matches, classes, kinds, audience). Reply-as-JSON in prose, parsed with `looseJson`, the same path the planner uses.

The system prompt states the rules the assistant must apply and explain:

- A folder tree nests one way. When the user wants two or three axes ("by size, colour and brand"), choose one axis as folders on the evidence (which is named most often and groups most evenly), propose the others as tags, show the consequence, and ask.
- A folder needs pictures behind it: use the group sizes and evidence shares; do not propose a folder the catalogue cannot fill; say so when the user asks for one ("only 8% name a colour, that would be mostly empty").
- An audience split (men / women / kids) only where the evidence share for audience supports it; say the number.
- Kinds are gates: logos, screenshots, diagrams get folders of their own kind or none.
- Keep the user's names where they gave them; never rename silently; never invent a path with a slash.
- When unclear, ask one question with two to four concrete choices rather than guess. When the user's request contradicts the evidence, say the evidence and ask, do not refuse.
- Answer the question behind the words: "I run a tech blog" means folders follow topics and publishing, not dates.

Cost: included in the plan; capped at 25 assistant turns per session, enforced by the plugin (the service also refuses `turns.length > 60` as a backstop). A typical session (summary, two proposals, ten turns) is under €1 of model time.

## Large libraries

Nothing in the flow reads all records into a model call. Summary and proposals use the clusters (600 sampled vectors, k=10, already in `vergeml_talk_groups()`), SQL aggregates, and up to 40 sample captions. Count estimation uses a 2,000-record sample. Filing is the sliced background job (`VERGEML_TALK_SLICE` 500 per slice, resumable, survives page close). A 10,000-picture library plans in the same time as a 600-picture one and files in about 20 minutes at today's rate on the box.

## Failure modes, and what the user sees

- **Service unreachable / cap reached:** the assistant column shows why in one sentence; the tree stays editable by hand; **This is the structure I want** still works. No dead end.
- **Answer does not validate:** retried once with the validation error fed back; then "I did not follow that — say it another way", draft untouched.
- **Library not described (or partly):** the entry point says so and points at the AI screen; screen 1 is not opened on an undescribed library.
- **Apply fails to create a folder:** reported per folder on screen 4; the rest proceeds; nothing is filed into a folder that does not exist.
- **Undo:** the existing undo record, one click, 24 hours.

## Where the code goes

Plugin:

- `core/guide.php` — page registration (`media-guide`, admin shell nav under Folders), session option, REST routes, summary and evidence SQL, count estimation, apply hand-off.
- `js/vergeml-guide.js` — the screen, in `wp.element` (no JSX, no build step): a store (session + actions), four screen components, the tree editor, the conversation pane. Enqueued only on the guide page with `wp-element`, `wp-api-fetch`.
- `css/vergeml-guide.css` (+ the RTL sheet from `tools/rtl.mjs`).
- Entry buttons on the Folders page and the dashboard card.

Service:

- `app/api/ai/guide/route.ts` — licence and entitlement checks as `/folders`, then `propose` or `turn`.
- `lib/guide.ts` — prompts, schemas, `proposeTrees()`, `guideTurn()`; the model choice; the retry-with-error.
- `lib/guide.test.ts` — schema and prompt tests; a Braintrust eval set of fixed conversations (`evals/guide/*.json`) including the monitors example, scored on: asked instead of guessed when unclear; kept the user's names; no slash paths; proposed a tag for a second axis.

## Testing

- Service: Vitest for schemas, mode routing, retry; Braintrust eval on the fixed conversations, run in CI on prompt changes.
- Plugin: the existing Playwright sweep gains "the guide screen opens cleanly" and a scripted session on the box (`box-fix.yml` job `guide-walk`) that starts a session, takes the first proposal, sends two turns, confirms, applies, and prints the outcome sentence.
- Manual: the four screens on the box with the 641-picture library; the monitors conversation from the design.

## Out of scope for this cycle

- Polishing the presentation of the existing chat card and wizard (separate task, agreed).
- Filters/"views" beyond tags (tags reuse the plugin's extra taxonomies).
- Multisite network-wide sessions.
