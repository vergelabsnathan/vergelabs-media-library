# Handover — the four-yesses plan, prepared for execution (Opus 5)

Same session as task 1.2, after it, at Nathan's instruction ("prepare —
overprepare is overperform"). No code changed. Nothing ran on the box.

## What exists now

- `tickets/2026-09-11-four-yesses.md` — why the plan was not executable as
  written, what the preparation is, what it refuses to do.
- `plans/four-yesses/phase-1.md` … `phase-5.md` — every remaining task as a
  six-field mini-spec (Files · Behaviour · Proof · Mirror · Copy · Do not),
  a model per task, sessions sized, stop points and gates as commands at the
  top of each file. Every path named was checked to exist on 2026-09-11.
- `plans/four-yesses.md` — a "How to run this plan" section pointing at the
  files, the state corrected, and every stop point in one table with the
  task it blocks.

## What the survey corrected

- Built on 2026-09-10 and now "prove" tasks: `/api/health` (five checks),
  `/api/cron/release-check`, per-licence rate limits (`lib/limits.ts`:
  240/min, 25,000/day).
- `SECURITY.md` exists with times and scope; 5.10's rehearsal has not run.
- `spendCredits()` locks the licence row before reading the balance, so 2.5
  is a concurrency proof, not a fix.
- The Folders screen exists (f3497e9). The mock awaiting approval is
  ways-in (2026-09-10). The memory `guided-sorting` says "nothing built yet"
  — stale.
- The tree component already has `role=tree`, `treeitem`, `aria-expanded`,
  a keydown handler; 3.10 closes gaps rather than starting from divs.
- No Dutch or Arabic translation exists (`.pot` only). Blocks 1.4's
  language axis and 3.11 until Nathan decides.
- Kamatera is retired (2026-08-29); the box is the real MySQL for 1.5.
- `tests/compat/matrix.sh` is wp-env/Docker; 1.4 is written for Playground
  and the box's `/var/www/ms`.
- 5.1–5.4 done (surface table, roles suite with 16 checks, 202 calls
  classified, escaping register).

## How the next session opens

Pick a phase file. The opener is the profile's, with the phase file in place
of the plan:

> Read `docs/handoffs/2026-09-11-four-yesses-prepared.md`, then
> `plans/four-yesses/phase-N.md`. State which model you are and follow that
> profile in `~/.claude/harness/model-profiles.md`. This session is Phase N,
> session S<k> as the file sizes it: tasks A and B. Stop points and gates are
> at the top of the file. End with a handoff in `docs/handoffs/`.

Cheapest sessions that need nothing from Nathan first: Phase 1 S1 (1.3 +
1.6), Phase 4 S1 (4.1 + 4.3, prove), Phase 5 S1 (5.5 + 5.6). Everything in
Phase 2 and Phase 3 waits on a stop point.

## Found, not done

- Two other PHP suites in `tools/verify.mjs` may carry the stale
  `env: 'playground'` label auto-file had (every PHP suite runs on the box).
- The `guided-sorting` memory should be corrected to "Folders screen built
  2026-09-05; ways-in mock pending".
