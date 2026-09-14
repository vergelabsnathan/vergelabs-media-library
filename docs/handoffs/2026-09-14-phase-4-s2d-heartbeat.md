# Handover — Phase 4, S2d: the release-check heartbeat built, deployed, pinged and documented (Opus 5)

2026-09-14, one session in `service`. Two commits on `service/main`,
`3f04705` (the ping and its tests) and `8927c31` (the two runbooks), both
pushed and deployed. Nothing reached a model, no credits. Phase 4's 4.2 is
now complete: the HTTP monitor from S2/S2b and the heartbeat from this
session.

**What is met:** the route pings Better Stack heartbeat `release-check`
after a run with nothing wrong; the three tests and the mutation hold; the
full suite is at 453; `RELEASE_CHECK_HEARTBEAT_URL` is in Vercel production
and the deploy carrying it serves; a manual run answered `200` and the
heartbeat's page shows the ping; `monitor.md` has a "Watched by heartbeat"
section and `incident.md`'s release-check section says where the email
comes from. **What is not:** nothing of this card.

## Decisions Nathan took this session

- **"Lift it":** the session did the `vercel env pull` → one line lifted
  by script → file deleted, in its scratchpad. The secret was never
  printed.
- **The heartbeat's settings by API, not by screen:** the heartbeat's page
  does not show grace or the alert route, so Nathan pasted a Better Stack
  account API token into the transcript; one node fetch of
  `/api/v2/heartbeats` read them. The token is to be revoked (see
  "Found, not done").

## What shipped

- `app/api/cron/release-check/route.ts` — `pingHeartbeat()`: one fetch of
  `RELEASE_CHECK_HEARTBEAT_URL` with a 10 s timeout, called after the
  results are known and only when `wrong.length === 0`; unset means no
  fetch; a non-2xx or a thrown fetch is `console.error`ed and the
  response stands.
- `lib/release-check.test.ts` — `HEARTBEAT` served `200` by the stub, the
  variable set in `beforeEach`, a `fetched()` helper listing every URL the
  route fetched in order, and three tests under `the heartbeat`: pinged once
  after the two zips on 200; not pinged on a 503; not pinged when unset and
  the run still 200.
- `docs/runbooks/monitor.md` — "What is watched" says "Nothing else by
  HTTP"; the release-check bullet moved out of "Not yet watched" into a new
  "Watched by heartbeat" section: heartbeat 493257 `release-check`, created
  07:44:59 UTC 2026-09-14, 1-day period, 10-minute grace, email to the
  account's on-call (Nathan), no SMS/call/push, no policy; the first ping
  (08:44:15 UTC); the deadline arithmetic against the S1 "by 05:00"
  wording; the token note. "Not yet watched" keeps only the provider (4.7).
- `docs/runbooks/incident.md` — the release-check section: "The email
  comes from the heartbeat", how a 503 and a cron that never ran both
  become a missed beat, the heartbeat's page first in "Where to look", the
  manual run after a fix is also the ping that resolves the incident, the
  suite line names the ping.
- `.harness/active.json` — this session's card (in `3f04705`).
- Vercel: `RELEASE_CHECK_HEARTBEAT_URL` added to production by the session
  (the URL Nathan pasted into the opener). Nothing was created or changed
  at Better Stack from the session.

## Gates

- `npx vitest run lib/release-check.test.ts` → `Tests 14 passed (14)`.
  Red first, before the route changed: `1 failed | 13 passed (14)`, the
  "pinged once" test. **Met.**
- Mutation, `if (wrong.length === 0) await pingHeartbeat();` →
  `await pingHeartbeat();`: `× the heartbeat > is not pinged on a 503 -- a
  stale channel must look like a missed beat`, `Tests 1 failed | 13 passed
  (14)`. Restored: `Tests 14 passed (14)`. `pnpm typecheck` clean. **Met.**
- `pnpm test` → `Test Files 31 passed | 7 skipped (38)`, `Tests 453 passed
  | 14 skipped (467)`, 90.31 s. **Met** — on the second run; the first was
  `2 failed | 451 passed`, both in `lib/login-drive.test.ts` (20.3 s and
  8.3 s), the flake S2c recorded; alone `3 passed (3)` in 10.9 s.
- `vercel env ls production` → `RELEASE_CHECK_HEARTBEAT_URL Encrypted
  Production 11s ago`. The deploy: the push of `3f04705`, said before it
  went; `vercel ls --prod` → `vergelabsmedia-4nmw8dlie…` `● Ready
  Production 19s`; `vercel inspect` → `dpl_ABenV1Nb2Ncg9Z9dQb37tnVMBdGF`,
  created 08:55:49 local, 27 s after the push, aliases `vergelabsmedia.com`,
  `ai.vergelabs.nl`, `vergelabsmedia.vercel.app`. **Met.** (The push also
  carried S2c's `1bc1f50`, which had been committed but never pushed. The
  docs commit `8927c31` triggered a second build, docs only.)
- Manual run, 08:44:15 UTC, bearer lifted by script: `200 no-store`,
  `"ok": true`, both plugins `the catalogue and the package agree`. Nathan's
  screenshot of Better Stack → Heartbeats → `release-check`: **Up ·
  Expected every 1 day**, "Last heartbeat recorded 2 minutes ago", one bar
  on the chart, 0 incidents. **Met.**
- `grep -n 'Watched by heartbeat\|Not yet watched' docs/runbooks/monitor.md`
  → lines 69 and 95; `grep -c 'not built yet'` → 0 in both runbooks;
  incident.md line 172 holds "10-minute grace and emails the account's
  on-call". **Met.**

## Decisions taken (routine, mine)

- The ping is a `GET` (Better Stack accepts HEAD, GET and POST, per the
  heartbeat's own page) with a 10 s timeout, shorter than the zips' 45 s,
  so a slow heartbeat host cannot push the route past `maxDuration`.
- The "pinged" test asserts the whole fetch order `[free, pro, heartbeat]`
  rather than "contains", so a ping before the results are known would
  also be red.
- The runbooks say "the account's on-call (Nathan)" for the email, not an
  address: the API returns `email: true` and no address, and the account's
  one user is Nathan. Not invented further.
- The 10-minute grace against the S1 "expect by 05:00" is recorded in
  `monitor.md` as a fact with the deadline arithmetic (last ping + 1 day +
  grace → about 04:33 UTC after tomorrow's run), not changed: nothing at
  Better Stack from the session. Widening it is a one-field change Nathan
  makes if he wants the S1 margin.

## Found, not done

- **Revoke the Better Stack API token** pasted into this transcript
  (Better Stack → Settings → API tokens). Used once from the scratchpad;
  in no file in either repo. Same class as the UptimeRobot key from S2,
  still to be regenerated.
- **The failed-ping path is untested** (`the heartbeat answered HTTP …`,
  `the heartbeat ping failed`): the card's three tests cover sent / not
  sent / unset. One more — the stub answering 500 for the heartbeat, the
  route still 200, the log line asserted — for a later card.
- **The first scheduled ping is tomorrow's 04:23 UTC run.** Nothing proves
  the cron itself pings until then; the heartbeat page on 2026-09-15 after
  04:30 UTC should show a second bar. If it shows a missed beat instead,
  the Cron Jobs page says whether the run happened, and `incident.md`
  "Where to look" carries on from there.
- **The fence lost the four read-first entries when the cwd moved** from
  the parent folder to `service` at the first Bash call — third session in
  a row (S2b, S2c, this). `sed -n 1p` on all four from `service`
  re-registered them. `hooks-design.md`: key the read-first list on the
  resolved absolute path.
- `lib/login-drive.test.ts` flakes under a full parallel run (S2c's item);
  fake timers or a wider window, a Phase 5 or later card.
- `tsconfig.tsbuildinfo` modified, `docs/mocks/` untracked, as before; not
  committed.

## Cost

Nothing reached a model. No credits. Two full test runs, four single-file
runs, two deploys, one live cron run (two zip downloads), two Better Stack
requests.

## Next — Phase 5 S1 on Opus, in `plugin`

Per the 2026-09-13 launch-order note: Phase 5's first session while the
wordpress.org queue sits; Phase 3 S3 after the Fable reset. The card and
the opener are in the S1 handoff
(`2026-09-13-phase-4-s1-health-release-check.md`, "Next"); reproduced
here so the next session has one file to read.

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Four yesses — Phase 5, S1: 5.5 files, paths and uploads + 5.6 outbound hosts and SSRF",
  "model": "opus",
  "plan": "plans/four-yesses/phase-5.md",
  "spec": "plans/four-yesses.md",
  "scope": [
    "core/**",
    "pro/includes/**",
    "tests/security/**",
    "tools/**",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-14-phase-4-s2d-heartbeat.md",
    "docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md",
    "plans/four-yesses/phase-5.md",
    "docs/security-surface.md",
    "tests/security/roles.php",
    "tests/security/db-calls.mjs"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The renamer stays off: never enabled to test it; defined('VERGEML_FILE_RENAME') false on the box",
    "No allowlist a filter can extend; VERGEML_AI_SERVICE and VERGEML_KNOWN_ISSUES_URL are owner define()s and documented as such",
    "The suite deletes nothing but its own fixture files; paths.php runs on the box, not Playground",
    "Refusal strings are the existing WP_Error ones; none new"
  ],
  "gates": [
    "node tools/verify.mjs paths hosts → green; tests/security/paths.php on the box: traversal, absolute path, symlink out — each refused, target hash unchanged",
    "mutation: the prefix check stripped at one site on the box copy → its case red; a wp_remote_get($_GET['u']) in a scratch file → hosts.mjs red",
    "sslverify true at all 19 sites, asserted",
    "docs/security-surface.md gains its Files section; docs/security-hosts.md one row per call site with a decided-by of the three kinds",
    "node tools/verify.mjs surface db-calls escaping globals roles → still green"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-14-phase-4-s2d-heartbeat.md, then
plans/four-yesses/phase-5.md tasks 5.5 and 5.6. State which model you are
and follow that profile in ~/.claude/harness/model-profiles.md. This session
is Phase 5 S1. Write the card from the handoff to .harness/active.json before
the first edit. Stop points and gates are in the card. End with a handoff in
docs/handoffs/.
```
