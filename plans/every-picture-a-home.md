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

## B.1 · The mocks, approved — Opus (done 2026-09-15, S3: three mocks, six states)

- **Files:** `docs/superpowers/mocks/2026-09-15-step-rail.html`,
  `2026-09-15-fill-questions.html`, `2026-09-15-other-steps.html` (Nathan
  asked for the rest of the rail), their shots in
  `docs/superpowers/mocks/shots/`; the glance mock as the base.
- **Behaviour:** (1) the step rail over the glance mock — five pills, every
  one a button, Describe ticked, Tree current, the rest hollow; one column,
  the change line under the tree, *This is my tree* the primary with *Skip*
  beside it; (2) Step 3 in its asking state — the tree with counts, the pill
  row (placed · sure · likely · questions · to sort), three question cards
  with the box's real numbers from the 2026-09-15 walk (4 fit both Server
  racks and Cooling; 46 look like 3d printers; 45 can't read), each with its
  answers as buttons and eight real thumbnails, *Leave the rest* under them;
  and its done state; (3) Describe, Alt text and Rename in the same grammar.
  ≤ 80 words on each without the tree, counted (`tools/shoot-mock.mjs
  --words`).
- **Proof:** screenshots posted into the conversation; Nathan's "approved"
  quoted in the handoff; the word counts in the handoff.
- **Mirror:** `2026-09-14-folders-glance.html` (approved) for the grammar
  and the word budget; `2026-09-06-ai-screen.html` for a two-state mock.
- **Copy:** the question sentences from the spec; the rail's five words;
  the done line "1,000 in folders · 0 to sort".
- **Do not:** build in `js/` or `core/`; add a fourth question kind; use an
  eyebrow, a middot string, or a paragraph.

## B.2 · The step rail, the confirm state, and no more talking first — Opus (done 2026-09-15, S4)

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
  folders" tab — paste is a button under the input now (*Paste or upload a
  list*, `.txt` / `.csv` read in the browser, no PDF).
- **As built (S4):** the page opens on the session's step and fires no model
  route; the rail is `.g-rail > button.g-step` from `core/guide.php`; the
  cards are drawn by `js/vergeml-folders.js` from the page's data; the fit
  carries `residue` (the biggest class the dry run would not place) for the
  placeholder; Skip on Describe / Tree / Alt text, no step a gate. Fill in
  B.2 is the run's button ("Fill 1,000 pictures", `/guide/apply`) with
  Unconfirm beside it; B.4 adds the live counts and the questions.

## B.3 · The tree with pills — Opus (done 2026-09-15, S4, in B.2's session; Fable was at ~8 % per the S3 handoff and was not switched to)

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

## B.4 · The Fill step: live counts and the questions — Fable if available, else Opus (done 2026-09-15, S5, Opus)

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
- **As built (S5):** `renderFill()` has four states — running (the pill row
  from the report's `tally`, the rows from `view.setProgress`), asking (the
  `.g-qs` column to the tree's right, `.g-cols.is-asking`; three cards, the
  answered one kept with its result line until the next answer; *Leave the
  rest* posts `id: "rest"` to `/guide/answer`, which answers every open
  question with leave, or keep-parent for a sibling question), done
  (`fillDone()`: 0 open, 0 unfiled, not running; `.g-cols.is-done`, the
  parents closed, what the answers made marked new through
  `view.setNewIds(made)`, *Next: Alt text*, Undo), and the run's button.
  The questions are read once the page has painted, only when the fill left
  some (`vergeml_talk_fill_status().open`); `made` (the folders the answers
  made, To sort included when leave made it) travels with the page and with
  every answer. *Show me* opens the card on the group's pictures, each a
  link to its modal. Undo now closes the questions with the Move. The
  questions are planted for the suite by `tests/ui/fill-fixture.php` over
  SSH (`tests/ui/box.mjs`), since no route writes the talk state.

## B.5 · Alt text and Rename as steps; confidence on a picture — Opus (done 2026-09-15, S5)

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
- **As built (S5):** Step 4's button is the plugin's own `/ai-alt` route,
  two hundred a request until none is left, no model route; the pill is
  every image without alt text (`alt_missing`), the button the ones the
  catalogue can fill (`alt_pending`, a new count in the boot: twelve queries
  now, `tests/tree/guide.php` A1). Never overwrites: `vergeml_ai_alt_pending`'s
  own `meta_value = ''` condition, asserted on a picture given an alt of its
  own. Step 5 was already gated on the rail from S4. The word on a picture is
  `vergeml_filing_confidence()` (core/filing.php): `by you` from the
  placed-by mark or a by-hand row, else the move's `why` — `ok` by score,
  `siblings` likely. `/librarian-why/{id}` answers `confidence` and `word`;
  the modal's section shows it as `.vgml-why-word` beside the label; the
  media list's line carries it after the folder (`.vgml-word`, one query a
  page for the moves rows).

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

---

# Phase C — the fill, honest (added 2026-09-15 evening, from Nathan's walk)

Nathan walked A.5 on the box (`docs/handoffs/2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md`):
612 placed, 30 questions, a 61-picture group "look like server racks" that
held 9 racks and 16 telecom towers, and *Put in Server racks* took all 61.
The engine's A.1–A.4 hold on their fixtures; the box's real profiles broke
the assumption under them: `infrastructure` sits on five folders, `people`
on three, `computer hardware` on two, so the matcher ties and abstains
(109 margins of 388 residue), the residue lumps by a 0.5 cosine to a fixed
seed and labels the lump after its largest minority, and the screen hides
every error and rebuilds everything twice per press. Four tasks. **FLOOR,
MARGIN and SURE do not move**: what changes is what a class hit is worth,
what a tie means, how a group is formed and named, and what one press costs.

**Sessions.** S7: C.1 + C.2. S8: C.3 + C.4. S9: C.5 (the second library,
walked). S10: the catalogue-scale epic's stories in order
(`docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`), then
the Folders polish card, then B.6.

**The sample's verdict, 2026-09-16** (the sheet, Nathan's marks): sure
14/30 = 47 % against 95; likely 12/30 = 40 % against 80, 11 too broad. Of
the 34 misses: 17 shared-class ties (C.1), 3 a kind word as a class and 7
a profile that means something Nathan does not (C.4), the rest vector
noise under 0.70. Handoff `2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md`.

**After C.1+C.2, same seed, dry (2026-09-16, S7):** sure 22/30 = 73 %; likely
7/30 = 23 %, 14 too broad (11 of them Hardware at 0.60–0.63: no child for a
desktop). The 8 sure misses: 3 Satellites `diagram`, 3 Components profile
(both C.4), 106552 launches (a canon fold bug, C.4), 106405 attendees in
People. Handoff `2026-09-16-s7-the-engine-honest.md`.

**Stop points (Nathan).** The box after his walk stays his (61 in Server
racks by hand, 327 in To sort) unless he says undo. K = 8 questions asked
at most, the rest one card — his number to change. C.5 costs real
describes on a second library — his call and his cost.

**Where fragility persists, and what in this phase answers it** (the
2026-09-16 review; every item below is a task line, not a hope):

| fragile seam | why it stays fragile after C.1–C.4 | answered by |
|---|---|---|
| three free-text models must agree (describer words, planner classes, group names) | C.4 makes them tolerant, not deterministic; a model update or a Dutch library shifts the words and nothing notices | **the baseline gate** (C.1, gate 5): the dry run's tally per library stored and compared on every engine, prompt or planner change |
| a profile is a guess about the owner's intent (Components ≠ "PC internals") | a better planner is still a guess | **classes on the tree** and **profile undo** (C.4) |
| one library, one shape (1,000 tech photos, 20 folders) | every constant and the cap were calibrated there | **C.5**, a second real library, before launch |
| a hand placement is law forever (`placed_by = user`) | the majority rule shrinks the trap; a person can still be wrong | **"placed by hand" review** on the media list (C.3) |
| kinds have no home (screenshots, diagrams, documents) | C.2 asks about them; nobody can file them well | known hole for launch; the planner's `kinds` stays a stop point |
| the screen is judged on a 36-plugin box (2.9 s a round trip) | C.3's win is measurable only on Playground | C.3 measures both; the box's plugin load is Nathan's to trim |
| the tests prove fixtures, not the field (A.1–A.4 were green while the box was wrong) | a mutation check cannot see reality | **the sheet is a gate**: every C task ends with the 60-picture sample re-taken on the same seed and its two numbers in the handoff |

## C.1 · The matcher tells folders apart — Opus (done 2026-09-16, S7: 1/k, second phrase 0.85, leaf name 1.0, either/or; box fresh dry run margin 109 → 39, sure 499 → 264; the baseline gate's outcome band re-taken; handoff 2026-09-16-s7-the-engine-honest.md)

- **Files:** `core/filing.php` (`vergeml_filing_pick`, `_profiles`,
  `_settle_claims`, `_questions`), `core/folder-talk.php` (the run's tally
  and the questions build for a new kind), `tests/filing/pick.php`.
- **Behaviour:** (1) a class held by k folders counts 1/k on each
  (`'shared' => [class => k]` computed once in `_profiles`), so a specific
  hit beats a shared word; (2) the picture's second phrase weighs 0.85, the
  first 1.0; (3) a folder's own leaf name matching a phrase exactly scores
  1.0 wherever it sits in the list; (4) a margin between two folders of
  *different* parents returns `children => [best, runner]` and becomes a
  question of kind `either` — "N pictures: Hardware or Server racks?" with
  `put-in:best`, `put-in:runner`, `split`, `leave`, `show-me` — tallied
  beside `siblings`; (5) a pick gated on every folder by kind carries its
  kind out (`why gated`, `kind`) so C.2 can group it.
- **Proof:** `pick.php` rows: "server rack; computer hardware" against
  Hardware[computer hardware, computer] and Server racks[infrastructure,
  server racks] → Server racks, `sure`; four folders sharing
  `infrastructure` + one also `server rack` and a picture "server rack;
  infrastructure" → that one, `sure`, no margin; a cross-parent margin →
  `children` set; `either` shape in `residue.php`. Mutations: the 1/k
  weight removed → the four-folder row red; the second-phrase weight
  removed → the Hardware row red; the cross-parent branch removed → the
  `either` row red. `tools/box-refile-all.php` dry run: margin well under
  109, printed beside today's line. **The baseline gate (new):**
  `tools/filing-baseline-check.mjs` grows a second band — the dry run's
  outcome tally (fits / sure / likely / siblings / nothing by floor,
  margin, gated) per library, stored in `tests/tree/filing-baseline.txt`
  beside the score band; a change of more than 3 % in any outcome fails
  the check and prints both lines. Re-taken deliberately after C.1 (the
  point is that C.1 moves it), then frozen: C.2–C.4, every prompt change
  in the service and every describer change must leave it green or
  re-take it on purpose with the reason in the handoff. **The sheet (new
  gate):** `tools/box-folder-quality.php` re-run on the same batch seed
  after C.1 — the two precision numbers beside 47 % / 40 % in the handoff.
- **Mirror:** A.1; the S1 handoff's line that all 202 margins were
  shared-word ties; `tools/filing-baseline-check.mjs` (the score band it
  already keeps, and why a band and not a value).
- **Copy:** the `either` sentence: "%1$s pictures: %2$s or %3$s?" — pills,
  no prose.
- **Do not:** move the three constants; re-profile the box's folders to
  make the numbers look better (C.4 fixes the planner; until then the
  test is the fixture and the dry run).

## C.2 · The residue, grouped and labelled honestly — Opus (done 2026-09-16, S7: kind first, 0.95 / 0.8, majority class with share, K = 8, 60 % put-in; the 61 is gone, but on today's residue of 435 the cap folds 280 into the small-groups card — see the handoff)

- **Files:** `core/filing.php` (`vergeml_filing_residue_groups`,
  `_questions`, new `vergeml_filing_group_nearest`), `core/folder-talk.php`
  (`vergeml_talk_questions_build`, `_question_text`; `state.residue` carries
  `id => nearest`), `tests/filing/residue.php`, `tests/tree/copy.mjs`.
- **Behaviour:** kind first: screenshots, illustrations, diagrams,
  documents, logos each form their own group and never merge across
  kinds; merge only near-identical phrases (`class_match ≥ 0.95` or
  centroid cosine ≥ 0.8), centroid recomputed over the merged vectors;
  after merging, `class` = the majority phrase with its `share`; the
  sentence is "N look like X" only from 70 % up, else "N mixed, mostly X";
  `GROUP_TINY` 5; the K = 8 largest readable groups are asked, the rest are
  one card "N more, in small groups" with leave / show-me; `put-in:X` only
  when X is the per-picture nearest for ≥ 60 % of the group and passes X's
  kind and audience gates. "I can't read" is renamed for what it is:
  "N with nothing to go on".
- **Proof:** `residue.php`: seven screenshot facts among photo-like
  vectors → their own group, never merged; a 3 at cosine 0.6 does not
  merge, at 0.85 does; seed 3 + incoming 5 → class flips, share 0.625,
  sentence "mixed"; 12 groups → 9 cards; nearest map 9/61 → no put-in,
  40/61 → put-in. `copy.mjs` pins the three strings. Mutations: the kind
  key removed → row 1 red; `GROUP_NEAR` back to 0.5 → the 0.6 row red; the
  cap removed → the 12-groups row red; the majority rule removed → the
  9/61 row red.
- **Mirror:** A.2; the 61 as the fixture's shape (racks 9, towers 16,
  robots 3, screenshots 7, singletons).
- **Copy:** the four sentences above, ≤ 8 words each.
- **Do not:** call the namer for a group the cap will fold (spend);
  let a group's name be an existing folder's name without offering
  `put-in` for that folder under the majority rule.
- **Gates carried from C.1:** the baseline check green (or re-taken with
  the reason); the sheet re-run on the same seed, numbers in the handoff.

## C.3 · One press, one honest answer — Opus (done 2026-09-16, S8: the error on the card, the moves under deferred counting with one flush (box 50 pictures: 593 → 729 queries with the defer removed), the answer response without the questions, the card patched in place and the tree rendered once, the strip's "2 more" at 48, keys 1–4 / Enter, Placed by hand in the list's folder filter with the drag-out clearing the mark, the why card's "No folder inside it fits better"; timed on both sites — see the S8 handoff)

- **Files:** `js/vergeml-folders.js` (`onAnswer`, `tookAnswer`,
  `refreshTree`, `renderQuestions`, `renderFill`), `core/guide.php`
  (`vergeml_guide_rest_answer`), `core/folder-talk.php`
  (`vergeml_talk_answer`), `tests/ui/folders.spec.mjs`.
- **Behaviour:** an error from `guide/answer` lands on the card
  (`.g-q-result`), never in the hidden change line; the move loop runs
  under `wp_defer_term_counting` and flushes counts once; the answer
  response carries the answered question, `made`, `undo` and the status —
  not all thirty questions with thumbnails; the client marks the card
  answered locally, appends the next card without rebuilding the grid,
  rebuilds the tree once (`setNewIds` before `setTree`, no re-append of
  the tree node); the strip shows a count when capped at 48; keyboard:
  1–4 answer the focused card, Enter opens the strip. **Placed by hand,
  reviewable (new):** the media list's folder filter gains *Placed by hand*
  (the `_vergeml_placed_by = user` rows — the 61 towers-in-Server-racks
  case), the word `by you` already on the row; a picture dragged out of
  that folder by the owner clears the mark, so the next fill may judge it
  again. An answer's result line says how many it placed by hand ("61
  placed by you — review them" linking to that filter).
- **Proof:** `folders.spec`: 30 planted questions; a 409 intercepted →
  its text on the card; `guide/answer` response has no `questions[].sample`;
  one `MutationObserver` childList batch on `.vgml-list` per answer; the
  answered card is the same DOM node after; a timing row printed **on both
  sites** — the box (the customer's worst case, 36 plugins) and Playground
  (the plugin's own cost) — with the plugin's own answer round trip under
  400 ms at 30 questions. `modes.spec`: the filter lists exactly the
  placed-by-hand rows; a drag out clears the mark (`filing-trail` row).
  Mutations: the defer removed → the query-count row red; the re-append
  restored → the observer row red; the mark not cleared → the drag row red.
- **Mirror:** S5's questions view; `tests/ui/folders.spec.mjs` "the Fill
  step asks".
- **Copy:** none new.
- **Do not:** measure on the box alone — its REST round trip is 2.9 s
  with 36 plugins; Playground gives the plugin's own number.

## C.4 · The planner speaks the describer's words — Opus (done 2026-09-16, S8: both prompts carry the library's top terms with counts and the one-folder rule (service 954a84b, live on ai.vergelabs.nl); the plugin sends the vocabulary and the samples' object; canon fold (spelling, irregular and -ies/-es plurals, the object's head noun), cosine floor 0.6, kind words to kinds, a neighbour's first class dropped and recorded; class pills on the tree with × and #word, the earlier profile kept a day and Unconfirm → Restore; baseline re-taken: sure 264 → 340, likely 262 → 220; the dry sheet on seed 133: **sure 87 %, likely 57 %** (was 73 / 23); the misses are the profiles (Components, Launches) and a missing Watches folder, Nathan's to press through the screen)

- **Files:** service `lib/anthropic.ts` (`profileFolders`, `planFolders`
  prompts), `lib/describe.ts` (schema `.describe` for `object`),
  `app/api/ai/folders/*`, plugin `core/filing.php`
  (`vergeml_filing_profile_ask` sends the vocabulary; `class_match` canon
  fold), `core/folder-talk.php` (`vergeml_talk_samples` carries `object`),
  `tests/filing/pick.php`, service `lib/*.test.ts`.
- **Behaviour:** the profile and plan prompts receive the library's top
  object terms with counts (as the guide summary already does) and the
  rule "use these words verbatim; a class belongs to one folder; a parent
  lists only what none of its children claims"; the plugin drops from a
  returned seed any class already rank-0 on a stored profile and logs it;
  the describer's schema description matches its prompt ("specific;
  class"); `class_match` folds British/American spellings and irregular
  plurals from a small table and floors the cosine path at 0.6; a class
  that is a kind word (`diagram`, `screenshot`, `illustration`, `photo`,
  `document`, `logo`) is never accepted as a class — it moves to `kinds`.
  **Classes on the tree (new):** on the Tree step, a folder's row carries
  its classes as quiet pills after the name (`vgml-meta` grammar, three at
  most, "+2"), so *Components · electronics component · semiconductor
  component* is read before it is confirmed; a pill is removable (`×`, the
  class leaves the profile, `by: you`) and a class can be typed in the
  row's `+` editor with a leading `#` (`#pc internals`). **Profile undo
  (new):** the previous profile is kept in term meta
  (`_vergeml_filing_profile_prev`); Unconfirm offers *Restore the earlier
  classes* when one exists; a confirm's planner answer that replaced a
  profile is undoable for a day like a Move.
- **Proof:** vitest: the prompt text carries the terms and the one-folder
  rule; `describe.test.ts` asserts the schema description contains ";";
  `pick.php`: `class_match('data centre','data center') === 1.0`; a
  planner answer with a neighbour's class → dropped, logged; a planner
  answer with `diagram` as a class → `kinds` gains it, `classes` does not.
  `folders.spec`: the class pills on a confirmed-tree row match the stored
  profile; `×` on one removes it from the draft's classes and the fit
  re-runs; `#word` in the add editor lands in classes; Unconfirm → Restore
  puts the previous profile back (the `sticky` suite's shape). Mutations:
  the rule line removed → the prompt test red; the fold table emptied →
  the pick row red; the kind-word guard removed → the `diagram` row red;
  the previous profile not written → the Restore row red.
- **Mirror:** `core/guide.php:513-528` (the summary's top terms);
  `lib/name-group.test.ts` for the prompt-test shape.
- **Copy:** none on screen.
- **Do not:** re-profile the box's folders by script to prove it (a
  planner call, metered; Nathan's call, through the screen).

## C.5 · A second library, a different shape — Opus prepares, Nathan pays and judges (walked 2026-09-16, S9: 626 product photos under a 318-folder catalogue on /var/www/ms2, €3.00 of describes + €0.23 of profiling; dry band fits 507/626, sure 482, either 64; five findings, none the matcher's, became the epic `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`; the sheet: sure 25/30 = 83 %, likely 11/30 = 37 % (six likely wrongs in a planner-noised Garden), the constants unmoved — S10.5; handoff 2026-09-16-s9-the-second-library.md)

- **Files:** `tools/box-seed-*.php` (a new seed script for the second
  library, in the shape of `tools/box-seed-technews.php`),
  `tests/tree/filing-baseline.txt` (a second band, keyed by library),
  `docs/handoffs/` for the numbers.
- **Behaviour:** a second real library on the box's network site
  (`/var/www/ms2`, so the tech library is untouched): a different shape on
  purpose — a shop's catalogue (300+ folders, few pictures each, `photo;
  product` everywhere) or a portrait photographer's (`photo; person` on
  every row, folders by client and year). Describe it (say the cost first:
  ≈ €0.0048 a picture, so 500 pictures ≈ €2.40), propose, confirm, fill
  through the screen — Nathan presses, as A.5 — and take the sheet. The
  cap K, the grouping thresholds and 1/k are judged there, not moved by
  feel: if one fails on the second shape, the handoff names which and the
  fix is a rule that reads the shape (folder count, pictures per folder),
  never a new constant.
- **Proof:** the second library's line in `filing-baseline.txt`; the
  sheet's two numbers for it in the handoff beside the tech library's;
  `folders.spec` green on the second site at 1600 and 1280 (the word
  budget holds at 300 folders because the parents are closed).
- **Mirror:** A.5 (the walk), this phase's sheet gate, `hetzner-box-fixtures`
  memory for the network site.
- **Copy:** none.
- **Do not:** describe the whole of a large second library to prove a
  point (a subset of 500 says the same); run it on the tech library's site
  (the baseline there is the reference).

# Phase D · Folders at catalogue scale, and the number pushed up (2026-09-16, from the C.5 walk)

The spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(BMAD shape, stories S10.0–S10.11 with their tests and mutations). Two
halves. The first is what the 318-folder shape broke or stalled on one
walk — none of it the matcher's; the second is where the number comes from
and how it rises on both kinds of library. The bounds today: **sure 34 %**
where the folder names are not the pictures' words (the tech library) and
**77 %** where they are (the shop — a fixture that flatters the name match,
Nathan's point; S10.5b measures a tree the fixture's author did not write).

**The rule of this phase (Nathan, 2026-09-16):** progress is always
visible — every long step shows a working state with a moving number, a
disabled button says why, a stalled run says so (S10.0, one component,
mock-first, first in every session).

**Sessions.**

- **S10 (done 2026-09-16, `docs/handoffs/2026-09-16-s10-folders-at-catalogue-scale.md`)** — S10.0 progress everywhere (mock → yes → build: one row, the shell's bar, the stall pill); S10.1 the
  confirm asks only what a planner can add — refined by the shape: leaves under a parent never go, parents and the top level go unless a library word (shop 308 → 37, one batch, 0 credits); S10.2 a fill that cannot stall
  behind a cron lock — the cause was the nudge posting a key it never held; the poll's pass is the guarantee; S10.3 the dry run at any shape (250 k pairs, a job above it). Gates: both baselines 4/4 (the shop re-taken: Nathan's answers made Illustrations and Diagrams), the
  confirm on the 322-folder tree 1 batch and 0 credits (measured read-only); `folders.spec` on ms2 — see the handoff.
- **S11 (done 2026-09-17, `docs/handoffs/2026-09-17-s11-the-method-back.md`)** — the method as found (BMAD never here; the profiles in the home tar), `/code-review` of S10 (four fixes, each a story), the owner's round (`round.spec`, the progress-row bug), S10.5 rules 1 and 2 (the path names, the either/or fold), the sweep premise corrected (stale is prompt-hash only), the describer eval (no switch).
- **S12 (done 2026-09-17, `docs/handoffs/2026-09-17-s12-the-tree-and-the-list.md`)** — Nathan's three answers (profiles file back, BMAD a pilot later, the prompt sweep the button only: both auto-starts gone, `background.php` G2–G3); × on a folder's last word is an explicit empty (`nowords`, a name-only profile, Restore brings the words back); S10.4 the fold pair first in the head and a closed parent's children as chips on hover (mock → yes → build); S10.6 the media list opens on the pictures (one row, the panel the viewport's height, the tree first; ms2 first row 453 → 180 px). Gates: tech 4/4, the shop re-taken (Nathan's confirm of 09-16 17:23 UTC re-profiled 66 folders; Cameras' plan class ties its name-only child — a profile finding for S10.7).
- **S13 (done 2026-09-17, `docs/handoffs/2026-09-17-s13-the-fill-learns.md`)** — S10.7 the fill learns from its own placements (a folder of three or more read over its members' words two or more say, the word owned by the folder holding most of them, a locked folder owning none; round 2 over the leftovers); S10.9 the picture's own words (file and title; the alt only when a person wrote it — the describer's doubled the ties); S10.5 rule 3 (the head noun capped only where the class half is the phrase's own modifier and names a folder by its first class or leaf). Tech read dry: sure 340 → 637, floor 370 → 147, margin 36 → 104; shop: sure 467 → 491, siblings 25 → 16, margin 60 → 71. The number is not the verdict: the batch-133 sheet re-read says the folders hold the fill's own misses (sure: right kept 13, wrong kept 13). The S13 dry sheet waits for Nathan's marks; neither band re-taken until then. S10.5b not reached (Nathan's yes on the catalogue).
- **S14** — S10.5b the foreign tree on the same pictures (~36 credits, 0 after S10.1, no describes; Nathan's yes on the catalogue first); the two bands re-taken with the reason after the marks; then S10.8 as below.
- **S14** — S10.8 file by the product (WooCommerce) and the flipped order
  of the steps for structured libraries (the rail mocked); S10.11 the
  opt-in model pass on what is left.
- **S15** — S10.10 the proposal from the pictures: groups to name and nest
  (per kind first, so a pack's documents get their own branch — the
  "kinds have no home" hole closed for bare packs), the proposal drawn as
  thumbnails, the pack's own signals, the tree's size from the shape. Gate: the tech library re-proposed from groups, the sheet
  re-taken on round 1 — the number beside 34 %.

**Stop points (Nathan).** Every mock before its build; every credit said
before it is spent (the confirm on ms2 ≈ 36 credits today, 0 after S10.1;
the opt-in pass a credit a picture); the tech library's baseline frozen
except by re-take with the reason; the shop's band within 3 % or re-taken;
`vgmls9` deleted when ms2's walk is over. Held behind, unchanged: B.6, the
`filing-trail.php` row, the Folders polish card's date folders and desktops.
