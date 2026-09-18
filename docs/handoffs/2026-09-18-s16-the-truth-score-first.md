# Handover — 2026-09-18, S16: the truth score first, the S15 verdict built, the source on the trail, file by the product (Opus 5)

Card: `.harness/active.json` (S16, as S15 wrote it). Plan:
`plans/every-picture-a-home.md` Phase D (S16 line written). Spec:
`docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md` (S10.7
and S10.8 carry S16 paragraphs). Evidence before: S15's handoff. Lean held:
the truth score before and after every rule, test first, one mutation per
story (each seen red), both bands re-taken with the reason after every rule
that moved a placement; ms2 read only — nothing there was filled, moved or
restored; the box runs this working copy.

**Spend: 0 credits.** Every run was read-only (the scorer, the probes, the
dry sheets, the bands); the phrase pairs the new rules ask about were
already cached by the picks. The `ai-background` gate described its three
fixture files twice (cents). A handful of `/embed` calls for the suites'
nonsense phrases (metered one per hundred). Writes outside the repo: the
librarian schema on the tech site is **4** (the moves table gained
`source` and `hit`; ms2's table gains them on its next batch); the
members' layers on both sites unchanged (the likeness pass runs at read
time, no stamp moved); the suites' own fixtures, restored.

Commits, plugin (`main`, deployed to the box after each): `88d644e` the
`lens` canon defect; `2b2e30a` a member word only where alike; `72c2a28`
bands; `274264f` the head-noun rule; `93c9563` bands; `cac7aa7` a folder's
name claims the planner classes it names; `560a94e` bands; `44ee087` the
pick says its source, the scorer by source; `df8823c` the source on the
trail (schema 4, the why card, sticky's H restated); `502d7eb` S10.8 task 1;
`8f49c7a` task 2; `eaaf02f` task 3 (the live woo case on its own pictures);
`61a1591` task 4's mock; `25e143a` the ask leaves a view home; `75a9ab4`
the surface doc; then the plan, the spec and this handoff.

## The score line, story by story

`node tools/box-eval.mjs tools/box-truth-score.php --site shop` — the
shop's 626 seed-labelled pictures, 581 of them mapping to a folder of the
C.5 tree; every pick fresh, nothing written.

| after | right-and-placed | right of placed | what moved |
|---|---|---|---|
| S15 (start) | 330 of 581 (57 %) | 69 % | — |
| `lens` canon | 333 | 70 % | 3 lens pictures right; 8 from an *electronics* siblings tie to a Lenses-or-TV & Video margin |
| member words alike | 333 | 70 % | 3 picks: two *laptop keyboard* (seeded under Laptops) Computers → Keyboards, a loudspeaker Audio → Electronics |
| head-noun rule | 333 | 70 % | 2 picks, none labelled |
| name claims | **348 (60 %)** | **71 %** | 8 lenses and 6 bookcases sure; one already-wrong *architecture* margin a wrong Garden sure |
| source / trail / product / views | 348 | 71 % | nothing (no product-bearing picture on either site) |

**By source, on the shop** (the scorer's new table): members 202 right of
253 (80 %), name 122 of 185 (66 %), the picture's own words 21 of 33, the
planner's own words **2 of 15**, matches 1 of 2.

## The probe first: the three leaves that lose most

Read off the score before any rule (`scratchpad/s16-losers-probe.php`,
`s16-causes-probe.php`, read-only, gone with the session):

- **Backpacks 0 of 11 placed** — the tree has two leaves named *Backpacks*
  (`bags & luggage`, `sports & outdoors › camping`) and every picture ties
  0.88 vs 0.88 → margin → nothing. 8 of the 11 say a class half naming the
  right leaf's *parent* (bags, bag, luggage, baggage). Over the whole
  truth: 14 namesake margins, 9 with a class half naming one leaf's
  ancestor. **Not built** (not on the card): on a margin between
  namesakes, the leaf whose ancestor the class half names would win.
- **Bookcases 0 of 11** — 0.89 against Shelving 0.83–0.88, inside the
  margin every time, because the planner gave Shelving *public bookcase*
  beside a leaf named Bookcases. The same shape as TV & Video's *camera
  lens* beside Lenses — built as the name-claims rule (below), +14.
- **Men's sweaters 8 wrong of 11, jackets 4 of 6** — all 17 pictures have
  audience `''` (the describer did not say *men*), the men's leaf is
  **gated**, and the namesake or the parent takes it: *motorbikes ›
  jackets* gets four wrong sures, Scarves two by *knitwear*. Of the 8
  sweater "wrongs", 5 are the seed's own noise (four doll cardigans, a dog
  sweater: the engine is right, the label is not). **Over the whole truth:
  44 of 581 have their own folder gated by audience — 15 wrong sures, 2
  wrong likelies, 12 nothing, 15 broad. The largest loss left. Not built:**
  a folder gated only by audience could still stand as the runner-up, so
  the pick becomes a "Men's jackets or Motorbike jackets?" question instead
  of a wrong sure (~17 wrongs into questions; no new rights). Nathan's
  call: the spec's rule is "a gendered folder needs the picture to say so".

And a defect on the way: `vergeml_filing_canon('lens')` stripped the *s*
to `len` while `lenses` folded to `lens` by the table, so *camera lens*
never equalled Lenses (11 of 12 scored 0.63 on their own folder). A word
the table gives as a singular is left alone (`pick.php` 17c).

## Story 1 — a member word only where alike to the folder's own (`2b2e30a`)

Nathan's S15 verdict, first: 6 of 7 wrong sures were words the folders had
learned from the fill's own misses. `vergeml_filing_members_alike`, before
the settle (so a word a folder cannot keep is not the one its rivals cede
to): a member word counts where it is alike to one of the base profile's
words — the plan's or the name's — equal, inside, head noun, or the phrase
vector at the **members' floor 0.5** (`VERGEML_FILING_MEMBERS_ALIKE`). The
first cut used the class match's 0.6 and took Robotics' *industrial robot*
(0.57 to *robotics*) and Launches' *falcon 9 rocket* (0.58 to *rocket
launch*) with the misses; the cosines read off the box drew the line:
rightful words 0.51–0.58, the learned misses 0.15–0.44 (cable reels ~
cooling 0.27, robotic arm ~ server racks 0.35, smart speaker ~ electronics
component 0.33, telecommunications mast ~ networking equipment 0.44).
`pick.php` 34–34b; the likeness test removed → 34, 34a, 34b red; the floor
made 0.6 → 34 red.

What the pass takes off on tech now: Cooling's *cable reels*, Server
racks' *fibre optic cable, telecommunications mast, robotic arm, cable
drums, software interface*, Components' *desktop computer, smart speaker,
optical fiber cable*, Batteries' *smart speaker*, Hardware's *smartphone,
mechanical keyboard*, People's *conference attendees, child*, Space's
*conference venue*. Tech 647/593/72 → 620/558/69, floor 274 → 323. **The
S15 sheet: wrong sures 7 → 3, right 19 → 17 (two desktop computers to
Hardware, the parent — broad, not wrong), none lost; wrong likelies 13 →
9.** The S13 sheet: wrong sures 4 → 3, right 24 → 22 the same two. The
sticky suite's H fixture had R learning *zzstickymember*, a word nothing
about R is like — exactly what the rule refuses; restated as *zzstickyround
gear* (alike to the plan's word by containment), the leftover *gear* placed
in round 2 by containment at 0.95 (44/44).

## Story 2 — a one-word class as the class half's head noun is no hit (`274264f`)

*hallway; interior space* → Space, *garage; commercial space* → Space ×3,
*griptape; skateboard component* → Components: 5 of the S15 sheet's 13
wrong likelies. `vergeml_filing_head_of`; in the pick, a one-word folder
class that is the head noun of the picture's class half is no hit unless it
is the folder's first class (a name-only folder has nothing else) or the
half is the word whole; the modifier direction (*launch* of *launch
event*) stands as S15 left it. `pick.php` 35–35d; the test switched off →
35, 35a red. Tech 620/558/69 → 611/558/60; **the S15 sheet's wrong
likelies 9 → 4**, sures untouched; the S13 sheet one right likely lost at
the floor (0.55). Shop: two placements moved, neither labelled.

## Story 3 — a folder's name claims the planner classes it names (`cac7aa7`)

The card's profile finding (Interviews claims *people* first, TV & Video
*camera lens*) is the shape the score found on Shelving. Judged dry first
(`s16-leafname-probe.php`, in memory): shop 333 → 347 right, none lost; tech
6 picks move, all to People or Conference talks. `vergeml_filing_name_claims`,
in `vergeml_filing_profiles` before the claims are settled: a planner class
whose whole is another folder's leaf name, or whose head noun is another
folder's one-word leaf name, comes off the planner's folder; a member word
stays (it passed the likeness test), the own leaf stays, a view claims
nothing. On the C.5 tree it takes 22 planner classes off (*camera lens*
off TV & Video, *public bookcase* off Shelving, *bicycle* off Cars & Bikes,
*jigsaw puzzle* off Outdoor play…). `pick.php` 36–36a; the pass a no-op →
both red. **Shop 333 → 348 of 581 (60 %), right of placed 70 → 71 %,
none 108 → 93.** Tech 611/558/60 → 615/562/56.

## The placement's source, on the pick, the trail and the scorer (`44ee087`, `df8823c`)

The pick returns `source` — plan / name / members / matches / word /
vector (no class hit at all) — and `hit`, "picture phrase ~ folder word",
for the winner or the nearest (`pick.php` 37–37c; the source not carried →
all four red). The moves row keeps both (librarian schema **4**, additive:
`source varchar(16)`, `hit varchar(160)`; `vergeml_talk_reason` packs them
as the tuple's sixth and seventh; `filing-trail.php` a row per outcome and
the upgrade list, 123/123 — the run that carried the upgrade inside its
pass read 122/123 on "the re-filing state is as it found it", every run
since 123/123; the tech site's state read back afterwards is a real fill
state, no fixture in it). The why card reads them: **"Matched fibre reel ~
cable reels · a word its pictures taught it"** (the five "whose" phrases and
"No word matched · placed by likeness alone" are **my wording, not the
plan's — Nathan's to change**; no live row carries the columns yet, so no
screenshot; rows written from now on do). The scorer prints "placed, by
the source of the hit".

## S10.8 — file by the product (`502d7eb`, `8f49c7a`, `eaaf02f`, `61a1591`)

Task 1, the map, pure: `vergeml_filing_product_folder( $cat_path,
$profiles )` — the category's path by canon names, else the one folder
whose leaf is the category's leaf, else 0; never a view (`pick.php`
38–38b, "the first" instead of "exactly one" → 38b red, the leaf fallback
removed → 38a red). Task 2, the fact: `vergeml_filing_product_sql` (the
product it is the featured image of, the one whose gallery lists it, the
one it was uploaded to — one correlated lookup each on the meta key, NULL
without Woo's product type), `vergeml_filing_product_folders` (one terms
query for the slice's products, the deepest category that names a folder
→ `product_folder` on the row); the pick answers `fits` / `sure` / why
`product` / source `product` / score 1 before any matching and reads
`placed_by = product` as placed; the run marks its by-product placements
and undo clears the mark with the move; the tally counts `product`; the why
card says "In X · by the product it belongs to" (`pick.php` 39–39c;
`sticky.php` I1–I5 on the suite's own product, category and three pictures
— the product path removed → I1–I4 red). Task 3: the live `woo` case
(`tests/integrations/live.php`) was red before S16 — its fixtures 1778,
1775 and 1774 went with the tech site's seed — and now makes its own three
pictures, checks the describe context and the repoint as before, and the
S10.8 count **dry** (nothing moves on the live library): both by the
product, sure, the copy nowhere, a second count the same, everything put
back; 9/9 on the tech site, the product path removed → red (run with
`scratchpad/box-live.mjs woo`, a copy of `box-eval.mjs` that passes the
positional argument). Task 4, **mocked, not built**:
`docs/superpowers/mocks/2026-09-18-fill-by-product.html` and its shot —
the rail Tree → Fill → Describe for a site that sells, one quiet line under
it ("This site sells: the products place their pictures first. Describing
is for what nothing placed."), the pills **by product · by evidence · to
sort** in the running and the done state; the report already carries
`tally.product`. Nathan's yes first.

## The ask leaves a view home (`25e143a`)

`vergeml_filing_draft_views` applies S15's view rule on the draft's own
shape ({key, name, parent}); `vergeml_filing_ask_split` skips a view and
everything under it — the planner's 24-class answer for *nieuwe collectie*
was words a view cannot use. `pick.php` 24c (the skip absent → Sale and
Sale › Garden go).

## Probed, not built

- **The class floor** (the card's last item): after this session's rules
  only **7 placements** stand on a vector-only class-half hit — tech 5
  likelies (two VR headsets to Hardware by *gaming hardware ~ graphics
  card* 0.65, a fibre cable to Server racks by *networking hardware ~
  networking equipment* 0.85, a game controller to Hardware, a
  microcontroller programmer to Components: broad or right), shop 2
  likelies (two sweaters to Scarves by *knitwear ~ knit scarf* 0.76, both
  wrong). A rule would take 2 wrongs off the shop and 5 likelies off tech,
  three of them defensible. Nathan's call.
- **The audience gate** (44 of 581) and **the namesake tie by the ancestor
  word** (9 of 14) — above, the two largest shop losses left, both off the
  card.
- **The audience words in the site's language** (dames, heren, kinderen…):
  not reached — it needs HEMA's tree on ms2 (a snapshot swap, no credits)
  and ms2 free.
- **The tech truth page**: shape proposed, no yes yet — one page, ~200
  tech pictures picked fresh, thumbnail and the fill's folder pre-filled in
  a select of the 21 folders, a "wrong picture / no folder" choice, a tally
  at the top, Save gives a JSON the scorer reads with `VGML_TRUTH`.
- **The why card's copy** for the source line (above) — mine, to review.
- The seed's own noise in the truth: four doll cardigans and a dog sweater
  labelled *men's sweaters*, staircases labelled *mirrors*, a bridge
  labelled *headphones* — the score's "wrong" carries a few of the seed's
  wrong fetches; a hand pass over the wrong pairs would take them out.

## Gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy
tree-view seed-shop ai-background` → green (filing 88/88 + 34/34, sticky
49/49, guide 41/41, surface 27/27 after its line numbers, roles 19/19,
escaping 9/10 the known ratio row, copy 58/58, tree-view 65/65, seed-shop
8/8, ai-background 37/37); `filing-trail` 123/123; the live `woo` case 9/9.
Tech band 4/4 (re-taken three times, each with its reason), C.5 shop band
4/4 (re-taken three times). The shop-b band reads whichever tree is up
(the C.5 tree, so it reads red until HEMA's is restored). `folders.spec`
and `modes.spec` not run this session (no screen changed).

## For Nathan

1. **Five calls**, none built without your word: the audience gate as a
   runner-up (17 wrong sures → questions), the namesake tie by the ancestor
   word (9), the class floor (7), the truth page's shape, the rail mock.
2. The why card's five "whose word" phrases are my wording — change them
   in `core/librarian.php` (`$whose`) or say the words.
3. ms2 shows the C.5 tree; HEMA's is a snapshot away for the audience
   words — say when it is free.
4. `scratchpad/box-live.mjs` is in my session's scratchpad, gone with it;
   the live case runs by `wp eval-file /tmp/vgml-live.php woo` on the box
   as the file's header says.

## Late in the session, on Nathan's yes: the audience gate as a runner-up (`0aff174`)

Nathan's answers (2026-09-18): 1 yes, 3 leave, 2 / 4 / 5 / 6 explained
and open. Built: when the picture says no audience, a folder gated by
audience alone keeps its score as a shadow — never the pick, but the
runner-up the margin is judged against; a picture the describer gave an
audience stays out for good. `pick.php` 40–40c, the shadow dropped → red.
**Shop: SCORE right-and-placed 347 of 581 (60 %) · right of placed 72 %**
— wrong sures 60 → 55, wrong likelies 3 → 1, one right to a question, 9
placements to questions in all. Fewer than the 17 estimated: *motorbikes ›
jackets* beats the gated men's leaf by 0.09 on the rest (its members'
centroid), just outside the 0.08 margin. Tech band identical (no gendered
folder there); the shop band re-taken (`2269c53`). The class floor: left,
on Nathan's word.

## Next — S17

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S17: Nathan's five calls from S16 first (the audience gate as a runner-up so a gated folder makes a question, not a wrong sure — 44 of 581; the namesake tie broken by the class half naming one leaf's ancestor — 9 of 14; the class floor — 7 placements; the tech truth page's shape; the rail mock's yes), each built only on a yes and judged by the SCORE line; then the audience words in the site's language on HEMA (dames, heren, kinderen, kind, baby, meisjes, jongens — restore vgml-shop-tree-hema.json first, 0 credits, ms2 free); the tech truth page built and marked (~200 pictures, ~30 min of Nathan's, no credits) so the tech library scores too; the rail built from the mock; then S10.10 the proposal from the pictures (a Fable-sized phase: groups to name and nest, per kind first, thumbnails, the pack's own signals)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S17)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "core/filing.php (the audience gate as a runner-up, on Nathan's yes: a folder gated by audience alone keeps its score for the margin, never as the pick — pick.php row on a men's jacket with audience '' beside motorbikes › jackets: a margin, not a sure; mutation: the gated score dropped -> the sure is back; judged by the SCORE line: wrong sures fall, right does not)",
    "core/filing.php (the namesake tie, on Nathan's yes: on a margin between two leaves of one name, the leaf whose ancestor the class half names wins — pick.php row on 'hiking backpack; luggage' between bags & luggage › backpacks and camping › backpacks; mutation: the ancestor test removed -> margin; judged by the SCORE line: backpacks 0 -> 8 of 11)",
    "core/filing.php (the class floor, on Nathan's yes: a class-half hit by vector alone corroborates, never places alone — pick.php row; judged by the SCORE line and the S15 sheet; 7 placements today)",
    "core/filing.php (vergeml_filing_audience_of reads the site's language: dames, heren, kinderen, kind, baby, meisjes, jongens; pick.php row; judged on HEMA round 1 with the tree restored, 0 credits)",
    "tools/truth-page.php, tools/box-truth-score.php (the tech truth page on the shape Nathan approves: ~200 pictures picked fresh, the fill's folder pre-filled in a select of the tree's folders, wrong picture / no folder, a tally, Save -> a JSON the scorer reads with VGML_TRUTH; then the SCORE line for the tech library in every check-in too)",
    "js/vergeml-folders.js, core/folder-talk.php (the rail from the mock 2026-09-18-fill-by-product.html on Nathan's yes: the pills by product · by evidence · to sort from tally.product, and the flipped order for a site that sells; folders.spec rows; the copy verbatim from the mock)",
    "core/librarian.php (the why card's source line wording, Nathan's words)",
    "tests/tree/filing-baseline.txt, tests/tree/filing-baseline-shop.txt, tests/tree/filing-baseline-shop-b.txt (re-taken only with the reason and the SCORE line)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-18-s16-the-truth-score-first.md (this: the score table, the three probes, the five calls, the source on the trail, S10.8's four tasks)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.7's S16 paragraph, S10.8's S16 paragraph, the order of the steps by library)",
    "tools/box-truth-score.php (the gate: the SCORE line, the wrong pairs, the leaves that lose, placed by source)",
    "docs/superpowers/mocks/2026-09-18-fill-by-product.html and its shot (the rail as mocked)",
    "memory: hetzner-box-fixtures, tests-never-touch-live-state, model-spend-discipline, probe-the-premise-before-the-build, truth-score-not-sheets"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "ms2 shows the C.5 tree (site language nl_NL); HEMA's tree is /var/www/ms2/wp-content/vgml-shop-tree-hema.json — swap with tools/box-tree-snapshot.php (snapshot the one up first), never while Nathan is on ms2",
    "The audience gate, the namesake tie, the class floor: each only on Nathan's yes, each judged by the SCORE line before it stays",
    "The truth page and the rail: a shape or a mock shown, Nathan's yes, then the build",
    "The why card's copy is Nathan's: never write a user-facing string the plan did not supply",
    "A band is re-taken only with the reason and the SCORE line",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work: restore a mutation by the same string edit that made it"
  ],
  "gates": [
    "node tools/box-eval.mjs tools/box-truth-score.php --site shop: the SCORE line printed in every check-in; a rule stays only when right-of-placed does not fall (today 348 of 581, 60 %, right of placed 71 %)",
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background filing-trail → green (escaping 9/10 known)",
    "the live woo case 9/9 on the tech site (wp eval-file tests/integrations/live.php woo)",
    "folders.spec and modes.spec on the tech site green when the rail is built",
    "the tech and C.5 bands 4/4 or re-taken with the reason; the shop-b band re-read when HEMA's tree is up"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-18-s16-the-truth-score-first.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S17 of
every-picture-a-home; the card is already in .harness/active.json —
read it before anything else. The truth score first (print its SCORE
line); then Nathan's five calls from S16, each only on his yes and each
judged by the score line before it stays; then the audience words on
HEMA (the tree restored from its snapshot, ms2 free, 0 credits); then
the tech truth page on the approved shape; then the rail from the mock.
Test first, one mutation per story, both sites measured, say every cost
before it is spent, never touch ms2 while Nathan is on it. Talk plainly.
End with a handoff carrying the S18 card.
```
