# Handover — 2026-09-15, every-picture-a-home Phase A, S2: A.3 + A.4 (Opus 5)

One session in `plugin`, lean. Both tasks built, tested first, one mutation
each on the box copy (two for the walk), deployed to the box and pushed
(`a4b41cc`, `3978dca`). Nothing on the box stays changed: the sticky suite
removes what it makes; the walk undoes its Move and then applies its own
snapshot over the undo, asserting the library, the folders, the profiles,
the options and the moves table as they were. Spend: 0 credits. Two metered
calls a walk (one `/folders` profile, ~10–20 `/name-group`), none debited.

## A.3 — confirm, lock, sticky

`core/guide.php`:

- The session carries `tree` (`editing` | `confirmed`), out to the browser in
  `vergeml_guide_session_out`. `POST /guide/confirm` → `vergeml_guide_confirm()`:
  (1) no draft → one is built from the live tree, so a person who never
  proposed can confirm what they have; (2) the planner is asked **once**,
  through the new `vergeml_filing_profile_ask()`, only about folders the
  draft gives no classes for **and** that carry no plan in term meta; its
  answer lands in the draft (classes, kinds, audience, matches), and a folder
  the proposal described is never touched — the suite feeds the planner a
  hijack answer for a described folder and asserts it is ignored; (3) every
  draft folder that exists and has classes is seeded into term meta
  (`vergeml_talk_seed_profile`, what the Move seeds), so preview and run read
  one profile. A folder the Move will make is seeded when made, from the same
  draft. When the planner changed the draft, the fit is recomputed. Returns
  `{ session, profiled, version }`; 502 when the planner was needed and failed.
- `POST /guide/unconfirm` → editing; 409 while a fill runs.
- Confirmed, `/guide/rule`, `/guide/turn` and a draft through
  `POST /guide/session` answer 409 `The tree is confirmed. Unconfirm it to
  change it.` (`vergeml_guide_confirmed_refusal()`). `reset` still works.
- `/guide/apply` is **not** gated on confirm (the card did not ask; B.2's
  screen decides whether Fill is reachable unconfirmed). After a fill ends
  the tree stays `confirmed`; unconfirm returns it.

`core/rest-tree.php` — the hand move is `vergeml_rest_assign` (`/assign`),
not `rest-folders.php` as the card's grep hint said: a gain in the librarian's
taxonomy marks `_vergeml_placed_by = user`. A removal marks nothing. The
list screen's bulk "Move to" (`core/media-list.php:608`) does **not** mark —
out of the card's scope; a two-line follow-up if a bulk move should be sticky.

`core/filing.php` / `core/folder-talk.php`:

- **Locked both ways.** S1 gated a locked folder *into*; a picture *in* one
  was still movable (and, worse, evicted: the eviction loop evicts from any
  gated folder, and `locked` is a gate). Now facts carry `in_locked` — the
  run's row reads it in SQL (a correlated count over termmeta), the preview
  computes it from `in_terms` — and `vergeml_filing_pick` answers
  `nothing` / why `locked` before scoring. `vergeml_filing_kept( $pick )`
  (placed or locked) is what the tally counts as `kept` and the run leaves
  alone with no trail row. Preview = run still holds (E3 in the walk).
- A hand-placed picture in a folder the Move removes follows that folder's
  fallback (trail why `placed`) rather than dropping out when the term goes.
- `vergeml_filing_profile_existing` now calls `vergeml_filing_profile_ask`.

## A.4 — the walk, by script

`tools/box-fill-walk.php` (+ `.sh`, `node tools/verify.mjs fill-walk`):
snapshot → undo any open Move (none: Nathan's 09-14 record had expired, so
the walk starts from the library as it is, 487 / 513) → the fixture (the
session's draft of 2026-09-15, 20 folders with the 09-14 planner's classes;
five without: 2026, Cooling, Interviews, Launches, September) → confirm
(the planner asked about 2026 and September, 14 s, described neither) →
`/guide/rule` 409 → preview → `/guide/apply`, the wp-cron nudge answered
with a `WP_Error` and the hook cleared, passes driven in-process → tallies
compared → questions answered (keep-parent / new-folder ≥ 20 / leave) →
status → To sort → trail → undo → snapshot applied → nine "as it was" rows.

The box's numbers, 2026-09-15, both preview and run identical:

```
looked 1000 = fits 510 (sure 444, likely 76) + siblings 10 + nothing 480 (floor 243, margin 158, gated 79) + kept 0
38 questions: 4 sibling (Components/Phones 4, Conference talks/Interviews 1, Server racks/Cooling 4, Solar/Wind 1),
              34 residue (8 named ≥ 20 → new folders: 3D Printers 46, Data Center 44, Infrastructure 37,
              Renewable Energy 27, "Office Spaces" 26 (road bikes!), EV Charging 26, Trade Show 20, E-Waste 20;
              25 groups < 20 → leave; 45 I can't read → leave)
0 in no folder · To sort holds 234 · 1000 trail rows · undo: 1000 put back, 8 folders unmade · 115 s
```

Different from S1's line (488/25/487) because the fixture's classes are the
draft's, not the 09-14 `profile_existing` rewrite the box's term meta held
— the stop point's rule, and the box's profiles are back to the rewrite
after the snapshot restore. Only 10 siblings now (was 25): with the draft's
classes fewer child pairs tie. `siblings` count 10 and 4 sibling questions
is consistent (one question per parent).

## Gates

- `node tools/verify.mjs filing` → 12/12, 18/18 (unchanged).
- `node tools/verify.mjs sticky` (box) → 26/26: the hand move through
  `/assign` survives a fill that moves its twin; the locked folder keeps
  its three and gains none; confirm stores the profiles, asks the planner
  once and ignores its answer for a described folder; rule / turn / session
  409 with the line; unconfirm → turn 200; re-confirm asks nothing.
  Mutation: the `placed_by` check removed from `vergeml_filing_pick` → D1
  red (D5, D6 with it), 23/26.
- `node tools/verify.mjs fill-walk` (box) → 22/22, ~2 min. Mutations:
  `leave` removed from `vergeml_filing_answer_plan` → "0 in no folder" red
  (216 in no folder, To sort empty) and the library still restored; the
  sibling branch removed → "at least one sibling question" red (0), tallies
  still identical. **The card's claim that the sibling mutation reddens
  "0 in no folder" was wrong** — the residue path answers every picture
  whatever the pick said — and the plan's A.4 line is corrected.
- `surface roles escaping` → 26/26, 19/19, 9/10 (the known
  `js/vergeml-folders.js:671` row). Regenerated security-surface and
  security-db-calls; roles' counts 78 live / 80 static with the two routes.
  Also db-calls 11/11, hosts 13/13, globals 7/7, copy 52/52.
- Deploy: `node tools/deploy.mjs --check` → box up to date, 137 files
  verified after every mutation was put back.

## Found, not done

- The confirm profile call counts toward the service's 40-a-day planner cap
  per site (`meterCall('folders', 10)`, not debited). Each walk run spends
  one of those; three ran today plus three mutation runs.
- The surface lists `/guide/confirm` with `outbound http` (the planner ask,
  behind `manage_categories`) and neither new route in the "a person must
  read" list — they take no id. S1's note on `/guide/answer` stands.
- The naming call gave "Office Spaces" to 26 road bikes — the two-word cap
  over eight captions can miss. A.5 judges it with Nathan.
- The walk's cron race: apply schedules the event and fires a loopback
  before the walk clears the hook; the loopback is refused by the filter,
  but a real page load on the box in that ~100 ms window would run a
  parallel pass. It would show as E3/E4 red, never silently.
- `state.residue` holds 480 ids; the questions carry 480 more; two options
  of ~30 KB each on the box.

## Next — Phase B, S3: B.1, on Opus, in `plugin`, from a fresh session

Nathan has ~8 % Fable today; B.3 and B.4 are the Fable candidates, and S3
is mocks (Opus). Keep Fable for S4/S5 if the 8 % is still there then.

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Every picture a home — Phase B, S3: B.1 two mocks, approved",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "docs/superpowers/mocks/2026-09-15-fill-questions.html",
    "docs/superpowers/mocks/2026-09-15-step-rail.html",
    "docs/superpowers/mocks/shots/**",
    "docs/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-phase-a-s2-confirm-lock-sticky-and-the-walk.md",
    "plans/every-picture-a-home.md",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
    "docs/superpowers/mocks/2026-09-14-folders-glance.html",
    "docs/superpowers/mocks/2026-09-06-ai-screen.html",
    "core/folder-talk.php (vergeml_talk_question_text, vergeml_talk_questions)",
    "tools/shoot-mock.mjs"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Both mocks approved by Nathan in the conversation, screenshots posted, before the handoff; the word 'approved' quoted",
    "No js/ or core/ change; nothing deployed",
    "Real numbers from the box's walk (this handoff): 4 sibling questions (Components/Phones 4, Server racks/Cooling 4 …), 46 look like 3d printers, 45 I can't read; the spec's sentences verbatim",
    "No eyebrow, no middot string, no paragraph; ≤ 80 words a state, counted in the handoff",
    "Banned visuals stand: no numbered-circle step flow with connecting lines for the rail — five pills"
  ],
  "gates": [
    "docs/superpowers/mocks/2026-09-15-step-rail.html: the glance mock with the rail (Describe ticked, Tree current, Fill/Alt text/Rename quiet), 'This is my tree' primary; screenshot at 1600×1000 and 1280×800",
    "docs/superpowers/mocks/2026-09-15-fill-questions.html: asking state (tree filling, pill row placed · sure · likely · questions · to sort, three question cards with eight thumbnails and their answer buttons) and done state ('1,000 in folders · 0 to sort', Undo); screenshots",
    "Word counts ≤ 80 per state, printed by the shoot script or counted by hand and written in the handoff",
    "Nathan: 'approved' for each, quoted"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-phase-a-s2-confirm-lock-sticky-and-the-walk.md,
then plans/every-picture-a-home.md task B.1 and the spec's §2 Step 3 and §3.
State which model you are. This session is Phase B S3 of
every-picture-a-home: the two mocks, approved in this conversation before
the handoff. Write the card from the handoff to .harness/active.json before
the first edit. Lean, no subagents. Show each mock as a screenshot and wait
for my word on it. End with a handoff in docs/handoffs/ carrying the S4 card
(B.2 + B.3).
```

What S3 inherits, concretely: the question sentences are
`vergeml_talk_question_text()` (`%s pictures fit both A and B.` / `%s look
like robot arms` / `%s I can't read`; answers `Keep them in P`, `Split them
by best score`, `Let me look`, `New folder N`, `Put in F`, `Leave them`,
`Show me`); the screen reads `/guide/questions` → `{ questions[ id, kind,
count, name, term_id, text, answers{key: label}, sample[{id, thumb}],
answered, result ], status{ open, unfiled, running, done } }` and
`/guide/progress` → `report.tally` (looked, fits, siblings, nothing, kept,
sure, likely, why{floor, margin, gated}, by_term); the session carries
`tree` (`editing` | `confirmed`) for the rail. Thumbnails for the mock: the
box's walk printed the question ids; `GET /vergeml/v1/guide/questions` on
the box has none open now (undone) — take eight from the library grid.
