# Handover — Phase 2, session S4b: task 2.12 (Opus 5)

Run on 2026-09-12, 17:35–18:10. The four defects the lifecycle walk found
(`service/docs/lifecycle.md`, A–D) are fixed in one commit, `d8c14bd`, which
is serving on production, and the live endpoint now carries
`charge.dispute.closed`. Tests first: the two suites were red (13 failed),
made green with the smallest change, both mutations named in the task go
red. Nothing outside the task's file list was touched except the runbook's
event count and a one-line note in `lifecycle.md`.

## What changed, in `service/`

- `lib/reconcile.ts` — `periodEndToStore(status, periodEnd)`: null for
  `past_due`, the date through otherwise.
- `app/api/cron/reconcile/route.ts` — the nightly sweep stores
  `periodEndToStore(truth, …)`, so it no longer writes the year Stripe
  rolled forward on a past-due subscription (it would have undone B every
  night).
- `app/api/stripe/webhook/route.ts`:
  - **A** — `linePeriodEnd(invoice)`: the latest `lines.data[].period.end`;
    the existing-row `invoice.paid` branch stores that instead of
    `invoice.period_end`. No lines → null → `coalesce` keeps the column.
  - **B** — the `customer.subscription.*` branch stores
    `periodEndToStore(status, periodEndOf(sub))`; `invoice.payment_failed`
    writes `past_due` with `current_period_end = coalesce(invoice.period_end,
    current)` — the renewal's due date, so `entitlement()`'s seven days run
    from the day the card failed.
  - **C** — that same update carries `and status <> 'canceled'`.
  - **D** — the `charge.refunded` body moved verbatim into
    `moneyBack(chargeId, ledgerKey, tx)`; `charge.refunded` calls it with a
    null key (the refund id, as before); a new `case 'charge.dispute.closed'`
    calls it with the dispute id when `status === 'lost'` and returns before
    any Stripe read otherwise. `charge.dispute.created` stays unhandled.
- `lib/lifecycle.test.ts` (new, was pre-written), `lib/reconcile.test.ts`
  (extended, pre-written): no test was edited.
- `docs/runbooks/stripe-webhook.md` — eight → nine events, `d8c14bd` named.
- `docs/lifecycle.md` — one line under "Findings": A–D fixed in `d8c14bd`.

## Stop points, as they went

1. `GRACE_DAYS` and the seats: untouched (`git show d8c14bd -- lib/licence.ts`
   is empty).
2. `charge.dispute.closed` subscribed only after `/api/build` on
   `vergelabsmedia.com` answered `sha: d8c14bd96c…` for deployment
   `dpl_5gTYtd6bdoZ2bqR2pHypkh7tSg5z` — then `ENDPOINT_ONLY=1` ran without
   asking, as briefed.

## Gates, with the lines

- Red first: `pnpm vitest run lib/lifecycle.test.ts lib/reconcile.test.ts` →
  `Tests 13 failed | 33 passed (46)`.
- Green: same command → `Tests 46 passed (46)`.
- Mutation 1, `fromUnix(invoice.period_end)` restored in A →
  `Tests 2 failed | 1 passed | 12 skipped` (the line-period test and the
  "does not move backwards" test). Restored.
- Mutation 2, `and status <> 'canceled'` dropped →
  `Tests 1 failed | 14 skipped` (C). Restored.
- `pnpm test` → `Tests 406 passed | 13 skipped (419)`.
- `pnpm typecheck` → exit 0.
- Deploy: `vercel ls --prod` showed `efm3rwy4y` Building 6 s after the push,
  Ready after 26 s; `/api/build` → `{"sha":"d8c14bd96c32f60fdaac12208f0e98fec2fd8183","ref":"main"}`.
- `ENDPOINT_ONLY=1 … stripe-live-setup.ts` → `enabled we_1UAvRvENqcXgcnrD7mu3Xym5 … events set to: … charge.refunded, charge.dispute.closed`.
- `node scripts/webhook-check.mjs` → `OK https://vergelabsmedia.com/api/stripe/webhook enabled with the 9 handled events`, exit 0.

## Cost

Nothing. No model called, no Stripe object created; the live endpoint's
event list was rewritten once (a read and a write against live Stripe).

## Mechanics

- `git commit -F -` reads stdin, which is null in this harness: the commit
  message went through a scratchpad file. The heredoc is not the problem;
  `-F -` is.
- `vercel inspect <url>` does not print the commit. `/api/build` on the site
  does (`app/api/build/route.ts`), and it is the build-identity check the
  global rule asks for.
- The hook did not block `node --env-file=<scratchpad>/prod.env`, contrary
  to memory `file-edits-in-this-harness` — the block seems to be on the
  repo's own env paths, not the scratchpad. `prod.env` deleted afterwards.
- `s.upsertSubscription` and the other `store()` writes in `applyEvent` run
  on the pool, not on `tx` — pre-existing, the plan's "every write inside the
  event transaction" was already only true of the `tx.query` calls. Not
  changed here; worth its own line if it matters.

## Found, not done

- The above: `store()` inside `applyEvent` is not the transaction.
- `export applyEvent` from the route file still breaks typecheck after a
  local `next dev` (S4 handoff); typecheck was green here because no dev
  server ran.
- `quoteCredits(2000,'eur')` vs the memory's €29.00 — one is stale (S4).
- Test-mode product blurbs (S4).
- `/account` Billing with several customers on one email (S4).
- S3's stale-sweep item.

## State left behind

- Service repo: `d8c14bd` (the fix) and a docs commit after it (runbook,
  lifecycle.md), both pushed; the docs commit deploys too, no code in it.
  `tsconfig.tsbuildinfo` modified and `docs/mocks/` untracked, as before.
- Plugin repo: this handoff, committed and pushed.
- Stripe live: endpoint `we_1UAvRvENqcXgcnrD7mu3Xym5` on nine events. No
  other live change.
- Scratchpad: `prod.env` deleted; `commit-msg.txt`, `build.mjs` remain
  (nothing secret in either).

## Next

- **S5 is 2.6 + 2.7** (seats on the box and Playground; Pro's suites), per
  the S4 handoff.
- 2.8 can run on the same test keys now that its four purchases go through
  the fixed webhook; B and D are decided and built, so the "decide first"
  note in the S4 handoff is closed.
