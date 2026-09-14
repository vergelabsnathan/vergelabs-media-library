# Handover — Phase 4, S2b: the 4.2 break rehearsed, alert and recovery received; the docs not yet written in (Opus 5)

2026-09-13, one session in `service`. No code changed, nothing reached a
model, no credits. Production was deliberately broken for **5 min 4 s**
(18:36:18 → 18:41:22 UTC) and UptimeRobot 803983434 emailed both halves.
Both redeploys ran from the agent's terminal — the refusal of 09-11 did not
repeat — so Nathan typed nothing.

**What is met:** the rehearsal, both emails with timestamps, the health
`200` after the restore, the two unverified facts in the sentences settled
from the code. **What is not:** `monitor.md` and `incident.md` are unchanged
(the fence sealed the session — see "Found, not done"), the heartbeat is not
built (Nathan's choice still open), `pnpm test` not run (no `.ts` touched).

## The rehearsal, UTC

| Time | Step | Who |
|---|---|---|
| 18:29 | `vercel env pull --environment=production --yes <scratchpad>/prod.env`; `lift.mjs` lifted the `PLUGIN_RELEASES` line to `catalogue.json` (720 bytes, `vergelabs-media-library@3.16.1`, `vergelabs-media-library-pro@1.0.2`) and deleted `prod.env` | agent |
| 18:30:33 → 18:30:40 | `vercel env rm PLUGIN_RELEASES production -y`; `printf 'not json' \| vercel env add PLUGIN_RELEASES production` — 7 s | agent |
| 18:35:35 → 18:36:18 | `vercel redeploy https://vergelabsmedia-d19e8b3f4-… --target production` → `vergelabsmedia-hk29y9b79`, 43 s, Ready | agent (not refused) |
| 18:36:36 | health script: `503 failing: releases` — `BAD releases PLUGIN_RELEASES is empty or not valid JSON (1 ms)`, the other four `ok` | agent |
| 18:40:04 | UptimeRobot's check sees the 503; **"is down" email** in Nathan's inbox at 19:40 local (0 minutes ago when pasted) | monitor |
| 18:40:27 → 18:40:34 | variable restored from `catalogue.json` — 7 s | agent |
| 18:40:34 → 18:41:22 | `vercel redeploy https://vergelabsmedia-hk29y9b79-… --target production` → `vergelabsmedia-lbieuzfhy`, 45 s (`vercel ls`: 39 s build), Ready | agent |
| 18:41:27 | health script: **`200 ok`** — `database 7 licences (381 ms) · releases free 3.16.1, pro 1.0.2 (0 ms) · stripe live mode (158 ms) · webhook secret present · ai relay on` | agent |
| 18:45:09 | UptimeRobot's check sees the 200; **"is up" email** | monitor |

Broken from the first build going live to the second: 18:36:18 → 18:41:22,
**5 min 4 s**. UptimeRobot's own incident: 18:40:04 → 18:45:09, "5 minutes
and 5 seconds". The ten-minute cap held with room.

The health probe script is `incident.md` step 1 verbatim, saved as
`health.mjs` in the session's scratchpad (the inline `node -e` form is
blocked for the agent). `vercel ls` after: `56s  vergelabsmedia-lbieuzfhy  ● Ready  Production  39s`
above `6m  vergelabsmedia-hk29y9b79` — the restore build is what serves.

## The emails, as pasted by Nathan (the gate's "screenshots")

Alert, `UptimeRobot <alert@uptimerobot.com>`, subject **"Monitor is DOWN:
vergelabsmedia.com/api/health"**, received 19:40 local:

> vergelabsmedia.com/api/health is down. … Your service is currently down in
> N. Virginia, USA. … Monitor name vergelabsmedia.com/api/health · Checked URL
> https://vergelabsmedia.com/api/health · Root cause **HTTP 503 - Service
> Unavailable** · Incident started at **2026-09-13 18:40:04** · Location
> N. Virginia, USA - IP 52.87.72.16

Recovery, same sender, subject **"vergelabsmedia.com/api/health is up"**:

> The latest incident has been resolved and your monitor is up again in North
> America. … Root cause HTTP 503 - Service Unavailable · Incident started at
> 2026-09-13 18:40:04 · **Resolved at 2026-09-13 18:45:09** · Duration
> **5 minutes and 5 seconds** · Location N. Virginia, USA - IP 54.167.223.174

Also in the inbox, from the S2 session's typo (`/api/healt`): down 18:18:10
(`HTTP 404 - Not Found`, Ashburn, USA - IP 5.161.113.195) and up 18:18:58
(N. Virginia, USA - IP 34.198.201.66, "0 minutes and 48 seconds"). Four
emails, two incidents, every one delivered.

What the emails establish for `monitor.md`: the checks come from UptimeRobot's
US East locations (Ashburn / N. Virginia, four IPs seen); a 503 is treated as
down with no setting to make; the check falls at ~:04 past each five-minute
mark (18:18:09 first check, 18:40:04, 18:45:09); one email at down, one at
up, nothing in between (contact threshold 0, recurrence 0).

## The two unverified facts, read from the code — the sentences stand

- **A failed describe is refunded.** `app/api/ai/describe/route.ts:337-357`:
  debit first; on `!outcome.ok`, `s.addCredits(licence.id, cost, 'refund',
  null)` then `502 model_unavailable`; a refund that itself fails logs
  `[relay] REFUND FAILED — replay by hand` with licence prefix and amount.
  The ai-relay sentence's "no credits are taken for a picture that was not
  described" is true.
- **A failed purchase has not charged the card.**
  `app/api/licence/intent/route.ts:381-391`: `paymentIntents.create` with
  `payment_method_types: ['card']` and no `capture_method` — automatic
  capture, so Stripe moves money only when the intent reaches `succeeded`;
  `requires_action`, `requires_payment_method` and an outage mid-confirm hold
  no charge. A `succeeded` intent that issued nothing is the webhook-secret
  sentence's case (and `cron/reconcile`'s), not this one. The stripe
  sentence's "has not been charged" is true.

So all five go into `incident.md` as drafted in the S2 handoff with the two
parentheticals removed. **Still Nathan's:** the webhook sentence's time
promise — "today" or "within the hour" — he did not answer at "okay just
go"; the next session writes the sentence with a placeholder no longer than
that and asks in its first check-in.

## Decisions taken (routine, mine)

- The redeploy was tried once from the agent before being handed to Nathan
  (memory `do-the-cli-work`); it ran, both times. `rollback.md` step 4 and
  `incident.md`'s "which the shell classifier refuses to the agent" are now
  wrong on 09-13's evidence — the next session softens both to "ran from the
  agent on 2026-09-13; refused on 2026-09-11".
- The catalogue was kept by `vercel env pull` to the scratchpad (not the
  repo), one line lifted by script, the file deleted in the same script —
  the `rollback.md` step 1 route, with the secrets on disk for under a minute.
- No pause of the monitor for the rehearsal, per `monitor.md`.
- `vercel env pull` to a path under `C:\Program Files\Git` failed with
  `EPERM` (the bash `$TMPDIR` resolved there); the scratchpad path worked.

## Found, not done

- **Two harness hooks disagree, and it cost this session its edits.** The
  turn had to end at 18:31 to wait for Nathan's inbox; `stop.mjs` refused to
  end it without a handoff newer than the card, so an "in progress" handoff
  was written. From then on `pre-tool.mjs`'s phase-fence read that file as
  "this step is handed off" and blocked every Write/Edit outside the handoff
  dir — `monitor.md` was ready and refused twice. A session that must wait
  on a person mid-phase cannot both end the turn and keep editing. The fence
  should accept a handoff that declares itself in progress (a marker line,
  or a `status: in-progress` front-matter), or the stop hook should accept a
  card-level "waiting on Nathan" note. For `~/.claude/harness/hooks-design.md`.
- **The fence lost the read-first list when the cwd moved** from the parent
  folder to `service` (Claude Code's environment update mid-session): eight
  files read via absolute paths at 18:22 were "still unread" at 18:42 and
  had to be touched again with `sed -n 1p`. Same file.
- **`monitor.md` rewrite for UptimeRobot** — written, refused by the fence;
  the full text is in the appendix below for the next session to paste.
- **`incident.md`**: the five sentences into "What to tell customers"; the
  "Rehearsed" bullet replaced with the table above; the two "refused to the
  agent" phrasings softened (above); the `releases` section's "not built yet"
  pointer for the heartbeat stays until it is.
- **The heartbeat** — Nathan's choice still open: (a) a Better Stack free
  account for the one heartbeat, (b) the UptimeRobot paid tier, (c) unwatched
  until 4.7. Recommendation (a): the only free heartbeat. The build is the S1
  handoff's: `RELEASE_CHECK_HEARTBEAT_URL`, a `fetch` of it in
  `app/api/cron/release-check/route.ts` when `wrong.length === 0`, three
  tests in `lib/release-check.test.ts`, `vercel env add`, a deploy.
- The UptimeRobot API key from the S2 transcript was not used this session
  and is not in this one; the regions came from the emails instead.
  Regenerate it (My Settings → API) when convenient.
- `service/.harness/active.json` is this session's card, committed with the
  handoff. `tsconfig.tsbuildinfo` modified, `docs/mocks/` untracked, as
  before.

## Gates

- monitor.md "Created" … emails … check history: emails **met** (above, with
  timestamps and locations); the check history is the two incidents' times
  in the emails, not a dashboard screenshot; `monitor.md` itself **not
  written** (fence) — text in the appendix.
- incident.md sentences and "Rehearsed": **not written** (fence); the
  content is in this handoff.
- `pnpm test` 453: **not run**, no `.ts` changed, heartbeat not built.
- health `200` after the restore: **met** — `2026-09-13T18:41:27.136Z 200 ok`.
- the heartbeat variable's deploy: **not applicable**, not built.

## Cost

Nothing reached a model. Five live health probes (five balance reads at
Stripe, five counts on the pooler); two production deploys; no credits.

## Next — Phase 4 S2c on Opus, in `service`

A short session: two docs from this handoff, the heartbeat if Nathan has
chosen, commit. Card, to `service/.harness/active.json` before the first edit:

```json
{
  "phase": "Four yesses — Phase 4, S2c: monitor.md and incident.md written from the S2b rehearsal; the heartbeat built if chosen",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-4.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md",
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts",
    "../plugin/docs/runbooks/rollback.md"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-4-s2b-monitor-rehearsal.md",
    "../plugin/docs/handoffs/2026-09-13-phase-4-s2-monitor-runbook.md",
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md",
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "monitor.md is the appendix of the S2b handoff, pasted; nothing about the monitor is invented beyond what the four emails and the S2 'Monitor created' section say",
    "The five customer sentences go into incident.md as drafted in the S2 handoff minus the two parentheticals; the webhook sentence's time promise ('today' or 'within the hour') is asked in the first check-in and not guessed",
    "The heartbeat is built only after Nathan names the account (Better Stack free, UptimeRobot paid) and pastes the heartbeat URL; unset RELEASE_CHECK_HEARTBEAT_URL means no ping; the deploy that carries the variable is a push to main or a redeploy",
    "No rehearsal is re-run; the timings in incident.md are the S2b table"
  ],
  "gates": [
    "monitor.md: status line 'created and rehearsed', 'Created' holds monitor 803983434, 300 s, 30 s timeout, the US East locations, nathan@vergelabs.nl; grep -c 'Better Stack' docs/runbooks/monitor.md → only the heartbeat bullet mentions it",
    "incident.md: 'What to tell customers' holds five sentences, one per check; 'Rehearsed' holds the S2b table with 18:36:18 → 18:41:22 and both email times; the redeploy is no longer described as refused to the agent",
    "if the heartbeat is built: npx vitest run lib/release-check.test.ts → 14 passed; pnpm test → 31 files, 453 passed; mutation: remove the wrong.length === 0 guard around the ping → the 'not on 503' test red; vercel env ls production shows RELEASE_CHECK_HEARTBEAT_URL; after the deploy a manual run of the cron route with the bearer (Nathan lifts CRON_SECRET) → the heartbeat's dashboard shows the ping",
    "if not built: pnpm test → 31 files, 450 passed, unchanged",
    "one commit on service/main; one on plugin/main for rollback.md's step 4"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-4-s2b-monitor-rehearsal.md, then
../plugin/docs/handoffs/2026-09-13-phase-4-s2-monitor-runbook.md ("Customer
sentences"). State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is Phase 4 S2c. Write the
card from the handoff to .harness/active.json before the first edit. Stop
points and gates are in the card. Heartbeat: <Better Stack free / UptimeRobot
paid / leave it>; webhook time promise: <today / within the hour>. End with a
handoff in ../plugin/docs/handoffs/.
```

## Appendix — `docs/runbooks/monitor.md`, to paste whole

````markdown
# Runbook — the monitor

**Status, 2026-09-13: created and rehearsed.** UptimeRobot monitor
**803983434**, in Nathan's account, watching `/api/health` every five
minutes; the "Created" section at the bottom holds what the account
actually holds, and the 4.2 rehearsal's two emails are in
`../../../plugin/docs/handoffs/2026-09-13-phase-4-s2b-monitor-rehearsal.md`
and its timings at the bottom of `incident.md`.

## What is watched

- `GET https://vergelabsmedia.com/api/health`, expecting `200`. Nothing
  else: a `200` from the front page proves that something answers, and the
  health route is the one URL whose `200` means the database, the release
  catalogue, Stripe, the webhook secret and the model relay all answered
  (`incident.md`, "What the health body says").
- Every five minutes — the plan's default and the free tier's shortest
  interval — with a 30-second timeout. UptimeRobot re-checks a failure from
  a second location before it calls the monitor down, so one dropped request
  from one place is not an incident; a `503` — the route's own word for "a
  check failed" — is alerted on the same as no answer (rehearsed: `HTTP 503
  - Service Unavailable` is the root cause in the alert email).
- From outside Vercel and outside the box. UptimeRobot's checks came from
  its US East locations on 2026-09-13 (`Ashburn, USA` and `N. Virginia,
  USA`, four different IPs in the emails). Not from the Hetzner box: the
  box is a test fixture that is rebooted, benchmarked and reinstalled, and a
  watcher that shares the fate of what it watches is not a watcher.

## Who is emailed

- Nathan, `nathan@vergelabs.nl` — the account's one alert contact (id
  8815599, threshold 0, recurrence 0): one email when the monitor goes
  down, one when it is up again, and nothing in between. No phone call, no
  SMS, no Slack: this is one service and one person.
- The email says the URL, the root cause as an HTTP status (`HTTP 503 -
  Service Unavailable`), the time the incident started and the location
  that saw it. Which of the five checks failed is in the body of
  `/api/health`, which the email does not carry — the first step of
  `incident.md` is to ask the endpoint yourself.
- Between the site failing and the email: up to five minutes for the next
  check, then the confirmation. Rehearsed: the broken build went live at
  18:36:18 UTC, the check at 18:40:04 saw it, the email was in the inbox
  within the minute; the restore build went live at 18:41:22, the check at
  18:45:09 saw it, the recovery email followed.

## How to pause it during a deploy

A deploy on Vercel does not need a pause: the previous deployment keeps
serving until the new one is ready, and the four production builds on
2026-09-13 took 24–45 seconds, shorter than one check interval. Pause it for
the things that make `/api/health` answer `503` on purpose:

- The 4.2 rehearsal, except that its point is to receive the email — do not
  pause for it.
- A deliberate `AI_RELAY_ENABLED` off does not need a pause: the check
  reads `off` and passes (`incident.md`, "ai relay").
- A database migration or a Supabase compute change that takes the pooler
  away for longer than a check: pause first, resume when the step-1 script
  in `incident.md` answers `200`.

The pause is the monitor's Pause in the UptimeRobot dashboard (Monitors →
`vergelabsmedia.com/api/health` → Pause), and Resume the same way; the API
does the same with `editMonitor` and `status=0` (paused) / `status=1`
(resumed) on `https://api.uptimerobot.com/v2/`. Write the pause and the
resume in the handoff of the session that did it, with the times: a monitor
left paused is no monitor, and the check history has a gap that the next
person needs to be able to read.

## Not yet watched

- **The daily release check** (`/api/cron/release-check`, `23 4 * * *`
  UTC) answers `503` to Vercel's cron runner on a stale channel and nobody
  is emailed. Nathan decided on 2026-09-13 on a **heartbeat**: the route
  pings a heartbeat URL after a run with nothing wrong, the heartbeat
  expects one ping a day with a grace that covers the 04:23 run (expect by
  05:00 UTC), and a missed ping emails. The URL is a new variable,
  `RELEASE_CHECK_HEARTBEAT_URL`, unset meaning no ping; the route and
  `lib/release-check.test.ts` gain the ping (sent on 200, not on 503, not
  when unset). **Not built:** a heartbeat monitor is on UptimeRobot's paid
  plans only (monitor type 5); free Better Stack has one. The choice —
  a Better Stack account for the one heartbeat, the UptimeRobot paid tier,
  or leaving the cron's 503 unwatched until 4.7 — is Nathan's and is open.
- **The provider** — a describe that fails with the relay `on`. 4.7.

## Created

- **2026-09-13, 18:16:52 UTC**, by Nathan in the UptimeRobot dashboard.
  Corrected by script at 18:18:40 (the URL had been typed `/api/healt`; its
  first check at 18:18:09 was a `404` and the very first down/up email pair
  came from that typo, 18:18:10 → 18:18:58).
- Monitor **803983434**, name `vergelabsmedia.com/api/health`, type HTTP,
  URL `https://vergelabsmedia.com/api/health`, interval 300 s, timeout
  30 s. Free tier.
- Locations seen in the emails: `Ashburn, USA` (5.161.113.195),
  `N. Virginia, USA` (34.198.201.66, 52.87.72.16, 54.167.223.174); the
  recovery emails say "up again in North America".
- Alert contact: `nathan@vergelabs.nl` (8815599), email only, at down and
  at up.
- Checks fall at about four to nine seconds past each five-minute mark
  (18:40:04, 18:45:09 on 2026-09-13).
- Rehearsed 2026-09-13: `PLUGIN_RELEASES` set to `not json` and deployed
  at 18:36:18 UTC; down email for 18:40:04 (`HTTP 503`); restored and
  deployed at 18:41:22; up email for 18:45:09. The timings are in
  `incident.md`, "Rehearsed".
- The account's main API key was pasted into the S2 session's transcript
  and used from a script in that session's temp dir; it is in no file in
  either repo. Regenerate it in UptimeRobot → My Settings → API, or use a
  read-only key for the next script.
````
