# Finish the suite — the mixed mode

Written 2026-09-20 (S25) on Nathan's go. What is left of `plans/suite-readiness.md` after epic 1 and story 2.1, run in the shape that finishes it fastest without lowering what a story has to prove.

**BMAD stays the spine.** Every story below is still `bmad-spec` (one question per step; Advanced Elicitation on A and C at least) → `bmad-build` → `bmad-code-review`; the retros stay; `sprint-status.yaml` stays the tracker. The harness stays: the model per phase from `~/.claude/harness/model-profiles.md`, `.harness/active.json` per session, the stop points repeated, a handoff per session, no compaction. What changes is only *when* stories run: the six in wave 1 run at the same time, each in its own worktree, and one consolidation session merges them. Everything else is sequential as before.

## Waves

| Wave | What | Mode | Model |
|---|---|---|---|
| 0 | Nathan: PRIVATE0 off at `/admin/discounts`; the `…AB26` licence cancelled in the walk account | his clicks | — |
| 1 | Six code-only stories A–F, each in a worktree, from the mini-specs below | parallel, one day | Opus 5 (F: Sonnet 5) |
| 1c | Consolidation: merge, the full batteries, one code review, Nathan's triage | one session | Fable 5.1 (Opus 5 if the quota is not there) |
| 2 | The release train: 4.0.1 cut → Pro 1.0.3 cut → service deploy; each with its stop points | sequential | Opus 5 |
| 3 | 4.2 the compatibility matrix on 4.0.0 — the Playground cells in parallel, the box cells sequential | mixed | Opus 5 |
| 4 | The €0.50 walk again on 4.0.1 + Pro 1.0.3 — only if wave 1 changed the money path (C does) | one session, Nathan's go | Opus 5 |
| 5 | The decision sitting (below) and writing the answers into the docs | one sitting + one session | Opus 5 |
| 6 | `bmad-code-review` on 2.1 if not done in 1c; retros for epics 2, 3, 4 | one session | Opus 5 |

Why these models: wave 1 stories are narrow tasks with acceptance criteria, a mirror and given copy — the Opus shape; F is a copy proposal, the Sonnet shape. Consolidation is a whole problem with the full context loaded once, a long run and gates at the end — the Fable shape. The train and the matrix are step-by-step with stop points — Opus.

## Wave 1 mechanics

- One worktree per story per repo: `git worktree add ../wt/<repo>-<story> -b story/<n>-<slug>` from `main` (plugin `5cc9d4b`, service `7dc49f1`, pro `cea2e1e`). A service worktree needs its own `pnpm install`; Playwright resolves from a plugin checkout only.
- Each agent: a fresh session, cwd the worktree, `.harness/active.json` filled for that story, the opener below. It reads its mini-spec, runs `bmad-spec` to write the story spec in `_bmad-output/implementation-artifacts/spec-<n>-<slug>.md` (the S25 shape), builds, runs its proof, commits on its branch, writes a handoff, stops. **Nothing else**: no box, no Stripe, no spend, no deploy, no shared file outside its Files line, no `sprint-status.yaml` edit (1c does that).
- Running them: six terminals with the opener, or Nathan says "use a workflow" in one session and it orchestrates the six and collects the proof lines.
- Found-not-done goes in each handoff; scope stays the mini-spec.

Opener, per agent (fill `<n>`, `<slug>`, `<worktree>`):

```
Read plans/finish-the-suite.md (wave 1, story <n> only) and
docs/handoffs/2026-09-20-s25-buyer-walk-on-4-0-0.md. State which model you
are and follow that profile in ~/.claude/harness/model-profiles.md. This
session is story <n> in worktree <worktree>: bmad-spec first, then
bmad-build, test first, the proof line pasted. Stop points: nothing on the
box, ms2 or the real shop; nothing to Stripe; nothing deployed; no file
outside the story's Files line. End with a handoff in docs/handoffs/.
```

## The six mini-specs

### A — FR10: the licence key leaves the support ticket (story 3.2)

- **Files:** `plugin/core/get-help.php` (`vergeml_help_send()`, the `key` line at 217–225); `plugin/readme.txt` (External services, same commit); `service/app/api/support/ticket/route.ts:75–92`; `service/lib/store.ts` (a lookup by prefix + site if none exists); tests: `plugin/tests/…/get-help.php` (new, Playground, `pre_http_request` captures the body), `service/app/api/support/ticket/route.test.ts` or `lib/store.test.ts`.
- **Behaviour:** the ticket body carries the key's prefix (the `…93RJ` shape the account page shows) and the site, never the key; the service resolves the licence from prefix + an activation on that site, and when nothing matches stores the ticket without a licence and says so in `keyNote`, as today; a free install still sends no key; the mail to support names the licence id, never a key.
- **Proof:** plugin suite: the captured `/support/ticket` body has no `key` field and its `licence` field is 4 characters; mutation: put the key back, the suite goes red. Service test: prefix + site → the licence id; prefix + another site → null.
- **Mirror:** `plugin/tests/…` any suite using `pre_http_request` (pro's `compat-free.php` Profile rows); `service/lib/whose.ts` (prefix lookups); `docs/outbound-audit-2026-09-19.md` for the readme line.
- **Copy:** the readme External services sentence — propose, do not write; the `keyNote` strings stay.
- **Do not:** change what else the ticket carries; touch `support/inbound`; send a hash of the key (a hash of a key is a key to us).

### B — FR12: the CSV export cannot execute in a spreadsheet (story 4.1)

- **Files:** `plugin/core/import-csv.php` (`vergeml_csv_line()`, the export at 172–195); `plugin/tests/import/csv.php` (the assertions); `plugin/tools/verify.mjs` — the `csv` entry at 356 is `env: 'box'`, and wave 1 has no box: register a `csv-local` entry with `php: 'wasm'` (the fixture-only local runner, ~13 s) so the proof runs here.
- **Behaviour:** a cell whose first character is `=`, `+`, `-`, `@`, tab or CR is prefixed with a single quote inside the quoted cell (`"'=HYPERLINK(...)"`), the import strips that prefix back, round-trip equal; numbers-as-names (`-5`) survive the round trip; every other cell unchanged.
- **Proof:** `node tools/verify.mjs csv-local` → its own `N/N passed` with a folder named `=HYPERLINK("http://x","x")` exported, re-imported, equal; mutation: remove the prefix, red. (1c re-runs `csv` on the box after the deploy.)
- **Mirror:** the existing CSV round-trip test; OWASP's CSV injection list for the character set.
- **Copy:** none.
- **Do not:** change the BOM, the delimiter, the header row, or the import's error shape.

### C — A subscription's first invoice never lands under Stripe's floor (found in 2.1)

- **Files:** `service/app/api/licence/intent/route.ts` (the coupon branch, 226–236 and 278–280); `service/lib/discounts.ts` (`discounted()` already floors one-offs; a `subscriptionFloor()` beside it); `service/lib/discounts.test.ts`; `service/app/api/pricing/route.ts` (the preview must say the same).
- **Behaviour:** a code on a yearly plan that would leave the first invoice between 1 and 49 cents is refused with `code_minimum_amount` (the cart already has the sentence); exactly 50 cents and above proceed; 0 (a 100 % code) proceeds as today only if Nathan says so — the default is refuse; the pricing preview and the intent agree.
- **Proof:** `pnpm test lib/discounts.test.ts`: 3900 with 99 % → refused, with 3850 off → 50 allowed, with 100 % → the decided answer; mutation: drop the floor, red. No live Stripe call.
- **Mirror:** `discounted()`'s 1–49 band; `eligible()`'s reason shape.
- **Copy:** the cart's existing `code_minimum_amount` sentence — reuse, do not write.
- **Do not:** touch the webhook; call Stripe in a test; change PRIVATE0's row (Nathan's).

### D — Billing shows what was paid; the order page sends a new buyer to registration (found in 2.1)

- **Files:** `service/app/api/account/licence/route.ts:160` (`invoices.list({ customer, limit: 12 })` — the filter goes here, in a pure function beside it); `service/app/account/page.tsx` (the Billing list, ~2067–2140, unchanged if the route filters); `service/app/order/page.tsx:130` (the account link); `service/app/account/page.tsx` (the register form reads `email` from the query).
- **Behaviour:** Billing lists paid invoices and open ones on an *active* subscription; invoices of `incomplete` / `incomplete_expired` subscriptions are not shown; the order page's "Go to your account" goes to `/account?mode=register&email=<address>` when the buyer's address has no account, else `/account`; the registration form pre-fills the address from the query.
- **Proof:** a unit test on the invoice filter (given Stripe's list with statuses, the kept set); Playwright against local dev with the requests mocked: 7 invoices in → 1 shown; the order page link's href for a fresh address; the register form's email value.
- **Mirror:** `scripts/inbox-state.mjs` shape (S25, in the scratch notes: `page.route` mocks); the account page's `sentTo` state (f581f56).
- **Copy:** none new; "Go to your account" stays.
- **Do not:** touch `/api/invoice` (the PDF route); change `whoseLicence`.

### E — Pro's minimum-free-version gate (the 1.0.3 blocker, on the record since epic 1)

- **Files:** `pro/vergelabs-media-library-pro.php` (`vgmlpro_base_plugin_active()`, `vgmlpro_requirements_notice()`, `vgmlpro_ready()`); `pro/tests/compat-free.php` (a row); version bump to 1.0.3 in the header and `VGMLPRO_VERSION` only if Nathan says so in the spec — default: no bump here, the train does it.
- **Behaviour:** a `VGMLPRO_MIN_FREE = '4.0.0'` constant; when `VERGEML_VERSION` is below it, Pro's features stay unloaded, the licence screen still renders, and a notice says the free plugin needs updating — a notice, never a fatal; on 4.0.0 and above nothing changes.
- **Proof:** `compat-free` on the archives leg green as today; a new Playground leg mounting the **3.16.1** free archive under pro HEAD: `vgmlpro_ready()` false, the notice's text present, no fatal, `N/N passed`; mutation: gate removed → the 3.16.1 leg fatals or loads features, red.
- **Mirror:** `vgmlpro_requirements_notice()`; the archives leg in `pro/tools/verify.mjs` (`archives: true`).
- **Copy:** the notice sentence — propose; do not write.
- **Do not:** touch the four touch points; bump the version; change `updates.php`.

### F — The 4.0.1 changelog and readme lines (copy proposals)

- **Files:** none changed; a proposal file `plugin/docs/release-notes-4.0.1-proposal.md`.
- **Behaviour:** three changelog lines (FR10, FR12, the PHP 8.4+ deprecation fix already in the tree since `6dc3223`), the External services sentence for A, and the `Tested up to` line if it moves — each with the evidence it rests on.
- **Proof:** none (copy); the file exists and names its sources.
- **Mirror:** `readme.txt`'s existing changelog voice; `_bmad-output/implementation-artifacts/release-notes-4.0.0.md`.
- **Copy:** all of it is a proposal for Nathan; nothing lands in `readme.txt` here.
- **Do not:** edit `readme.txt`; invent a date.

## Consolidation (1c)

One session, Fable 5.1. Reads: this plan, the six handoffs, the six story specs. Then:

1. Merge the six branches into `main` per repo (A touches plugin and service — two branches); resolve conflicts by the mini-specs' Files lines, never by keeping both.
2. The full batteries, not the six suites: plugin `node tools/verify.mjs` (all local + Playground; the box suites only if the plugin is deployed there, and it is not in this session), Plugin Check on a fresh `git archive`, `archive-hygiene`; service `pnpm test`, `pnpm build`; pro `node tools/verify.mjs` on the archives and `--tree`.
3. `bmad-code-review` over each merged diff; one triage log; patch the confirmed rows; re-run what they touch.
4. `sprint-status.yaml`: 3.2 and 4.1 to `review`, the found items closed in `deferred-work.md`; the copy proposals from A, E, F collected into one block for Nathan.
5. Handoff. Gates: every battery's own summary line pasted; the review's triage table.

Stop points in 1c: any conflict that cannot be resolved from a Files line; the copy block; nothing deploys and nothing is cut here.

## The release train (wave 2, Opus 5, sequential)

Nathan's copy first (the block from 1c). Then, each with its evidence line before the next: readme lines in → version 4.0.1 in the header and `VERGEML_VERSION` → Plugin Check on `git archive` (0 errors) → `archive-hygiene` → `dist/vergelabs-media-library-4.0.1.zip` → tag `v4.0.1`, GitHub release with the zip → `service/public/releases/vergelabs-media-library-4.0.1-<sha12>.zip`, read back over HTTPS → `PLUGIN_RELEASES` (say what goes out, then do it) → redeploy → `/api/health` `free 4.0.1` → release-check by hand. Pro: 1.0.3 the same way from `pro` HEAD after E (the sealed key at rest ships with it — say so in the release text). Rollback is the env step alone (`docs/runbooks/rollback.md`).

## The decision sitting (wave 5)

One document, `docs/decisions-2026-09.md`, one row each: the question, the evidence on file, a recommended answer, Nathan's answer. Rows: 3.1 availability during a service outage (what filing and describing do — today they wait); 3.3 the response time that can be kept solo; 4.3 operator-grade or client-grade fill (the 20,000-pictures-unread question); PRIVATE0 (off, or credits only); the two supplier addresses on the invoice (`vat.md` item 5); the UK VAT row (`vat.md` item 6); the retros — held or recorded. He answers in one pass; the session writes each answer where it lives (`docs/`, `readme.txt`, the code where a decision is a constant).

## What "finished" means

`plans/suite-readiness.md` closed: epics 2–4 `done` in `sprint-status.yaml`, 4.0.1 and Pro 1.0.3 served with the channel's checks agreeing, the buyer walk green on them, the decisions written down, the wordpress.org form sent (Nathan's).

## Cost and time

Wave 1: six agents for a day (about six sessions of tokens in one day; no money; no describe). 1c: half a day. Wave 2: a day with stop points. Wave 3: a day, no money. Wave 4: €0.50 and one credit, on Nathan's go. Waves 5–6: a day. **Three to four calendar days from the go on wave 1**; Nathan's own time about a working day in total (the plan read, elicitation on A and C, the 1c triage, the train's stop points, the sitting).
