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

**Sessions.** S1: 2.1 + 2.5. S2: 2.2 (Nathan present, with a card). S3: 2.3.
S4: 2.4 (test mode, test clocks). S5: 2.6 + 2.7. S6: 2.8.

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
