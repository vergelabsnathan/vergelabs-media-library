# Phase 4 — the system survives 2,000 sites

Tasks for `plans/four-yesses.md` Phase 4. The service is `../service`.
Written 2026-09-11 from a survey; every path named exists. Three of the
plan's nine items were built on 2026-09-10, the day the plan was written, and
are re-pointed here at proving rather than building.

**State on 2026-09-11.**
- `app/api/health/route.ts` exists: five timed checks — `database`,
  `releases`, `stripe`, `webhook secret`, `ai relay`; 200 or 503 naming the
  failed check, never a value. **4.1 is built; unproven that the 503 path
  fires for each.**
- `app/api/cron/release-check/route.ts` exists and is in `vercel.json`'s
  crons with `reconcile` (`17 3 * * *`) and `support-distill`. **4.3 is
  built; unproven against a deliberately stale channel.**
- `lib/limits.ts`: per licence, `BURST_MAX_CREDITS = 240` per 60 s,
  `DAY_MAX_CREDITS = 25_000` per rolling 24 h, `PLANNER_DAY_MAX_PER_SITE = 40`;
  refuses with `rate_limited` / `daily_cap` and `retryAfterSeconds`.
  **4.4's per-licence half exists; no concurrency ceiling, no shedding
  queue, and the 10× drive has never been run.**
- No global daily cap anywhere (`grep -rn "GLOBAL\|global" lib/limits.ts` is
  empty). **4.5 is open.**
- No external monitor. **4.2 is open.**
- Supabase point-in-time recovery: unknown until the dashboard is read.
  **4.6 is open.**
- The OpenRouter key was pasted into a transcript on 2026-09-10. **4.9 is
  open and is a stop point.**
- Env names the code reads: `DATABASE_URL`, `STRIPE_SECRET_KEY`,
  `STRIPE_WEBHOOK_SECRET`, `PLUGIN_RELEASES`, `NEXT_PUBLIC_SITE_URL`, plus
  the OpenRouter key and the cron secret — read `lib/*.ts` for the exact
  names before 4.9 lists them.
- The measured ceiling: 82/min end to end at 16 on the box (memory
  `capacity-plan`); the pooler must be `DB_POOL_MODE=transaction`.

**Model.** Opus 5 throughout; 4.4 and 4.5 together are one Fable-sized
session if Fable is available (a queue that sheds is a design decision).

**Sessions.** S1: 4.1 + 4.3 (prove both). S2: 4.2 + 4.8 (the monitor and
the runbook it feeds). S3: 4.4 + 4.5. S4: 4.6. S5: 4.7 + 4.9.

**Stop points (Nathan).**
- 4.2 — which monitor (UptimeRobot, Better Stack, Vercel's own checks) and
  whose inbox gets the email. Default: Better Stack free tier, five-minute
  HTTP check on `/api/health` expecting 200, email to Nathan.
- 4.5 — the global daily number. Default proposed: **€50/day of provider
  cost** ≈ 10,000 describes at €0.00483; alert at 80 %, refuse at 100 %.
- 4.6 — a scratch Supabase project to restore into (his account, his cost
  for the hour it exists).
- 4.9 — rotating the OpenRouter key is his account; the session writes the
  list and the procedure, he turns the key.

**Gates, as commands.**
- `curl -s -o /dev/null -w '%{http_code}' https://vergelabsmedia.com/api/health` →
  200; and 503 once for each check, deliberately broken, in the handoff.
- An email from the monitor in Nathan's inbox, screenshotted.
- `pnpm test` green including `lib/limits.test.ts` and the new
  `lib/spend-cap.test.ts`.
- A row count from the restored scratch project, pasted.
- `docs/runbooks/incident.md` and `docs/secrets.md` in the service.

**Spend.** 4.4's 10× drive uses the mock provider or a licence with 10
credits so the refusal is by rate, not by money — say which before running.
4.7 blocks the provider; nothing is spent. Nothing else reaches a model.

---

## 4.1 · A health endpoint that exercises what matters — Opus, prove

- **Files:** `app/api/health/route.ts` (read); create
  `lib/health.test.ts`; `docs/runbooks/incident.md` gets its "what the
  health body says" section (4.8).
- **Behaviour:** each of the five checks fails on its own and the body names
  it: database (a wrong `DATABASE_URL` in a local run), releases
  (`PLUGIN_RELEASES` set to `not json` — the route logs "not valid JSON"),
  stripe (a revoked test key), webhook secret (unset), ai relay (the relay
  URL pointed at a closed port). No value appears in any body — grep the five
  bodies for anything key-shaped.
- **Proof:** `lib/health.test.ts` runs the route handler with each env
  broken in turn and asserts 503 + the check's name and no other check's
  failure; the live endpoint's 200 body pasted in the handoff. Mutation:
  make the `stripe` check swallow its error and the test for it goes red.
- **Mirror:** the route's own comment block; `lib/updates.test.ts` for how a
  route is tested with env swapped.
- **Copy:** the check names are the five strings in the route — unchanged.
- **Do not:** add a check that reads a value into the body; call the live
  provider in the test (the relay check is a reachability probe — mock it).

## 4.2 · Something external watches it — Opus

- **Files:** the monitor's dashboard (outside the repo); create
  `docs/runbooks/monitor.md` in the service (what is watched, from where,
  who is emailed, how to pause it during a deploy).
- **Behaviour:** an external service requests `/api/health` every five
  minutes, expects 200, emails on two consecutive failures. It is not hosted
  on Vercel and not on the box. Break the service deliberately (set
  `PLUGIN_RELEASES` to `not json` in production for ten minutes, or take the
  relay down) and receive the email; restore; receive the recovery email.
- **Proof:** the alert email and the recovery email screenshotted in the
  handoff with their timestamps; the monitor's check history showing the
  window; `docs/runbooks/monitor.md` written.
- **Mirror:** memory `the-watch` for the nightly update watch that already
  exists (a different thing — it watches competitors' releases, not us).
- **Copy:** none.
- **Do not:** monitor the front page (a 200 there proves nothing); monitor
  from the box; leave production broken longer than the rehearsal.

## 4.3 · The release channel cannot go stale — Opus, prove

- **Files:** `app/api/cron/release-check/route.ts` (read), `lib/updates.ts`;
  create `lib/release-check.test.ts`.
- **Behaviour:** the check compares each advertised version against the zip
  it points at (the zip's `Version:` header, or its size and digest) and
  reports a mismatch. Prove it with the three-week failure replayed: a
  `PLUGIN_RELEASES` advertising 3.0.0 pointing at the 3.16.1 zip → the check
  reports it; the correct JSON → passes. And the inverse: a JSON naming a zip
  that 404s.
- **Proof:** `lib/release-check.test.ts` with those three cases green; the
  cron's last run visible in Vercel's cron log; what it does on failure
  (email? log line? health check reads it?) — if it only logs, that is a
  finding: wire it to the `releases` health check so 4.2's monitor sees it.
- **Mirror:** the route's comment block ("On 2026-09-10 it did not, and had
  not for three weeks"); `lib/updates.test.ts`.
- **Copy:** none.
- **Do not:** let it pass on a version string match alone — the zip is the
  thing.

## 4.4 · Limits, so one site cannot take the service down — Opus (Fable with 4.5)

- **Files:** `lib/limits.ts` (read; the per-licence brake), the describe
  route `app/api/ai/describe/route.ts` and batch `app/api/ai/batch/route.ts`
  (where the ceiling applies); create `scripts/drive.mjs` (the load driver),
  `lib/limits.test.ts` (exists — extend).
- **Behaviour:** beyond the per-licence brake: a **concurrency ceiling** per
  licence and global (the measured 82/min at 16 workers is the number to
  protect), and a queue that **sheds** — a request over the ceiling gets 429
  with `retryAfterSeconds` immediately, not a slow 200 or a 504. Drive
  `describe` at 10× the ceiling from one licence (`scripts/drive.mjs`, mock
  provider or a 10-credit licence) while a second licence makes one request
  a second: the second licence's requests all succeed within their normal
  latency; the first sees 429s and no 5xx; the service's health stays 200.
- **Proof:** `scripts/drive.mjs` prints a table: for each licence, requests
  sent, 200s, 429s, 5xxs, p50 and p95 latency; the assertion in the handoff
  is "licence B: 0 refused, p95 under twice its solo p95 (measured first, written in the table); licence A: 0 5xx". `limits.test.ts`
  covers the ceiling arithmetic. Mutation: set the ceiling to Infinity and
  licence B's p95 blows past X.
- **Mirror:** `lib/limits.ts` comment block for why a brake and not a budget;
  memory `capacity-plan` for the 82/min and the pooler mode; `tools/box-burst.sh`
  in the plugin for a driver that already exists.
- **Copy:** the 429 body's `error` stays `rate_limited`; the plugin already
  reads `retryAfterSeconds`.
- **Do not:** queue inside a serverless function (it dies with the
  invocation); raise the per-licence numbers to make the drive pass; run the
  drive against the box's describe screen (drive the service directly).

## 4.5 · A spend cap, per licence and global — Opus (Fable with 4.4)

- **Files:** `lib/limits.ts` (add the global window), the store
  (`lib/store.ts` — a `provider_spend` table or a daily row: migration in
  `db/migrations/`, applied file-by-file as `vgml_media` — memory
  `service-fresh-database`), the describe/batch routes; create
  `lib/spend-cap.test.ts`; `app/api/health/route.ts` gains a `spend` check
  that fails at 100 %.
- **Behaviour:** every provider call records its cost (the per-image basis
  is €0.00483 measured; embeds ~0); a rolling 24 h global total; at 80 % an
  email to Nathan (through `lib/email.ts`) once per window; at 100 % every
  describe and embed is refused with `error: 'spend_cap'` and the plugin's
  screen says so (the plugin already handles `rate_limited`; add the string —
  a Phase 3.7 copy line, stop point). The per-licence half exists as credits.
- **Proof:** `lib/spend-cap.test.ts`: at 79 % no email, at 80 % one email
  and not a second, at 100 % refusal; the health check fails at 100 %.
  Mutation: remove the once-per-window guard and the "not a second" case
  goes red.
- **Mirror:** the daily window in `lib/limits.ts` (`DAY_WINDOW_MS`, rolling
  not calendar); `lib/reconcile.ts` for a nightly aggregate.
- **Copy:** the plugin-side string is a stop point; the email's subject and
  body drafted in the session's first message for approval.
- **Do not:** cap by credits (credits are what the customer spends, not
  us); reset at midnight (rolling); store the cost in the credit ledger.

## 4.6 · Backups, and a restore that happened — Opus

- **Files:** create `docs/runbooks/restore.md` in the service; nothing in code.
- **Behaviour:** read the Supabase dashboard for project
  `pbyqedpjhbqeqeytblle`: is PITR on, what is the retention. Then restore a
  point from yesterday into a **scratch** project (Nathan creates it — stop
  point), connect with `psql`, and count: `licences`, `credit_entries`,
  `sites`/activations, invoices — and compare with the same counts on
  production for the same timestamp (`select count(*) … where created_at <
  the restore point`). Delete the scratch project.
- **Proof:** the two count tables side by side in the runbook with the
  timestamp and the time the restore took; the runbook's steps as run;
  PITR's setting screenshotted.
- **Mirror:** memory `service-fresh-database` for the pooler and the role;
  `scripts/inspect.mjs` for a read-only connection.
- **Copy:** none.
- **Do not:** restore into production; leave the scratch project running;
  count with `SELECT *`.

## 4.7 · The provider can fail — Opus

- **Files:** `lib/anthropic.ts` / `lib/routing.ts` (where OpenRouter is
  called — memory `openrouter-always`), the describe route; the plugin's
  `core/ai-background.php` (the queue's retry) and `js/vergeml-ai.js` (the
  finished lines — "kept failing and were left"); create
  `lib/provider-down.test.ts`; `docs/runbooks/incident.md` gains a
  "provider down" section.
- **Behaviour:** with the provider unreachable (point the base URL at a
  closed port in a local run) or rate-limiting (a stub answering 429): the
  service returns a typed error, **no credit is spent** (the ledger row is
  written after the provider answers, or reversed — read `describe.ts` and
  say which), the plugin's screen shows the picture as failed-and-left with
  the reason, and the background queue resumes on its own when the provider
  is back (the nudge core — memory `capacity-plan`).
- **Proof:** `lib/provider-down.test.ts`: 503 from the provider → our error
  code, balance unchanged; 429 → our `retryAfterSeconds` set; the plugin's
  `ai-background` suite run once with the box's service URL pointed at a
  closed port (its `before` un-describes three) → the three are left with an
  error row, then pointed back → described. Cost: three real describes ≈
  €0.015.
- **Mirror:** the `errors` handling in `js/vergeml-ai.js:229–236`;
  `core/ai-background.php` "kept failing" logic.
- **Copy:** the plugin's existing failed-and-left lines; nothing new.
- **Do not:** spend a credit on a request the provider refused; retry
  forever (the plugin's hold on failed rows is ten minutes — keep it).

## 4.8 · An incident runbook — Opus

- **Files:** create `docs/runbooks/incident.md` in the service, written
  while doing 4.2, 4.3 and 4.7 (their sessions each append a section);
  pointers from `docs/recovery.md` in the plugin and the service README.
- **Behaviour:** one page: the alert fires → open `/api/health`, read which
  check → per check, what to look at in order (Vercel logs, Supabase status,
  Stripe status, the provider's status page, `PLUGIN_RELEASES`), what to
  change, how to confirm, and what to tell customers (a sentence per
  situation, on the site's status line or by email). Includes 1.7's rollback
  and 4.7's provider-down section by reference.
- **Proof:** the page exists with a section per health check; one rehearsal
  (the 4.2 break) walked by the page's steps and timed, noted at the bottom.
- **Mirror:** `docs/recovery.md`; the health route's check names as the
  section heads.
- **Copy:** the customer sentences are a stop point — drafted, approved.
- **Do not:** write it after the fact from memory; describe a step that was
  not run once.

## 4.9 · Secrets are rotatable, and rotated — Opus

- **Files:** create `docs/secrets.md` in the service (every secret: where it
  lives, what reads it, how it is replaced without downtime, when it was last
  turned); `scripts/mode-check.mjs` as the pattern for checking a key without
  printing it.
- **Behaviour:** the list: `DATABASE_URL` (Supabase pooler, role
  `vgml_media`), `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, the OpenRouter
  key, the cron secret, the licence key secret (`keySecret()` in
  `lib/licence.ts:220` — rotating it re-seals every key; say what that
  means), the guide token secret, `VGML_TEST_KEY` on the box, the ssh key
  `~/.ssh/hetzner_vgml`, `CLAUDE_CODE_OAUTH_TOKEN` (memory `the-watch`: only
  Nathan can mint it). The OpenRouter key rotated by Nathan; the session
  verifies the old one is refused (`mode-check`-style probe) and the new one
  works (`/api/health` `ai relay` passes).
- **Proof:** `docs/secrets.md` complete, one row per secret with a "last
  turned" date; the OpenRouter row dated today; health 200 after.
- **Mirror:** `scripts/mode-check.mjs`; the health route's `envPresent()`.
- **Copy:** none.
- **Do not:** print a secret in any output or commit; rotate the licence key
  secret in this task (it needs its own migration plan — note it).
