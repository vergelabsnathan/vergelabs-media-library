# Handover — 2026-09-16, S9: C.5 the second library, a different shape (Opus 5)

Card: `.harness/active.json` (S9). Plan: `plans/every-picture-a-home.md`
C.5. Epic written from this session's findings:
`docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md` (BMAD shape:
five stories with their tests; the bmad skills were not loaded in this
session, so the spec is by hand in their form). Evidence before: S8's
handoff. Lean was the brief; the walk broke it — two of the findings had to
be built mid-walk for the walk to go on, each test-first with its mutation.

**Spend (Nathan's yes each time):** describes 626 × 1 credit = €3.00
(OpenRouter: ~$0.0048 a picture); the confirm's profiling 36 credits on the
licence, $0.246 on the ledger ($0.053–0.062 per 60-folder batch, 4.3–5.1 k
output tokens each); embeds cents. Box licence 24,870 → 24,208.

Commits, plugin: `888d518` (seed, tree, gate `--library`, sheet, connect),
`1f8cc36` (batched confirm, credits on the button, prefetch, fold button),
`e9ee789` (one batch a request), perf `…` (memo), `&amp;` fix, `61507cd`
(band + sheet); service on main: `67644f0` (batches, charge, embed
`texts[]`, 8192), `bf1aa3b` (batch metered 1/100), and the measured-cost
note. Deployed: box `deploy --box` after each plugin change (137 files);
production Vercel builds from the main pushes (the newest `Production ·
Ready` rows at push time). The service checkout sits on
`redesign/plus-style`; my commits were cherry-picked onto `main` and pushed
there, the redesign branch untouched.

## The second library

- **Site:** `/var/www/ms2` (own network, `wpms2`,
  `http://ms2.46.225.66.194.nip.io`, plugin via the deployed symlink,
  wp-cli as `sudo -u www-data wp … --url=…`). Session super-admin `vgmls9`
  (multisite refuses a hyphen), password in this session's scratchpad only —
  **delete the user when C.5 closes**. Connected to the box's agency licence
  by `tools/box-connect-ms2.sh` (the key unsealed on the main site, resealed
  on ms2, a seat taken; the seal is per-site auth salt).
- **Pictures:** 626 Commons product photos over 88 subjects
  (`tools/box-seed-shop.php`; each stamped `_vergeml_seed_leaf` with the
  catalogue leaf it was fetched for; `tests/tree/seed-shop.mjs` holds seed
  and tree to each other). The first run took 11 a subject and reached 500
  at the 48th subject (fixed: a per-subject cap; the top-up ran at 3 a
  subject because the wrapper did not forward `VGML_SEED_PER` — fixed
  after). All described on production `ai.vergelabs.nl`: photo 598,
  illustration 18, diagram 5, document 3, screenshot 2. The site profile
  was set as a shop would.
- **Tree:** `tools/box-seed-shop-tree.txt`, 318 folders, 3 levels, 243
  leaves, collisions on purpose (Sneakers ×3, Boots ×3, Jackets ×3, Jeans
  ×2, Helmets ×2, Wheels ×2, Backpacks ×2, Brushes ×2, Furniture ×2).
  Uploaded on the Folders screen by Nathan; confirmed twice (the first
  profiled 11 of 318 — see finding 1; the second, after the fix, 49).
- **Walk:** Fill pressed at 12:04; stalled four minutes (finding 3); ended
  with **513 of 626 placed, 45 questions, 113 to sort**. The questions are
  Nathan's to answer; at the time of writing he is on them.

## The numbers

**The shop band** (`tests/tree/filing-baseline-shop.txt`, dry, fresh picks):

```
shop: looked 626 = fits 507 (sure 482, likely 31) + siblings 6 + nothing 113 (floor 39, margin 64 = either 64, gated 10)
tech: looked 1000 = fits 553 (sure 340, likely 220) + siblings 7 + nothing 440 (floor 370, margin 36 = either 36, gated 34)   (unchanged, 4/4 after every change today)
```

A shop's leaf names are the describer's words, so name-only profiles land
(sure 77 % of the library against the tech library's 34 %); the 64 either/or
margins are the seeded collisions being asked about — 1/k and either/or on
the second shape. The fill's own tally (what Nathan's screen did, with the
`&amp;` profiles as they were) placed 513, close to the dry 507 + 6.

**The sheet:** `docs/superpowers/mocks/shots/2026-09-16-quality-sample-shop.html`,
30 sure of 481, 30 likely of 32, fill batch 1; each card carries *fetched
for* (the seed's leaf). **Nathan's marks are not in yet** — S10.5. Beside the
tech library's C.4 sheet (sure 87 %, likely 57 %) it is the second pair the
card asked for.

**Timings** (ms2, the paste's dry run, 626 × 319): cold 392 s → unknown
(batches refused by the burst cap; one call each); warm 21–28 s → unknown
(over the 20 s budget); after the memo **15 s, counted**; profiles 0.3 s
with the prefetch. The confirm: six batches at ~35 s a request. The fill:
313 moves a minute once it moved.

## Five findings, none the matcher's (the epic's stories)

1. **The service capped the profile ask at 60 folders and said nothing** —
   11 of 318 profiled. Fixed: batches of the cap, one a request, charged
   past the first hundred at cost + 25 % (Nathan's rule), the button saying
   the number. Left for S10.1: the planner should not be asked about a
   folder whose name is already a describer word — 259 of 308 answers were
   "nothing", and batches that cannot see each other give parents noisy
   words ("Garden = power tool, architecture").
2. **The paste's dry run died at nginx's 60 s** — ~900 vectors fetched one
   call each, and the confirm button stayed grey until a retry found the
   cache warm. Fixed: one `/embed` request; the pick's memo. Left for S10.3:
   the budget is a constant (20 s) and the paste's cap (500 folders × 1,000
   pictures) projects past it — a background fit above a measured pairs
   count.
3. **The fill stalled behind a stale cron lock for four minutes** (the
   first tick's chained key never arrived; every later tick refused; the
   60 s lock timeout eventually let a poll re-spawn). Not fixed — S10.2:
   the poll that already runs every 5 s should run a pass inline when the
   run is active and its event past due.
4. **`Bags &amp; Luggage`** — WordPress stores `&` as `&amp;`, every reader
   took the stored form: the tree showed it, the draft never matched the
   made folder by name (638 "changes" mid-run), and 139 profiles were built
   from the wrong text. Fixed: `vergeml_term_name()` everywhere a name is
   read (pick.php row 23); the 139 rebuilt before the band was taken.
5. **The tree at 300 folders** — Nathan did not see *Open every folder*
   (top right of the tree card, a quiet chip) and wants a hover on a closed
   folder to preview its children. S10.4, mock first.

## Gates, as run

- `node tools/verify.mjs filing seed-shop tree-view structure copy surface roles escaping` → 25/25 + 30/30, 8/8, 61/61, 31/31, 58/58, 26/26 (regenerated), 19/19 (roles: 79/81 endpoints — `/guide/profiles-restore` from S8 was never counted), 9/10 (the known ratio row).
- Mutations run: seed leaf misspelled → seed-shop red; the shop pointed at the tech file → the gate passes wrongly (seen, restored); batch made 500 → pick 21 red; the decode removed → pick 23 red. The prefetch and the stall were watched going green from red on the box (the numbers above), not by a suite.
- `filing-baseline-check.mjs` tech 4/4 after the perf work (identical); `--library shop` taken, then 4/4.
- **`folders.spec` on ms2 at 1600 and 1280: NOT RUN** — it plants and restores the talk state, and Nathan was answering the 45 questions on that state. Run it when he says the answers are done; the word-budget row is the one this shape tests.
- service: `vitest lib/profile-price.test.ts lib/folders-prompt.test.ts` 10/10; `tsc --noEmit` clean.

## Not done, and why

- The sheet's marks and the constants' judgement (K = 8, GROUP_NEAR 0.8,
  the 60 % put-in, 1/k): Nathan's, on the sheet and the 45 questions.
- `folders.spec` on ms2: see above.
- S10.1–S10.4 as written in the epic; the cron stall is the one that bites a
  customer first.
- The website's inner pages (Nathan, mid-session: "extremely boring, keep
  using higher-level illustrations throughout") — the site window's work,
  flagged here so it is not lost.
- Memory: `hetzner-box-fixtures` updated (ms2, `vgmls9`, the tools).

## Next — S10

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S10: folders at catalogue scale (the epic's stories S10.1–S10.5, in order)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (C.5 walked; the epic docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "core/guide.php (the confirm's ask, the poll's kick, the background fit)",
    "core/filing.php (profile_ask over the vocabulary)",
    "core/folder-talk.php (the run's pass from a poll)",
    "js/vergeml-folders.js, js/vergeml-tree-view.js (S10.4 after the mock is approved)",
    "js/vergeml-tree.js, css/vergeml-tree.css, core/media-list.php (S10.6 after the mock is approved: the list opens on the pictures, the sidebar full height, the tree first)",
    "tests/filing/pick.php, tests/tree/guide.php, tests/tree/tree-view.mjs, tests/ui/folders.spec.mjs",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-16-s9-the-second-library.md (this: the numbers, the five findings, what is not run)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (the stories and their tests)",
    "memory: hetzner-box-fixtures (ms2, vgmls9), tests-never-touch-live-state, playground-counts-queries-not-ms"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "First: run folders.spec on ms2 at 1600×1000 and 1280×800 once Nathan says the questions are answered; then read his marks on the shop sheet and his verdict on K = 8 and the either/or cards — that verdict decides whether S10.5 changes a rule",
    "S10.0 (one progress component, a bar with a number and an estimate, mocked and approved) goes first and every later story renders through it; S10.1 before S10.2: a confirm that asks about 60 folders instead of 308 is the cheaper and quieter walk, and the stall reproduces on ms2 either way",
    "S10.4 is mock-first: one line of concept, tools/shoot-mock.mjs, Nathan's yes, then the build",
    "Every change measured on both sites: the tech baseline 4/4 unchanged, the shop band within 3 % or re-taken with the reason",
    "Nothing describes; profiling costs credits — say the number before a confirm on ms2; delete vgmls9 when C.5 closes",
    "The tech library's site is never seeded or filled for this"
  ],
  "gates": [
    "folders.spec green on ms2 at both sizes",
    "S10.1: confirm on the 318-folder tree asks ≤ 60 folders, 0 credits; pick.php row with its mutation",
    "S10.2: a planted stale lock + past-due event moves pictures on one poll (tests/tree/guide.php), and a Fill on ms2 moves within 30 s of the press",
    "S10.3: the paste of the 318-folder file settles with counts within 30 s on ms2; the query-count row on Playground",
    "node tools/verify.mjs filing surface roles escaping copy tree-view seed-shop → green (escaping 9/10 known); both baselines 4/4"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-16-s9-the-second-library.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are. This session is S10 of every-picture-a-home: the
catalogue-scale epic, stories in order. Write the card from the handoff to
.harness/active.json before anything else. Lean: test first, one mutation
check per story, both sites measured, say every cost before it is spent.
End with a handoff carrying the S11 card.
```
