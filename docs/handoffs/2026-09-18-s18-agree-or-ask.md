# S18 — agree or ask: the text model beside the rules

**Date:** 2026-09-18. **Model:** Opus 5. **Plan:** `plans/agree-or-ask.md`,
six tasks in the Opus shape, all six built, each with its suite and its
mutation seen red. **From:** S17's measurement (`docs/handoffs/2026-09-18-s16-the-truth-score-first.md`,
the last section). Service `eaf1ecc` (pushed, live on Vercel and shipped
to the box's own copy); plugin `7bb5109` → `716bfb3`.

## The score lines

Rules-only, unchanged all session (the bands stay rules-only, none re-taken):

- **shop: SCORE right-and-placed 347 of 581 (60 %) · right of placed 72 %**
- **tech: SCORE right-and-placed 153 of 200 (77 %) · right of placed 76 %**

With the model in (`VGML_MODEL=1`, task 5 — the first two lines ever, ~40
cents in all, cached a week):

- **shop: 354 of 581 (61 %) · right of placed 89 % · agree 357 · doubt 71
  (X or nowhere right 24) · questions 76 (right among the two 66) · with
  the questions answered 444 of 581 (76 %) · sure right 90 %** — both floors
  met (sure ≥ 90, answered ≥ 74). Wrong sures 12 % → 6 %.
- **tech: 139 of 200 (70 %) · right of placed 84 % · agree 78 · doubt 30
  (X or nowhere right 25) · questions 23 (right among the two 22) · with
  the questions answered 169 of 200 (85 %) · sure right 90 %** — answered
  ≥ 84 met; **sure right 90 % misses the 97 % floor.** Wrong sures 24 % →
  10 %.

What the tech miss is: 6 of the 8 wrong sures are one pair, *hardware →
data centres › server racks* — the rules (a members' word) and the model
both file the picture under Server racks where Nathan's mark says the
parent, Hardware. The two agree with each other against the mark; S17's
99 % came from the truth page's picks of that day, not fresh picks. The
other two: a component into Components (one no-folder picture, one
Hardware). The model-alone band (rules nothing, model a folder) is 7
pictures on tech and 1 is right — that band is the hard residue and reads
*likely*, as designed. On the shop it is 39 pictures, 32 right.

On the shop, 47 of the 71 doubts have their truth in a third folder:
when the model says "nothing fits" against the rules' X, X is wrong more
often than right (24 of 71 are X or nowhere). A doubt is a good question.

## Built, task by task

1. **`/v1/file` (service `eaf1ecc`).** `lib/file-prompt.ts` (pure): the
   prompt of `tools/truth-model.mjs` verbatim, the tree deduplicated and
   sorted without *To sort*, the answer parser mapping every asked id to the
   path as the tree spells it or null. `app/api/ai/file/route.ts`: Sonnet 5
   at temperature 0 through OpenRouter, ≤ 40 pictures / ≤ 500 folders or
   400, licence and limits as `/name-group`, metered on `file` at cost 1,
   never debited, no description logged, 502 `filing_unavailable` on a
   failed call. `next.config.mjs` gained the `/v1/file` rewrite (the v1
   suite caught its absence). `file-prompt.test.ts` 11 rows; the tree guard
   removed → 2 red. `pnpm test` 502 passed. **The box's own service copy
   (`127.0.0.1:3100`) is shipped only by `gh workflow run vps.yml --ref
   main`** — dispatched this session after the box answered 404; every
   service push needs it.

2. **The plugin asks once per picture per tree (`7bb5109`).**
   `vergeml_filing_ask_model( $rows, $profiles )` puts `model_folder` on
   each row: a term id, 0 (nothing of the tree fits, or a path the tree
   does not hold), -1 (unasked: placed by hand / answer / product, in a
   locked folder, no description, no licence, the service down). The tree
   goes as paths (`vergeml_filing_model_tree`), *To sort* never among them;
   batches of 40; a week's transient per picture keyed by the tree's paths
   and the description. **Cached as the folder's canon path, not its id**
   (found in task 4: the suite re-makes its folders under the same names
   every run and a cached id read as "nothing fits" — a wrong veto for any
   folder deleted and made again). A failed call marks the service down
   for a minute (`VERGEML_FILING_MODEL_DOWN`) so a thirty-slice fill does
   not wait thirty timeouts. `sticky.php` J0–J4b; the cache read removed →
   J2–J4 red.

3. **The pick, three tiers (`822654f`).** `vergeml_filing_pick` = the
   rules' pick (`vergeml_filing_pick_rules`, the old body untouched) then
   `vergeml_filing_pick_model`: agree → fits, sure, why `agree`; disagree →
   nothing, `margin`, children [rules, model] (the either/or question);
   the model's nothing → nothing, `doubt`, nearest the rules' folder; the
   rules' nothing + the model's folder → that folder, likely, source
   `model`, hit ''; siblings under P + a child of P (or P itself) → that
   folder, sure, `agree`. -1 changes nothing; product and hand placements
   never overruled; a locked, gated, view or unknown folder the model
   names counts as nothing. `pick.php` 42–42i; the tiers removed → 7 red.

4. **The run and the trail (`b7e61f7`).** The run asks per slice before
   the count, a call at a time with a heartbeat between (the screen's
   stall line is 30 s; a slice is three calls). The tally gains `agree`
   and `doubt` (and `why.doubt`); doubt rows go to the residue with the
   rules' folder as nearest; the trail row says `agree` / `doubt`; the
   pill reads `agree` as sure and `ok` by `model` as likely whatever the
   score. The why card says nothing new: an agree row reads as any filing
   (In X · scored · Matched …), a doubt row only as looked at. `sticky.php`
   J5–J8 (a real fill with the stand-in: agree in A, model-alone in B, five
   doubts as one residue card offering "Put in A", the swap as an A-or-B
   question, undo); the ask removed from the run → J5–J7 red.
   `filing-trail.php` +2 rows on written rows, 125/125.

5. **The score lines (`c2bea7e`).** `VGML_MODEL=1` asks through task 2's
   helper for the labelled pictures only (the tech box's 900 mock rows are
   not paid for); the line above.

6. **The preview says which it is (`5c4b8ac`).** The count route's tally
   carries `rules_only: true` and never asks (`guide.php` F4b: `/v1/file`
   counted 0 on a 260-folder count; an ask mutated in → red). The Fill
   card's quiet pill waits for Nathan's copy — none until then.
   `folders.spec` reads `rules_only` off the turn route.

## Found, not done — and fixed on the way

- `filing-trail.php` had been red since S17's audience shadow (`0aff174`):
  its `ok` fixture said no audience and C (women's) became the shadow
  runner-up, 1.00 vs 0.93 → margin. The fixture now says `men`, with the
  reason in the file.
- `guide.php` A1 (boot ≤ 12 queries) had been red since S17's rail
  (`7d13048`, the `on_products` count). Cap 13, with the reason.
- The service's `e2e` workflow fails on my push and on the scheduled run
  before it (10:01 UTC) — pre-S18, not looked at.
- `docs/ai-service.md` (the plugin↔service contract) does not list
  `/v1/file` yet — out of the card's scope, one paragraph to add.

## For Nathan

**Copy — done (`d661cf6`), Nathan's option A, no "AI" on any screen:**
1. Why card, agree: "In X · both matches agree" (sure; no score line).
2. Why card, doubt: "Left where it was · matched X, but in doubt".
3. The Fill card after a run: "N in doubt" (only when N > 0).
4. The Fill card before a run: "estimate", quiet, at the end of the dry
   count's pills (the outcome sentence is not on the Folders screen, so
   the pill is the place). Mock: `docs/superpowers/mocks/2026-09-18-model-words.html`;
   the live card beside it in `mocks/shots/2026-09-18-model-words-*-live.png`.

**Pricing:** the call is metered on `file` at cost 1 and never debited.
~130 tokens a picture ≈ 50 cents a thousand at Sonnet 5's rate; the
button should say the price once there is one.

**Decided with Nathan (2026-09-18, end of S18):** doubts stay as built —
unfiled, in the residue by class, the small ones in the "more" card. A
"Put in X" card per folder was proposed and dropped: when the model
doubts, the rules' folder is right only 24 times in 71, so that button
would be wrong two times in three. The either/or cards stay (66 of 76
right among the two, one press each). Nathan: "I agree with you."

**The strings:** first drafted with "the AI" in them; Nathan: "rather no
AI" — option A above, shipped.

**One open call: the tech sure floor.** 90 % against 97 %: the six
hardware pictures both matchers file under Server racks. Either the mark
is the parent and both are wrong the same way, or those six are racks.
His eye on the truth page before any rule is touched.

**What the tiers cost in questions:** the shop reads 76 either/or + 71
doubts = 147 questions on 581 pictures (S10.5 folds one-picture pairs
into one card; residue by class). A walk of the Fill screen with the
model in — never done this session; every suite used the stand-in — is
the first thing to see before this ships.

## Gates

- `filing` 105/105 + 34/34 · `sticky` 61/61 · `guide` 42/42 · `filing-trail`
  125/125 · `surface` 27/27 · `escaping` 9/10 (the known ratio row) · `copy`
  58/58 · `tree-view` 65/65 · `seed-shop` 8/8 · `roles` 19/19 ·
  `ai-background` 37/37. Service `pnpm test` 502 passed, 14 skipped;
  `tsc` clean.
- Both SCORE lines rules-only after every task: shop 347 of 581 (60 %) ·
  72 %; tech 153 of 200 (77 %) · 76 %.
- Bands: tech 4/4 (fits 615 · sure 562 · likely 56 · siblings 3 · nothing 382), C.5 shop 4/4 (fits 497 · sure 490 · likely 13 · siblings 6 · nothing 123) — placements identical, rules-only, none re-taken.
- `folders.spec` + `modes.spec` on the tech site as `vgmls18`: **26 passed, 3 skipped, 0 failed (26.8 min)** — the same three skips as S16; `vgmls18` deleted.

Nothing moved on either library: every fill in the suites was the suite's
own pictures, undone or deleted; the scorer writes only the answer cache
(transients, a week). The one-off `vgml-fm_*` transients from the suites
are deleted by the suites; the scorer's 781 stay (they are the feature's
cache, and make a second scoring free).

## Next — S19

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S19: the model in the room. The strings are in (d661cf6); the button's price once Nathan prices the call. First a walk of the Fill on the tech site with the model in (a fresh tree, ~1,000 pictures, ~60 cents, screenshots of the Fill card and the question cards, the trail read on ten pictures) — the first time a person sees agree / doubt / the questions; then the six tech hardware pictures on the truth page (his eye, no rule; doubts stay as built, decided 2026-09-18); then the road to complete: a real WooCommerce shop walked end to end, a user's pass over every Folders screen, Plugin Check and the archive.",
  "model": "opus",
  "plan": "plans/agree-or-ask.md (done: the six tasks); the S19 tasks as this card lists them, a plan file only if Nathan's calls open more than a copy pass and a walk",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "js/vergeml-folders.js, core/folder-talk.php, tests/ui/folders.spec.mjs (the button's price, once Nathan prices the call: his words)",
    "tools/box-fill-walk.php, docs/superpowers/mocks/shots/** (the walk with the model in: cost said first, undone after)",
    "docs/ai-service.md (the /v1/file paragraph of the contract)",
    "tests/tree/filing-baseline.txt, tests/tree/filing-baseline-shop.txt (rules-only, re-taken only with the reason)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-18-s18-agree-or-ask.md (this: the two model lines, the tech sure miss, the doubt grain, the three strings, the box's service copy)",
    "plans/agree-or-ask.md (the decisions and the tiers as built)",
    "core/filing.php vergeml_filing_pick_model and vergeml_filing_ask_model (the tiers and the cache), core/folder-talk.php the ask before the count",
    "memory: openrouter-always, model-spend-discipline, tests-never-touch-live-state, truth-score-not-sheets, hooks-block-curl-and-key-paths, do-the-cli-work"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "The price of the model call is Nathan's; until priced it is metered, never debited",
    "Every user-facing string is Nathan's, verbatim; a placeholder is nothing",
    "Doubts stay as built (decided 2026-09-18); the six hardware pictures are his eye, not a rule",
    "The model never overrules a product placement, a hand placement, or files into a view or a locked folder",
    "A band is re-taken only rules-only and with the reason; the model's lines are never a band",
    "Say every cost before it is spent: a fill with the model over the tech library is ~60 cents a fresh tree; the suites use the stand-in, never the service",
    "A service push is not on the box until gh workflow run vps.yml --ref main has run and the box's copy answers the route",
    "Test first, one mutation per story; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work; never chain a checkout into a command"
  ],
  "gates": [
    "node tools/box-eval.mjs tools/box-truth-score.php --site shop and the tech line with --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json (MSYS_NO_PATHCONV=1): both rules-only lines in every check-in (shop 347 of 581 (60 %) · 72 %, tech 153 of 200 (77 %) · 76 %); with --env VGML_MODEL=1 after any tier change: shop 354 (61 %) · 89 % · answered 76 % · sure 90 %, tech 139 (70 %) · 84 % · answered 85 % · sure 90 % — neither line falls",
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background filing-trail → green (escaping 9/10 known); service pnpm test green",
    "the tech and C.5 bands 4/4 rules-only or re-taken with the reason",
    "folders.spec and modes.spec on the tech site green after any screen change"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-18-s18-agree-or-ask.md, then
plans/agree-or-ask.md. State which model you are and follow that profile
in ~/.claude/harness/model-profiles.md. This session is S19 of
every-picture-a-home; the card is already in .harness/active.json — read
it before anything else. Both rules-only SCORE lines first (shop 347 of
581, tech 153 of 200); then the Fill walk with
the model in on the tech site (say the cost, screenshots, undo); then the
six hardware pictures with Nathan on the truth page; then the road to
complete. Test first, one
mutation per story, both score lines after every task, every cost said
before it is spent, never on ms2 while Nathan is on it. Talk plainly. End
with a handoff carrying the S20 card.
```
