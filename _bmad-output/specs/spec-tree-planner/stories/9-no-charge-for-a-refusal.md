---
title: 'No charge for a refusal'
type: 'feature'
created: '2026-09-29'
status: 'review'
baseline_commit: '0b47ede'
route: 'dispatch'
review_loop_iteration: 1
context: []
---

## Intent

**Problem:** The never-worse guard (story 8) runs in the plugin after the service has charged. When it keeps the site's folders ("Your folders are already well organised — a new plan wouldn't improve them."), the owner has paid 38-100 credits for a plan they never see. The guard was also biased: it measured each filed picture's cosine to a centre that included the picture itself, so a folder of one read perfectly tight. On the shop's existing 125-folder tree (34/47, purity 76 %) it read 0.823 against 0.788 for a plan better on the truth (42/47, 78 %).

**Approach:** (A) Each plan has an id on its spend and in its answer. Whenever a charged plan is not shown to the owner, the plugin names it to `/v1/plan-tree/refund` and the service gives the charge back, within bounds that hold against a client edited to always ask. (B) The guard measures only the filed pictures on both sides, by leave-one-out and by average link, and offers a plan only when it is clearly tighter on the first and no looser on the second. Everything was measured and proved without a paid call.

Nathan authorised the run and asked for decisions to be made for correctness, abuse-robustness and cost, and written here (2026-09-29).

## Commits

- Service (`tree-planner-service`, baseline `07c40b3`): `cfe62e6` refund route, ledger and migration; review round: `58b18e3` bill-back view, `aebc2c4` a plugin-named plan id.
- Plugin (baseline `0b47ede`): `26b0293` guard, first round; `876c99c` refund claim; `32f3b9c` this story. Review round (after story 10's `24315c2`..`e705d45`): `9ee48fb` guard, filed pictures only plus average link; `f3c1361` lock refresh; `30f28dd` lost-answer and confirmed-mid-plan claims, honest message.

## Review round (2026-09-29): what the reviewer found and what changed

The independent reviewer found no critical or high money bug. Four fixes:

1. **Medium: the guard's bias flipped.** Leave-one-out rises with centre size, and the after centre counted the To-sort pictures the plan files. A folder of three at pairwise 0.6 read 0.671, and the same three plus seven To-sort pictures read 0.747. A merge that lowered similarity to 0.525 read +0.016 and was offered at 0.015, a margin 0.0018 above tech's run 4. Fixed in `9ee48fb`:
   - Only filed pictures make a centre, on both sides.
   - A plan must also not lose on the average link (each picture's mean cosine to every other member, exact from the sums), which leans towards finer trees.
   - The margin returns to 0.02 (see Task B).
2. **Low: the plan lock could expire mid-job.** Fixed in `f3c1361`: the lock is refreshed when the ask returns and again before a refund.
3. **Low: two paths charged without ever showing anything.** Fixed in `30f28dd` and `aebc2c4`:
   - A tree confirmed while the plan ran now drops the plan and claims its refund.
   - After a timeout the owner read "Nothing was charged" with no plan id to claim by. The plugin now sends its own uuid as `plan`. The service charges under it, and a reuse is `409 duplicate_plan`: nothing charged, no model asked.
   - After an unreachable or unreadable answer the plugin claims by that id. It says "Nothing was charged" only when the service confirms: given back, or `404 not_refundable`, meaning nothing is held for that id. Otherwise it shows a new message that asserts nothing about a charge.
4. **Low: the bill-back view.** It dropped a whole spend when any refund existed, and every plan counted as one "image". Fixed in `58b18e3`: used = spend − refunds against it, and images count only describes the site kept.

## Task A: the design and its abuse bound

The guard needs the pictures' vectors, which never leave the site (a published promise), so the service cannot check its verdict. Any design either trusts the client or bounds it. Options weighed:

1. **Refund claim, bounded (chosen).** The plan is charged as today, and the plugin claims a refund for a plan it did not show. The service gives back only a plan charged to that licence for that site, within the hour, once, and within a per-site and per-licence allowance. It reuses the ledger's own shape (a spend, and a refund with the same source id), and the ledger's unique index on `(licence_id, source_id, reason)` makes "never twice" a database fact.
2. **Hold, then commit by default.** Equivalent in effect: the client's word releases the hold, silence commits. It needs a pending state and something to settle expired holds, for no bound that option 1 lacks. Rejected.
3. **Considered and rejected.** Charging only on accept makes free the default. Running the guard in the service needs the vectors. A partial refund contradicts "no charge for a refusal".

**Abuse bound.** A client edited to always claim, whether "refused", "lost answer" or "confirmed mid-plan", gets per rolling thirty days one free plan per activated site, and never more than 25 per licence (the agency plan's seats; legacy unlimited-site licences are capped there too). A plan costs at most `planPrice(3000) = 190` credits: the plugin folds to 1,500 labels (100 credits), but an edited client can send 3,000.
- Worst case per month: single and lifetime 190 credits, five 950, agency 4,750.
- In model cost: at most about $3.60 per free plan (fifteen runs at the 16,000-token cap), so about $3.60 a month for a single licence and $90 for an agency.
- Moving to a new site address buys nothing, because the per-licence count ignores which site.
- Refunded plans still count towards the daily planner cap and the burst and day limits.
- A client-chosen plan id changes none of this: the id is only a name. A licence reusing one of its own ids is refused as a duplicate. `credit_sites` is keyed on the source id across licences, so a licence that reused another licence's id would lose its own site attribution and its own refund; it cannot touch the other licence's.

## Task A: what changed

**Service:**
- `app/api/ai/plan-tree/route.ts`:
  - The plan's id is the request's `plan` when it is a lowercase uuid, otherwise `randomUUID()`.
  - The spend carries `plan:<uuid>` with the site (`credit_sites`), and a failed plan's refund carries the same id.
  - The answer gains `plan`, and a duplicate spend answers 409.
- `app/api/ai/plan-tree/refund/route.ts` (new), with the `/v1/plan-tree/refund` rewrite:
  - Body `license_key`, `site`, `plan`; the site must be activated.
  - No entitlement check: a refund spends nothing.
- `lib/store.ts` `refundPlan`: under the licence row's `for update` lock (the same one `spendCredits` takes), one `insert … select` into `plan_refunds` decides every rule, taking the amount from the ledger's spend. It then writes the ledger's refund row.
  - An idempotent second ask answers `again: true`.
  - `hasPlanRefunds` checks that 022 and 017 exist; until they do, `503 refund_unavailable`.
- `usageBySite`: as in review fix 4.
- `lib/plan-tree.ts`: `PLAN_REFUND_WITHIN_MS` (1 h), `PLAN_REFUND_PERIOD_MS` (30 d), `PLAN_REFUND_SITES_MAX` (25), `planSource`, `isPlanId`.
- `db/migrations/022_plan_refunds.sql` (new, **not applied**):
  - `plan_refunds (source_id pk, licence_id, site, credits > 0, at)`.
  - Its own table because the `credit_entries` reason check needs the owner, and a failed plan's refund must not use the allowance.
  - Numbered 022 because 021 was written and dropped.

**Plugin:**
- `core/plan-tree.php`:
  - `vergeml_plan_event()` makes the plan's uuid (`wp_generate_uuid4`) and sends it with the plan.
  - It claims through `vergeml_plan_refund()` when:
    - the guard keeps;
    - the ask ends `unreachable` or `failed`;
    - the tree was confirmed while the plan ran.
  - `vergeml_plan_refund()` returns the refund, `refunded 0` for `404 not_refundable` (nothing held), or null (unknown).
  - The lock is refreshed after the ask and before a kept refund.
  - `vergeml_plan_credits_left()` updates the button's balance.
- `readme.txt` External services, `docs/outbound-audit-2026-09-19.md` and `docs/ai-service.md` are updated in the same commits: the plan's uuid on `/plan-tree`, and the refund and its triggers.
- `tools/box-plan-refund.php`: the free box proof below.

## Task B: the guard measure

**Story 8:** the mean over filed pictures of cos(picture, its folder's centre), with the picture in its own centre. It favours many small folders.

**First round (`26b0293`, superseded):** leave-one-out, with the after centre including the To-sort pictures the plan files, and a margin of 0.015. It favoured fewer, larger folders and To-sort inflation (review fix 1).

**Now (`9ee48fb`):** `vergeml_plan_gain_better` offers a plan only if, over the same filed pictures and with only those pictures in any centre:
- leave-one-out (each picture's cosine to the centre of the rest of its folder) gains at least `VERGEML_PLAN_GAIN` = 0.02; **and**
- the average link (each picture's mean cosine to the other members) does not fall.

Both come from the folder sums and counts, so there is no second pass over the vectors. Leave-one-out is within 0.0005 of the per-picture value on every replay. The average link is exact.

**Why these two, and why 0.02.**
- Each measure alone rewards one granularity. Together, a merge or a split cannot pass on granularity alone.
- On tech, run 4 (62 %, below the site's own 67 %) gains 0.0140, and the weakest good run (run 1, 73 %) gains 0.0182. Run 4 also gains on the average link (+0.019), so no rule monotone in both measures separates it from the shop's good plans.
- I also tried subtracting a same-size random-folder baseline from leave-one-out. It rated every shop plan below the existing tree, so it was dropped.
- No margin separates run 4 from run 1 with room. Following the coordinator's instruction, the safer failure is chosen: the margin returns to story 8's 0.02 (Nathan's "clearly better"). That leaves run 4 0.006 below the line, and a good plan near the line is refused and refunded rather than a worse one offered.

**Harness (free):** `node tools/plan-sim.mjs <export.json> score <answers…> --gain` replays the service's rules, the plugin's choice, the draft's merge onto existing folders and the fill. It prints leave-one-out, the average link and the verdict, with story 9's first measure and story 8's beside them.
- Inputs: the tech export and story 8's ten answers (scratchpads `312abff7…`, `c378600c…`); the shop export `shop-all.json` (the existing 125-folder tree) and forty shop answers `x/{lab,chain,given}` (scratchpad `dd13a8e8…`).
- The box's real PHP (`vergeml_plan_choose`, `vergeml_plan_draft`, `vergeml_plan_gain`; read-only, outbound blocked) gives the same leave-one-out values to three decimals. The shop's average link before (0.5545) matches too, from the refund proof.

### Tech (existing 17 folders: 7/11, purity 67 %)

| Plan | Truth after the fill | Story 8 (0.693, +0.02) | First round (0.675, +0.015) | Now: leave-one-out 0.6749, link 0.4712 |
|---|---|---|---|---|
| run 1 | 8/11, 73 % | 0.711, refused | +0.0214, offered | +0.0182, link +0.0285: **refused** (under 0.02) |
| run 2 (the flow's kept plan) | 7/11, 79 % | 0.736, offered | +0.0421, offered | +0.0379, link +0.0622: **offered** |
| run 3 | 7/11, 76 % | 0.718, offered | +0.0270, offered | +0.0205, link +0.0358: **offered** |
| run 4 | 5/11, 62 % | 0.701, refused | +0.0132, refused | +0.0140, link +0.0191: **refused** |
| run 5 | 7/11, 76 % | 0.720, offered | +0.0281, offered | +0.0240, link +0.0419: **offered** |
| folder-name plans 1-5 | 3-7/11, 49-73 % | all refused | all refused | at most +0.0023: **all refused** |

(a) holds: the folder-name plans and run 4 are refused, and today's good plans are offered, except run 1, which is refused and refunded as the safer failure.

### Shop (existing 125 folders: 34/47, purity 76 %, F1 leaf 37 %)

| Set | Kept plan, truth after the fill | Story 8 (0.823) | First round (0.667) | Now: leave-one-out 0.6666, link 0.5545 |
|---|---|---|---|---|
| lab, 5 runs | r4: 42/47, 78 % | 0.788, refused | +0.0224, offered | +0.0191, link −0.0074: **refused** |
| chain, 20 runs | r5: 39/47, 76 % | 0.789, refused | +0.0167, offered | +0.0125, link −0.0138: **refused** |
| given, 15 runs | r5: 39/47, 75 % | 0.787, refused | +0.0177, offered | +0.0156, link −0.0122: **refused** |

All forty runs one by one: none offered. Every plan loses on the average link against 125 small folders.

(b) is **not met** on the shop: a plan about as good on the truth is refused. That is the safer failure the coordinator's instruction allows. With 022 applied it is refunded, once per site per month, so the owner pays nothing, but they are not offered the better-recall plan (F1 leaf 63-70 % against 37 %). This goes to Nathan as an open decision (below).

**The review's constructed cases** (`tests/tree/plan-gain.php`, rows 7-9):

| Case | Leave-one-out | Link | Verdict |
|---|---|---|---|
| a folder of 3 at 0.6, plus 7 To-sort pictures | 0.6708 → 0.6708 (was 0.7474 in the first round) | 0.600 → 0.600 | refused |
| three folders of 3 merged, similarity 0.525 | 0.6708 → 0.6868 (+0.016) | 0.600 → 0.525 | refused |
| two folders of 2 merged (0.6 inside, 0.5 across) | 0.600 → 0.6426 (+0.043, over the margin) | 0.600 → 0.533 | refused, on the link alone |

## Decisions

- A refund claim rather than hold/commit. The amount is always the ledger's.
- Allowance: one refund per site per 30 days. Per licence, one per activated site at claim time, at most 25. Claim window: one hour after the charge.
- Every path where a charged plan is not shown claims through the same allowance: guard keeps, lost or unreadable answer, tree confirmed mid-plan.
- The plugin names the plan (a uuid) so a lost answer can be claimed. The service accepts only a lowercase uuid, and a reuse is 409 with nothing charged.
- The plugin says "Nothing was charged" only when the service confirms it. Otherwise the message makes no claim about a charge.
- The plugin retries a refund once, and only on a network error.
- Guard: filed pictures only; leave-one-out +0.02 and no loss on the average link. Where no margin separates, refuse and refund. `plan-gain` row 11 pins the margin at least 0.005 above run 4 and at most run 3's +0.0205.
- The bill-back view shows used = spend − refunds; images are describes kept, never plans.
- A second kept plan within 30 days stays charged, with the approved message. No new copy explains it.

## I/O matrix

`POST /v1/plan-tree/refund` with `{ license_key, site, plan }`:

| Input | Answer | Ledger |
|---|---|---|
| this licence's plan, this site, ≤ 1 h, allowance free | 200 `{ refunded, credits_remaining, again: false }` | refund row `plan:<id>`, `plan_refunds` row |
| the same plan again | 200 `{ …, again: true }` | nothing |
| a second plan, same site, < 30 d after the last refund | 429 `refund_limit` | nothing |
| the same, once that refund is > 30 d old | 200 | refund |
| a new site address after deactivating the old (single licence) | 429 `refund_limit` | nothing |
| the plan named on another activated site of the licence | 404 `not_refundable` | nothing |
| a plan charged > 1 h ago; another licence's plan; an id never charged; a failed plan's id | 404 `not_refundable` | nothing |
| not a lowercase uuid | 400 `invalid_plan` | nothing |
| 022 not applied | 503 `refund_unavailable` | nothing |
| two claims at once for one site's allowance | one 200, one 429 | one refund |

`POST /v1/plan-tree` with `plan`: a lowercase uuid is charged under that name; the same name again from the licence gives 409 `duplicate_plan` (nothing charged, no model asked); anything else gets a service-made name.

Plugin, `vergeml_plan_event`:

| Path | Service answer to the claim | Plan state | Message |
|---|---|---|---|
| guard offers | (no claim) | `done`, charged N | none; the draft is set |
| guard keeps | 200 | `kept`, charged 0, refunded N | "…wouldn't improve them. Nothing was charged." |
| guard keeps | 429 / 404 / 503 / two blips | `kept`, charged N | the approved message |
| guard keeps, older service (no `plan`) | (no claim) | `kept`, charged N | the approved message |
| answer lost (timeout, unreachable) | 200, or 404 `not_refundable` | `failed` | "The service could not be reached. Nothing was charged." (existing) |
| answer lost | anything else (429, 503, an old service's 404 page) | `failed` | **new:** "The plan did not come back, and it may have been charged. Your balance is on the Licence screen." |
| unreadable answer (`failed`) | any | `failed` | "The service answered: …" (existing, claims nothing) |
| tree confirmed while the plan ran | any | plan dropped | none (as before) |

## Proof (2026-09-29, free)

- **Service:** `pnpm typecheck` clean; `pnpm test` 616 passed, 14 skipped (the live suites). `lib/plan-refund.test.ts` has 13 rows on PGlite, driving both routes in-process.
  - The per-site, per-licence and one-hour mutations each turn their own row red.
  - Rows from the review round: the client id is used; a reuse gives 409 and charges once; a lost answer is given back by its id; an unknown id gives 404; the bill-back view shows a partial refund as 6 used and 1 image, a full refund as 0 and 0, and a plan never as an image.
- **Plugin suites:** `node tools/verify.mjs plan-gain plan-choose plan-fold plan-audience plan-grow folders-shop` give 11/11, 15/15, 27/27, 9/9, 18/18 and 14/14.
- **Box deploy:** `node tools/deploy.mjs --box`, then `--check`: box up to date at `30f28dd`, 139 files verified.
- **Box, real PHP, read-only:** the guard values in both tables above.
- **Box refund wiring**, `tools/box-plan-refund.php --site shop`, with every request answered in the script and the session and credits cache restored raw: **13/13**.
  - The guard-kept paths (rows 1-8, as in the first round).
  - The plan goes out under a site-made uuid, and a lost answer is claimed by it (9).
  - A lost answer that is given back (10) or has nothing held (11) shows "Nothing was charged."
  - A lost answer that is not given back shows the new message (12).
  - A tree confirmed mid-plan drops the plan and claims it (13).
  - On that tree (given/r12), leave-one-out 0.6667 → 0.6366 and link 0.5545 → 0.4583: refused.

## Pending: to go live, and the paid proof

1. **Migration** (Nathan): `node --env-file=<prod env> scripts/migrate-one.mjs 022_plan_refunds.sql` in the service repo, and on the box copy's database if the box plugin points there. Until then refunds answer 503 and plans stay charged.
2. **Service deploy** (Nathan): `cfe62e6`, `58b18e3` and `aebc2c4` to `main`, then `node tools/promote.mjs`. Either order is safe. An old service ignores the plugin's `plan` and has no refund route, so the plugin's claims come back unknown: charged, with the new message after a lost answer. An old plugin ignores `plan` in the answer.
3. **Plugin release:** the next version carries this story's plugin commits.
4. **Paid proof, after 1 and 2** (about 127 credits, some given back; model cost about $0.25-0.50 a plan):
   - **Shop on its existing tree.** Snapshot: `node tools/box-eval.mjs tools/box-tree-snapshot.php --site shop --env VGML_MODE=snapshot --env VGML_FILE=/tmp/vgml-shop-tree.json`. Set the session's `tree` to `editing`, then run `node tools/box-eval.mjs tools/box-plan-proof.php --site shop`. Expect `plan kept: … Nothing was charged.`, a `plan:<id>` spend and refund pair, and one `plan_refunds` row. Restore the snapshot after.
   - **The allowance:** a second kept plan on the same site within the month stays charged, with the approved message.
   - **Tech:** one plan (51 credits), expected offered if it resembles run 2, 3 or 5. Score it with `tools/tree-lab.mjs score`.

## Copy for Nathan

- Shown when a kept plan was given back: "Your folders are already well organised — a new plan wouldn't improve them. Nothing was charged."
- Shown after a lost answer that was not confirmed refunded: "The plan did not come back, and it may have been charged. Your balance is on the Licence screen."
- readme.txt, External services, "Planning your folders":
  - inserted after "your licence key,": "a random reference number the plugin makes for the plan,"
  - appended: "When a plan is not shown to you -- it would not improve the folders you already have, its answer did not arrive, or you confirmed your tree while it ran -- the plugin asks the service for the plan's credits back, sending your site's address, your licence key and the plan's reference number, nothing else."

## Open decisions and risks

- **Decision for Nathan: the shop.** Over a tree of many small folders, the guard now refuses every plan, including ones better on recall (F1 leaf 63-70 % against 37 %) at the same purity. The owner is refunded but never offered them. Vector cohesion cannot say whether 85 folders beat 125 at equal purity. Options: accept this; offer the plan with a note when leave-one-out passes but the average link does not; or make the choice the owner's rather than the guard's.
- **Tech run 1** (73 %, better than the site) is refused at +0.0182. The margin sits 0.006 above run 4 and 0.0005 below run 3, so good plans near the line will sometimes be refused and refunded.
- A second refused plan within the month is charged, and the message does not say so.
- A refund is lost if the job dies before claiming, or after two network failures. Either way it fails charged.
- `refundPlan`'s `for update` lock is not proved by PGlite. It is the same lock `spendCredits` takes, which `credits-race.test.ts` proves live.
- The allowance counts activated sites at claim time, so the bound is stated per seat, at most 25.
- The bill-back view changes visibly: refunded describes and plans no longer count as used or as images.
- The `.env.local` database is stale, so production's schema could not be read. That describes write `credit_sites` on every spend is the evidence that 017 is live.
