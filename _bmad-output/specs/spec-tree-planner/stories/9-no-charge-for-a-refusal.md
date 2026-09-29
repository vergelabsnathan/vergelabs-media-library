---
title: 'No charge for a refusal'
type: 'feature'
created: '2026-09-29'
status: 'review'
baseline_commit: '0b47ede'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

## Intent

**Problem:** The never-worse guard (story 8) runs in the plugin after the service has charged. When it keeps the site's folders ("Your folders are already well organised — a new plan wouldn't improve them."), the owner has paid 38-100 credits for a plan they never see. And the guard was biased: it measured each filed picture's cosine to a centre that included the picture itself, so a folder of one read perfectly tight. On the shop's existing 125-folder tree (34/47, purity 76 %) it read 0.823 against 0.788 for a plan that is better on the truth (42/47, 78 %), and refused it.

**Approach:** (A) The plan's id travels on its spend and in the answer; when the guard keeps, the plugin names the plan to a new `/v1/plan-tree/refund` and the service gives the charge back, within bounds that hold against a client edited to always ask. (B) The guard counts each picture against the rest of its folder (leave-one-out), from the sums it already keeps, with the margin moved from 0.02 to 0.015. Both measured and proved without a paid call.

Nathan authorised the run and asked for decisions to be made for correctness, abuse-robustness and cost, and written here (2026-09-29).

## Commits

- Service (`tree-planner-service`, baseline `07c40b3`): `cfe62e6` feat(plan-tree): give a plan back when the site's own folders beat it.
- Plugin (baseline `0b47ede`): `26b0293` fix(tree): the never-worse guard no longer rewards many small folders; `876c99c` feat(tree): ask for a plan's credits back when the site's folders beat it; this story file.

## Task A: the design and its abuse bound

The guard needs the pictures' vectors, which never leave the site (a published promise), so the service cannot check its verdict. Any design either trusts the client or bounds it. Options weighed:

1. **Refund claim, bounded (chosen).** The plan is charged as today; the plugin claims a refund for a plan it did not offer. The service gives back only a plan it issued, for the licence and site that paid, within the hour, once, and within a per-site and per-licence allowance. It reuses the ledger's own shape (a spend, and a refund with the same source id), and the ledger's unique index on `(licence_id, source_id, reason)` makes "never twice" a database fact.
2. **Hold, then commit by default.** Equivalent in effect: the client's word releases the hold, silence commits. It needs a pending state and something to settle expired holds, for no bound that option 1 lacks. Rejected as more machinery for the same guarantee.
3. **Considered and rejected.** Charging only on accept makes free the default: an edited client never accepts and plans forever for nothing. Running the guard in the service needs the vectors, which never leave the site. A partial refund (keeping the ten-credit base) contradicts "no charge for a refusal".

**Abuse bound.** A client edited to always claim gets, per rolling thirty days, one free plan per activated site, and never more than 25 per licence (the agency plan's seats; legacy unlimited-site licences are capped there too). A plan costs at most `planPrice(3000) = 190` credits: the plugin folds to 1,500 labels (100 credits), but an edited client can send 3,000. Worst case: single, five and lifetime licences get 190, 950 and 190 credits a month; agency 4,750. In model cost, at most about $3.60 per free plan (fifteen runs at the 16,000-token cap), so about $3.60 a month for a single licence and $90 for an agency. Deactivating a site and activating a new address buys nothing: the per-licence count ignores which site. Every refunded plan still counts towards the daily planner cap and the burst and day limits (`metered_calls`, `spentSince`), so refunds cannot be used to plan faster.

## Task A: what changed

**Service** (`cfe62e6`):
- `app/api/ai/plan-tree/route.ts`: a `randomUUID()` per plan; the spend carries `plan:<uuid>` with the site (`credit_sites`); a failed plan's refund carries the same id; the answer gains `plan`.
- `app/api/ai/plan-tree/refund/route.ts` (new) + `/v1/plan-tree/refund` rewrite. Body `license_key`, `site`, `plan`. No entitlement check (a refund spends nothing, and a licence that lapsed within the hour still paid for the plan). Site must be activated.
- `lib/store.ts` `refundPlan`: under `select … for update` on the licence (the lock `spendCredits` takes), one `insert … select` into `plan_refunds` decides every rule, taking the amount from the ledger's spend (`-delta`), never from the client; then the ledger's refund row. An idempotent second ask answers `again: true` and moves nothing. `hasPlanRefunds` checks that 022 and 017 exist; until they do, the answer is `503 refund_unavailable`, which is today's behaviour: charged.
- `usageBySite` (the bill-back view) leaves out spends that were given back. Plan spends now land in `credit_sites`, and a refunded plan is not usage. This also stops refunded describes counting as used, which they wrongly did before.
- `lib/plan-tree.ts`: `PLAN_REFUND_WITHIN_MS` (1 h), `PLAN_REFUND_PERIOD_MS` (30 d), `PLAN_REFUND_SITES_MAX` (25), `planSource`, `isPlanId`.
- `db/migrations/022_plan_refunds.sql` (new, **not applied**): `plan_refunds (source_id pk, licence_id, site, credits > 0, at)` with an index on `(licence_id, at desc)`. Its own table rather than a reason on `credit_entries`: that reason check needs the table's owner (001-017), and a failed plan's refund must not use up the owner's allowance. Numbered 022 because 021 was written and dropped (`a038c48`).

**Plugin** (`876c99c`):
- `core/plan-tree.php` `vergeml_plan_refund()`: POSTs `license_key`, `site`, `plan` (20 s, one retry on a network error only). `vergeml_plan_event()` calls it only when the guard keeps and the answer names a plan. The plan state gains `refunded`, `charged` becomes the net amount, the credits cache takes the refund's balance, and the message becomes the one new string below. A refused refund (limit, older than an hour, 503) keeps the approved message and the charge.
- `readme.txt` External services, `docs/outbound-audit-2026-09-19.md` (a new row) and `docs/ai-service.md` (the refund contract) are updated in the same commit, as AGENTS.md policy requires.
- `tools/box-plan-refund.php`: the free box proof below.
- `js/vergeml-folders.js`: unchanged. It already shows `plan.message` for `kept`.

## Task B: the guard measure

**Before (story 8):** the mean over filed pictures (To sort left out) of cos(picture, its folder's centre), with the centre including the picture. A folder of *n* flatters each member by about 1/*n*; a folder of one reads 1.

**After:** each picture's cosine to the centre of the *rest* of its folder (leave-one-out). From the folder sums and counts alone (`vergeml_plan_gain_folder`), so there is no second pass over the vectors and no extra memory at 500,000 pictures: the numerator Σ v·(S−v) = S_K·S_all − n_K is exact, and each picture's distance to the rest is taken at the folder's root mean square. It is exact for folders of one and two, and within 0.0005 of the per-picture sum on all 51 replays. A picture alone in a folder counts 0, and a picture the plan unfiles still counts 0. `VERGEML_PLAN_GAIN` moves from 0.02 to 0.015.

**Candidates tried and dropped** (offline): average-link (mean cosine to the other members) kept the shop bias; a centroid silhouette rated tech's worst good-looking plan (run 4, 62 %) the highest of all; a folder-count penalty would mark down tech's good plans, which add folders, while the shop's remove them. Leave-one-out was the only one that ordered both sites, and it is the simplest.

**Harness (free):** `node tools/plan-sim.mjs <export.json> score <answers…> --gain` replays the service's rules, the plugin's choice, the draft's merge onto existing folders and the fill, then the guard by both measures. Inputs: the tech export and story 8's ten answers (scratchpads `312abff7…` and `c378600c…`), the shop export `shop-all.json` (626 pictures, the existing 125-folder tree) and forty shop answers `x/{lab,chain,given}` (scratchpad `dd13a8e8…`). The box ran the same trees through the real PHP (`vergeml_plan_choose`, `vergeml_plan_draft`, `vergeml_plan_gain`, read-only, outbound blocked). Before the change it matched story 8's measure to three decimals (tech 0.693 → 0.711/0.736/0.718/0.701/0.720; shop 0.823 → 0.788/0.788/0.787/0.790…), and after it matched the new one.

### Tech (existing 17 folders: 7/11, purity 67 %)

| Plan | Truth after the fill | Story 8 measure (0.693 before, +0.02) | New measure (0.675 before, +0.015) |
|---|---|---|---|
| run 1 | 8/11, 73 % | 0.711, refused | 0.697 (+0.0214), offered |
| run 2 | 7/11, 79 % | 0.736, offered | 0.717 (+0.0421), offered |
| run 3 | 7/11, 76 % | 0.718, offered | 0.702 (+0.0270), offered |
| run 4 | 5/11, 62 % | 0.701, refused | 0.688 (+0.0132), refused |
| run 5 | 7/11, 76 % | 0.720, offered | 0.703 (+0.0281), offered |
| folder-name plans 1-5 | 3-7/11, 49-73 % | 0.645-0.696, all refused | 0.636-0.680 (at most +0.0048), all refused |

(a) holds: the five folder-name plans are still refused, today's good plans are offered, and run 4, below the site's own 67 %, is refused. Run 1, better than the site on the truth, is now offered as well.

### Shop (existing 125 folders: 34/47, purity 76 %, F1 leaf 37 %)

The real flow keeps the tightest of its runs, so the plan the guard sees is each set's kept plan:

| Set | Kept plan, truth after the fill | Story 8 measure (0.823 before) | New measure (0.667 before) |
|---|---|---|---|
| lab, 5 runs | r4: 42/47, 78 %, F1 70 % | 0.788, refused | 0.689 (+0.0224), offered |
| chain, 20 runs | r5: 39/47, 76 %, F1 65 % | 0.789, refused | 0.684 (+0.0167), offered |
| given, 15 runs | r5: 39/47, 75 %, F1 63 % | 0.787, refused | 0.684 (+0.0177), offered |

All forty runs one by one. Story 8's measure refused all 40. The new one: good plans (≥ 72 % and ≥ 37/47) 18 of 19 offered (given/r13 is at +0.0150, just under); middling plans (64-70 %) 5 of 7 offered; poor plans (< 64 %) 0 of 14 offered.

(b) holds: no plan about as good on the truth as the existing tree is refused.

A note on story 8's live refusal: the shop's real plan that day read 0.757 on the old measure. In these replays, 0.757 goes with runs of 59-61 % purity (chain/r9 0.756, given/r3 0.754). That plan's tree was not saved, so this cannot be settled; it may have been refused rightly. The bias itself stands on the forty replays.

## Decisions

- Refund claim over hold/commit (above). The amount is always the ledger's.
- Allowance: one per site per 30 days; per licence, one per activated site at claim time, at most 25; claim window 1 h after the charge. A plan settles in about a minute, so the hour is generous for a slow cron and short for a hoarded id.
- A second kept plan on the same site within 30 days stays charged and keeps the approved message. No new copy explains it (see open risks).
- A failed plan's refund now carries the plan's id. It is still free (spec), and it no longer uses up the site's allowance, because the allowance lives in `plan_refunds`, not in the ledger.
- The plugin retries once, only on a network error. A 4xx/5xx answer is final.
- No entitlement check on the refund route. Activation is required.
- Guard: leave-one-out from sums, margin 0.015, pinned by `plan-gain` row 9 between tech run 4 (+0.0131 offline / +0.0132 via the committed harness) and the shop chain set's kept plan (+0.0167).
- `usageBySite` excludes spends that were given back (all reasons).

## I/O matrix

`POST /v1/plan-tree/refund` with `{ license_key, site, plan }`:

| Input | Answer | Ledger |
|---|---|---|
| this licence's plan, this site, ≤ 1 h, allowance free | 200 `{ refunded: <charge>, credits_remaining, again: false }` | refund row `plan:<id>`, `plan_refunds` row |
| the same plan again | 200 `{ …, again: true }` | nothing |
| a second plan, same site, < 30 d after the last refund | 429 `refund_limit` | nothing |
| the same, once that refund is > 30 d old | 200 | refund |
| a plan on a new site address after deactivating the old (single licence) | 429 `refund_limit` | nothing |
| the plan named on another activated site of the licence | 404 `not_refundable` | nothing |
| a site not activated on the licence | 403 `site_not_activated` | nothing |
| plan charged > 1 h ago | 404 `not_refundable` | nothing |
| another licence's plan id / an id never issued | 404 `not_refundable` | nothing |
| a failed plan's id (already refunded by the route) | 404 `not_refundable` | nothing |
| not a lowercase uuid | 400 `invalid_plan` | nothing |
| 022 not applied | 503 `refund_unavailable` | nothing |
| two claims at once for one site's allowance | one 200, one 429 | one refund |
| bad key / unknown key / bad site | 401 `bad_key` / 403 `not_found` / 400 `invalid_site` | nothing |

Plugin, `vergeml_plan_event` after the guard:

| Guard | Service answer | Plan state | Message |
|---|---|---|---|
| offers | (not asked) | `done`, charged N | none; the draft is set |
| keeps | 200 refunded N | `kept`, charged 0, refunded N; balance = refund's | "…wouldn't improve them. Nothing was charged." |
| keeps | 429 / 404 / 503 | `kept`, charged N, refunded 0 | the approved message |
| keeps | network error, then 200 | as 200 (two asks) | "…Nothing was charged." |
| keeps | network error twice | charged N (two asks, no third) | the approved message |
| keeps | an older service, no `plan` | charged N, no ask | the approved message |

## Proof (2026-09-29, free)

- Service `pnpm typecheck` clean; `pnpm test`: 614 passed, 14 skipped (the live suites), 50 files. `lib/plan-refund.test.ts`: 11 rows, both routes in-process on PGlite. Each mutation was tried and turns its own row red: dropping the per-site `not exists`, dropping the per-licence count, widening the one-hour window. Dropping the "never refunded" `not exists` stays green by design, because the ledger's unique index refuses the second refund row and the allowance row rolls back. `lib/plan-tree.test.ts`: the plan id is on the spend and on a failed plan's refund.
- Plugin deployed to the box (`node tools/deploy.mjs --box`, then `--check`: box up to date, 139 files verified; the zip line is STALE on `core/ai.php`, which predates this story).
- Free suites: `node tools/verify.mjs plan-gain plan-choose plan-fold plan-audience`: 9/9, 15/15, 27/27, 9/9.
- `tests/tree/plan-gain.php`: 9 rows. Rows 1-4 were re-derived for the new measure; new rows cover a folder of one (6), a group the site split into pairs, put back together (7; story 8's measure read 0.9764 → 0.9764, a refusal, and the new one 0.9075 → 0.9579), the closed form for two (8), and the margin pinned (9).
- Box, real PHP, read-only: the guard lines in the two tables above (tech, story 8's `trees.json`; shop, nine trees from `plan-sim --trees`).
- Box, `tools/box-plan-refund.php --site shop` (every request answered in the script, the session and credits cache restored raw): 8/8. Refund given (message, charged 0, refunded 38, balance 138); the ask carries exactly `license_key`, `site`, `plan`; no draft; 429 keeps the charge and the approved message; a blip then 200 (two asks); two blips (charge stands, no third ask); no plan id, no ask. Guard on that tree (given/r12): 0.6667 → 0.6385, kept.

## Pending: to go live, and the paid proof

1. **Migration** (Nathan): `node --env-file=<prod env> scripts/migrate-one.mjs 022_plan_refunds.sql` in the service repo, and the same on the box copy's database if the box plugin points there (`tools/box-vps-point.sh`). Until then the refund answers 503 and plans stay charged, as today.
2. **Service deploy** (Nathan): `cfe62e6` to `main`, then `node tools/promote.mjs`. The order does not matter: an old service sends no `plan`, so the new plugin never asks, and an old plugin ignores `plan`.
3. **Plugin release**: the next version carries `26b0293` and `876c99c`.
4. **Paid proof, after 1 and 2** (about 127 credits plus 38 given back; model cost about $0.25-0.50 a plan):
   - Shop on its existing tree: `node tools/box-eval.mjs tools/box-tree-snapshot.php --site shop --env VGML_MODE=snapshot --env VGML_FILE=/tmp/vgml-shop-tree.json` (the snapshot holds the guide session too); set the session's `tree` to `editing` (its confirmed tree refuses a plan); then `node tools/box-eval.mjs tools/box-plan-proof.php --site shop`. If the plan is kept, expect `plan kept: … Nothing was charged.`, a `plan:<id>` spend and refund pair in `credit_entries` and one `plan_refunds` row. If it is offered (likely under the new measure, when the kept plan is like the replays), expect 38 charged and the draft, and score it with `tools/tree-lab.mjs score`. Restore the snapshot after.
   - The allowance: a second plan the guard keeps on the same site within the month stays charged, with the approved message.
   - Tech: one plan (51 credits), expected offered; score with `tools/tree-lab.mjs score` against the story 8 bar (≥ 75 % and not below 67 %).

## Copy for Nathan

- New plugin string (shown when a kept plan was given back): "Your folders are already well organised — a new plan wouldn't improve them. Nothing was charged."
- readme.txt, External services, appended to "Planning your folders": "When the plan would not improve the folders you already have, it is not offered, and the plugin asks the service for the plan's credits back, sending your site's address, your licence key and the plan's reference number, nothing else."

## Open risks

- The margin is thin: tech's worse plan is at +0.0132, the shop chain set's kept plan at +0.0167, and the margin sits at 0.015. The evidence is two sites and 51 replays, so a real plan near the line can land either way. The paid proof above is the next data point.
- On the shop, middling plans (64-70 %, below the site's own 76 %) are offered 5 times in 7 when seen alone. The real flow keeps the tightest of fifteen, which in every set was a good plan, but "never below its own purity" is not guaranteed on the shop by a vector measure.
- A second kept plan within the month is charged, and the message does not say so. Copy for that case was not written unasked.
- A refund is lost if the job dies between the plan's answer and the claim, or after two network failures. That fails charged: safe for the service, not for the owner.
- `refundPlan`'s `for update` is not proved by PGlite, which runs one transaction at a time. It is the same lock `spendCredits` takes, which `credits-race.test.ts` proves live.
- The allowance counts activated sites at claim time: a licence can raise its cap by activating up to its seat count, which is why the bound is stated per seat (at most 25).
- `usageBySite` now also drops refunded describes from the account page's bill-back. This corrects an overstatement, but it is a visible change.
- The `.env.local` database is stale (last ledger row 2026-08-31, no `metered_calls`), so production's schema could not be read. That describes write `credit_sites` on every spend is the evidence that 017 is live.
