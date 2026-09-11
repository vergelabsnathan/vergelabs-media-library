# Plan — four yesses, at two thousand

Written 2026-09-10. Revised the same day from 300 to **2,000 installs**, because
300 was a number that let several questions stay unasked.

**The four questions, verbatim:**

1. Is the free plugin ready for use by **2,000** people?
2. Is paying for Pro and getting it running worth it — does it fulfil what is promised?
3. Is the UI/UX ready enough to ship?
4. Is the plugin and the system behind it ready for market?

**The rule:** a yes is something showable to somebody else — a suite output, a
screenshot, an email in an inbox, an alert that fired, a restored database. Not
a belief.

## What 2,000 changes, and why the plan grew

300 installs is a community. 2,000 is a population, and populations produce
things a single box never will:

| at 300 you can assume | at 2,000 it is a certainty |
|---|---|
| most sites resemble the test box | every WordPress from 6.5 to 7.1, PHP 7.4 to 8.5, multisite, RTL, Polylang, WPML |
| libraries are thousands of files | some are hundreds of thousands; one will be a million |
| a bad release is embarrassing | a bad release reaches 2,000 sites before you wake up |
| support is email you answer | support is a queue, and silence in it is churn |
| one person describing at a time | concurrent describes, provider rate limits, a runaway import |
| you notice breakage | you learn about breakage from a stranger, publicly |

So the plan gains four things it did not have at 300: **a compatibility matrix**,
**a rollback that has been rehearsed**, **limits and a spend cap**, and **a
restore drill**. Anything less is optimism.

## How to run this plan — read first (2026-09-11)

The tasks below are the reasoning. **The executable form is one file per
phase under `plans/four-yesses/`** — `phase-1.md` … `phase-5.md` — every task
as a mini-spec in the shape `~/.claude/harness/model-profiles.md` requires
(Files · Behaviour · Proof · Mirror · Copy · Do not), a model named per task,
the sessions sized, the stop points and the gates as commands at the top of
each file. A session opens with its phase file, not with this one. The ticket
is `tickets/2026-09-11-four-yesses.md`.

**What the survey of 2026-09-11 corrected.** Three Phase 4 items were built
on 2026-09-10 and are now "prove" tasks: `/api/health` (five checks),
`/api/cron/release-check`, and per-licence rate limits in `lib/limits.ts`.
`SECURITY.md` exists. The credit spend already locks the row
(`select … for update`), so 2.5 proves rather than fixes. The Folders screen
exists (f3497e9); what awaits approval is the ways-in mock of 2026-09-10. No
Dutch or Arabic translation exists — `languages/` holds the `.pot` only.
Kamatera was retired 2026-08-29; the box is the real MySQL.

**Stop points, all of them, with the task each blocks.**

| decision | blocks |
|---|---|
| retire `librarian-ui` with the sentence in phase-1, or keep it red — **retire (2026-09-11)** | 1.3 |
| translations: machine-translate the `.pot` into nl_NL and ar, or ship English — **English strings; locale and RTL only (2026-09-11)** | 1.4, 3.11 |
| WPML zip (paid, not in `Pluginexamples/`) or drop it from the row | 1.4 |
| a clean host for the size numbers, or the box with its 28 plugins | 1.5 |
| the WordPress.org username — **VergelabsDev (2026-09-11)** | 1.8 |
| a real card and an hour, for the buyer walk | 2.2 |
| ≈ €0.50 to describe 100 pictures through the screen | 2.3 |
| a test-mode Stripe key for the lifecycle walks | 2.4, 2.8 |
| approve the ways-in mock (four screenshots) | 3.1 → 3.2–3.5 |
| approve the first-screen mock and its copy | 3.6 |
| approve the eighteen-state sheet and its copy | 3.7 |
| the confirmation strings for bulk move, folder delete, uninstall wipe | 3.9 |
| five strangers, their time, and who watches | 3.12 |
| which monitor, whose inbox | 4.2 |
| the global daily spend number (proposed €50/day) | 4.5 |
| a scratch Supabase project for the restore | 4.6 |
| rotate the OpenRouter key (his account) | 4.9 |
| who reviews, and what it costs | 5.9 |
| `security@in.vergelabs.nl` reaches a person | 5.10 |
| plan inclusions (2,000 credits inside €39) — priced, not decided | none |
| re-describe the box's library (≈ €4.83), then re-take `tests/tree/filing-baseline.txt`; mock on or off there — **mock stays on until 2.3 (2026-09-11)** | the filing-baseline gate of Phase 1, every meaning-search proof |

## What is already true, 2026-09-10

- Plugin Check: **2 errors**, neither a submission blocker. Zip is 135 files, no hidden ones.
- Service: **365 tests green**, typecheck clean, live, licence-gated.
- Plugin: **21 of 33 suites green**; 10 red and uncharacterised; 2 skipped.
  → 2026-09-11: **30 of 33**, one red (`librarian-ui`, explained), 2 skipped.
- Counts at 251,000 attachments: **8.6ms cold**, was 10,038ms.
- Pricing re-rated on measured cost (€0.00483/image) and live.
- Box: 136 files, scanners blocked, deploy syncs and stamps mtimes.

## Stop points — Nathan's

- **The buyer walk needs a real card**; Phase 2 cannot finish without him.
- **The WordPress.org username.**
- **The Folders mock** must be approved before Phase 3 builds anything.
- **Plan inclusions** — 2,000 credits inside €39 is $80 of alt-text value at a
  competitor's rate. Priced, not decided.
- **The rollout shape.** This plan argues 20 → 200 → 2,000. His call.
- **The OpenRouter key** pasted into a transcript on 2026-09-10 should be
  rotated. His account, his call, named here so it is a decision.

---

## Phase 1 · The free plugin holds up across 2,000 installs — Opus

**The yes:** every suite green or explained; the matrix passes; a stranger
installs and uninstalls it cleanly; and a bad release can be pulled back.

### 1.1 · Characterise all ten red suites

Run `search-try`, `ai-folders`, `auto-file`, `ai-background`, `ai`, `smart`,
`organize`, `voice`, `librarian`, `librarian-ui` alone with credentials against
the fixture. One line each: **product / suite / fixture**, with the assertion
that proves it. **No fixes in this task.**

**GOTCHA.** `librarian` was green at 14:30 and red at 17:00 with no commit
touching it. Read that one first; it is either the fixture or the counts cache,
and both matter.

### 1.2 · Fix what is the product's fault
Each with its own commit naming the suite that catches it, and a mutation check.

### 1.3 · Repair or retire the suites that are wrong
A deletion needs a sentence that survives Nathan reading it.
**Proof:** `node tools/verify.mjs` — **33 of 33, zero red.**

### 1.4 · The compatibility matrix

**This is the task 2,000 adds.** Install the release zip and run a fixed
five-minute script — make a folder, upload, drag a file into it, filter, bulk
move, uninstall — on each of:

| axis | values |
|---|---|
| WordPress | 6.5 (minimum), 7.0, 7.1 (current) |
| PHP | 7.4 (minimum), 8.2, 8.5 |
| install shape | single site, **multisite subdirectory**, multisite subdomain |
| language | English, **Dutch**, **Arabic (RTL)** |
| alongside | nothing, FileBird, Premio Folders, Enhanced Media Library, Polylang, WPML |

**Proof:** a table of ✓/✗ with the failure named, in `docs/compatibility.md`.
**Mirror:** the existing compatibility work — eighteen plugins already recorded.
**GOTCHA.** RTL ships three hand-kept sheets. A matrix that skips Arabic is a
matrix that will be wrong for the first Arabic install.

### 1.5 · Two library sizes, on real MySQL

Kamatera, not Playground: **10,000** and **250,000** attachments. Time the media
library, the tree, search and the duplicate scan at both. Record the numbers.

**GOTCHA.** The counts are fixed; nothing else has been measured at size. The
duplicate scan and the tree render are the two most likely to be next.

### 1.6 · Uninstall is safe, proven

On a clean site with real content: uninstall and confirm folders survive as
categories, no attachment is deleted, no orphan tables remain when the "remove
everything" option is used, and the debug log is clean.

**Why it earns a task:** the whole argument against FileBird is that uninstalling
does not cost you your folders. That claim must be tested, not asserted.

### 1.7 · A release can be pulled back

Rehearse it: publish a deliberately broken 3.16.2 to the update channel, watch a
site offer it, then roll `PLUGIN_RELEASES` back to 3.16.1 and confirm the site
stops offering the bad one. **Write the runbook while doing it.**

**Why:** at 2,000 sites a bad release is a same-hour problem, and the first time
you do this must not be the first time it matters.

### 1.8 · The submission
`Contributors:` set, zip rebuilt, Plugin Check re-run, submitted. **Blocked on Nathan.**

**Gate:** verify 33/33 · Plugin Check ≤ 2 known · filing baseline re-taken and
green · the matrix table · the rollback runbook.

---

## Phase 2 · Pro is worth paying for, at a hundred paying customers — Opus

At 2,000 free installs a 5% conversion is **100 paying customers**. That is
renewals, failed cards, cancellations, refunds, seat disputes and VAT invoices —
none of which has ever happened once.

### 2.1 · The live-mode webhook, before any card
Endpoint at `/api/stripe/webhook`, signing secret matching Vercel production,
every handled event subscribed. **Proof:** Stripe's delivery log showing 200.
**GOTCHA.** `stripe-live-setup.ts` creates products and prices and **no
endpoint**.

### 2.2 · The buyer walk
One real €39 purchase, walked as a buyer. Seven proofs: the charge live; the
licence email; the key activating on a real site; Pro downloading and installing;
the invoice; `/account` showing it; the refund behaving.
**Do not** substitute component checks — every component returned 200 last time
and the customer got nothing.

### 2.3 · The promise, kept, on a real library
Describe 100 pictures **through the plugin's screen**: alt text written to the
attachments, meaning-search finding a picture by what it shows, duplicates found.
**GOTCHA.** `vergeml_ai_describe()` returns and does not persist — proven at a
cost of $0.48 and zero rows. Use the screen.

### 2.4 · The subscription lifecycle, all of it

**The task 2,000 adds.** Each of these happens to somebody in the first year and
none has been exercised:

- a **renewal** succeeds a year on (clock-shifted in test mode)
- a renewal **fails** — the card expired. What does the customer see, what email
  goes, how long do the AI features keep working, and when do they stop
- a **cancellation** mid-term: access until period end, then not
- an **upgrade** Single → Five, and a **downgrade**, and what happens to credits
- a **refund** after credits were spent
- a **chargeback**

**Proof:** one test-mode walk per row, and a line in the docs saying what the
customer is told at each.

### 2.5 · Credits cannot be spent twice

Fire 20 concurrent describes against a licence with 10 credits. **Exactly 10
succeed.** The ledger's own comment already worries about parallel requests
passing the balance check together; this proves it or finds it.

### 2.6 · Seats are enforced and disputable
Single on two sites: the second is refused with a sentence a person understands.
Staging keeps its seat (Pro 1.0.2 claims this — prove it). Deactivating returns
one.

### 2.7 · Pro gets tests
`pro/tests/` covering licence check, seats, staging, updates. **Zero today**, and
it is the code that decides whether somebody who paid gets what they bought.

### 2.8 · Invoices are correct for a Tenerife supplier
A VAT-correct invoice for: a Dutch consumer, a Dutch business with a VAT number,
a UK consumer, a US business. **Destination VAT from the first euro and Stripe
will not run the right OSS scheme by itself.** Get this wrong at 100 customers
and it is a tax problem, not a bug.

**Gate:** the seven screenshots · lifecycle walks · the concurrency proof · Pro's
suite green · four correct invoices.

---

## Phase 3 · The UI is ready to ship to 2,000 — Opus, design-first

At 300 the UI needs to work. At 2,000 it needs to work **for people who will
never ask you a question** — they will uninstall instead, and you will never
know why. That changes what has to be proven:

| at 300 | at 2,000 |
|---|---|
| it works when you drive it | it works when a stranger drives it, badly, once |
| an empty screen is obviously new | an empty screen with no words is a broken screen |
| a failure shows an error | a failure has to say what to do next |
| your laptop is the viewport | 1366×768 is the most common screen in the world |
| Chrome is the browser | Safari is a quarter of your installs |
| you know what a folder draft is | nobody does, and nothing on screen teaches them |

So the phase gains five tasks the 300 plan did not have: **the first sixty
seconds**, **every empty and every error state**, **a viewport and browser
matrix**, **destructive actions and undo**, and **five strangers doing it while
somebody watches**.

### 3.1 · Finish the Folders mock, get it approved
Fix the column layout against the real DOM, screenshot four states into the
conversation, wait for approval. **Do not build first** — this screen was patched
four times in two days for want of that.

### 3.2 · The app shell, and a conversation with a bottom
The thread scrolls in its own region; composer and Move stay on screen at the
25-turn cap. Asserted at 1600×1000 and 1280×800.

### 3.3 · A draft renders as a tree, not as bullets
The tree component's own rows. No new visual grammar.

### 3.4 · Bring a structure you already have
Paste (three spellings, detected, live preview) and file import (CSV, TXT,
Markdown, JSON, XLSX, parsed locally). Both produce **a draft**. Refusals said
out loud. PDF and Word are out.

### 3.5 · A sentence per method
Each way in says what it is and when to reach for it.

### 3.6 · The first sixty seconds

What a stranger sees between activating the plugin and understanding what it is
for. Today: nine menu items and no path through them.

- **Behaviour.** One route from activation: a screen that says what this does,
  what is free, what a licence adds, and offers exactly one next action — make
  your first folder, or connect a licence. Dismissible for ever, never nagging.
- **Proof.** A recording of activation to first folder, **under sixty seconds,
  by somebody who has not seen it before**.
- **Do not.** No tour, no coach marks, no modal that must be dismissed before the
  screen can be used. One screen, one action.

### 3.7 · Every empty state, and every failure state

**The largest single task in this phase**, because it is the one nobody does and
it is what a new install is made of.

- **Behaviour.** For each of the nine screens, both:
  - **empty** — no folders, no pictures, nothing described, no licence. Each says
    what would be here and how to get it. Never a blank panel, never a zero that
    reads as a fact when it means "not looked".
  - **failed** — the service unreachable, the licence invalid, credits at zero,
    the network gone mid-run. Each says what happened and what to do about it.
- **Proof.** A screenshot grid: nine screens × two states, eighteen pictures, in
  the handoff.
- **Mirror.** Phase 5 of the traces work already did this once — `guide/rules`
  failing no longer fabricates a zero. That is the standard; apply it everywhere.
- **GOTCHA.** "Not looked" and "none" are already different answers in the code
  (`null` versus `0`). Every empty state has to keep that distinction visible or
  the screens start lying quietly.

### 3.8 · Viewports and browsers

- **Behaviour.** The five screens that matter — Library grid, Library list,
  Folders, AI, Duplicates — at **1366×768** (the most common screen there is),
  1440×900, 1920×1080, and a 1280 laptop with the WordPress menu expanded. In
  **Chrome, Firefox, Safari and Edge**.
- **Proof.** A grid of screenshots; nothing clipped, nothing overlapping,
  no horizontal page scroll, the primary action visible without scrolling.
- **GOTCHA.** Safari is the one that will break: it lags on `:has()`,
  `inset-inline`, and flex `min-height: 0` behaviour — all three of which this
  admin uses.

### 3.9 · Destructive actions, and the way back

- **Behaviour.** Every action that changes many files at once — Move, bulk move,
  folder delete, rename across a library, uninstall's "remove everything" —
  says **exactly what it will do, with the number**, before it does it, and
  offers a way back where one exists.
- **Proof.** A spec asserting each confirmation names its own count; and the
  Move's undo walked on a real library.
- **Why it earns a task.** A stale draft behind a Move button once cost
  twenty-one real folders. At 2,000 installs that is somebody's client's library.

### 3.10 · Keyboard and screen reader

- **Behaviour.** The folder tree, the media modal and the Folders screen driven
  by keyboard alone; the tree announced as a tree with expand state; every
  control named; focus never lost when the modal closes; visible focus rings.
  Target **WCAG 2.2 AA** on those three surfaces.
- **Proof.** A pass with a screen reader, recorded, and **axe clean of criticals
  and serious** — not just criticals.
- **GOTCHA.** The tree is a custom widget. Without `role="tree"`,
  `aria-expanded` and roving tabindex it is a pile of divs to a screen reader,
  however it looks.

### 3.11 · The other language, and the other direction

- **Behaviour.** The admin in **Dutch** and in **Arabic**, every screen.
- **Proof.** Screenshots of all nine in both, and the RTL sheets regenerated or
  proven current.
- **GOTCHA.** Three RTL sheets are hand-kept. They are the most likely thing in
  this plugin to be quietly wrong, and Arabic is where it shows.

### 3.12 · Five strangers, watched

**The task that decides whether any of the above worked.**

- **Behaviour.** Five people who have never seen it, one at a time, on their own
  machine, with a real WordPress and a real library. Three tasks, no help:
  *make folders for these pictures*, *find the picture of a server rack*, *give
  these images alt text*. Watch, do not explain. Write down every place they
  stop, hesitate or do the wrong thing.
- **Proof.** A page per person, and a ranked list of what to fix. Five people
  find roughly four in five of the problems that exist; this is the cheapest
  rigour available and nothing in this plan substitutes for it.
- **Do not.** Do not run this before 3.6 and 3.7. Watching five people
  rediscover a missing empty state is wasting them.

**Gate:** UI suites at both viewports · `ROW_ALARM` unchanged · axe zero
criticals and zero serious · eighteen state screenshots · the viewport and
browser grid · nine screens in three languages · five session write-ups with a
ranked list, and the top three fixed.

---

## Phase 4 · The system survives 2,000 sites — Opus

**The yes:** it tells you when it breaks, it cannot be bankrupted by one
customer, and its database has been restored from a backup at least once.

### 4.1 · A health endpoint that exercises what matters
Database answers; `PLUGIN_RELEASES` parses and names current versions; Stripe
reachable; webhook secret present; AI relay answers. 200 or 503 naming what
failed. **Never the values, only the names.**

### 4.2 · Something external watches it
Every five minutes, email on failure. **Proof:** break it deliberately and
receive the email. A monitor on the thing it watches reports nothing when the
thing is down.

### 4.3 · The release channel cannot go stale
A check comparing the advertised version against the zip it points at. It would
have caught the three weeks the channel advertised 3.0.0 while the plugin was
3.16.1.

### 4.4 · Limits, so one site cannot take the service down

**The task 2,000 adds.** Per-licence and per-site rate limits on describe; a
concurrency ceiling; a queue that sheds rather than collapses. Measured at
**82/min end to end** — so what happens at 300/min from one agency?

**Proof:** drive 10× the ceiling and watch it refuse politely rather than fall
over, with everyone else still served.

### 4.5 · A spend cap, per licence and global

A runaway import must not become a four-figure provider bill. A per-licence cap
already exists in credits; a **global daily cap** does not. Add one, alert at
80%, refuse at 100%.

**Why:** credits bound what a *customer* spends. Nothing bounds what *you* spend
when something loops.

### 4.6 · Backups, and a restore that happened

Confirm Supabase point-in-time recovery is on, then **restore into a scratch
project and count the rows.** A backup nobody has restored is a hope.

### 4.7 · The provider can fail
OpenRouter has an outage or rate-limits. What does a customer see, what happens
to the credit, and does the queue resume? **Proof:** block the provider and walk
it.

### 4.8 · An incident runbook
One page: the alert fires — what do you look at, in what order, and what do you
tell customers. Written while doing 1.7, 4.2 and 4.7, not after.

### 4.9 · Secrets are rotatable, and rotated
The OpenRouter key was pasted into a transcript today. Rotate it, and write down
where every secret lives and how each is replaced without downtime.

**Gate:** health endpoint honest · a real alert received · stale-release check
proven · limits proven under 10× load · a restore performed and counted · the
runbook.

---

---

## Phase 5 · The plugin is not a hazard — Opus, adversarial

**The yes:** every way in is enumerated and audited, an outsider has tried to
break it, and there is a way to ship a fix the same day one is reported.

**Why this is its own phase.** A WordPress plugin at 2,000 installs is a target
worth somebody's afternoon. A vulnerability does not stay private: Patchstack
and WPScan publish it, the listing can be pulled from WordPress.org, and every
install hears at once. The plugin also runs inside *other people's* businesses —
a hole here is a hole in a company that trusted you.

**The surface, measured 2026-09-10:**

| | count |
|---|---|
| REST routes | **69** |
| AJAX actions | **8** |
| direct `$wpdb` calls | **291** |
| `current_user_can` checks | 128 |
| nonce checks | **28** |
| routes with `permission_callback` returning true | **0** |

Two numbers to sit with: 291 direct database calls, and 28 nonces against 69
routes plus 8 AJAX actions. Neither is damning — a REST permission callback is
not a nonce — but neither has been audited as a whole.

### 5.1 · Enumerate every way in

- **Behaviour.** One table: every REST route, AJAX action, admin_post handler,
  form, cron hook and filter that acts on input. For each: what it does, who may
  call it, what it reads from the request, what it writes.
- **Proof.** `docs/security-surface.md`, generated from the code rather than
  remembered, and regenerated in CI so it cannot drift.
- **Do not.** Audit nothing until the list is complete. An audit of an unknown
  surface is a sample.

### 5.2 · Authorization: capability, ownership, intent

- **Behaviour.** Every row of 5.1 answers three questions: **can this caller do
  this at all**, **may they do it to *this* object**, and **did they mean to**.
  Anything missing one is a finding.
- **Mirror.** `core/quick-edit.php:161` and the librarian-why route: `edit_post`
  on *that* attachment, not a blanket `manage_options`. The question the screen
  asks, asked of the object in front of it.
- **GOTCHA.** The dangerous shape is IDOR, not a missing login — a subscriber or
  an editor acting on somebody else's attachment, folder or licence. Test as a
  low-privileged user, not as an anonymous one.
- **Proof.** A suite calling every route as **anonymous, subscriber, author and
  editor**, asserting the refusal. Red when a route forgets.

### 5.3 · Injection: all 291 database calls, classified

- **Behaviour.** Each is **prepared**, **interpolates only a `$wpdb` table
  name**, **interpolates only integers it cast itself**, or **is a finding**.
  Per call, not per file.
- **Proof.** The 14 `UnescapedDBParameter` and 9 `InterpolatedNotPrepared`
  warnings reach zero, or carry a one-line reason a reviewer would accept.
- **GOTCHA.** Twelve were annotated as safe today after reading them. That was
  right, and it is also exactly how a real one gets waved through. Re-read them
  **adversarially**: assume the annotation is wrong and try to prove it.

### 5.4 · Escaping, where HTML is built from data we did not write

- **Behaviour.** Plugin Check reports **zero** unescaped-output errors on the
  clean tree — a good start, not an audit. Check by hand everywhere markup is
  built from folder names, file names, captions and tags the model wrote, and
  anything from an imported file.
- **GOTCHA.** A folder name reaches the tree, the modal, the list table and the
  block editor. One unescaped path is a stored XSS that fires for every
  administrator who opens the library.

### 5.5 · Files, paths and uploads

- **Behaviour.** The import, the renamer, and every `wp_delete_file` path: no
  traversal, nothing written outside uploads, no path from a request acted on
  before it is resolved and checked.
- **GOTCHA.** `/rename-files` is gated behind `VERGEML_FILE_RENAME` and its
  reference rewrite is unfinished. It stays off, and this task records that in
  writing rather than leaving it to memory.

### 5.6 · Outbound requests, and SSRF

- **Behaviour.** Every host the plugin fetches — the AI service, the
  known-issues file, anything a user can influence. A URL from settings or a
  request is validated, never trusted.
- **Proof.** A list of every outbound host and what decides it.

### 5.7 · Secrets: at rest, in logs, in responses

- **Behaviour.** The licence key is sealed (`vergeml_ai_seal`) — confirm the
  sealing is real, and that the key never appears in a log, an error, a REST
  response or a support bundle. Same for the download token and the guide token.
- **GOTCHA.** A 159MB debug log turned up today. Grep the next one for anything
  key-shaped before it is ever attached to a support email.

### 5.8 · The service, audited separately

- **Behaviour.** `npm audit` clean of high and critical; the Stripe webhook
  verifies its signature; the download token is signed **and** the licence
  re-checked at redemption (it is — keep it); login rate limits hold; session
  tokens stored hashed (they are); no secret in a client bundle.
- **Proof.** A dependency report and a walk of each.

### 5.9 · Somebody who did not write it tries to break it

- **Behaviour.** An independent review of plugin and service — a paid
  WordPress-specialist review or a scoped bounty. Brief them with 5.1's table.
- **Why it earns money.** Everything above is found by the person who wrote the
  code, which is the weakest kind of audit there is however careful they were.

### 5.10 · A way to be told, and a way to fix it that day

- **Behaviour.** `SECURITY.md` with an address that is read, a stated response
  time and a disclosure policy. Then rehearse: a report arrives at 09:00 — what
  is shipped by 17:00? It uses 1.7's rollback and 4.2's alerting.
- **Proof.** The runbook, and one rehearsal.
- **Why:** at this size the question is not whether a report arrives. It is
  whether the answer is "fixed and shipped" or "we are looking into it".

**Gate:** the surface table complete · the role-based refusal suite green · 291
calls classified with none unexplained · `npm audit` clean of high and critical ·
an independent review with findings closed · `SECURITY.md` published and one
rehearsal done.

## ACCEPTANCE — the five yesses, at 2,000

- [ ] **Yes 1.** verify 33/33, zero red · Plugin Check ≤ 2 known · the matrix
      table complete across WordPress, PHP, multisite, RTL and five neighbours ·
      timings at 10,000 and 250,000 · uninstall proven safe · a rollback
      rehearsed.
- [ ] **Yes 2.** Seven screenshots of a real purchase · every lifecycle event
      walked · credits provably not double-spendable · seats enforced · Pro's
      suite green · four correct VAT invoices.
- [ ] **Yes 3.** The Folders screen matches an approved mock · conversation
      scrolls · draft renders as a tree · a structure can be pasted or uploaded ·
      every method says what it is · **activation to first folder under sixty
      seconds by somebody new** · eighteen empty-and-failed state screenshots ·
      the viewport and browser grid clean including Safari · every destructive
      action names its own count · WCAG 2.2 AA on tree, modal and Folders with
      axe zero criticals and zero serious · nine screens in Dutch and Arabic ·
      **five strangers watched, written up, top three fixed**.
- [ ] **Yes 4.** `/api/health` honest · an alert received · stale releases
      caught · limits hold at 10× · a spend cap that refuses · **a database
      restored from backup and counted** · a runbook · secrets rotated.
- [ ] **Yes 5.** Every route, action and hook enumerated · a suite proving each
      refuses anonymous, subscriber, author and editor where it should · all 291
      database calls classified with none unexplained · outbound hosts listed ·
      no secret in a log or a response · `npm audit` clean of high and critical ·
      **an independent review with its findings closed** · `SECURITY.md` live and
      a same-day fix rehearsed.

## Order, and the rollout

Phases 1 and 4 are independent — one is the plugin, one is the service. Phase 2
needs Nathan and a card. Phase 3 needs an approved mock.

**If time is short: 2, 4, 5, 1, 3.** Security moves ahead of the board and the polish, because a hole shipped to 2,000 sites is the only item on this page that cannot be taken back. A shop that delivers, watched by something that
says when it stops, on a plugin whose faults are known, is a business.

**And do not go to 2,000 in one step.** 20, then 200, then 2,000, with a week
between. Each step is a chance to learn something cheaply that the next step
would teach expensively. The matrix in 1.4 is what makes 200 safe; the limits in
4.4 and the restore in 4.6 are what make 2,000 safe.

## What this plan refuses to do

- Ship to 2,000 in one move.
- Delete a red suite to make a board green.
- Call a backup done that has never been restored.
- Call anything done on a component check when a person could walk it instead.

## AMENDMENTS

- 2026-09-11 — prepared for execution at Nathan's request ("overprepare is
  overperform"). Every task rewritten as a six-field mini-spec in
  `plans/four-yesses/phase-N.md`, model per task, sessions sized, stop points
  consolidated above. State corrected from a survey: 4.1, 4.3 and the
  per-licence half of 4.4 exist; `SECURITY.md` exists; 2.5 is a proof; the
  Folders screen exists; no translations exist. Ticket:
  `tickets/2026-09-11-four-yesses.md`.

- 2026-09-10 — raised from 300 to 2,000 installs at Nathan's request. Added the
  compatibility matrix (1.4), size measurement (1.5), uninstall safety (1.6),
  release rollback (1.7), the whole subscription lifecycle (2.4), credit
  concurrency (2.5), seat enforcement (2.6), VAT invoices (2.8), accessibility
  rate limits (4.4), a global spend cap (4.5), a restore drill (4.6), provider
  failure (4.7), a runbook (4.8) and secret rotation (4.9).
- 2026-09-10 — Phase 3 raised to the same bar at Nathan's request. UI/UX gained
  the first sixty seconds (3.6), every empty and failure state across nine
  screens (3.7), a viewport and browser matrix including Safari and 1366x768
  (3.8), destructive actions naming their own count (3.9), WCAG 2.2 AA with a
  screen reader (3.10), Dutch and Arabic (3.11), and five strangers watched
  doing three tasks unaided (3.12). The last one decides whether the rest
  worked; at 2,000 installs the people who cannot work it out do not write in,
  they uninstall.
