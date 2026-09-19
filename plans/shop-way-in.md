# The shop's way in — a site that sells starts from its own categories

Card: `.harness/active.json` (S20 of every-picture-a-home). Handoff:
`docs/handoffs/2026-09-18-s19-the-real-shop.md`. Spec:
`docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md` (S10.8).
Mocks: `docs/superpowers/mocks/2026-09-18-shop-way-in.html` and
`2026-09-19-shop-way-in-options.html`; shots in `mocks/shots/shop-way-in-*`.
Model: Opus 5 — every task carries Files, Behaviour, Proof, Mirror, Copy, Do not.

## Problem

S19 walked the real shop (`shop.ms2`, 27 products, 9 categories, 33 pictures)
as its owner. The rail says *the products place their pictures first*, and
they do — but only into folders that already carry the categories' names. The
Tree step offers Propose (from the descriptions: Headwear / Hoodies / Other,
the categories unknown to the planner), a paste, or folders by hand. The nine
categories had to be typed into the composer for the fill to place 32 of 33 by
product, sure, with no model asked. A shop owner would not know to.

## Decisions taken (Nathan, 2026-09-19)

- **Shape B**: the press is the card's primary in the move row while the tree
  is empty, and nothing once a tree stands — where *Propose folders* sits on a
  described library. Shapes A (a chip among the tweak chips) and C (both) were
  drawn and dropped.
- **The label is L1, `Use my 9 product categories`**, the count live from the
  site. L2–L4 were drawn.
- **The press is a paste.** The categories go through `js/vergeml-structure.js`
  as `Clothing > Hoodies` lines, so one line makes its parent, a category that
  already is a folder is reused by name, and the paste's own refusals (deeper
  than five levels, more than five hundred) are said in the paste's own words.
  Nothing after the press is new: the draft, the counting row and the held
  confirm are what a paste already does.
- **The planner's context is the summary.** The service dumps the plugin's
  library summary into the prompt whole (`service/lib/guide.ts contextBlock`),
  so the categories reach the planner by riding in `vergeml_guide_summary()`.
  No service prompt change, no deploy — the card forbids both.

## Out of scope

- The service's planner prompt and `/folders` body (the card: never the prompt).
- Filing by category without a folder — `vergeml_filing_product_folder` already
  maps a category path to a folder and is untouched.
- WooCommerce product tags, attributes, brands; only `product_cat`.
- The four small things S19 found on the AI and Fill screens (Nathan's copy).

## Tasks

1. **The categories the site has, as paths.**
   - Files: `core/guide.php` (`vergeml_folders_product_paths()`, the
     `product_cats` count in `vergeml_folders_facts()`, the
     `guide/product-categories` route), `tests/tree/guide.php`.
   - Behaviour: the paths are the product categories root to leaf as names,
     depth first in the shop's own order, each a `string[]`; Woo's default
     category is left out when nothing is in it and kept when products are;
     the count rides on the facts only when the site sells (a site with Woo
     and no product picture pays no query, so the boot stays at thirteen);
     the route is `manage_categories` and returns `{ paths, total }`.
   - Proof: `node tools/verify.mjs guide` — section G: the fixture's two
     categories come back as `[['Probe'], ['Probe','Deep']]`, the empty
     default category is absent, the facts carry the count on a selling site
     and not otherwise, A1 still ≤ 13 queries.
   - Mirror: `vergeml_filing_product_folders()` in `core/filing.php` builds a
     category's path with `get_ancestors` + `vergeml_term_name`; copy that.
   - Copy: none — no string reaches a screen from this task.
   - Do not: query `product_cat` on a site without products; read the terms
     through `vergeml_folders_nodes` (that is the media taxonomy); leave the
     fixture's terms behind.

2. **The button, and the press.**
   - Files: `js/vergeml-folders.js`, `tests/tree/folders-shop.{html,mjs}`,
     `tools/verify.mjs`.
   - Behaviour: on a site that sells, with no folder in the draft and none on
     the site, the move row's primary is the categories button, before *Propose
     folders*; it is gone as soon as a tree stands; the press reads the route,
     turns the paths into `A > B` lines, hands them to `STRUCT.parse` and
     `pasteDraft`, exactly as a paste; a refusal opens the paste panel with the
     lines in it so it is read in the paste's own words; the button says it is
     working while the route answers, and is off while a paste or a turn is
     still settling.
   - Proof: `node tools/verify.mjs folders-shop` (local, from disk, the mirror
     of `talk-chips`): the button shows only when it should, one press makes
     nine folders in the draft with Clothing as the parent of six, the turn
     route is asked for the counts, the paste panel stays shut.
   - Mirror: `tests/tree/talk-chips.{html,mjs}` for the harness;
     `onPropose`/`pasteDraft` in `js/vergeml-folders.js` for the press.
   - Copy: the button `Use my 9 product categories`
     (`/* translators: %s: product categories */ 'Use my %s product categories'`).
     Nothing else.
   - Do not: write a second reader for the paths; invent a progress row; leave
     the button standing once the tree has folders.

3. **The planner starts from them.**
   - Files: `core/guide.php` (`vergeml_guide_summary()`,
     `vergeml_guide_summary_fresh()`), `tests/tree/guide.php`.
   - Behaviour: the summary carries `product_categories` (the same paths, the
     same cap) on a site that sells and the key that decides a refresh carries
     their count, so a category added after the session opened re-takes the
     summary; nothing is sent on a site without products.
   - Proof: `node tools/verify.mjs guide` — section G: the summary holds the
     fixture's paths, the key changes when a category is added, and neither is
     there when the site does not sell.
   - Mirror: `evidence` / `classes` in `vergeml_guide_summary()`.
   - Copy: none.
   - Do not: change the service; send the categories with the turn body as a
     second field; send them when the site has no product picture.

## Gates

- Both rules-only SCORE lines after every task (`shop 347 of 581 (60 %) · 72 %`,
  `tech 153 of 200 (77 %) · 76 %`).
- `node tools/verify.mjs filing sticky guide surface roles escaping copy
  tree-view talk-chips folders-shop seed-shop ai-background filing-trail`.
- `folders.spec` + `modes.spec` on the tech site (the button never shows there:
  no product carries a picture).
- The shop walked again: one press, nine folders, 32 by product, every screen shot.
