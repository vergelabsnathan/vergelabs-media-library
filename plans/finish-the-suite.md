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

## Consolidation 1c — done 2026-09-20 (Opus 5, same session as wave 1)

Merged, no conflicts: plugin `e932d62` (F `1f4780f`, B `f510bf2`+`6d85510`, A `e9bd6b2`+`fcad5ea`+`48931c5`), service `40c33e4` (A `2bbd3f1`, D `eb1d314`+`a39d8c1`), pro `5f8ffb9` (E `9fa975f`+`aaa44a5`). Batteries before review: service `534 passed`, tsc, build; plugin `get-help 22/22`, `csv-local 69/69`, `archive-hygiene 8/8` after the zip re-cut (`c93e7de`); pro `min-free 15/15`, `min-free-current 15/15`.

Review: four lenses over the combined diff (2,566 lines) — blind hunter 14, edge-case hunter 13, verification gap 2 + 2, acceptance auditor 12. Triage (`patch` = done in `b75aed9` service, `a850512` plugin, `da04526` pro; `defer` = `deferred-work.md`; `reject` with the reason):

| # | Finding | Verdict | Route |
|---|---|---|---|
| 1 | D · Billing's `expand: ['data.subscription']` is rejected on API versions from 2025-03-31; the catch turns the tab dark and the newer-shape reader is unreachable | medium | patch: `subscriptionExpand(STRIPE_API_VERSION)` from the exported pin; test |
| 2 | D · `limit: 12` before the filter hides a paid invoice behind twelve abandoned checkouts | medium | patch: 50 asked, 12 shown |
| 3 | D · `has_account` and the order page's link have no test | medium | patch: `app/api/order/route.test.ts`, 4 rows |
| 4 | D · the buyer's email in the registration URL (history, logs, referrers) | medium | patch: the register form reads the cart's `localStorage vgml-email`; the link carries no address |
| 5 | D · "open on an active subscription" widened to "not incomplete" (canceled, paused pass) | medium | patch: `OWED_ON` = active, past_due, unpaid, trialing; test |
| 6 | D · a hand-issued open invoice (no subscription) vanished from Billing | medium | patch: shown; test |
| 7 | D · `meta.email === ''` passes through and builds a register link | low | patch: empty is absent; test |
| 8 | D · the enumeration comment overclaims | low | patch: comment |
| 9 | D · the Playwright proof is not in the repo | low | defer: the route test pins the boolean; an `/order` e2e row in `deferred-work.md` |
| 10 | A · a key shorter than four characters would be sent whole; lowercase last-four rejected by the service's regex | low | patch: `strlen > 4`, `strtoupper` |
| 11 | A · a malformed prefix reads as a free install in the support note | low | patch: "a licence prefix was sent in a shape we do not issue"; test |
| 12 | A · prefix + seat fails for a moved origin, staging, a network subsite (the old full-key path resolved regardless of site) | medium | patch the note with the count of licences ending that way; **defer** the unique-prefix fallback — a privacy call, Nathan's |
| 13 | A · spec tasks unticked, no implementation notes | low | patch: ticked, notes with the proof and mutation lines |
| 14 | A · `docs/outbound-audit-2026-09-19.md:46` still says plaintext key | low | patch: dated note in the row |
| 15 | A/F · the readme sentence was written (plan Files) vs proposed (plan Copy); F proposed a different one | low | patch F to what landed; the words stay Nathan's at the train (copy block below) |
| 16 | A · readme lacks a 4.0.1 changelog entry | — | reject: the train's, from Nathan's copy |
| 17 | A · multisite network key in the suite | low | reject: fixture-only suite |
| 18 | B · the filename column is prefixed too (`'-01.jpg`) | low | reject: every cell is protected by design; recorded |
| 19 | B · a 4.0.0 export with a folder literally named `'=x` loses an apostrophe on import | low | defer |
| 20 | B · the box suite never exports a trigger folder | low | defer: one fixture folder at the next box run |
| 21 | B · the plan's "re-imported, equal" is met at cell level; `vergeml_csv_path()`'s `/`→`-` is pre-existing | — | reject: documented in the spec's matrix |
| 22 | E · `min-free.php` clears the site's schedule and backoff unconditionally; a text match on "Warning" | medium | patch: snapshot and put back; `Fatal error` only |
| 23 | E · the notice assertion is pinned to the sentence Nathan will edit | medium | patch: anchored on `VGMLPRO_MIN_FREE` |
| 24 | E · a `4.0.0-beta` free version reads as below | low | reject: the free plugin ships no pre-release tags |
| 25 | E · in-page sentence for "active, too old"; pro readme floor; `Requires Plugins` header | low | defer (copy block) |
| 26 | E · `compat-free --tree` with a real key not run | — | defer: the train's, with `VGMLPRO_SEATS_KEY` |
| 27 | verify.mjs · `runPhpPlayground` collapses exit 2 into FAILED; judging duplicated from `runPhpLocal` | low | patch exit 2 → SKIPPED; defer the dedupe |
| 28 | Files outside the stories' Files lines (`secrets.php` E, `verify.mjs` by A, `.gitattributes` by E, the order route by D) | — | reject: each is the story's proof or the plan's own gap; no conflict at the merge |
| 29 | D/E specs under `docs/specs/` not `_bmad-output` | — | reject: those repos carry no `_bmad-output`; the plugin's sprint status tracks 3.2 and 4.1 |

After the patches: service `541 passed`, tsc, build; plugin `get-help 22/22`, `csv-local 69/69`, `archive-hygiene 8/8`; pro `15/15` × 2. Service pushed `b75aed9`. Nothing cut, nothing on the box, nothing to Stripe.

## The release train — done 2026-09-20 (Opus 5, S26)

Copy approved "as in the copy block"; say-and-do, no pauses; the one-seat key minted by the session. Evidence, in the train's order:

- **4.0.1.** Plugin `657b257` (readme: Stable tag, the 4.0.1 changelog — strapline and three lines verbatim from the proposal; header and `VERGEML_VERSION`), `b6a89f1` (Playground zip). Plugin Check on `git archive` of `657b257` in Playground, PHP 8.3, all five categories: **0 errors, the 2 known warnings**. `archive-hygiene` **8/8** (143 / 136 entries, same files). Tag `v4.0.1` on `b6a89f1`; `dist/vergelabs-media-library-4.0.1.zip` = `git archive v4.0.1 --format=zip`, **143 entries, 1,100,286 bytes, sha256 `c5510da6b16a`**, header `4.0.1`, no hidden entry. GitHub release `v4.0.1` **latest**, asset `vergelabs-media-library.zip` read back `200 · c5510da6b16a`. Service `38a7ed5` put `public/releases/vergelabs-media-library-4.0.1-c5510da6b16a.zip` on the shelf, `pnpm test` **542 passed**; HTTPS read-back **`200 · sha256 c5510da6b16a · 1,100,286 · Version: 4.0.1 · 143 entries`**. Catalogue parsed by `releases()` (both rows), env swapped 18:39:16 → :24 UTC, redeploy aliased in 45 s, `promote.mjs` both hostnames. Health **`free 4.0.1, pro 1.0.2`**; `version=4.0.0 → update:true` with the versioned package, `4.0.1 → update:false`; release-check **`200 ok:true`** both slugs. Box: `csv` **33/33**, `secrets` **80/80** after a suite fix (`c6a8d21`: the mock-on half set mock on instead of assuming it; the box runs real mode now; restored to 0 after).
- **Pro 1.0.3.** Pro `5d3c47d` (readme: Stable tag, `= 1.0.3 =` — the approved gate line plus one line for the sealed key, the Description names the 4.0.0 floor; header and `VGMLPRO_VERSION`). `min-free` / `min-free-current` **15/15 × 2**. Licence **20** (single, box@vergelabs.nl, 2,000 credits) issued by `issue-box-licence.ts` from a pulled env, deleted after; `compat-free` archives **24/24**, `compat-free --tree` (free 4.0.1 tree, pro 1.0.3 tree) **23/23**, seat 1 → 0. Tag `v1.0.3`; `dist/vergelabs-media-library-pro-1.0.3.zip` **8 files, sha256 `9bb6c8ff5088`**, header `1.0.3`. Service `058bfe5` shelf, release-files **9 passed**; read-back **`200 · 9bb6c8ff5088 · 26,380 · Version: 1.0.3`**. Env swapped 18:53:58 → 18:54:05 UTC, redeploy 47 s, promoted. Health **`free 4.0.1, pro 1.0.3`**; Pro `1.0.2 → update:true` (`licence_required` without a key, as designed), `1.0.3 → update:false`; release-check **`200 ok:true`** both.
- Not done, on record: `box.yml` deployed the plugin to the box on the `main` push (18:34 UTC) before the read-back (18:37) — `deferred-work.md`; `Requires Plugins` header left out (the free plugin is not on wordpress.org, so WordPress's install link would dead-end); nothing retired from the shelf.

## Wave 3 — done 2026-09-20 (Opus 5, S27): the matrix on 4.0.1

Spec `_bmad-output/implementation-artifacts/spec-4-2-the-compatibility-matrix-on-4-0-0.md` (the defaults recorded, one pre-mortem in place of the elicitation Nathan pre-answered). `tools/matrix.mjs --parallel 3` over the 18 cells on the Playground zip `66814aa2d056` (file-for-file the same 136 files as `dist/vergelabs-media-library-4.0.1.zip`; the box up to date at `e3ac8dc`). Mock on in every cell, no key anywhere, nothing spent. Result: **15 ✓, 2 not run, 1 ✗** — `docs/compatibility.md` dated 2026-09-20. Not run: FileBird on Playground (SQLite, as before) and the subdomain network (`/var/www/ms2` is the shop library; the stop point). The ✗: **beside FileBird 6.5.8 on MariaDB a single unselected row dragged onto a folder files nothing** (the checked-rows drag does); reproduced three times, mock on and off; the 09-11 bytes passed it — a story of its own, `deferred-work.md`. Two runner defects found and fixed on the way (the box mock write ran in `/root`; two of three 6.5 cells started together never booted — rerun alone, ✓ both). Review: four lenses, 22 rows in the spec's triage log, 12 patched (`9d59493`), 4 deferred, 6 rejected. `sprint-status.yaml` 4.2 → `review`. Plugin `e3ac8dc` → `9d59493` + the handoff; nothing pushed.

## Story 4.4 — done 2026-09-21 (Opus 5, S28): a single row drags into a folder beside FileBird

Between waves 3 and 4 on the S27 recommendation. Spec `_bmad-output/implementation-artifacts/spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md`. The cause was measured before the spec (a probe on `/var/www/ms`, jQuery UI instrumented): not the arming — `e68bbcd` (09-14) made `core/media-list.php` size File at 30 %/40 % once a column beyond core's is visible, every column was then sized, and the fixed table gave its slack to the checkbox column (**324 px of 900** beside FileBird's one column; **137 px of 982** with two of ours on), whose full-cell label is FileBird's drag handle. Fix, two shipped files: File is `auto`; the share waits behind `body.vgml-file-share`, which `js/vergeml-media-list.js` adds in the frame after load only when File measures below its share. Proof on the box: the FileBird cell **✓ 10/10** (was 7/10), `drag.mjs` beside FileBird through the new `tools/box-drag-beside.mjs` **21/21** (was 7/19) and 21/21 alone, modes.spec's row test **2 passed** with a fourth set (two of ours), the checkbox 34 px and the press on the title cell in every set, the share class where predicted. Review: four lenses, 21 rows in the spec's triage log, 8 patched, 2 deferred, 11 rejected. `sprint-status.yaml` 4.4 → `review`; nothing pushed, no version bump — 4.0.2 material for the train. On record: an unintended 21-minute run of the whole UI suite on the tech site (a shell split the `-g` pattern), put right and costed in the spec's notes.

## Story 4.5 — done 2026-09-21 (Opus 5, S28b): our folders take only our own drag; File's share follows a live tick

4.4's two open questions, Nathan's go, before the 4.0.2 cut. Spec `_bmad-output/implementation-artifacts/spec-4-5-our-folders-take-only-our-own-drag.md`. Our droppables read the drag under way in `over`/`drop` and take only our helper (`js/vergeml-tree.js` `ownDrag()`): beside FileBird its drag from the checkbox neither lights our folder nor drops. File's share is decided by the script from the head cells (30 beside another plugin's columns, 40 among ours, 0 none), PHP emits both rules, and a Screen Options tick re-measures. Found on the way and fixed: since 09-10 the tree's positioned `.wrap` covered core's Screen Options and Help tabs on the list screen (one rule, `css/vergeml-tree.css`). Proof on the box: harness beside FileBird **21/21** + the label step (helper `fb-v-dragging`, not lit, not filed), alone **21/21** (ours, lit, filed), `reparent.mjs` **12/12**, the FileBird cell **✓ 10/10**, modes.spec **2 passed** with the class per set and the live tick (`-30` on at 322/1082, off at 554). Review: 20 rows, 14 patched, 0 deferred, 6 rejected (`accept` → the `over`/`drop` guard was the review's). `sprint-status.yaml` 4.5 → `review`; 4.0.2 material.

## The release train, 4.0.2 — done 2026-09-21 (Opus 5, S29)

Stories 4.4 and 4.5 and the Screen Options fix, from `c1056a1` (HEAD `8e71f2e` differed only in export-ignored files). Copy: the S28b handoff's two lines verbatim plus a strapline, approved "do what's best"; the strapline is the catalogue's line and the release's first line. Evidence, in the train's order: plugin `41ec54a` (readme: Stable tag, the 4.0.2 changelog; header and `VERGEML_VERSION`), `0e03cd4` (Playground zip); `archive-hygiene` **8/8** (143 / 136, same files); Plugin Check on `git archive` of `0e03cd4` in Playground (CLI pinned to 3.1.54 — 3.1.55 will not install), PHP 8.3, five categories: **0 errors, the 2 known warnings**. Tag `v4.0.2` on `0e03cd4`; `dist/vergelabs-media-library-4.0.2.zip` = `git archive v4.0.2`, **143 entries, 1,102,302 bytes, sha256 `d57c3020567e`**, header `4.0.2`, no hidden entry. Plugin `main` pushed as a merge over the watch's `d92a798` (`9b121f0`; a rebase would have moved the tagged commit); `box.yml` put it on the box (`deploy.mjs --check`: 136 files verified). GitHub release `v4.0.2` **latest**, asset read back `200 · d57c3020567e · 1,102,302 · Version: 4.0.2 · 143 entries`. Service: the shelf commit `fc940b9` made in a worktree at `origin/main` and pushed as `main` alone (the checkout carries Nathan's unpushed pricing commits and migration 020); `release-files` **10 passed**, `pnpm test` **544 passed | 14 skipped**; HTTPS read-back the same line. Catalogue parsed by `releases()` (both rows; Pro's unchanged), env swapped 11:34:12 → :20 UTC, redeploy `6d65ofa17` Ready in 43 s, `promote.mjs` both hostnames. Health **`free 4.0.2, pro 1.0.3`**; `4.0.1 → update:true` with the versioned package, `4.0.2 → update:false`; release-check **`200 ok:true`** both slugs. Nothing spent; nothing on ms2 or the real shop. `sprint-status.yaml` 4.4 and 4.5 → `done`.

## Wave 4 — the walk on 4.0.2 + Pro 1.0.3, done in two halves 2026-09-21 (Opus 5, S29b)

**Spent: €0.50 on Nathan's card (refunded by him), 1 credit of a box-issued row.** Money half, live: cart with `WALK0921` → checkout **Pay €0.50** → `pi_3UI5jVENqcXgcnrD176cYtNA` succeeded 11:51:54 UTC, invoice **AWTXBCFC-0020** paid €0.50 (coupon `once`, tax `not_supported`), `sub_…SGaGPppU` active to 2027-09-21 → `/order` "Your One site licence is ready", key on the page → licence mail → registration → confirmation → `/account` **Download Pro 1.0.3 (zip)** (downloaded, in Nathan's own browser). Then, unscheduled: Nathan pressed **Refund** in the Stripe Dashboard at 11:56:22; `moneyBack()` cancelled the subscription at 11:56:24 and the account showed *Canceled*, download off — the money-back path, proven live. `WALK0921` applied a second time on a fresh checkout (no redemption is written for subscriptions — S25's finding, confirmed; that checkout abandoned, no charge). Site half, no money: licence **22** (single, `box@vergelabs.nl`, 2,000) by `issue-box-licence.ts`; `upg` on free **4.0.2** (installed from the shelf, `d57c3020567e`) + Pro **1.0.3** from the served zip (`9bb6c8ff5088`, the account route's file); `connect` **1 / 1, 2,000**, key sealed (95 chars); `describe` picture 23 in 14.4 s, a real alt and caption, **We wrote this**, **2,000 → 1,999**, no new debug line; `restore` seat **0/1**, key absent, Pro inactive, rows **0 differences**, 0 Pro lines. Not walked: `final` (the script's window never held the session; S25's cancel-at-period-end stands, D's Billing filter has its route test). Shots `2026-09-21-buyer-walk-01…03, 10…13`. Three rows in `deferred-work.md` (the walk tool's window premise, the unpinned Playground CLI, the box rows).

### Copy block for Nathan (approved 2026-09-20 "as in the copy block"; shipped in 4.0.1 / Pro 1.0.3)

1. `readme.txt:183`, External services, as landed by A: "…the email address you gave, the last four characters of your licence key if you have one (never the key itself), and a full system report: …".
2. `readme.txt` changelog 4.0.1, proposed in `docs/release-notes-4.0.1-proposal.md` (three lines: the ticket, the CSV cells, the PHP 8.4+ notice) and a two-line strapline.
3. Pro's notice, as landed by E: "VergeLabs Media Library Pro needs VergeLabs Media Library 4.0.0 or newer. This site has 3.16.1, so Pro features are paused until the free plugin is updated." (`%1$s` the minimum, `%2$s` the version found; "an older version" when unknown). Changelog line for Pro 1.0.3: "Pro now needs VergeLabs Media Library 4.0.0 or newer. On an older free plugin it pauses its features and says so, instead of failing."
4. Pro readme: name the 4.0.0 floor in the Description; optionally the `Requires Plugins: vergelabs-media-library` header.
5. The service's support note strings (`a licence prefix was sent in a shape we do not issue`, `(N licences end that way)`) — internal mail to support, not customer-facing; yours all the same.
