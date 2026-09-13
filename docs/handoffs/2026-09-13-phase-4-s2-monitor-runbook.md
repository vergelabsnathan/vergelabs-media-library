# Handover — Phase 4, S2: 4.8's runbook written, 4.2's monitor specified, not created (Opus 5)

2026-09-13. One session in `service`, one commit on `main`, no deploy, no
code changed. Nothing reached a model, Stripe was asked for its balance
three times by the live health route (three probes), the pooler counted
`licences` three times. No credits.

**4.8 is written; 4.2 is specified and waiting on Nathan three times** — the
Better Stack account, the two redeploys of the rehearsal, and the customer
sentences. None of the three can be done from a session. The gates that
need the monitor (the emails, the check history, the timed walk) are not
met; the gates that are documents are.

## What shipped

- `docs/runbooks/incident.md` — rewritten around the five checks. "When
  the alert fires": the three steps every time (the probe script and its
  healthy answer at 17:50 UTC; `vercel logs <deployment URL>` and what a
  passing and a failing health hit print there; note the time), then the
  two steps common to every check — the variable step (`vercel env rm` +
  `add`, 11 s on 09-11) and the deployment step (`vercel redeploy`,
  Nathan's). A section per check — `database`, `releases` (with the daily
  release check under it, unchanged from S1), `stripe`, `webhook secret`,
  `ai relay` — each with what fails there, what to look at in order, what to
  change, how to confirm. "What to tell customers" holds the stop point,
  not sentences. `rollback.md` and `recovery.md` by relative path. A
  "Rehearsed" section: 09-11's rollback halves; the 4.2 break not yet run,
  with what it needs.
- `docs/runbooks/monitor.md` — status line first ("specified, not
  created"); what is watched and why only `/api/health`; five minutes, a
  confirming second request, `503` alerted the same as no answer; from
  Better Stack's regions, not the box, and why; who is emailed (Nathan,
  alert and recovery, nothing else); how to pause during a deploy (a deploy
  needs no pause — builds are 24–39 s, shorter than one interval; pause for
  a migration that takes the pooler away; the relay off passes and needs no
  pause; never pause for the rehearsal); "Not yet watched" — the release
  check's heartbeat per Nathan's 09-13 decision, and the provider (4.7);
  "Created" — not yet, and what gets written there when it is.
- `README.md` — the service had none. What the service is, the five
  runbook pointers, the health line.
- `.harness/active.json` — this session's card, from the S3 handoff.
- Commit on `service/main`: `docs(runbooks): 4.8 incident runbook per
  check; 4.2 monitor specified`.

## Evidence

- Gate "incident.md: a section per health check": `grep -c '^## \`' docs/runbooks/incident.md` → 5 (`database`, `releases`, `stripe`, `webhook secret`, `ai relay`); each has "Look, in order", "Change", "Confirm". Customer sentences: held (stop point 2). The 4.2 walk timed at the bottom: **not met** — not run.
- Gate "monitor.md: what is watched, from where, who is emailed, how to pause": the four sections exist under those heads. The monitor itself: **not met** — no account.
- Gate "node fetch of /api/health → 200 after the restore": no restore happened; the probe answered `200` three times this session, the last at `2026-09-13T17:53:38.719Z 200 ok`. The script is the one in `incident.md` step 1.
- Gate "pnpm test unchanged": `Test Files 31 passed | 7 skipped (38)`, `Tests 450 passed | 14 skipped (464)`, 58.88 s, run at 18:49 local before any edit; no `.ts` changed after it.
- Gate "the monitor requests /api/health every five minutes … emails screenshotted": **not met**, nothing to screenshot.
- Steps the runbook names were each run once, per 4.8's "do not": the probe script (three times); `vercel ls` (24–39 s builds, six Ready deployments); `vercel env ls production` (names and ages only — the list is where the ages in the sections come from); `vercel logs <URL>` streamed for 40 s with a health hit inside the window — it prints nothing for a passing hit, so the runbook says so; the four status pages fetched (`vercel-status.com` "All Systems Operational", `status.supabase.com` "Partially Degraded Service" while our check passed — that observation is in the `database` section; `status.stripe.com` 200; `status.openrouter.ai` 200). Not run: any redeploy; any Stripe dashboard step (the runbook names the pages, not clicks); a `vercel env pull` (it writes every production secret to disk — named as a step with "then delete `prod.env`", not run today).
- The inline `node -e "fetch(…)"` is blocked by the shell hook for the agent (the message says so); a script file is not. The runbook gives the script, not the one-liner.

## Stop points

1. **Which monitor and whose inbox** — proceeded on the default (Better Stack free tier, five-minute check on `/api/health`, email to Nathan); `monitor.md` says so. The account cannot be created from a session: it is registered to Nathan's address. Two ways in, either works: Nathan creates the monitor in the dashboard from `monitor.md`'s "What is watched" (URL, expected 200, five minutes, a confirmation period, email on/call off), or he creates the account and pastes an Uptime API token into the next session, which creates the monitor and the heartbeat by `POST https://uptime.betterstack.com/api/v2/monitors` from a node script and records the answer in `monitor.md`. The token route is the one the next card assumes; the dashboard route only needs the "Created" section filled in.
2. **The customer sentences** — drafted below, not written in. Two facts in them are unverified and are marked.
3. **The rehearsal** — not run. It needs (a) the monitor, (b) `PLUGIN_RELEASES` set to `not json` and **a redeploy**, (c) ≥ two failed checks — ten minutes at a five-minute interval, plus the build — then (d) the variable restored and **a second redeploy**. The redeploy is refused to the agent (twice on 09-11, `rollback.md`); both are Nathan's, so the ten-minute window is his to keep. With a five-minute interval and "two consecutive failures", the window can run to fifteen minutes; Better Stack's confirmation period (a re-check from a second region seconds later) instead of a second interval keeps it inside ten. The next session proposes that in its first check-in.

## Monitor created — after the handoff above, same session, 18:16–18:19 UTC

Nathan chose **UptimeRobot**, not Better Stack, by making the monitor
himself and pasting his API key into the conversation. What the account
holds, read and corrected by script (`getAlertContacts`, `getMonitors`,
`editMonitor` on `https://api.uptimerobot.com/v2/`):

- Monitor **803983434**, type 1 (HTTP), interval **300 s**, timeout 30 s,
  created 18:16:52 UTC with the URL `https://vergelabsmedia.com/api/healt`
  — a missing `h` — so its first check at 18:18:09 was `404 Not Found` and
  the status was 9, "seems down".
- Corrected at ~18:18:40 to `https://vergelabsmedia.com/api/health`, named
  `vergelabsmedia.com/api/health`, alert contact 8815599
  (`nathan@vergelabs.nl`, threshold 0, recurrence 0 — one email at down,
  one at up) attached. The account's one contact.
- **Up at 18:18:57 UTC** (log type 2, status 2). The 404 → up transition
  happened after the contact was attached, and **Nathan confirmed the "is
  UP" email arrived** (his words, ~18:25 UTC): the recovery-email half of
  the gate is real, unrehearsed. The screenshot goes into the S2b handoff
  with the rehearsal's pair.
- UptimeRobot re-checks from a second location before it declares down, so
  one failed five-minute check alerts: the rehearsal's break fits in ten
  minutes with no "two consecutive" setting to make.
- The API key is in this conversation's transcript. It is the account's
  main key (it edits and deletes). Nathan regenerates it in UptimeRobot →
  My Settings → API when the rehearsal is done, or uses a read-only key
  next time. It is in no file in either repo; the script that used it is
  in the session's temp dir.

This changes the next card: `monitor.md` is written for Better Stack and
is rewritten for UptimeRobot ("Created" filled from the facts above; the
pause is UptimeRobot's Pause on the monitor, which the API also does with
`editMonitor status=0`). The **heartbeat** for the release check is on
UptimeRobot's paid plans only (monitor type 5); free Better Stack has it.
Nathan's 09-13 heartbeat decision therefore has a question on it: a second
account at Better Stack for the one heartbeat, the UptimeRobot paid tier,
or the cron route's 503 stays unwatched until 4.7. The next session asks
in its first check-in and does not build the ping before the answer.

Still Nathan's for the rehearsal: the two redeploys. Nothing else.

## Customer sentences, drafted for approval (stop point 2)

Written to the copy standard: the fact, then what it means for them. Channel is email through Resend — the site has no status line (`public/index.html` carries the design-system banner, nothing for incidents). Nathan edits or approves; then they go into `incident.md`, "What to tell customers", one per situation.

- **database** — "The service cannot reach its database, so describing, licence checks and credit balances are unavailable. Your pictures, folders and descriptions on your own site are untouched. We will write again when it is back."
- **releases** — "The update channel is answering with an error, so Pro sites are offered no update. The version you run keeps working; nothing on your site changes. Updates are offered again once the channel is fixed."
- **stripe** — "Purchases are failing at our payment provider. Existing licences and credits are unaffected. A card that was refused has not been charged — *(unverified: a Stripe outage can leave a PaymentIntent in `requires_action`; the sentence should not promise "not charged" until the webhook runbook's answer table says what state a failed purchase leaves)* — we will write when purchases work again."
- **webhook secret** — "Your payment went through and the licence or credits did not arrive. The purchase is recorded on our side and we are issuing it by hand *(the time promise is Nathan's — "today" or "within the hour")*."
- **ai relay** — "Describing is paused while the model provider is unavailable. Filing, search and everything already described keep working. Pictures waiting to be described are described when it is back, and *(unverified: whether a failed describe is refunded — `lib/limits.ts` and the ledger say; the sentence should not claim it until read)* no credits are taken for a picture that was not described."

## Decisions taken (routine, mine)

- `incident.md` is organised by check because the health body's `failing` names the check: the alert email does not, so the page starts with asking the endpoint. The S1 "What the health body says" section is kept verbatim as the reference table.
- The two steps every check shares (variable, deployment) are written once at the top, not five times.
- `README.md` created rather than skipped: the plan names "the service README" as a pointer's home and there was none; it is fourteen lines.
- `monitor.md` states the plan's five-minute default and does not state the tier's interval, regions or the address: those are read off the dashboard when the monitor exists and go into "Created". Nothing in the file describes a monitor as running.
- `recovery.md`'s pointer (plugin side) not written: the fence resolves scope entries inside the project root, so `../plugin/docs/recovery.md` can never match from a `service` session — that is the hook's design (`r === null` for a path outside root), not a card mistake. Five lines, for a plugin-side session; the text is below.

## Found, not done

- **The heartbeat code** (Nathan's 09-13 decision, S1 handoff): `RELEASE_CHECK_HEARTBEAT_URL`, the ping in `app/api/cron/release-check/route.ts` on `wrong.length === 0`, three tests in `lib/release-check.test.ts`. The S3 card did not carry the two files; the fence froze the card at the first edit. On the next card below.
- **`../plugin/docs/recovery.md`** — a section "If it was the service" before "If it was us": *A site that is up but whose describes, licence checks or updates fail is the service's incident, not the site's: `../../service/docs/runbooks/incident.md` — `/api/health` says which of the five checks failed, and the page says what to look at, what to change and how to confirm. What watches the service from outside, and who it emails, is `../../service/docs/runbooks/monitor.md`.* For the next `plugin` session (Phase 3 S3's card can carry it).
- The card's `readFirst` names `docs/runbooks/rollback.md` in the service; the file is `../plugin/docs/runbooks/rollback.md`. The fence skips a missing entry, so nothing blocked; the next card names the right path.
- `status.supabase.com` read "Partially Degraded Service" at 17:55 UTC while every check passed; not ours to act on, noted in the `database` section as a reason not to trust the page alone.
- `docs/mocks/` is untracked in `service` from before this session and left alone; `tsconfig.tsbuildinfo` modified, as before.
- `tools/` in the service holds forty-odd `_*.mjs`/`_*.py` scratch files from earlier sessions; a sweep is a chore for a Phase 5 or later card, not this one.

## Cost

Nothing reached a model. Three live health probes (three balance reads at Stripe, three counts on the pooler); four status-page fetches; no deploy; no credits.

## Next — Phase 4 S2b on Opus, in `service`, when Nathan has (1) the Better Stack token or the monitor, (2) approved or edited the five sentences

Card, to `service/.harness/active.json` before the first edit:

```json
{
  "phase": "Four yesses — Phase 4, S2b: 4.2 the monitor created, the heartbeat built, the break rehearsed; 4.8 the sentences written in",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-4.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md",
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-4-s2-monitor-runbook.md",
    "../plugin/docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md",
    "../plugin/plans/four-yesses/phase-4.md",
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md",
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts",
    "../plugin/docs/runbooks/rollback.md"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "The monitor exists: UptimeRobot 803983434 on /api/health, 300 s, email to nathan@vergelabs.nl (this handoff, 'Monitor created'); monitor.md is rewritten for UptimeRobot from those facts, nothing invented; the heartbeat for the release check is paid on UptimeRobot — Nathan picks a second Better Stack account, the paid tier, or leaves it unwatched, before the ping is built",
    "The customer sentences go into incident.md only as approved in the S2 handoff or as Nathan edits them; the two facts marked unverified are read from the code first (a failed purchase's state; whether a failed describe is refunded) and the sentence changed if the code says otherwise",
    "The rehearsal breaks production deliberately: PLUGIN_RELEASES to 'not json', a redeploy (Nathan's), the alert email, the variable restored, a second redeploy (Nathan's), the recovery email — at most ten minutes broken; the session proposes the confirmation-period setting so two failures fit inside ten minutes, and does not start the break before Nathan says he is at the terminal for both redeploys",
    "RELEASE_CHECK_HEARTBEAT_URL is set on Vercel production by the session (vercel env add) once the heartbeat exists; the deploy that carries it is Nathan's"
  ],
  "gates": [
    "monitor.md 'Created': date, monitor name, interval as the tier gave it, regions, address; the alert and recovery emails screenshotted into the handoff with timestamps; the monitor's check history showing the window",
    "incident.md: 'What to tell customers' holds the five approved sentences; 'Rehearsed' holds the 4.2 break walked by the releases section's steps with times",
    "pnpm test → 31 files passed, 453 tests passed (450 + the three heartbeat tests): the ping is sent on 200, not on 503, not when RELEASE_CHECK_HEARTBEAT_URL is unset; mutation: remove the wrong.length === 0 guard and the 'not on 503' test goes red",
    "a node script's fetch of https://vergelabsmedia.com/api/health → 200 after the restore, pasted with its timestamp",
    "after the deploy that carries the heartbeat variable: vercel ls shows it serving, and the next 04:23 UTC run pings (the heartbeat's dashboard shows the ping) — or a manual run with the bearer, logged in the handoff"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-4-s2-monitor-runbook.md, then
../plugin/docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md
("Stop point, decided") and ../plugin/plans/four-yesses/phase-4.md tasks 4.2
and 4.8. State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is Phase 4 S2b. Write the
card from the handoff to .harness/active.json before the first edit. Stop
points and gates are in the card. End with a handoff in
../plugin/docs/handoffs/.
```

The alternative while Nathan's three inputs are outstanding is Phase 3 S3
(3.5 + 3.9) in `plugin`, whose card is in
`docs/handoffs/2026-09-13-phase-3-s2-shell-paste-tree.md`; that session can
also carry the `recovery.md` pointer above.
