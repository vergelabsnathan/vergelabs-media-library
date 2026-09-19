---
stepsCompleted: [1, 2, 3, 4]
inputDocuments:
  - plans/suite-readiness.md
  - _bmad-output/planning-artifacts/architecture/architecture-plugin-2026-09-19/ARCHITECTURE-SPINE.md
  - AGENTS.md
  - docs/runbooks/rollback.md
---

# vergelabs-media-library - Epic Breakdown

## Overview

The epic and story breakdown for `plans/suite-readiness.md`: the suite around the plugin (service, Pro, release channel, money path, operations) brought level with plugin 4.0.0. No PRD; the plan is the requirements and the architecture spine binds how releases are published and proven.

## Requirements Inventory

### Functional Requirements

FR1: The update channel serves free 4.0.0 — `/api/health` reports `free 4.0.0`, the daily release-check passes, and a Pro site's `/api/plugin/update` answers from the same catalogue. (plan 1.1)
FR2: The 4.0.0 zip is hosted as an immutable versioned static file that the signed download route can 302 to. (plan 1.1, AD-1)
FR3: New free installs get 4.0.0 — a GitHub release for `v4.0.0` carries the archived zip, because the site's install page points at Releases/latest. (AD-4)
FR4: A site on 3.16.1 with existing folders, librarian rows and a Folders session (3.16.1 has no confirmed-tree state; that is 4.0.0's) upgrades to 4.0.0 in place and keeps its folders, term assignments and librarian rows, with the librarian schema 3 → 4 and no lock left. (plan 1.2, AD-5)
FR5: Pro 1.0.2 activates and works against free 4.0.0 — licence page, provenance column, describe path — with Pro's own suites green. (plan 1.3, AD-6)
FR6: The purchase walks end to end as a buyer on 4.0.0: buy → licence issued → connect → describe → credits decrement → invoice. (plan 2.1)
FR7: `CREDITS_MIN` is confirmed absent from Vercel production. (plan 2.2 — answered 2026-09-19: not set; default 500 applies)
FR8: The VAT position is settled with an accountant before real invoicing. (plan 2.3 — not engineering)
FR9: An availability answer exists for filing and describing during a service outage. (plan 3.1)
FR10: The support ticket no longer carries the plaintext licence key. (plan 3.2)
FR11: A response time that can be kept solo is stated. (plan 3.3)
FR12: The CSV export quotes formula-leading cells so a folder named `=HYPERLINK(...)` does not execute. (plan 4.1)
FR13: The 18-cell compatibility matrix and the plugin-conflict set are re-run against 4.0.0. (plan 4.2)
FR14: The fill either stays operator-driven or the screen stops a client filling 20,000 pictures unread. (plan 4.3 — product decision)

### NonFunctional Requirements

NFR1: Test first; one mutation per story; the proof's own output line in every check-in.
NFR2: Every cost in money or model calls is said before it is spent; the buyer walk is a real Stripe payment and starts only on Nathan's go.
NFR3: Nothing runs on ms2, the real shop, or production data that a story does not name; specs restore what they write.
NFR4: Every user-facing string is Nathan's, verbatim; release text is approved before it goes out.
NFR5: A change to what the plugin sends is written into readme.txt's External services in the same commit.
NFR6: A deploy is verified by a content marker unique to the build, never by a status code.
NFR7: Secrets (`PLUGIN_RELEASES`, `DOWNLOAD_SECRET`, `CRON_SECRET`) are read through `vercel env` only and never printed.

### Additional Requirements

- AD-1: artefacts at `service/public/releases/<slug>-<version>-<sha256[:12]>.zip`, never overwritten; the unversioned free zip retired in its own commit after the catalogue stops naming it.
- AD-2: order is zip deploy → read the `Version:` header back over HTTPS → `PLUGIN_RELEASES` → redeploy → `/api/health` → release-check by hand. Rollback is the env step alone.
- AD-3: the free plugin never gets an updater.
- AD-4: the GitHub release's zip is byte-identical to the served one.
- AD-5: the upgrade walk runs on MySQL in a fresh WordPress beside `/var/www/ms2`, Playground as the smoke only.
- AD-6: Pro's contract with free is four touch points; proof is free archive then Pro archive in Playground plus `pro/tools/verify.mjs`.
- Catalogue `source` is always `https://vergelabsmedia.com/releases/<file>`; the channel is proven on `ai.vergelabs.nl`.
- `_bmad-output` and any new top-level directory are `export-ignore`.

### UX Design Requirements

None — no screen changes in Phase 1. Plan 4.3 may produce one later and will get its own mock first.

### FR Coverage Map

FR1: Epic 1 - the channel serves 4.0.0
FR2: Epic 1 - the versioned zip on the service
FR3: Epic 1 - the GitHub release
FR4: Epic 1 - the 3.16.1 → 4.0.0 upgrade walk
FR5: Epic 1 - Pro 1.0.2 against 4.0.0
FR6: Epic 2 - the buyer walk (real payment)
FR7: Epic 2 - CREDITS_MIN (answered 2026-09-19, recorded only)
FR8: Epic 2 - VAT (Nathan + accountant; a story that records the answer)
FR9: Epic 3 - availability answer
FR10: Epic 3 - licence key out of the support ticket
FR11: Epic 3 - response time
FR12: Epic 4 - CSV formula injection
FR13: Epic 4 - compatibility matrix on 4.0.0
FR14: Epic 4 - operator-grade or client-grade (product decision)

## Epic List

### Epic 1: A customer gets 4.0.0 and keeps what they had
Every *new* install is 4.0.0 and Pro's channel serves it; the channel's checks say so; a 3.16.1 site with a 3.16.1 fill and a Folders session upgrades in place and keeps all of it; a Pro customer's plugin still works the morning after. Existing free sites reach 4.0.0 through wordpress.org, which is Nathan's form and outside this epic. Standalone: nothing here needs Epics 2–4.
**FRs covered:** FR1, FR2, FR3, FR4, FR5
**Found, on the record (elicitation 2026-09-19):** Pro checks only that the free plugin is active (`VGMLPRO_REQUIRES`), never `VERGEML_VERSION`. The day a Pro release needs a 4.x function, a free 3.16.x site fatals instead of seeing a notice. A Pro release of its own; not this epic.
**Also on the record:** pro HEAD `a57bc77` (2026-09-12, licence sealed at rest, Pro tests) is unreleased; customers have the `0825e17` build as 1.0.2. A Pro 1.0.3 is waiting and nobody has scheduled it. The one env string `PLUGIN_RELEASES` carries both slugs: a typo in the free entry takes Pro's account download to 409 until the env is flipped back.

### Epic 2: A buyer pays and gets what they paid for
The purchase walked as a buyer on 4.0.0, the credit floor confirmed, the VAT position settled. Uses Epic 1 (the 4.0.0 Pro download). One story is a real Stripe payment and starts only on Nathan's go with the cost said. FR7 is answered (2026-09-19: `CREDITS_MIN` not set in production) and FR8 is Nathan's with an accountant — recorded, no story for either.
**FRs covered:** FR6, FR7, FR8

**Sequencing note (elicitation 2026-09-19, Nathan's call):** FR10 and FR12 are both small plugin changes that need a release. Cutting 4.0.1 with both *before* the wordpress.org form means the first version reviewers see carries neither a known formula injection nor a plaintext key in the ticket. 4.0.0 stays the 1.1 publish — it is cut, checked and tagged.

### Epic 3: What is promised is what can be kept
An availability answer, a response time, and a support ticket that no longer carries the plaintext licence key. Standalone.
**FRs covered:** FR9, FR10, FR11

### Epic 4: A client's library is safe in the plugin's hands
The CSV export cannot execute in a spreadsheet, the compatibility matrix is re-run on 4.0.0, and the fill's grade is decided. Standalone.
**FRs covered:** FR12, FR13, FR14

## Epic 1: A customer gets 4.0.0 and keeps what they had

Every new install is 4.0.0 and Pro's channel serves it; the channel's checks say so; a 3.16.1 site with a 3.16.1 fill upgrades in place and keeps all of it; a Pro customer's plugin still works the morning after. Existing free sites reach 4.0.0 through wordpress.org, outside this epic.

### Story 1.1: The channel serves 4.0.0

As a new customer,
I want the download I am sent to be 4.0.0 and the service's own checks to agree,
So that I do not install four months behind and Pro's channel is never told a version the download cannot honour.

**Acceptance Criteria:**

**Given** `../dist/vergelabs-media-library-4.0.0.zip` (sha256 `bf0d63b70056…`) and the catalogue still naming free 3.16.1
**When** the zip is committed to `service/public/releases/` under its versioned name, deployed, read back, and only then the catalogue is changed and redeployed
**Then** `https://vergelabsmedia.com/releases/vergelabs-media-library-4.0.0-bf0d63b70056.zip` answers 200 with `Version: 4.0.0` in its header and the same sha256
**And** `/api/health` reports `free 4.0.0, pro 1.0.2`
**And** `https://ai.vergelabs.nl/api/plugin/update?slug=vergelabs-media-library&version=3.16.1` answers `update:true` with `package` = that URL
**And** the release-check cron, run by hand, answers 200
**And** GitHub Releases `latest` is `v4.0.0` with the byte-identical zip attached
**And** `service` `pnpm test` is green, and the unversioned `vergelabs-media-library.zip` is retired in a separate commit after the above.

- **Files:** `service/public/releases/vergelabs-media-library-4.0.0-bf0d63b70056.zip` (new); `service/public/releases/vergelabs-media-library.zip` (removed, second commit); Vercel env `PLUGIN_RELEASES` (not a file); `docs/runbooks/rollback.md` (the naming convention, one paragraph); the GitHub release `v4.0.0`.
- **Behaviour:** the six ANDs above, in AD-2's order; rollback is `PLUGIN_RELEASES` back to the previous JSON and nothing else.
- **Proof:** the new `PLUGIN_RELEASES` JSON parsed by the service's own `releases()` (`lib/updates.ts` via tsx, env from a local file) printing both slugs and versions, before `vercel env add`; a node fetch script in the scratchpad printing status, header `Version:` and sha256 of the served zip; `health.mjs` → `free 4.0.0, pro 1.0.2`; the update probe lines for `version=3.16.1` (`update:true`) and `version=4.0.0` (`update:false`); the cron's status line (bearer read from `vercel env pull` into a local file, never printed); `gh release view v4.0.0 --json assets` showing the asset's digest; `pnpm test` summary line in `service/`.
- **Mirror:** Pro's own file `vergelabs-media-library-pro-1.0.2-2a6a794642.zip`; `docs/runbooks/rollback.md:27-70` for the env procedure; `service/app/api/cron/release-check/route.ts:48-70` for how the header is read.
- **Copy:** the catalogue's `changelog` line for free 4.0.0 and the GitHub release title and body — Nathan's; a proposal goes in the check-in before either is used.
- **Do not:** overwrite the unversioned zip; change `PLUGIN_RELEASES` before the served zip's header has been read back; print any env value; re-cut the archive (it is checked and tagged); touch Pro's catalogue entry.
- **Cost:** none.
- **Stop points:** the release text; the moment before `PLUGIN_RELEASES` changes (say what goes out, then do it).

### Story 1.2: A 3.16.1 site upgrades in place and keeps everything

As an existing customer on 3.16.1 with folders, filed pictures and a Folders session,
I want the update to 4.0.0 to keep my folders, my pictures' places, and the record of why each is where it is,
So that update day changes nothing I built.

**Acceptance Criteria:**

**Given** a fresh WordPress on real MySQL beside `/var/www/ms2` on the Hetzner box, with 3.16.1 installed from `../dist/vergelabs-media-library-3.16.1.zip`, about twenty pictures, a small tree, moves made by 3.16.1's own code (so `vergeml_librarian_moves` has schema-3 rows — 3.16.1's `VERGEML_LIBRARIAN_VERSION` is 3, not 1), and a Folders session saved by 3.16.1
**And** `vergeml_librarian_moves` holds at least 5 rows and `vergeml_librarian_batches` at least 1, counted before the swap (an empty table makes "rows intact" vacuous)
**When** 4.0.0 is installed over it with `wp plugin install ../dist/vergelabs-media-library-4.0.0.zip --force` — the `WP_Upgrader` path a customer's Plugins → Upload → Replace takes, never a directory swap — and wp-admin is loaded once
**Then** `vergeml_version` reads `4.0.0`, `vergeml_librarian.schema` reads `4`, and `vergeml_librarian_moves` carries the `source` and `hit` columns with the old rows intact
**And** every term, term assignment and folder count equals the literal-SQL snapshot taken before the swap
**And** the Folders screen loads with the 3.16.1 session and `tree` = `editing` (4.0.0 merges a version-2 session with its fresh shape, `core/guide.php:559`)
**And** no `vergeml_upgrading` option remains and `debug.log` carries no PHP notice from the plugin
**And** Playground has run the same swap first as the smoke, which also covers the never-opened-Folders path (no `vergeml_guide_session`).

- **Files:** `tools/box-upgrade-walk.mjs` (new: install, snapshot by literal SQL, swap, assert — through the node ssh wrapper, one job per call); `tests/compat/upgrade-3161.php` (new: the assertions, run with `wp eval-file` on the new site, counters in `$GLOBALS`); `tools/verify.mjs` (register the suite).
- **Behaviour:** the five ANDs; the site stays up afterwards as the standing upgrade fixture unless Nathan says otherwise.
- **Proof:** `node tools/verify.mjs upgrade-3161` → its own `N/N passed` line; the snapshot diff line `0 differences`; a screenshot of the Folders screen after the swap.
- **Mirror:** `tools/box-shop-untree.php` (freeze and restore by literal SQL); `tests/librarian/gate7-schema.php` (schema assertions); `tools/box-batch-23.php` (option schema against the code constant); `tests/compat/upgrade.js` (the EML walk's shape).
- **Copy:** none.
- **Do not:** touch ms2 or the real shop; use Playground as the proof; compute the snapshot through plugin code; start until the box has room and a hostname for the second site (the story's first step, and Nathan's if it needs DNS).
- **Cost:** if the twenty pictures are described so 3.16.1 files them, about €0.05 (20 × ~€0.002 plus the filing calls), said before the run. Manual moves cost nothing.
- **Stop points:** the hostname; whether the fixture stays up.

### Story 1.3: Pro 1.0.2 works on 4.0.0

As a Pro customer,
I want Pro 1.0.2 to keep working the morning after the free plugin updates,
So that update day does not take my licence page, my provenance column or my describe path with it.

**Acceptance Criteria:**

**Given** the four touch points in AD-6
**When** the free 4.0.0 archive is activated and then the Pro 1.0.2 **archive** (`../dist/vergelabs-media-library-pro-1.0.2.zip`, sha `2a6a7946426f`, the `0825e17` build — not pro HEAD, which carries the unreleased licence sealing) in Playground
**Then** activation succeeds with no fatal, and a Playground suite asserts the four touch points against that archive
**And** Pro's suites on the box (`pro/tools/verify.mjs`: api-base ×4, licence, updates, seats ×3) are run and recorded as **HEAD, not the customer's code** — supporting evidence, not the proof
**And** the media list shows Pro's `vgmlpro_source` column, the licence page renders, and the attachment screen shows provenance for a described picture
**And** `vergeml_index_get()` still returns `alt`, `model`, `locked`, `error`, `described_at` and `vergeml_ai['site_profile']` is still read where Pro reads it
**And** no Pro version bump is made unless a break is found.

- **Files:** `pro/tools/verify.mjs` (a Playground leg that mounts the two **archives**, not the working trees — the existing leg mounts `../plugin` and the pro checkout); `pro/tests/compat-free.php` (new: the four touch points, counters in `$GLOBALS`, `N/N passed`).
- **Behaviour:** the four ANDs; a break found becomes a "found, not done" line with the failing suite's output, not a fix in this story.
- **Proof:** `node tools/verify.mjs` in `pro/` → each suite's `N/N passed` line; `node tools/deploy.mjs --check` in `plugin/` before it, showing up to date; three screenshots (media list column, licence page, attachment provenance).
- **Mirror:** `pro/tools/verify.mjs:164-171` (the blueprint); `plugin/tools/plugin-check.mjs` (mounting an archive in Playground).
- **Copy:** none.
- **Do not:** run a describe on a real library (the provenance screenshot uses an already-described picture); bump Pro; change any of the four touch points in free.
- **Cost:** none — no describe run.
- **Stop points:** `VGMLPRO_SEATS_KEY` and `VGMLPRO_EXPIRED_KEY` are Nathan's to put in the environment (issued by `service/scripts/issue-box-licence.ts`, no money).

## Epic 2: A buyer pays and gets what they paid for

The purchase walked as a buyer on 4.0.0, the credit floor confirmed, the VAT position settled.

### Story 2.1: The buyer walk on 4.0.0

As a buyer,
I want buy → licence issued → connect → describe → credits decrement → invoice to work as one path,
So that I get what I paid for the first time.

**Acceptance Criteria:**

**Given** Stripe live and the 4.0.0 Pro download from Epic 1
**When** a real purchase is made at the smallest plan, with the cost said and Nathan's go given first
**Then** every step in the path shows its own evidence, not a status code.

- **Cost:** a real payment; the amount is said before Nathan's go. Detailed when Epic 1 is done.

### Story 2.2: CREDITS_MIN in production — recorded, no work

Answered 2026-09-19: `CREDITS_MIN` is not set in Vercel production; `service/lib/credits.ts:53` defaults to 500.

### Story 2.3: The VAT position — Nathan's, with an accountant

Not engineering. Recorded so it gates real invoicing visibly.

## Epic 3: What is promised is what can be kept

### Story 3.1: An availability answer

Nathan's decision, recorded in the docs when made.

### Story 3.2: The licence key leaves the support ticket

As a Pro customer,
I want a support ticket to identify me without carrying my working licence key in the clear,
So that a leak of the ticket does not hand out my licence.

**Acceptance Criteria:** to be detailed when sequenced; touches `core/get-help.php` (`vergeml_help_send()`), readme.txt External services in the same commit, and needs a release (see the sequencing note).

### Story 3.3: A response time that can be kept solo

Nathan's decision, recorded when made.

## Epic 4: A client's library is safe in the plugin's hands

### Story 4.1: The CSV export cannot execute in a spreadsheet

Quote or prefix cells that begin with `=`, `+`, `-`, `@`, tab or CR in the folder export; to be detailed when sequenced; needs a release (see the sequencing note).

### Story 4.2: The compatibility matrix on 4.0.0

Re-run the 18 cells and the plugin-conflict set; to be detailed when sequenced.

### Story 4.3: Operator-grade or client-grade

A product decision, Nathan's; a mock before any screen change.
