# Handover — Phase 2, session S2: task 2.2, the buyer walk (Opus 5)

Run on 2026-09-12, 08:29–10:35, in `../service`, Nathan present, a real card,
Stripe live. **The walk is complete: purchase, licence, activation, Pro
delivered, invoice, account, refund, cancellation — all as pictures, in
`2026-09-12-phase-2-s2-buyer-walk/`.** No code changed. Four defects found,
one of them costing money on every sale; none fixed here (the profile says a
walk records, its own task fixes).

## How it deviated from the plan, and why

- **Plan `five` at €0.79, not `single` at €39.** Nathan did the cart and the
  card himself in the walk's browser window and applied his own **PRIVATE0**
  (99 % off; cart said €79 → €0.79). Every amount below is therefore not a
  customer's amount; the *paths* are the customer's. The refund is €0.79.
- **The buyer address is fake.** `walkerdevid@vergelabs.com` has no mailbox.
  So screenshot 2 is the licence email **as Resend sent it** (its HTML,
  rendered), not an inbox; Resend marks it `bounced`, and the later account
  confirmation `suppressed`. The confirmation link was taken from Resend's
  copy and opened by me — what the buyer would have clicked.
- **"Download Pro from `/account`"** does not exist (finding 3). Screenshot 4
  is the path that does: the box's Pro 1.0.1 → 1.0.2 through the buyer's
  licence on the Plugins screen.
- **The Licence screen is `Settings → Media Library Pro`**
  (`options-general.php?page=vgmlpro-licence`), not `Media → Licence`.
- The refund alone reflects nowhere (finding 1), so a second dashboard action
  — cancel the subscription immediately — was added to get screenshot 7's
  "reflected" half. Nathan: "I certainly hope I do not have to do this by
  hand in the future" — that is finding 1's fix.

## The seven, in order

| # | The thing | File |
|---|---|---|
| 1 | The charge in Stripe, live — receipt 2061-6011, €0.79 paid, invoice AWTXBCFC-0008, Mastercard ·0957, LAUNCH2026 −€78.21 | `02-stripe-receipt.png` (and `/order` with the key in full: `01-order.png`) |
| 2 | The licence email as sent — key, "Up to 5 sites, 5,000 AI credits a year", renews 12 September 2027 | `02-licence-email-as-sent.png` |
| 3 | The key activating on the box — Connected, Plan Five, Sites 1 / 5, Credits 5,000 | `03-box-licence-activated.png` |
| 4 | Pro installed and active — Plugins screen, Version 1.0.2, "Updated!", package served through the buyer's licence | `04-plugins-pro-1.0.2-active.png` |
| 5 | The invoice PDF from `/account` → Billing — AWTXBCFC-0008, €79 − €78.21, VAT (none charged), total €0.79 | `05-invoice-pdf.png`, `invoice-AWTXBCFC-0008.pdf` |
| 6 | `/account` — Five …YMME, 5,000 credits, 1 / 5 sites, renews 12 Sept 2027, connected site 46.225.66.194 | `06-account-overview.png` |
| 7 | The refund reflected — **after refund + reconcile: nothing changed** (`07a-account-after-refund-only.png`, `07c-box-after-refund-only.png`); **after the subscription was cancelled**: `/account` Plan Five *Canceled*, the box's Licence screen back to the empty key field | `07-account-after-cancel.png`, `07-box-after-cancel.png` |

## What Stripe and the database recorded (the chain, timed)

- `pi_3UEmWdENqcXgcnrD1P7BEv4d` succeeded, live; charge
  `ch_3UEmWdENqcXgcnrD1a2k1ini`, card ES Mastercard ·0957; customer
  `cus_VFH41LMplxD1bu`; invoice `in_1UEmWcENqcXgcnrDAkjxMTaP` paid, total
  79, discount 7821; subscription `sub_1UEmWcENqcXgcnrDkSKrdo94` active to
  2027-09-12T08:43:06Z, metadata `plan: five, code: PRIVATE0, code_id: 3,
  licence_key: …`.
- Events, all `pending_webhooks 0`: `customer.subscription.created`
  08:43:07 (Nathan opened checkout at 08:43, paid at 09:42),
  `payment_intent.succeeded` 09:42:36, `invoice.paid` and
  `customer.subscription.updated` 09:42:37.
- **Licence #11** issued at 09:42:39 (`five`, prefix `YMME`, 5 seats,
  `initial +5000` keyed `sub:sub_1UEmWc…`); licence email out at 09:42:39.
- Account created 09:50:59; confirmation link signed the buyer in directly.
- `/api/plugin/update?slug=vergelabs-media-library-pro&version=1.0.1&key=…&site=http://46.225.66.194`
  → `{update: true, version: "1.0.2", package: "…/api/plugin/download?token=…"}`.
- Refund `re_3UEmWdENqcXgcnrD1lDwtuO2` succeeded 10:16:34 (€0.79);
  `charge.refunded` and `refund.created` raised — **not subscribed, not
  handled**. Subscription still `active`. Reconcile by hand (`CRON_SECRET`
  from a pulled env, deleted after): `{"checked":3,"repaired":[],…}`.
- Cancel immediately 10:21:57 → `customer.subscription.deleted` delivered →
  licence #11 `canceled` in the DB. Pro's own check (`vgmlpro_refresh`,
  12-hour cache, forced by `wp eval`): `valid: false, reason: canceled`.

## Findings — ranked

1. **A refund does not reach the licence.** Nothing handles
   `charge.refunded` / `refund.created`; `/api/cron/reconcile` re-reads only
   the subscription's *status*, which a refund does not change. After the
   refund the customer keeps `/account` Active, 10,000 credits, the box
   Connected, the invoice listed as paid. Only a separate dashboard
   cancellation ended it. *Fix (its own task, test in `lib/…test.ts`):*
   handle `charge.refunded` in the webhook — for a full refund of a
   subscription's invoice, cancel the subscription and the licence, mark the
   credits; subscribe the endpoint (`stripe-live-setup.ts` `endpoint()` +
   `webhook-check.mjs` read the route's `case` lines, so adding the case is
   enough).
2. **Every subscription's first-year credits are granted twice — the night
   after purchase.** `invoice.paid` issues a Payment-Element subscription and
   grants `initial` keyed `sub:<sub>`; reconcile then lists the same first
   invoice as "a paid invoice we never saw" and grants `renewal` keyed
   `in_<invoice>`. Proven on **licence 7** (2,000 → 4,000, cron 09-04 03:17),
   **licence 8** (5,000 → 10,000, same night) and **licence 11** today
   (5,000 → 10,000 the moment reconcile ran; `/account` shows two "+5,000"
   lines). ≈ €10 of model cost per Five licence, ≈ €40 per Agency. *Fix (own
   task, test in `lib/reconcile.test.ts` or a live-DB test):* key the initial
   grant on the first invoice's id, or have reconcile skip the invoice that
   issued the licence. Licences 7 and 8 are Nathan's own; whether to remove
   their extra rows is his.
3. **There is no Pro download for a buyer.** `/account`'s two
   "Download Pro →" buttons and the email's "Download Pro from your account"
   all go to `/#install` → `/install`, the free plugin's docs (GitHub
   release). The only signed package delivery is `/api/plugin/update`, which
   only an already-installed Pro asks. A stranger with a fresh site cannot
   get Pro at all — the 08-22 last-inch, still open. *Fix:* a "Download Pro
   (zip)" on the Licence tab that signs a download token for the signed-in
   owner's licence (`signDownload` in `lib/updates.ts` already exists).
4. **Copy that contradicts itself across the chain** (Phase 3.7):
   - Where to paste the key: email says *Settings → Media → VergeLabs*;
     `/order` says *Settings → VergeLabs Media Library Pro*; `/account` says
     *Media Library → Licence*; the screen is *Settings → Media Library Pro*.
   - Email: "Keep this email — the key is not stored anywhere we can read it
     back." `/order`: "It is also in the email, and always in your account."
     `/account` shows the prefix only.
   - After cancellation `/account` shows Plan *Canceled* beside "Renews
     12 September 2027", "Credits bought 10,000", and still offers "Cancel
     subscription", "Switch to …" and "Download Pro →".
   - Pro's Licence screen after a cancelled licence shows the bare key field
     and "Connect" — state carries `reason: canceled`, the screen says
     nothing.
   - Billing: "No card saved yet" — the subscription was created with
     `save_default_payment_method: 'on_subscription'`; either the card was
     not attached or the tab reads the wrong place. Renewal risk; 2.4 will
     meet it.
   - Two ways in on Pro's Licence screen (paste a key, "Connect this site"
     handshake) shown as equals — Nathan asked whether that confuses owners.
     One primary action and the paste folded under "Have a key?" is the
     shape; copy task.
5. `vgmlpro_licence_key` on the box holds the key **in plain text**
   (`vergeml_ai.license_key` is sealed `v1:…`). Plan 2.7 expects "the key is
   sealed at rest (the option value is not the key)" — it will find this.

## State left behind

- **Stripe live:** customer `cus_VFH41LMplxD1bu` with the refunded invoice
  and the cancelled subscription. Left as a real record. PRIVATE0's use
  count went 3 → 4.
- **DB:** user `walkerdevid@vergelabs.com` (verified), customer, licence 11
  `canceled` with 10,000 credits on the ledger (5,000 of them finding 2),
  one activation row for `46.225.66.194`, one `discount_redemptions` row.
  Not deleted — say so if you want it gone.
- **Box** `/var/www/wp`: agency licence #5 restored from the backup and
  re-activated (`valid, agency, sites_used 1, 18,440 credits`); backups and
  `box-ui-user.sh` removed from `/root`; session admin `vgml-walk` deleted.
  **Pro is now 1.0.2** (was 1.0.1) — the walk's upgrade, left in place.
  `vgml-smoke` no longer exists on the box (memory said it did); make a
  session admin with `tools/box-ui-user.sh` instead.
- **Scratchpad:** `prod.env`, both passwords and the Resend HTML deleted; the
  Chromium profile `buyer-profile/` (buyer session cookie) is in the
  session scratchpad — the hook refuses `rm -rf`, so it stays until the
  scratchpad is cleared.
- **Cost:** €0.79 charged and refunded; Stripe keeps its fee on the
  original (≈ €0.26). No model call, no credit spent.

## Gates

- `pnpm test`, `pnpm typecheck`: not run — no code changed this session.
- `node scripts/webhook-check.mjs`: not re-run; the handled events all
  delivered `pending_webhooks 0` above, which is the same proof.
- The seven screenshots: in the folder, listed above.

## Mechanics that worked (for the next walk)

- One headed Chromium via Playwright's `launchPersistentContext` with
  `--remote-debugging-port=9222` in a background task; every step a short
  script over `connectOverCDP`. Nathan typed the card in that window; I
  screenshotted from it before and after. Scripts were in the scratchpad.
- Box: `box.mjs` wrapper (`spawnSync('ssh', ['-i', path.join(homedir,
  '.ssh','hetzner_vgml'), …])`); `export MSYS_NO_PATHCONV=1` in the Bash
  call itself for `scp` targets, not only in the child env.
- Resend's API lists sent mail with `last_event` and returns the HTML —
  the substitute for an inbox that does not exist.
- Invoice PDF → PNG: `python -c "import fitz …"` (PyMuPDF is on the
  machine).

## Next

- **S3 as planned is 2.3** (100 pictures, ≈ €0.50). But findings 1 and 2
  are money on every sale and should be their own tasks before 2.4 walks the
  lifecycle: a task "refund cancels the licence" (webhook case +
  `webhook-check`) and a task "the first invoice is granted once" (reconcile
  key + test), each with the proof line in the plan's six-field shape.
  Finding 3 (Pro download in `/account`) is the other blocker for a real
  stranger. Recommend those three before 2.3.
