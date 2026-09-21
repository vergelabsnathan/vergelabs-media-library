# Decisions, September 2026 — the sitting

Written 2026-09-21 (S30, wave 5 of `plans/finish-the-suite.md`). One row per
open question: what is on file, one recommended answer, Nathan's answer. When
a row is answered the session writes it where it lives (named per row) and
this file keeps the record. Nothing below is decided until the last column
holds Nathan's words.

---

## 1. Story 3.1 — what filing and describing do during a service outage

**On file.** The plugin never blocks on the service; each path already has a
written behaviour, none of it said to the customer in one place:

- Describing: a 5xx/429/timeout puts the picture on a 10-minute hold, the run
  stays active and reschedules itself; four transient failures in a row end
  the pass; no credit is taken for a picture not described (the service
  refunds on `model_unavailable`). `core/ai.php:1386, 1568-1601`.
- Filing: a failed `/file` call sets a one-minute "model down" mark and the
  pick runs rules-only (classes and embeddings already on the local index).
  `core/filing.php:1756-1779`.
- Search by meaning: falls back to the keyword results. `core/search-meaning.php:27`.
- Pro licence: soft-fails, 12-hour cache, 15-minute retry. `pro/includes/licence.php:16-35`.
- Credit balance: keeps the last number, warns after an hour stale. `core/ai.php:275`.
- Terms already promise: "If it's down, credits aren't consumed and your plugin
  keeps working without the AI features." The incident runbook's approved
  customer sentence says the same for describing.
- Gap: while a run waits on an outage the status line shows counts only —
  nothing says "waiting for the service".

**Recommended.** Adopt what exists as the answer — *wait and resume, degrade,
never charge* — and write it down once, in words a buyer reads before an
outage: readme.txt FAQ ("What happens when the service is down?"), the docs
page on credits, and the incident runbook's customer sentence (already
there). Add one status word when a background run is holding for the service
— copy Nathan's — as a small story, not in this sitting. Do not build offline
describing or a queue; the behaviour is already the right one.

**Where it lands.** `readme.txt` FAQ; `service/docs/manual/credits.md`;
`service/docs/runbooks/incident.md` (cross-reference); `sprint-status.yaml`
3.1 → done; a `deferred-work.md` row for the status word.

**Nathan's answer.** _(not given at the sitting — asked again; the recommendation stands as the proposal)_

---

## 2. Story 3.3 — the support response time that can be kept solo

**On file.** "One business day, Monday to Friday, in English" is already
shipped in four places: the site's support section, terms clause 5, the
ticket acknowledgement mail (`service/lib/email.ts:289`), and the safe-mode
copy. Security: "acknowledge within 24 hours". readme.txt names no time.
Monitoring is one person, email only, UptimeRobot every 5 minutes. Two
support addresses are in circulation: `support@in.vergelabs.nl` (site
support section) and `support@vergelabsmedia.com` (app chrome, checkout,
account, mail reply-to).

**Recommended.** Keep **one business day** — it is promised in the terms and
every mail already, and a slower number now would be a downgrade a buyer
can read. State it as an *answer* time, not a fix time: "We answer within one
business day, Monday to Friday. A fix can take longer; the answer says how
long." Put the same sentence in readme.txt's Support section. Make
`support@vergelabsmedia.com` the one address on every surface (the site's
`in.vergelabs.nl` line is the odd one out) — unless that box is the one
Nathan actually reads, in which case the reverse.

**Where it lands.** `readme.txt` Support; `service/public/index.html`
support section and terms (same number, one address); `sprint-status.yaml`
3.3 → done.

**Nathan's answer.** **One business day stays; the address is support@vergelabsmedia.com** ("2 support@", 2026-09-21). Landed: readme.txt Support (the ticket mail's sentence, verbatim); service public/index.html support heading (0317178). The "more than 25 sites" link in the unpushed pricing commit still says in.vergelabs.nl — Nathan's branch.

---

## 3. Story 4.3 — operator-grade or client-grade fill

**On file.** Filing accuracy on the shop: rules-only 60 % exactly right
(347/581), 72 % of placed right; with the text model 61 % and **89 % of
placed right**; tech site 70–77 %. The fill runs in slices of 100 inside a
15-second budget (626 pictures in 27 s); `/file` is metered, not debited;
one credit per *describe*, printed on the Describe button. Guards today: Fill
is disabled until the library is described and the tree confirmed; the Tree
step shows "would be placed / would stay unfiled" as an estimate; **Undo
covers the whole step for a day**; hand placements are never moved; locked
folders are never filed into. No cap on library size, no confirmation, no
mock for 4.3 exists. 20,000 pictures ≈ 13 hours of describing at 25/min
and 20,000 credits; the fill itself is minutes.

**Recommended.** **Client-grade, with one pause.** The operator answer does
not scale to a solo maintainer, and the undo already makes a wrong fill a
one-click event. What a client must not do is press once and get 20,000
placements unread. So: above a threshold (proposal: 500 pictures) the fill
runs its first slice of 100 and stops on "100 placed — look at them, then
continue or undo"; the continue button carries the remaining count. Below
the threshold the fill runs whole, as today. This is a screen change, so
per the story: a mock first, Nathan's approval, then a story of its own.

**Where it lands.** A mock in `docs/superpowers/mocks/`; then a story
(spec → build → review); `sprint-status.yaml` 4.3 → in-progress with the
decision recorded in `epics.md`.

**Nathan's answer.** **Client-grade with the pause at 500 / first 100** ("3 yes i agree"). Landed: epics.md story 4.3 carries the decision; the mock is the next step, not started.

---

## 4. PRIVATE0

**On file.** A 99 % code of ours (`discount_codes` id 3, made at
`/admin/discounts`), applied to yearly plans and credit packs alike. On
2026-09-20 it produced a free year (licence `…AB26`, invoice at €0.39 marked
paid at €0). Story C floored subscriptions: `PRIVATE0` on any yearly plan now
answers `minimum_amount`; on credits it still takes 99 % off (`credits=10000
→ €1.40`). Subscription purchases write no redemption, so `max_uses` does
not hold on yearly plans (moot behind the floor). Wave 0 asked Nathan to
switch it off and cancel `…AB26`; no handoff records either click.

**Recommended.** **Switch it off** (one click, "Switch off" on the admin
page), and cancel `…AB26` in Stripe. When a private top-up is wanted, make a
new code restricted to credits — the page's own rule is "switch it off and
make a new one". Nothing in the code changes.

**Where it lands.** Nathan's two clicks; `deferred-work.md` row closed;
`sprint-status.yaml` note under 2.2.

**Nathan's answer.** **Off** ("4 yeah fine"). Landed: deferred-work.md row marked decided. The two clicks (/admin/discounts Switch off; …AB26 cancel) are Nathan's and not yet confirmed on file.

---

## 5. Two supplier addresses on the invoice (`vat.md` item 5)

**On file.** Our PDF prints `INVOICE_SELLER_ADDRESS` = Boetiek House S.L.,
trading as VergeLabs, Calle Fernando Fuentes 2, 38300 Santa Cruz de
Tenerife, CIF B75865022 — the same address as every legal page, the DPA and
the site's privacy and terms. Stripe's business profile (which Stripe's own
invoice PDF and receipts print) says Calle Leon 29, La Orotava. A buyer sees
Stripe's address only through Stripe's own emails; the account's Billing tab
links our PDF.

**Recommended.** One address everywhere, and it is the one in the Registro
Mercantil for B75865022. If that is Santa Cruz (as every document we
publish already says), change Stripe's business profile to match — one
dashboard field, no code. If the registry says La Orotava, the change is the
other way: one env value (`INVOICE_SELLER_ADDRESS`) and the legal pages,
which is a copy pass.

**Where it lands.** Stripe dashboard (Nathan) or `INVOICE_SELLER_ADDRESS` +
legal pages; `vat.md` item 5 closed.

**Nathan's answer.** **Santa Cruz** ("5 santa cruz"). Landed: vat.md item 5 (1f92502). Stripe's business profile and tax head office to Calle Fernando Fuentes 2 — Nathan's dashboard field, not yet confirmed on file.

---

## 6. The UK VAT row (`vat.md` item 6)

**On file.** Live: only an ES `oss_union` registration; no GB registration,
so a UK buyer is quoted in GBP (£35 / £69 / £349, prices exclusive), Stripe
marks UK VAT `not_collecting`, the invoice says "VAT (none charged)".
Nathan's rule says 0 % outside the EU; the earlier memory says "UK VAT from
the first sale". As I understand the UK rule — to be confirmed by the
accountant, not by me — a business with no UK establishment selling digital
services to UK *consumers* must register for UK VAT from the first such
sale (no threshold for non-established sellers); a sale to a UK *business*
with a VAT number is reverse-charged and needs no registration.

**Recommended.** Put the one question to the accountant this week, as
`vat.md` already frames it. Until the answer: keep selling to UK businesses
(a GB VAT number on the checkout), and either register before the first UK
consumer sale or hold UK consumer checkout — my recommendation is the hold,
because a registration is paperwork Nathan owns and a missed one is a tax
problem, not a bug. If the accountant says 0 %, the hold comes off with
no code change beyond the checkout rule.

**Where it lands.** The accountant's answer into `vat.md` item 6; if a hold:
one rule in `service/app/api/licence/intent/route.ts` (GB + no tax id →
`code`/reason the cart already shows) with a sentence of Nathan's; if a
registration: Stripe Tax registration GB, no code.

**Nathan's answer.** **Neither hold nor guess: UK VAT and US sales tax according to the rules as they apply to a Spanish entity, which VergeLabs is for now** ("6 uk vat or us according to the rules which is i am a spanish entity for now"). The accountant states the rules; live stays as row C meanwhile, no checkout hold. Landed: vat.md item 6 (1f92502). The recommended hold was not taken.

---

## 7. Retrospectives for epics 2, 3 and 4 — held or recorded

**On file.** Epic 1's retro is on file (`epic-1-retro-2026-09-20.md`, 15
action items, 9 done, 6 open — four of them Nathan's). Epics 2–4 are
`in-progress` with their retro rows `optional`. Story 2.1's code review has
no triage log (the S25 handoff left it open; 1c reviewed wave 1 only).
Epic 2 cannot close before 2.3 (the accountant); epics 3 and 4 close when
rows 1–3 above are written.

**Recommended.** **Recorded, not held**: one headless session
(`bmad-retrospective -H`) for epics 3 and 4 together once rows 1–3 are
written, with 2.1's `bmad-code-review` done first in the same session (it
is the one review gap); epic 2's retro when 2.3 lands. A held sitting adds
Nathan's hour and the evidence is all in files already.

**Where it lands.** `sprint-status.yaml` retro rows → done with the file
names; `plans/finish-the-suite.md` wave 6 record.

**Nathan's answer.** **Recorded, headless** ("7 okay"). Runs in wave 6 once row 1 is answered; 2.1's code review first.

---

## The pass

Seven answers in one message, numbered. The session then writes each where
its row says, runs what a change touches, and records the file names here.
