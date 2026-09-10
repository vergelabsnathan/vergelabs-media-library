# Plan — four yesses

Written 2026-09-10, after an assessment that answered four questions with
"probably", "unproven", "not for strangers" and "no". This plan turns each one
into a yes, and each yes has a number or an artefact behind it rather than an
opinion.

**The four questions, verbatim:**

1. Is the free plugin ready for use by 300 people?
2. Is paying for Pro and getting it running worth it — does it fulfil what is promised?
3. Is the UI/UX ready enough to ship?
4. Is the plugin and the system behind it ready for market?

**The rule for this plan:** a yes is something that can be shown to somebody
else. A suite output, a screenshot, an email in an inbox, an alert that fired.
Not a belief.

## What is already true, 2026-09-10

Measured today, so the plan starts from facts rather than from the last handoff:

- Plugin Check: **2 errors**, neither a submission blocker. The zip is 135 files, no hidden ones.
- The service: **365 tests green**, typecheck clean, live, licence-gated.
- The plugin: **21 of 33 suites green**; 10 red and uncharacterised; 2 skipped.
- Counts at 251,000 attachments: **8.6ms cold**, was 10,038ms.
- Pricing: re-rated on measured cost (€0.00483/image) and live.
- The box: 136 files (was 1,988), scanners blocked, deploy syncs and stamps mtimes.
- The library: 1,000 tech-news pictures, 18 fixture folders, all described by fixture.

## Stop points — Nathan's, before the phase that needs them

- **The buyer walk needs a real card.** Phase 2 cannot complete without him.
- **The WordPress.org username** — Phase 1's last task, and out of our hands.
- **The Folders redesign mock** must be approved before Phase 3 builds it.
- **Whether to cut plan inclusions** (2,000 credits inside €39). Priced, not decided.
- **The cohort size.** This plan argues for 20 before 300; that is his call.

---

## Phase 1 · The free plugin is ready for 300 — Opus

**The yes:** every suite green or explained in writing, and one clean install by
somebody who is not us.

### Task 1.1 · Characterise all ten red suites

- **Files.** None yet. This is measurement.
- **Behaviour.** Run each of `search-try`, `ai-folders`, `auto-file`,
  `ai-background`, `ai`, `smart`, `organize`, `voice`, `librarian`,
  `librarian-ui` alone, with `UI_USER`/`UI_PASS` set, against the box carrying
  the fixture. For each, write one line: *the fault is in the product / the
  suite / the fixture*, with the assertion that proves it.
- **Proof.** A table in the handoff, ten rows, no "unknown".
- **Mirror.** Today's diagnosis of `smart`: a sixty-second selector timeout that
  was a wrong password, found by reading the suite's own login rather than the
  selector it died on.
- **Do not.** Do not fix anything in this task. Characterise first; a fix
  written before the cause is known is how three of today's hours went.
- **GOTCHA.** `librarian` was green at 14:30 and red at 17:00 with no commit in
  between that touches it. Something in the fixture or the counts cache moved
  it. That one is the most interesting of the ten and should be read first.

### Task 1.2 · Fix what is the product's fault

- **Behaviour.** Only the rows from 1.1 marked *product*. Each gets its own
  commit naming the suite that catches it.
- **Proof.** The suite goes from red to green, and a mutation check: break the
  fix, watch that suite go red, restore.
- **Do not.** Do not touch a suite that is wrong about the product — that is 1.3.

### Task 1.3 · Repair or retire the suites that are wrong

- **Behaviour.** A suite asserting behaviour the product deliberately changed is
  updated, and the commit says what changed and when. A suite that tests nothing
  any more is deleted, and the commit says why.
- **Proof.** `node tools/verify.mjs` — **33 of 33 green or skipped, zero red**.
- **GOTCHA.** Deleting a red suite to get a green board is the failure this task
  invites. A deletion needs a sentence that would survive Nathan reading it.

### Task 1.4 · A stranger installs it

- **Behaviour.** A clean WordPress on the Playground **and** a clean install on
  the Kamatera box (real MySQL, no plugin soup): install from the release zip,
  make folders, upload, filter, uninstall. Nobody who built it drives.
- **Proof.** A short screen recording or six screenshots, and the debug log
  clean of anything with `vergeml` in it.
- **GOTCHA.** Deactivating any plugin on the Hetzner box fatals in core's FTP
  filesystem class. That is that box, not us — prove it on the clean one.

### Task 1.5 · The submission, sent

- **Behaviour.** `Contributors:` set to Nathan's WordPress.org username, zip
  rebuilt, Plugin Check re-run, submitted.
- **Proof.** The submission confirmation email.
- **Blocked on Nathan.** Everything else in Phase 1 can finish without it.

**Gate:** `node tools/verify.mjs` 33/33 · Plugin Check ≤ 2 known non-blockers ·
`node tools/filing-baseline-check.mjs` re-taken against the new library and green.

---

## Phase 2 · Pro is worth paying for, and delivers — Opus

**The yes:** a stranger's money becomes a working plugin, watched end to end,
and the promise on the pricing page is a thing that happened.

### Task 2.1 · The live-mode webhook, before any card

- **Behaviour.** In Stripe, live mode: an endpoint at
  `https://vergelabsmedia.com/api/stripe/webhook`, its signing secret equal to
  `STRIPE_WEBHOOK_SECRET` in Vercel production, and every event the code handles
  subscribed.
- **Proof.** Stripe's own delivery log showing a 200.
- **GOTCHA.** `stripe-live-setup.ts` creates products and prices and **no
  endpoint**. Live keys with a test-mode webhook is a shop that charges and
  issues nothing — the exact failure already lived through.

### Task 2.2 · The buyer walk

- **Behaviour.** Nathan buys **one Single at €39 with a real card**, as a buyer,
  from `vergelabsmedia.com/cart?plan=single`. Then, in order: the charge in the
  live dashboard; the licence email arrives; the key activates on a real
  WordPress; Pro downloads through `/api/plugin/download` and installs; the
  invoice renders; `/account` shows the purchase and the credits. Then refund,
  and confirm the licence behaves as designed afterwards.
- **Proof.** A screenshot of each of the seven, in the handoff.
- **Do not.** Do not substitute component checks. Every component returned 200
  the last time and the customer got nothing.

### Task 2.3 · The promise, kept, on a real library

- **Behaviour.** On a clean site with the licence from 2.2: describe 100 real
  pictures **through the plugin's own screen**, and confirm alt text is written
  to the attachments, search by meaning finds a picture by what it shows, and
  duplicates are found.
- **Proof.** Before and after on one picture — empty alt text, then written —
  and one search that returns a picture whose filename contains none of the
  query.
- **GOTCHA.** `vergeml_ai_describe()` returns and does not persist;
  `tools/box-describe-cost.php` proved that at a cost of $0.48 and zero rows.
  Use the screen, not a script.
- **Cost.** ~100 credits, about $0.51.

### Task 2.4 · Pro gets tests

- **Files.** `pro/tests/`.
- **Behaviour.** The licence check, the seat count, staging behaviour and the
  update channel. Pro has **zero** automated tests today and it is the code that
  decides whether somebody who paid gets what they bought.
- **Proof.** A runner that `tools/verify.mjs` can call, and it is green.

**Gate:** the seven screenshots · Pro's suite green · the refund confirmed.

---

## Phase 3 · The UI is ready to ship — Opus, design-first

**The yes:** the four faults found in an hour of ordinary use are gone, and the
Folders screen has an approved design behind it rather than four patches.

### Task 3.1 · Finish the Folders mock and get it approved

- **Files.** `docs/superpowers/mocks/2026-09-10-folders-ways-in.html`.
- **Behaviour.** The column layout is wrong in the current draft and it is
  honest about that. Fix it against the real screen's DOM (`.vgml-folders-left`
  / `.vgml-folders-right`, `.vgml-tv[data-surface="folders"]` with `.vgml-row`
  children), then screenshot the four states: conversation, rules, paste, upload.
- **Proof.** Four screenshots **into the conversation**, and Nathan's approval.
- **Do not.** Do not build anything before that approval. This screen was built
  and patched four times in two days on 2026-09-05 for exactly this reason.

### Task 3.2 · The app shell, and a conversation with a bottom

- **Behaviour.** Per the spec Nathan approved: the screen fills the window, the
  thread scrolls in its own region, the composer and the Move button stay on
  screen at the 25-turn cap. Below 900px tall or 1180px wide it falls back to
  normal page scrolling.
- **Proof.** A spec asserting the Move button is on screen at the cap, at
  1600×1000 and 1280×800.
- **GOTCHA.** `.vgml-conv` has no height and no overflow today; that is the whole
  bug, and `min-height: 0` on the flex children is what makes the fix work.

### Task 3.3 · A structure that looks like a structure

- **Behaviour.** The draft stops being `vgml-facts` bullets and becomes the tree
  component's own rows — chevrons where there are children, counts on the right,
  `is-new` on what this draft would create.
- **Proof.** A screenshot beside the old one, and a spec asserting a nested
  draft renders nested.
- **Do not.** Invent no new visual grammar. The rows exist.

### Task 3.4 · Bring a structure you already have

- **Behaviour.** Paste (indentation, `Parent > Child`, `Parent/Child`, detected
  not declared, with a live preview) and file import (CSV, TXT, Markdown, JSON,
  XLSX, parsed locally). Both produce **a draft**, never folders. Refusals said
  out loud: empty name, deeper than five levels, more than 500, and a name that
  already exists at that place is reused rather than duplicated.
- **Proof.** A spec: the same tree from all three spellings; 501 refused with a
  reason; a file of each type produces a draft and no folders.
- **Out of scope.** PDF and Word — interpretation, not parsing. A later phase.

### Task 3.5 · A sentence per method

- **Behaviour.** Each of the four ways in carries one or two lines saying what it
  is and when to reach for it, in the copy standard: a fact or an action with its
  consequence.
- **Proof.** `node tools/verify.mjs copy` green with the new strings asserted.

**Gate:** the UI suites green at both viewports · `modes.spec` `ROW_ALARM` canary
unchanged · screenshots of every changed screen in the handoff.

---

## Phase 4 · The system is ready for market — Opus

**The yes:** when something breaks, we find out before a customer does.

### Task 4.1 · A health endpoint that exercises what matters

- **Files.** `service/app/api/health/route.ts`.
- **Behaviour.** One request that checks, and reports individually: the database
  answers; `PLUGIN_RELEASES` parses and names the current plugin and pro
  versions; Stripe's API is reachable with the configured key; the webhook secret
  is present; the AI relay answers. 200 when all pass, 503 with a body naming
  what failed otherwise.
- **Proof.** Curl it; break one env var in a preview deploy and watch it go 503
  naming that one.
- **GOTCHA.** It must not leak secrets — the names of what is missing, never the
  values.

### Task 4.2 · Something watches it

- **Behaviour.** An external monitor hitting `/api/health` every five minutes and
  emailing Nathan on failure. Free tier of any uptime service; no infrastructure
  of ours.
- **Proof.** Deliberately break the health check and receive the email.
- **Why external.** A monitor that runs on the thing it watches reports nothing
  when the thing is down.

### Task 4.3 · The release channel cannot go stale again

- **Behaviour.** A check — in the health endpoint or beside it — that compares
  the version `PLUGIN_RELEASES` advertises against the version in the zip it
  points at, and fails when they disagree.
- **Proof.** It would have caught the three weeks the channel advertised 3.0.0
  while the plugin was 3.16.1. Prove it by pointing it at an old release.

### Task 4.4 · The box stops being the only truth

- **Behaviour.** The nightly watch already runs; add the four gates to it so a
  red board is noticed the next morning rather than at the next assessment.
- **Proof.** One run, one report, red when a suite is red.

**Gate:** the health endpoint green · a real alert email received · the stale
release check proven against an old version.

---

## ACCEPTANCE — the four yesses

Each is answerable by a person other than the author.

- [ ] **Yes 1.** `node tools/verify.mjs` reports **33 of 33** with none red;
      Plugin Check ≤ 2 known non-blockers; a clean install and uninstall
      recorded on a WordPress with no other plugins.
- [ ] **Yes 2.** Seven screenshots of one real purchase, from charge to working
      alt text on a real site; Pro's own suite green; the refund confirmed.
- [ ] **Yes 3.** The Folders screen matches an approved mock; the conversation
      scrolls; a draft renders as a tree; a structure can be pasted or uploaded;
      every method says what it is. Screenshots of each.
- [ ] **Yes 4.** `/api/health` answers honestly, an external monitor alerts on
      failure and the alert has been received, and a stale release channel fails
      a check rather than a customer.

## Order, and why

Phases 1 and 4 are independent and can run in parallel — one is the plugin, the
other is the service. Phase 2 needs Nathan and a card. Phase 3 needs an approved
mock before any of it is built.

**If time is short, the order that buys the most is 2, 4, 1, 3.** A shop that
takes money and delivers, watched by something that tells you when it stops, on
a plugin whose faults are known — that is a business. A perfect board on
software nobody has bought is not.

## What this plan refuses to do

- Ship to 300 before 20. The gap between "works on my box" and "works on 300
  strangers' installs" is what the twenty are for, and they are cheap to serve.
- Delete a red suite to make a board green.
- Call anything done on a component check when a person could walk it instead.

## AMENDMENTS

- (none yet)
