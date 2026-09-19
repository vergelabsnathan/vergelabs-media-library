---
title: 'The channel serves 4.0.0'
type: 'chore'
created: '2026-09-19'
status: 'done'
baseline_commit: 'plugin 4e490fd / service 5a2fbad'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
  - '{project-root}/docs/runbooks/rollback.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Plugin 4.0.0 is cut, tagged and checked, but the service catalogue still says free 3.16.1 (`/api/health` 2026-09-19), the served zip is 3.16.1, and the site's install page sends new customers to GitHub Releases/latest, which is v3.16.0. Every new install is months behind and the service's own checks are certifying the wrong version.

**Approach:** Publish the 4.0.0 archive as an immutable versioned static file on the service, read it back, then move the catalogue to it and redeploy; publish the same bytes as GitHub release `v4.0.0`; retire the unversioned zip afterwards in its own commit.

## Boundaries & Constraints

**Always:** AD-2's order — zip deployed → header and sha256 read back over HTTPS → `PLUGIN_RELEASES` changed → redeploy → `/api/health` → release-check by hand. The new catalogue JSON is parsed by the service's own `releases()` before it is set. Pro's row is copied through unchanged. Secrets are read via `vercel env pull` into the scratchpad and deleted; nothing but slugs, versions and URLs is printed. Rollback is `PLUGIN_RELEASES` back to `catalogue-before.json` and a redeploy — nothing else.

**Never:** overwrite `vergelabs-media-library.zip`; re-cut the archive; change the free row's `paid`, `requiresWp`, `requiresPhp` or `testedWp`; use release text Nathan has not approved; claim the deploy is live from a status code.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Served zip | GET `/releases/vergelabs-media-library-4.0.0-bf0d63b70056.zip` | 200, `Version: 4.0.0` in `vergelabs-media-library/vergelabs-media-library.php`, sha256 `bf0d63b70056…` | anything else: stop before the env step |
| Old site asks | `/api/plugin/update?slug=vergelabs-media-library&version=3.16.1` on `ai.vergelabs.nl` | `update:true`, `package` = the new URL, `version` `4.0.0` | — |
| Current site asks | same with `version=4.0.0` | `update:false` | — |
| Health | `/api/health` after redeploy | 200, `releases` detail `free 4.0.0, pro 1.0.2` | 503: roll the env back |
| Cron | `/api/cron/release-check` with the bearer | 200 `ok:true` | 503: roll the env back |
| GitHub | `gh release view v4.0.0` | latest, one asset, digest = the served sha256 | — |
| Bad JSON | a typo in the new catalogue | caught by the local parse (`releases()` returns `[]`) before `vercel env add` | never reaches production |

**Decisions recorded at approval:** the GitHub asset is named `vergelabs-media-library.zip`, as every previous release's asset is; the catalogue changelog line is `4.0.0: the Folders screen builds your tree and fills it, filing reads what a picture shows, and a shop's product categories become its folders.`; the GitHub release title is `VergeLabs Media Library 4.0.0 — a screen that builds your folders and fills them` and its body is the six-paragraph proposal approved by Nathan on 2026-09-19 (stored as `release-notes-4.0.0.md` beside this spec).

</frozen-after-approval>

## Code Map

- `service/lib/updates.ts:131-176` -- `Release` shape and `releases()` parser; the local parse before `vercel env add` uses this, via `npx tsx -e`.
- `service/app/api/plugin/update/route.ts:57-59` -- free row: `update:true, package: release.source`, no licence. Do not touch.
- `service/app/api/cron/release-check/route.ts:48-70` -- reads `Version:` from `<slug>/<slug>.php` inside each `source`; the read-back script mirrors this.
- `service/app/api/health/route.ts:90-106` -- `releases` detail string `free X, pro Y`.
- `service/public/releases/` -- static artefacts; `vergelabs-media-library-pro-1.0.2-2a6a794642.zip` is the naming mirror; `vergelabs-media-library.zip` (3.16.1) is retired in the second commit.
- `service/public/index-v2-backup.html:678,997` -- the only references to the unversioned zip; a backup page, not served. Leave it.
- `../dist/vergelabs-media-library-4.0.0.zip` -- the source bytes, sha256 `bf0d63b70056ae47d159b8611ce5ed9adeec4e0ad0b15d3c9e423cdc86886a31`.
- `docs/runbooks/rollback.md:22-25,34-37` -- says the free plugin "only ships as `vergelabs-media-library.zip`" and to keep the previous zip beside the current; update to the versioned naming.
- scratchpad `catalogue-before.json` -- the current catalogue, saved 2026-09-19; the rollback input.
- Service deploys on push to `main` (four production builds today, 22–32 s); `vercel redeploy <url> --target production` after the env change.

## Tasks & Acceptance

**Execution:**
- [x] `service/public/releases/vergelabs-media-library-4.0.0-bf0d63b70056.zip` -- copy from `../dist`, verify sha256, commit `chore(releases): 4.0.0 served under its versioned name`, push -- AD-1.
- [x] scratchpad `readback.mjs` -- fetch the served URL, print status, `Version:` from inside the zip, sha256; run until it matches (edge cache) -- AD-2 read-back.
- [x] scratchpad `catalogue-after.json` -- the before file with the free row's `version`, `source`, `changelog` changed; parse it with `releases()` from `lib/updates.ts` and print slug@version -- the bad-JSON guard.
- [x] Vercel `PLUGIN_RELEASES` -- `vercel env rm … -y` then `vercel env add … < catalogue-after.json`, then `vercel redeploy` -- say the JSON first (stop point).
- [x] probes -- `health.mjs`, `hosts.mjs` (`version=3.16.1` and `4.0.0`), cron with bearer from a pulled env file (deleted after) -- the read lines pasted.
- [x] GitHub -- `gh release create v4.0.0 ../dist/vergelabs-media-library-4.0.0.zip#vergelabs-media-library.zip --title … --notes-file …` with the approved text; `gh release view v4.0.0 --json assets,isLatest` -- AD-4.
- [x] `service/public/releases/vergelabs-media-library.zip` -- remove, second commit `chore(releases): the unversioned 3.16.1 zip retired`, push, re-run `health.mjs` and `pnpm test`.
- [x] `docs/runbooks/rollback.md` -- the naming convention and "keep the previous zip beside the current" in one paragraph; commit in the plugin repo.

**Acceptance Criteria:**
- Given the deploy of the zip, when it is fetched, then the header says 4.0.0 and the sha256 matches before any env change is made.
- Given the redeploy, when health, both update probes and the cron are read, then all four lines say 4.0.0 and 200.
- Given the GitHub release, when `latest` is viewed, then it is v4.0.0 with one asset whose digest equals the served file's.
- Given the retirement commit, when `pnpm test` runs in `service/`, then it is green and health still says `free 4.0.0`.

## Implementation Notes

- 2026-09-19 13:00–13:05Z. service `5e1f5e9` (zip), `dd07dd0` (retirement); production deploys `dz4ectxrb` (zip), redeploy after the env change aliased to ai.vergelabs.nl in 50 s, `8v4ytzfk2` (retirement).
- Read-back before the flip: `status 200 · sha256 bf0d63b70056 · length 1101616 · Version: 4.0.0`.
- Local parse of the new catalogue: `vergelabs-media-library@4.0.0 paid=false | vergelabs-media-library-pro@1.0.2 paid=true`, `isNewer(4.0.0, 3.16.1) true`, `isNewer(4.0.0, 4.0.0) false`; a deliberate typo parsed to `serving no updates` (matrix row Bad JSON).
- After the redeploy: health `free 4.0.0, pro 1.0.2`; `version=3.16.1 → update=true package=…4.0.0-bf0d63b70056.zip`; `version=4.0.0 → update=false`; Pro `1.0.2 → update=false`; cron `200 ok:true`, both slugs "the catalogue and the package agree". Env file pulled for the bearer was deleted after the call.
- GitHub: `v4.0.0` is latest; the asset first landed as `vergelabs-media-library-4.0.0.zip` (gh's `#label` sets a label, not the name) and was replaced by `vergelabs-media-library.zip`, digest `sha256:bf0d63b70056…`, 1,101,616 bytes.
- After retirement: `pnpm test` 38 files / 505 tests passed, 14 skipped (87.8 s); health still `free 4.0.0`; the old URL 404.
- Deviation from step 3: implemented directly, not by a context-free subagent, because the env flip is a stop point only this conversation can honour and the story is remote ops Nathan approved.
- Rollback input kept: scratchpad `catalogue-before.json` (free 3.16.1 row; its zip is now only in git and `../dist`).

## Spec Change Log

## Review Triage Log

| # | Finding (layer) | Verdict | Evidence | Route |
|---|---|---|---|---|
| 1 | Rule says 12 hex, Pro's file has 10 (all three) | low | sha256sum: Pro 2a6a7946426f…, name 2a6a794642 (10) | patch: runbook says twelve from 4.0.0, Pro's ten predates |
| 2 | "previous beside current" stated while this change removed the only previous free zip; none for either slug (blind, edge) | medium | public/releases held only 4.0.0 and pro 1.0.2 after dd07dd0 | patch: 3.16.1 back as …-3.16.1-7f2a4fe9bee9.zip (3eb0b9b); runbook says Pro 1.0.1 needs git show 15f4d47^ |
| 3 | "restore from git" has no path and would restore the unversioned name (blind, edge) | low | old path is a rename in git; checkout of the old name finds nothing | patch: command and versioned target in the runbook |
| 4 | "never removed while any row could point at it" is the wrong test (blind) | low | wording | patch: current + previous stay; older retired in its own commit |
| 5 | GitHub pull-back needs gh release edit <prev> --latest; previous differs per surface (3.16.1 vs v3.16.0); email.ts also links latest (blind, edge) | medium | lib/email.ts:16 FREE_PLUGIN_RELEASE; gh release list shows no v3.16.1 | patch: runbook "When" bullet |
| 6 | releases() one-liner not runnable, never throws (blind) | low | releases() returns [] on bad JSON, drops bad rows | patch: the actual one-liner and "count the rows" |
| 7 | Release order omits the GitHub step and the asset-name exception (blind, edge) | low | spine AD-4 | patch: runbook paragraph |
| 8 | "How you know it worked" lacks the sha256-vs-name check (blind, edge) | low | cron reads the header only | patch: one bullet |
| 9 | Heading date contradicted by Pro's hashed names since 1.0.0 (blind) | low | Pro zips hashed since c126395 | patch: "binding for both from 4.0.0" |
| 10 | Retiring a zip within the WP update-transient window 404s mid-update (edge) | false for this change, medium in general | no free updater (AD-3) so no free site holds the package URL; Pro caches 6 h | patch: runbook "never within a day" |
| 11 | index-v2-backup.html Download links 404 (edge, gap) | low | nothing links to the backup page; it is Nathan's archived export | defer: Nathan's file |
| 12 | No automated check that every live catalogue source answers 200 between pnpm test and the daily cron; extend tools/promote.mjs (gap) | medium, pre-existing | the read-back was done by hand this story; release-check exists for exactly this and runs daily | defer: candidate story |


## Verification

**Commands:**
- `node <scratchpad>/readback.mjs` -- expected: `200 Version: 4.0.0 bf0d63b70056…`
- `npx tsx -e "…releases()…"` in `service/` with `PLUGIN_RELEASES` from `catalogue-after.json` -- expected: `vergelabs-media-library@4.0.0 vergelabs-media-library-pro@1.0.2`
- `node <scratchpad>/health.mjs` -- expected: `"releases","ok":true,"detail":"free 4.0.0, pro 1.0.2"`
- `node <scratchpad>/hosts.mjs` -- expected: `update:true` + the new package for 3.16.1, `update:false` for 4.0.0
- cron probe -- expected: `200 {"ok":true…}`
- `gh release view v4.0.0 --json isLatest,assets` -- expected: `isLatest: true`, one asset, size 1101616
- `pnpm test` in `service/` -- expected: all suites pass
