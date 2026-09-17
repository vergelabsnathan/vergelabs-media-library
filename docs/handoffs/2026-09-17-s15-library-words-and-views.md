# Handover — 2026-09-17, S15: library words, the class half inside a phrase, a view of the tree owns nothing, the apply's dead round trips (Opus 5)

Card: `.harness/active.json` (S15, as S14 wrote it). Plan:
`plans/every-picture-a-home.md` Phase D (S15 line written, S16 and S17
lines moved). Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(S10.5b, S10.7 and S10.8 carry S15 paragraphs). Evidence before: S14's
handoff. Lean held: the probe before every rule, test first, one mutation
per story (each seen red), both sites measured dry; ms2 read only —
nothing there was filled, moved or restored; the box runs this working copy
(ms2 the same directory), which changes what the next fill *would* do and
nothing of what is there.

**Spend:** 27 credits on ms2 for the leaves' planner ask (Nathan's yes, below); 0 for the engine work. One `/embed` of
a nonsense phrase to time a cold round trip (metered one per hundred). The
`ai-background` gate described its three fixture files as every run does
(cents). Writes outside the repo: the member-layer caches in term meta on
both sites (rebuilt once under the new stamp, `/h`); `vgmls15`, an
administrator on the tech site for the UI specs — **delete it** (below) if
the full run's end did not reach this handoff; the guide and sticky suites'
own fixtures, restored.

Commits, plugin (`main`, deployed to the box after each): `aa30779` the
library-word rule and the class half inside a phrase; `b2f9c5f` both bands
re-taken; then the S15 dry sheet; `1b01b9a` a view of the tree owns nothing
(shop-b re-taken again); `5d31260` the apply's per-folder round trips
removed; `f366116` the apply's row; the security docs; `e54fe50` the foreign leaves to the planner (shop-b re-taken); `1ee7a56` k by lines (both bands re-taken); `da07996` the audience split and the C.5 tree back (its band re-taken); then the spec, the plan and this handoff.

## The gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy
tree-view seed-shop ai-background` → green (filing 63/63 + 34/34, sticky
44/44, guide 41/41, surface 27/27, roles 19/19, escaping 9/10 the known
ratio row, copy 58/58, tree-view 65/65, seed-shop 8/8, ai-background 37/37).
Tech band 4/4 (re-taken once, with the reason); shop-b band 4/4 (re-taken
twice, each with its reason). The C.5 shop band cannot be read while HEMA's
tree is up: **when the C.5 tree goes back it will read red** — re-read its
marks by path first (`VGML_SITE=shop VGML_DRY=1` through the sheet tool),
then re-take with the reason. `folders.spec` + `modes.spec` on the tech
site: the progress-row test green with the branch in, red with it out; the
full run as vgmls15: **24 passed, 3 skipped, 0 failed (25.7 min)**, the same three skips as S14.

## The probe first: 93 pictures say *electronics*, none of them in Batteries

Read off the tech library before any rule (`scratchpad/s15-class-half-probe.php`,
read-only): 319 distinct class halves; *electronics* is said by 93 pictures
(148 with "electronic component", "wearable electronics", "consumer
electronics" by containment), sitting in **Components 65 · To sort 13 ·
Phones 9 · Hardware 6 — and none in Batteries**, which carries the word
from the planner at rank 3. So "X; electronics" scored Batteries 0.72
(whole, k 1) against Components 0.69 ("electronics component" by
containment): S14's coin flip. The same shape on *computer hardware* (64
pictures, 49 in Hardware; a planner word on Hardware and Laptops both) and
*infrastructure* (30, on five folders).

Of the S13 sheet's 7 wrong likelies still placed: 2 were Batteries'
*electronics*; 2 the class half hitting a member word by containment
("interior" inside *desktop computer interior*; "technical diagram" over
*diagram*, a kind word Satellites' old plan still carries); **3 are vector
class matches at 0.62–0.70** with a high cosine ("workspace" ~ *space*,
"computer equipment" ~ *computer terminal*) — the class floor's business,
not a word rule's. Found, not done.

## Story 1 — a class half the library says is a library word (`aa30779`)

`vergeml_filing_members_layer` carries the class halves two or more members
say (`halves`, canon); `vergeml_filing_members_apply` puts them on the
profile; `vergeml_filing_settle_claims` counts, for every folder class, the
folders whose members say it — the class equal to what they say, or
**inside** it ("electronics" in "consumer electronics") — and k is the
larger of that and the folders holding the class. The layer's stamp moved
(`/h`), so every cached layer rebuilt once. `pick.php` 31–31d; the count
not folded into `shared` → 31a and 31b red.

**The first cut read containment both ways and was wrong:** Hardware's own
member word *desktop computer interior* was demoted to k 3 by the word
*computer* inside it (said as a class half in Laptops, Hardware and Server
racks), and sure fell 599 → 580 with a right Robotics sure lost to a
margin. A specific phrase that *contains* a library word is not one. One
direction only.

## Story 2 — the class half inside a folder's specific phrase is no hit (`aa30779`)

With Batteries' hold gone, 22 pictures with no folder of their own —
drones, a quadcopter, 3D printers — went to Components, likely, through
*"electronics" inside "electronics component"* (0.95 by containment), and
four "3d printer; equipment" to Energy through *"equipment" inside
"renewable energy equipment"*. In the pick, the class half sitting whole
inside a folder's **non-first, non-leaf** class is no hit
(`vergeml_filing_inside`, the pick's class-half line); the other direction
("launch" is the head of "launch event") stands, and so does a folder's
first class or its own leaf ("appliance" under *Kitchen appliances*).
`pick.php` 32–32d; the inside test removed → 32 and 32b red.

**The pair, dry.** Tech: fits 655 → 645, sure 599 → 594, likely 71 → 68,
siblings 15 → 17, **margin 64 → 27**, floor 232 → 277. The three sheets by
path (S14 in brackets):

| sheet | sure | likely |
|---|---|---|
| S13 | right kept 24 · right lost 1 (the shuttle launch, as S14) · **wrong dropped 1** · wrong kept 4 [5] | right kept 5 · **wrong dropped 13** [10] · right lost 1 (106757, as S14) · wrong kept 4 [7] · broad kept 6 · moved 1 |
| C.1 | right kept 21 · right lost 1 (106285, Robotics — Server racks learned *robotic arm* from two members; a margin in S14 too) · wrong kept 3 · broad kept 3 · moved 2 (105861 → Phones sure, 106552 → Launches) | right kept 5 · wrong dropped 3 [2] · wrong kept 4 [6] · broad kept 11 · moved 7 |
| batch 133 | identical | right kept 4 [3] · wrong dropped 2 · right lost 1 [2] · wrong kept 1 [3] · moved 21 |

The full-run diff (`scratchpad/band-diff.mjs`): 13 Batteries sures out —
all "electronic waste; recycling…", two of them Nathan's wrong marks; 22
margins → floor (the drones and printers, to the residue instead of a
"Batteries or Components?" question); 4 Cooling and 5 Hardware likelies on
a lone class half down; 6 ties into Components, Phones and Server racks as
sures (microchips, capacitors, a phone's insides, a fibre spool). No right
placement lost that S14 had not lost.

Shop-b (HEMA's tree, dry): fits 449 → 428, sure 255 → 258: 28 likelies out
of *nieuwe collectie* (neckties, scarves, handbags by *accessory* k 2;
phones, a game controller, a smart speaker by "electronics" inside its
*wearable electronics*), 3 sures into *buiten en onderweg* (sunglasses, a
bicycle light), 4 laptops into *school en kantoor*. Both bands re-taken
with those reasons (`b2f9c5f`). The S15 dry sheet is
`docs/superpowers/mocks/shots/2026-09-17-quality-sample-s15-dry.html`,
unmarked, the earlier marks carried where the pick is the same folder.

## HEMA's findings

**Finding 1, judged and not built as a count.** Round 1 on HEMA (base
profiles, no members; `scratchpad/s15-round1-probe.php`): only **9 of 292
folders** have a planner profile (the Dutch leaves are name-only), and
*nieuwe collectie*'s has **24 classes** — furniture, jewellery,
electronics, audio equipment, accessory, eyewear, smartwatch, table lamp,
armchair… the library's vocabulary read back (23 of 24 in it); every other
parent 3 to 11. It took 201 of 626 on round 1, every one some
department's. The one existing number with a written reason is
`MEMBERS_WORDS = 8`; measured dry: a planner profile past eight read from
its name alone → **fits 422 → 140** (*buiten en onderweg* — bicycle,
hiking boot, footwear, luggage, sport, 11 classes — is a department, and
its 95 went with the 201); cut to eight → nieuwe collectie still 162. The
count is not the signal.

**Findings 1 and 3, built as one (`1b01b9a`).** The tree's own shape is:
*sale* has 11 children of which **10 are the tree's top-level names**,
*nieuwe collectie* 12 of which 11; a department repeats one in twelve
(*baby* under *wonen en slapen*). `vergeml_filing_views` (pure, in
`vergeml_filing_profiles` before the members layers): a folder more than
half of whose children repeat names held **higher** in the tree is a view
— higher, because the view's copy of a department repeats that
department's children in turn (sale › home › furniture is deeper than home
› furniture), and a naive count made Home a view in the fixture. A view
and everything under it keep no class and no vector, learn nothing from
members (a sale is curated, not a kind), and the copy of a name under a
view is never filed into — the folder of that name elsewhere is the one.
`pick.php` 33–33b; the pass removed → 33 and 33a red; the majority made
"any child" → 33b red. Dry on ms2: **nieuwe collectie 184 → 0**, fits 428 →
259, sure 258 → 186, likely 170 → 73, margin 14 → 3; of the 180 it gives
up, 13 land in their own folders (pearl necklaces to *dames › accessoires ›
sieraden*, lamps to their leaf), the rest go to the residue. **The honest
number for a tree whose names say nothing is sure 30 %** (was 41 % with the
marketing folder counted). Tech: no views, band identical. Not done: the
ask split (S10.1) still sends a view to the planner; leaving it home saves
the credits and the 24-class answer — a small follow-up in
`vergeml_filing_ask_split`.

**Finding 2, built on Nathan's yes ("spend the credits"), `e54fe50`.**
The phrase vector gets cognates part-way (*elektronica* ~ electronics
0.67) and never enough to place. The rule: on a site whose language is not
the describer's (`vergeml_filing_tree_is_foreign`, the locale), a leaf
whose name no picture says goes to the planner too
(`vergeml_filing_ask_split( …, $foreign )`; `pick.php` 24b, the branch
removed → red). ms2 was `en_US` — the fixture's artefact, not a shop's —
so the `nl_NL` pack was installed and the site set to it (WP refuses a
language it does not have; `update_option` alone was silently rejected,
and the locale is fixed at load, so the first confirm ran as English and
re-profiled five parents for 0 credits). Then unconfirm + confirm through
the route: **290 go, 5 batches, 27 credits, 241 s; 64 leaves named** —
*koekenpannen* = frying pan, *nagellak* = nail polish, *portemonnees* =
leather wallet, *messen* = chef's knife, *puzzels* = jigsaw puzzle; misses
*melkopschuimers* = beauty appliance, *meisjeskleding* = doll cardigan; the
rest came back "nothing" (name-only, as before).

**What it did, dry.** On the state as it stands: ~50 pictures into leaves
for the first time (armchairs → *fauteuils* 16 sure, lamps 8, watches,
wallets, knives), and 48 *out* of *buiten en onderweg* — the parent had
learned *hiking boot* from S14's members and the re-profiled *dames* now
holds it too (k 2). **Round 1** (the honest read, `s15-round1-probe.php`
with views): leaves by name fits 297 (sure 159, likely 138); leaves as the
planner named them **fits 203 (sure 152, likely 52)** — worse, because a
leaf and its own parent holding one word are halved by 1/k as if rivals;
with **k counted by lines** (a folder whose ancestor holds the word is that
line, not a second holder) **fits 247, sure 195, likely 54**: the broad
parent placements become leaf sures, the boots go to a *dames*-or-*buiten*
question because the Dutch audience words are not read as audiences. Two
rules for S16, judged on the tech sheets first (k by lines moves the tech
band: *computer hardware* on Hardware and Laptops is one line,
*infrastructure* on five folders is two). The shop-b band re-taken with
all of this as the reason (244 / 180 / 77).

## The apply's 188 s (`5d31260`, `f366116`)

Measured on ms2 (read-only): the profile's text was cached for all 292
folders (the paste's dry run prefetches it), and the apply asked the
service for a **second** text per folder — "name. matches", never
prefetched — into `state['vectors']`, which nothing reads; one cold round
trip is 3.4 s from the box today, ~0.5 s then, × 292 ≈ the 150 s. The loop
and `vergeml_talk_vector()` are gone; the "could not reach the service"
answer stays, read off the profiles the seed built. `guide.php` G2 counts
the apply's `/embed` requests (0; the transients emptied first, or the
week-old cache hides the loop — it did, once); the loop put back → 5, red.
Then the row: pressed and not yet answered, `renderMove` said "Filling 0
pictures so far"; it now says **"Making 292 folders · 4 s"** (the draft's
folders, or the live ones on a fill after a fill) with the open bar until
the first report. `folders.spec`, the progress row: the model driven; the
branch out → red ("Filling 0 pictures so far"). The apply on HEMA's 292
should now take the passes' 39 s plus the inserts — not measured live (ms2
is Nathan's); the next paste there says.

## S10.8 — the premise probed, the story for S16

Neither site has a product-bearing picture: the tech site runs Woo with 8
products and none has an image; ms2 has no Woo, its 626 carry
`_vergeml_seed_leaf`. And there is no folder ↔ product-category map in the
plugin: the map is the story. Written into the spec's S10.8 and the S16
card below in the Opus shape.

## Found, not done

- **The class floor for vector-only class-half matches:** 3 of the 7
  ("workspace" ~ *space* 0.62, "computer equipment" ~ *computer terminal*
  0.70, "computer workstation" ~ *computer peripheral* 0.69) — a class half
  placing alone on a phrase-vector likeness above 0.6. A rule to judge: a
  class half's vector match corroborates (like S14's words), never places
  alone. Probe first: how many likelies stand on a vector-only class hit.
- **The ask split leaves views home** (above).
- **Satellites' cached plan carries *diagram*** (S14's note): a re-plan or
  Restore clears it; "technical diagram" → Satellites stands on it.
- **Server racks learned *robotic arm*** from two members the fill put
  there, and Robotics (whose word is *industrial robot arm*) loses 106285
  to a margin: profiles when a member's own trail is wrong, as S14 said.
- **The C.5 shop band** reads red when its tree returns until its marks are
  re-read by path and the band re-taken.

## Late in the session, on Nathan's "anything else?": k by lines, the C.5 tree back

**k by lines ().** In  a folder whose
ancestor holds the same word is that ancestor's line, not a second holder
(, for the holders and the sayers alike); between
parent and child the depth rule chooses.  rows 0 and 15 restated
with the reason (infrastructure: Data centres' line and Space, 2 not 4);
the ancestor walk removed → row 0 red. Tech: the three sheets identical,
645/594 → 647/593, five placements move — a modem to Server racks (right),
a keynote to a People siblings tie, a game controller to Hardware likely
(broad), and two the wrong way that are profile findings: a car charging
station to Server racks likely (*infrastructure* on 3 lines, not 5) and
"figure; person" from People to *Interviews* sure (Interviews' plan claims
*people* first). HEMA round 1: sure 152 → 195, likely 52 → 54; as it
stood: fits 244 → 292, sure 180 → 227 (36 %). Both bands re-taken.

**The C.5 tree back ().** HEMA's tree snapshotted first with its
named leaves and profiles — (292 terms, 303 termmeta, 440 relationships; restoring it costs no
credits) — then the C.5 tree restored with its ids. Its band read red and
the sheet showed why: **Watches read as a view** (its children Men, Women,
Smart watches; Men and Women sit at the top too) and 14 watch sures fell.
A child named for an audience is a split, never a repeat, and never a vote
( 33c, the exception removed → red). Then: no views on C.5, the
sheet as S14 read it (sure 23 · 1 · 2 · 4; likely 8 right kept, 248 lost,
7 wrong, 6 broad, 7 moved), the band 510/488 → 495/479 with the tree's own
ambiguities left: 8 digital cameras to a Cameras-or-TV & Video tie (the
planner put *camera lens* first on TV & Video — S12's finding, C.4's kind)
and 5 sandals and boots out of Shoes (*footwear* on four lines: Shoes and
the seeded Sneakers under Men, Women, Kids). Re-taken with the reason.
**ms2 shows the C.5 tree now** (site language still ); the shop-b
band reads whichever tree is up, so it reads red until HEMA is restored.


## For Nathan

1. **Mark the S15 sheet** if you want its number beside S13's; the two
   Batteries likelies and the e-waste sures are gone from it.
2. **Finding 2 is spent and measured** (27 credits): the leaves have English words; ms2's site language is now `nl_NL` (the pack installed) and stays so — it is the HEMA fixture's honest setting.
3. **ms2 shows the C.5 tree again**; HEMA's is a snapshot away (see above), no credits.
4. `vgmls15` on the tech site: deleted at the end of the full UI run if it
   reached this handoff; otherwise `node tools/box-eval.mjs
   tools/box-ui-admin.php --env VGML_ACTION=delete --env VGML_USER=vgmls15`.

## Next — S16

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S16: the audience words in the site's language and the two profile findings (Interviews claims people first; TV & Video camera lens), S10.8 file by the product (the map, the fact, the pick, the live test; the rail's 'placed by product N'), the flipped order for structured libraries (mocked), the class floor for vector-only class-half matches",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S16)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "core/filing.php (S10.8 task 1 — the map, pure: vergeml_filing_product_folder( $cat_path, $profiles ): a product_cat's path to the folder whose path equals it by canon names, else the folder whose leaf equals the category's name when exactly one does, else 0; pick.php rows, mutation: the leaf fallback removed)",
    "core/filing.php, core/folder-talk.php (S10.8 task 2 — the fact: a row's product folder from _thumbnail_id, _product_image_gallery and post_parent (vergeml_filing_product_sql, the shape of vergeml_filing_words_sql); the pick answers fits / sure / why 'product' before any matching and treats placed_by 'product' like 'user'; the run records placed_by = product and the reason line 'by the product'; sticky.php rows on the suite's own product, mutation: the product path removed -> the matcher)",
    "tests/integrations/live.php (S10.8 task 3 — 'woo': its own product in a category, a folder of that name, two pictures as featured and gallery -> both by product, sure; a second count -> unchanged; everything put back; on the tech site, never ms2)",
    "js/vergeml-folders.js (S10.8 task 4 — the Fill step's rail: 'placed by product N · by evidence N · to sort N' from the report's tally; the flipped order for structured libraries mocked first, Nathan's yes)",
    "core/filing.php (vergeml_filing_audience_of reads the site's language too: dames, heren, kinderen, kind, baby, meisjes, jongens; pick.php row; judged on HEMA round 1 — restore vgml-shop-tree-hema.json first, 0 credits)",
    "core/filing.php (a leaf whose plan claims its parent's members' word first — Interviews: people; TV & Video: camera lens — is the planner's noise: judged dry on both sheets before any rule)",
    "core/filing.php (the class floor: a class half's vector-only match corroborates, never places alone; probe first, judged dry on all three sheets)",
    "core/filing.php (vergeml_filing_ask_split leaves a view home)",
    "tests/tree/filing-baseline.txt, tests/tree/filing-baseline-shop.txt, tests/tree/filing-baseline-shop-b.txt (re-taken only with the reason, on a marked sheet re-read by path)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-17-s15-library-words-and-views.md (this: the probe, the two word rules and their sheets, the view rule and HEMA's honest 30 %, the apply's dead round trips, S10.8's premise)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.8 and its S15 paragraph; S10.5b's; the order of the steps by library)",
    "core/ai.php vergeml_ai_context (the one place a product is read today) and tests/integrations/live.php 'woo' (the fixture's shape: a product, a category, 1778 and 1775)",
    "memory: hetzner-box-fixtures (Woo active on the tech site, ms2 has none), tests-never-touch-live-state, model-spend-discipline, probe-the-premise-before-the-build"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "ms2 shows the C.5 tree (site language nl_NL); HEMA's tree is /var/www/ms2/wp-content/vgml-shop-tree-hema.json — swap with tools/box-tree-snapshot.php (snapshot the one up first), never while Nathan is on ms2",
    "S10.8 touches products: the live test makes and removes its own product on the tech site; Woo on ms2 and products from the seed leaves only on Nathan's word",
    "The rail's flipped order is a mock before a build",
    "A band is re-taken only on a marked sheet re-read by path, and the re-take carries the reason",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work: restore a mutation by the same string edit that made it"
  ],
  "gates": [
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background → green (escaping 9/10 known)",
    "tests/integrations/live.php woo on the tech site green, red with the product path removed",
    "folders.spec and modes.spec on the tech site green; on ms2 once Nathan says it is free",
    "the tech and shop-b bands 4/4 or re-taken with the reason; the C.5 band re-read and re-taken when its tree is back"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-17-s15-library-words-and-views.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S16 of
every-picture-a-home; the card is already in .harness/active.json —
read it before anything else. the audience words first (HEMA restored from its snapshot, round 1 dry), the two profile findings probed; then S10.8 task by task as the
card writes them (the map pure and tested before any row is read); the
rail mocked before built; then the class floor probe. Test first, one mutation per story, both sites measured,
say every cost before it is spent, never touch ms2 while Nathan is on it.
Talk plainly. End with a handoff carrying the S17 card.
```
