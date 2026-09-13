# The secrets, and where each one is allowed to be

Written by hand on 2026-09-13, Phase 5.7 of `plans/four-yesses.md`.
`tests/security/secrets.php` (box) is the proof: it plants a canary key of the
real shape through the settings route and reads it back from everywhere a key
could surface. What follows is what the code does today, stated so that the
next reader does not have to re-derive it — and so that the suite's rows, which
assert each line, have a sentence to point at.

## What counts as a secret here

| secret | what it is | shape | lives |
|---|---|---|---|
| the licence key | authenticates every `/v1` call the plugin makes to the service | `VGML-` + 34 of `0-9A-Z` minus I L O U (the service's `lib/licence.ts`) | sealed, in the `vergeml_ai` option (`license_key`); on a network also in `vergeml_ai_network` |
| the guide token | a bearer the service mints for one licence, one site and one library summary, so the browser can stream a conversation without holding the key | the service's; opaque | in the clear, in `vergeml_guide_session` / `vergeml_brief_session` (`token`), for its hour |
| the support token | ties a free install's tickets together on the other side without identifying anyone | 32 random characters | in the clear, in `vergeml_support_token`; random, per site, not a credential to anything |

There is no download token in the plugin. The download token is the service's
(`app/api/plugin/download/route.ts`) and is 5.8's to audit.

## The seal (`core/ai.php`, `vergeml_ai_seal()` / `vergeml_ai_unseal()`)

- **Cipher:** AES-256-GCM through `openssl_encrypt()`, a fresh 12-byte
  `random_bytes()` IV per seal, a 16-byte tag.
- **Stored form:** `v1:` + base64( iv · tag · ciphertext ). The ciphertext is
  as long as the key; the blob never contains the key's bytes.
- **The key that opens it:** `hash( 'sha256', wp_salt( 'auth' ), true )`.
  `wp_salt( 'auth' )` is `AUTH_KEY . AUTH_SALT` from `wp-config.php`. That is
  the whole point: the sealed option is in the database, the salt is not, so a
  copied database or a stray SQL export does not hand out a working licence.
  Re-derived inside the suite (section A) rather than read: the row goes red
  the day the derivation changes.
- **The premise, and its edge:** when `wp-config.php` lacks `AUTH_KEY` or
  `AUTH_SALT` (or has the `put your unique phrase here` placeholder), WordPress
  generates a salt and stores it in the options table as `auth_key` /
  `auth_salt` — beside the sealed key, in the same dump. On such a site the seal
  hides the key from a casual `SELECT` and from nothing else. The suite asserts
  both constants are defined and not the placeholder, and that no generated
  `auth_key` / `auth_salt` option exists; a site where that fails should fix
  its `wp-config.php`, not the plugin.
- **Wrong salt, wrong tag:** a blob sealed elsewhere reads as no key here, and
  a single flipped byte reads as no key (GCM refuses). The failure mode of a
  salt rotation is therefore "the licence reads as unset — enter it again", and
  the seal's own comment says so.
- **Two seals of one key differ** (the IV), so a known key's seal cannot be
  matched against the stored one either.

## Where the key travels, and where it does not

The key leaves the seal in one way — `vergeml_ai_unseal()` — and goes to one
kind of place: the top-level `license_key` (or `key`) field of a JSON body
posted to the service, over TLS, to a host `docs/security-hosts.md` decides.
The suite records every request the run makes (section G) and asserts the key
is that one field and nothing else — not in a URL, not in a header, not
anywhere else in the body. Callers today:

| file | sends the key as | to |
|---|---|---|
| core/ai.php | `key` | `/licence` (activate, check) |
| core/ai.php, core/brief.php | `license_key` | `/describe` |
| core/connect.php, core/licence-page.php | `key` | `/licence` |
| core/filing.php, core/folder-talk.php | `license_key` | `/folders` |
| core/get-help.php | `key` | `/support/ticket` — the ticket, so the customer is known on the other side |
| core/guide.php | `license_key` | `/guide/session` — the token mint, for the Folders guide and the brief |
| core/instrument.php | `license_key` | `/counts` — only when the site opted in |
| core/search-meaning.php | `license_key` | `/embed` |

Where it is **not**, each a row in the suite:

- **REST bodies.** Every readable route under `/vergeml/v1/` that needs no
  argument is called as an administrator (22 today) and the body read. The
  status route says `has_license: true` and nothing more about it;
  `/ai-settings` answers with the status. Four routes need an argument
  (`health-uses`, `search-meaning`, `search-try`, `similar`) and are not
  called; none of them reads the settings.
- **The database.** After the plant: no row in `options`, `postmeta`,
  `termmeta`, `usermeta`, `posts` or the plugin's `vergeml_ai_index` carries
  the key. `get_option( 'vergeml_ai' )` serialized has no trace of it; only
  `vergeml_ai_unseal()` over it does.
- **PHP's log.** A describe pass is driven with mock off against a stand-in
  service (`pre_http_request`), once answered 500 and once with a description,
  plus a licence check answered 403 then with a balance. The process's own
  error log (redirected for the run) and the bytes `debug.log` gained during
  the run carry no key. There is no `error_log()` call anywhere in `core/`;
  what PHP would log is a notice or a fatal, and `zend.exception_ignore_args`
  decides whether a fatal's trace carries arguments — the box's PHP 8.5 has it
  on, as production ini files do.
- **The support ticket.** The system report (`vergeml_system_report_text()`
  and `_data()`), the question and the known-issues match carry no key. The
  ticket body's own `key` field is the customer identifier and is the one
  allowed place.
- **The counts snapshot.** Nine keys of numbers and versions; the key is the
  `license_key` field beside it, not inside it.
- **Error messages.** `vergeml_ai_describe_result()` builds its `WP_Error`
  messages from the status code, never from the request; the index row's
  `error` column stores those messages.

Where it is, on purpose, in part: the dashboard header and the Licence screen
show the **last four characters** (`…XY7Q`), the same four the account page
shows. Nothing else renders any of it.

## The guide token

By design it reaches the browser: `POST /guide/token` and `POST /brief/token`
(manage_categories / manage_options) answer with it, and the browser streams to
the service with it. The suite mints one against the stand-in under a sentinel
value and asserts:

- it is in those two bodies and in no other route's body;
- it is cached in `vergeml_guide_session` in the clear, with an expiry inside
  the hour (`VERGEML_GUIDE_TOKEN_SLACK` decides when a new one is minted);
- it is not in the log, the system report, the ticket or the snapshot.

It is not sealed. A database dump therefore yields, at worst, an hour of one
site's turn budget on the service — bounded by the expiry and the daily cap
the service enforces per site. Sealing it the same way is a few lines if that
bound is ever judged too loose; noted, not done.

## The box's logs, read once by hand (2026-09-13)

Before this suite touched anything, each log on the box was grepped for the
real key shape (`VGML-[0-9A-Z]{20,}`, case-insensitive), for the plan's
lowercase shape, for sealed blobs (`v1:[base64]{40,}`), and for `license_key`,
`sk-or-`, `whsec_`, `Bearer`:

| log | lines | key-shaped | sealed blobs | licence mentions |
|---|---|---|---|---|
| `wp-content/debug.log` (23 MB) | 113,607 | 0 | 0 | 0 |
| `/var/log/nginx/error.log` | — | 0 | — | — |
| `/var/log/nginx/access.log` | — | 0 | — | — |
| `/var/log/php8.5-fpm.log` | — | 0 | — | — |

The debug log is PHP 8.5 deprecations from other plugins (91,449 from one
Brizy line); zero lines name `vergelabs-media-library`. Nothing was copied off
the box. `tools/box-errorlog-read.sh` remains the way to read it; a grep for the
key shape above belongs in front of any paste into a ticket.

## Mutation checks

Run on 2026-09-13 against the box copy of `core/ai.php`, restored (md5 equal)
and the real key confirmed to open afterwards. 78/78 before and after each.

1. The settings route stores the key as typed and `unseal()` passes a plain
   value through → **74/78**: "the option holds a v1: blob", "the blob is not
   the key", "the serialized row has no trace", and the table grep naming
   `options:1 (vergeml_ai)`.
2. The status body gains `license_key` → **76/78**: the `/ai-settings` answer
   and `GET ai-status`, "1 occurrence".
3. `error_log( 'vergeml describe with ' . $license )` in the describe path →
   **77/78**: "this process's error log carries no key (168 bytes)".
4. The seal derives its key from a constant instead of `wp_salt( 'auth' )` →
   **75/78**: the re-derivation row and both wrong-salt rows.

## Found on the way

- The plan's by-hand pattern `vgml-[a-z0-9-]{20,}` would not have matched a
  real key: keys are `VGML-` and upper-case. The suite's canary has the real
  shape and the log grep above used it.
- The marker `CANARY` alone matched an Elementor transient's release-channel
  word (`_transient_elementor_remote_info_api_data_4.2.4`) on the first run;
  the suite matches `VGML-CANARY`.
- `vergeml_stats_send()` and the ticket put the key beside the payload rather
  than inside it, as their comments say; nothing to change.
