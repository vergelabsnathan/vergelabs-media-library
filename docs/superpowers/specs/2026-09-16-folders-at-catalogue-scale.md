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
- **Marked (2026-09-16): sure 25/30 = 83 %, likely 11/30 = 37 % with 11 too broad** (tech after C.4: 87 % / 57 %). The sure misses are single pictures; six of eight likely wrongs sit in *Garden* (planner profile "power tool, architecture" — batch noise on a parent: S10.1, S10.7), three broads are laptops in *Electronics / Computers* by the siblings rule. So the likely band on this shape is a profile finding before it is a threshold finding; no constant moves on this sheet.
- If a constant fails here, the story is a rule that reads the shape (folder count, pictures per folder), never a new number — as the C.5 card says.
- **What the 45 questions already said (read off the box, 2026-09-16):** 3 siblings, 41 either/or, 4 residue cards (6 illustrations, 5 diagrams, 37 in small groups, 1 with nothing).
  1. **A question that names two folders of the same name is unanswerable** — "10 pictures: Backpacks or Backpacks?", also Helmets, Jackets ×2, Furniture, Wheels: the seeded collisions, worded by leaf. Defect: `vergeml_talk_question_text()` carries the path whenever two named folders share a leaf ("Bags & Luggage › Backpacks or Camping › Backpacks?"). Test in `residue.php`; mutation: the path dropped → red.
  2. **38 of 41 either/ors are about one picture.** K = 8 shows eight and drops 33 into one card; on a collision-heavy tree the tail is long and thin. **Nathan, after answering them (2026-09-16): "this is very tedious for a user — later we have to think of a solution."** The verdict on K = 8 for this shape: the cap is not the problem, the grain is — one card per picture-pair is too fine. Rule that reads the shape: either/ors of one picture fold into one "show me" card per folder pair (the pictures with both folders as the two answers), so the cap counts pairs, not pictures. No new constant.
  3. **The head-noun rule ties compound nouns against their own class** — "cheese wheel; cheese" → Cheese 1.0 and Wheels 1.0 (the head noun), "hair dryer" → Chairs?, "vase" → Glasses? ("rocket launch" → Launches was the case it was written for). Candidate rule: a head-noun hit on the first phrase never outranks a full hit of the second phrase on another folder (1.0 → 0.9 when the class half hits elsewhere in full); judged on both sheets before it moves, because it touches the tech baseline (re-take with the reason).

### S10.6 · The media list opens on the pictures, with the folders beside them
**Nathan, 2026-09-16, on ms2:** "if I open my media library I don't want to scroll past all kinds of filters — bad UX; and the sidebar is too short, make it cover the view at least."
- Measured (Playwright, `upload.php?mode=list`): at 1280×800 the first row sits at **453 px** — core's filter bar is two rows (*All dates · Necklaces (9) · Term for bulk actions · Filter*, then *Reset all filters*), then the search row, then the bulk-actions row; at 1600×1000 the first row is at 377 px. The sidebar is **677 px tall in an 800 px viewport** (877 in 1000), ending above the page's foot, and the folder tree comes **after** the *Filters* and *AI folders* groups, so a 318-folder tree starts below the fold.
- Rule: the pictures are the page. One row above the list: the folder pill (the current folder as a chip, the tree beside it), search, and a single *Filter* chip that opens the date/type/term choices only when pressed; *Reset* appears only when something is set. The sidebar is the viewport's full height, sticky, with its own scroll; the **tree first**, the *Filters* and *AI folders* groups collapsed below it (open by the person, remembered).
- Design first: concept line → mock at 1600 and 1280 (`tools/shoot-mock.mjs --words`) → Nathan's yes → build; `modes.spec` and `shots.spec` cover the list.
- Test: `modes.spec`: the first row's top ≤ 240 px at 1280×800 with no filter set; the sidebar's height ≥ the viewport's; the tree's first folder visible without scrolling. Mutation: the sticky removed → the height row red.

### S10.5b · A tree the fixture's author did not write
**Nathan, 2026-09-16:** "you made both the collection of images and the tree — how well would it have performed if you didn't?" Fair: the seed's subjects are product nouns and the leaves are named after them, so most name-only profiles match by construction; the tech library (a newsroom's beats, planner words needed) reads sure 34 % where the shop reads 77 %. A real shop's tree carries names no picture says ("New in", "Summer '26", "Gifts under €50", brands).
- Rule: same 626 pictures, **no new describes**; a second catalogue lifted from a real retailer's sitemap or category export (marketing names kept, ~300 folders, ≤ 500), pasted on ms2 after Unconfirm + the folders removed (the undo, or a tree restore), confirmed (say the credits first), filled; a second band file (`filing-baseline-shop-b.txt`) and a second sheet. The pair of numbers beside the first shop sheet and the tech sheet is the honest range.
- What to read from it: how many folders the planner can profile at all (S10.1's rule), how much the vector path carries when names say nothing, and whether the either/or cards still make sense to the owner.

## The second half of the epic: pushing the number (Nathan, 2026-09-16: "how can we push that number up?")

The two libraries bound the engine today: **sure 34 %** where the folder names are not the pictures' words (the tech library) and **77 %** where they are (the shop, a fixture that flatters the name match). The stories below move both bounds by changing where a folder's profile and the tree itself come from — from guesses about words to the pictures and facts the site already has. Order of build after S10.6.

### S10.7 · The fill learns from its own placements (profile from members)
**Rule that reads the shape:** a folder holding ≥ 3 described pictures is profiled from them — its classes the top object words of its members (canon-spelled, counted, the leaf name kept), its vector their centroid, `source: members` — and that profile outranks the planner's and the name's. Rebuilt whenever the folder's members change (the fill, a hand placement, an undo), cached like the others.
- For a structured library (a shop with products in place) this is the first profile; for a bare pack it is the **second pass**: round 1 places from the planner's tree, the folders now hold pictures, round 2 re-profiles from members and places the leftovers. The Fill step shows rounds ("round 1: 553 placed · round 2: 640 · 113 to sort"); nothing is re-described between rounds.
- Test: `pick.php` — a folder with members {desktop pc ×5, tower case ×2, workstation} profiles to those words and their centroid; a pick that the name-profile called `floor` lands `ok` on the member profile. Mutation: the member profile made to lose to the name profile → the row red. Gate: on the tech library, round 2's band ≥ round 1's sure by 10 points or the story says why (the sheet re-taken, Nathan's marks).

### S10.8 · File by the product, not the picture (WooCommerce)
**Rule:** a picture attached to a product (featured image, gallery) goes where the product's categories say, at confidence `sure`, reason "by the product", before any matching; the folder ↔ product category map is the existing Woo integration's. Deterministic, no model, no credits. The rail's Fill step: "placed by product N · by evidence N · to sort N". Only pictures no product owns reach the describer — the describe step moves **after** this fill for such sites, and its number shrinks to the leftovers.
- Test: `tests/integrations/live.php` (Woo active on the box): a product in *Dresses* with two images → both in the Dresses folder by product, never re-filed by a later round. Mutation: the product path removed → they fall to the matcher.

### S10.9 · The picture's own words as evidence (filename, title, alt)
**Rule:** the matcher reads a third phrase list per picture — the words of its filename (split on `-_.`), title and alt — and a folder class hit there counts like the describer's second phrase (0.85). A shop's `summer-dress-red-front.jpg` and a photographer's `2024-06-smith-wedding-012.jpg` carry what no describer word does.
- Test: `pick.php` — a row whose describer words tie two folders is settled by its filename word. Mutation: the third list dropped → margin.

### S10.10 · The proposal from the pictures, not from a word list
**Rule:** the proposal groups the library first (the residue grouper's clustering over the stored vectors, GROUP_NEAR, then the groups' top words), and the planner is handed **groups to name and nest** — "61 pictures: desktop pc, tower case, workstation" — instead of the eighty commonest words. A proposed folder arrives with the pictures that made it: its profile is its group's words and centroid (S10.7's shape, from the start), the dry run's counts are the groups' sizes, and the planner does only what it is good at, naming and nesting. The current prompt stays for libraries too small to cluster (< 40 described).
- **Groups form per kind first** (photo, illustration, screenshot, diagram, document, logo), so a pack of images *and* documents gets a branch for its PDFs and scans proposed from their own words ("Invoices", "Contracts", "Manuals") instead of "5 look like documents" in the residue — this closes the plan's "kinds have no home" hole for bare packs; the kind gate then files them there and nowhere else.
- With it: the proposal drawn as pictures — six thumbnails per proposed folder and its count — so an owner judges "Components" by what is in it; the pack's own signals handed to the planner (year/month upload folders, filename prefixes, EXIF date and camera, the site profile); and the tree's size bound from the shape (a target of pictures per folder gives the folder count from the library's size; the planner gets it as a bound, no constant of its own).
- Test: `service/lib/folders-prompt.test.ts` — the plan prompt from groups carries each group's size and words and no bare term list; `pick.php` — a group-born profile equals the member profile of the same pictures. Mutation: the groups dropped from the prompt → red. Gate: the tech library re-proposed from groups, Nathan's yes on the tree, the sheet re-taken on round 1: the number beside 34 %.

### S10.11 · An opt-in model pass on what is left
**Rule:** a button on the Fill step, "Ask the model where these 113 go · 113 credits": the describer sees the picture and the tree (the folder paths, ≤ 500) and answers one folder or none; placed as `sure` with reason "the model, asked". Never automatic (C.3's rule stands); the number on the button before the press.
- Test: `folders.spec` — the button carries the count and the credits; a planted answer places the picture with its reason on the why card. Mutation: the credits pill removed → the copy row red ("a button without its number").

### The order of the steps, by library
- **Structured** (folders with pictures, products): Tree (what the site has) → Fill by facts (S10.8, S10.7 members) → Describe only what nothing placed → Fill by evidence → questions.
- **Bare pack:** Describe → propose from groups (S10.10) → confirm (profiles are the groups') → Fill → rounds (S10.7) → questions → the opt-in pass (S10.11).
- The rail shows which it is on and why; mocked with S10.0's progress component.

### Out of this epic
- The website's inner pages (illustrations, imagery like the homepage) — the site window, not the plugin.
- B.6 (bulk `term_relationships` write), the `filing-trail.php` row for the reason line — held behind, unchanged.
