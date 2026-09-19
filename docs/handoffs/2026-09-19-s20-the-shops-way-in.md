# S20 — the shop's way in, the cut captions, the walk's leftover

**Date:** 2026-09-19. **Model:** Opus 5. **Card:** S20 of every-picture-a-home
(`.harness/active.json` as S19 left it). **From:**
`docs/handoffs/2026-09-18-s19-the-real-shop.md`. **Plan:** `plans/shop-way-in.md`.
Plugin `a76a761` → `bb8f6c1`, four commits, test first, every mutation seen red.
Service `eaf1ecc` → `edd5344`, pushed, live on Vercel and shipped to the box.

## The score lines

Rules-only, after every task, unchanged all session (no rule moved):

- **shop: SCORE right-and-placed 347 of 581 (60 %) · right of placed 72 %**
- **tech: SCORE right-and-placed 153 of 200 (77 %) · right of placed 76 %**

## Nathan's calls

Three shapes and four labels were drawn before a line of the build
(`docs/superpowers/mocks/2026-09-18-shop-way-in.html`,
`2026-09-19-shop-way-in-options.html`; shots beside them):

- **Shape B** — the press is the Tree card's primary while the tree is empty,
  where *Propose folders* sits on a described library, and gone once a tree
  stands. A chip among the tweak chips (A) and both (C) were dropped.
- **Label L1** — `Use my 9 product categories`, the count live from the site.

## Built, story by story

1. **The categories, as paths (`e4d0bba`).** `vergeml_folders_product_paths()`
   in `core/guide.php`: the product categories root to leaf as names, a parent
   before its child, one terms query, capped at the paste's own five hundred so
   the button and the reader agree on what one press makes. Woo's default
   category is left out while nothing is in it and nothing is under it.
   `guide/product-categories` reads them **at the press**, so a category made
   since the page opened is in them; `product_cats` rides on the facts only on
   a site that sells, so a site with WooCommerce and no product picture pays no
   query. The same list rides in `vergeml_guide_summary()` — the service puts
   the summary into the planner's prompt whole (`lib/guide.ts contextBlock`),
   so a proposal starts from the shop's own names with **no service change**,
   and the summary key counts the categories or a new one never lands.
   `guide` **51/51** (H1–H9, the fixture's categories made and removed before a
   single check runs); the default-category rule mutated away → H3 red, 50/51.
2. **The button, and the press (`0aee999`).** `js/vergeml-folders.js`: the move
   row's primary on a selling site with no tree; the press turns the paths into
   `Clothing > Hoodies` lines and hands them to `js/vergeml-structure.js`, so
   the draft, the counting row and the held confirm are what a paste already
   does. A refusal (deeper than five levels, more than five hundred) opens the
   paste panel with the lines in it, read in the paste's own words.
   `tests/tree/folders-shop.{html,mjs}` (local, from disk, the mirror of
   `talk-chips`), registered in `verify.mjs`: **11/11**; two mutations red — the
   "no tree yet" gate dropped → B9, the press without the reader → no draft.
3. **One primary in the row (`db34bc3`).** The walk caught what the mock could
   not: with the library described, *Propose folders* draws itself as the
   primary too whenever there is no tree, so the card offered two blue buttons
   side by side. Propose is the primary only when nothing else in the row is.
   `folders-shop` B2b, red before the fix.
4. **The cut captions (service `edd5344`).** `checkDescription` A11: a caption
   that does not end in terminal punctuation is a failed check — refunded and
   described again, not shipped. **Counted before it was built:** over the
   box's thousand rows, 98 % of captions end in terminal punctuation and every
   one of the 23 that do not is a cut ("…spending about 72 minutes"); only 73 %
   of good alts end in punctuation at all, so the same rule on the alt would
   refund a quarter of every run — the check is the caption's alone, and the
   cut takes the caption with it anyway. `pnpm test` **505 passed, 14 skipped**;
   `tsc` clean; the end rule widened to match anything → A11 red. The prompt is
   untouched, so nothing sweeps. Pushed, production serves `edd5344`
   (`/api/build`), and the box's own copy carries the A11 line in four compiled
   chunks built 06:28 UTC after `gh workflow run vps.yml`.
5. **The fill-walk's leftover (`bb8f6c1`).** `fw_terms()` carries
   `_vergeml_filing_profile_prev` and the put-back restores or deletes it, with
   G5b saying so in its own row. Mutation: the put-back skipped → G5 and G5b
   red, **15 left**, 21/23 — the exact fifteen S19 found. Green at **23/23**,
   36 s on last night's cached answers, no model spend.

## The walk (the real shop, from zero)

`tools/box-shop-walk.mjs` (kept), nine shots
`docs/superpowers/mocks/shots/2026-09-19-shop-way-in-*.png`. The shop's tree was
frozen first (`/var/www/ms2/wp-content/vgml-realshop-tree-s19.json`, literal
SQL) and cleared; a session admin of this session's own, deleted after.

Folders opens on Tree · **33 pictures · 32 on products · 0 folders** → one press
on *Use my 9 product categories* → the nine, every row *new*, the counting row,
the confirm held → *This is my tree* → Fill → **32 by product · 0 by evidence ·
1 question · 1 to sort**, 9 folders. S19 got that from four typed turns; this is
one press. Spent: nothing — the 33 were described in S19 and cached, the confirm
profiled 9 folders inside the free hundred.

## Found, not done

- **The Tree step's estimate ignores the products.** On the shop it read *23
  would be placed · 10 would stay unfiled*; the fill placed **32** by product.
  The dry count is rules-only and does not know a product's category names a
  folder, so on every shop the estimate understates. The fill is right; the
  number before it is not.
- S19's four small things still stand (the AI screen's header after a run,
  *Filling 0 of 33* on a one-slice run, *Show me* on a one-picture card,
  *round 1: 32 placed · round 2: 32*). Copy and shapes: Nathan's.
- `A1` in `tests/tree/guide.php` re-pointed 13 → 14 queries, measured on the
  box: the fourteenth is the confirm's profile facts when the live session
  holds a draft (C.5). Red before this session's first change, not caused by it.

## Gates

- `filing` 105/105 + 34/34 · `sticky` 61/61 · `guide` **51/51** · `filing-trail`
  125/125 · `surface` 27/27 · `roles` (live endpoints 79 → **80**) ·
  `escaping` 9/10 (the known ratio row, 243 text against 65 HTML) · `copy` 58/58
  · `tree-view` 65/65 · `talk-chips` 6/6 · **`folders-shop` 11/11** ·
  `seed-shop` 8/8 · `ai-background` 37/37 · `fill-walk` 23/23. Service
  `pnpm test` 505 passed, 14 skipped; `tsc` clean.
- `folders.spec` + `modes.spec` on the tech site as `vgmls20`: **see the line
  below** (run at the end of the session; `vgmls20` deleted on both networks).
- Both SCORE lines above, after every task. Bands not re-taken (no rule moved).
- `docs/security-surface.md` and `docs/security-escaping.md` regenerated.

## Next — S21

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S21: the road's end. The shop's way in is built and walked (one press → 9 folders → 32 by product) and the service refuses a cut caption. What is left: the Tree step's estimate on a site that sells counts rules only (23 would be placed where the fill placed 32 by product) — Nathan's call whether the preview learns the product placements; then a user's pass over every Folders screen (Nathan's own, with the four small things S19 listed); then Plugin Check and the archive for wordpress.org.",
  "model": "opus",
  "plan": "plans/shop-way-in.md is done (three tasks); a plan file for the preview's product count only if Nathan asks for it, the six fields per task",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.8, the order of the steps by library)",
  "scope": [
    "core/filing.php vergeml_filing_count / the dry run, core/guide.php (the preview's product count, if Nathan says yes)",
    "js/vergeml-folders.js, tests/tree/folders-shop.mjs, tests/ui/folders.spec.mjs (any screen change)",
    "docs/superpowers/mocks/** (a mock before any visible change)",
    "readme.txt, docs/**, the archive (Plugin Check)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-19-s20-the-shops-way-in.md (this: the way in, the walk, the estimate's gap, the caption check)",
    "plans/shop-way-in.md (the decisions and the three tasks as built)",
    "core/guide.php vergeml_folders_product_paths and vergeml_guide_summary (the paths and the planner's context), js/vergeml-folders.js onUseCategories",
    "memory: shop-starts-from-its-categories, hetzner-box-fixtures (--site realshop and the walk's reset), do-the-cli-work, ui-less-text-pills, model-spend-discipline"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Every user-facing string is Nathan's, verbatim; a mock before any visible change",
    "The price of the model call is Nathan's; until priced it is metered, never debited",
    "The model never overrules a product placement, a hand placement, or files into a view or a locked folder",
    "A band is re-taken only rules-only and with the reason; the model's lines are never a band",
    "Say every cost before it is spent: a describe is a credit a picture; the shop's 33 are described and cached",
    "A service push is not on the box until gh workflow run vps.yml --ref main has run and the box's copy answers the route",
    "Test first, one mutation per story; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work; never chain a checkout into a command",
    "box-eval --site shop is the C.5 main site; --site realshop is the WooCommerce shop (S20 closed that trap)"
  ],
  "gates": [
    "node tools/box-eval.mjs tools/box-truth-score.php --site shop and the tech line with --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json (MSYS_NO_PATHCONV=1): both rules-only lines in every check-in (shop 347 of 581 (60 %) · 72 %, tech 153 of 200 (77 %) · 76 %) — neither falls",
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view talk-chips folders-shop seed-shop ai-background filing-trail → green (escaping 9/10 known); service pnpm test green",
    "folders.spec and modes.spec on the tech site green after any screen change",
    "the shop re-walked after any change to the way in: tools/box-shop-walk.mjs, one press, 32 by product, every screen shot"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-19-s20-the-shops-way-in.md, then
plans/shop-way-in.md. State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S21 of
every-picture-a-home; the card is already in .harness/active.json — read it
before anything else. Both rules-only SCORE lines first (shop 347 of 581,
tech 153 of 200); then the Tree step's estimate on a site that sells (it
counts rules only where the fill places by product — my call first, with the
numbers); then the user's pass over the Folders screens and the four small
things S19 listed; then Plugin Check and the archive. Test first, one
mutation per story, every cost said before it is spent. Talk plainly. End
with a handoff carrying the S22 card.
```
