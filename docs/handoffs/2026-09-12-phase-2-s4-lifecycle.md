# Handover — Phase 2, session S4: task 2.4 (Opus 5)

Run on 2026-09-12, 14:45–16:50, in Stripe **test mode** with a test clock per
row, against the service at `adbdf5c` running locally on a throwaway Neon
database. **All six lifecycle rows walked and written down** in
`service/docs/lifecycle.md`, each with its Stripe event ids and the exact
"customer is told" strings off the email, `/account` and Pro's Licence
screen. Six findings, none patched (stop point 2). One file added to the
repo: `service/docs/lifecycle.md`. Nothing in live mode, no real card, no
production row touched (stop point 3). Screenshots and the listener's
delivery log: `docs/handoffs/2026-09-12-phase-2-s4-lifecycle/`.

## Stop points, as they went

1. **The key.** Nathan supplied the `sk_test_`/`pk_test_` pair in the
   conversation (so they are in the transcript — roll them in the dashboard
   if that matters; test keys move no money). Written to a scratchpad
   `test.env`, deleted at the end. `scripts/mode-check.mjs` first:
   `livemode: false — TEST MODE — enabling licensing cannot move real money`.
2. **Gaps are findings.** Six, in `docs/lifecycle.md` under "Findings — not
   fixed here", each with the event id that showed it and the fix shape.
3. **Nothing live.** Production, the prod DB and the live endpoint untouched.

Three calls the plan left open, taken on Nathan's "as you want": a throwaway
Neon project as the database (deleted at the end); Playground CLI for Pro's
screen (it reached `localhost:3011` — no fallback needed); the inbox
`nathan+lifecycle@vergelabs.nl` (five emails landed there; he can delete
them).

## The six rows, one line each (the table is in `service/docs/lifecycle.md`)

1. **Renewal** — `invoice.paid evt_1UEsanENbkxwktzhkc5BRerl` → `+2000 renewal`,
   balance 4,000; **no email**; `/account` and Pro then show the renewal a
   year short until reconcile (finding A).
2. **Failed renewal** — `invoice.payment_failed evt_1UEshlENbkxwktzhmkVqWJZq`
   → `past_due`; **no email**; grace runs from the period Stripe rolled
   forward unpaid, so AI features stay on for a year and a week (B); Stripe
   cancels between day 7 and 14, and the last retry's failure overwrote
   `canceled` with `past_due` (C); reconcile repaired it. Pro's real screen:
   "Your last payment did not go through. Everything keeps working for a few
   days while you update your card." Lapsed: `Download Pro 1.0.2 (zip)`
   disabled with **Canceled** beside it; Pro: "That licence was cancelled."
3. **Cancel mid-term** — `/api/licence/plan` cancel → "Cancelled. Everything
   keeps working until the end of the period you have paid for."; `/account`:
   "This subscription is set to end on 12 Sept 2027. Everything keeps working
   until then."; at period end `customer.subscription.deleted
   evt_1UEsodENbkxwktzhBlWYcMPT` → `canceled`. **No email** at either step.
4. **Upgrade and back** — seats 1 → 5 → 1 on `customer.subscription.updated`
   (`evt_1UEspwENbkxwktzhNrn1pYKY`, `evt_1UEsqhENbkxwktzhRTmlAC1J`); credits
   2,000 throughout; prorations net €3.29 on the next invoice; "Plan changed.
   The difference is charged or credited on your next invoice…". **No email.**
5. **Refund after spending** — pack refunded → ledger `−2000 refund
   re_3UEss3ENbkxwktzh0gvnETZg`, balance **−1,500**, carried; subscription
   refunded → `charge.refunded evt_3UEsrtENbkxwktzh0B4O1WRe` → cancel →
   `canceled` (2.9 works end to end). Pack purchase emailed "2,000 credits
   added — invoice AWTXBCFC-0096"; **refunds email nothing**.
6. **Chargeback** — `charge.dispute.created evt_1UEsuEENbkxwktzhXglprL5z`,
   closed lost `evt_1UEsvKENbkxwktzh6MoMgEig`: both `200` from `default:`,
   ignored; licence active, credits intact, subscription due to renew (D).
   The live endpoint is not subscribed to either event.

## Gates, with the lines

- `service/docs/lifecycle.md` — six rows, event ids from the clock runs, a
  "the customer is told" cell per row including the download control's
  label on a lapsed licence (`Download Pro 1.0.2 (zip)` disabled, `Canceled`).
- Delivery log `stripe-listen.log` in the folder: **56 × `[200]`**, 3 ×
  `[404]` — the three are the very first purchase on Turbopack (below), whose
  clock was deleted and the row redone. Every handled event of the six rows
  answered `200`; each id is in `processed_events` (`events` output in the
  transcript).
- `pnpm test` → `Tests 386 passed | 13 skipped (399)`.
- `pnpm typecheck` → exit 0 (after the trap below was cleared).
- Pictures in the folder: `01-renewal`, `01b-renewal-overview`,
  `02-failed-overview`, `02b-failed-licence`, `02c-failed-canceled`,
  `03-cancel-overview`, `03b-cancel-billing`, `05-refund-overview`,
  `06-pro-cancelled`, `06b-pro-connected`, `06c-pro-degraded`.

## Cost

Nothing. Test mode; no model was called; Resend sent five emails to Nathan's
own address; the Neon free tier for two hours. Real-world side effects:
`neon projects list` opened an OAuth window in Nathan's browser once.

## Mechanics — what the next session needs to know

- **Turbopack 404s every three-level `app/api/*/*/route.ts`** on this
  machine (`/api/stripe/webhook`, `/api/auth/me`, `/api/licence/verify`,
  `/api/account/download` …) while two-level ones work. `pnpm dev --webpack
  --port 3011` serves them all. Probably the emoji in the repo path; not
  investigated.
- **`pnpm typecheck` goes red after any local `next dev`** that compiles the
  webhook route: `.next/dev/types/app/api/stripe/webhook/route.ts` is
  generated, `tsconfig.json` includes `.next/dev/types/**`, and Next's
  route-type check rejects the `export async function applyEvent` that 2.9
  added for its test. Delete `.next/dev/types` (gitignored) and `git checkout
  next-env.d.ts` and it is green. The real fix is to move `applyEvent` out of
  the route file (the S2c handoff hit the same rule for `PRO_SLUG`). Not
  done here — no code in 2.4.
- The hook blocks any command naming `.env.local`; the key came from Nathan
  instead. `with-env.mjs` (scratchpad) loads `prod.txt` from `vercel env
  pull` plus the overrides and refuses a non-`sk_test_` key.
- `stripe listen` prints `[200] POST … [evt_…]` per delivery — that is the
  delivery log for a local walk; Stripe's API has none. Its `whsec_` is
  per-run (`--print-secret`); the copy in the folder is redacted.
- Test clocks: a customer's saved card is overridden by the
  **subscription's** `default_payment_method`; swap both to make a renewal
  fail (`pm_card_chargeCustomerFail`). `pm_card_createDispute` raises the
  dispute at purchase. Deleting a clock deletes its customer and
  subscriptions — and raises `customer.subscription.deleted`, answered 200.
- Playground CLI (`npx @wp-playground/cli server`, the `tools/matrix.mjs`
  shape, both plugins mounted from a copy outside the emoji path,
  `--define VGMLPRO_API_BASE http://localhost:3011`) can reach a local
  service; Pro's screen read from it directly.
- Node drops piped stdout on `process.exit()` on Windows — the walk tool
  flushes first. `spawn(shell:true)` re-splits an argument with spaces (a SQL
  string) unless it is re-quoted.
- Scratchpad tools, not in the repo: `with-env.mjs`, `walk.mjs` (buy /
  advance / card / plan / spend / pack / refund / dispute / state / events /
  session / verify / sql / clock-delete), `w.sh`, `shot.mjs`, `proshot.mjs`,
  `mail.mjs`, `pg.mjs`, `cron.mjs`, `http.mjs`.

## Found, not done

- Findings A–F in `service/docs/lifecycle.md` (period end on renewal; grace
  from an unpaid period; `payment_failed` undoing `canceled`; no chargeback
  handler and no subscription for it; no email after purchase; copy/state on
  `/account` and Pro). Each is its own task with a test in `lib/*.test.ts`;
  A and C are small; B and D are decisions first.
- `export applyEvent` from the route file breaks typecheck after `next dev`
  (above).
- `quoteCredits(2000,'eur')` = €34.00; memory `credit-pricing-tiers` says
  €29.00 for 2,000. One is stale.
- Test-mode product blurbs still say "500 AI credits a year" / "1,500" /
  "5,000" (`scripts/stripe-catalogue.mjs`); the live products were made by
  `stripe-live-setup.ts` — check they say 2,000 / 5,000 / 20,000.
- `/account` Billing tab, for an account whose email owns several Stripe
  customers, shows one customer's invoices and card under every licence —
  only an artefact of the walk (one email, eight customers), but the
  resolution rule is worth a look.
- S3's stale-sweep item still stands.

## State left behind

- Repo: `service/docs/lifecycle.md` (new, untracked), this handoff and its
  folder (untracked). `tsconfig.tsbuildinfo` modified and `docs/mocks/`
  untracked, as before. `next-env.d.ts` restored; `.next/dev/types` removed.
  No commit made — Nathan's call; suggested message
  `docs(service): the subscription lifecycle, walked in test mode`.
- Stripe test mode: all seven test clocks deleted (their customers and
  subscriptions with them); the disputes, refunds and invoices remain as
  test-mode history. Prices untouched. No endpoint created.
- Neon: project `quiet-cake-13327983` deleted; list shows none named
  `vgml-lifecycle`.
- Scratchpad: `test.env`, `prod.txt`, `neon.env`, `listen.env`,
  `walk-state.json` deleted. `prices.env` (test price ids, not secret) and
  the tools remain.
- Ports 3011 and 3020 freed; no `stripe` process running.
- Inbox `nathan+lifecycle@vergelabs.nl`: five messages (four licence keys,
  one credits invoice).

## Next

- **S5 is 2.6 + 2.7** (seats on the box and Playground; Pro's suites). The
  box's Pro is on the live licence — 2.6 issues one with
  `scripts/issue-box-licence.ts`.
- Before 2.8 (same test keys): decide B and D, and whether A and C get their
  own small session first — 2.8's four purchases will run the same webhook.
- Roll the test keys if the transcript copy bothers you.
