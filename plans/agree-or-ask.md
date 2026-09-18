# Agree or ask — the text model beside the rules (S18)

**Date:** 2026-09-18. **From:** S17's measurement (`docs/handoffs/2026-09-18-s16-the-truth-score-first.md`,
"the model as the matcher"). **Model for the phase:** Opus 5, one task at a
time, the two SCORE lines after every task. **Nathan's go:** "okay" on
option 1, 2026-09-18.

## Problem

Nathan, marking the tech truth page: "the descriptions are almost always
spot on." The describer is right; the matching of a right description to
the folders is what loses. Measured on both answer keys with Sonnet 5
reading the descriptions and the tree (text only, 40 pictures a call):

| | tech (200) | shop (581) |
|---|---|---|
| rules alone | 77 % right · 76 % of placed | 60 % · 72 % |
| model alone | 70 % · 70 % | 73 % · 88 % |
| both agree → sure; disagree → question; model alone → likely | 61 % before the questions, **86 % with them**; sure 99 % right | 65 % → **76 %**; sure 91 % right |

Neither alone wins both shapes; together they do, and the wrong-sure rate
(24 % / 12 % today) becomes 1 % / 9 %. The text call costs ~130 tokens a
picture ≈ 50 cents a thousand.

## Decisions taken

- The fill asks the model once per picture per tree: the same descriptions
  against the same folders never asked twice (cached by a hash of the
  folder paths + the picture's description).
- Sonnet 5 through OpenRouter, temperature 0, the prompt of
  `tools/truth-model.mjs` verbatim; *To sort* never offered.
- Metered like `/name-group` (counted, not debited) until Nathan prices
  it; the button says the cost when it is priced.
- Rules place first (they know what the folders hold); the model's answer
  decides the band: agree → **sure**; disagree → **a question** carrying
  both folders; rules nothing + model a folder → **likely**; rules a folder
  + model nothing → **a question** "X, or nowhere?" (the residue's "Put in
  X" / leave); both nothing → as today.
- The trail records it: `why` = `agree` on an agreement, `doubt` on a
  veto; `source` unchanged (whose word the rules matched), `hit` unchanged.
- The rail's pills do not change; the tally gains `agree` and `doubt`.

## Out of scope

The proposal from the pictures (S10.10) — a later phase. The model seeing
pictures (S10.11) — not needed: the descriptions are the pictures. Any copy
the plan does not supply.

## Tasks (Opus shape)

1. **The service route.**
   - Files: `service/app/api/ai/file/route.ts` (new), `service/lib/file-prompt.ts` (new, pure), `service/lib/file-prompt.test.ts`.
   - Behaviour: POST `{ key, site, folders: string[] (≤ 500 paths "A > B"), pictures: [{ id, says, caption }] (≤ 40) }` → `{ answers: { id: path | null }, model }`. The prompt is `tools/truth-model.mjs`'s system and user text, built by `filePrompt( folders, pictures )`; an answer naming no folder of the list is `null`; a missing id is `null`. Licence and limits as `/name-group`; metered, not debited.
   - Proof: `file-prompt.test.ts`: the prompt carries every folder once and every picture as `id: says — caption`; the answer parser turns a path not in the list into null; 41 pictures → 400. The route stood in by the describe-route test pattern.
   - Mirror: `service/app/api/ai/name-group/route.ts`; `wireModel` in `lib/anthropic.ts`.
   - Copy: none.
   - Do not: debit credits; accept more than 40; log a description.

2. **The plugin asks, once per picture per tree.**
   - Files: `core/filing.php` (`vergeml_filing_model_sql`? no — `vergeml_filing_ask_model( $rows, $profiles )`: per row `model_folder` = term id | 0 (null = nothing fits) | -1 (unasked: no licence, service down)); a transient per picture keyed `md5( tree paths ) . ':' . md5( says )`, a week.
   - Behaviour: batches of 40; a picture already cached is not sent; the answer's path is mapped to a term id through the profiles' paths by canon names (the map of S10.8 task 1); a path that maps to nothing is 0. Without a licence or on a failed call every row is -1 and the pick runs rules-only, as today.
   - Proof: `sticky.php` section J: the stand-in service (`sk_answer`) answers a batch; two fills ask once (the second reads the cache: the stand-in counts its calls); a service failure leaves the picks as rules-only (the fill still ends).
   - Mirror: `vergeml_filing_product_folders` (the per-slice fact on the row), `vergeml_talk_name_group` in `core/folder-talk.php` (the request shape, `pre_http_request` in the suites).
   - Copy: none.
   - Do not: ask on a dry count (the preview stays rules-only and says so — task 5); block the fill on the model (a timeout is -1).

3. **The pick, three tiers.**
   - Files: `core/filing.php` (`vergeml_filing_pick`, after the rules' outcome), `tests/filing/pick.php` rows 42–42e.
   - Behaviour: with `facts['model']` = -1 nothing changes. Rules fit X, model X → fits, sure, why `agree`. Rules fit X, model Y ≠ X (a folder) → nothing, why `margin`, children [X, Y] (the either/or question). Rules fit X, model 0 → nothing, why `doubt`, nearest X (the residue: "Put in X"). Rules nothing, model Y → fits Y, likely, why `ok`, source `model`, hit `''`. Rules siblings (parent P), model a child of P → that child, sure, `agree`; model elsewhere → the question. A locked or gated folder the model names counts as 0.
   - Proof: `pick.php` 42 (agree = sure), 42a (disagree = margin with both), 42b (veto = doubt, nearest), 42c (model alone = likely, source model), 42d (-1 = rules-only, identical outcome), 42e (the model naming a gated folder = 0). Mutation: the tiers removed → 42, 42a, 42b red.
   - Mirror: the product answer at the top of the pick (S10.8); `vergeml_filing_is_either`.
   - Copy: none (the question cards' copy exists).
   - Do not: let the model overrule a `product` placement or a hand placement; let the model place into a view.

4. **The run and the trail.**
   - Files: `core/folder-talk.php` (the ask before the count, the tally's `agree` / `doubt`), `core/librarian.php` (`why` list: `agree`, `doubt` are the matcher's own words; the why card's lines for them — **copy is Nathan's, two sentences to ask for**), `tests/tree/filing-trail.php` (a row per new why).
   - Behaviour: the fill asks per slice (task 2) before `vergeml_filing_count`; `doubt` rows go to the residue with the rules' folder as nearest; the report's tally carries `agree` and `doubt`.
   - Proof: `sticky.php` J rows: the trail row says `agree` / `doubt`; the residue question for a doubt offers "Put in X"; undo as before. `filing-trail.php` 123 → +2.
   - Mirror: the product mark in the run (S10.8 task 2).
   - Copy: Nathan's — placeholders until then: the why card says nothing new (no invented line).
   - Do not: write a why-card sentence.

5. **The score lines, both, with the model in.**
   - Files: `tools/box-truth-score.php` (`VGML_MODEL=1` asks through task 2's helper — the cache makes a second run free), `tools/box-eval.mjs` (nothing new).
   - Behaviour: with `VGML_MODEL=1` the picks carry the model's answers; the SCORE line as today plus `agree N · doubt N · questions N (right among the two N)`; without it, rules-only as today.
   - Proof: tech: sure ≥ 97 % right, right-with-questions ≥ 84 %; shop: sure ≥ 90 %, right-with-questions ≥ 74 % (S17's measured 99/86 and 91/76 with a margin for the mapping). Cost: ~30 cents a library, once.
   - Mirror: the scorer's product/source additions.
   - Do not: re-take a band on a model run (the bands stay rules-only).

6. **The preview says which it is.**
   - Files: `js/vergeml-folders.js` (the dry-run pills), `core/guide.php` (the count route's tally).
   - Behaviour: the dry count is rules-only and its pill row carries one quiet pill — copy Nathan's; until then none.
   - Proof: `folders.spec` row.
   - Do not: ask the model on a count.

## Gates

`node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background filing-trail` green; `service`: `pnpm test` green; both SCORE lines (task 5) at their floors; tech and C.5 bands 4/4 rules-only; `folders.spec` + `modes.spec` on the tech site.

## Stop points

Never on ms2 while Nathan is on it. Pricing of the call is Nathan's. Every user-facing string is Nathan's. A band is re-taken only rules-only, with the reason.
