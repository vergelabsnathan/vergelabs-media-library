# Handover — Phase 5, S2b: 5.8 the service, audited separately (Opus 5)

2026-09-13. One session in `service`, one commit on `main`. No route changed:
the webhook, the download route and the login route were read, driven, mutated
and restored (`git diff --stat` empty each time). Three suites, one register.
Nothing reached a model, Stripe or the database; the drive ran in memory.

## What shipped

- `docs/security.md` in the service — one section per item, each with the
  command and what it printed: the audit, the webhook, the download token,
  the sign-in limit, sessions at rest, the client bundle; then the standing
  gates and "found on the way". Mirrors the plugin's `docs/security-*.md`.
- `lib/webhook-signature.test.ts` (5) — wrong signature → 400, signature over
  different bytes → 400, no header → 400, the same body signed with the real
  secret → 200 and the handler entered once, missing secret → 500. Only
  `store()` is mocked; `constructEvent` is Stripe's own.
- `lib/download-redemption.test.ts` (5) — the route driven against the real
  migrations in PGlite: a good token → 302 to the release; the **same
  still-valid token → 403 `not_entitled` once the licence is cancelled**;
  unknown licence → 403; forged and expired → the same 403 `invalid_token`;
  stale version → 409.
- `lib/login-drive.test.ts` (3) — 30 wrong passwords, then the 31st, then the
  right one; per-address not per-IP proven both ways; a right password before
  the limit signs in and clears the count.

## Evidence

- `pnpm vitest run lib/webhook-signature.test.ts` → **5 passed**. Mutation
  (`constructEvent` → `JSON.parse`) → **2 failed | 3 passed**, `expected 200
  to be 400`.
- `pnpm vitest run lib/download-redemption.test.ts lib/updates.test.ts` →
  **26 passed**. Mutation (entitlement branch unreachable) → **1 failed | 4
  passed**, `expected 302 to be 403`.
- `pnpm vitest run lib/login-drive.test.ts` → **3 passed**; the line:
  `30 wrong passwords in 708 ms: 8 × 401 then 22 × 429; the 31st: 429; the
  right password after: 429; login_attempts rows for the address: 8`.
  Mutation (threshold 1000) → **2 failed | 1 passed**.
- `pnpm build` → exit 0; `.next/static` is 22 files, 828,084 bytes; `sk_live`,
  `sk_test`, `whsec_`, `sk-or-v1-`, `sk-or-`, `pooler.supabase.com`,
  `aws-1-eu-west-1`, `postgres://`, `postgresql://`, `VGML-[0-9A-Z]{34}`,
  `DOWNLOAD_SECRET`, `STRIPE_WEBHOOK_SECRET`, `OPENROUTER_API_KEY`,
  `DATABASE_URL` → **0 each**; controls `vergelabsmedia.com` 14, `stripe` 10.
- `pnpm typecheck` exit 0; eslint on the three suites exit 0; `pnpm test` →
  **31 files passed | 7 skipped, 450 tests passed | 14 skipped**.
- Sessions at rest: `lib/auth.ts:72-74` `hashToken()` SHA-256;
  `lib/accounts.ts:121` and every lookup (`:154 :170 :194 :210 :220 :230`)
  through it; verify/reset tokens the same (`:86 :99`). Cited in the register.

## The one red gate

`pnpm audit --prod --audit-level=high` → **2 critical, 1 high**, all through
`next@16.3.2`: GHSA-p293-qw3h-jr36 (Windows RCE), GHSA-2xp9-vwfh-vxw4 (AVIF
image-optimisation RCE), `sharp <0.35.4` (libheif). One bump closes all three:
`next@16.3.5` declares `sharp ^0.35.4` (`16.3.3` still `^0.35.3`).

Not done here: `package.json` and `pnpm-lock.yaml` are outside the card's scope
(the S1 card listed `docs/**`, `lib/**`, `app/api/**`, `next.config.*`; the
plan's Files list has both), and the card froze on the first edit. Running the
bump through Bash would have sidestepped the fence, so it is the next card's
first task — or five minutes by hand:

```
cd service && pnpm add next@16.3.5 && pnpm build && pnpm test && pnpm audit --prod --audit-level=high
```

then paste the audit's output under "Dependencies" in `docs/security.md`, and
`vercel ls` after the deploy to confirm the new build is the one serving.

Two moderates remain below the gate: `qs` via `braintrust > express`.
`braintrust` sits in `dependencies` and is the eval runner; moving it to
`devDependencies` takes `express` and `qs` out of `--prod`. Whether anything
at runtime imports it is a question, not a finding.

## Gate wording that did not survive contact

- **"drive `auth/login` … against a local next dev or a preview."** Local
  `next dev` reads production's `DATABASE_URL` (memory: Supabase pooler), so
  the 30 failures would have been 30 rows in the production `login_attempts`
  table, and the hook stops the agent cleaning them. The drive is the route
  called in-process against the real migrations in PGlite — the same shape
  `account-download.test.ts` uses. Stricter than the stop point; stated in the
  register. If Nathan wants the HTTP shape as well, a preview URL with a
  throwaway address answers it in one minute and the register has the
  expected sequence to compare against.
- **"what happens on the 31st"** — the 9th already gets 429; `MAX_ATTEMPTS`
  is 8 in 15 minutes. The 31st is 429, and so is the right password after it.

## Decisions taken (routine, mine)

- Three suites rather than citations alone: the plan's Proof is the register,
  but a cited line is a reading and a mutation-checked assertion is a proof.
  Each suite mirrors an existing one (`health.test.ts` for the mocked route,
  `account-download.test.ts` for PGlite).
- The webhook suite's fourth row (a good signature reaches the handler) is
  there so the 400 rows cannot pass on a route that answers 400 to everything.
- The bundle grep names the shapes, not the values: the values are in the
  environment and the hook blocks reading `.env*`. A shape grep with a control
  grep is the honest version.

## Found, not done

- `next` 16.3.2 → 16.3.5, above.
- `braintrust` in `dependencies`, above.
- No per-IP component to the sign-in limit, by design (`route.ts:55-59`). A
  wide attacker can lock a victim's address for 15 minutes with 8 requests;
  the reset path stays open. For 5.9's reviewer to weigh, in the register.
- `docs/mocks/` and `tsconfig.tsbuildinfo` were already dirty in `service`
  before the session and are left as found; neither is in the commit.

## Cost

Nothing reached a model or the service. One `next build`, one full `pnpm
test`, three mutation runs. No credits, no deploy.

## Next — Phase 5 S2c on Opus, in `service` (5.8's red gate)

A short one: the bump, the re-run, the paste. Written to
`service/.harness/active.json` before the first edit.

```json
{
  "phase": "Four yesses — Phase 5, S2c: 5.8's audit gate — next 16.3.5, re-run, pasted",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-5.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "package.json",
    "pnpm-lock.yaml",
    "docs/security.md"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-5-s2b-service.md",
    "../plugin/plans/four-yesses/phase-5.md",
    "docs/security.md"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "next goes to 16.3.5 and nothing else moves; no dependency fixed by pinning a fork",
    "braintrust stays in dependencies unless Nathan says move it",
    "The deploy is confirmed by build identity (vercel ls age or a marker), never by a status code"
  ],
  "gates": [
    "pnpm audit --prod --audit-level=high → exit 0, output pasted under Dependencies in docs/security.md",
    "pnpm build → exit 0; the .next/static grep from docs/security.md re-run → 0 each",
    "pnpm test → green, the count pasted",
    "after the push: vercel ls shows the new deployment serving"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-5-s2b-service.md, then
../plugin/plans/four-yesses/phase-5.md task 5.8. State which model you are and
follow that profile in ~/.claude/harness/model-profiles.md. This session is
Phase 5 S2c. Write the card from the handoff to .harness/active.json before the
first edit. Stop points and gates are in the card. End with a handoff in
../plugin/docs/handoffs/.
```

After S2c, Phase 5 S3 (5.9's brief) opens in `plugin`, per the plan's session
list; it inherits from here the service register's path (`service/docs/security.md`)
and the two "found, not done" items for the reviewer's pack.
