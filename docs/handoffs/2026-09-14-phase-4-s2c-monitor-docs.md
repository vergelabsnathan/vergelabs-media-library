# Handover — Phase 4, S2c: monitor.md and incident.md written from the rehearsal; rollback.md step 4 softened; the heartbeat chosen, not built (Opus 5)

2026-09-14, one session in `service`. No code changed, nothing reached a
model, no deploy, no credits. Two commits: `1bc1f50` on `service/main`,
`b1a1848` on `plugin/main`.

**What is met:** 4.2's `monitor.md` says "created and rehearsed" and holds
what the account holds; 4.8's `incident.md` holds the five customer
sentences and the timed 4.2 table; `rollback.md` step 4 no longer says the
redeploy is refused. **What is not:** the heartbeat — Nathan chose Better
Stack free this session but has no heartbeat URL yet, so the route, the
tests and the variable are untouched and `pnpm test` stays at 450.

## Decisions Nathan took this session (the three stop points)

- **Heartbeat: Better Stack free.** He has not created the account or the
  heartbeat yet. The ping is built in the session he pastes the URL into.
- **Webhook sentence's time promise: "today".** Asked three times before it
  landed — he did not know what the webhook was or why the word was his.
  The plain version that worked: Stripe has taken the money, its "paid,
  issue it" message fails to verify on our side, so nothing is issued;
  subscriptions self-heal in the 03:17 `reconcile` cron, one-time purchases
  (credits, lifetime) do not, and the sentence promises how fast he issues
  those by hand. Zero occurrences so far. Recorded so no session asks again.
- **`rollback.md` from a `service` session: apply by script.** The fence
  cannot match a path outside the project root (S2 handoff, "Decisions
  taken"); Nathan chose a Python replace over deferring to a plugin session.
  The script is `rollback-step4.py` in this session's scratchpad; it
  asserted the old text occurred once and preserved line endings.

## What shipped

- `service/docs/runbooks/monitor.md` — the S2b appendix, pasted whole, with
  one change: the heartbeat bullet's last sentence now records the
  2026-09-14 Better Stack choice instead of listing the three options.
- `service/docs/runbooks/incident.md` — four edits: the deployment step
  ("ran from the agent's terminal twice on 2026-09-13 and was refused by
  the shell classifier on 2026-09-11, so if it is refused it is Nathan's";
  builds 24–45 s); the release-check bullet's heartbeat sentence dated both
  decisions; "What to tell customers" holds the five sentences as drafted in
  S2 minus the two parentheticals, the webhook one ending "by hand today",
  with a lead-in naming the two route files the facts were read from;
  "Rehearsed" holds the 09-11 bullet (now "refused to the agent that day")
  and the 09-13 break as the S2b table with the 5 min 4 s window.
- `plugin/docs/runbooks/rollback.md` — step 4: "Production builds took
  24–45 seconds on 2026-09-11 and 09-13 … It ran from the agent's terminal
  twice on 2026-09-13 (the monitor rehearsal, `incident.md`, "Rehearsed")
  and was refused by the shell classifier on 2026-09-11; if it is refused,
  it is Nathan's". The "Rehearsed" section's 09-11 account of the refusal is
  left as it is — it is a dated fact.
- `service/.harness/active.json` — this session's card, from the S2b
  handoff.

## Gates

- monitor.md: line 3 `**Status, 2026-09-13: created and rehearsed.**`;
  `grep -c 'Better Stack'` → 2, both lines inside the one heartbeat bullet
  (lines 80–81); "Created" holds 803983434, 300 s, 30 s, Ashburn /
  N. Virginia with the four IPs, `nathan@vergelabs.nl` (8815599). **Met.**
- incident.md: five `- **check**` bullets under "What to tell customers";
  "Rehearsed" holds `18:36:18 → 18:41:22`, `18:40:04` and `18:45:09`;
  `grep 'refuses to the agent\|not run by the agent'` → 0; the five
  ``## `check` `` headings still count 5. **Met.**
- heartbeat gates: **not applicable**, not built.
- `pnpm test`: `Test Files 31 passed | 7 skipped (38)`, `Tests 450 passed |
  14 skipped (464)`, 140.74 s. **Met** — on the second run; see below.
- one commit per repo: `1bc1f50` (service), `b1a1848` (plugin). **Met.**

## Found, not done

- **`lib/login-drive.test.ts` flakes under load.** First full run: `2
  failed | 448 passed` — "locks the address after MAX_ATTEMPTS and the
  thirty-first gets 429 whatever the password" and "is counted per address,
  not per IP". Alone: `3 passed (3)` in 17 s. Second full run: all 450.
  Both full runs took 140 s (S2's took 59 s on the same machine); the suite
  drives thirty attempts inside a one-minute window and the window straddles
  under a slow parallel run. Not this card's file. A fix is either fake
  timers in that suite or a wider window; for a Phase 5 or later card.
- **The fence lost the two handoff reads when the cwd moved** from the
  parent folder to `service` at the first Bash call, exactly as in S2b;
  `sed -n 1p` on both from `service` re-registered them. Same
  `hooks-design.md` item as S2b's: the read-first list should key on the
  resolved absolute path, not the cwd-relative one.
- **The fence and a scope entry outside the root** — third session to hit
  it (S2 recorded it, S2b carried it, this one asked). Either the fence
  resolves `../` entries against the parent folder, or cards stop listing
  cross-repo files and the plugin-side session carries them. For
  `hooks-design.md`.
- `recovery.md`'s "If it was the service" section (S2 handoff, "Found, not
  done") is still unwritten; a plugin-side card.
- The UptimeRobot main API key from the S2 transcript is still to be
  regenerated (My Settings → API).
- `tsconfig.tsbuildinfo` modified, `docs/mocks/` untracked, as before; not
  committed.

## Cost

Nothing reached a model. No live probes, no deploys, no credits. Two full
test runs, one single-file run.

## Next — the heartbeat, on Opus, in `service`, when Nathan has the URL

Nathan, first: Better Stack → sign up (free) → Heartbeats → Create: name
`release-check`, period 1 day, grace 40 minutes (the run is 04:23 UTC; the
S1 decision says expect by 05:00), email on missed. Copy the URL it gives
(`https://uptime.betterstack.com/api/v1/heartbeat/…`) into the opener.

Card, to `service/.harness/active.json` before the first edit:

```json
{
  "phase": "Four yesses — Phase 4, S2d: the release-check heartbeat built and deployed",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-4.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts",
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-14-phase-4-s2c-monitor-docs.md",
    "../plugin/docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md",
    "app/api/cron/release-check/route.ts",
    "lib/release-check.test.ts",
    "docs/runbooks/monitor.md"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "The heartbeat URL is the one Nathan pasted into the opener; nothing is created at Better Stack from the session",
    "The ping is one fetch of RELEASE_CHECK_HEARTBEAT_URL after the results are known, only when wrong.length === 0; a failed ping is logged and does not change the response; unset means no fetch",
    "vercel env add RELEASE_CHECK_HEARTBEAT_URL production is the session's; the deploy is a push to main (the commit) or a redeploy — say which before it goes out",
    "The manual cron run needs the production CRON_SECRET in the bearer: Nathan lifts it (vercel env pull to the scratchpad, the line lifted by script, the file deleted) or runs the request himself"
  ],
  "gates": [
    "npx vitest run lib/release-check.test.ts → 14 passed (11 + three: pinged on 200, not on 503, not when unset)",
    "mutation: remove the wrong.length === 0 guard around the ping → the 'not on 503' test red; put it back → green; both output lines in the handoff",
    "pnpm test → 31 files, 453 passed",
    "vercel env ls production shows RELEASE_CHECK_HEARTBEAT_URL; vercel ls shows the deploy that carries it serving",
    "a manual run of /api/cron/release-check with the bearer → 200, and the Better Stack heartbeat's page shows the ping with its time (a screenshot into the conversation)",
    "monitor.md 'Not yet watched': the release-check bullet moves to a 'Watched' line with the heartbeat's name, period, grace and the date; incident.md's release-check bullet says the heartbeat is built and where its email goes"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-14-phase-4-s2c-monitor-docs.md, then
../plugin/docs/handoffs/2026-09-13-phase-4-s1-health-release-check.md (the
heartbeat design). State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is Phase 4 S2d. Write the
card from the handoff to .harness/active.json before the first edit. Stop
points and gates are in the card. Heartbeat URL: <paste>. End with a handoff
in ../plugin/docs/handoffs/.
```

Phase 4's remaining items after S2d are on the 2026-09-13 launch-order note:
Phase 5 on Opus next, Phase 3 S3 after the Fable reset.
