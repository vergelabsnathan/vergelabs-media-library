# Handover — Phase 2, S6 (started in the S5 session): 2.8, expectations only (Opus 5)

2026-09-12, after 2.7. Nathan said go ahead; 2.8's first step — the four
expected outcomes written **before** any purchase — is done and committed
(`service/docs/vat.md`, `e4d3ccb`, pushed; docs only, nothing to serve).
The purchases themselves did not run: **there is no test-mode Stripe key
on this machine**. `scripts/mode-check.mjs` against the Vercel envs:
development `no key`, preview `no key`, production `LIVE`. The plan and the
card both forbid live mode. Stopped there.

## What 2.8 needs from Nathan

- An `sk_test_…` key for the session (not stored in the repo), with the
  test-mode prices set (`STRIPE_PRICE_SINGLE` etc. exist in test mode? — the
  S4 session ran 2.4 in test mode, so the S4 handoff says where they came
  from).
- Then: four checkouts of `single` as the four buyers in `docs/vat.md`, the
  four PDFs from `/account` into the handoff, the "Issued" table filled, and
  any mismatch named as a finding.

## Already visible from the code (findings-in-waiting, in `docs/vat.md`)

- The PDF prints no **rate** — `InvoiceData` has `tax` and `taxLabel` only.
- No **reverse-charge sentence**: row B's label is `VAT (none charged)`, the
  same as a no-VAT sale. The wording is the accountant's.
- Only `customer_tax_ids[0]` is printed.

## State

- Service card is S6 (`.harness/active.json`), scope the 2.8 file list.
- Pro card still says S5b; nothing pending there.
- Test licences 12/13 and the S5b box state as in the S5b handoff.
- Scratchpad: `prod.vars`, `development.vars`, `preview.vars` — delete after
  the session.
