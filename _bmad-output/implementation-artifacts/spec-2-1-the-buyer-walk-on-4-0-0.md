---
title: 'The buyer walk on 4.0.0'
type: 'walk'
created: '2026-09-20'
status: 'ready-for-dev'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: 'e106e20ab17783bdedd4b9e78ed94750c9f1c28f'
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** No customer has bought a plan on the live Stripe account and been walked to a described picture. The only live payment ever was a €0.90 credits top-up on an existing licence (2026-09-01); the lifecycle walk of 2026-09-12 was test mode on a throwaway database. Component tests pass at every layer and have already once delivered nothing (2026-08-22: a key minted and discarded, no email, no zip). What is unproven is the one path a first customer takes on 4.0.0: pay → key → account → Pro zip → connect → describe → credits down → invoice.

**Approach:** One real purchase of `single` by Nathan as a first-time buyer with a fresh email, in **one headed Playwright window** driven by the walk script on this machine: Nathan's fingers touch only the card fields, Pay, and the walk password at registration; the script prints the form's own figure and stops until his go. Each step yields its own artefact — a page read as the customer (screenshot into the repo), an email received, a Stripe object read back, a picture's alt text, the describe response's own credit line — never a status code. The site side runs on the `upg` fixture (4.0.0 on MySQL, Pro installed from the zip Playwright captures off `/account`, never a checked-out tree), one describe, and every fixture state put back from a literal-SQL snapshot. Nothing on the service, Pro or the plugin changes in this story; a break becomes a "found" line with its evidence, not a fix. Fallback if Stripe Radar refuses a card typed into an automated browser: the buy step in Nathan's own browser, the key pasted once.

## Boundaries & Constraints

**Always:** the amount is said before Nathan's go, and the go is given on the checkout form's own figure (`charged`, Stripe's number) before Pay is pressed. The Pro zip is the one `/account` hands the buyer, its sha256 read on the box before install (expected `2a6a7946426f`, the served 1.0.2). One describe, one picture, one credit, on `upg`. The walk licence's seat is released and the key removed from `upg` at the end; `vergeml_ai` (mock 1, empty profile) and the picture's `wp_posts` row (caption), all its `postmeta` (alt, Pro's provenance) and its `vergeml_index` row (the lock) put back from the literal-SQL snapshot. The customer-side artefacts are the proof; production DB rows are optional lines Nathan runs (scripts against the prod DB are blocked here). Stripe is read through a script with the key from `vercel env pull` into a local file; if that is blocked, Nathan reads the subscription and invoice in the dashboard and quotes them. The key lives in the walk script's memory; the `/order` and Licence-tab shots mask the key element before capture; check-ins, docs and the handoff carry the prefix only. The subscription is set to cancel at period end from `/account` in the same window, before the restore.

**Never:** ms2, the real shop, or Nathan's existing licence #6; a discount code (PRIVATE0 on `single` is €0.39, under Stripe's €0.50 floor — recorded, not used); any Stripe write from a script (reads only; the refund or cancel decision is Nathan's, taken in `/account`, never in the dashboard); a second describe; a change to service, Pro or plugin code; a deploy.

## I/O & Edge-Case Matrix

| Step | Input / State | Evidence (the customer's, not a status code) | If it breaks |
|------|--------------|---------------------------------------------|--------------|
| 1 Buy | `/checkout?plan=single` in the walk window, email `nathan+buyer-0920@vergelabs.nl`; Nathan types the card; ES by IP → `eur`, no VAT line | The script prints the form's figure **€39.00** and waits for go; `/order` shows plan Single, the key (masked in the shot), "fulfilled"; Stripe (read): `sub_…` active, `in_…` paid €39.00, `pi_…` succeeded, `metadata.kind=plugin_licence` | the order page polls without fulfilment → the webhook's log line, story stops |
| 2 Licence issued | webhook `invoice.paid` on the first invoice | the licence email in Nathan's inbox (subject and first line quoted; key, plan, `/account` link); `/api/licence/verify` `action:check` → `valid:true, plan:single, credits_remaining:2000, sites_used:0`; optional, Nathan: `licences` row `single/active/1`, ledger `+2000 initial` | no email → Resend's log; no row → `processed_events` |
| 3 Download | register at `/account` in the walk window (Nathan types the walk password; the verification mail's link opened there), Licence tab | `Download Pro 1.0.2 (zip)` → Playwright's download event → bytes on disk → the box; sha256 `2a6a7946426f`; `box-upgrade-site.sh plugin <zip>` prints `1.0.2 active` | 403 `not_entitled` → the entitlement row |
| 4 Connect | key typed by the script on Pro's Licence screen on `upg` (`vgmls22`) | screen 1440×900: **Connected**, Single, 1 / 1, 2,000 credits; verify → `sites_used:1` | `seat_limit` / `not_found` on the screen |
| 5 Describe | row action **Describe with AI** on the snapshotted picture (`admin_post_vgmlpro_describe`) | the picture's `_wp_attachment_image_alt` is a real sentence, not `Mock alt for…`; the media list cell **We wrote this**; the provenance meta names the model; `debug.log` 0 new lines | `vgmlpro_msg=<error code>` in the redirect, `debug.log` line |
| 6 Credits down | after step 5 | the describe response's own `credits_spent:1, credits_remaining:1999` (Pro's notice or the index); verify → `credits_remaining:1999`; `/account` Credit activity: `−1` beside `+2,000` (shot); optional, Nathan: `credit_entries` `delta −1` | balance unchanged → `credit_entries` for the licence |
| 7 Invoice | the subscription's first invoice | Stripe invoice number, `€39.00`, tax line as Stripe wrote it for ES; `/account` Invoices: the row (shot) and `/api/invoice?licence=<prefix>&id=in_…` → our PDF, total `€39.00`, seller block; Stripe's receipt/invoice email if the dashboard sends one (recorded either way) | `no_billing_account` → `stripe_customer` on the row |
| 8 Afterwards and restore | end of walk | `/account` → cancel at period end: the page's reply and the panel's sentence (shot); Pro Licence screen: deactivate → verify `sites_used:0`; key option absent; Pro `inactive 1.0.2`; `vergeml_ai` mock 1; the picture's `wp_posts`, `postmeta` and `vergeml_index` rows equal the snapshot; `debug.log` 0 new Pro lines | a held seat is reported and released by hand before the handoff |

</frozen-after-approval>

## Code Map

- `service/app/checkout/page.tsx` — the form; `startPurchase` → `POST /api/licence/intent` → `charged` is the figure shown; success → `/order?payment_intent=…&payment_intent_client_secret=…`.
- `service/app/api/licence/intent/route.ts:290-353` — `single` is a subscription, `default_incomplete`, automatic tax by IP (`x-vercel-ip-country`), the key derived onto `metadata.licence_key`; `:226-236` a code on a yearly plan becomes a Stripe coupon (`ensureStripeCoupon`) — why PRIVATE0 would go to Stripe as 99 % of €39.
- `service/app/api/stripe/webhook/route.ts:453-522` — `invoice.paid` with no licence row issues it (`issueLicence`, `checkoutSessionId sub:<id>`), queues the licence email; `:524-541` `payment_intent.succeeded` returns early for a subscription plan.
- `service/app/api/order/route.ts` — `fulfilled` = the licence row exists; `key` from `unsealKey` or the intent metadata.
- `service/app/api/account/download/route.ts` — owner + `entitlement()` → 302 to `/api/plugin/download?token=…` for `PRO_SLUG` (1.0.2 in the catalogue).
- `service/app/api/licence/verify/route.ts:52-63, :127-129` — `action` `check|activate|deactivate`; the answer carries `sites_used`, `credits_remaining`.
- `service/app/api/invoice/route.ts` — `GET /api/invoice?licence=<prefix>&id=in_…` → `lib/invoice.ts` PDF, owner-checked.
- `service/lib/licence.ts:59-70, :97-111` — `PLANS.single` = 1 site, 2,000 credits; one credit per image.
- `service/docs/vat.md:63-72` — live tax registration ES `oss_union` since 09-12: EU consumers pay destination VAT, a Spanish buyer is `not_collecting` (so €39.00 from Nathan's IP, ES per `/api/pricing`).
- `pro/includes/describe.php:29-119, :211-248` — `vgmlpro_describe_attachment()` posts the image as base64 to `/v1/describe` with the key and site; the answer carries `credits_spent`, `credits_remaining`, `model`; the handler writes alt, caption (`post_excerpt`) and `vgmlpro_record_provenance()` (`provenance.php:39`, postmeta); `includes/licence.php:292` `/api/licence/verify`.
- `plugin/core/ai-index.php` `vergeml_index_watch_alt` — an alt written outside `vergeml_index_writing()` locks the index row; why the snapshot carries `vergeml_index`.
- `service/lib/licence.ts:345-353` — `normaliseEmail` keeps a `+tag`, so the walk email is its own account.
- `plugin/tools/upg-pro-shots.mjs` — Playwright on `upg` as `vgmls22`, 1440×900, activate/deactivate with restore of key and state (the mirror for steps 4, 5, 8 — it uses the test key; the walk uses the buyer's).
- `plugin/tools/box-upgrade-site.sh plugin <zip>` — prints sha256 and the installed slug's version/status (A-3).
- `plugin/tools/box-shop-untree.php` — literal-SQL freeze and restore (the mirror for step 8's snapshot).
- `service/docs/lifecycle.md:113-124` — how the last walk read Stripe, Resend and `/account`.

## Tasks & Acceptance

**Execution:**
- [ ] T1 Prepare: `node tools/deploy.mjs --check` in `plugin/`; `upg` as found (4.0.0 active, Pro 1.0.2 inactive, key absent, mock 1, `wp config get VGMLPRO_API_BASE` absent so Pro talks to `ai.vergelabs.nl`) recorded; the literal-SQL snapshot of one picture (`wp_posts` row, all `postmeta`, `vergeml_index` row) and of `vergeml_ai`; `health` → `free 4.0.0, pro 1.0.2`, `stripe live mode`; the walk script `tools/buyer-walk.mjs` (headed Playwright, stages with a stop before Pay, shots with the key masked) written and its dry stages run against `/checkout` up to the figure. Nothing spent.
- [ ] T2 Buy: the walk window opens `/checkout?plan=single` with the walk email; the script prints the form's figure and stops → **go on €39.00** → Nathan types the card, Pay → `/order` polled to "fulfilled", key read by the script, shot with the key masked. Then the read-only Stripe script from `service/` (`sk_live` via `vercel env pull` into a local file, never printed) lists the subscription, invoice, intent and their metadata; fallback: Nathan quotes them from the dashboard.
- [ ] T3 Licence and email: verify `check` with the key; Nathan quotes the licence email's subject and first line; registration at `/account` in the walk window (Nathan types the walk password; the verification link opened in the window), Licence tab shot.
- [ ] T4 Download and install: `Download Pro 1.0.2 (zip)` captured by Playwright → the box → sha256 → `box-upgrade-site.sh plugin` → `1.0.2 active`.
- [ ] T5 Connect and describe: the script logs in to `upg` as `vgmls22`, types the key on the Licence screen, shot; **Describe with AI** on the snapshotted picture; the media list cell shot; the alt, caption and provenance read back by SQL; `credits_spent`/`credits_remaining` from the response. Cost said first: 1 credit of the 2,000 and ~€0.002 of model.
- [ ] T6 Credits and invoice: verify → 1999; `/account` Credit activity and Invoices shots; our PDF via `/api/invoice` (walk session) read for its number and total; Stripe's invoice fields from the T2 script.
- [ ] T7 Afterwards, restore, record: `/account` → cancel at period end, reply and panel shot; deactivate on the Licence screen, verify `sites_used:0`, key removed, Pro inactive, mock 1, the picture's three rows put back from the snapshot and diffed to 0, `debug.log` diffed; the walk written into `service/docs/buyer-walk-2026-09-20.md` (the matrix's rows with the artefacts named, the key's prefix only) and `deferred-work.md` for anything found (PRIVATE0 under the €0.50 floor already).

**Acceptance Criteria:**
- Given live Stripe and a fresh buyer email, when Nathan pays €39.00 on his go, then the `/order` page shows the key and "fulfilled", the licence email arrives, and `verify` says `single / 2000 / 0 sites`.
- Given the walk licence, when its holder downloads Pro from `/account`, installs it on `upg`, connects and describes one picture, then the picture carries a real alt, Pro's cell says "We wrote this", and `verify` says `1999`.
- Given the first invoice, when read from Stripe and as our PDF, then both say €39.00 with the same number and the tax line Stripe wrote.
- Given the end of the walk, when `upg` is read, then it equals the T1 record and the seat is free.

## Implementation Notes

## Spec Change Log

- 2026-09-20 Nathan's four calls: €39.00 with no code; buyer `nathan+buyer-0920@vergelabs.nl` (a first-time account); the site is `upg`; afterwards the subscription is cancelled at period end from `/account` (no refund).
- 2026-09-20 pre-mortem (Advanced Elicitation, applied): one headed Playwright window instead of Nathan's browser; DB rows optional, customer artefacts the proof; the snapshot widened to `wp_posts` + `postmeta` + `vergeml_index`; the key masked in shots; cancel at period end inside T7; the Stripe-read fallback; `VGMLPRO_API_BASE` checked in T1. Ruled out by reading: `normaliseEmail` keeps the tag; Pro sends bytes, not a URL.

## Review Triage Log

## Verification

**Commands:**
- `node tools/deploy.mjs --check` in `plugin/` — expected: up to date
- the T2 read-only Stripe script — expected: one subscription `active`, one invoice `paid` `3900 eur`, `metadata.kind plugin_licence`
- `/api/licence/verify` `check` before and after the describe — expected: `credits_remaining` 2000 → 1999, `sites_used` 0 → 1 → 0
- literal-SQL read of the picture and `vergeml_ai` after T7 — expected: 0 differences from T1
- Cost: **€39.00** on Nathan's card to VergeLabs (net cost Stripe's fee, ≈ €0.84); 1 credit; ~€0.002 of model. Said before T2 and T5; nothing else spends.
