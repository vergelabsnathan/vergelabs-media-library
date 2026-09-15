# Handover — 2026-09-15, every-picture-a-home Phase A, S1: A.1 + A.2 (Opus 5)

One session in `plugin` and `service`, lean. Both tasks built, tested first,
one mutation each, deployed to the box and pushed. Nothing on the box was
filed: the one Move-shaped thing run there was a read-only scoring pass.
Spend: 0 credits (no describe, no propose, no naming call reached a model —
the residue is only named when a real run ends).

## A.1 — one filing path, three outcomes

`core/filing.php`:

- `vergeml_filing_pick()` now returns `outcome` (`fits` | `siblings` |
  `nothing`), `term_id`, `parent_id`, `confidence` (`sure` ≥ 0.70,
  `likely` below; `VERGEML_FILING_SURE`), `children` (siblings: the two),
  and keeps `why`, `score`, `runner_up`, `runner_score`, `nearest`,
  `scores`, `gated` for every caller that read them before (auto-file, the
  librarian's veto, the rule fit, the trail). `why` gains `siblings` and
  `placed`.
- **Siblings**: best and runner-up under one parent, inside the margin →
  placed in the parent, `likely`. Decided on paths (as descent is), so the
  preview's synthetic ids and the run's real ids answer alike; a parent that
  is not a folder (slash-named orphans) or is locked → still `nothing`.
- A profile with `locked` (term meta `_vergeml_locked`, read by
  `vergeml_filing_profiles`) is gated out as `locked`. A picture whose facts
  say `placed_by = user` returns `nothing` / `placed` — the run counts it as
  `kept`, writes no trail row, never evicts it. The read side of
  `_vergeml_placed_by` is wired everywhere the index is read for filing
  (run slice, preview, `box-refile-all`): a LEFT JOIN on postmeta. **A.3
  writes it** on a hand move and on confirm.
- `vergeml_filing_count( $profiles, $rows, $deadline )` → `picks` +
  `counts` (`vergeml_filing_tally_fresh()` shape: looked, fits, siblings,
  nothing, kept, sure, likely, why{floor,margin,gated}, by_term). The
  preview (`vergeml_guide_draft_fit`) calls it over the library; the run
  (`vergeml_talk_refile_run`) calls it per slice and acts on the picks,
  tallying with `vergeml_filing_tally()`. `fit.tally` and `report.tally`
  carry it to the screen (Phase B reads them).

`core/folder-talk.php` / `core/guide.php`:

- **No planner call in a fill.** `vergeml_talk_apply` no longer sets
  `profile`; the run no longer calls `vergeml_filing_profile_existing`.
  Instead apply seeds every folder the draft describes
  (`vergeml_talk_seed_profile`: made, renamed, found by name, *and kept*)
  with the draft's classes/kinds/audience/matches; a folder the draft says
  nothing about keeps its stored profile. `vergeml_guide_draft_profile`
  does the same in memory — until today a kept folder scored its stored
  profile in the preview while the run re-profiled it: that was 816 vs 487.
- Proven on the box, read-only (scratch `box-one-path.php`): the run's
  tally over `vergeml_filing_profiles()` and the preview's over the draft's
  in-memory profiles are **identical** — 1,000 = 488 fits (459 sure, 54
  likely) + 25 siblings + 487 nothing (251 floor, 202 margin, 34 gated),
  same landed-per-folder distribution.
- Only 25 siblings, not the 232 the 09-14 handoff counted: the 202 margin
  ties on the box are **cross-parent** — `Space / Satellites` vs
  `Data centres / Cooling` on a shared "infrastructure" class, `Energy /
  Batteries` vs `Hardware / Phones` on "electronics". Those are the stored
  profiles' classes (17 of 20 folders carry a plan seed; the 09-14 Move's
  `profile_existing` wrote them). The floor and margin did not move (stop
  point); those pictures now come back as residue questions ("smartphone"
  group → Put in Hardware / Phones) rather than as nothing. A.5 judges the
  classes.
- A locked folder is not deleted by a Move that leaves it out of the draft
  (`$remove` skips it): To sort must survive the next Move. Unlock to
  delete.

## A.2 — the residue, grouped, named, answered

- `vergeml_filing_residue_groups( $facts )`: by the describer's first
  phrase (plural folded), under 5 merged into the nearest group by centroid
  cosine ≥ 0.5 (largest first), still under 3 → one unreadable group,
  last. `vergeml_filing_questions( $groups, $siblings, $names, $nearest )`
  builds the shape `{ id 's:<parent>' | 'r:<n>', kind, term_id, children,
  count, sample (8), ids, name, class, unreadable, answers }`.
  `vergeml_filing_answer_plan( $q, $answer )` is the pure decision (moves,
  make, placed_by, show, answered); the suite asserts it on a map.
- The run's state carries `residue` (ids), `siblings` (parent → ids →
  best child, children counts), `tally`, `questions`, `names`.
  `vergeml_talk_refile_finish` builds the questions
  (`vergeml_talk_questions_build`): facts read back off the index, each
  group ≥ 5 named once through the service (`vergeml_talk_name_group` →
  `/name-group`, cached in `state.names` by member hash; a failed call is
  not cached, the class word stands in), `put-in` offered with the folder
  nearest the centroid (`vergeml_talk_nearest_folder`, ≥ 0.5, never a
  locked one).
- `vergeml_talk_answer( $id, $answer )` executes: `new-folder` makes the
  term at the top (or reuses one by that name), `put-in:<term>` checks the
  term exists, both mark the pictures `_vergeml_placed_by = user`; `leave`
  is **To sort** (`vergeml_talk_to_sort`: slug `to-sort`, made on first
  use, `_vergeml_locked`); `split` files each sibling picture into its own
  best child; `keep-parent` moves nothing; `show-me` returns the ids and
  answers nothing. Every move goes into the undo record (`files`, `made`,
  and a new `placed` list whose meta undo deletes) and the trail (`why`
  `user` or `answer`). 409 on a running fill or an answered question, 400
  on an answer the question does not offer.
- `vergeml_talk_question_text()` — the spec's sentences verbatim: "%s
  pictures fit both A and B." / "%s look like robot arms" / "%s I can't
  read"; answers "Keep them in P", "Split them by best score", "Let me
  look", "New folder N", "Put in F", "Leave them", "Show me".
- Routes: `GET /guide/questions` → `{ questions (with text, labelled
  answers, sample with thumbs, answered, result), status, version }`;
  `POST /guide/answer { id, answer }` → `{ result, questions, status, undo,
  version }`. `vergeml_talk_fill_status()` = `{ open, unfiled (DB count of
  described pictures in no folder), running, done }`, done only at 0 / 0.
- Service: `app/api/ai/name-group/route.ts` (licence, activation, limits at
  cost 1, `meterCall('name-group', 1)`, never debited — `/embed`'s shape),
  `lib/name-group.ts` (`nameGroupPrompt`, `capName`: two words, label
  stripped), `nameGroup()` in `lib/anthropic.ts` on the describe model,
  24 tokens, thinking off. **It shipped without its `/v1/name-group`
  rewrite** and answered 404 on Vercel; fixed in `1fb799f`, and
  `lib/v1-rewrites.test.ts` now fails for any `app/api/ai/**/route.ts`
  without a line in `next.config.mjs`.

## Gates

- `node tools/verify.mjs filing` → pick 12/12, residue 18/18. Runs
  **locally** through Playground's PHP (`php: 'wasm'` in verify.mjs, ~13 s
  a file; memory `local-php-suites-via-playground`). Mutations: sibling
  branch removed → pick rows 3 and 12 red; merge-under-5 removed →
  residue row 1 red (a "robotic arm" group of 3 survives).
- `../service`: `pnpm test` 32 files, 474 passed (11 new + 1 guard);
  typecheck clean. Mutations: `meterCall` removed → two route rows red;
  the rewrite line removed → the guard red.
- `node tools/verify.mjs surface roles escaping` → 26/26, 19/19, 9/10
  (the known `js/vergeml-folders.js:671` row). Also db-calls 11/11, hosts
  13/13, globals 7/7, copy 52/52. Regenerated: security-surface, -escaping,
  -db-calls; hosts gained the `/name-group` row by hand; roles' live count
  is 76 (78 static) with the two new routes.
- `tools/box-refile-all.php` on the box prints the outcomes line and they
  sum: `looked 1000 = fits 488 (sure 459, likely 54) + siblings 25 +
  nothing 487 (floor 251, margin 202, gated 34) + kept 0`.

## Deployed

- Plugin: `2fe45d9` on the box (deploy.mjs, php -l clean) and pushed.
- Service: `66290b9` + `1fb799f` + `1dfb092` pushed. Verified by identity,
  not status: `POST https://ai.vergelabs.nl/v1/name-group` with a bad key
  answers `401 {"error":"bad_key"}` (a route only the new build has; the
  build before the rewrite answered 404). The box's standalone service
  (`VERGEML_AI_SERVICE` 127.0.0.1:3100, what the box's plugin actually
  talks to) answers the same after `vps.yml` run 34946100329; pm2 shows
  `vgml-service` restarted 08:18:17 UTC. One Vercel build errored in
  between (`b99841c`, a test with an untyped import) and was replaced.

## Found, not done

- The `/guide/answer` row lands in the surface's "a person must read"
  list (unscoped capability on an id from the request): the id is a
  question id inside the site's own option, behind `manage_categories` —
  same class as `/guide/turn`. Worth a line in the security brief.
- The questions are built inside the last cron pass: one naming call per
  group ≥ 5, 20 s timeout each. On a 1,000-picture library that is ~10–20
  calls after the pass's 15 s budget; fine on the box, but a library with
  hundreds of groups would hold that cron request for minutes. If it
  bites, build the questions in a pass of their own.
- `state.residue` holds every unplaced id (513 on the box); on a 100k
  library that option grows to a few hundred KB, like the undo record.
- `vergeml_guide_draft_fit`'s preview lines still say "N too close to
  call" for the cross-parent margin only; sibling placements show in
  `fit.tally.siblings`. No copy was added (A.1 has no screen).
- `folders.spec.mjs` was not run (no `js/` change; Phase B's suite). Its
  planted-draft counts may shift by the kept-folder seeding — check when
  B.2 touches it.
- The `2026 / September` empty date folder from the planner is still in
  the tree.

## Next — Phase A, S2: A.3 + A.4, on Opus, in `plugin`, from a fresh session

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Every picture a home — Phase A, S2: A.3 confirm, lock, sticky + A.4 the walk, by script",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/guide.php",
    "core/filing.php",
    "core/folder-talk.php",
    "core/rest-folders.php",
    "tests/filing/**",
    "tools/box-fill-walk.php",
    "tools/box-fill-walk.sh",
    "tools/verify.mjs",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-phase-a-s1-one-path-and-questions.md",
    "plans/every-picture-a-home.md",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
    "core/filing.php",
    "core/folder-talk.php",
    "core/guide.php",
    "tests/filing/residue.php",
    "tools/box-why-walk.php"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The walk starts by undoing Nathan's Move on the box (487 / 513) and ends with the library as it was; nothing stays filed by script",
    "Confirm stores the draft's own classes as the profiles (what apply seeds today); the planner is asked only for folders the tree gives no classes for (a pasted tree) — never to re-profile a folder the proposal described",
    "The floor, margin and sure values do not change",
    "No screen work: js/ and css/ are Phase B",
    "A propose costs ~10 credits; use a fixture of the last tree where one exists, and say the cost when it does not"
  ],
  "gates": [
    "node tools/verify.mjs filing → still 12/12 and 18/18",
    "tests/filing/sticky.php on the box: a hand move survives a fill that would have moved it; a locked folder keeps its 3 and gains none; confirm → /guide/rule and /guide/turn answer 409; makes and removes its own pictures and folders; mutation: the placed_by check removed from vergeml_filing_pick → the first row red",
    "node tools/verify.mjs fill-walk on the box: undo → propose or fixture → confirm → fill → every question answered by script (keep-parent for siblings, new-folder for residue groups ≥ 20, leave for the rest) → 0 in no folder, To sort holds exactly the leftovers, preview tally = run tally, a trail row per picture; undo at the end; mutation: the sibling branch removed on the box copy → '0 in no folder' red",
    "node tools/verify.mjs surface roles escaping → green (escaping 9/10; roles counts 76 + whatever S2 registers)"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-phase-a-s1-one-path-and-questions.md, then
plans/every-picture-a-home.md tasks A.3 and A.4 and the spec's §2 Step 2
and Step 3. State which model you are. This session is Phase A S2 of
every-picture-a-home. Write the card from the handoff to .harness/active.json
before the first edit. Lean: build inline, test first, one mutation check
per task, no subagents. Stop points and gates are in the card. End with a
handoff in docs/handoffs/ carrying the S3 card (B.1, or B.2 + B.3 if B.1's
mocks are already approved).
```

What S2 inherits from S1, concretely: `VERGEML_FILING_LOCKED` and
`VERGEML_FILING_PLACED_BY` constants and their read side; `locked` on
profiles; `vergeml_talk_to_sort()`; `vergeml_talk_seed_profile()` (what
confirm should call per folder, storing the draft's seed in term meta);
`vergeml_talk_answer()` already marks `placed_by = user` for `new-folder`
and `put-in`; the hand-move route is `grep -n "wp_set_object_terms"
core/rest-folders.php`. The walk's assertions have a function each:
`vergeml_talk_fill_status()` for 0 / 0, `report.tally` vs `fit.tally` for
preview = run, `vergeml_talk_questions()` for the list to answer through
`vergeml_talk_answer()`.
