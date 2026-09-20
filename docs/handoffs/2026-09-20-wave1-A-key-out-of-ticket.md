# Wave 1, story A — the licence key leaves the support ticket (story 3.2, FR10)

2026-09-20. Opus 5, one session, worktrees `wt/plugin-A` and `wt/service-A`, branch `story/3.2-key-out-of-ticket` in both repos. Nothing pushed, nothing deployed, nothing on the box, ms2 or a real site, nothing to Stripe, nothing spent. The only network was Playground's own boot.

## What landed

| Repo | Commit | What |
|---|---|---|
| plugin | `e9bd6b2` | `_bmad-output/implementation-artifacts/spec-3-2-the-licence-key-leaves-the-support-ticket.md` — the S25 shape, decisions taken, open questions, the copy proposal |
| plugin | `fcad5ea` | `core/get-help.php` — `$body['licence'] = substr( $key, -4 )` where `$body['key'] = $key` was; a free install and safe mode send neither. `readme.txt` External services, same commit. `tests/security/get-help.php` new. `tests/security/secrets.php` section E flipped (it asserted the key *was* the ticket's `key` field). `tools/verify.mjs`: `runPhpPlayground()` and the `get-help` entry (`env: 'local', php: 'playground'`) |
| service | `2bbd3f1` | `lib/store.ts` `findLicenceByPrefixOnSite(prefix, site)` — `licences join activations` on `key_prefix` and `site`, newest activation first. `app/api/support/ticket/route.ts` — the `licence` branch after the `key` branch; the key still wins; a prefix miss says so in `keyNote`. `lib/store.test.ts` (six rows) and `app/api/support/ticket/route.test.ts` (eight) |

Base commits: plugin `43003b4`, service `7c12bf1` (main already carried story C's commit when this branch was cut; the plan named `7dc49f1`).

## Proof, verbatim

Plugin, `node tools/verify.mjs get-help` in `wt/plugin-A` (Playground booted here on the mounted checkout, WordPress installed, `pre_http_request` catching the one request):

```
22/22 passed
════════ summary
  passed   get-help
```

Before the change the same suite: `16/22 passed` — red on "no key field", "a licence field, 4 characters", "it is the last four characters of the key", "the key occurs nowhere in the raw body", "apart from licence, the licensed body has the same fields as the free one", and "3 requests caught; the key is in none of them".

Service, `npx vitest run lib/store.test.ts app/api/support/ticket/route.test.ts` in `wt/service-A`:

```
 Test Files  2 passed (2)
      Tests  55 passed (55)
```

Before the change: `Test Files 2 failed (2) · Tests 8 failed | 47 passed (55)`.

`npx tsc --noEmit -p .` in `wt/service-A`: no output, exit 0.

## The mutation

`$body['key'] = $key;` put back beside the `licence` line in `core/get-help.php`, `node tools/verify.mjs get-help`:

```
  FAIL  no key field
  FAIL  the key occurs nowhere in the raw body  -- 1 occurrence(s)
  FAIL  apart from licence, the licensed body has the same fields as the free one  -- email,known_issues,question,report,site,site_token vs email,key,known_issues,question,report,site,site_token
  FAIL  3 requests caught; the key is in none of them, in no URL and no header  -- /v1/support/ticket
18/22 passed
  FAILED — 18/22, exit 1
```

Restored; `22/22 passed` again before the commit.

## Found, not done

- `node tools/verify.mjs surface` is red on this branch and on `43003b4` alike: `docs/security-surface.md` has drifted (`core/ai.php` line numbers, a `line` parameter on `/ai-status`). Not this story's file; the comment in `get-help.php` was kept to its original two lines so the story adds no row to that drift. One `node tools/security-surface.mjs` in 1c.
- `node tools/verify.mjs hosts` is red the same way: `core/filing.php:1762 vergeml_ai_service_url() . '/file'` has no row in `docs/security-hosts.md`. Pre-existing; 1c.
- Every `env: 'playground', php: true` entry in `verify.mjs` (`say`, `quarantine`, `utilities`) still ships over SSH to the box (`runPhp` ignores `env`); the comment at `verify.mjs:300` says so. They could move to `php: 'playground'` now that a booted local runner exists — a chore, not this story.
- `docs/outbound-audit-2026-09-19.md:46` still lists "the plaintext licence key" in the `/v1/support/ticket` row. It is the dated record of `990a8ff`; the readme is the disclosure that ships. Re-run the audit when it is next re-run.
- The Playground runner prints two `lockWholeFile: unlock failed` lines from the CLI on every run (SQLite drop-in on Windows); harmless, not ours.
- Service `tsconfig.tsbuildinfo` is tracked and `tsc --noEmit` rewrites it; restored with `git checkout --` before the commit.
- `tests/security/secrets.php` (box, `env: 'box'`) was flipped here but not run — there is no box in wave 1. 1c runs it after the deploy; the flipped rows are E's "it carries the key's last four characters as `licence`, not the key" and the unchanged "nothing in it carries the key".
- The service deploys before the plugin reaches 4.0.1 sites, and the route keeps the `key` branch, so the order of the train does not matter for this story; a 4.0.1 plugin against the *old* service would file every licensed ticket as a free install (the old route ignores `licence`) — no data leaks, only the licence id is missing on the row. Recorded so the train's order is a choice, not a surprise.

## Open questions (safe defaults taken, in the spec)

- Should the agent's customer facts show "the licence ending …XXXX" when the prefix resolves nothing? Default: no — the agent reads `licence_id` off the row as today; the miss lives in `keyNote` only.
- Is the outbound audit updated in this story or at its next re-run? Default: next re-run.

## Copy proposal (for Nathan; the file carries the plain version)

`readme.txt`, External services, the "Asking for help" paragraph — in the file now:

> …posts what you typed, the email address you gave, the last four characters of your licence key if you have one (never the key itself), and a full system report: …

Proposed:

> **Asking for help** works without a licence key, as connecting one and the known-problems list below do. Pressing Send on the Get help screen posts what you typed, the email address you gave, the last four characters of your licence key if you have one -- never the key itself -- and a full system report: …

The `keyNote` strings in the route are unchanged; one line was added for the prefix-miss case ("a licence prefix was sent but no licence ending …XXXX is activated on this site") — an internal mail line, not screen copy. The screen's consent sentence ("It does not contain your licence key…") is untouched and now true of the whole request.

## Next steps

1. 1c merges `story/3.2-key-out-of-ticket` in plugin and service; the full batteries will show `surface` and `hosts` red for the pre-existing reasons above.
2. After the service deploy and the box deploy, `node tools/verify.mjs secrets` on the box proves the flipped section E against MySQL.
3. Nathan's call on the readme wording (Copy above) goes into the train's copy block.
