---
title: "A subscription's first invoice never lands under Stripe's floor"
type: 'fix'
created: '2026-09-20'
status: 'review'
route: 'dispatch'
baseline_commit: 'service 7dc49f1'
context:
  - '{project-root}/plans/finish-the-suite.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A code on a yearly plan is applied by a Stripe coupon, and Stripe does not charge an invoice below its minimum (€0.50): it marks it paid at €0.00 and activates the subscription anyway. `invoice.paid` then issues the licence. Seen live 2026-09-20: PRIVATE0 (99 %) on `single` → invoice AWTXBCFC-0012, total €0.39, `amount_paid 0`, licence `…AB26` with 2,000 credits for nothing. `discounted()` floors the 1–49 cent band for one-off payments only; subscriptions had no floor.

**Approach:** the floor lives in `eligible()` — the one rule both the pricing preview and the intent route already ask — as a new reason `minimum_amount` for a recurring plan whose first invoice would come to less than the currency's minimum (€0.50, $0.50, £0.30), zero included. The cart's existing sentence for that reason is reused. No Stripe call changes.

## Boundaries & Constraints

**Always:** preview and intent agree (same function, same context); exactly the minimum is allowed (the €0.50 walk stays valid); one-off purchases (credits, lifetime) keep today's behaviour.

**Never:** touch the webhook; call Stripe in a test; change PRIVATE0's row (Nathan's); write new copy.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected | Error |
|---|---|---|---|
| 99 % on single | 3900 eur, percent 99, recurring | refused `minimum_amount` | — |
| €38.50 off single | 3900 eur, amount 3850, recurring | ok (50 = the floor) | — |
| €38.51 off single | 3900 eur, amount 3851, recurring | refused | — |
| 100 % on single | 3900 eur, percent 100, recurring | refused (a free year is a decision, not a code) | — |
| 99 % on credits | 9000 eur, percent 99, one-off | ok as today (`discounted()` charges 90) | — |
| GBP floor | 3500 gbp, amount 3475, recurring | ok (25 < 30 → refused; 3470 → 30 ok) | — |

</frozen-after-approval>

## Code Map

- `service/lib/discounts.ts` — `eligible()` gains `ctx.recurring`; `STRIPE_MINIMUM` per currency; `rawAfter()` (no floor) beside `discounted()`.
- `service/app/api/licence/intent/route.ts:230` — passes `recurring: !buyingCredits && isSubscriptionPlan(plan)`.
- `service/app/api/pricing/route.ts:62` — passes `recurring` for `single|five|agency`.
- `service/app/checkout/page.tsx:137` — `code_minimum_amount: 'That discount code needs a larger order.'` (reused).

## Tasks & Acceptance

- [x] Test first in `lib/discounts.test.ts`: the six rows above.
- [x] `eligible()` floor; the two routes pass `recurring`.
- [x] Mutation: floor removed → the 99 % row goes green wrongly, the test red.
- [x] Deploy on "say what goes out"; live probe `/api/pricing?plan=single&code=PRIVATE0` → `ok:false, reason minimum_amount`.

## Implementation Notes

- Service `7c12bf1`, pushed 2026-09-20 and serving. `lib/discounts.test.ts` red first (`expected null to be 'minimum_amount'`), then `7 passed (7)`; mutation (the floor line removed) `1 failed | 6 passed`, restored `7 passed`; full suite `513 passed | 14 skipped`; `tsc` clean.
- Live probe after the build: `/api/pricing?plan=single&code=PRIVATE0` → `ok:false, minimum_amount, "That code needs a larger order."`; `credits=10000&code=PRIVATE0` → `ok €1.40` (one-off path unchanged); `plan=single&code=WALK0920` → `ok €0.50` — the floor itself, allowed by design.
- Found: WALK0920 (max_uses 1) is still `ok` after its one use — subscription purchases never write a redemption (webhook returns early for yearly plans before `recordRedemption`). In `deferred-work.md`.
- Done in one go on Nathan's word; no `bmad-spec` question asked — the plan's default (refuse at zero too) taken.

## Verification

- `pnpm vitest run lib/discounts.test.ts` — expected: all green, the new rows named.
- Live: `/api/pricing?plan=single&code=PRIVATE0` → `minimum_amount`; `…&code=WALK0920` → `exhausted` (1 use) — both refusals, neither a free year.
- Cost: none.
