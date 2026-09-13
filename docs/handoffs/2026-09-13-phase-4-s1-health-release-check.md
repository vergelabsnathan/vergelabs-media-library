# Handover — Phase 4, S1: 4.1 the health endpoint proven, 4.3 the release check proven (Opus 5)

2026-09-13. One session in `../service`, two commits, deployed. Both routes
were built on 2026-09-10 and unproven; now each 503 path has a test that
fires it alone, and one real leak in the health route is closed.

## What shipped — commits `489f325`, `f2df441`, on `main`, deployed

- **`lib/health.test.ts`** (13): every check broken in turn — the database
  query rejecting, `PLUGIN_RELEASES` = `not json`, Stripe's balance read
  throwing, `STRIPE_WEBHOOK_SECRET` unset, `OPENROUTER_API_KEY` unset with the
  relay on — and `failing` must be exactly that one name, every other check
  `ok`. Two at once names both. `LICENSING_ENABLED=false` and
  `AI_RELAY_ENABLED=false` pass, not fail. No body contains any of four
  planted values (a connection string with a password, a Stripe key, the
  webhook secret, the relay key). `db()` and `stripe()` are swapped through
  `vi.mock('@/lib/stripe')` as `account-download.test.ts` does; the other three
  are `process.env`. Nothing reaches the network.
- **`app/api/health/route.ts`, one change**: it published a library's
  `err.message` verbatim on a 503 — a pg or Stripe message quoting the
  connection string or the refused key would have gone out on a public body,
  against the route's own comment. Now the route's seven own messages are a
  `CheckFailed` and stay (they name a variable, never its contents); any other
  error reads `failed` in the body — the fallback word the route already had —
  and its message goes to `console.error('[health] <name> failed', …)`. The
  healthy body is byte-for-byte what it was. The five check names are
  unchanged.
- **`lib/release-check.test.ts`** (11): zips built in the test with JSZip and
  served by a stubbed `fetch`. The three-week failure replayed: the catalogue
  at 3.0.0 over the 3.16.1 zip → 503, `served: '3.16.1'`, the detail line and
  the `[release-check]` log line asserted; the inverse (catalogue 3.16.1, zip
  3.0.0) → 503, so the zip is what is read; the correct catalogue → 200 and
  nothing logged; a 404 zip → `the package answered HTTP 404` with the other
  plugin still passing; a zip without `<slug>/<slug>.php`; `not json` → 503
  before any fetch; four wrong bearers and an empty `CRON_SECRET` → 401 with
  no fetch. The route is unchanged.
- **`docs/runbooks/incident.md`** started in the service: "What the health
  body says" (4.1's section) and the `releases` / release-check section
  (4.3's). 4.2, 4.7, 4.8 append theirs. No customer sentence is written (4.8's
  stop point).

## Evidence

- `npx vitest run lib/health.test.ts` → **13 passed (13)**.
- `npx vitest run lib/release-check.test.ts` → **11 passed (11)**.
- `pnpm test` → **437 passed | 14 skipped (451)**, 28 files; the skips are
  the env-gated live suites, as before. `pnpm typecheck` → clean.
- Mutation, the gate's: the stripe check wrapped in `try { … } catch { return
  'test mode' }` → **3 red**, first `expected 200 to be 503`. Restored.
- Mutation, mine, on the stop point: `const ok = r.version === r.version`
  (the string against itself) → **2 red**, both stale cases. Restored.
- Live `/api/health` through a node fetch, 08:30 UTC before the deploy and
  again after:

  ```
  200 no-store
  { "ok": true, "checked_at": "2026-09-13T08:30:09.005Z", "failing": [],
    "checks": [
      { "name": "database",       "ok": true, "detail": "7 licences",                "ms": 388 },
      { "name": "releases",       "ok": true, "detail": "free 3.16.1, pro 1.0.2",   "ms": 0 },
      { "name": "stripe",         "ok": true, "detail": "live mode",                 "ms": 169 },
      { "name": "webhook secret", "ok": true, "detail": "present",                   "ms": 0 },
      { "name": "ai relay",       "ok": true, "detail": "on",                        "ms": 0 } ] }
  ```

- Deploy: `dpl_2uGeipgqRDRQEeiNMEW4ddk1uYa6`, created 09:38:06 local, 51 s
  after the push of `f2df441`, Ready, aliased to `vergelabsmedia.com`. The
  healthy body carries no marker of the build (it is identical by design);
  the deployment's age is the identity.
- `CRON_SECRET` is in Vercel production (`vercel env ls`: Encrypted, 22 d).

## The live cron run, and the wiring

- **Run live by Nathan at 08:49 UTC** (the secrets guard keeps the cron
  secret from the session; `vercel env pull` to a temp file, the one line
  lifted into `$env:CRON_SECRET`, the file deleted, one node fetch from
  `service`):

  ```
  200 { "ok": true, "checked_at": "2026-09-13T08:49:44.587Z", "results": [
    { "slug": "vergelabs-media-library",     "advertised": "3.16.1", "served": "3.16.1", "ok": true, "detail": "the catalogue and the package agree" },
    { "slug": "vergelabs-media-library-pro", "advertised": "1.0.2",  "served": "1.0.2",  "ok": true, "detail": "the catalogue and the package agree" } ] }
  ```

  `served` is read from `Version:` inside each zip, so the channel serves
  what it advertises today. The scheduled 04:23 UTC run itself is only on
  the dashboard's Cron Jobs page (`vercel logs` in CLI 50 streams from now).
- **On failure the release check logs and answers 503 to the cron runner, and
  that is all.** The dashboard marks the run failed; nobody is emailed;
  `/api/health`'s `releases` check does not read it. The gate says "wire it
  to the releases health check" — that needs the result to live somewhere
  across invocations, and nothing does: no `checks`/`kv` table in the schema
  (21 tables, none fits), and `db/migrations/` is outside this card's scope.
  Not built. The decision is a stop point below.

## Decisions taken (routine, mine)

- The library-message leak was fixed in the route rather than reported: the
  plan's proof is "grep the five bodies for anything key-shaped", the route's
  comment states the invariant, and `failed` is the route's own existing word,
  so no copy was written. Alternative if you want the message back: redact by
  shape (`sk_…`, `whsec_…`, `postgres://…`) — brittle, not recommended.
- The relay check is proven as built: a presence check on the key, by the
  route's own comment. The plan words its failure as "the relay URL pointed
  at a closed port"; there is no URL probe in the route and adding one is a
  design call (money per health hit, or a reachability probe of OpenRouter
  every five minutes from the monitor). Not added.
- `lib/updates.test.ts`, the plan's mirror, tests pure functions; the route
  pattern actually in the repo is `account-download.test.ts`
  (`vi.mock('@/lib/stripe')`). Mirrored that.

## Stop point, decided — the release check reaches a person by heartbeat

**Nathan, 2026-09-13: a heartbeat monitor.** Of the three ways (a Better
Stack heartbeat; a `checks` table by migration that health reads; the HTTP
monitor calling the cron route with the bearer), the heartbeat: no schema,
no secret leaves Vercel, and it also catches the cron not running at all.

For 4.2's card, alongside the HTTP check on `/api/health`:
- A Better Stack heartbeat with a daily period and a grace that covers the
  04:23 UTC run (expect by 05:00 UTC).
- `app/api/cron/release-check/route.ts` pings the heartbeat URL after a run
  whose `wrong.length === 0`, and does not ping on a 503. The URL is a new
  env variable (`RELEASE_CHECK_HEARTBEAT_URL`; unset means no ping, so tests
  and local runs stay silent). `lib/release-check.test.ts` gains: the ping is
  sent on 200, not on 503, not when the variable is unset.
- Proof, in 4.2's rehearsal: set `PLUGIN_RELEASES` stale, run the cron route
  once by hand, receive the missed-heartbeat email; restore; run; receive the
  recovery. `docs/runbooks/monitor.md` and the `releases` section of
  `incident.md` say so.

## Found, not done

- `lib/health.test.ts` and `lib/release-check.test.ts` set `process.env`
  directly and do not restore it; vitest isolates files, so nothing leaks
  between suites today. `routing.test.ts` saves and restores — the neater
  shape if a shared-environment runner is ever used.
- `docs/runbooks/incident.md` says a `PLUGIN_RELEASES` change needs a
  redeploy — true of Vercel env variables; the runbook should carry the exact
  `vercel env` + redeploy commands once 4.8 walks it.
- `tsconfig.tsbuildinfo` is modified in the working tree before this session
  and left alone.

## Cost

- Nothing reached a model. The two live health probes are one balance read at
  Stripe and one count on the pooler each; the release check was not run
  live. No credits.

## Next — Phase 5 S1 on Opus, in `plugin`

Per the 2026-09-13 reorder: Phase 5's first session while the wordpress.org
queue sits. `plans/four-yesses/phase-5.md` S1 is 5.5 (files, paths and
uploads — a box suite) + 5.6 (outbound hosts and SSRF — a local register
suite). The Phase 3 S3 card stays in the S2 handoff for after the reset.

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
Read docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md, then
plans/four-yesses/phase-5.md tasks 5.5 and 5.6. State which model you are
and follow that profile in ~/.claude/harness/model-profiles.md. This session
is Phase 5 S1. Write the card from the handoff to .harness/active.json before
the first edit. Stop points and gates are in the card. End with a handoff in
docs/handoffs/.
```
