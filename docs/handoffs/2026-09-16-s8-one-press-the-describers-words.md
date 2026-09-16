# Handover — 2026-09-16, S8: C.3 the screen made honest + C.4 the planner speaks the describer's words (Opus 5)

Card: `.harness/active.json` (S8). Plan: `plans/every-picture-a-home.md`
Phase C. Evidence: `2026-09-16-s7-the-engine-honest.md` (the numbers this
session moves), `2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md` (seams 3
and 4). Lean: test first, one mutation check per task, **no fill on the box**,
measured on both sites. Spend: one embed per profile the pills test seeded
(cached a week), nothing else; no planner or describer call.

Commits: plugin `9521888` (C.3 + the C.4 screen), `7a35612` (the C.4 engine,
the baseline re-taken, the sheet); service `954a84b` (the prompts), pushed and
built — `vercel ls` shows the production deploy created 09:13, one minute
after the push, aliased to `ai.vergelabs.nl` (the health route carries no
commit marker; the identity is the time). Box deployed and verified after each
change (`tools/deploy.mjs --box`, 137 files); `--check` says up to date.

## What changed

### C.3 · one press, one honest answer

- **Server** (`core/folder-talk.php`, `core/guide.php`): the move loop reads
  the pictures' folders in one query, runs under `wp_defer_term_counting`,
  unhooks the per-picture count flush and flushes once; the answer carries
  `placed` (how many are the person's after it). `guide/answer` returns the
  result, status, made, undo, version — **no `questions`** (the screen holds
  them; "Leave the rest" reads the raw state, renders nothing).
- **Client** (`js/vergeml-folders.js`): an error lands on the card as
  `.g-q-error` and the buttons come back; `renderQuestions` reconciles the
  grid (the answered card is the same node, patched; the next card appended;
  the strip is rebuilt only when it opens); `refreshTree` calls
  `setNewIds(…, quietly)` before `setTree` so the tree renders **once**, and
  `dom.tree` is only appended into a slot it is not already in. "Show me" past
  48 appends a `N more` pill. A card takes focus: **1–4** press its answers,
  **Enter** opens the strip. The result line links `N by you · review` to the
  list's new filter.
- **Placed by hand** (`core/media-list.php`, `core/taxonomies.php`,
  `core/rest-tree.php`, `js/vergeml-tree.js`): the list's folder dropdown gains
  *Placed by hand (N)* third, value `by_you` → a meta query on
  `_vergeml_placed_by = user`; an `assign` that only loses folders (the Unfiled
  drop) deletes the mark; the tree's `syncListBar` no longer resets the bar's
  own options to *All folders*.
- **The reason** (`core/librarian.php`): the why card adds "No folder inside it
  fits better" to a likely placement in a folder with children (true by the
  matcher's 0.03 descent rule) and "Two folders inside it tie for it" to a
  siblings one. Read on the box: 106797 in Hardware at 0.56 carries it, 105951
  in Phones (a leaf) does not.
- **A deep link fixed on the way**: `upload.php?item=N` (core's modal for one
  picture) was lost when a folder was remembered — the grid re-selected it
  under the modal. `item` now counts as a chosen URL and the grid leaves a
  non-bare arrival alone. Found because the session admin had a remembered
  folder from an earlier spec; `modes.spec` "the grid modal says why" is green
  again.

### C.4 · the planner speaks the describer's words

- **Service** (`lib/anthropic.ts`, `app/api/ai/folders/route.ts`,
  `lib/describe.ts`): `profilePrompt()` and `planPrompt()` are exported pure
  functions; both carry the library's top terms with counts and
  `ONE_FOLDER_RULE` ("use those words verbatim; a class belongs to exactly one
  folder; a parent lists only what none of its children claims; a kind word is
  never a class"). The route caps `terms` at 80. The describer's schema
  description for `object` now says "specific; class" like its prompt.
  `lib/folders-prompt.test.ts` (6) and a `describe.test.ts` row; mutation run:
  the rule line removed → 2 red.
- **Plugin, the seam** (`core/filing.php`, `core/folder-talk.php`):
  `vergeml_filing_vocabulary()` (both phrases of every object, canon-spelled,
  counted, top 80) goes with the profile call and the plan call;
  `vergeml_talk_samples()` appends `[object: …]` to each caption.
- **Plugin, the canon** (`core/filing.php`): `vergeml_filing_canon` (spelling
  table, irregular plurals, `-ies`, `-ches/-shes/-sses/-xes/-zes`, then `-s`);
  `class_match($a, $b, $head)`: canon-equal 1.0; **the picture's object phrase
  whose head noun is the folder's one-word class is 1.0** (`rocket launch` vs
  `launches`) — the pick passes `$head` only for the first phrase, because
  with it on the class half every "…; computer hardware" hit the parent
  *Hardware* in full and the too-broad likelies there became *sure* (seen on
  the first dry sheet of the day, reverted); containment 0.95; the vector path
  floored at `VERGEML_FILING_CLASS_COSINE_FLOOR = 0.6`.
- **Plugin, the seed** (`vergeml_filing_clean_seed`, pure): a kind word as a
  class moves to `kinds`; a class another folder holds first
  (`vergeml_filing_claimed_classes`) is dropped and recorded in
  `profile['dropped']`. `profile_build` keeps the replaced planned profile in
  `_vergeml_filing_profile_prev` for a day.
- **Plugin, the tree** (`js/vergeml-tree-view.js`, `js/vergeml-folders.js`,
  `core/guide.php`, `core/rest-tree.php`): nodes carry `classes` (the stored
  plan's, without the appended leaf) and `prev`; rows show three quiet pills
  and `+n`; `×` emits a `classes` edit (the draft's classes, by you, the fit
  re-runs through the paste path, no model); `#word` in the + editor adds one;
  the confirm merges the stored plan under a draft that only changed the
  words; `guide/unconfirm` answers `prev`, and `guide/profiles-restore` swaps
  the earlier profile back on every folder that has one and writes its
  classes into the draft.
- `pick.php` rows 16–20 (22/22); mutation run: the `-es` fold removed → rows
  17 and 17b red.

### The gates, as run

- `node tools/verify.mjs filing surface roles escaping copy tree-view` → 22/22
  + 30/30, 26/26 (register regenerated), 19/19, 9/10 (the known ratio row;
  the new media-list sink has its reason in `tools/escaping.mjs`), 58/58,
  61/61.
- `folders.spec` on the box: **16 of 16 passed** (the 17th is the GUIDE_WALK
  one). `modes.spec`: the row tests, the grid modal, nothing-covers → green;
  **"the folder filter: Unfiled … returns files" fails on state**: the box has
  0 unfiled since the walk (327 in To sort), so *Unfiled* honestly lists
  nothing — a fixture-free test written for a library with unfiled pictures.
  Not touched; say so.
- `node tools/filing-baseline-check.mjs` → **re-taken** with the reason
  (`--retake`), then 4/4.
- Mutations run this session: the defer removed (box: 533 → 729 queries in
  PHP, 800 → 1102 ms); the rule line removed (vitest 2 red); the `-es` fold
  removed (pick 2 red). The observer/re-append/no-questions rows were watched
  going green from red during the build, not by a separate mutation run.

## The numbers

**Timing, both sites** (`folders.spec` "one press": 50 pictures moved, 30
questions open; handler ms from `tests/perf/mu-perf.php`):

```
box        · answer: handler 620–891 ms · 591 queries · wall 3.3–3.5 s · tree GET: handler 21–24 ms · wall 2.8 s
playground · answer: handler 2535 ms   · 435 queries · wall 5.1 s     · tree GET: handler 29 ms    · wall 2.7 s
```

- The plugin's own loop is ~7 queries a picture (term_exists, the terms read,
  the relationship's check and insert, the mark's two); the box's other
  plugins add ~3 (Jetpack's sync queue, a termmeta read). The budget in the
  spec: box ≤ 12/picture + 80, Playground ≤ 9/picture + 60.
- **The plan's "under 400 ms on Playground" is not met and cannot be read
  there**: php-wasm's SQLite is ~6 ms a query (435 in 2.5 s) where the box's
  MySQL is ~1 ms (591 in 620 ms). mu-perf's own header says the honest
  Playground number is the query count; the spec gates that and prints the
  ms. On a real database with six plugins, 50 moves is ~0.4–0.5 s of handler.
  What would cut it further is a bulk write of `term_relationships` past
  `wp_set_object_terms` — which bypasses every other plugin's hooks (Jetpack,
  FileBird). Not done; a stop point if wanted.

**The baseline, re-taken** (dry run, `VGML_FRESH`, 1000 pictures):

```
C.1+C.2: looked 1000 = fits 512 (sure 264, likely 262) + siblings 14 + nothing 474 (floor 401, margin 39 = either 39, gated 34)
C.4:     looked 1000 = fits 553 (sure 340, likely 220) + siblings  7 + nothing 440 (floor 370, margin 36 = either 36, gated 34)
```

**The sheet** (`docs/superpowers/mocks/shots/2026-09-16-quality-sample-c4-dry.html`,
dry, seed 133; the tool now also carries the C.1 dry sheet's 60 marks and
re-reads them by folder path):

- The C.1 sheet's 60 under this engine: **sure — 22 right kept, 4 wrong kept,
  4 broad kept, 0 moved**; likely — 7 right kept, 8 wrong kept, 12 broad kept,
  3 moved. So the canon fold moved none of the sure misses (3 Satellites
  `diagram`, 3 Components, 106552 Launches, 106405 People): they are the
  **profiles'** — the kind-word guard and the neighbour drop act on the *next*
  planner answer, and that is a planner call Nathan presses through the
  screen (confirm re-profiles only folders without a plan; the way to a fresh
  profile today is Unconfirm → `×` the wrong words / `#` the right ones →
  confirm, or a proposal).
- **The verdict (Nathan, 2026-09-16, on the C.4 dry sheet):**

  ```
  sure:   26/30 right (87 %, was 73 %, was 47 %)  ·  bar 95  ·  too broad 106549 (a shuttle launch in Space, not Launches), 106489, 106492 (Components)  ·  wrong 106720 (Batteries)
  likely: 17/30 right (57 %, was 23 %, was 40 %)  ·  bar 80  ·  too broad 106018, 106041, 106082, 106666, 105893 (Hardware as the parent), 106477 (Energy)  ·  wrong 106374, 106366, 106368, 106390 (smartwatches in Phones: no Watches folder), 106784, 105957 (Space), 106696 (Energy)
  ```

  His notes: the smartwatches have no folder and land in Phones (he reads that
  as fair); motherboards and the like sit in Hardware where Components is
  right; cables are too broad; the shuttle launch in Space belongs in
  Launches. The tool carries these 60 marks (`$bfq_c4`) for the next take. The sure 30 hold
  Components 13, Batteries 5, Laptops 4, Robotics 3, Server racks 2, Phones 2,
  Wind, Solar, Space.

## Not done, and why

- The 400 ms on Playground: see above — a wasm number, gated as queries.
- `modes.spec` "Unfiled returns files": state (0 unfiled on the box).
- The real mouse drag out of a folder is grid-only under `MODES_WALK`; the
  drag-out clearing the mark is proven through the same `assign` request the
  drop sends (`{mode: 'move', add: []}`), in `folders.spec`.
- The why card's reason line is read on the box, not pinned by a
  `filing-trail.php` row (the suite's `ok` picture is sure in a leaf).
- `fill-walk` not run (a fill on the box).
- Re-profiling the box's folders with the new prompts: a planner call,
  Nathan's, through the screen. Until then the profiles are the S7 ones and
  the sure misses stand.

## Next — S9

Two cards; Nathan picks. **C.5** is the plan's next task and the one that
judges K, the thresholds and 1/k on a second shape; it costs describes (≈
€2.40 for 500) and his time to confirm and fill. If he does not buy it now,
the **Folders polish** card is the honest use of a session: the K question,
the date folders, a desktop folder, the fill-walk re-run.

Card A — C.5, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — Phase C, S9: C.5 a second library, a different shape (Opus prepares, Nathan pays and judges)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase C: C.5)",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "tools/box-seed-*.php (a seed for the second library, in the shape of tools/box-seed-technews.php)",
    "tests/tree/filing-baseline.txt (a second band keyed by library)",
    "tools/filing-baseline-check.mjs (--library)",
    "tests/ui/folders.spec.mjs (the second site at 1600 and 1280)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-16-s8-one-press-the-describers-words.md (this: the numbers, the sheet that waits, the 400 ms reading)",
    "plans/every-picture-a-home.md (C.5 and its proof; A.5 the walk)",
    "memory: hetzner-box-fixtures (the network site /var/www/ms, the walk's state), model-spend-discipline, playground-counts-queries-not-ms",
    "tools/box-seed-technews.php (how the first library was seeded)"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Say the cost before describing: ≈ €0.0048 a picture, 500 ≈ €2.40; a subset of 500 says the same as the whole",
    "The second library on the network site (/var/www/ms2 or the ms site), never on the tech library's site: its baseline is the reference",
    "Nathan presses propose, confirm and fill through the screen (A.5); the session takes the sheet on the fill's batch",
    "The cap K = 8, GROUP_NEAR 0.8, the 60 % put-in and 1/k are judged on the second shape, not moved by feel: if one fails, name which and propose a rule that reads the shape (folder count, pictures per folder), never a new constant",
    "The tech library's sheet after C.4: sure 87 %, likely 57 % (bars 95 / 80); the misses are the profiles (Components, Launches) and a missing Watches folder — Nathan's re-profiling and his folder, not the engine",
    "The baseline gate stays frozen for the tech library; the second library gets its own band"
  ],
  "gates": [
    "tests/tree/filing-baseline.txt carries a second band keyed by library, and filing-baseline-check.mjs runs either",
    "The second library's sheet: 30 sure + 30 likely on the fill's batch, the two numbers in the handoff beside the tech library's",
    "folders.spec green on the second site at 1600×1000 and 1280×800 (the word budget holds at 300 folders because the parents are closed)",
    "node tools/verify.mjs filing surface roles escaping copy tree-view → green (escaping 9/10 known)"
  ]
}
```

Card B — the Folders polish, if C.5 is not bought:

```json
{
  "phase": "Every picture a home — S9: the Folders polish (K, the date folders, a home for desktops, the fill walked)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (held behind S8)",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/filing.php (the small-groups card split by nearest folder, or a cap on pictures)",
    "core/folder-talk.php",
    "js/vergeml-folders.js",
    "tests/filing/residue.php",
    "tests/ui/folders.spec.mjs",
    "tools/box-fill-walk.php",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-16-s8-one-press-the-describers-words.md (this)",
    "docs/handoffs/2026-09-16-s7-the-engine-honest.md (the 280 in one card; the date folders; the desktops)",
    "memory: tests-never-touch-live-state, hetzner-box-fixtures"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "K = 8 is Nathan's number: propose the split of the small-groups card by the pictures' nearest folder (60 % majority inside a sub-cluster) or a cap on pictures; build the one he picks",
    "The date folders (2026 / September) take the illustrations by their name-derived kinds: whether they exist at all is his",
    "11 likely pictures are desktops with no child to go to: a Desktops folder is a proposal, not a script",
    "fill-walk runs a fill on the box: only with his yes, and the walk's state (61 by hand, 327 in To sort, 30 questions) put back after",
    "A Watches folder and the Components/Launches re-profiling are Nathan's to press: propose, do not script"
  ],
  "gates": [
    "residue.php: the split or the picture cap has its row and its mutation",
    "folders.spec: the small-groups card as built, on the box",
    "node tools/verify.mjs filing surface roles escaping copy tree-view → green; filing-baseline-check 4/4 or re-taken with the reason"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-16-s8-one-press-the-describers-words.md, then
plans/every-picture-a-home.md C.5 (or the held items behind S8). State
which model you are. This session is S9 of every-picture-a-home: [C.5 the
second library | the Folders polish]. Write the card from the handoff to
.harness/active.json before anything else. Lean: test first, one mutation
check per task, no fill on the tech library, say every cost before it is
spent. End with a handoff carrying the S10 card.
```

Held behind S9 either way: B.6, the bulk `term_relationships` write past
`wp_set_object_terms` (only if 50 moves at 0.6 s on a real database is too
slow for someone), the `filing-trail.php` row for the reason line.
