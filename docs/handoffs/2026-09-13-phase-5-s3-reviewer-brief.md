# Handover — Phase 5, S3: 5.9's brief — the reviewer's pack, written (Opus 5)

2026-09-13. One session in `plugin`, one commit on `main`, no deploy. Two
documents created, no code, no suite, no config changed. Nothing reached a
model, Stripe or the database; the eight security suites ran once on the box
(the five local ones twice), same counts as S2a. No credits.

**The brief is written, not sent.** Sending is Nathan's (stop point 3); the
date goes into `docs/security-brief.md` and `docs/security-findings.md` once
he says it went. Who reviews and what it costs is untouched (stop point 1).

## What shipped

- `docs/security-brief.md` — the reviewer's pack. In order: what is asked
  (the three questions, and what a table cannot hold); scope in and out,
  verbatim from `SECURITY.md`; the spine (`docs/security-surface.md`, its
  nine tables and 158 rows, the two rows with no capability found — the
  notice-dismiss AJAX and the transport bridge, which I read: it builds a
  `WP_REST_Request` under `/vergeml/v1/` only and hands it to
  `rest_do_request()`, so the endpoint gate applies); what is already proven
  and by what (the eight suites with today's counts; the roles coverage count
  with the commands; the five registers with what each holds and its suite;
  the service's `docs/security.md` in six lines); the known open items Nathan
  routed here on 2026-09-13, eleven rows with where and what we think; how to
  get a copy (zip, Playground, a box, the service); how to report; and a
  closing "For Nathan, before it goes" list.
- `docs/security-findings.md` — the register: the four columns (finding,
  status, task, commit) with what each holds, the four statuses defined,
  severity in the finding's text rather than a column, and an empty rows
  table.
- `.harness/active.json` — this session's card, from the S2c handoff.
- Commit `a090acd` `docs(security): 5.9 — the reviewer's brief and the
  findings register`.

## Evidence

- The coverage count, from the files:
  - `awk '/^## REST endpoints/,/^### REST rows/' docs/security-surface.md | grep -c '^| \`/vergeml/v1/'` → **76**.
  - `node tools/verify.mjs roles` → `ok the REST server holds 74 of our
    endpoints, with the file renamer off -- found 74`; `ok /vergeml/v1/rename-files
    is not registered, because VERGEML_FILE_RENAME is off`; **19/19 passed**.
  - `grep -c "admin-ajax\|wp_ajax\|admin_post\|…\|show_in_rest" tests/security/roles.php` → **0**.
  - The other tables, by the same `awk` over their sections: AJAX 7,
    `admin_post` 11, cron 4, screens 13, front end 2, settings 7, meta 2,
    hooks 36 — matching the surface's own count table.
  - **74 of 76 REST rows; 74 of 107 gated entries (REST + AJAX + admin_post +
    screens); 74 of 158 rows.** The 33 gated entries no suite calls as four
    kinds of person are named in the brief as the reviewer's first ground.
- Gate 3, `node tools/verify.mjs surface db-calls escaping globals roles paths
  hosts secrets` at `f3c2fc7`, before the edits: surface **26/26**, db-calls
  **11/11**, hosts **13/13**, escaping **9/10**, globals **7/7**, roles
  **19/19**, paths **66/66**, secrets **78/78**; runner summary `passed
  surface, db-calls, hosts, globals, roles, paths, secrets / FAILED escaping
  (exit 1)`. The red row is `js/vergeml-folders.js:667 innerHTML ← sprintf(…)`,
  the same row as in the S1 and S2a handoffs, Phase 3 S3's. The five local
  suites re-run after the two docs were written: the same five lines.
  "Unchanged by this session" holds; "green" holds for seven of eight and was
  already not true for escaping when the card was written.
- Gate 1: the brief names `docs/security-surface.md`; the coverage count
  with its commands; `docs/security-hosts.md`, `docs/security-secrets.md`
  and the Files section of the surface (and the two generated registers
  beside them); `../service/docs/security.md`; how to get a box; scope in
  and out from `SECURITY.md` verbatim.
- Gate 2: `docs/security-findings.md` exists with the row shape and no rows.
- Gate 4: the count is a number with the command, above and in the brief.

## Decisions taken (routine, mine)

- "Rows of the surface table" read as every table in the file, 158 rows, with
  the honest sub-counts: 74/76 REST is the one that means something for a
  roles suite; 74/107 counts the entries a roles suite *could* call; 74/158
  is the whole file. All three are stated so nobody picks the flattering one.
- The two `/rename-files` rows are counted as not exercised, not as covered:
  the suite asserts their absence, which is a different assertion from
  calling their gate.
- The two generated registers (db-calls, escaping) are listed with the three
  hand-written ones: the gate names three, the reviewer needs five.
- The box's address and the SSH line are **not** in the brief; it may go to
  an outsider, and `docs/testing.md` says the team's box is the team's. The
  brief says a review box is Nathan's to provide and that `deploy.mjs`'s
  `BOXES` map is where it is added.
- The known open items include the two decided ones (the cron loopbacks'
  `sslverify`; the per-address sign-in limit) marked as decided, so the
  reviewer sees the trade rather than re-finding it.
- Pro's absence from the surface table is put to Nathan as a question in the
  brief's closing list, not decided.

## Found, not done

- **Pro is not in the surface table.** `tools/security-surface.mjs` reads the
  free plugin's 66 files; Pro's three outbound sites are in the hosts
  register, its routes and handlers nowhere. Whether it is in the review's
  scope is Nathan's; if yes, the generator needs a second root, which is a
  Phase 5 task, not this one.
- `docs/runbooks/incident.md` exists in the service already (`ls
  ../service/docs/runbooks` → `incident.md`, `stripe-webhook.md`); 4.8 says
  "create" — the next Phase 4 session reads and appends.
- `escaping` 9/10 — Phase 3 S3's row, as before.
- The `.harness/active.json` card is this session's; the next session writes
  its own.

## Cost

Nothing reached a model. One full battery on the box, one local re-run; no
deploy; no credits.

## Next

Phase 5 is now waiting on Nathan twice: 5.9 on the send (and the reviewer,
the box and the key it needs), 5.10 on 4.2 and 1.7 — 1.7's rollback is
rehearsed (`docs/runbooks/rollback.md`); 4.2's monitor does not exist. The
plan's S4 (closing 5.9's findings) cannot start before findings arrive.

The one thing that unblocks Phase 5 from inside a session is **Phase 4 S2:
4.2 + 4.8** in `../service` — the external monitor on `/api/health` and the
incident runbook it feeds, which 5.10's rehearsal then walks. Its stop point
has a default the plan already proposes (Better Stack free tier, five-minute
check, email to Nathan); the session proceeds on the default unless Nathan
names another. The alternative is Phase 3 S3 (3.5 + 3.9), whose card is in
`docs/handoffs/2026-09-13-phase-3-s2-shell-paste-tree.md`.

Card for Phase 4 S2, to `../service/.harness/active.json` before the first
edit:

```json
{
  "phase": "Four yesses — Phase 4, S2: 4.2 something external watches it + 4.8 the incident runbook",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-4.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "docs/runbooks/monitor.md",
    "docs/runbooks/incident.md",
    "README.md",
    "../plugin/docs/recovery.md"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-5-s3-reviewer-brief.md",
    "../plugin/plans/four-yesses/phase-4.md",
    "app/api/health/route.ts",
    "docs/runbooks/incident.md",
    "docs/runbooks/rollback.md",
    "../plugin/docs/recovery.md"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "Which monitor and whose inbox: the plan's default is Better Stack free tier, a five-minute HTTP check on /api/health expecting 200, email to Nathan; the session proceeds on the default unless Nathan names another, and the account is his",
    "The customer sentences in incident.md are drafted and approved before they are written in",
    "Production is broken deliberately for the rehearsal only — PLUGIN_RELEASES set to 'not json' for at most ten minutes, restored, both emails received — and not left broken longer"
  ],
  "gates": [
    "the monitor requests /api/health every five minutes from outside Vercel and outside the box; the alert email and the recovery email screenshotted into the handoff with timestamps; the monitor's check history showing the window",
    "docs/runbooks/monitor.md: what is watched, from where, who is emailed, how to pause it during a deploy",
    "docs/runbooks/incident.md: a section per health check (database, releases, stripe, webhook secret, ai relay) — what to look at in order, what to change, how to confirm, what to tell customers; the 4.2 break walked by its steps and timed at the bottom; rollback.md by reference",
    "a node fetch of https://vergelabsmedia.com/api/health → 200 after the restore (curl is blocked by the hook; use a node script)",
    "pnpm test unchanged"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-5-s3-reviewer-brief.md, then
../plugin/plans/four-yesses/phase-4.md tasks 4.2 and 4.8. State which model
you are and follow that profile in ~/.claude/harness/model-profiles.md. This
session is Phase 4 S2. Write the card from the handoff to .harness/active.json
before the first edit. Stop points and gates are in the card. End with a
handoff in ../plugin/docs/handoffs/.
```

When Nathan says the brief went: one line in `docs/security-brief.md` (the
**Sent** line) and one in `docs/security-findings.md`, committed as
`docs(security): 5.9 brief sent <date>`. When findings arrive: rows in the
findings file, one task each in `plans/four-yesses/phase-5.md` under S4.
