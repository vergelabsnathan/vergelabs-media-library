# Every outbound request, and what decides its host

Written by hand on 2026-09-13, Phase 5.6 of `plans/four-yesses.md`.
`tests/security/hosts.mjs` reads this file: a `wp_remote_*` call without a row
here fails the suite, a row without a call fails it, a kind other than the three
fails it, and every helper cited in backticks below is read for a filter, an
option or a request. Rows are keyed by file and URL expression, not by line.

## The rule

Every host is decided by one of three things, and nothing else:

- **constant** — a URL written in the plugin.
- **define** — a `define()` the site owner put in `wp-config.php`, with the
  plugin's constant as the fallback. An owner's choice: whoever can edit
  `wp-config.php` can edit the plugin too, so the define adds no way in.
  `VERGEML_AI_SERVICE`, `VERGEML_AI_STREAM`, `VERGEML_SITE_URL` and
  `VGMLPRO_API_BASE` must be `https://` or loopback, or the constant is used
  instead; `VERGEML_KNOWN_ISSUES_URL` has no such rule (see the row).
- **derived** — built from one of those two, or from this site's own address
  (`site_url()`, the `siteurl` option only an administrator writes).

Not a REST parameter, not an option, not a post's content, and **not a
filter**: a filter on a host is a way in for any co-installed plugin, and these
requests carry the licence key. `vergeml_connect_base()` had one until today
(nothing used it); the service manual's `hooks.md` already stated the rule for
`vergeml_ai_service_url()`.

## TLS

17 of the 19 sites verify the certificate — nine say `'sslverify' => true`,
eight say nothing and WordPress's default is true. The two remaining are
loopbacks to **this site's own `wp-cron.php`**, the nudge that keeps a
background run going on a site nobody is browsing. They follow WordPress's own
`spawn_cron()`: `'sslverify' => apply_filters( 'https_local_ssl_verify', false )`
— unverified unless the owner turns that core filter on, because a site with a
self-signed certificate would otherwise never run its own cron. Nothing secret
travels on them: the query carries the cron lock key and the body is empty. The
suite allows that form only inside a function that names `wp-cron.php`; a
literal `false` anywhere is red.

## The sites

Hosts as they stand with no defines: `ai.vergelabs.nl` is the AI service,
`vergelabsmedia.com` the account site, `raw.githubusercontent.com` is GitHub's.

| where | url | host | kind | decided by |
|---|---|---|---|---|
| core/ai-background.php | `add_query_arg( 'doing_wp_cron', $key, site_url( 'wp-cron.php' ) )` | this site | derived | this site's own address through `site_url()`; the cron nudge inside a tick, as core's spawn_cron does it |
| core/ai.php | `vergeml_ai_service_url() . '/licence'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE` in wp-config.php, else the constant inside `vergeml_ai_service_url()`; two sites in this file, activate and check |
| core/ai.php | `$request['url']` | ai.vergelabs.nl | derived | `$request` is the array vergeml_ai_describe_request() returns a few lines above — not a WP_REST_Request — and its url is `vergeml_ai_service_url()` . '/describe' |
| core/brief.php | `$request['url']` | ai.vergelabs.nl | derived | the same array from vergeml_ai_describe_request(), so `vergeml_ai_service_url()` . '/describe'; the brief under test rides in the body |
| core/connect.php | `vergeml_connect_base() . '/api/connect/exchange'` | vergelabsmedia.com | define | `VERGEML_SITE_URL` in wp-config.php, else the constant inside `vergeml_connect_base()`; the one-time code goes here and the key comes back, so no filter — removed 2026-09-13 |
| core/connect.php | `vergeml_ai_service_url() . '/licence'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()`; gives the previous licence its seat back |
| core/filing.php | `vergeml_ai_service_url() . '/folders'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()` |
| core/folder-talk.php | `vergeml_ai_service_url() . '/folders'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()` |
| core/folder-talk.php | `$url` | this site | derived | `$url` is `add_query_arg( 'doing_wp_cron', …, site_url( 'wp-cron.php' ) )` three lines above in vergeml_talk_refile_schedule(): this site's own address through `site_url()`, the cron nudge |
| core/get-help.php | `VERGEML_KNOWN_ISSUES_URL` | raw.githubusercontent.com — GitHub's host, not ours | define | the constant at the top of core/get-help.php unless the owner defines `VERGEML_KNOWN_ISSUES_URL` first; a public JSON of known issues, read only, cached an hour; no https rule applies to this define |
| core/get-help.php | `vergeml_help_service_url() . '/support/ticket'` | ai.vergelabs.nl | define | `vergeml_help_service_url()` returns `vergeml_ai_service_url()` when core/ai.php is loaded and restates its rule (`VERGEML_AI_SERVICE`, https or loopback, else the constant) for safe mode |
| core/guide.php | `vergeml_guide_stream_url() . '/guide/session'` | ai.vergelabs.nl | define | `VERGEML_AI_STREAM` in wp-config.php, else `vergeml_ai_service_url()` unless that is loopback, then the constant — inside `vergeml_guide_stream_url()` |
| core/instrument.php | `vergeml_ai_service_url() . '/counts'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()`; sent only when the site opted in |
| core/licence-page.php | `vergeml_ai_service_url() . '/licence'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()`; gives the removed licence its seat back |
| core/search-meaning.php | `vergeml_ai_service_url() . '/embed'` | ai.vergelabs.nl | define | `VERGEML_AI_SERVICE`, else the constant inside `vergeml_ai_service_url()` |
| pro/includes/describe.php | `vgmlpro_api_base() . '/v1/describe'` | ai.vergelabs.nl | define | `VGMLPRO_API_BASE` in wp-config.php, else `VGMLPRO_API_DEFAULT`, inside `vgmlpro_api_base()`; https or loopback only |
| pro/includes/licence.php | `vgmlpro_api_base() . '/api/licence/verify'` | ai.vergelabs.nl | define | `VGMLPRO_API_BASE`, else `VGMLPRO_API_DEFAULT`, inside `vgmlpro_api_base()` |
| pro/includes/updates.php | `$url` | ai.vergelabs.nl | derived | `$url` is `add_query_arg( …, vgmlpro_api_base() . '/api/plugin/update' )` just above in vgmlpro_check_for_update(): `VGMLPRO_API_BASE`, else `VGMLPRO_API_DEFAULT`, inside `vgmlpro_api_base()` |

## Not `wp_remote_*`, and still outbound

- `core/mime-types.php`: `simplexml_load_file()` reads an SVG's width and
  height from `get_attached_file()`, and when that file is missing falls back
  to `wp_get_attachment_url()` — the site's own upload URL from the database,
  fetched over `allow_url_fopen`. Derived from the site's own address, read
  only, no key. Listed so the reader knows it exists; the suite does not count
  it.

## Found on the way

- `vergeml_connect_base()` applied `apply_filters( 'vergeml_connect_base', … )`
  to the host that receives the licence-exchange code and returns the key.
  Nothing in the plugin, the pro plugin, the tests or the service used the
  filter. Removed; the owner's `VERGEML_SITE_URL` define stays.
- `core/folder-talk.php`'s cron nudge said `'sslverify' => false` where its
  twin in `core/ai-background.php` said `apply_filters( 'https_local_ssl_verify', false )`.
  Now both say what core's `spawn_cron()` says.
- The plan's premise "sslverify true at every site" was true at 17; the two
  loopbacks are recorded above rather than forced to `true`, which would stop
  cron chaining on any site with a self-signed certificate. Nathan's to change.
