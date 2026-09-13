# Handover — Phase 5, S2a: 5.7 secrets at rest, in logs, in responses (Opus 5)

2026-09-13. One session in `plugin`, one commit on `main`. No file in `core/`
changed: the seal and every caller were read, asserted and left alone. One new
suite, one hand-written register, one tool hardened, the box's logs read once
by hand. Nothing reached a model or the service; nothing left the box.

## What shipped

- `tests/security/secrets.php` (box, **78 checks**). Plants `VGML-CANARY` +
  23 random characters — the real key shape — through `POST /ai-settings` as
  an administrator, then reads it back from: the seal itself (A), the options
  table plus postmeta, termmeta, usermeta, posts and `vergeml_ai_index` (B),
  22 GET routes as administrator and the two token routes (C), a describe pass
  with mock off against a stand-in `pre_http_request` — 500 then a
  description; the licence check 403 then a balance — plus the process's own
  redirected error log and the bytes `debug.log` gained (D), the support ticket
  driven through `vergeml_help_send()` to its redirect with the system report
  inside it (E), the counts snapshot and `vergeml_stats_send()` (F), every
  caught request — the key is the one top-level `license_key`/`key` field of a
  JSON body, never a URL or header, bound only for the service, the stream host
  or the site's own `wp-cron.php` (G), and everything put back verbatim from a
  shutdown function, re-checked (H). The canary is never printed; a red row
  names a place and a count.
- The seal's key derivation is **re-derived inside the suite**, not read:
  `hash( 'sha256', wp_salt( 'auth' ), true )` opens the blob; another
  install's salt (the core `salt` filter) does not; one flipped byte does not.
  The premise is asserted too: `AUTH_KEY`/`AUTH_SALT` defined and not the
  placeholder, no generated `auth_key`/`auth_salt` option in the database.
- `docs/security-secrets.md`: the three secrets and where each lives; the seal
  stated (AES-256-GCM, 12-byte IV, 16-byte tag, `v1:` + base64, key =
  sha256 over `AUTH_KEY . AUTH_SALT`, and the edge where WordPress stores a
  generated salt in the database); the eight callers that send the key and the
  one field they send it in; where it is not; the guide token's design; the
  by-hand log read; the mutation checks.
- `tools/box-errorlog-read.sh`: counts key-shaped strings (`VGML-[0-9A-Z]{20,}`)
  per log before printing anything, and redacts any in the lines it prints —
  its output is what goes into a ticket. Run once on the box: 0, 0, 0.
- `tools/verify.mjs`: `secrets` registered (box).

## Evidence

- `node tools/verify.mjs secrets` → **78/78 passed**, `passed   secrets`;
  10 requests caught, all to the service / stream host / wp-cron.
- Mutations on the box copy of `core/ai.php`, each restored (md5
  `357e9e2a…` equal, real key opens, mock still 1):
  1. stored unsealed + `unseal()` passthrough → **74/78**, table grep naming
     `options:1 (vergeml_ai)`;
  2. `license_key` added to the status body → **76/78**, `GET ai-status … 1
     occurrence(s)`;
  3. `error_log()` of the key in the describe path → **77/78**, "this
     process's error log … (168 bytes)";
  4. seal key from a constant instead of `wp_salt( 'auth' )` → **75/78**, the
     re-derivation row and both wrong-salt rows.
- By hand, before the suite ran: `debug.log` (23 MB, 113,607 lines),
  nginx error and access logs, php-fpm log — **0** key-shaped strings
  (`VGML-[0-9A-Z]{20,}`, case-insensitive), 0 sealed blobs, 0 `license_key`
  mentions, 0 lines naming our plugin. The log is PHP 8.5 deprecations from
  Brizy, Elementor and Packlink. Nothing copied off the box.
- After everything: `debug.log` 0 `VGML-CANARY`/`TOKENSENTINEL`, options table
  0, no `/tmp/vgml-secrets-*` left.
- Standing gates: surface **26/26**, db-calls **11/11**, hosts **13/13**,
  globals **7/7**, roles **19/19**, paths **66/66**, escaping **9/10** (the
  known `js/vergeml-folders.js:667` row, Phase 3 S3's).

## Gate wording that did not survive contact

- **"never in … a support bundle, or an instrument payload."** Both *send* the
  key: the ticket's `key` field identifies the customer, the counts' `license_key`
  authenticates the post — the same one field every `/v1` call uses, to the
  host 5.6 decided. Asserted as: the system report, question, known issues and
  snapshot carry no key; the key is that one top-level field and nothing else;
  in the whole run the key appears in no URL and no header. Written that way
  in the register.
- **"the download token"** does not exist in the plugin. It is the service's
  (`app/api/plugin/download/route.ts`), 5.8's. Stated in the register.
- **"the guide token never appears in … any REST response body."** It is
  handed to the browser by `POST /guide/token` and `POST /brief/token` by
  design — that is what it is for. Asserted as: in those two bodies and no
  other route's; not in the log, the report, the ticket or the snapshot; cached
  in `vergeml_guide_session` in the clear with an expiry inside the hour.
- **The by-hand pattern `vgml-[a-z0-9-]{20,}`** would not have matched a real
  key: keys are `VGML-` + 34 upper-case Crockford characters
  (service `lib/licence.ts`). The read used the real shape; the tool uses it.

## Decisions taken (routine, mine)

- Every readable route without a required argument is called, rather than a
  hand-picked list: a list is what a new route would be missing from. The four
  that need an argument are named in the output and none reads the settings.
- The describe pass is `vergeml_ai_describe()` twice, not `vergeml_ai_index_step()`:
  it builds and sends the real request (the part that touches the key) without
  writing index rows to the box's library.
- The first `/licence` answer of the run is a 403 wherever it comes from, so
  both branches of `vergeml_ai_refresh_credits()` run; the suite forces two
  checks and reads the second, so a fresh cache on a rerun cannot make it red.
- The marker moved from `CANARY` to `VGML-CANARY` after an Elementor transient
  matched the bare word on the first run.
- The token is minted *before* the GET loop, so the loop is what would catch it
  surfacing in another body.
- `tools/box-errorlog-read.sh` gained the count and the redaction rather than
  a separate script: the plan named the file, and its output is the paste.

## Found, not done

- **The guide token is stored unsealed** in `vergeml_guide_session` /
  `vergeml_brief_session` for its hour. A database dump yields at most that
  hour of one site's turn budget, bounded by the service's daily cap per site.
  Sealing it the same way is a few lines; for 5.9's reviewer to weigh, in the
  register.
- **`wp_salt()` on a site without `AUTH_KEY`/`AUTH_SALT`** stores a generated
  salt in the options table, beside the sealed key. The suite asserts the box
  is not such a site; the plugin cannot fix a site's `wp-config.php`. The
  Licence screen could say so when it detects it (one `defined()` check, copy
  needed) — for the brief, not built.
- The `escaping` row at `js/vergeml-folders.js:667` — Phase 3 S3's, as before.
- `.harness/active.json` carries this session's card; the next session writes
  its own.

## Cost

Nothing reached a model or the service: `pre_http_request` answered all ten
requests inside PHP. Six suite runs on the box (one first run, one green, four
mutations) and one gate battery; no credits, no deploy (no `core/` change).

## Next — Phase 5 S2b on Opus, in `../service` (5.8)

The card from the S1 handoff stands unchanged; it is repeated here so the next
session opens from this file. Written to `../service/.harness/active.json`
before the first edit.

```json
{
  "phase": "Four yesses — Phase 5, S2b: 5.8 the service, audited separately",
  "model": "opus",
  "plan": "../plugin/plans/four-yesses/phase-5.md",
  "spec": "../plugin/plans/four-yesses.md",
  "scope": [
    "docs/**",
    "lib/**",
    "app/api/**",
    "next.config.*"
  ],
  "readFirst": [
    "../plugin/docs/handoffs/2026-09-13-phase-5-s2a-secrets.md",
    "../plugin/plans/four-yesses/phase-5.md",
    "app/api/stripe/webhook/route.ts",
    "app/api/plugin/download/route.ts",
    "lib/auth.ts"
  ],
  "handoffDir": "../plugin/docs/handoffs",
  "stopPoints": [
    "The login drive runs against a local next dev or a preview, never production (4.2's monitor would page)",
    "No dependency fixed by pinning a fork",
    "Nothing in docs/security.md is claimed without the command and its output pasted"
  ],
  "gates": [
    "pnpm audit --prod --audit-level=high → no high or critical",
    "webhook: a body with a wrong stripe-signature → 400, cited",
    "download: token signed and licence re-checked at redemption, lines cited",
    "login: 30 wrong passwords in a minute, what the 31st gets, per IP or per account, stated",
    "next build then grep .next/static for sk_live, whsec_, the OpenRouter prefix and the pooler host → empty",
    "docs/security.md in the service, one section per item with command and output"
  ]
}
```

Opener, cwd `service`:

```
Read ../plugin/docs/handoffs/2026-09-13-phase-5-s2a-secrets.md, then
../plugin/plans/four-yesses/phase-5.md task 5.8. State which model you are and
follow that profile in ~/.claude/harness/model-profiles.md. This session is
Phase 5 S2b. Write the card from the handoff to .harness/active.json before the
first edit. Stop points and gates are in the card. End with a handoff in
../plugin/docs/handoffs/.
```

Two things 5.8 inherits from here: the download token is its to audit (the
plugin has none), and the key shape for any bundle grep is `VGML-[0-9A-Z]{34}`
beside `sk_live`, `whsec_`, the OpenRouter prefix and the pooler host.
