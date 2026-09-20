---
title: 'The licence key leaves the support ticket'
type: 'fix'
created: '2026-09-20'
status: 'review'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: 'plugin 43003b4d892032dfe9f346cf6e67cee70ada8be5 · service 7c12bf149bbbda6c36c232ff06f997f67b24ce46'
context:
  - '{project-root}/plans/finish-the-suite.md'
  - '{project-root}/docs/outbound-audit-2026-09-19.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Pressing Send on the Get help screen posts the plaintext licence key to `/v1/support/ticket` (`core/get-help.php:217-225`), and the ticket is the one outbound call that works with no licence at all. The service only ever uses the key to find the licence's id (`route.ts:75-90`); the key then sits in every hop between — the request, the log line a proxy might keep, the support inbox. `docs/outbound-audit-2026-09-19.md` lists it as the largest undisclosed payload in the plugin, and the screen's own consent text already promises "It does not contain your licence key" about the report. FR10 of `plans/suite-readiness.md`.

**Approach:** the plugin sends what the account page already shows — the key's last four characters (`keyPrefix()` in `service/lib/licence.ts:260`, stored as `licences.key_prefix`) — as `licence`, beside the `site` it already sends. The service resolves the licence from that prefix and an activation on that site (a mirror of `whose.ts`'s prefix path, with the activation as the ownership check instead of a session), and stores the ticket without a licence when nothing matches, saying so in `keyNote` as today. A full `key` from a 3.16.x install keeps working — those installs exist in the field and the service is deployed before the plugin reaches them. The readme's External services sentence changes in the same commit (AGENTS.md policy); the sentence in the file is plain and factual, the proposed wording is under Copy for Nathan.

## Boundaries & Constraints

**Always:** a free install sends no `key` and no `licence`; a licensed install sends `licence` (4 characters) and `site`, never `key`; the mail to support names the licence id, never a key (already so); the service accepts `key` from older plugins unchanged; `readme.txt` External services updated in the plugin commit; the proof suite runs here, in Playground, never on the box.

**Never:** change what else the ticket carries (question, email, site token, known issues, report); touch `support/inbound` or the agent; send a hash of the key (a hash of a key is a key to us); a call to the Hetzner box, ms2, a real site, Stripe, or the live service.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected | Error |
|---|---|---|---|
| free install | no key in `vergeml_ai` | body has `site`, `site_token`, `question`; no `key`, `license_key` or `licence` | — |
| licensed install (4.0.1) | key `VGML-…XXXX` sealed in `vergeml_ai` | body has `licence: 'XXXX'` and `site`; the key appears nowhere in the body | — |
| safe mode | `core/ai.php` not loaded | as free install (the guard at `get-help.php:219` already does this) | — |
| service: prefix + activated site | `licence: 'XXXX'`, `site` normalises to an activation of a licence ending XXXX | ticket stored with that `licence_id`; `keyNote` names the id, plan, status | — |
| service: prefix + another site | same prefix, a site with no activation of that licence | ticket stored with `licence_id null`; `keyNote` says the prefix matched nothing on this site | — |
| service: two licences ending XXXX, one activated here | both exist | the activated one | — |
| service: full `key` (3.16.x plugin) | `key: 'VGML-…'` | as today: `findLicenceByKey`, the three existing `keyNote` strings | — |
| service: `licence` of the wrong shape | e.g. `'x'`, `''`, 12 chars | ignored: stored as a free install, `keyNote` "no licence key sent (free install)" | — |
| service: both `key` and `licence` | a plugin between versions | the key wins (it is the stronger proof); the prefix is not consulted | — |

</frozen-after-approval>

## Code Map

- `plugin/core/get-help.php:217-225` — the block that unseals the key into `$body['key']`. Becomes `$body['licence'] = substr( $key, -4 )`. The guard (`vergeml_ai_settings` and `vergeml_ai_unseal` present, key non-empty) stays, so safe mode and a free install send nothing.
- `plugin/core/get-help.php:191-204` — the rest of the body; unchanged.
- `plugin/readme.txt:183` — "Asking for help …your licence key if you have one…" → the last four characters of the key.
- `plugin/tests/security/secrets.php:508-513` — section E asserts the key *is* the ticket's `key` field. Flipped to assert the prefix and the absence of the key; section G (every request: the key is the one auth field of a JSON body) already tolerates a body with no key.
- `plugin/tests/security/get-help.php` — new: the free body and the licensed body, caught by `pre_http_request`.
- `plugin/tools/verify.mjs` — a `get-help` entry with `env: 'local', php: 'playground'`, and `runPhpPlayground()`: `run-blueprint` with the checkout mounted as the plugin, activated, then `runPHP` loading `wp-load.php` and the suite. The existing `php: 'wasm'` runner has no WordPress; the existing `env: 'playground', php: true` entries ship over SSH to the box regardless (`verify.mjs:300-303`).
- `service/lib/licence.ts:260` `keyPrefix()` = `key.slice(-4)`; `:274` `normaliseSite()` = `scheme://host`, what `activations.site` holds (`001_licensing.sql:61-63`).
- `service/lib/whose.ts:55,80-91` — the prefix path: `/^[A-Z0-9]{2,8}$/`, a query for the id, then `findLicenceById`. The mirror.
- `service/lib/store.ts:208-215` — `findLicenceByKey`, `findLicenceById`, `activate`; the new `findLicenceByPrefixOnSite(prefix, site)` goes beside them.
- `service/app/api/support/ticket/route.ts:75-90` — the `key` branch; the `licence` branch goes after it.
- `service/support/agent.ts`, `tools.ts`, `investigate.ts` — read `licence_id` off the row, never the request; untouched.
- `service/tests/counts.test.ts` — the route-test shape (`vi.mock('@/lib/stripe')`, `POST(new Request(…))`).
- `service/lib/store.test.ts:34-40` — the PGlite store shape for the lookup test.

## Tasks & Acceptance

**Execution:**
- [ ] T1 Spec (this file).
- [ ] T2 Plugin test first: `tests/security/get-help.php` (free body, licensed body, restore) and `secrets.php` section E flipped; `runPhpPlayground()` and the `get-help` entry in `verify.mjs`. Red on the current code.
- [ ] T3 Service test first: `lib/store.test.ts` — prefix + activated site → the licence; prefix + another site → null; two licences with one prefix → the activated one. `app/api/support/ticket/route.test.ts` — the four bodies of the matrix. Red on the current code.
- [ ] T4 The plugin change: `$body['licence']`, the readme sentence, same commit.
- [ ] T5 The service change: `findLicenceByPrefixOnSite()` in `store.ts`; the `licence` branch in `route.ts`.
- [ ] T6 Proof: `node tools/verify.mjs get-help` green; `npx vitest run lib/store.test.ts app/api/support/ticket/route.test.ts` green; `npx tsc --noEmit -p .` clean; mutation (the key back in the body) → `get-help` red, restored.
- [ ] T7 Commits on `story/3.2-key-out-of-ticket` in both repos; handoff `docs/handoffs/2026-09-20-wave1-A-key-out-of-ticket.md`.

**Acceptance Criteria:**
- Given a licensed 4.0.1 install, when Send is pressed on Get help, then the captured `/support/ticket` body has no `key` field, its `licence` field is 4 characters and equals the key's last four, and the key occurs nowhere in the body.
- Given a free install, when Send is pressed, then the body has neither `key` nor `licence`.
- Given the service, when a ticket carries `licence` and a `site` on which a licence ending in it is activated, then the ticket row carries that licence's id and `keyNote` names it; when no such activation exists the row's `licence_id` is null and `keyNote` says so.
- Given a 3.16.x install sending `key`, when the ticket arrives, then it resolves exactly as before this story.

## Decisions taken

- **Prefix = last four characters**, the `…93RJ` shape: `keyPrefix()` is `key.slice(-4)` and `licences.key_prefix` holds exactly that; the plugin computes `substr( $key, -4 )` with no new helper.
- **Field name `licence`**, the name `whose.ts` already reads a prefix under; the service accepts the same shape (`/^[A-Z0-9]{2,8}$/`).
- **The key branch stays first and wins** when both fields arrive; a prefix is only consulted when no key is sent. Nothing a 3.16.x install sends changes meaning.
- **Two licences with one prefix on one site** (36⁴ = 1.7 M prefixes; possible on an agency site that changed licence): the most recent activation wins (`order by a.activated_at desc`).
- **A new `keyNote` string for the prefix-miss case** ("a licence prefix was sent but no licence ending …XXXX is activated on this site") rather than reusing "a licence key was sent but matches nothing": the existing strings are kept verbatim; this is a line in the internal mail, not screen copy.
- **The suite runs in Playground with WordPress booted**, not through the `wasm` fixture runner (no WordPress there) and not through `runPhp` (SSH to the box). A third runner mode, `php: 'playground'`, boots `run-blueprint` on the mounted checkout. Registered `env: 'local'` so the reachability probe of `BASE` is skipped.
- **`secrets.php` section E is flipped** in this story (a test, in scope): it asserted the very thing FR10 removes. Its `env: 'box'` registration is untouched; 1c runs it after a deploy.
- **The readme sentence in the file is factual and minimal** ("the last four characters of your licence key if you have one"); the wording Nathan may prefer is under Copy.

## Open questions

- Whether `licence` should also reach the *agent's* customer facts as a display string ("the licence ending …XXXX") when no licence resolves. Safe default taken: no — the agent reads the row's `licence_id` as today, and the miss is only in `keyNote`.
- Whether the audit's row for `/v1/support/ticket` (`docs/outbound-audit-2026-09-19.md:46`) is updated in this story or when the audit is next re-run. Safe default: left as the 2026-09-19 record of `990a8ff`; the readme is the disclosure that ships.

## Copy

Proposal for `readme.txt` External services, the "Asking for help" paragraph (Nathan's to accept or edit; the file carries the plain version):

> **Asking for help** works without a licence key, as connecting one and the known-problems list below do. Pressing Send on the Get help screen posts what you typed, the email address you gave, the last four characters of your licence key if you have one -- never the key itself -- and a full system report: …

The `keyNote` strings stay. The consent sentence on the screen ("It does not contain your licence key…") stays; it was already true of the report and is now true of the whole request.

## Spec Change Log

- 2026-09-20 written from the mini-spec A in `plans/finish-the-suite.md`; no elicitation round (wave 1 runs unattended; the decisions above are the ones a round would have asked).

## Review Triage Log

## Verification

**Commands:**
- `node tools/verify.mjs get-help` in `wt/plugin-A` — expected: `N/N passed`, no key in the captured body, `licence` 4 characters
- `npx vitest run lib/store.test.ts app/api/support/ticket/route.test.ts` in `wt/service-A` — expected: all green
- `npx tsc --noEmit -p .` in `wt/service-A` — expected: no output
- mutation: `$body['key'] = $key;` back in `get-help.php` → `get-help` red on the "no key field" rows; restored
- Cost: nothing spent; no request leaves this machine except Playground's own boot.
