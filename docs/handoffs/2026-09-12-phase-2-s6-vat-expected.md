# Handover — Phase 2, S6: 2.8, invoices for a Tenerife supplier (Opus 5)

2026-09-12, evening, same session as S5/S5b after Nathan's "go ahead" and
the `sk_test_` key he pasted. Done: the four expected outcomes written
before any purchase, eight test-mode purchases in two passes, sixteen PDFs
(ours and Stripe's), the expected-vs-issued table and seven findings in
`service/docs/vat.md` (`4b24dd4`, pushed; docs and scripts only). No tax
position was decided; no rate changed; nothing in live mode was written.

## The rule, as Nathan stated it mid-session

EU rules — inside the EU a consumer pays VAT, a business with a VAT number
is reverse-charged; outside the EU no VAT; the company is based in Spain.
Recorded in `vat.md` beside the memory's Canary nuance; the only row where
they differ is the UK consumer (0 % vs 20 %), left as the accountant's
question.

## What was run

- `service/scripts/vat-walk.mts` (new) — four buyers (NL consumer, NL
  business with `NL123456789B01`, UK consumer, US business with an EIN),
  each the way `app/api/licence/intent/route.ts` buys: customer, tax id,
  subscription with automatic tax, `default_incomplete`, then the
  confirmation with `pm_card_visa`. Refuses a live key. One difference from
  the route, documented: `customer.address` instead of an IP.
- `service/scripts/vat-pdf.test.ts` (new) — renders our PDF per invoice
  from the walk's list; a vitest file because react-pdf's packages export
  only the `import` condition and tsx's CJS loading of `lib/` cannot
  resolve them. Skips itself without `VAT_WALK_DIR` + a test key, so
  `pnpm test` never touches Stripe.
- Pass 1 `noreg`: as live is (no Stripe Tax registration anywhere — read
  off the live account, read-only). Pass 2 `oss`: a **test-mode**
  `ES oss_union` registration (`taxreg_1UEvyBENbkxwktzhluujM311`) so the
  EU-consumer row carries VAT.
- PDFs: `2026-09-12-phase-2-s6-vat/{noreg,oss}-{A,B,C,D}-{ours,stripe}.pdf`.

## The lines

- Pass 1: A `tax 0` (21 % identified, `not_collecting`); B `0`
  (`reverse_charge`, tax id printed); C `0` (20 %, `not_collecting`,
  inclusive — test-mode price option only, live is `exclusive` in every
  currency, checked read-only); D `0` (8.875 % NY, `not_collecting`).
- Pass 2: **A `tax 819`, total `4719`** (21 %, `standard_rated`); B, C, D
  as pass 1.
- Ours renders: `noreg A €39.00 … oss A €47.19, oss B €39.00, oss C £35.00,
  oss D $45.00`; `Tests 1 passed`.
- Live read-only: `tax/registrations` → none; head office La Orotava,
  Calle Leon 29; `price_1UAvUv…` exclusive in eur/gbp/usd.
- `pnpm test` → `Tests 406 passed | 14 skipped (420)`; `pnpm typecheck` →
  exit 0.

## Findings (the full text is `service/docs/vat.md`)

1. Live collects **no VAT from EU consumers** — no registration. Adding
   one is a dashboard action, no code; which one is the accountant's.
2. Our PDF has **no reverse-charge sentence**; Stripe's prints "Tax to be
   paid on reverse charge basis" for the same invoice.
3. Our PDF prints **no rate** next to the VAT amount.
4. Country as a code (`NL`) where Stripe prints `Netherlands`.
5. **Two supplier addresses**: `INVOICE_SELLER_ADDRESS` (Santa Cruz) vs
   Stripe's head office (La Orotava).
6. The UK row: 0 % or 20 % — the accountant's.
7. **Live invoices carry no buyer address** (S2's `AWTXBCFC-0008` confirms:
   "Add a billing name and address to your account…"). A business invoice
   without it is a Phase 3 question for the checkout.

Each is its own task with a test in `lib/invoice.test.ts` (to create); none
fixed here, per the plan.

## S6b — "fix it, you have Stripe access" (same session, later)

- **Live Stripe**: ES `oss_union` registration `taxreg_1UEwp2ENqcXgcnrDKtscslQB`,
  active from 2026-09-12 — the one live write of the day, on Nathan's
  instruction. EU consumers are now charged destination VAT. Spanish
  domestic buyers are still `not_collecting` (needs a `standard` ES
  registration; Canary-vs-mainland IVA is the gestor's question — not done).
- `lib/invoice.ts` — `taxSummary()`: `VAT 21%` / `Sales Tax 8.875%` from the
  expanded `tax_rate`; a reverse charge → `VAT 0%` plus "Tax to be paid on
  reverse charge basis" (Stripe's own sentence) under the total;
  `not_collecting` stays `VAT (none charged)`. `countryName()`: `NL` →
  `Netherlands`. The retrieve expands `total_tax_amounts.tax_rate`.
- `lib/invoice-pdf.tsx` — `taxNote` on `InvoiceData`, one muted line under
  the total.
- `lib/invoice.test.ts` (new) — 7 tests on the two pure functions; written
  before the code.
- Re-rendered PDFs in the folder show it (`oss-B-ours.pdf`: `VAT 0%`,
  the sentence, `Netherlands`; `oss-A-ours.pdf`: `VAT 21% €8.19`).
- `pnpm test` → `Tests 413 passed | 14 skipped (427)`; typecheck 0.
  Commit `ffa392f`; `vercel ls --prod` Ready after 23 s; `/api/build` →
  `{"sha":"ffa392f57f12afed074ed4ca8f44553dbfb7ac8c","ref":"main","deployed":"dpl_39P4VngCMipt5RrVNakXHagWStrw"}`.
- Findings 5–7 stand (two supplier addresses, the UK row, no buyer address
  at checkout).

Nathan also said, mid-session, verbatim: "the reverse charge is out costs and
for a refund its the smount minus rate for credits". Recorded, not built: it
reads as two policy statements (reverse charge at our cost; a refund is the
amount minus the used credits at their rate) and the second is a refund
rule, not an invoice one. Needs one clear sentence from him before it is a
task.

## Mechanics

- react-pdf under `tsx`: `ERR_PACKAGE_PATH_NOT_EXPORTED` for
  `@react-pdf/hyphenate/en-us` — `import`-only exports. Renaming the
  script `.mts` does not help because `lib/` is still loaded CJS. vitest
  resolves it; hence the test file.
- Test-mode tax ids: `eu_vat NL123456789B01` is accepted as `pending` and
  still reverse-charges; `us_ein` is `unavailable`.
- Stripe's test-mode invoice PDF prints the sandbox business profile
  ("94103 San Francisco Cádiz") — not a finding, live prints the real one.

## State left behind

- Stripe **test mode**: eight customers `nathan+vat-{a,b,c,d}-{noreg,oss}@vergelabs.nl`
  with paid yearly subscriptions (`metadata.vat_walk`), and the ES OSS
  registration `taxreg_1UEvyBENbkxwktzhluujM311` still active. Delete from
  the dashboard when read; test mode, no money.
- Live: one write — the ES OSS registration above. Everything else read-only.
- Service card is S6b. Pro card S5b.
- Scratchpad: `test.vars` (the test keys Nathan pasted — they are in this
  transcript too; roll them in the dashboard if that matters), `prod.vars`,
  `development.vars`, `preview.vars`, probes. Delete after the session.

## Next

- Phase 2 tasks 2.1–2.12 are all done or walked. What is left of Phase 2 is
  the findings above and the ones the earlier walks listed, each its own
  task; then Phase 3.7's copy list.
- Pro 1.0.3 (sealed key, shape check) is due; the box runs it, the channel
  does not.
