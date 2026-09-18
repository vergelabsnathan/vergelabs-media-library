# S19 — the real shop, walked as its owner

**Date:** 2026-09-18. **Model:** Opus 5. **Card:** S19 of every-picture-a-home
(`.harness/active.json` as S18 left it). **From:** `docs/handoffs/2026-09-18-s18-agree-or-ask.md`.
Plugin `549eff7` → `3b6beb6`, ten commits, one story per commit, test first,
red seen before every fix. Service untouched.

## The score lines

Rules-only, after every task, unchanged all session (no rule moved):

- **shop: SCORE right-and-placed 347 of 581 (60 %) · right of placed 72 %**
- **tech: SCORE right-and-placed 153 of 200 (77 %) · right of placed 76 %**

## The real shop (Nathan's go: "both samples")

`http://shop.ms2.46.225.66.194.nip.io`, blog 3 of the ms2 network, built by
`tools/box-shop-real-build.sh` + `tools/box-shop-real-import.php` (`26d7703`):
the C.5 tree frozen first (`/var/www/ms2/wp-content/vgml-shop-tree-before-real.json`,
10 MB, nothing on that site touched since), WooCommerce 11.1.0 linked from the
tech site and active on the shop only, Woo's own `sample_products.csv` (25
products, 22 photos) and its fashion sample (9, 10) through Woo's own
importer — **27 products, 9 categories, 32 real product photos** (+ Woo's
placeholder = 33), every photo a product's featured or gallery image, the
licence sealed on ms2's seat (24,180 credits at the start). Eleven photos have
no `post_parent` (Woo fetched them before the product row existed); our
product fact reads featured and gallery first, so all 32 carry their product.
**Trap, in memory:** `node tools/box-eval.mjs … --site shop` is the ms2 *main*
site (the C.5 library), not the shop — one credit went to describing a
Commons sandal before I noticed; the shop needs `wp … --url=http://shop.ms2…`.

## The walk (27 screens, `docs/superpowers/mocks/shots/2026-09-18-real-shop-*.png`)

Dashboard → library → Folders (Tree first: "This site sells") → the Tree
card's *Describe the pictures* (a link to the AI screen) → **Describe 33 in
55 s, 0 failed** → *Propose folders · 10 credits* → the planner asked *kind or
garment type?* → **the chip threw** (fix 1) → four turns (garment type; the
shop's categories typed by hand; keep Jackets empty; leave the placeholder)
→ *This is my tree* (9 folders, "Confirming 33 pictures against 9 folders",
1 s) → Fill: estimate 29 placed · 23 sure · 6 likely · 4 not · *estimate* →
**Fill 33 pictures: 32 by product · 0 by evidence · 1 question · 1 to sort**
(the placeholder; the model was asked about that one picture only) → *Show
me* → Alt text (33 have one, the describe wrote them) → Rename (*Not
available yet*, gated as it should be) → the library: Clothing 29 · Decor 1 ·
Music 2 · Unfiled 1, every row naming its folder. Spent: 36 describes, ~10
credits of turns, the model's word on one picture; 24,180 → 24,146 on the
sidebar before the turns.

## Built, story by story

1. **The planner's own chips were dead (`a145c7a`).** `messageEl( turn, last )`
   in `js/vergeml-talk.js` named its parameter `turn`, shadowing the `turn()`
   function; a chip's click called the object: *turn is not a function*. Since
   the component was cut out on 2026-09-06 (`871e813`); no suite loaded the
   file. `tests/tree/talk-chips.{html,mjs}` (local, from disk, the mirror of
   `tree-view.mjs`): **6/6**, 1/6 before; registered in `verify.mjs` as
   `talk-chips`.
2. **A tree from the conversation counted before it showed numbers
   (`2a707b2`, `bb67df8`, `900a592`).** Measured on the shop: the reply ends
   at 11.5 s, the draft paints at 17.2 s with **0 on every row and no pill**,
   the counts land at 23.4 s. `state.turnPending` in `js/vergeml-folders.js`
   mirrors `pastePending`: set in `onFinish` when a tree came, cleared when
   `persist()` answers or fails; the paste's *Counting 33 pictures against 9
   folders · 0 s* row, no counts on the new rows, the confirm held, no quiet
   pill beside the row. `folders.spec` row "a tree from the conversation
   counts before it shows numbers" (the token and the stream answered by the
   test, the real turn route held three seconds): red at the row's visibility
   before, green after. Live: `…-16-tree-counting.png`.
3. **The "on products" pill counted pairs (`11ff36c`).** *36 on products*
   beside *33 pictures*: featured + gallery entries summed, and Woo's importer
   puts the featured image in the gallery too. Now one product-side query
   (the featured ids and the gallery lists) and the id set made in PHP — an
   attachment-side `EXISTS` would probe every picture of a 20k library
   against every product. `tests/integrations/live.php woo` +1 row (0 → 3 red,
   0 → 2 green, 10/10); `guide` 42/42 at 13 queries; the shop reads **32 on
   products**.
4. **`docs/ai-service.md` gained the `/v1/file` paragraph (`c04601f`).**
5. **Two gate rows re-pointed (`ff74324`), both red before S19 began:**
   `sticky` J6b asserted the why card's lines from before S18's copy commit
   (`d661cf6` landed after the gate line in the S18 handoff); `surface` and
   `escaping` docs re-synced to moved line numbers.

## Found, not done

- **`tools/box-fill-walk.php` leaves `_vergeml_filing_profile_prev` on the
  real folders.** Its confirm on the fixture tree re-planned 15 real tech
  folders at 19:34 (+01:00, S18); a plan replacing a plan keeps the old as
  *prev* (`core/filing.php:416`), the walk's snapshot (`fw_terms`) does not
  know that key, and its put-back left the 15. `folders.spec` "the words a
  folder takes" then found 15 earlier profiles where it expects none. I
  deleted the 15 rows stamped at that minute (they expire after a day anyway)
  and the row went green; the walk's snapshot should carry the key. Out of
  the card's scope.
- **Captions cut mid-sentence: 2.4 % of the week's describes (40 of 1,665;
  2 of 33 on the shop: "WordPress pennant with", "Cover art for an album
  titled").** Braintrust shows the service returned them cut — caption, alt
  and details all stop at the same word, where a quoted word ("WordPress",
  "the album") would begin; a second call gave whole strings with `\"WOO\"`
  escaped. The structured-output format closes a string at an unescaped `"`
  the model emits. Service side: proposal, `normalise()` treats a caption or
  alt without terminal punctuation as a failed check (escalation, refund),
  as the other checks do. Nathan chose to hold it for the card.
- The AI screen's header line stays *0 described* after a run until reload;
  *Filling 0 of 33 · 15 s* stands still on a one-slice run; *Show me* on a
  one-picture card changes nothing visible (it makes the thumbnail a link);
  *round 1: 32 placed · round 2: 32* reads as if round 2 placed 32 more.
  Copy and shapes: Nathan's.

## For Nathan — the one finding that matters

**A site that sells gets no way to make its product categories the tree.**
The rail says "the products place their pictures first", and they do — but
only into folders that already carry the categories' names, and the Tree
step offers Propose (from the descriptions: Headwear / Hoodies / Other, the
categories unknown to the planner), a paste, or hand-made folders. I typed
the nine categories into the composer; a shop owner would not know to. With
the tree mirroring the categories the fill placed 32 of 33 by product, sure,
no model. Proposal, shape and words yours: a chip *Use my 9 product
categories* on the Tree step of a site that sells (the tree pasted from
`product_cat` by path), and the categories in the planner's context so a
proposal starts from them.

## Gates

- `filing` 105/105 + 34/34 · `sticky` 61/61 · `guide` 42/42 · `filing-trail`
  125/125 · `surface` 27/27 · `escaping` 9/10 (the known ratio row) · `copy`
  58/58 · `tree-view` 65/65 · `talk-chips` 6/6 · `seed-shop` 8/8 · `roles`
  19/19 · `ai-background` 37/37 · `globals` 7/7.
- `folders.spec` + `modes.spec` on the tech site as `vgmls19`: **28 passed,
  3 skipped (the same three), 0 failed** after the prev-profile clean-up;
  `vgmls19` deleted on both networks.
- Both SCORE lines above, after every task; the model lines not re-taken
  (no tier changed); bands not re-taken (no rule changed).
- Nothing moved on the C.5 site or the tech library; the shop subsite stands
  as the walk left it (9 folders, 32 filed, 1 open question) for S20.

## Next — S20

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S20: the shop's way in, then the road's end. The real shop stands (shop.ms2, 27 products, 32 photos, tree = the categories, 32 by product) and S19's walk found the one thing that matters: a site that sells has no way to make its product categories the tree — the owner types them or gets a proposal that never heard of them. Nathan's shape and words first (a chip on the Tree step; the categories in the planner's context), then the build; then the cut-caption check on the service (2.4 % of the week's captions end mid-sentence; normalise() fails a caption or alt without terminal punctuation, as the other checks do; pnpm test; vps.yml for the box copy); then the fill-walk's snapshot carries _vergeml_filing_profile_prev; then a user's pass over every Folders screen (Nathan's), Plugin Check and the archive.",
  "model": "opus",
  "plan": "a plan file for the shop's way in once Nathan's shape is in (the six fields per task, plans/folders-one-tree.md Phase 0 as the reference shape); the cut-caption check and the fill-walk key as single tasks under this card",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.8, the order of the steps by library)",
  "scope": [
    "js/vergeml-folders.js, core/guide.php, core/folder-talk.php, tests/ui/folders.spec.mjs, tests/tree/guide.php (the shop's way in: the chip, the categories as a paste by path, the planner's context)",
    "service/lib/anthropic.ts normalise(), service/lib/*.test.ts (the cut-caption check; never the prompt)",
    "tools/box-fill-walk.php (the snapshot and put-back of VERGEML_FILING_META_PREV)",
    "docs/superpowers/mocks/** (the chip's mock before the build; the shop's screens)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-18-s19-the-real-shop.md (this: the shop, the walk, the finding, the cut captions, the fill-walk's leftover)",
    "docs/handoffs/2026-09-18-s18-agree-or-ask.md (the model beside the rules, the tiers as built)",
    "core/filing.php vergeml_filing_product_folder and vergeml_filing_product_folders (the map a category path takes to a folder), core/guide.php vergeml_folders_facts (sells, on_products)",
    "memory: hetzner-box-fixtures (the shop subsite and the --site shop trap), tests-never-touch-live-state, model-spend-discipline, hooks-block-curl-and-key-paths, do-the-cli-work, ui-less-text-pills"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first — the shop subsite is on ms2",
    "The chip's shape and every string are Nathan's; a mock before the build; no placeholder copy",
    "The price of the model call is Nathan's; until priced it is metered, never debited",
    "The model never overrules a product placement, a hand placement, or files into a view or a locked folder",
    "A band is re-taken only rules-only and with the reason; the model's lines are never a band",
    "Say every cost before it is spent: a describe is a credit a picture; the shop's 33 are described and cached; the suites use the stand-in",
    "A service push is not on the box until gh workflow run vps.yml --ref main has run and the box's copy answers the route",
    "Test first, one mutation per story; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work; never chain a checkout into a command",
    "box-eval --site shop is the C.5 main site: the shop subsite needs --url=http://shop.ms2.46.225.66.194.nip.io through a wrapper"
  ],
  "gates": [
    "node tools/box-eval.mjs tools/box-truth-score.php --site shop and the tech line with --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json (MSYS_NO_PATHCONV=1): both rules-only lines in every check-in (shop 347 of 581 (60 %) · 72 %, tech 153 of 200 (77 %) · 76 %) — neither falls",
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view talk-chips seed-shop ai-background filing-trail → green (escaping 9/10 known); service pnpm test green after the caption check",
    "folders.spec and modes.spec on the tech site green after any screen change (28 passed, 3 skipped today)",
    "the shop walked again after the chip: the tree from the categories in one press, 32 by product, every screen shot"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-18-s19-the-real-shop.md, then
docs/handoffs/2026-09-18-s18-agree-or-ask.md. State which model you are and
follow that profile in ~/.claude/harness/model-profiles.md. This session is
S20 of every-picture-a-home; the card is already in .harness/active.json —
read it before anything else. Both rules-only SCORE lines first (shop 347 of
581, tech 153 of 200); then the shop's way in: a mock of the Tree step's
chip for a site that sells, Nathan's yes and words, then the build, test
first, one mutation per story; then the cut-caption check on the service;
then the fill-walk's snapshot key; then the rest of the road. Every cost said
before it is spent, never on ms2 while Nathan is on it. Talk plainly. End
with a handoff carrying the S21 card.
```
