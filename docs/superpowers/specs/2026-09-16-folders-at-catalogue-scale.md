# Folders at catalogue scale — the second shape (epic, BMAD)

**Date:** 2026-09-16 (S9 of every-picture-a-home, C.5). **Owner:** Nathan. **Status:** stories drafted, S10 to build in order.
**Evidence:** `docs/handoffs/2026-09-16-s9-the-second-library.md`, `tests/tree/filing-baseline-shop.txt`, `docs/superpowers/mocks/shots/2026-09-16-quality-sample-shop.html`.

## Why this epic exists

The Folders screen and the filing engine were built and judged on one library: 1,000 tech photos under 21 folders. C.5 put a second shape beside it — a department store's catalogue, **318 folders three levels deep, 626 product photos, most folders empty** — and walked it as a customer would (a `.txt` upload, *This is my tree*, Fill). Five things broke or stalled on the first walk, none of them the matcher. Every one was found by the shape, not by a test, and every one is a customer with a real catalogue.

What the second shape proved about the engine (dry, nothing moved): **626 = fits 507 (sure 482, likely 31) + siblings 6 + nothing 113 (floor 39, either/or 64, gated 10)**. A shop's leaf names are the describer's own words ("Sneakers", "Headphones"), so name-only profiles land where the tech library's needed a planner; the 64 either/or margins are the seeded collisions (Sneakers under Men/Women/Kids, Helmets under Cycling/Motorbikes) being told apart or asked about, which is what 1/k is for. The sheet's two numbers are Nathan's to mark.

## What S9 already shipped (in plugin `1f8cc36`…`61507cd`, service `67644f0`, `bf1aa3b`)

- The confirm profiles in batches of the service's sixty, **one batch a request**, the button counting down; the first hundred folders of an ask free, then one credit per six (cost + ~25 %, measured $0.053–0.062 a batch on OpenRouter's ledger); the planner's output budget 8192 (a sixty-folder batch is 4.3–5.1 k tokens out).
- The paste's dry run fetches every vector in **one** `/embed` request (`texts[]`, metered one per hundred), and the pick remembers spelling, vector and length per phrase: 626 × 319 went from 392 s cold / 21–28 s warm (over the 20 s budget, "counts not worked out") to **15 s counted**. The tech baseline: identical, 4/4.
- Folder names decoded (`&amp;` → `&`) everywhere a name is read; 139 profiles on the shop rebuilt.
- *Open every folder / Close every folder* at the tree head; the confirm's credits on the button; "counting the pictures" beside a disabled confirm.
- `--library shop` on the baseline gate; the sheet's card carries the seed's leaf.

## Stories, in build order

Each story: one acceptance test written first, one mutation named, measured on **both** sites (the tech library's baseline stays 4/4 throughout).

### S10.0 · Progress, always, the same way everywhere (cross-cutting; first)
**Nathan, 2026-09-16:** "it is very important to communicate progress to the user at all times … loading, and not nothing, minus 60 — in a UX-friendly, best-practice way."
- One progress component for the Folders screen (and later the AI screen): a bar with a percentage, the step's own words ("Reading folders · 3 of 6 batches"), an honest estimate when one is known ("about 40 s left", from the batches already done), and an *elapsed since anything moved* line so a stall reads as a stall. Determinate when the total is known (batches, pictures), indeterminate with the elapsed time when it is not. The word budget holds: the bar is one row.
- Where it goes: the confirm (batches), the paste's dry run (pictures × folders counted), the fill (pictures moved of total), the describe step (already a run with numbers — reads the same component), a disabled button's reason.
- The interim countdown on the confirm button ("Reading 248 folders") is replaced by this, not kept beside it.
- Design first: one line of concept, `tools/shoot-mock.mjs`, Nathan's yes. No default spinner, no dots.
- Test: `tree-view.mjs`/`folders.spec`: the confirm shows the bar at 1/6 after the first batch and hides it at 6/6; the fill's bar moves with `moved`; a run with no tick for 30 s shows the stall line. Mutation: the batch count not passed → the bar stays at 0.

### S10.1 · The confirm asks only what a planner can add
**As** a shop owner with a catalogue, **I want** the confirm to profile only the folders whose name is not already one of my pictures' words, **so that** a 318-folder tree costs seconds and cents, not two minutes and 36 credits to hear "nothing" 259 times.
- Rule that reads the shape: a folder whose leaf name (canon-spelled, plural folded) equals a term in `vergeml_filing_vocabulary()` — or whose head noun does — is profiled from its name (`source: name`), never sent. The planner sees only the rest, with the whole tree's paths as context.
- Evidence: 308 asked, 49 answered with classes (16 %); the answered ones are the parents ("Bags & Luggage = bag, luggage") and the odd leaf ("Boots = work boot"); the planner's own words on parents are noisy ("Garden = power tool, architecture", "Sports & Outdoors = … beverages") because batches do not see each other.
- Test: `tests/filing/pick.php` — a pure `vergeml_guide_profile_ask()` split over a fixture vocabulary: {Sneakers, Headphones} stay home, {Bags & Luggage, Kids} go. Mutation: the head-noun match removed → Sneakers goes.
- Gate: on ms2, Unconfirm → confirm asks ≤ 60 folders, ≤ 1 batch, 0 credits; the shop band moves by < 3 % (or is re-taken with the reason).

### S10.2 · A fill that cannot stall behind a cron lock
**As** anyone pressing Fill, **I want** the run to keep moving whatever wp-cron's lock did, **so that** "it hangs" never happens.
- Evidence: the first tick made 319 folders, rescheduled itself for +3 s and set `doing_cron` to a key no arriving request carried; every later `wp-cron.php` was refused as not its own; nothing moved for four minutes until the 60-second lock timeout let a poll re-spawn. Main site: never seen (one spawn, no chain contention).
- Rule: the screen already polls `folders/version` every 5 s while the run is active; a poll that finds the run active, its event past due and no tick in the last 30 s **runs one pass inline** (the answer route's own budget, C.3) — the run's progress never depends on a chained spawn arriving. Alternatively the chain posts blocking with a 2 s timeout inside the tick; measure which on the box first.
- Test: `tests/tree/guide.php` (Playground): plant a run with a stale lock and a past-due event; one poll → moved > 0. Mutation: the poll's kick removed → moved 0.

### S10.3 · The dry run at any shape
**As** the owner of a big tree, **I want** the counts beside the tree to arrive whatever pictures × folders is, **so that** the confirm is never disabled without a number.
- Evidence: 626 × 319 = 15 s after S9; 1,000 × 500 (the paste's cap) projects to ~45 s — over the 20 s budget and nginx's 60 s.
- Rule that reads the shape: pairs = pictures × folders; below 250 k the fit runs in the request as now; above, the turn answers `counted: false` at once and the fit runs as a background job the same poll reads, the pill saying "counting 626 pictures against 319 folders". No new constant: 250 k is the pairs the measured 20 s buys, written where it is measured (`tests/perf/mu-perf.php`).
- Test: `folders.spec` on ms2: the paste of the 318-folder file settles with counts within 30 s; mutation: the prefetch removed → not counted (Playground: the query count row, never the ms).

### S10.4 · The tree at 300 folders reads without scrolling
**As** Nathan on 2026-09-16: "I do not see the expand and collapse button" and "hovering a collapsed folder should reveal the underlying folders from a mini folder".
- Design first (the no-default-visuals rule): a one-line concept → mock (`tools/shoot-mock.mjs`) → approval → build. Candidates: the fold control as an icon pair at the head's left where the eye starts; a hover card on a closed parent listing its children as chips with counts (a preview, not a second tree). Word budget stays ≤ 80.
- Test: `tree-view.mjs` — hover on a closed parent renders N chips, leaves the tree's open state alone; `folders.spec` at 1600 and 1280 on ms2.

### S10.5 · The sheet's verdict on the second shape, and the constants
- Nathan marks `2026-09-16-quality-sample-shop.html` (30 sure of 481, 30 likely of 32) and says what the 45 questions felt like: K = 8, the either/or cards (64 on the dry run), the small-groups card.
- If a constant fails here, the story is a rule that reads the shape (folder count, pictures per folder), never a new number — as the C.5 card says.

### Out of this epic
- The website's inner pages (illustrations, imagery like the homepage) — the site window, not the plugin.
- B.6 (bulk `term_relationships` write), the `filing-trail.php` row for the reason line — held behind, unchanged.
