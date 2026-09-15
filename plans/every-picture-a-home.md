# Every picture a home — the plan

For `tickets/2026-09-14-every-picture-a-home.md` and the spec
`docs/superpowers/specs/2026-09-14-every-picture-a-home.md`. Written
2026-09-14 from the box's numbers; every path named exists. Two phases:
**A, the engine** (what the fill does) and **B, the screens** (what a person
sees). A is built first because B's questions view has nothing to show
until the engine produces questions; B's mocks are made and approved while
A is being built, so no session waits.

**State on 2026-09-14.** The matcher is `core/filing.php` (603 lines):
`vergeml_filing_facts`, `vergeml_filing_pick` with gates on kind and
audience, `VERGEML_FILING_FLOOR` 0.55, `VERGEML_FILING_MARGIN` 0.08,
`VERGEML_FILING_MISFIT` 0.40; profiles per term in term meta
(`vergeml_filing_profile`, `_build`, `_profiles`, `_profile_existing` — the
planner call). The run is `core/folder-talk.php`: `vergeml_talk_apply`
(since `49b3e88` answers at once and schedules), `vergeml_talk_refile_run`
(resumable passes, the undo record, the trail), `vergeml_talk_report`,
`vergeml_talk_undo`. The session and routes are `core/guide.php`:
`/guide/session|token|turn|rules|rule|apply|progress|stop|undo`. The screen
is `js/vergeml-folders.js` (1,045 lines) + `js/vergeml-talk.js` (515) +
`css/vergeml-folders.css` + `css/vergeml-talk.css`; the tree component is
`js/vergeml-tree-view.js`. Suites: `tests/ui/folders.spec.mjs` (688 lines,
plants a turn, restores what it writes), `tests/tree/*.mjs`,
`tools/box-refile-all.php` (dry run of the matcher), `tools/box-folder-quality.php`
and `tools/box-folder-audit.php` (scores of members to their folders),
`tools/filing-baseline-check.mjs`. The approved mock for Step 2 is
`docs/superpowers/mocks/2026-09-14-folders-glance.html`. The box: 1,000
described, 20 folders, 487 filed, 513 not (the numbers this plan answers).

**Model.** Every task Opus 5 unless marked; B.3 and B.4 (rendering a shared
component's rows and a view from an approved mock) are Fable 5.1 candidates
if Fable has budget, otherwise Opus. The profile is
`~/.claude/harness/model-profiles.md` where it exists; where it does not, the
rule is: one session per phase-task pair below, a handoff at the end of
every session, never compact.

**Sessions.** S1: A.1 + A.2. S2: A.3 + A.4. S3: B.1 (mocks, approved in the
conversation) — can run before S1. S4: B.2 + B.3. S5: B.4 + B.5. S6: A.5
(the box walk with Nathan, both phases together). S7: B.6 (the other two
screens). Seven sessions; the ticket said eight and one is the mock session
that overlaps.

**Stop points (Nathan).**
- B.1 — the two mocks (Step 3's questions view; the step rail over the
  glance mock) approved with screenshots in the conversation.
- A.2 — the residue clusters are named by a model call: metered (free to
  the licence), or a credit? Default: metered, like embeds.
- A.2 — the thresholds for `sure` (0.70) and the sibling rule (parent when
  margin < 0.08). Defaults stand unless Nathan names others; A.5 is where
  they are judged on the box.
- A.5 — Nathan answers the questions on the box himself; the session does
  not click through them.

**Gates, as commands.**
- `node tools/verify.mjs filing` (new in A.1) — the engine's suite, local,
  green.
- `tools/box-fill-walk.php` (new in A.4) on the box: propose → confirm →
  fill → questions answered by script (A.4) or by Nathan (A.5) → **0 in no
  folder**; preview count = run count; asserted, not read.
- `npx playwright test --config tests/ui/playwright.config.mjs tests/ui/folders.spec.mjs`
  — green at 1600×1000 and 1280×800; the word-budget assertion (≤ 80 a
  step) in it.
- `node tools/verify.mjs surface roles escaping` — still green after every
  route change (escaping stays 9/10 until Phase 3 S3).
- Mutation per task, named in each task's Proof.

**Spend.** A propose is a planner call (~10 credits); a describe is a credit
a picture; the Folders page must **no longer** open a conversation on its own
(B.2 removes it). Box suites plant a turn first (`folders.spec.mjs` shows
how). Every browser run needs the throwaway admin `vgml-smoke`. Say a test's
cost in the handoff when it exceeds a dollar.

**Do not, anywhere in this plan.** Tune the floor to make the number look
better; add a rule builder; touch the description prompt; enable the
renamer; open the conversation without a press; write a sentence where a
pill will do.

---

# Phase A — the engine

## A.1 · One filing path, and its suite — Opus

- **Files:** `core/filing.php` (`vergeml_filing_pick` returns an outcome;
  `vergeml_filing_profiles` reads profiles the tree carries),
  `core/folder-talk.php` (`vergeml_talk_apply`: no re-profiling; the state
  carries the profiles the preview scored with), `core/guide.php` (the
  preview's count and the run's count come from one function),
  `tests/filing/pick.php` (new; local, no box), `tools/verify.mjs` (`filing`
  registered, local).
- **Behaviour:** `vergeml_filing_pick( $facts, $profiles )` returns
  `array( 'outcome' => 'fits'|'siblings'|'nothing', 'term_id', 'parent_id',
  'score', 'runner_up', 'runner_score', 'confidence' => 'sure'|'likely'|'',
  'gated' => [...] )`. `siblings` when best and runner-up share a parent and
  `score - runner_score < VERGEML_FILING_MARGIN`; `nothing` when
  `score < VERGEML_FILING_FLOOR` or all gated. The profiles a fill scores
  with are the ones stored when the tree was confirmed (A.3); `vergeml_talk_apply`
  never calls `vergeml_filing_profile_existing`. The preview's numbers and
  the run's are produced by one function, `vergeml_filing_count( $profiles )`,
  called by both.
- **Proof:** `tests/filing/pick.php` — twelve fixtures in a PHP array (no
  database): a clear fit (sure), a fit inside 0.70 (likely), two siblings
  inside the margin → `siblings` with the parent, two non-siblings inside the
  margin → `nothing`, all gated → `nothing` with `gated` filled, a locked
  folder never picked, a user-placed picture never re-picked (A.3's meta,
  stubbed). Mutation: remove the sibling branch → the siblings fixture
  returns `nothing` and the suite is red at that row. `node tools/verify.mjs
  filing` → 12/12.
- **Mirror:** `tests/security/roles.php` for a suite that reports per row;
  `tools/box-refile-all.php` for how facts and profiles are read today.
- **Copy:** none; this task has no screen.
- **Do not:** change the floor or margin values; touch `js/`; delete the
  `misfit` eviction (it stays, for pictures the user did not place).

## A.2 · The residue, grouped and named — Opus

- **Files:** `core/filing.php` (`vergeml_filing_residue_groups( $ids )`),
  `core/folder-talk.php` (the state carries `questions`), `core/guide.php`
  (`/guide/questions` GET, `/guide/answer` POST), the service
  (`../service/app/api/ai/name-group/route.ts`, metered like `/embed`;
  `lib/anthropic.ts` one small prompt: "these objects, these captions — one
  folder name, two words"), `tests/filing/residue.php`.
- **Behaviour:** after a fill pass ends with residue, the residue is grouped
  by `filing.object` class (`vergeml_filing_classes_of_object`), then any
  group under 5 is merged into the nearest by vector; groups under 3 become
  one "I can't read these" group. Each group ≥ 5 gets a name from one
  metered call (cached in the state; never repeated for the same group).
  Sibling questions come from A.1's `siblings` outcomes, one per parent.
  Each question: `{ id, kind: 'siblings'|'residue', count, sample: 8 ids,
  name, answers: [...] }`. `/guide/answer` takes `{ id, answer }` where
  answer ∈ `keep-parent | split | new-folder | put-in:<term> | leave |
  show-me` and applies it: `new-folder` makes the term (by the user's hand:
  `_vergeml_placed_by = user` on the pictures), `split` files by best score
  ignoring the margin, `leave` moves the group into **To sort** (a real
  folder, made on first use, slug `to-sort`, locked). The step's "done" is
  `open questions = 0 AND unfiled = 0`.
- **Proof:** `tests/filing/residue.php` — 40 fixture facts → groups: sizes,
  merging under 5, the can't-read group, question shapes; each answer's
  effect asserted on a PGlite-free in-memory map. Mutation: the merge-under-5
  rule removed → a group of 3 survives and the count row is red. The service
  route: `lib/name-group.test.ts` — the prompt shape, the two-word cap, the
  metered row. `node tools/verify.mjs filing` → green (both files).
- **Mirror:** `/guide/rule` for a POST that changes the draft; `lib/embed.ts`
  for a metered route.
- **Copy:** the question sentences, verbatim from the spec §2 Step 3, in
  `vergeml_talk_question_text()`; the answers are the six words above.
- **Do not:** ask per picture; name a group without a cache; let "leave"
  put anything in no folder.

## A.3 · Confirm, lock, sticky — Opus

- **Files:** `core/guide.php` (session `tree` state: `editing | confirmed`;
  `/guide/confirm` POST, `/guide/unconfirm`), `core/filing.php` (profiles
  stored on confirm; `_vergeml_locked` on a term; `_vergeml_placed_by` on a
  picture), `core/tree.php` or wherever the drag-drop REST move lands
  (`_vergeml_placed_by = user` on a hand move — find the route with
  `grep -n "wp_set_object_terms" core/rest-folders.php`), `tests/filing/sticky.php`.
- **Behaviour:** confirm stores the tree's profiles (from the proposal's
  classes/kinds/audience or, for a pasted tree, from the names — the
  existing `vergeml_filing_profile_build`) and runs the planner's profiling
  once, here, not in the fill. A confirmed tree refuses `/guide/rule` and
  `/guide/turn` edits until unconfirmed. A locked folder is skipped by
  `vergeml_filing_pick` both ways. A user-placed picture is skipped by every
  fill and never evicted.
- **Proof:** `tests/filing/sticky.php` on the box (it writes term meta): a
  hand move → the picture survives a fill that would have moved it; a locked
  folder keeps its 3 and gains none; confirm → `/guide/rule` answers 409.
  Mutation: the `_vergeml_placed_by` check removed from `vergeml_filing_pick`
  → the first row red. Creates and removes its own three pictures and two
  folders.
- **Mirror:** `tests/security/paths.php` for a box suite that cleans up;
  `/guide/apply`'s "one press per plan" transient for a locking pattern.
- **Copy:** the 409 line: "The tree is confirmed. Unconfirm it to change
  it." (a fact and its consequence).
- **Do not:** lock To sort against the user (locked means the *fill* stays
  out; a person can always move things); store profiles anywhere but term
  meta.

## A.4 · The walk, by script — Opus

- **Files:** `tools/box-fill-walk.php` + `.sh` (new), `tools/verify.mjs`
  (`fill-walk`, box).
- **Behaviour:** on the box: undo any Move; propose (or paste the last
  proposal from a fixture file, to spend nothing); confirm; fill; answer
  every question by script (`keep-parent` for siblings, `new-folder` for
  residue groups ≥ 20, `leave` for the rest); assert 0 in no folder,
  `To sort` holds exactly the leftovers, preview count = run count, and the
  trail has a row per picture. Undo at the end; the library is as it was.
- **Proof:** the script's own assertions, printed as rows; `node tools/verify.mjs
  fill-walk` → green. Mutation: the `leave` branch removed from
  `vergeml_filing_answer_plan` on the box copy → "0 in no folder" red (216
  left, 2026-09-15); A.1's sibling branch removed → "at least one sibling
  question" red — not "0 in no folder", because the residue path answers
  every picture whatever the pick said. Cost: one propose (~10 credits) or
  none with the fixture; no describes.
- **Mirror:** `tools/box-why-walk.php` + `-undo.php` for a walk that undoes
  itself; memory `tests-never-touch-live-state`.
- **Copy:** none.
- **Do not:** leave the box filed by script; run against Playground (the
  matcher needs the real index).

## A.5 · The walk, by Nathan — Opus prepares, Nathan does it

- **Files:** `docs/handoffs/` for the record; `tools/box-folder-quality.php`
  run after.
- **Behaviour:** Nathan, on the box, through the screen (B.4 done): describe
  is done; propose; edit by prompt or hand; confirm; fill; answer the
  questions himself. The session watches the counts and takes the quality
  sample after: 60 pictures, 30 `sure` and 30 `likely`, shown to Nathan as a
  contact sheet with their folder; he marks right/wrong.
- **Proof:** 0 in no folder; the sample: `sure` ≥ 95%, `likely` ≥ 80%; the
  numbers in the handoff with the sheet's path. If a threshold fails, the
  handoff names which and the next card tunes **that**, with the sheet as
  evidence — not the floor by feel.
- **Mirror:** the 2026-09-13 "walk the purchase as a buyer" (memory).
- **Copy:** none.
- **Do not:** answer a question for him; call it done on the counts alone.

---

# Phase B — the screens

## B.1 · Two mocks, approved — Opus

- **Files:** `docs/superpowers/mocks/2026-09-15-fill-questions.html`,
  `docs/superpowers/mocks/2026-09-15-step-rail.html`, their shots in
  `docs/superpowers/mocks/shots/`; the glance mock as the base.
- **Behaviour:** (1) the step rail over the glance mock — five pills,
  Describe ticked, Tree current, the rest quiet; *This is my tree* as the
  primary button; (2) Step 3 in its asking state — the tree filling on the
  left with counts climbing, the pill row (placed · sure · likely ·
  questions · to sort), three question cards with the box's real numbers
  (232 siblings Server racks/Cooling; 61 robot arms; 18 can't read), each
  with its answers as buttons and eight real thumbnails; and its done state.
  Real data from the box's trail (`tools/box-refile-all.php` dry run gives
  the groups). ≤ 80 words on each, counted.
- **Proof:** screenshots posted into the conversation; Nathan's "approved"
  quoted in the handoff; the word counts in the handoff.
- **Mirror:** `2026-09-14-folders-glance.html` (approved) for the grammar
  and the word budget; `2026-09-06-ai-screen.html` for a two-state mock.
- **Copy:** the question sentences from the spec; the rail's five words;
  the done line "1,000 in folders · 0 to sort".
- **Do not:** build in `js/` or `core/`; add a fourth question kind; use an
  eyebrow, a middot string, or a paragraph.

## B.2 · The step rail, the confirm state, and no more talking first — Opus

- **Files:** `core/guide.php` (page markup: the rail; no auto-propose on
  load), `js/vergeml-folders.js` (steps as states; `state.step`), `css/vergeml-folders.css`,
  `tests/ui/folders.spec.mjs`.
- **Behaviour:** the page opens on the step the session is at, with the
  rail; Describe shows described/total and the button (delegates to the AI
  screen's run); Tree shows the glance layout; *Propose folders* is a button
  with its cost and nothing is proposed without it; *This is my tree* calls
  `/guide/confirm` and moves to Fill. The word count per step ≤ 80.
- **Proof:** `folders.spec.mjs`: open the page on an empty session → **no**
  REST call to a model route (assert no `/guide/turn` POST fired); the rail
  shows five pills; confirm → rail advances → `/guide/rule` answers 409;
  `innerText` word count per step ≤ 80. Mutation: re-enable the auto-open →
  the first assertion red.
- **Mirror:** the glance mock; `.vgml-seg` for a segmented control the shell
  has.
- **Copy:** the rail's five words; "Propose folders · 10 credits"; "This is
  my tree"; "Unconfirm".
- **Do not:** keep the 25-turn thread as the opener; leave the "Paste
  folders" tab — paste is a link under the input now.

## B.3 · The tree with pills — Fable if available, else Opus

- **Files:** `js/vergeml-tree-view.js` (count as a pill, `new`/`changed`
  as pills, confidence pill on a row when asked), `css/vergeml-tree-view.css`,
  `js/vergeml-folders.js` (the sibling row for parents with ≤ 3 children),
  `tests/tree/*.mjs` where the row is asserted.
- **Behaviour:** rows render as in the glance mock everywhere the component
  is used (Folders, the Library sidebar, the modal): the count is a pill,
  `new` is the brand-yellow pill, a parent with ≤ 3 children may show them
  on one line (Folders screen only, a data attribute). No row geometry
  change elsewhere.
- **Proof:** `tests/tree/shots.mjs` screenshots before/after in the handoff;
  `folders.spec.mjs` asserts `.g-pill` (or the component's class) on every
  count. Mutation: the pill class dropped → red.
- **Mirror:** the component's `render()`; the nav's count pill in
  `css/vergeml-shell.css`.
- **Copy:** none.
- **Do not:** change `aria-*` on rows; touch the drag handlers.

## B.4 · The Fill step: live counts and the questions — Fable if available, else Opus

- **Files:** `js/vergeml-folders.js` (the Fill state: polls `/guide/progress`,
  renders `/guide/questions`, posts `/guide/answer`), `css/vergeml-folders.css`,
  `core/guide.php` (the questions view markup), `tests/ui/folders.spec.mjs`.
- **Behaviour:** as B.1's approved mock: counts climb in the tree and the
  pill row while the run goes; when it ends, the question cards appear in
  order (siblings first, residue by size); an answer posts, the card shows
  its result line ("Robotics made · 61 moved"), the tree gains the folder;
  the done state when questions = 0 and unfiled = 0, with Undo.
- **Proof:** `folders.spec.mjs`: plant a confirmed tree and a state with two
  questions (through `/guide/session` as the suite plants drafts), assert
  the two cards and their buttons; click `new-folder` → the tree shows the
  folder and the card its line; the done state's text. Word count ≤ 80.
  Mutation: the done condition without `unfiled = 0` → a planted state with
  unfiled 3 shows done → red.
- **Mirror:** the Move button's three states today (`renderMove`,
  `data-state`); `took()` for the poll.
- **Copy:** from the spec §2 Step 3 and B.1's approved mock.
- **Do not:** show a question before the run ends; show more than eight
  thumbnails; scroll the page for a card.

## B.5 · Alt text and Rename as steps; confidence on a picture — Opus

- **Files:** `core/guide.php` (Steps 4 and 5 markup), `js/vergeml-folders.js`,
  `core/ai.php` (`vergeml_ai_apply_alt` called from the step), `js/vergeml-media-list.js`
  and the grid modal's "why" (`core/traces.php` or where `why is it here`
  renders — `grep -rn "why is it here" core js`) for the confidence pill.
- **Behaviour:** Step 4 shows pictures missing alt as a pill and one button;
  pressing writes the catalogue's alt onto them and never overwrites; Step 5
  shows "Not available yet" and one line. The grid modal and the list row
  show `sure` / `likely` / `by you` as a pill next to the folder.
- **Proof:** `folders.spec.mjs`: Step 4 on a planted state with 3 missing →
  press → 0 missing, one picture with an existing alt untouched (assert its
  text); Step 5 disabled with its line. `tests/ui/modes.spec.mjs`'s modal
  test asserts the pill. Mutation: the "never overwrites" guard removed →
  the existing-alt assertion red.
- **Mirror:** the AI screen's "Alt text for 900" button; the traces modal.
- **Copy:** "Write alt text for 900 pictures · never replaces one you have";
  "Rename files · not available yet".
- **Do not:** enable the renamer; write alt text during the fill.

## B.6 · The AI screen and the Dashboard at a glance — Opus

- **Files:** `core/ai-screen.php`, `core/admin-menu.php` (`vergeml_admin_home`),
  their CSS, `tests/ui/shots.spec.mjs` (word-count assertions added).
- **Behaviour:** the same subtraction: ≤ 100 words on the AI screen (from
  420), ≤ 100 on the Dashboard (from 505); pills for every count; the
  "What is written where" table becomes a disclosure closed by default; no
  eyebrows, no middot strings. A mock for each, approved first, in the
  glance grammar.
- **Proof:** `shots.spec.mjs` word counts ≤ 100 each; the two mocks'
  approval quoted; screenshots in the handoff. Mutation: the disclosure
  forced open → the AI count red.
- **Mirror:** the glance mock; B.2.
- **Copy:** drafted in the mocks, approved with them.
- **Do not:** remove a fact the screen needs (credits, last run, what
  leaves the site) — move it behind a disclosure, don't delete it.
