# Handover — 2026-09-17, S14: the words corroborate, never place alone; the trust rule judged and not built (Opus 5)

Card: `.harness/active.json` (S14, as S13 wrote it). Plan:
`plans/every-picture-a-home.md` Phase D (S14 line written, S15 and S16
lines moved). Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(S10.7 and S10.9 carry S14 paragraphs). Evidence before: S13's handoff.
Lean held: test first, one mutation per story (each seen red), both sites
measured dry, ms2 read only (the deploy lands there; no suite, no walk —
the stop point says ask, and Nathan was not asked because nothing on ms2
needed running).

**Spend:** 0 credits on either library. Every number is a dry read
(`tools/filing-baseline-check.mjs`, `tools/box-folder-quality.php` with
`VGML_DRY=1`) or a suite on its own fixture. Writes outside the repo: the
member-layer caches in term meta on both sites (rebuilt twice under a
changed stamp, then under the old one again — the same layers as before);
`vgmls14`, an administrator on the tech site for the UI specs, deleted at
the end; the sticky and guide suites' own fixtures, restored.

Commits, plugin (`main`, deployed to the box after each; ms2 runs the same
copy): `74e407c` the word rule, both bands, the sheet tool, guide F7;
`de71999` the confirm pressed goes to work, the UI specs; then the plan,
the spec and this handoff.

## The gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy
tree-view seed-shop ai-background` → green (filing 51/51 + 34/34, sticky
44/44, guide 40/40, escaping 9/10 the known ratio row). `folders.spec` +
`modes.spec` on the tech site 24 passed, 3 skipped, 0 failed. Both bands
re-taken with the reason; the shop-b band not taken (S10.5b waits on the
yes).

## The first story, judged: a member counts only when sure or the person's — no

Built as the card said (`vergeml_filing_members_trusted`, the reader
handing each member its trail row, the trail's shape in the stamp; pick.php
27f–27h and sticky H5, both mutations red), then judged dry on both sheets
and taken out again, with the reason on `vergeml_filing_members_layer`'s
docblock:

- **The trail says the premise was wrong for the tech library.** Of 975
  members in folders of three or more: 405 placed sure, 131 with no row
  (before the record, or the site's own), 61 by hand, 327 by an answer
  (all in *To sort*, locked, owning nothing) — and **47 likely, 4
  siblings**. The rule can take 51 members out. The learned misses S13 saw
  (e-waste in Batteries, a keyboard-layout diagram in Satellites) came
  from *sure* placements of earlier fills: batch 133's own sure band was
  14 right / 13 wrong.
- **The S13 sheet under it:** sure unchanged (25 right, 5 wrong kept);
  likely 3 of 17 wrong dropped. Tech tally 686/637 → 685/641.
- **The shop under it:** the four watches Nathan marked *right* in Watches
  (181/184/187/188) went *sure* into Smart watches; the three laptops in
  Computers went *sure* into Keyboards. Watches and Laptops held their
  pictures as likely, right placements; the rule took those members out,
  the folders lost their words, and the neighbour with untouched members
  took the lot. Against S13's read (510/491): fits 491, sure 470.
- **Verdict:** a placement's confidence does not say whether the word it
  teaches is true. Every member counts, as S13 built it.

## The cause of the likely band, found and fixed: S10.9 placed alone (`74e407c`)

Read off the 21 wrong and broad likelies still placed (a probe printing
each pick's class hit, word hit and cosine): **10 had no describer hit on
the folder at all** and stood on one filename or title word by head noun —
"farm" of a 3D-printer farm was Wind's *wind farm*, "switch" of a smart
plug was Server racks' *network switches*, "technology" of a conference
award was Phones' *wearable technology*, "electronic" of an e-waste model
was Batteries' *electronics*, "computer" in a protest photo's filename was
Hardware's — a likely at 0.57–0.70 with the runner-up at 0.11–0.19. S10.9's
"read only where the describer's phrases left room" had been written as
`class < 0.85`, which includes 0.

**The rule:** the words are read only where the describer's phrases
already hit the folder (`$class > 0.0`). A first cut let the folder's own
name count alone (the photographer's `smith-wedding-012.jpg` in *Smith
wedding*); on the tech library a title saying "phone" then put an office
desk in Phones, *sure* 0.74 (106756/106757), so that path went too: a
shoot's folder is a signal about the pack, and S10.10 hands those to the
planner. `pick.php` 29e–29g; the gate removed → 29e red (Wind, sure 0.76).

**Tech, dry:** fits 686 → 655, sure 637 → 599, likely 78 → 71, siblings
29 → 15, nothing 285 → 330 (floor 147 → 232, margin 104 → 64). 116
placements move: 40 sure → floor, 14 likely → floor, 21 margin → floor, 15
margin → likely, 6 margin → sure. The 40 sures read by hand: ~10 right
(three "Data_Center_UNC" building shots, three rocket launches, a barn
with wind turbines), ~26 wrong (thirteen "Person-Playing-Virtual-Reality"
files in People by "person", four in Satellites by "diagram", a bicycle
rack in Server racks, a walnut tree in Wind, three portraits in Laptops),
four arguable (cats on laptops).

**The three sheets, re-read by path:**

| sheet | sure | likely |
|---|---|---|
| S13 (25/30, 6/30) | right kept 24 · right lost 1 (the shuttle launch) · wrong kept 5 | right kept 5 · **wrong dropped 10** · right lost 1 (106757) · wrong kept 7 · broad kept 7 |
| C.1 (22/30, 7/30) | right kept 21 · wrong dropped 2 · right lost 1 · wrong kept 2 · broad kept 3 | right kept 5 · wrong dropped 2 · wrong kept 6 · broad kept 10 · moved 7 |
| batch 133 | right kept 14 · wrong kept 9 · moved 7 | right kept 3 · wrong dropped 2 · right lost 2 · wrong kept 3 · moved 19 |

The 7 wrong likelies left are the pre-S10.9 kind: the picture's *class
half* hitting a folder's learned or planned word by containment —
"technical diagram" → *diagram* on Satellites, "interior" → *desktop
computer interior*, "gaming hardware" → *hardware*, "electronic test
equipment" → *electronics*, "computer equipment" → *computer* — 0.63–0.69
with the runner-up far below. The next cause; a story of its own.

**The shop, read only:** S13 read it at fits 510 / sure 491 and never
re-took (its band file still said 493/467 from `2037294`). Under the word
rule: fits 510, sure 488, likely 31, siblings 9, nothing 107 (floor 44,
margin 61). Its C.5 marks re-read by path for the first time: sure 23
right kept · 1 wrong dropped (432, Books) · 2 wrong kept · 4 moved
(353/345/348 digital cameras to Cameras — S12's Cameras finding, not
S14's); likely 8 right kept · 1 wrong (521) and 1 broad dropped · 1 right
lost (248, a make-up product placed by its filename) · 7 wrong kept · 8
broad kept · 5 moved into the child (Nail polish, Suitcases, Chocolate).

**Both bands re-taken** with those reasons in the file (`tests/tree/
filing-baseline.txt` 655/599, `filing-baseline-shop.txt` 510/488).

## The sheet tool

`tools/box-folder-quality.php` re-reads the S13 sheet and the shop's sheet
by path (the shop block prints only where its ids are the library's).
`bfq_path` read the stored `&amp;` while the marks say `&`, so every "Bags
& Luggage" row read as *moved* — 8 false moves a sheet on the shop, and
the reason its marks had never been re-read. Now `vergeml_term_name`.

## Guide F7 — a real cron job raced the poll (`74e407c`)

`tests/tree/guide.php` F7 went red twice (39/40) after S13's 40/40. The
fit itself takes 4.1 s cold, 1.6 s warm; through the poll it took 48.7 s
and settled unknown. F6 books a fit job and spawns cron; sent for real,
the box ran that job — 1,000 pictures against 200 folders, up to 240 s —
beside F7's own fit, which starved past its 20 s budget. The suite already
answered the site's `wp-cron.php` in-suite, but only from section G; the
answer now sits before F. 40/40.

## The UI reds — not what S13 read, and one real screen bug (`de71999`)

Run as `vgmls14` on the tech site (made and deleted). The two named reds:

1. **The upload test** failed at the tree card's pills, not at Start over:
   `b88ff00` made the Tree step's dry run speak in the conditional ("938
   would be placed · 62 would stay unfiled") and the assertion still said
   "placed". Fixed to the screen's copy.
2. **The Fill step's "filled again"** had two faults: the regex
   `/^Fill [d,.]+ pictures$/` (a literal `d`) could never match, and the
   fixture plants its questions on a tree nobody confirmed, so the done
   step's one primary is *This is my tree* (`renderFill`, line 857) and no
   fill button is offered. The assertion now says that.

Under the first, **a restore race**: `restore()` only left the screen
when the URL was not under `wp-admin` — every screen is — so the Folders
app stayed open and polled `/guide/progress` while restore reset the
session; a poll that revives a pending fit reads the session, counts for
seconds, then saves what it read, the old 52 turns back over the reset,
and restore's own 52 turns were refused at the cap ("Every turn of this
conversation is used"). Restore leaves the screen first, always; a
refusal names its code; a session found past the cap is put back up to
the cap and said.

And **a third red, a real one, in the full run:** "the progress row: the
confirm counts its batches" — `onConfirm` dimmed `dom.confirm`, the last
"This is my tree" rendered, and with the tree unconfirmed the Fill card
renders its own after the Tree card's (`renderFill` 857 and 884), so the
press on the Tree step dimmed a hidden button and left its own standing.
It passed in S13 because the box's tree was confirmed then. The button
pressed (`ev.currentTarget`) goes to work now; red before, green after,
red again with the fix reverted.

`folders.spec` + `modes.spec` on the tech site, first full run: 22
passed, 3 skipped (the streamed proposal, grid-mode actions, Move to
folder — each skips without its precondition), 2 failed (the two above,
then fixed); the second full run, on `de71999`: **24 passed, 3 skipped,
0 failed (25.9 min)**. Not run on ms2 (the stop point).

## Found, not done

- **The class-half hits** (7 of the S13 sheet's likelies): a rule on the
  second phrase against a folder's non-first word by containment. Judged
  on both sheets before it moves; the shop's "computer equipment" cases
  will say whether it is the modifier or the class.
- **Satellites' cached plan carries "diagram"**, a kind word
  (`vergeml_filing_clean_seed` drops those at build time; this profile
  predates the rule). A re-plan or Restore clears it; four wrong sures
  stood on it until the word rule took them.
- **The word rule costs the shop a right likely** (248) and the tech
  library ten right sures (the Data_Center building shots, the launches):
  what those need is the filename read as *structure* (S10.10's signals),
  not as a class hit.
- **Profiles when a member's own trail is wrong** — the sure misses
  (e-waste in Batteries) still teach. No rule reads that; the fix is the
  person's: a hand move out re-profiles the folder on the next read.

## For Nathan

1. **S10.5b's yes is still open:** `tools/box-seed-shop-tree-b.txt`,
   HEMA's tree, 292 folders, 34 sent, 0 credits. Say yes (or another
   retailer) and when ms2 is free.
2. The tech and shop bands are re-taken on marks re-read by path, not on
   a fresh sheet: a fresh S14 sheet (`VGML_DRY=1 VGML_SEED=133`) is saved
   in the session scratchpad if you want to mark it; the pre-fills carry
   every earlier mark whose folder is the same.
3. `vgmls14` is deleted; ms2 was not run on.

## Next — S15

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S15: the foreign tree (S10.5b) on Nathan's yes, the class-half hits (the 7 wrong likelies left), then file by the product (S10.8)",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S15)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "tools/box-seed-shop-tree-b.txt (S10.5b: written, HEMA's tree, 292 folders; pasted on ms2 after Unconfirm and the folders removed; tests/tree/filing-baseline-shop-b.txt and its sheet)",
    "core/filing.php (the class-half hit by containment on a non-first, non-leaf word: 'technical diagram' → diagram, 'gaming hardware' → hardware; judged dry on both sheets before it moves; pick.php row, one mutation)",
    "tests/tree/filing-baseline.txt, tests/tree/filing-baseline-shop.txt (re-taken only with the reason, on a marked sheet re-read by path)",
    "core/filing.php, core/folder-talk.php, core/integrations/** (S10.8: a picture attached to a product goes where the product's categories say, sure, 'by the product', before any matching; tests/integrations/live.php)",
    "js/vergeml-folders.js (the rail's Fill step: 'placed by product N · by evidence N · to sort N'; the flipped order for structured libraries, mocked)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-17-s14-words-corroborate.md (this: why the trust rule is out, the word rule and its cost, the 7 class-half hits, the sheet tool's &amp; fix, the UI reds as they really were)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.5b, S10.8; the S10.7/S10.9 S14 paragraphs)",
    "memory: hetzner-box-fixtures (ms2, vgmls9), tests-never-touch-live-state, model-spend-discipline, shared-classes-break-the-matcher"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "S10.5b's catalogue and its confirm (0 credits after S10.1's split — say the number) are Nathan's yes before the paste",
    "A band is re-taken only on a marked sheet re-read by path, and the re-take carries the reason",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire",
    "S10.8 touches products: every write measured on the box's Woo fixture, never on ms2's live catalogue without Nathan's word",
    "Never git checkout a file carrying uncommitted work: restore a mutation by the same string edit that made it"
  ],
  "gates": [
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background → green (escaping 9/10 known)",
    "folders.spec and modes.spec on the tech site green; on ms2 once Nathan says it is free",
    "both baselines 4/4 or re-taken with the reason; the shop-b band and sheet taken"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-17-s14-words-corroborate.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S15 of
every-picture-a-home. Write the card from the handoff to
.harness/active.json before anything else. S10.5b first if Nathan's yes
is in (the paste, the fill, the second band and sheet); the class-half
hits judged dry on both sheets before they move; then S10.8.
Test first, one mutation per story, both sites measured, say every cost
before it is spent, never touch ms2 while Nathan is on it. Talk plainly.
End with a handoff carrying the S16 card.
```
