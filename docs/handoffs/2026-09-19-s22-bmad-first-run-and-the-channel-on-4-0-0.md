# S22 — BMAD's first run, and the channel serves 4.0.0

**Date:** 2026-09-19. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-19-s21-the-estimate-and-the-small-things.md`. **Plan:**
`plans/suite-readiness.md`, Phase 1. Plugin `65f54af` → `79d6e4c` (seven
commits, all BMAD artefacts and docs); service `5a2fbad` → `4dfff34` (six
commits, the shelf and one test). Nothing spent.

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

## Open, and Nathan's

- **The spec's frozen rollback line** names `catalogue-before.json`, whose
  free `source` is a 404 since the retirement. The usable input is
  `_bmad-output/implementation-artifacts/catalogue-rollback-3.16.1.json`. The
  line is inside `<frozen-after-approval>`; amend or leave.
- **Which 3.16.1 is the rollback target** — the clean re-cut is on the shelf;
  the leaked build is in git at `dd07dd0^`. Overturn if you want the served
  bytes back.
- **The sequencing note in the epics:** FR10 (plaintext key in the ticket)
  and FR12 (CSV formula injection) are both small plugin changes that need a
  release; cutting 4.0.1 with both *before* the wordpress.org form means
  reviewers never see either. Your call on the order.
- **Pro 1.0.3** (sealing + tests, unreleased) and the missing minimum-free
  gate — when.
- **The wordpress.org form** — still yours to send; nothing else reaches
  existing free sites.

## Next — S23: stories 1.2 and 1.3

Both fully specified in `_bmad-output/planning-artifacts/epics.md` (Files,
Behaviour, Proof, Mirror, Copy, Do not, Cost, Stop points); sprint status has
them `backlog`. Run `bmad-build` on each; each opens on one of Nathan's:

- **1.2** — a fresh WordPress on MySQL beside `/var/www/ms2`: does the box
  have room and a **hostname** for it (DNS is Nathan's if it needs one)? Cost
  ≤ €0.05 only if the twenty pictures are described; manual moves cost
  nothing. Whether the fixture stays up afterwards.
- **1.3** — `VGMLPRO_SEATS_KEY` and `VGMLPRO_EXPIRED_KEY` in the environment
  for Pro's box suites (issued by `service/scripts/issue-box-licence.ts`, no
  money); the proof itself is the archive leg in Playground, no cost.

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-19-s22-bmad-first-run-and-the-channel-on-4-0-0.md,
then _bmad-output/planning-artifacts/epics.md (Epic 1, stories 1.2 and 1.3)
and the spine in _bmad-output/planning-artifacts/architecture/. AGENTS.md
loads via CLAUDE.md.

State which model you are and follow that profile in
~/.claude/harness/model-profiles.md.

This session is bmad-build on story 1.2, then 1.3, then bmad-code-review on
each. Stop points: the second site's hostname on the box (1.2); the fixture
staying up (1.2); the two Pro keys in the environment (1.3); nothing on ms2
or the real shop. Cost: say it before any describe run. Test first, one
mutation per story. End with a handoff in docs/handoffs/.
```
