# Phase 2 — Pro is worth paying for, at a hundred paying customers

Tasks for `plans/four-yesses.md` Phase 2. The service repo is
`../service` (Next.js on Vercel, Postgres on Supabase `pbyqedpjhbqeqeytblle`
as role `vgml_media` — memory `service-fresh-database`), the Pro plugin is
`../pro`. Written 2026-09-11 from a survey; every path named exists.

**State on 2026-09-11.** Stripe is **live**; Nathan has made real payments.
Plans in `lib/licence.ts`: `single` (1 site, 2,000 credits/yr), `five` (5,
5,000), `agency` (unlimited, 20,000), `lifetime`. Credit packs priced in
`lib/credits.ts` (memory `credit-pricing-tiers`). The webhook route handles
`checkout.session.completed`, `customer.subscription.{created,updated,deleted}`,
`invoice.paid`, `payment_intent.succeeded`, `invoice.payment_failed`
(`app/api/stripe/webhook/route.ts`). `scripts/webhook-check.mjs` lists the
registered endpoints from Stripe's side. `spendCredits()` in `lib/store.ts:853`
takes `select … for update` before it reads the balance, and dedupes on
`source_id`. `pro/tests/` holds one file (`api-base.php`). `lib/invoice.ts`
renders the invoice; `lib/taxid.ts` guesses a tax id's type. 365 service tests
green (`pnpm test`), typecheck clean.

**Model.** Opus 5 throughout. 2.2 and 2.4 are walks, not builds — one task
per session, because each waits on Stripe and on a person.

**Sessions.** S1: 2.1 + 2.5 (done 09-12). **S4b: 2.12** (the walk's four defects, before 2.8). S2: 2.2 (done 09-12, Nathan present,
with a card — handoff `docs/handoffs/2026-09-12-phase-2-s2-buyer-walk.md`).
S2b: 2.9 + 2.10 — the two money defects the walk found (done 09-12, live;
handoff `docs/handoffs/2026-09-12-phase-2-s2b-refund-and-first-invoice.md`).
**S2c: 2.11.** S3: 2.3. S4: 2.4 (test mode, test clocks). S5: 2.6 + 2.7.
S6: 2.8. The walk's copy findings go to Phase 3.7, listed in the handoff.

**Stop points (Nathan).**
- 2.2 — a real card, and his presence for the walk. The refund at the end is
  real money back.
- 2.3 — 100 pictures through the screen ≈ €0.50 at €0.00483/image. Say it,
  then run.
- 2.4 — runs in **test mode**; needs a test-mode key on the machine for the
  session (`scripts/mode-check.mjs` says which mode a key is). Nathan supplies
  it; it is not stored in the repo.
- 2.8 — the four invoices are test-mode purchases with four customer profiles;
  the VAT position is memory `canary-islands-vat-position` and any doubt is a
  question to Nathan's accountant, not to a model.
- Plan inclusions (2,000 credits inside €39) — priced, not decided; not a
  blocker for any task here.

**Gates, as commands.**
- `cd ../service && pnpm test && pnpm typecheck` — green.
- `node scripts/webhook-check.mjs` — the live endpoint listed, every handled
  event subscribed.
- The seven screenshots of 2.2 in the handoff.
- `wp eval-file pro/tests/licence.php --allow-root` on the box — green.

**Spend.** 2.3 ≈ €0.50 (said before it runs). 2.2 is one real €39 charge and
its refund. Nothing else reaches a model; test-mode Stripe costs nothing.

---

## 2.1 · The live-mode webhook, before any card — Opus

- **Files:** `scripts/webhook-check.mjs` (read-only listing), `scripts/
  stripe-live-setup.ts` (creates products and prices and **no endpoint**),
  Vercel production env (`STRIPE_WEBHOOK_SECRET` — the health check
  `webhook secret` at `app/api/health/route.ts:116` says whether it is set);
  create `docs/runbooks/stripe-webhook.md` in the service.
- **Behaviour:** a live-mode webhook endpoint exists at
  `https://vergelabsmedia.com/api/stripe/webhook`, subscribed to exactly the six event
  types the route handles, with its signing secret matching Vercel
  production. Created through the Stripe dashboard or the API by Nathan or by
  a script with his live key — the script is `stripe-live-setup.ts` extended
  with an idempotent "endpoint" step (reuse one whose `url` matches).
- **Proof:** `node scripts/webhook-check.mjs` prints the endpoint `enabled`
  with the six events; Stripe's delivery log shows a 200 for a test event sent
  from the dashboard ("Send test event" → `invoice.paid`); `curl -s
  https://vergelabsmedia.com/api/health` shows `webhook secret` passing. Mutation: send the
  test event with the secret rotated and the route answers 400 (the
  signature check in the route).
- **Mirror:** `scripts/stripe-live-setup.ts` for the idempotent-by-metadata
  shape; `scripts/webhook-events.mjs` for reading deliveries.
- **Copy:** none.
- **Do not:** subscribe to `*`; create a second endpoint when one exists;
  print the secret anywhere (`mode-check.mjs` is the pattern: never the key).

## 2.2 · The buyer walk — Opus, Nathan present

- **Files:** none change. `docs/handoffs/` gets the seven screenshots.
- **Behaviour:** as a stranger with a real card: buy `single` at €39 from the
  live site; receive the licence email; paste the key into a real WordPress
  (the box is fine: `Media → Licence`); see it activate; download Pro from
  `/account` and install it; open the invoice from `/account`; see `/account`
  show the plan, the key prefix, the credits; then refund from the Stripe
  dashboard and see `/account` and the plugin's Licence screen reflect it
  within the reconcile window (`/api/cron/reconcile`, `17 3 * * *`, or run it
  by hand with its secret).
- **Proof:** seven screenshots in the conversation and in the handoff: the
  charge in Stripe (live), the email in the inbox, the key activating, Pro
  installed and active, the invoice PDF, `/account`, the refund reflected.
  Every one is a picture of the thing, not a component's 200.
- **Mirror:** memory `walk-the-purchase-as-a-buyer` — every component returned
  200 last time and the customer got nothing. `scripts/events.mjs` and
  `scripts/peek-event.mjs` show what Stripe recorded if a step goes quiet.
- **Copy:** none.
- **Do not:** substitute `stripe trigger`, `pm_card_visa`, or a direct call to
  `licence/checkout`; skip the refund (the refund path is the one most likely
  to be wrong); use the WALK50 code unless Nathan says so (memory
  `discount-codes`).

## 2.3 · The promise, kept, on a real library — Opus

- **Files:** none change. The box's AI screen (`admin.php?page=media-ai`).
- **Behaviour:** with **mock off** in the box's AI settings (it is on since
  2026-09-11 — the 1.2 handoff), describe 100 pictures through the screen:
  "Describe new images" after `wp eval` un-describes exactly 100 (the `ai`
  suite's precondition in `tools/verify.mjs` shows the shape; use
  `posts_per_page => 100`). Then: alt text present on those 100
  (`_wp_attachment_image_alt`); a meaning search on the Library grid finds a
  picture by what it shows and not by its filename (pick a word from a new
  caption that appears in no filename — `tests/tree/search-try.php` A5 shows
  the LIKE); the Duplicates screen finds the known pairs
  (`tools/box-fixture.php` plants copies — check its comment for how many).
- **Proof:** three screenshots: the AI screen's finished line for 100, the
  grid with the caption-only search and its hits, the Duplicates screen with
  its pairs. `wp eval` output: `100` rows with `model <> 'mock'` and
  `described_at` today. Cost line in the handoff.
- **Mirror:** the `ai` suite (`tests/tree/ai.mjs`) for the screen's states;
  memory `redescribe-analysis` for what a real described library searched
  like on 2026-09-03.
- **Copy:** none.
- **Do not:** call `vergeml_ai_describe()` from `wp eval` (returns and does
  not persist — $0.48 for zero rows last time); describe more than 100
  without saying the cost; leave mock off afterwards if Nathan wants it on
  (ask).

## 2.4 · The subscription lifecycle, all of it — Opus, test mode

- **Files:** create `docs/lifecycle.md` in the service (one line per event:
  what the customer sees, what email goes, what the plugin does, when it
  stops). Code changes only if a walk finds a gap — then stop and say so.
- **Behaviour:** in test mode with a Stripe **test clock** attached to a test
  customer, walk each row and record it:
  - a renewal a year on succeeds → licence `expires_at` moves, credits
    top up per `PLANS[plan].creditsPerYear`, email sent;
  - a renewal fails (expired test card) → `invoice.payment_failed` handled;
    what `/account` says, what email goes, how long AI features keep working
    (`past_due` in the webhook's status switch at `route.ts:113`) and when
    they stop;
  - cancellation mid-term → access until period end, then `vgmlpro_state()`
    reports it and the plugin's Licence screen says so;
  - upgrade `single → five` and downgrade back → seats and credits before and
    after, prorations as Stripe made them;
  - refund after credits were spent → the ledger (`credit_entries`) shows
    what happens to the negative balance;
  - a chargeback (dispute created in test mode) → the licence's state.
- **Proof:** `docs/lifecycle.md` with six rows, each carrying the Stripe
  event id from the test clock run and a one-line "the customer is told:
  …" with the exact string from the email or the screen. The webhook's
  delivery log at 200 for every event. Any row that has no handler is a
  finding with the event name — not silently a row.
- **Mirror:** `scripts/expiry.mjs` and `scripts/peek-sub.mjs` for reading a
  subscription's state; `lib/reconcile.ts` for what the nightly job
  reconciles; `lib/email.ts` for which emails exist.
- **Copy:** the "customer is told" strings are read off the screens and
  emails, not written here; a missing one is a finding for Phase 3.7.
- **Do not:** run any of this in live mode; write a handler mid-walk (a gap
  is a finding, fixed in its own task with a test in `lib/*.test.ts`); use
  a real card.

## 2.5 · Credits cannot be spent twice — Opus

- **Files:** create `lib/credits-race.test.ts` in the service (vitest, against
  the live DB the way `lib/live-db.test.ts` does).
- **Behaviour:** a licence with exactly 10 credits; 20 concurrent
  `spendCredits( licenceId, 1, uniqueSourceId )` calls fired with
  `Promise.all`; exactly 10 return `ok: true`, 10 return
  `insufficient_credits`, and `select coalesce(sum(delta),0) from
  credit_entries where licence_id = $1` is 0 afterwards — never negative.
  Then the duplicate case: two calls with the same `source_id` — one `ok`,
  one `duplicate`, balance moved by one.
- **Proof:** `pnpm test lib/credits-race.test.ts` green. Mutation: remove
  the `for update` line in a local copy of `spendCredits` and the test goes
  red (balance below zero or more than 10 `ok`).
- **Mirror:** `lib/store.ts:853–906` (the lock, the read, the dedupe);
  `lib/live-db.test.ts` for connecting; `lib/credits.test.ts` for the
  fixture shape.
- **Copy:** none.
- **Do not:** run against the pooler in session mode (memory
  `capacity-plan`: `DB_POOL_MODE=transaction`); leave the test licence and
  its ledger rows behind (delete in `afterAll`).

## 2.6 · Seats are enforced and disputable — Opus

- **Files:** `pro/includes/licence.php` (`vgmlpro_environment()`,
  `vgmlpro_is_production()`, `vgmlpro_refresh()`), service
  `app/api/licence/verify/route.ts` and the activation in `lib/store.ts`
  ("Same reasoning as activate: lock, then read, then write"); create
  `pro/tests/seats.php`.
- **Behaviour:** a `single` licence activated on the box; a second site
  (the box's `/var/www/ms` network, or Playground with Pro installed) is
  refused with the sentence the service returns; that sentence names the
  plan and the seat count. A site whose `wp_get_environment_type()` is
  `staging` activates without taking a seat (Pro 1.0.2 claims it — prove
  it: the licence's activation count unchanged). Deactivating Pro on the
  first site returns the seat (count back by one) — on Playground, not the
  box (deactivation fatals there).
- **Proof:** `pro/tests/seats.php` prints `N/N passed` with the three
  checks reading `vgmlpro_state()` and the service's response body.
  Mutation: define `WP_ENVIRONMENT_TYPE` as `production` on the "staging"
  site and the seat is taken (the test's staging check goes red).
- **Mirror:** `pro/tests/api-base.php` for the runner shape;
  `lib/licence.test.ts` for what the service already asserts about seats.
- **Copy:** the refusal is the service's string — read it off
  `licence/verify` and paste it into the test as the expected text. If it
  does not name the plan and the count, that is a finding for Phase 3.7.
- **Do not:** raise `sitesAllowed` to make a test pass; count staging as a
  seat; deactivate anything on `/var/www/wp`.

## 2.7 · Pro gets tests — Opus

- **Files:** create `pro/tests/licence.php`, `pro/tests/updates.php`; keep
  `pro/tests/seats.php` from 2.6 and `pro/tests/api-base.php`; create
  `pro/tools/verify.mjs` (a cut-down copy of the plugin's: ship each PHP
  file to the box, run `wp eval-file`, count the `N/M passed` line, refuse
  0).
- **Behaviour:** `licence.php`: a valid key activates (`vgmlpro_state()`
  `active`), a malformed key is refused before any request (`isValidKeyShape`
  on the service side; the Pro side's own shape check), an expired licence
  reports `expired` and the AI features are off, the key is sealed at rest
  (the option value is not the key). `updates.php`: with the channel at
  3.16.1 and Pro at 1.0.2, `vgmlpro` offers no update; with a newer version
  in the channel (mock the transient the updater reads) it offers one whose
  `package` URL is the service's signed download.
- **Proof:** `node pro/tools/verify.mjs` runs three suites, each `N/N
  passed`, none 0. Mutation per suite named in its header (e.g. store the
  key unsealed and `licence.php` goes red).
- **Mirror:** `tools/verify.mjs` `runPhp()` and `countedChecks()` in the
  plugin; `tests/copy/voice.php` for a PHP suite's counter shape
  (`$GLOBALS`, never `global` — memory `suites-that-cannot-fail`).
- **Copy:** none.
- **Do not:** test against the live licence Nathan uses (issue one with
  `scripts/issue-box-licence.ts`); let a suite pass with `0/0`.

## 2.8 · Invoices are correct for a Tenerife supplier — Opus

- **Files:** `lib/invoice.ts`, `lib/invoice-pdf.tsx`, `lib/taxid.ts`,
  `scripts/tax-behavior.mjs`, `scripts/oss-return.mjs`; create
  `docs/vat.md` in the service.
- **Behaviour:** four test-mode purchases of `single`: a Dutch consumer; a
  Dutch business with a valid VAT number (`NL…B01`); a UK consumer; a US
  business. For each, the invoice PDF from `/account` and Stripe's own
  invoice: the tax line, the rate, the customer's tax id where given, the
  supplier's address, and the reverse-charge or no-VAT wording where it
  applies. The expected values come from memory
  `canary-islands-vat-position` (non-EU supplier: destination VAT from the
  first euro for EU consumers; Stripe will not run the OSS scheme by
  itself) — write the expected four outcomes in `docs/vat.md` **before**
  running, then compare.
- **Proof:** four invoice PDFs in the handoff, and a table in `docs/vat.md`:
  expected vs. issued per row. Any mismatch is a finding with the row named;
  the fix is a separate task with a test in `lib/invoice.test.ts` (create).
- **Mirror:** `scripts/tax-behavior.mjs` for what Stripe Tax is configured
  to do; `scripts/backfill-invoices.ts` for how invoices are produced.
- **Copy:** the invoice's own strings — "VAT (none charged)" is at
  `lib/invoice.ts:62`; any new wording is Nathan's accountant's, not ours.
- **Do not:** decide a tax position in the session; change rates; run in
  live mode.

---

## Found by the buyer walk (2026-09-12) — added after S2

## 2.9 · A refund ends the licence — Opus

- **Files:** `app/api/stripe/webhook/route.ts` (a new `case 'charge.refunded':`
  in `applyEvent`); `lib/store.ts` only if no existing function cancels a
  licence by subscription or by customer (`upsertSubscription` with status
  `canceled` exists — use it); the live endpoint's event list, through
  `scripts/stripe-live-setup.ts`'s `endpoint()` step (it reads the route's
  `case` lines, so the new case is enough) and `node scripts/webhook-check.mjs`
  after; create `lib/refund.test.ts`.
- **Behaviour:**
  - `charge.refunded` with `charge.refunded === true` (a full refund) on a
    charge whose invoice belongs to a subscription: the subscription is
    cancelled at Stripe immediately (`subscriptions.cancel`) and the licence
    row goes `canceled` in the same event — the customer sees `/account`
    Canceled and Pro's next check `reason: canceled`, without a second
    dashboard click. Cancelling at Stripe raises `customer.subscription.deleted`,
    which the existing case handles again, harmlessly.
  - a full refund of a lifetime licence's payment intent: the licence goes
    `canceled`.
  - a full refund of a credit pack: a `refund` ledger row of `-pack` keyed on
    the refund id, so the ledger says why.
  - a partial refund: nothing, logged with the charge id — 2.4 decides.
  - the event is idempotent: a redelivery finds the licence already canceled
    and does nothing (`processed_events` already refuses the duplicate at the
    top of the route).
- **Proof:** `pnpm test lib/refund.test.ts` green: `applyEvent` (export it, or
  the piece it dispatches to) with a constructed `charge.refunded` event
  against PGlite the way `lib/credits.test.ts` fixtures do; asserts the
  licence status, the Stripe cancel call (a stub), and the credit-pack ledger
  row. Then live: `node scripts/webhook-check.mjs` prints `OK … enabled with
  the 8 handled events`. Mutation: delete the `case 'charge.refunded'` line
  and the test goes red on status.
- **Mirror:** `case 'customer.subscription.deleted'` at `route.ts:349` for the
  cancel shape; `case 'payment_intent.succeeded'` for telling a lifetime from
  a pack by `metadata.kind` / `plan`; the 2.2 handoff for the exact event
  ids of a real refund (`evt_3UEmWdENqcXgcnrD19lKgVmf`).
- **Copy:** none in this task. A "your refund is done, the licence is closed"
  email is a Phase 3.7 string — do not write one.
- **Do not:** handle `refund.created` as well (one event, one path);
  cancel on a partial refund; touch the seat rows (the customer may
  reconnect if they buy again); roll the webhook secret.

## 2.10 · The first invoice is granted once — Opus

- **Files:** `app/api/cron/reconcile/route.ts` (the "paid invoice we never
  saw" loop at ~95–99), `app/api/stripe/webhook/route.ts` (`case
  'invoice.paid'`, the existing-row branch at ~430–437), `lib/reconcile.ts`
  (a pure `isRenewalInvoice(invoice)` — `billing_reason === 'subscription_cycle'`),
  `lib/reconcile.test.ts` (extend).
- **Behaviour — the cause:** a Payment-Element subscription is issued in
  `invoice.paid` with its first-year grant keyed `sub:<subscription>` as
  `initial`; `credit_entries_source_key` is unique on `(licence_id,
  source_id, reason)`, so the nightly reconcile's grant for that same first
  invoice, keyed `in_<invoice>` as `renewal`, is a different row. Every
  subscription sold through the site gets its first year twice, the night
  after purchase — licences 7, 8 (09-04 03:17) and 11 (09-12) prove it.
  A redelivered first `invoice.paid` would do the same through the webhook's
  renewal branch.
  - reconcile grants only for invoices whose `billing_reason` is
    `subscription_cycle`; the `subscription_create` invoice is the webhook's
    issue path and never a renewal;
  - the webhook's existing-row branch of `invoice.paid` uses the same
    predicate;
  - the three wrong rows: `delete from credit_entries where reason =
    'renewal' and source_id in ('in_1UBhihENqcXgcnrDfmNenL8e',
    'in_1UBhj4ENqcXgcnrDqs6t9thw', 'in_1UEmWcENqcXgcnrDAkjxMTaP')` — **Nathan
    runs it** (7 and 8 are his own licences; 11 is the walk's, canceled).
- **Proof:** `pnpm test lib/reconcile.test.ts`: `isRenewalInvoice` true for
  `subscription_cycle`, false for `subscription_create`, `manual`,
  `subscription_update`. Then the live line, the morning after deploy:
  `node scripts/…` or SQL — no licence has two grants dated within its first
  48 hours (`select licence_id, count(*) from credit_entries where reason in
  ('initial','renewal') group by 1 having count(*) > 1` returns only
  licences older than a year). Mutation: return `true` unconditionally from
  `isRenewalInvoice` and the unit test goes red.
- **Mirror:** `statusFromStripe` in `lib/reconcile.ts` — "duplicated
  deliberately from the webhook: reconciliation must reach the same
  conclusion the webhook would" — the predicate follows the same rule, one
  function used by both.
- **Copy:** none.
- **Do not:** change the ledger's unique index; re-key the `initial` grant
  (`checkout_session_id` is the issue idempotency key and is unique on
  `licences`); delete the rows from the session — hand Nathan the SQL.

## 2.11 · A buyer can download Pro — Opus

- **Files:** `app/account/page.tsx` (the Licence tab: a "Download Pro
  <version> (zip)" control; the two `href="/#install"` "Download Pro →"
  buttons at ~1435 and ~1773 point at it instead), create
  `app/api/account/download/route.ts` (session → `whoseLicence` →
  `entitlement` → `signDownload` → 302 to `/api/plugin/download?token=…`);
  `lib/email.ts` `sendLicenceEmail` — its button already says "Download Pro
  from your account" and links `/account`; leave the string, it becomes true.
- **Behaviour:** signed in as the owner of an entitled licence, the Licence
  tab offers the current Pro release (`findRelease('vergelabs-media-library-pro')`
  from `PLUGIN_RELEASES`) and clicking it downloads the zip through the
  existing signed route, same token TTL as the updater; a canceled or expired
  licence shows the control disabled with the plan's own status word beside
  it (no new copy); a signed-out visitor gets `/account`'s sign-in.
- **Proof:** a vitest for the new route with a minted session (`lib/live-db.test.ts`
  shape, or the `vergelabs.sessions` insert the account smoke used) asserting
  302 to a `/api/plugin/download?token=` URL whose token `verifyDownload`
  accepts for that licence, and 403 for a licence the session does not own;
  then the picture: the buyer's `/account` Licence tab with the control and
  the zip landing in the browser's downloads (Playwright `download` event,
  `suggestedFilename()` ends `.zip`). Mutation: drop the `whoseLicence`
  check and the 403 assertion goes red.
- **Mirror:** `app/api/invoice/route.ts` — session-gated, licence-checked,
  streams a file; `app/api/plugin/update/route.ts:98–111` for the exact
  `signDownload` claim shape and `DOWNLOAD_TTL_SECONDS`.
- **Copy:** the control's label is the one string, and it is the plan's:
  `Download Pro <version> (zip)`.
- **Do not:** serve the zip from the account route itself (one download
  route, one token check); expose a link that does not expire; put the
  download on `/order` (the key is enough there — the account is where the
  licence check lives).

---

## Found by the lifecycle walk (2026-09-12, S4) — added after 2.4

## 2.12 · The webhook survives a year — Opus

Four defects `service/docs/lifecycle.md` found (A–D), fixed together because
they are four branches of one `applyEvent` and 2.8 sends four more purchases
through it. Decisions taken 2026-09-12 (Nathan: "let's continue" on the
recommendation): the grace after a failed card is counted from the failed
invoice's due date; a lost dispute is treated exactly like a full refund.

- **Files:** `app/api/stripe/webhook/route.ts` (the `invoice.paid`
  existing-row branch, the `customer.subscription.*` branch, the
  `invoice.payment_failed` case, a new `case 'charge.dispute.closed':` that
  shares the refund path), `lib/reconcile.ts` (a pure
  `periodEndToStore(status, periodEnd)`), `app/api/cron/reconcile/route.ts`
  (uses it), `lib/reconcile.test.ts` (extend); create `lib/lifecycle.test.ts`.
  Then the live endpoint's event list through `ENDPOINT_ONLY=1
  scripts/stripe-live-setup.ts` **after the build is serving**, and
  `node scripts/webhook-check.mjs` → `OK … 9 handled events`.
- **Behaviour:**
  - **A** — a renewal's `invoice.paid` stores the *new* period's end: the
    latest `lines.data[].period.end` on the invoice, never
    `invoice.period_end` (which is the period just closed — `in_1UEsac…`
    carried 2027-09-12 there and 2028-09-12 on the line). No lines → the
    period is left as it is.
  - **B** — on `invoice.payment_failed` the licence goes `past_due` with
    `current_period_end` = the failed invoice's `period_end` (the renewal's
    due date), so `entitlement()`'s seven days run from the day the card
    failed. A `past_due` subscription's own `current_period_end` — which
    Stripe rolls a year forward at the failed attempt — is **not** stored:
    `periodEndToStore('past_due', …)` is null, in the webhook's
    subscription branch and in the nightly reconcile alike. The next paid
    invoice (A) or an `active` subscription event moves it forward again.
  - **C** — `invoice.payment_failed` never lowers `canceled`: the update
    carries `and status <> 'canceled'`. Delivered after
    `customer.subscription.deleted`, it changes nothing.
  - **D** — `charge.dispute.closed` with `status: 'lost'` does what
    `charge.refunded` does for a full refund: the subscription is cancelled
    at Stripe and the licence goes `canceled`; a lifetime licence goes
    `canceled`; a credit pack gets a `refund` ledger row of `-pack` keyed on
    the dispute id. `won`, `warning_closed` and every other status: nothing.
    `charge.dispute.created` stays unhandled (a dispute can be won).
  - Everything else in `applyEvent` unchanged; every write still inside the
    event transaction.
- **Proof:** `pnpm vitest run lib/lifecycle.test.ts lib/reconcile.test.ts`
  green: A (the line's period wins), B (past_due + due date; a past_due
  subscription event leaves the period alone; `entitlement()` at due + 8
  days is `expired`), C (deleted then failed → still canceled), D (lost on a
  subscription → `subscriptions.cancel` called once and `canceled`; lost on
  a pack → one `-2000 refund` row keyed `dp_…`, twice delivered; won →
  nothing). Then `pnpm test && pnpm typecheck` green, deploy verified by
  commit, `webhook-check.mjs` `OK`. Mutation: restore
  `fromUnix(invoice.period_end)` in A and the line-period test goes red;
  drop `and status <> 'canceled'` and C goes red.
- **Mirror:** `lib/refund.test.ts` for constructing events against PGlite
  with Stripe stubbed; the `charge.refunded` case for the money-back shape;
  `isRenewalInvoice` for a predicate shared by webhook and reconcile.
- **Copy:** none. The emails (finding E) and the screens (F) are Phase 3.7.
- **Do not:** handle `charge.dispute.created`; touch the seats; change the
  grace length (`GRACE_DAYS = 7`); subscribe the new event before the build
  that handles it is serving (runbook rule); move `applyEvent` out of the
  route file here — that is its own line in the S4 handoff.
