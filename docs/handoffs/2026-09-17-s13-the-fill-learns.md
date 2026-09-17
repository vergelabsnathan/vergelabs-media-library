# Handover — 2026-09-17, S13: the fill learns from its own placements, the picture's own words, the head noun (Opus 5)

Card: `.harness/active.json` (S13, as S12 wrote it). Plan:
`plans/every-picture-a-home.md` Phase D (S13 line written, S14 line added).
Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(S10.7, S10.9 and S10.5 rule 3 carry Built paragraphs). Evidence before:
S12's handoff. Lean held: test first, one mutation per story (each seen red),
both sites measured dry, ms2 read only (no suite, no walk — user 5 logged in
at 11:01 UTC and the stop point says ask).

**Spend:** 0 credits on either library. Every measurement was the baseline
read (`tools/filing-baseline-check.mjs --save`) or the dry quality sheet;
the member words are the pictures' own phrases, already in the vector cache.
Writes outside the repo: the profile and member-layer caches in term meta on
both sites; `sticky.php`'s own fixture, restored. Nothing moved.

Commits, plugin (`main`, deployed to the box after each; ms2 runs the same
copy): `76d917f` S10.7, `3fa8e13` S10.9, `3f720d9` rule 3, `d12448b` rule 3
refined on the shop, then the plan, the spec, the sheet and this handoff.

## S10.7 — the fill learns from its own placements (`76d917f`)

A folder holding ≥ 3 described pictures is read over them
(`vergeml_filing_members_layer`, pure): its classes their **object words two
or more members say**, most carried first, then the plan's words, the leaf
kept; its vector their centroid; `source: members`, `base_source` what it
was. The layer is cached in term meta (`_vergeml_profile_members`) under a
stamp of the members (count, ids summed and squared, last described) **and
the rule's numbers**, so a fill, a hand move, an undo, a re-describe or a
rule change rebuilds it with no hook — `vergeml_talk_answer` takes the term
hooks off around its write, so a hook would have missed it. One light query
reads every stamp, one more the rows of the stale folders. The dry run reads
the same layer (`vergeml_guide_draft_fit`). The fill runs **round 2** over
what round 1 left unplaced, once, when round 1 moved anything; the tally
gives the leftovers back first so a picture is counted once; the report
carries `round` and `rounds`; the Fill step says
"round 1: 553 placed · round 2: 640 · 113 to sort" (a quiet pill,
`data-rounds`, only when there were two).

Three rules the tech library forced, each found by a read, not a test:

- **Only the object words, not the class half** — every folder under
  Clothing would hold "clothing", and 1/k would dilute the parent.
- **A word one member says is that picture, not the folder.** The first read
  put every folder's misses into its profile ("Space" holds *conference
  venue, computer lab, hallway*; "Cooling" *cable reels, brewery production
  line*) and margin went 36 → 104. Two members must say it; members that
  agree on nothing leave the profile as it was.
- **A word belongs to the folder holding most of the pictures that say it**
  (`vergeml_filing_members_settle`, pure; equal counts keep it on both, an
  honest 1/k tie), **and a locked folder owns none** — *To sort* held seven
  smart speakers, won the word, and Batteries and Components both ceded it.

`pick.php` 27–28b (mutations red: the members' words put after the base's;
the centroid; the two-member rule). `sticky.php` H0–H4 on the box: R planned
for one word and holding two hand-placed pictures of another, a fill of four
— round 1 places one, round 2 places the leftover, the layer reads 4 members;
the second round removed → H1–H4 red. The suite's one-hot embed stub widened
64 → 4096: by section H more than 64 phrases had been embedded and two
collided at 1.0. `folders.spec`'s progress test asserts the rounds pill.

**The tech number and why it is not the verdict.** Read dry, against the
frozen band: fits 553 → 645, **sure 340 → 583**, likely 220 → 73, nothing
440 → 344 (floor 370 → 248, margin 36 → 62); 477 of the ok→ok picks keep
their folder, 31 move; the centroid lifts every ok score by 0.17 on average
(the floor was set for the text vector). The gate's ten points are met on the
number — and the dry sheet's re-read says what the number means: **the
batch-133 fill's 30 sure that Nathan marked come back right kept 14 · wrong
kept 13 · moved 3 · wrong dropped 0.** The folders hold what the fill did,
right and wrong; a member profile learns both. The engine's own C.4 dry sheet
reads better (sure: right kept 21 · wrong dropped 2 · wrong kept 2 · broad
kept 4). The refinement this points at, not built: **a member counts only
when its own placement was sure or the person's** (the moves trail knows the
score each placement had). Nathan's marks decide; the band is not re-taken
until then (the stop point).

## S10.9 — the picture's own words (`3fa8e13`)

A third phrase list per picture: the filename (split on `-_.` and space, the
extension, short words and numbers dropped), the title, and the alt — a hit
worth the describer's second phrase (0.85), **by words alone**
(`vergeml_filing_word_match`: the class spelled the one way 1.0, its head
noun 0.95, never a modifier — a keyboard is not a keyboard layout diagram —
never by vector), and read only where the describer's phrases left room.
One SQL fragment (`vergeml_filing_words_sql`) carries file, title and alt
into every reader: the fill, the dry run, the rule fit, the upload hook, the
baseline, the quality sheet.

**The alt, measured apart** (tech, dry): none → file → +title → +alt gave
margin 62 → 92 → 104 → **162** and fits 645 → 681 → 686 → 676. Every tech
alt is the describer's own sentence, and every noun in it hits a folder. So
the alt counts **only when a person wrote it** — the index keeps the
describer's alt, and the file's counts when it differs (the AI screen already
counts "alt equals the model's" the same way). Tech after: fits 686, sure
637, floor 147, margin 104. `pick.php` 29–29d (the third list dropped → 29
red).

## S10.5 rule 3 — the head noun (`3f720d9`, `d12448b`)

Where the describer's class half is the first phrase's **own modifier**
("cheese" of "cheese wheel") and names a folder in full **by its first class
or its leaf**, a first-phrase hit on a class that is only the phrase's head
noun is capped at 0.9 on every other folder, whichever path scored it. Cheese
0.7125 now leads Wheels 0.675 and the fill asks (it filed Wheels); "rocket
launch; launch" keeps its 1.0 on Launches. Two overreaches caught by the
sheets before they shipped: as first written (any class half, any class) it
weakened **17 right tech placements** — "mobile phone; electronics" lost its
1.0 on Phones because the planner had put *electronics* fourth on Batteries,
one sure became a question; and on the shop "klippan sofa; furniture" cost
Sofas a sure to Garden › Furniture, because *furniture* is the category, not
the modifier. Refined, **the tech rows are identical to the S10.9 run**.
`pick.php` 30–30d, the cap removed → 30 red.

## The shop, read only (ms2 not touched otherwise)

Against its band (`2037294`): fits 493 → 510, **sure 467 → 491**, likely
51 → 35, **siblings 25 → 16** (22 sibling placements now file — the Cameras
finding of S12), nothing 108 → 100 (floor 46 → 27, margin 60 → 71). Of the
ties, ten are the seeded "Backpacks or Backpacks", six are *Bookcases* vs
*Shelving* (Nathan's confirm gave Shelving the planner class *public
bookcase*), four are *Rings* vs a folder called *Gold* under Saws (the seed).
Not re-taken: the sheets first.

## The gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy
tree-view seed-shop ai-background` → green after S10.9 (guide 40/40, sticky
44/44, escaping 9/10 the known ratio row); the registers regenerated (two
tree sinks from `ffe3edd`, read as literals). Rule 3 touched only the pick;
`filing tree-view copy` green after it. `folders.spec`/`modes.spec` not run
this session (ms2 the stop point; the tech run is 14 min — S14's first gate
after the deploy check). **Both baselines 3/4 by design** until Nathan marks
the sheet; the runs are saved in the session scratchpad and the check has
`--save`.

## For Nathan

1. **Mark the sheet:** `docs/superpowers/mocks/shots/2026-09-17-quality-sample-s13-dry.html`
   — 30 sure of 637, 30 likely of 78, 9 marks carried; the verdict line at
   the bottom. That number beside 87 % / 57 % is S10.7 + S10.9 + rule 3.
2. **S10.5b's yes:** the catalogue is ready — `tools/box-seed-shop-tree-b.txt`,
   HEMA's public tree, 292 folders, Dutch, marketing folders kept (the .md
   beside it says how). The confirm sends **34 folders, one batch, 0
   credits**; no describes. Say yes (or name another retailer) and say when
   ms2 is free; the paste, the fill, the second band and sheet are S14's first
   hour.
3. Say when ms2 is free: `folders.spec` and `round.spec` there, and the
   walk of a fill with two rounds.

## Not done, and why

- S10.5b — the catalogue is written and costed (34 go, 0 credits); the paste waits on Nathan's yes and a free ms2 (stop points).
- Both bands re-taken — the sheets are judged first (stop point).
- The member refinement (count only sure or user placements) — a finding,
  one story for S14 if the marks say the wrong-kept 13 matter.
- The fill's progress row at 1600×1000 with every parent open — not reached
  (S11's "seen, not fixed").

## Next — S14

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S14: the foreign tree (S10.5b), the bands re-taken on the marks, the member profile trusts only sure placements (if the marks say so), then file by the product (S10.8)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S14)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "tools/box-seed-shop-tree-b.txt (S10.5b: written, HEMA's tree, 292 folders; pasted on ms2 after Unconfirm and the folders removed; tests/tree/filing-baseline-shop-b.txt and its sheet)",
    "tests/tree/filing-baseline.txt, tests/tree/filing-baseline-shop.txt (re-taken with the reason once Nathan has marked the S13 sheet)",
    "core/filing.php (if the marks say the wrong-kept 13 matter: a member counts only when its placement was sure or the person's — vergeml_filing_members_layers reads the moves trail; pick.php row, one mutation)",
    "core/filing.php, core/folder-talk.php, core/integrations/** (S10.8: a picture attached to a product goes where the product's categories say, sure, 'by the product', before any matching; tests/integrations/live.php)",
    "js/vergeml-folders.js (the rail's Fill step: 'placed by product N · by evidence N · to sort N'; the flipped order for structured libraries, mocked)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-17-s13-the-fill-learns.md (this: the three member rules, why the tech number is not the verdict, the alt, rule 3's two overreaches)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.5b, S10.8; the S10.7/S10.9/rule 3 Built paragraphs)",
    "memory: hetzner-box-fixtures (ms2, vgmls9), tests-never-touch-live-state, model-spend-discipline, shared-classes-break-the-matcher"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "S10.5b's catalogue and its confirm (0 credits after S10.1's split — say the number) are Nathan's yes before the paste",
    "The bands are re-taken only after Nathan's marks on the S13 sheet, and the re-take carries the reason",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire",
    "S10.8 touches products: every write measured on the box's Woo fixture, never on ms2's live catalogue without Nathan's word"
  ],
  "gates": [
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background → green (escaping 9/10 known)",
    "folders.spec and modes.spec green on the tech site after the first deploy; on ms2 once Nathan says it is free",
    "both baselines 4/4 or re-taken with the reason; the shop-b band and sheet taken"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-17-s13-the-fill-learns.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S14 of
every-picture-a-home. Write the card from the handoff to
.harness/active.json before anything else. Take Nathan's marks on the S13
sheet and re-take both bands with the reason; S10.5b with his yes on the
catalogue; the member refinement only if the marks say so; then S10.8.
Test first, one mutation per story, both sites measured, say every cost
before it is spent, never touch ms2 while Nathan is on it. Talk plainly.
End with a handoff carrying the S15 card.
```
