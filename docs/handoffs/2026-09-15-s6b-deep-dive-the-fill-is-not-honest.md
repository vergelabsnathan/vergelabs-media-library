# Handover — 2026-09-15 evening, S6b: A.5 walked to done, and why it is not right (Opus 5)

Same session as `2026-09-15-s6-the-walk-paused-and-the-tree-by-hand.md`,
continued after Nathan pressed on. Four read-only investigations ran in
parallel as sub-agents (matcher, residue grouping, the questions screen,
the describer → planner seam); every finding below was then checked
against the box's own rows. Spend: nothing beyond the walk's 10 credits
(the sample and every probe are read-only; one fix deployed).

> "the questions are not smooth … 30 questions is too much … Let me look
> is failing in some instances … very very slow and jerky … the system is
> far from ready for launch, especially the folder creation and image
> interpretation part" — Nathan, 2026-09-15, on the box, mid-walk.

He is right, and the box says exactly where.

## The walk, as it happened (the A.5 record)

Nathan pressed, in order: Propose folders (10 credits) → This is my tree
(the planner profiled nothing: every folder carried a profile) → Fill
1,000 pictures → one answer by hand → Leave the rest.

```
run:  looked 1000 = fits 558 (sure 499, likely 113) + siblings 54 + nothing 388 (floor 245, margin 109, gated 34) + kept 0
screen: 612 placed · 499 sure · 113 likely · 30 questions · 337 to sort
questions: 4 sibling (Cooling/Server racks 9, Components/Phones 24, Interviews/Conference talks 2, Wind/Solar 19)
           26 residue: 61 "server rack"→Data Center, 41 3D Printers, 38 Electric Vehicle, 30 Office Workspaces,
           25 Trade Shows, 20 Drones, 18 E-waste, 14, 14, 12, 10, 9, 7, 7, 6, 5, 5, 4, 4, 4, 4, 3, 3, 3, 3, and "38 I can't read"
answers:  r:0 → Put in Server racks (61, by hand); the other 29 → Leave the rest (327 into To sort, locked)
end:      0 in no folder · 0 open · done · undo available · To sort 327
```

Preview = run was not measurable this time: the spec's restore had
dropped the fit tally from the session (the earlier handoff says so).

**The 60-picture sample — Nathan's verdict, 2026-09-16** (sheet
, 30 sure of 499,
30 likely of 113, batch 133; a third mark, *too broad*, added at his ask):

\
Both thresholds fail. Read against the fill's own rows, the 16 wrong
 are four kinds, none a threshold:

- **A kind word as a class (3):** the planner gave Satellites the class
  ; every diagram in the library (keyboard layout, speaker-tracking
  schematic, controller test guide) went to Space / Satellites at 0.76–0.84
  with no runner-up. C.4 (a class is never a kind word) and C.1 (kind out).
- **Parent instead of child, or the wrong child, through a shared class (6):**
  solar panel → Energy 0.84 (Solar next); rocket launch → Space 0.73 (not
  Launches); graphics cards → Hardware 0.85 and Laptops 0.81; conference
  attendees → Interviews 0.86 over Conference talks 0.77; Ethernet switch →
  Server racks 0.77. The shared word outscored the folder's own name. C.1.
- **The profile means something Nathan does not (7):** Components took
  loose ICs and e-waste piles at 0.77–0.89 (); Conference talks took attendee and venue shots
  (). The matcher did what the profile says. C.4: the
  planner writes the folder in the describer's words *and* the owner's
  intent, and the owner sees the classes on the tree before confirming.

The 11  are one pattern: eight wind turbines in *Energy* because
Solar and Wind tie at 0.85 ( rank 0 on
Energy, Solar and Wind; Wind's own name rank 1) and the sibling rule keeps
the parent; two PC towers in *Hardware* the same way (Components vs
Laptops). C.1's leaf-name-at-1.0 and 1/k on the shared word, exactly. The
seven  wrong: phones in Hardware (Phones vs Components tie), a
rack hallway in Data centres, a wafer, a factory in Cooling, a shop in
Batteries — shared classes and vector noise below 0.70.

**So the tuning card is Phase C, not a number:** C.1 fixes the 11 broad and
the 6 shared-class misses; C.4 fixes the 3 diagrams and the 7 profile
misreadings. The sample is re-taken after S7 and again after S8 (same
batch seed if the fill is not redone; a new fill re-seeds).

## What the box says about the 61

The group shown as **"61 look like server racks"** (`class` server rack,
model's `name` "Data Center", answers new-folder / put-in Server racks /
leave / show-me). Its pictures, each with the fill's own row:

- 9 server-rack photos — `margin`, "Hardware 0.87 / Server racks 0.83"
  (different parents, so the sibling rule did not fire)
- 16 telecom towers, cell towers, fibre-optic cable jobs, cable drums —
  `margin`, "Cooling 0.84 / Space 0.84", "Cooling 0.82 / Space 0.78"…
- 3 industrial robots — `margin`, "Robotics 0.74 / Cooling 0.66"
- 7 screenshots and illustrations — `gated`, 0.00 everywhere
- a barn, a walnut tree, a control room, an ion-bombardment lab, trucks —
  `floor`, 0.35–0.52

`why`: margin 35 · floor 19 · gated 7. "Nearest > runner-up": Cooling >
Space 16, Hardware > Server racks 7, — 7, Space > Cooling 4. Nathan
pressed *Put in Server racks*: 61 pictures, 16 of them towers, now sit in
Server racks marked placed-by-user, which every later fill leaves alone.

**The profiles the fill used** (term meta, all `source: plan`, all
`kinds [photo]` but Satellites `[photo,diagram]` and the two date folders):

```
infrastructure                   on 5: Cooling, Data centres, Satellites, Server racks (rank 0 on four), Space
people                           on 3: Conference talks, Interviews, People
renewable energy infrastructure  on 3: Energy, Solar, Wind
computer hardware / computer     on 2 each: Hardware, Laptops
networking equipment             on 2: Data centres, Server racks
spacecraft                       on 2: Launches, Space
```

## The diagnosis, four seams, ranked by damage

Every claim carries its file:line; the agents' full reports are in the
session transcript, the box facts above are the check.

### 1 · The matcher cannot tell the folders apart (core/filing.php)

- **A class shared by k folders scores the same on every one.** A class
  hit is `0.75 × match` plus `0.25 × embed` (`filing.php:490-510`); a
  shared word at rank 0 gives 0.75 before any vector, at rank ≥ 1 still
  0.6375 — both above the 0.55 floor. `vergeml_filing_settle_claims`
  (`:326-352`) only reassigns a duplicated *first* class, and the stored
  profiles show it did not even do that here (four folders hold
  `infrastructure` at rank 0). Two folders sharing a word differ only by
  `0.25 × Δembed`, which never reaches the 0.08 margin. That is the 109
  margins and most of the 61.
- **The picture's two terms weigh the same** (`:491`): "server rack;
  computer hardware" matches Hardware's rank-0 `computer hardware` at 1.0
  and Server racks' `server racks` (its own name, appended *last*,
  `:249-252`) at 0.85. The folder named for the object loses to its
  parent's neighbour: 0.87 vs 0.83.
- **A cross-parent tie is silent residue** (`:565-574`): Hardware vs
  Server racks is exactly the question a person can answer in one press,
  and it is never asked.
- **The kind gate has no exit** (`:473`, `:513`): every planner profile
  says `kinds: ['photo']` (the prompt: "almost always"), so every
  screenshot and illustration is `gated` on every folder and falls into
  residue by its object word — an illustrated server rack lands with the
  photographed ones.

### 2 · The residue grouping lumps, then mislabels (core/filing.php:754-830, core/folder-talk.php)

- Groups are keyed on the first object phrase only (`:760`); `kind`,
  the second phrase and the per-picture nearest folder play no part.
  Every group under 5 pictures (`GROUP_MIN`) joins the standing group
  whose **seed centroid** (never recomputed, `:775-778`, `:803-807`) is
  nearest at cosine ≥ **0.5** (`GROUP_NEAR`). "Telecommunications tower"
  (3), "fibre optic cable" (4), "barn" (1), "monitoring dashboard" (2)
  each clear 0.5 against data-centre text, so 52 pictures accreted to a
  9-picture seed.
- The seed keeps the label: `class` is set at `:766` and never
  re-derived; no coverage rule (9 of 61 = 15 %). The model names the
  merged list of twelve class words + the seed's captions →
  "Data Center". Sentence and button are computed from different inputs.
- No cap: one card per group (`:898-920`); "38 I can't read" is empty
  phrases + no-vector rows + singletons that matched nothing (`:813-827`).
- "Put in X" is the folder nearest the **seed centroid** (`folder-talk.php:1961,1987-2004`),
  offered for the whole group with no kind/audience gate and no majority
  rule — the Server-racks trap.

### 3 · The questions screen (js/vergeml-folders.js, core/guide.php, core/folder-talk.php)

- **"Let me look" on a sibling card showed nothing** — fixed this session
  (`910748b`): `vergeml_filing_answer_plan` took `array_values` of a
  `picture => best child` map and handed the screen term ids; every
  thumbnail URL was empty. Confirmed on the box (`s:2423` → 2428, 2428,
  2432…), proven by `tests/filing/residue.php` 16b (old line → red),
  deployed.
- **Errors vanish**: any `guide/answer` failure goes to `talk.note()`,
  rendered into the change line, which is hidden while the tree is
  confirmed — i.e. always on Step 3 (`folders.js:412, 762`). A 409, a
  404, a PHP timeout on a 61-picture move all look like "nothing
  happened".
- **Per answer, server**: the move loop calls `wp_set_object_terms` per
  picture with term counting on and `vergeml_folder_flush_counts` firing
  each time (`folder-talk.php:2291-2303`, `post-folders.php:205`) — ~600
  queries for 61 — then the response rebuilds all 30 questions with 240
  thumbnails (`guide.php:856`) and a NOT EXISTS count over the index.
- **Per answer, client**: GET tree → full tree rebuild → `renderCards`
  re-appends the tree node (restarting `is-entering` animations) →
  `renderQuestions` destroys and rebuilds every card → `setNewIds`
  renders the tree **again** (`folders.js:555, 697, 968-978`;
  `tree-view.js:842-852, 1709-1758`). Two tree rebuilds and a grid
  rebuild for one press; buttons lose focus; the next card pops.
- **The box itself**: a REST round trip on 46.225.66.194 is **2.9 s**
  for a 16-byte answer (`/folders/version`, `admin-ajax` heartbeat the
  same) with 36 plugins active (WooCommerce, Elementor, Brizy, Jetpack,
  WP Rocket, three SEO suites, Zion, Beaver, Revslider…). Our compute is
  4–50 ms per route. Every 5-second version poll and every answer pays
  the 2.9 s first. A customer with six plugins pays ~0.3 s; the jank is
  ours *and* the box's, and the polish must be measured on both.

### 4 · The describer → planner seam (service/lib/describe.ts, lib/anthropic.ts)

- The describer's `object` is "specific; class" free text
  (`describe.ts:220`), while its own schema description says "two or
  three words, no class" (`:93`) — the model gets both.
- **The profile planners never see the describer's vocabulary**:
  `profileFolders` and `planFolders` get folder names + captions
  (`anthropic.ts:525-528, 609-614`; `folder-talk.php:170` picks
  `caption`, not `filing`), are told "a broad folder lists the classes of
  everything it holds", and nothing forbids a class on several folders.
  Only the guide's summary carries object terms (`guide.php:513-528`).
- No canon: "data centre"/"data center", irregular plurals fall to an
  unfloored cosine (`filing.php:377-398`).

## What this means for the plan

The engine's A.1–A.4 were built and proven on fixtures; the box's real
profiles broke the assumption underneath them (classes are distinct per
folder). The spec's §5 gates stand; what changes is what a class hit is
worth and what a tie means — not the floor, not the margin. Phase C in
`plans/every-picture-a-home.md`, four tasks, order C.1 → C.2 → C.3 → C.4;
each with the test that proves it and the mutation that reddens it.

## Gates this session

- `node tools/verify.mjs filing` → 12/12, 19/19 (16b new; mutation red).
- `node tests/tree/tree-view.mjs` → 61/61; `folders.spec` 11 passed, 1
  skipped on the box (before the walk's fill; not re-run over Nathan's
  live state — its restore rewrites the session).
- `node tools/deploy.mjs --check` → box up to date after `910748b`.

## Next — S7: Phase C.1 + C.2 (the engine, honest), Opus, fresh session

Card, to `plugin/.harness/active.json` before anything:

```json
{
  "phase": "Every picture a home — Phase C, S7: C.1 the matcher tells folders apart + C.2 the residue grouped and labelled honestly",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase C)",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/filing.php",
    "core/folder-talk.php",
    "core/guide.php",
    "tests/filing/**",
    "tools/box-refile-all.php",
    "tools/box-fill-walk.php",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md (the evidence; the four seams)",
    "plans/every-picture-a-home.md (Phase C: C.1, C.2 and their proofs)",
    "core/filing.php (pick, settle_claims, residue_groups, questions, answer_plan)",
    "tests/filing/pick.php and tests/filing/residue.php (the fixtures to extend)",
    "memory: filing-by-evidence, model-spend-discipline, tests-never-touch-live-state"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "FLOOR 0.55, MARGIN 0.08, SURE 0.70 do not move; the change is what a class hit is worth (1/k for a class on k folders, the picture's second term at 0.85, the leaf name's exact hit at 1.0) and what a tie means (a cross-parent tie is an either/or question, not residue)",
    "Residue: kind first (screenshots, illustrations, diagrams as their own groups), merge only near-identical phrases (class_match >= 0.95 or centroid >= 0.8, centroid recomputed), class re-derived from the merged majority with a share, the sentence says 'mixed, mostly X' under 70 %, the K = 8 largest asked and the rest one card, Put in X only when X is nearest for >= 60 % and passes X's gates",
    "The box is Nathan's after his walk: 61 in Server racks by hand, 327 in To sort. Do not undo it unless he says so; prove C.1/C.2 on tools/box-refile-all.php (dry run) and on the fixtures, never by a fill",
    "Say the cost before any planner call; the box's REST round trip is 2.9 s, so time nothing on the box that the fixtures can time"
  ],
  "gates": [
    "tests/filing/pick.php: 'server rack; computer hardware' vs Hardware[computer hardware] and Server racks[server rack] → Server racks, sure; a word on four folders adds at most 0.25×0.75; a cross-parent margin returns children and the questions carry kind 'either'; a screenshot never lands in a photo group",
    "tests/filing/residue.php: seven screenshot facts form their own group; a 3 at cosine 0.6 does not merge; class flips to the majority with share; 12 groups → 9 cards; 9/61 nearest → no put-in, 40/61 → put-in",
    "tools/box-refile-all.php dry run on the box: margin count well under 109 of 1000 and the 61 no longer one group — the numbers in the handoff beside today's line",
    "node tools/verify.mjs filing fill-walk surface roles escaping → green (escaping 9/10 known); every mutation named in the plan turns its own row red"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-s6b-deep-dive-the-fill-is-not-honest.md, then
plans/every-picture-a-home.md Phase C (C.1 and C.2). State which model you
are. This session is S7 of every-picture-a-home: the engine made honest on
the box's evidence. Write the card from the handoff to .harness/active.json
before anything else. Lean: test first, one mutation check per task, no
fill on the box. End with a handoff in docs/handoffs/ carrying the S8 card
(C.3 the screen + C.4 the service seam).
```

Held behind S7: **the sample's verdict** (Nathan marks the sheet, pastes
the two lines; the numbers go in S7's handoff), **C.3** (the screen: errors
on the card, deferred term counting and a slim answer response, one tree
rebuild per answer, keyed cards — with folders.spec at 30 planted
questions and a timing row), **C.4** (the service: planners see the
describer's vocabulary and the one-folder rule; the schema/prompt
contradiction; a canon fold), then the Folders polish card and B.6.
