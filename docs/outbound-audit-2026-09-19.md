# Every outbound request this plugin can make

Audited 2026-09-19, against `990a8ff`. Written because the readme's External
services section was found to be describing one feature of several, and the
only way to know what a disclosure should say is to enumerate the calls.

Method: `wp_remote_*`, `file_get_contents` on a URL, `curl_`, `fsockopen`,
remote scripts, stylesheets, fonts, images and iframes, and every absolute
`http(s)://` in shipped `.php`, `.js` and `.css`. `tools/` and `tests/` are
`export-ignore` and not in the archive; their calls are out of scope.

**Twenty call sites in shipped code.** Eighteen external, two loopback to the
site's own `wp-cron.php`. One of the eighteen is made **from the admin's
browser**, not from PHP. No remote scripts, stylesheets, fonts, images or
iframes anywhere — the fonts in `fonts/` are local.

Three of the findings below were verified by hand after the audit, being the
ones a mistake would most embarrass us: the support ticket's payload
(`core/get-help.php:190-231`, `core/system-report.php:120-140`), the
browser-side stream call (`js/vergeml-talk.js:396-404`), and the known-issues
cache durations (`core/get-help.php:59-71`).

## Where requests can go

| Destination | Set by | Changeable |
|---|---|---|
| `https://ai.vergelabs.nl/v1` | `vergeml_ai_service_url()` | `VERGEML_AI_SERVICE`, only `https://` or localhost; no filter, deliberately |
| `https://vergelabsmedia.com` | `vergeml_connect_base()` | `VERGEML_SITE_URL`; no filter |
| the stream host | `vergeml_guide_stream_url()` | `VERGEML_AI_STREAM`; no filter |
| `raw.githubusercontent.com/…/known-issues.json` | `VERGEML_KNOWN_ISSUES_URL` | wp-config constant |
| the site's own `wp-cron.php` | `site_url()` | — (loopback) |

## The calls

| Destination | What is sent | Trigger | Needs a licence? |
|---|---|---|---|
| `/v1/describe` | licence key, site URL, environment, filename, MIME, **image bytes** (downsized JPEG), the site profile / brief text, the attachment's title and caption, the parent post's title, up to six WooCommerce category names; and with Page context on (**default on**) the page title, focus keyphrase, meta description and up to three related keyphrases | a describe run: REST `/ai-index`, or the `vergeml_ai_run_tick` cron. Not on upload, not on page load | yes |
| `/v1/licence` | licence key (or the old key), site URL, `activate` / `check` / `deactivate`, environment | saving, removing or connecting a key — **and the credits check on admin page loads**: every VergeLabs screen, the dashboard, the Licence screen, and once per subsite on the network screen. Cached five minutes | yes |
| `/v1/folders` | licence key, site URL, the typed instruction, the conversation history, **every folder's name, parent and count**, forty captions, the top eighty object phrases with counts, cluster sizes with captions, an audience ratio | the propose button (`/folders-propose`); Confirm a plan (`/guide/confirm`), one call per sixty folders | yes |
| `/v1/file` | licence key, site URL, **the whole folder tree as paths**, and up to forty × { attachment id, object phrases, caption } | a Move / refile pass on the `VERGEML_TALK_HOOK` cron. Never on a dry count | yes |
| `/v1/name-group` | licence key, site URL, object phrases, captions | naming an unnamed photo group during a refile pass | yes |
| `/v1/embed` | licence key, site URL, **search phrases a user typed**, folder paths and their *matches* free text, object phrases — up to five hundred a call | a meaning search (`?vgml_meaning=1`, `/search-meaning` at `upload_files`, `/search-try`); any filing, fit or dry-count pass | yes |
| `/v1/guide/session` | licence key, site URL, and the library summary: **every folder's id, name, parent and count**, the top twenty-four classes, captions, kind counts, evidence ratios, **every WooCommerce category path** — or the brief catalogue with the **top twenty-five tags and counts** | minting a token for the Folders screen or the brief tab | yes |
| `/v1/guide/stream`, `/v1/brief/stream` — **from the admin's browser** | the bearer token, the conversation, **the draft tree** (folder names, the *matches* text, classes, audience) or the draft brief, **what the admin typed**, the library summary — and, because it is the browser, **the admin's IP address and browser headers** | every conversational turn | yes |
| `/v1/counts` | licence key, site URL, counts: attachments, per-family MIME counts, folders, depth, added in thirty days, plugin / WP / PHP versions, locale | `admin_init`, once a day, **opt-in, off by default** | yes |
| `/v1/support/ticket` | site URL, a persistent per-site token, **the typed question**, **an email address**, the matched known issues, **the plaintext licence key** (until 4.0.0; from 4.0.1 — story 3.2, `fcad5ea` — the key's last four characters only, matched to an activation on the site), and **the whole system report** — home URL, WP / PHP / MySQL versions, server limits, debug flags, theme, attachment count, every attachment taxonomy with its term count, fifteen plugin settings, and **the name and version of every active plugin** | the Send button on Get help: `manage_options`, nonce, a question, and a consent tick | **no** |
| `vergelabsmedia.com/api/connect/exchange` | the one-time code, the site URL | returning from the Connect handshake | **no** |
| `vergelabsmedia.com/connect` — **browser redirect** | `state`, the site URL, the admin URL, and the admin's IP and headers | pressing Connect | **no** |
| `raw.githubusercontent.com/…/known-issues.json` | nothing in the request; **the site address travels in WordPress's default User-Agent** | opening Get help until 4.0.7; **from 4.0.7 only the Check for known problems button** (no screen fetches it by opening; the price quote the AI screen fetched in 4.0.5–4.0.6 is gone too). Cached **12 hours**; a failure is not cached and the screen says so | **no** |
| the site's own `wp-cron.php` (×2) | a `doing_wp_cron` key | chaining a describe or refile pass from inside a tick | no |

## What can leave the site

Image bytes · file names · attachment titles · attachment captions · parent
post titles · SEO focus keyphrases and related keyphrases · SEO meta
descriptions · WooCommerce category names · **folder names, their parents and
their counts** · **the folder *matches* text the admin wrote** · AI-written
captions · AI-written tags with frequencies · object phrases with counts ·
attachment ids · **search queries a user typed** · the site profile and brief
text · **free-text instructions and the whole conversation history** · a
support question · an email address · the plaintext licence key · site and
admin URLs · **the admin's IP and browser headers** · **every active plugin
with versions**, the theme and fifteen plugin settings · server and WordPress
environment facts · library counts · a persistent per-site token.

## What readme.txt does not say, and where it is wrong

### Not disclosed at all

1. **`vergelabsmedia.com` as a destination.** Only `ai.vergelabs.nl/v1` and
   `raw.githubusercontent.com` are named.
2. **The `/licence` endpoint** — activate, check, deactivate. The key, the site
   URL and the environment type leave on saving a key and **on routine admin
   page loads**.
3. **The support ticket.** The largest undisclosed payload in the plugin, and
   it works **with no licence key**, which undercuts the readme's whole framing.
4. **The Folders conversation surface** — `/folders`, `/file`, `/name-group`,
   `/guide/session`, `/embed`: folder names, typed instructions, conversation
   history, AI captions, tag frequencies, and every WooCommerce category path.
5. **Requests from the admin's browser.** The only calls that expose the
   admin's IP to the service. Nothing in the readme suggests the browser talks
   to it at all.
6. **Search queries can leave the site** — and the route sits at `upload_files`,
   not `manage_options`, so any uploader can cause one.
7. **Extra fields in the describe body** beyond image, filename, MIME, site and
   key: the environment, the attachment's own title and caption, the parent
   post's title, up to six WooCommerce category names — the last two **not**
   gated by the Page-context switch the FAQ implies is the gate — up to three
   related keyphrases, and the site profile text.
8. The per-family MIME breakdown in the opt-in counts payload.
9. The four wp-config constants that can point any of this at another host.

### Contradicted by the code

10. **"With no key … no request on page load."** True with no key. With a key,
    the credits check fires on every VergeLabs screen, the dashboard and the
    Licence screen — and once per subsite on the network screen. Get help
    fetches from GitHub on page load with or without a key.
11. **"only the images you asked to have described"** (FAQ) — contradicted by
    items 3 to 7.
12. **"The folder tree … make no outbound requests at all"** — the folder tree
    itself is fine, but the Folders screen's planner, Move and conversation are
    the busiest external callers in the plugin. The sentence needs a boundary a
    reader can apply.
13. **"at most once a day"** for the known-issues feed — it is cached twelve
    hours, so twice a day, and one hour after a failure, so up to twenty-four
    times a day if GitHub is unreachable.
14. **"it carries nothing about your site — no address"** — true of the body
    and query string, but WordPress's default User-Agent is
    `WordPress/<version>; <home_url>`, so the address does reach GitHub. Either
    set a `user-agent` on that request or drop the claim.

### Checked and accurate

- No describe request on upload — there is no describe hook on `add_attachment`.
- Demo mode sends nothing externally.
- The opt-in counts feature is opt-in, daily, and refuses without a key.
- "a downsized copy … never the original file" is true of large files; a file
  already under 1024 px and 600 KB is sent as it is, so the sentence wants one
  word of precision.

### Outside the code's reach

What the service does after receipt — retention, sub-processors, whether
images are discarded — is a claim about the server. Nothing in the plugin can
confirm or contradict it.
