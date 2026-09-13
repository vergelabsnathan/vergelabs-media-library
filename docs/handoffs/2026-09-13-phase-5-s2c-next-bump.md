# Handover — Phase 5, S2c: 5.8's audit gate — next 16.3.5, re-run, pasted (Opus 5)

2026-09-13. One session in `service`, two commits on `main`, one deploy. One
dependency moved: `next` 16.3.2 → 16.3.5. No route, suite or config changed.
Nothing reached a model, Stripe or the database; the only production contact
was `vercel ls` and one read of the deployments API.

## What shipped

- `package.json`: `"next": "16.3.5"`. `pnpm-lock.yaml`: `next`, `@next/*`,
  `sharp` 0.35.3 → 0.35.4, `@img/*` (libvips 1.3.2 → 1.3.3), and `next`'s
  own `caniuse-lite` and `baseline-browser-mapping` (deduped with
  `browserslist`). Nothing else moved; no fork pinned.
- `docs/security.md` in the service: "Dependencies" now opens **Green on
  2026-09-13 after `next@16.3.5`**, keeps the three closed advisories by id,
  pastes the new audit output and `pnpm list sharp --depth 2`, keeps the two
  `qs` moderates and the braintrust note as they were. The bundle section
  carries the post-bump byte count; "Standing gates" lists audit, build,
  typecheck, eslint and test on 16.3.5; the header names the bump commit.

## Evidence

- `pnpm audit --prod --audit-level=high` → **`2 vulnerabilities found` /
  `Severity: 2 moderate`, exit 0**. The two are `qs` via
  `.>braintrust>express>qs` (GHSA-x5fp-wj9c-mxmx, GHSA-4mjr-xmp4-gh2g), below
  the gate, listed in the register.
- `pnpm list sharp --depth 2` → `next@16.3.5 └── sharp@0.35.4`.
- `pnpm build` → **exit 0**. `.next/static`: 22 files, 828,024 bytes (was
  828,084). The grep from the register re-run: `sk_live`, `sk_test`,
  `whsec_`, `sk-or-v1-`, `sk-or-`, `pooler.supabase.com`, `aws-1-eu-west-1`,
  `postgres://`, `postgresql://`, `VGML-[0-9A-Z]{34}`, `DOWNLOAD_SECRET`,
  `STRIPE_WEBHOOK_SECRET`, `OPENROUTER_API_KEY`, `DATABASE_URL` → **0 each**;
  controls `vergelabsmedia.com` 14, `stripe` 10, `pk_live_|pk_test_` 0. Same
  counts as on 16.3.2.
- `pnpm test` → **Test Files 31 passed | 7 skipped (38); Tests 450 passed |
  14 skipped (464)**, exit 0. Same counts as S2b.
- `pnpm typecheck` → exit 0.
- Commits in `service`: `5b7cd7b` `fix(deps): next 16.3.5 — two criticals
  and sharp's high closed; audit green, pasted`; `d50015c` `docs(security):
  name the bump commit in the register`. Pushed `11ae097..d50015c`.
- Deploy, by identity: `vercel ls` showed
  `vergelabsmedia-d19e8b3f4-…vercel.app` at 14s **Building**, then at 51s
  **Ready** (39s build). The deployments API for
  `dpl_DLNKcUV911x1EgSPpb4nDYrF9faa`: `readyState READY`, `target
  production`, `githubCommitSha d50015c16e9…`, `githubCommitRef main`, aliases
  `vergelabsmedia.com`, `www.vergelabsmedia.com`, `ai.vergelabs.nl`,
  `vergelabsmedia.vercel.app`. The build that is serving is this commit.
  (`vercel inspect --json` does not carry the sha; the API read was a
  scratchpad node script using the CLI's own token, printing only the meta.)

## Decisions taken (routine, mine)

- Two commits, not one amended: the register names the bump commit, and the
  hash is not known until the bump is committed. The second commit is the
  one-line header edit.
- The lockfile's `caniuse-lite` / `baseline-browser-mapping` moves were
  judged inside "next's own subtree": both are `next@16.3.5`'s declared
  dependencies (`pnpm-lock.yaml` under `next@16.3.5(...)` → `dependencies`),
  and the stop point allows next's subtree. Stated in the register.
- The red audit block was not kept verbatim; the three advisory ids, the
  ranges' meaning and the fix are in one paragraph above the green paste,
  so the history reads without a 15-line block of closed rows. The S2b
  handoff still holds the verbatim red output.

## Found, not done

- `braintrust` in `dependencies` (the reason `express` and `qs` are in a
  production audit at all). Stop point: stays unless Nathan says move it.
- The sign-in limit's no-per-IP design (`app/api/auth/login/route.ts:55-59`)
  — for 5.9's reviewer, unchanged from S2b.
- `docs/mocks/` and `tsconfig.tsbuildinfo` were dirty in `service` before
  the session and are left as found; neither is in either commit.
- `pnpm add` warned `Ignored build scripts: unrs-resolver@1.12.2` — a
  pre-existing pnpm 10 allow-list notice, not new to this bump; build and
  tests are unaffected.

## Cost

Nothing reached a model. One `pnpm add`, one `next build`, one full `pnpm
test`, one Vercel deploy from the push. No credits.

## Next — Phase 5 S3 on Opus, in `plugin` (5.9's brief)

The plan's S3: 5.9's brief, sent. 5.10's rehearsal waits for 4.2 and 1.7.
Inherits from S2b/S2c: the service register at `../service/docs/security.md`
and the two "found, not done" items above for the reviewer's pack. The first
thing the brief needs (plan, State): count whether `tests/security/roles.php`
covers every row of `docs/security-surface.md`. Written to
`plugin/.harness/active.json` before the first edit.

```json
{
  "phase": "Four yesses — Phase 5, S3: 5.9's brief — the reviewer's pack, written and sent",
  "model": "opus",
  "plan": "plans/four-yesses/phase-5.md",
  "spec": "plans/four-yesses.md",
  "scope": [
    "docs/security-brief.md",
    "docs/security-findings.md"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-13-phase-5-s2c-next-bump.md",
    "plans/four-yesses/phase-5.md",
    "docs/security-surface.md",
    "tests/security/roles.php",
    "SECURITY.md",
    "../service/docs/security.md"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Who reviews and what it costs (a WordPress-specialist review or a scoped bounty) is Nathan's; the brief is the session's, the money and the choice are his",
    "Do not close a finding without the reviewer's word; do not pick the reviewer",
    "The brief is sent by Nathan; the session writes it and records the date only once he says it went"
  ],
  "gates": [
    "docs/security-brief.md exists and lists: docs/security-surface.md; the roles suite's coverage count (rows of the surface table covered by tests/security/roles.php, counted, out of the total); the three hand-written registers (docs/security-hosts.md, docs/security-secrets.md, and the Files section of docs/security-surface.md from 5.5); ../service/docs/security.md; how to get a box; what is in and out of scope, from SECURITY.md",
    "docs/security-findings.md exists with the row shape (finding, status, task, commit) and no rows yet",
    "node tools/verify.mjs surface db-calls escaping globals roles paths hosts secrets — green, unchanged by this session",
    "the brief's coverage count is a number with the command that produced it, not a reading"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-13-phase-5-s2c-next-bump.md, then
plans/four-yesses/phase-5.md task 5.9. State which model you are and follow
that profile in ~/.claude/harness/model-profiles.md. This session is Phase 5
S3. Write the card from the handoff to .harness/active.json before the first
edit. Stop points and gates are in the card. End with a handoff in
docs/handoffs/.
```
