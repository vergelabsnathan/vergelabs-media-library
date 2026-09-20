# S22 — BMAD's first run, the channel serves 4.0.0, and a 3.16.1 site upgrades in place

**Date:** 2026-09-19. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-19-s21-the-estimate-and-the-small-things.md`. **Plan:**
`plans/suite-readiness.md`, Phase 1. Plugin `65f54af` → `50de59d`; service
`5a2fbad` → `4dfff34` (the shelf and one test). Stories 1.1 and 1.2 done.
Nothing spent.

## The score lines

Not re-taken: no engine, screen or suite in the plugin changed this session.
S21's stand: shop 347 of 581 (60 %) · 72 %; tech 153 of 200 (77 %) · 76 %.

## BMAD, run for the first time

`bmad-help` placed us at the very start: only Project Context existed. The
required chain then ran in this session — architecture `330e010`, epics and
stories `0661ebd`, sprint status `4e490fd`, build + code review of story 1.1
`1ec5963` / `79d6e4c`. Outputs live in `_bmad-output/` (now `export-ignore`,
which it was not — a release zip would have carried it).

How the skills were run against Nathan's lean rule: every menu halt was
kept, but batched to one question per step, and each skill's investigation
was done before any question went out. **Advanced elicitation was chosen at
both offers and found real defects each time** (below); it is not ceremony
here.

### Decisions taken (Nathan, one round)

- Release zips are immutable, versioned static files
  (`<slug>-<version>-<sha256[:12]>.zip`), beside the previous version, never
  overwritten — AD-1.
- The catalogue moves last, after the zip's header and hash are read back
  over HTTPS — AD-2.
- The free plugin never gets an updater (wordpress.org guideline 8) — AD-3.
- Every tagged version gets a GitHub release with the byte-identical zip,
  because the site's install page and the licence email send new installs to
  Releases/latest — AD-4.
- The 3.16.1 → 4.0.0 upgrade proof runs on real MySQL in a fresh WordPress
  beside `/var/www/ms2` on the Hetzner box, Playground as the smoke — AD-5.
- Pro compatibility is the four touch points, proven by activating the
  archives in Playground — AD-6.
- Release text for 4.0.0: the catalogue line and the six-paragraph GitHub
  body, both approved as proposed (`release-notes-4.0.0.md`).

### What the investigation and the elicitation found

1. **The free plugin has no updater.** Only Pro asks the channel. An existing
   free site cannot be told about 4.0.0 by the service at all; wordpress.org
   (Nathan's form) is the only road to them. The plan's "every existing site
   is told it is current" was not the mechanism.
2. **New clients were getting 3.16.0.** The site's Download → `/install` →
   GitHub Releases/latest = v3.16.0. Now v4.0.0.
3. **The customer's Pro 1.0.2 is not pro HEAD.** The served zip (`2a6a7946`)
   is the `0825e17` build; HEAD `a57bc77` carries the licence sealing and the
   Pro tests, unreleased. Pro's box suites run against HEAD and cannot prove
   1.3; a Playground leg with the archives can. A Pro 1.0.3 is waiting.
4. **Pro has no minimum-free-version gate** — `VGMLPRO_REQUIRES` checks the
   basename is active, never `VERGEML_VERSION`. On the record, not this epic.
5. **3.16.1's librarian schema was 3, not 1**, and 3.16.1 already used
   guide-session version 2; 4.0.0 merges an old session with its fresh shape
   (`core/guide.php:559`), so the upgrade walk asserts the Folders screen opens
   on the 3.16.1 session with `tree = editing`. 3.16.1 had no confirmed-tree
   state; the plan's "confirmed tree" is 4.0.0's.
6. **A customer upgrades through `WP_Upgrader`**, so the walk installs with
   `wp plugin install <zip> --force`, never a directory swap.
7. **`CREDITS_MIN` is not set in Vercel production** — plan 2.2 answered.
8. **The served 3.16.1 had leaked `tickets/*.md` and `pnpm-lock.yaml`**
   (`539e4937`, 148 entries, served 09-09 → 09-19). Found by the code review
   when it noticed the 3.16.1 I put back on the shelf (`7f2a4fe9bee9`, the
   clean `dist` re-cut, 142 entries) was not the bytes that had been served.
   The clean one is the rollback target, by my decision; the runbook says so.

## Story 1.1 — the channel serves 4.0.0 (done)

In AD-2's order: service `5e1f5e9` put
`public/releases/vergelabs-media-library-4.0.0-bf0d63b70056.zip` up; read
back **`200 · sha256 bf0d63b70056 · 1,101,616 · Version: 4.0.0`**; the new
catalogue parsed by the service's own `releases()` (both slugs, `isNewer`
true/false as expected, a deliberate typo → `serving no updates`); the JSON
shown, then `vercel env rm/add` and a redeploy (aliased in 50 s). After:
health **`free 4.0.0, pro 1.0.2`**; `version=3.16.1 → update:true` with the
versioned package; `version=4.0.0 → update:false`; Pro 1.0.2 untouched;
release-check cron **`200 ok:true`** for both slugs. GitHub `v4.0.0` is
latest with `vergelabs-media-library.zip`, digest `bf0d63b70056…` (gh's
`#label` only sets a label; the asset was re-uploaded under the right name).
`dd07dd0` retired the unversioned zip; `3eb0b9b` put 3.16.1 back versioned;
`6c65233` restored Pro 1.0.1 and added `lib/release-files.test.ts` (every zip
on the shelf named after its own bytes; a 1.0.3-named 1.0.1 fails it —
tried); `4dfff34` fixed the type error that made `6c65233`'s deploy error in
13 s (production stayed on `3eb0b9b` meanwhile). `pnpm test` **511 passed,
14 skipped**. `promote.mjs`: both customer hostnames on `4dfff34`.

Two review passes, 26 findings triaged in the spec's log. Deferred
(`deferred-work.md`): Nathan's `index-v2-backup.html` still links the gone
unversioned zip; `promote.mjs` could HEAD every catalogue source and produce
the versioned file name.

`docs/runbooks/rollback.md` now carries the naming rule, which 3.16.1 is on
the shelf and why, the one-liner that actually runs (`m.default.releases`
under `tsx -e`), the GitHub step in a free pull-back, and the `gh` commands
that exist.

## Story 1.2 — a 3.16.1 site upgrades in place and keeps everything (done)

Nathan: "the hostname is just the box" → the box's own nip.io pattern, so the
fixture is **`upg.46.225.66.194.nip.io`** (`/var/www/upg`, MySQL `wpupg`,
admin `vgmls22`, password in `/root/.upg-admin-pass` on the box), made by
`tools/box-upgrade-site.sh` (`create | plugin <zip> | reset | destroy`). It
stays up (Nathan) and `reset` re-arms it for the next schema bump.

The walk: 3.16.1 in through `wp plugin install`, the state built by **3.16.1's
own code** in mock mode — 20 pictures, 3 folders, 20 mock-described (with alt
texts), 6 filed through `vergeml_autofile_file(…, 'accepted')` (1 batch, 6
moves), a Folders session with two turns, a summary and a draft through
`vergeml_guide_clean_draft` — frozen by literal SQL
(`tests/compat/upgrade-3161-snapshot.php`, nine tables in every column,
embeddings by hash, three options), then **`wp plugin install …4.0.0.zip
--force`** (the `WP_Upgrader` path a customer's Upload → Replace takes), one
admin request, then `node tools/verify.mjs upgrade-3161` → **31/31 passed**:
schema 3 → 4 with `source` and `hit`, the six old rows at their defaults,
**0 differences** row for row, the session opening with `tree = editing`, no
lock, the Folders screen **200 logged in**. Playground first as the smoke
(`upgrade-3161-smoke`, PHP 8.5 against the tree's own zip): **27/27, 0
differences**. Mutations: one alt text altered → red on that row; the
`ai.php` fix reverted → the smoke red on the exact line.

Found by the walk: **4.0.0 on PHP 8.4+ logged a deprecation on every request**
(`vergeml_ai_rest_status()` implicit-nullable, `core/ai.php:1995`, the only
such site) — fixed in the tree, PHP 8.5's linter 2 → 0, ships with 4.0.1; the
fixture runs the 4.0.0 archive so its suite names that one line as known until
then. And **4.0.0's Folders screen answers 500 on a malformed draft** (a draft
of strings, `guide.php:1255`) — 3.16.1 never writes that shape, so no customer
path reaches it; on the record in `deferred-work.md`. The plan's "schema
1 → 4" was 3 → 4, and its "confirmed tree" is 4.0.0's, not 3.16.1's.

Two review passes, 40 findings triaged in the spec; commits `6dc3223`,
`8f9bf53`, `68fd127`, `50de59d`. Screenshot
`docs/superpowers/mocks/shots/2026-09-19-upgrade-3161-folders.png`.

## Open, and Nathan's

- **The spec's frozen rollback line** names `catalogue-before.json`, whose
  free `source` is a 404 since the retirement. The usable input is
  `_bmad-output/implementation-artifacts/catalogue-rollback-3.16.1.json`. The
  line is inside `<frozen-after-approval>`; amend or leave.
- **Which 3.16.1 is the rollback target** — the clean re-cut is on the shelf;
  the leaked build is in git at `dd07dd0^`. Overturn if you want the served
  bytes back.
- **The 4.0.1 cut, before the wordpress.org form:** FR10 (plaintext key in
  the ticket), FR12 (CSV formula injection) and now the PHP 8.4+ deprecation
  fix already in the tree. Your call on the order; the changelog line is yours.
- **Pro 1.0.3** (sealing + tests, unreleased) and the missing minimum-free
  gate — when.
- **The wordpress.org form** — still yours to send; nothing else reaches
  existing free sites.

## Next — S23: story 1.3

Spec approved by Nathan on 2026-09-20 and `ready-for-dev`:
`_bmad-output/implementation-artifacts/spec-1-3-pro-1-0-2-works-on-4-0-0.md`
— `bmad-build` resumes it at implementation. The two keys were issued
(licences 14 single, 15 expired, `box@vergelabs.nl`) and live only in
Nathan's terminal and the S22 conversation: paste them into the new session
as `VGMLPRO_SEATS_KEY` / `VGMLPRO_EXPIRED_KEY`, never into a file in the repo. Its stop
point: **`VGMLPRO_SEATS_KEY` and `VGMLPRO_EXPIRED_KEY` in the environment** for
Pro's box suites (issued by `service/scripts/issue-box-licence.ts`, a prod-DB
step — Nathan's); the proof itself is the archive leg in Playground (free
4.0.0 archive, then the Pro 1.0.2 archive `2a6a7946426f` — the `0825e17` build
customers have, not pro HEAD), no cost. The fixture from 1.2 is a fine place
for a Pro-on-4.0.0 box check too (`upg`, mock mode).

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-19-s22-bmad-first-run-and-the-channel-on-4-0-0.md,
then _bmad-output/implementation-artifacts/spec-1-3-pro-1-0-2-works-on-4-0-0.md
(approved, ready-for-dev) and _bmad-output/planning-artifacts/epics.md (Epic 1, story 1.3)
and the spine in _bmad-output/planning-artifacts/architecture/. AGENTS.md
loads via CLAUDE.md.

State which model you are and follow that profile in
~/.claude/harness/model-profiles.md.

This session is bmad-build on story 1.3 (resume the ready-for-dev spec), then
bmad-code-review. VGMLPRO_SEATS_KEY=<paste> VGMLPRO_EXPIRED_KEY=<paste> are
in this message, not in any file. Stop points: nothing on ms2 or the
real shop; no describe run on a real library. Cost: none expected; say it if
that changes. Test first, one mutation per story. End with a handoff in
docs/handoffs/.
```
