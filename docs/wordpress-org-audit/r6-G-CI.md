# Round 6 audit: G and CI rows (4.1.0 archive)

The release archive was read from `C:\Users\viete\AppData\Local\Temp\vgml-r6-archive`: 147 files (68 PHP, 43 JS, 22 CSS), byte-identical to `HEAD` `5dc983a`. The audit counts annotated code as code, so a line under `phpcs:ignore` gets the same verdict as any other. Every grep below ran over that folder. The scripts live outside it, in `C:\Users\viete\AppData\Local\Temp\vgml-r6-gci-scratch\`:

- `names.js`: every declared name by class, with the ones that do not carry the prefix
- `toplevel-vars.js`: file-scope variables
- `sql.js`: every `$wpdb` query call, classified
- `prepare-concat.js`: any concatenation inside a `prepare()` first argument
- `echo-strict.js`: operands of `echo`, `print`, `printf`, `_e` and `wp_die`. Only `esc_*`, `wp_kses*`, `absint`, `intval`, `number_format_i18n`, `checked`, `selected` and literals count as escaped.
- `i18n.js`: arguments of every gettext call in PHP and JS
- `rest-args.js`: declared REST args for each callback, against the params it reads
- `mangle.js`: share of single-letter bindings per JS file
- `links.mjs`: HTTP status of each readme link

Totals: **114 G rows, 120 CI rows.**

| ID | verdict | evidence |
|---|---|---|
| G0.1 | N/A | Process: who holds commit or support access on the plugin's Advanced page. Not in the archive. |
| G0.2 | N/A | Consequence clause; it has no check of its own. |
| G0.3 | N/A | Process: the `vergelabsdev` wordpress.org profile email must be a monitored inbox. Nathan has to confirm this. |
| G0.4 | N/A | Process: the profile email must not be a helpdesk or send auto-replies. The readme's `support@vergelabsmedia.com` is for customers; this rule is about the wordpress.org profile email. |
| G0.5 | PASS | The hint's five checks. (1) Superglobals: `grep -rnE '\$_(POST\|GET\|REQUEST\|FILES\|COOKIE\|SERVER)\b'` finds 136 code lines. Each is `isset`/`empty`, compared to a literal, cast, or wrapped in `sanitize_*( wp_unslash() )`. (2) Output: 268 output statements, 0 unescaped operands (CI-escape-output). (3) SQL: 0 plugin tables interpolated, 0 unprepared variable SQL (CI-wpdb-prepare). (4) REST: 77 `register_rest_route` with 85 named permission callbacks and 0 `__return_true`. (5) Handlers: 6 `wp_ajax_` and 10 `admin_post_` handlers, each with `check_ajax_referer`/`check_admin_referer` plus `current_user_can` (e.g. offer.php:147-149, options-pages.php:884-887, taxonomies.php:1353-1355, :1415-1433, transport.php:40-53). `tb_load_editor` only enqueues, behind `upload_files` (page-builders.php:46-52). Two intake points a reviewer may quote are filed under CI-sanitize-input. Neither is exploitable. |
| G0.6 | PASS | Same pass as G0.5; no exploitable endpoint found in this share. The route-by-route permission audit is the REST share. |
| G0.7 | N/A | Process (asking plugins@ before upload). |
| G1.1 | PASS | vergelabs-media-library.php:13-14 `License: GPLv2 or later` / GPL-2.0 URI; readme.txt:8-9 `License: GPLv2 or later`. |
| G1.2 | PASS | Every file in the inventory: own code and Enhanced Media Library-derived code GPLv2+ (header :16-26); `fonts/inter-*.woff2` SIL OFL 1.1 with `fonts/INTER-LICENSE.txt`; `fonts/webbistro.*`, Enhanced Media Library's IcoMoon-generated icon font, shipped by EML under GPLv2+ and credited in readme.txt:117-123; `images/spinner*.gif`, WordPress core's spinners (GPL); natural-compare-lite under MIT with its notice (js/eml-media-models.js:265-270). Nothing proprietary or NC. |
| G1.3 | PASS | Same inventory. 0 `vendor/`, `node_modules/`, `composer.json`, `package.json` in the archive (`find`). |
| G2.1 | PASS | Whole zip judged: css 22, js 43, php 68, txt 3, md 1, pot 1, xml 1, fonts 6, gif 2; 0 hidden files, 0 vendor/build directories. |
| G2.2 | PASS | Every class removed in rounds 1-5, grepped back at 0: `deactivate_plugins` 0; `wp_dequeue_*`/`wp_deregister_*` 0. `remove_action`/`remove_filter` appear only on the plugin's own callbacks: admin-shell.php:499 behind the own-file check :507-529, folder-talk.php:2700-2701, mime-types.php:38. Writes to `$pagenow`/`$hook_suffix`/`$submenu` 0; JS `findPosts=`/`adminpage=`/`pagenow=` 0; `toggleSelection` is a subclass (js/eml-media-grid.js:353-364). `wp_add_inline_script` 0; `Requests::` 0 (comment at ai.php:772 only); `doing_cron` written 0 (read only, ai-background.php:213). `upload_mimes` is remove-only (mime-types.php:287-302); `raw.githubusercontent` 0. Core AJAX actions: 0 answered (taxonomies.php:1262/:1314 only test `doing_action`). The service URL is forced to https (ai.php:316-318). |
| G2.3 | PASS | The inventory is in the readme: "What does it include from other projects?" (readme.txt:117-123) lists EML 2.9.4 and webbistro, Inter (OFL), natural-compare-lite (MIT), WP Rocket's admin layout and WordPress's spinners. |
| G2.4 | PASS | Only the plugin's own services are called. Request hosts in code: `ai.vergelabs.nl` (ai.php:313, get-help.php:187) and `vergelabsmedia.com` (connect.php:39). The other URL strings are not requests: the w3.org SVG namespace and links. Terms: vergelabsmedia.com/legal/terms, HTTP 200 on 2026-10-12. |
| G2.5 | PASS | Every bundled asset has a stated licence (G1.2); every API host has public terms (G2.4). |
| G3.1 | N/A | Process (SVN tag = release build). |
| G3.2 | N/A | Process (GitHub releases vs SVN). The archive carries no self-update code (G8.3). |
| G4.1 | PASS | `eval(` 0, `create_function` 0, `gzinflate\|gzuncompress\|gzdecode\|str_rot13` 0, `p,a,c,k,e` 0, `\x..\x..` runs 0, `chr(n).chr` chains 0, `atob(` 0, `new Function(` 0. `base64_decode` 2, both data, not code: ai.php:355 (the plugin's own sealed licence key) and organize.php:2403 (its own packed float vectors). |
| G4.2 | **FAIL** | Two shipped files are minifier output, pretty-printed: single-letter bindings, `!0`/`!1`, comma-chained statements. Their own headers say "Recovered from …min.js". See FAIL 1. |
| G4.3 | **FAIL** | `\$[a-z_]{1,3}[0-9][a-z0-9]{5,}` 0 hits and `ioncube\|sg_load` 0. But the two files in FAIL 1 keep the minifier's single-letter names (43/45 and 38/55 bindings): "unclear naming". Same fix. |
| G4.4 | PASS | No build step. 0 `*.min.js`/`*.min.css`, 0 dist/build directories, and no line of 400 characters or more in any of the 43 JS files. The `-rtl.css` sheets are readable generated copies of the shipped LTR sheets; their generator `tools/rtl.mjs` is in the public repo (vergelabs-media-library.php:86-90). |
| G4.5 | PASS | readme.txt:21 `[Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)` returns HTTP 200. |
| G4.6 | PASS | No compiled output to document. Optional: one readme line naming `tools/rtl.mjs` would cover the only generator. |
| G5.1 | PASS | Every licence check guards a service request: ai.php:101/:153/:518 (activate, credits, describe), ai.php:1952, ai-background.php:251, brief.php:329-333/:462/:542, filing.php:1768/:3139, folder-talk.php:443/:2341, guide.php:871-877 (stream token), guide.php:1278 (profile asks), search-meaning.php:125-127, instrument.php:227, offer.php:34 (the offer replaces a service action). Tree, smart folders, health, import, galleries, MIME settings, the Librarian's date/type scheme and the plan-grow have no licence check. UI strings `Unlock\|Premium\|Pro version\|Upgrade to`: 0; 3 code comments only (options-pages.php:2084/2649/2778). The file renamer is off behind `VERGEML_FILE_RENAME` (journey.php:353), a constant for an unfinished feature; nothing is gated on payment. |
| G5.2 | PASS | `days_left\|install_date\|expires\|_quota\|trial_ends` used as logic: 0. The caps found are technical: `VERGEML_HEALTH_CAP` 200 is a list length (health.php:109); `VERGEML_GUIDE_TURN_CAP` 25 counts service conversation turns. The trial is 25 service credits (offer.php:21). |
| G5.3 | PASS | `sandbox\|test mode` 0; production endpoint ai.php:313. |
| G5.4 | PASS | The paid part is the service (credits); the local code is complete (G5.1). |
| G5.5 | PASS | No premium code: `_PRO\|premium/\|pro/` as logic 0. EML Pro appears only as a name in a detection list (neighbours.php:56). `vergeml_autofile_vector` (auto-file.php:188) is an extension point, not shipped premium code. |
| G5.6 | N/A | Not a developer tool. |
| G5.7 | PASS | Buy links appear only on the plugin's AI and licence screens: `vergeml_buy_url` callers ai-screen.php:412, licence-page.php:154, offer.php:65/:133 (AI screen without a licence), connect.php:281 (rendered only from licence-page.php:174-175). The credits pill is on own screens (admin-shell.php:415). |
| G6.1 | PASS | The plugin is an interface to the VergeLabs service: 21 `wp_remote_*` call sites; key field on the Licence screen. |
| G6.2 | PASS | Real processing: `/describe` (ai.php:594), `/embed` (search-meaning.php:132/:200), `/folders` (folder-talk.php:458), `/plan-tree` (plan-tree.php:1042), filing asks (filing.php:1794). |
| G6.3 | PASS | readme.txt:51-77 `## External services ##` names the hosts, the data and the triggers; Terms and Privacy at :77, both HTTP 200. |
| G6.4 | PASS | `/activate` (ai.php:105) registers the key with the service; no local code unlocks on its answer (G5.1). |
| G6.5 | PASS | No remote config, HTML, CSS or feature flags are rendered. What comes back is captions, tags, vectors, plans, the credit balance and the known-issues JSON (get-help.php:76, documented). |
| G6.6 | PASS | A full media-library tool (tree, filters, taxonomies, MIME types, import, galleries); purchase links are a small part (G5.7). |
| G7.1 | RUNTIME | Static trace of 21 call sites: 1 loopback to the site's own wp-cron.php (ai-background.php:220). 2 behind opt-in or consent: stats (instrument.php:233, opt-in default 0 at :47) and the help ticket (get-help.php:260, consent :205/:212). 1 on a nonce'd button (known issues, get-help.php:76/:289). Trial, connect and licence on a button (offer.php:159, connect.php:161/:186, licence-page.php:55). 13 need a licence key plus a user action, or are the credits refresh when a VergeLabs screen opens (G7.9). Activation makes no request. Only a running site can prove that nothing leaves without consent; see RUNTIME. |
| G7.2 | PASS | Opt-ins default off: instrument.php:47 `'opted' => 0`. The help consent checkbox is unchecked (get-help.php:368). The trial needs a typed email and a press of Start (offer.php:153-166). |
| G7.3 | PASS | readme.txt:51-77 lists every payload, with Privacy, DPA, sub-processors and retention links (:75-77), all HTTP 200. |
| G7.4 | PASS | `admin_email\|REMOTE_ADDR\|HTTP_USER_AGENT\|get_plugins(` 0. The system report and plugin list go only in a help ticket, after the consent tick (get-help.php:205/:212/:240-245). The email field is prefilled but sent only on Send. |
| G7.5 | PASS | The plugin works without signing up; the trial email is only for the optional service key. No activation redirect: `wp_redirect\|activation_redirect` 0, and all 18 `wp_safe_redirect` follow a form action. |
| G7.6 | PASS | `wp_enqueue_*` with an http src: 0. CSS `url(` points only at `../fonts`, `../images` and `data:` (css/eml-admin.css:96-193, css/vergeml-shell.css:41-50). `<img src="http`: 0. |
| G7.7 | PASS | The only remote list is known-issues (get-help.php:58/:76): fetched on a button press, cached 12 h, documented at readme.txt:69. |
| G7.8 | PASS | `googletagmanager\|gtag(\|analytics.js\|facebook.net\|hotjar\|mixpanel\|segment\|posthog\|clarity.ms\|plausible\|matomo\|adsbygoogle\|doubleclick`: 0. |
| G7.9 | PASS | Service calls need a configured key (`vergeml_ai_ready`, ai.php:367-369) and a user action; the readme says so. |
| G8.1 | PASS | `'http://` request URLs 0 (only the w3.org SVG namespace, admin-menu.php:109 and css); `sslverify => false` 0 (14 explicit `true`); service URL forced https (ai.php:316-318, get-help.php:189). Note: `VERGEML_SITE_URL` (connect.php:39), `VERGEML_KNOWN_ISSUES_URL` (get-help.php:58) and `VERGEML_AI_STREAM` (guide.php:856) are wp-config overrides without that https check. They are owner-set; the same `0 === strpos( $url, 'https://' )` rule would close them. |
| G8.2 | PASS | `eval\|create_function\|assert(` 0. `include`/`require` only load `plugin_dir_path( __FILE__ )` files, and every target exists. `unserialize(` 0; `maybe_unserialize` 2, on the database's own meta values (health-delete.php:208, health.php:932). Remote bodies are only `json_decode`d. |
| G8.3 | PASS | `pre_set_site_transient_update_plugins\|site_transient_update_plugins\|plugins_api\|update_plugins_\|Puc_\|EDD_SL\|Plugin_Upgrader\|Theme_Upgrader\|unzip_file\|download_url`: 0. No `Update URI:` header (vergelabs-media-library.php:2-27). |
| G8.4 | PASS | No installer (G8.3 greps 0). |
| G8.5 | PASS | `cdn\|jsdelivr\|unpkg\|cdnjs\|code.jquery\|ajax.googleapis` in PHP/JS/CSS: 0. Every asset is served by `plugins_url()` or `plugin_dir_url()`. |
| G8.6 | PASS | The 7 cron hooks (uninstall.php:43) do local work or a service run the user booked; no scheduled list fetch. |
| G8.7 | PASS | `<iframe` in PHP/JS: 0. |
| G8.8 | PASS | No route installs or updates software. All 77 routes sit under `vergeml/v1`, on media, folders and AI. |
| G9.1 | PASS | Umbrella row; G9.2-G9.15 have no FAIL. |
| G9.2 | PASS | 5 tags (readme.txt:3). Term frequency is natural: folders 39, plugin 36, media 34 in 3,120 words. Front-end output carries no hidden links (G10.1). |
| G9.3 | PASS | `traffic\|backlink\|more visitors\|rank higher` in readme, changelog, PHP, JS: 0. |
| G9.4 | PASS | Review/rating prompts (`rate us\|5 star\|leave a review\|/reviews`): 0. |
| G9.5 | PASS | Unlock/Premium/Pro/Upgrade-to strings in the UI: 0 (G5.1). |
| G9.6 | N/A | Process (sockpuppeting). |
| G9.7 | PASS | The fork is credited: header :16-20 keeps wpUXsolutions' copyright, readme.txt:19, admin footer (options-pages.php:3058), FAQ credits (readme.txt:117-123). |
| G9.8 | PASS | `complian\|gdpr\|ccpa\|wcag\|\bADA\b\|legally\|accessibility act\|section 508` in UI and readme: 0. One hit is a code comment (js/vergeml-tree.js:2504, WCAG reference). |
| G9.9 | PASS | `coinhive\|cryptonight\|miner\|.wasm`: 0; cron does the plugin's own work only. |
| G9.10 | N/A | Process (conduct). |
| G9.11 | N/A | Process (conduct). |
| G9.12 | N/A | Process (forum conduct). |
| G9.13 | PASS | Other plugins are named neutrally: readme.txt:19 ("not made by, or affiliated with") and the neighbours notice (neighbours.php:111-116). |
| G9.14 | N/A | Process (identity). |
| G9.15 | PASS | Nothing meets the letter but not the intent: opt-ins default off, consent unchecked, the licence gates only service calls, no remote feature flags. |
| G10.1 | PASS | Front-end output is the gallery block, shortcode and builder widgets (gallery-block.php, gallery-widgets.php). `Powered by\|vergelabs.(nl\|com)\|vergelabsmedia` in those files, js/vergeml-gallery*.js and css/vergeml-gallery.css: 0. `wp_footer\|wp_head\|the_content` hooks: 0. |
| G10.2 | PASS | There is no credit to opt into (G10.1). |
| G10.3 | PASS | Nothing requires a credit to work (G10.1). |
| G10.4 | PASS | No branding in the front-end output. |
| G11.1 | PASS | 9 `admin_notices`/`network_admin_notices` hooks. Four only follow the plugin's own redirect: bulk-terms.php:230-236 (upload screen plus flag), media-list.php:729/:740, rename.php:402/:461. Neighbours (neighbours.php:76-117): media and own screens only, dismissible. Two problem notices show only while the problem exists: requirements (vergelabs-media-library.php:1601-1618) and safe mode (watchdog.php:26-39). The connect banner is rendered only on the Licence screen (licence-page.php:174-175). |
| G11.2 | PASS | Upgrade prompts only on the AI and licence screens (G5.7); no notice upsells. |
| G11.3 | PASS | The site-wide notices (requirements, safe mode) clear themselves when the extension or the constant changes. The neighbours notice's dismissal is stored per user (neighbours.php:121-149). Dashboard widgets: 0. |
| G11.4 | PASS | Both error notices name the fix: the host enables mbstring/OpenSSL (vergelabs-media-library.php:1612-1615), or the wp-config line is removed (watchdog.php:37). |
| G11.5 | PASS | `wp_add_dashboard_widget`: 0; no promotion outside the plugin's own screens. |
| G11.6 | PASS | Buy links carry `utm_source=plugin`, `utm_medium=<screen>` and `utm_campaign=upgrade` (connect.php:51-59): no site identifier, and the user clicks them. `?ref=\|affiliate`: 0. |
| G11.7 | PASS | `adsbygoogle`: 0. |
| G11.8 | PASS | Admin images are local or inline SVG (admin-menu.php:109-118 data URI, core/icons.php); `<img src="http`: 0. |
| G12.1 | PASS | readme.txt and languages/vergelabs-media-library.pot: the pot's only URLs are the plugin URI, the author URI and the support forum. No spam. |
| G12.2 | PASS | The readme's 10 links: terms, privacy, DPA, sub-processors, retention, GitHub ×2, Patchstack VDP, EML on wordpress.org. All answer 200 (Patchstack redirects to its plugin page); 0 affiliate links, shorteners or redirects. |
| G12.3 | PASS | The tags name no competitor. |
| G12.4 | PASS | 5 tags (readme.txt:3). |
| G12.5 | PASS | No stuffing in title, short description or Description (frequencies at G9.2). |
| G12.6 | PASS | No required products; links in moderation (G12.2). |
| G12.7 | PASS | The tags are generic terms, not product names. |
| G12.8 | **FAIL** | readme.txt:3: the term "media" appears in 3 of 5 tags. See FAIL 2. |
| G12.9 | PASS | The Description is prose written for people (readme.txt:13-49). |
| G12.10 | N/A | No affiliate links. |
| G13.1 | PASS | `grep -rliE "jQuery JavaScript Library\|Underscore.js [0-9]\|Backbone.js [0-9]\|moment.js\|lodash.com\|@license React\|PHPMailer\|SimplePie\|PasswordHash\|phpass"`: 0 files. Dependencies are core handles (`jquery`, `underscore`, `media-views/models/editor/grid`, `wp-api-fetch`, `wp-blocks`, style `wp-jquery-ui-dialog` at vergelabs-media-library.php:844). |
| G13.2 | PASS | Same as G13.1: no bundled copy of a core-registered script. |
| G14.1 | N/A | SVN process. |
| G14.2 | N/A | SVN process. |
| G14.3 | N/A | SVN process. |
| G14.4 | N/A | SVN process. |
| G14.5 | N/A | SVN process. |
| G14.6 | N/A | SVN process. |
| G15.1 | PASS | `Version: 4.1.0` (vergelabs-media-library.php:6) = `VERGEML_VERSION` `'4.1.0'` (:43). |
| G15.2 | PASS | `Stable tag: 4.1.0` (readme.txt:7) = Version. |
| G16.1 | PASS | Complete: a scripted check of every `include`/`require` target, every `plugins_url()`/`$vergeml_dir` asset and every `vergeml_screen_script` file found 0 missing. Activation is proved at runtime (RUNTIME 3). |
| G16.2 | PASS | Real functionality, not a reserved name. |
| G16.3 | N/A | Process. |
| G17.1 | PASS | Name and slug start with the plugin's own brand (vergelabs-media-library.php:3, readme.txt:1). |
| G17.2 | PASS | Neither the slug nor the plugin name contains "wordpress". |
| G17.3 | PASS | The slug's first token is `vergelabs`. |
| G17.4 | N/A | The name integrates no third-party brand. |
| G17.5 | PASS | Not named after a bundled library. |
| G17.6 | PASS | Own name and menu mark (admin-menu.php:100-118). Banner and icon live in the SVN `assets/` folder, not in this zip. |
| G18.1 | N/A | Process. |
| G18.2 | N/A | Process. |
| G18.3 | N/A | Process. |
| G18.4 | N/A | Process. |
| G18.5 | N/A | Process. |
| CI-sanitize-input | **FAIL** | 136 superglobal code lines are all sanitized or cast at first use (G0.5). Three REST intake points use raw input: the bridge's `php://input` params, the Folders-screen instruction and history, and imported CSV cells. See FAIL 3. |
| CI-validate | PASS | Allow-lists: scope (ai.php:1962, ai-background.php:247), quarantine action (quarantine.php:396), mode (folder-talk.php:477), taxonomy against `vergeml_tree_taxonomies()` (rest-folders.php:187, import.php:151), NL verb (nl-commands.php:607). REST args are typed (`integer`/`boolean`/`array`) in 54 `args` blocks. Superglobals are compared to literals or cast (journey.php:1111 `max(0,(int))`, bulk-terms.php:254 `absint`). |
| CI-escape-output | PASS | `echo-strict.js` covers 268 statements (echo, print, printf, vprintf, `_e`, `_ex`, `wp_die`, exit): 0 unescaped operands. Its 4 flags are 3 ternaries with both branches escaped (admin-shell.php:740, journey.php:1311, options-pages.php:1146) and the JSON download (options-pages.php:1243). JS: 0 dynamic `innerHTML` sinks beyond static SVG or literals; the jQuery HTML sinks concatenate only l10n strings. |
| CI-context-functions | PASS | Email `sanitize_email` (get-help.php:206, offer.php:154); textarea `sanitize_textarea_field` (get-help.php:204, ai.php:2001, guide.php:1557); file name `sanitize_file_name` and MIME `sanitize_mime_type` (options-pages.php:1288-1289). Keys use `sanitize_key`, ints `absint`/`(int)`. URLs use `esc_url` at output (46 calls); `esc_url_raw` only feeds a redirect (connect.php:139). |
| CI-escape-not-sanitize | PASS | `esc_*(` on `$_POST`/`$_GET`/`$_REQUEST` or `get_param`: 0 (Grep). `esc_*` inside `update_*`/`insert`/`wp_insert_term`: 0. |
| CI-sanitize-then-escape | PASS | No superglobal is echoed (0 echo operands contain one). |
| CI-filter-param | PASS | `filter_input\|filter_var\|filter_input_array\|filter_var_array`: 0. |
| CI-filter-default | PASS | `FILTER_DEFAULT\|FILTER_UNSAFE_RAW`: 0. |
| CI-nonce-sanitize | PASS | 15 `wp_verify_nonce` calls. 14 take `sanitize_text_field( wp_unslash( … ) )` or `sanitize_key( wp_unslash( … ) )` inline; transport.php:46 takes a `$nonce` already sanitized at :40/:43. |
| CI-whole-input | **FAIL** | admin-menu.php:200 `foreach ( array_keys( $_GET ) as $key )`, and transport.php:83-93 forwards every key of the JSON body's `params`. See FAIL 4. |
| CI-escape-echoed-vars | PASS | Bare `$var`, constant or helper-call echoes: 0 (`echo-strict.js`). |
| CI-escape-late | PASS | Helper markup is escaped again at the echo, through `wp_kses( …, vergeml_kses_allowed() )` at 13 sites (e.g. licence-page.php:150-258, options-pages.php:1823/:2335/:2828). `echo $html`-style output: 0. |
| CI-escape-options | PASS | `echo get_option\|get_post_meta\|get_user_meta\|get_term_meta`: 0. |
| CI-return-not-echo-json | PASS | AJAX and REST answer through `wp_send_json*` or `rest_ensure_response`. The only JSON echo is the settings-export file download (options-pages.php:1236-1245, `Content-Disposition: attachment`). |
| CI-sanitize-json | PASS | Service answers are sanitized before storage: ai.php:684-697 runs `sanitize_text_field` on caption, alt, tags, title and model; keys at connect.php:198 and offer.php:187. REST responses return stored, sanitized data. |
| CI-escape-html-too | PASS | HTML is printed through `wp_kses` with an explicit allow-list (vergelabs-media-library.php:159-207: no script, no iframe, no `on*`). |
| CI-esc-url-raw | PASS | 1 use, connect.php:139, inside `wp_safe_redirect`: a sanitizing use, not output. |
| CI-esc-url | PASS | `href=`/`src=`/`action=` concatenated without `esc_url`: 0. The arguments of every `%s` URL in sprintf/printf were read: all `esc_url` (admin-shell.php:329/:417, ai-screen.php:220, connect.php:279, journey.php:457, rename.php:329/:437, search-meaning.php:712). |
| CI-escape-__ | PASS | `echo __(\|_x(\|_n(`: 0; `printf( __(`: 0 across 35 printf calls. |
| CI-escape-_e | PASS | `_e(`: 0. |
| CI-escape-_ex | PASS | `_ex(`: 0. |
| CI-wp-json-encode | PASS | Bare `json_encode(`: 0. |
| CI-json-flags | PASS | `JSON_UNESCAPED_*`: 0. |
| CI-kses-html | PASS | Same evidence as CI-escape-html-too. |
| CI-esc-html-on-html | PASS | `esc_html( $html\|$out\|$output\|$markup`: 0. |
| CI-sanitize-not-escape | PASS | `echo sanitize_*`: 0. |
| CI-wp-handle-upload | PASS | `move_uploaded_file\|copy( $_FILES`: 0. The settings import only reads its tmp file after `is_uploaded_file` (options-pages.php:1295/:1309) and stores nothing on disk. |
| CI-unfiltered-uploads | PASS | `ALLOW_UNFILTERED_UPLOADS\|unfiltered_upload`: 0. |
| CI-upload-mimes | PASS | The `upload_mimes` filter is remove-only (mime-types.php:287-302, `unset()` only). `wp_check_filetype_and_ext` (:324-415) lets a real-MIME mismatch through only for ttf/otf/woff/woff2, and for an xlsm already in `get_allowed_mime_types()`. Runnable types are refused (`vergeml_mime_refused`). |
| CI-remote-assets | PASS | Remote assets: 0 (G7.6, G8.5). |
| CI-include-locally | PASS | All assets are local; core libraries come through core handles (G13.1). |
| CI-remote-service-exception | N/A | No remote asset to excuse. |
| CI-no-remote-jquery-css | PASS | `ajax.googleapis`: 0. |
| CI-no-help-iframe | PASS | `iframe`: 0; help is local (core/help.php) plus links. |
| CI-no-own-domain-images | PASS | `<img src=` or `url(` pointing at the plugin's own domain: 0. |
| CI-ok-google-fonts | N/A | No Google Fonts; Inter ships locally. |
| CI-ok-service-api | PASS | Calls to the plugin's own service are documented (readme.txt:51-77). |
| CI-ok-offload-comments | N/A | Not a comments service. |
| CI-ok-oembed | N/A | No oEmbed calls. |
| CI-service-readme | PASS | readme.txt:53 names both hosts, the licence requirement and the account (trial or connect). |
| CI-lib-stable | PASS | No pre-release library: no lock files. The one embedded library is natural-compare-lite (js/eml-media-models.js:265-312). |
| CI-lib-beta-explain | N/A | No pre-release library. |
| CI-lib-unmaintained | PASS | natural-compare-lite's last release was 2015. It is a 40-line pure function inlined with its notice: no dependency, no known issue. If asked: it is a snippet, not a dependency. |
| CI-lib-outdated | PASS | natural-compare-lite has no newer release. |
| CI-wpdb-prepare | PASS | `sql.js` over every `$wpdb->query/get_*`: 181 with `prepare()` inline; 20 static (core tables and literal LIKE only, e.g. ai-screen.php:117/:119, uninstall.php:36-210); 6 interpolate only core table properties inside `prepare` (taxonomies.php:1117/:1128/:1558/:1611, options-pages.php:1554/:1574); 17 are prepared fragments for `posts_join`/`posts_search` or nested calls. 0 plugin tables interpolated (all `%i`); 0 `mysqli_`/`PDO`. DROP goes through `prepare` with `%i` (ai-index.php:55, options-pages.php:1616, uninstall.php:116); CREATE only into `dbDelta`. |
| CI-wpdb-array-placeholders | PASS | Every IN list is `implode( ', ', array_fill( 0, count( $x ), '%d' ) )` (`prepare-concat.js`: 198 prepare calls; the one other concatenation is librarian.php:1785, a multi-row VALUES list built the same way). `IN ( $` or `IN ( " .` without `array_fill`: 0. |
| CI-no-heredoc | PASS | `<<<`: 0. |
| CI-direct-access | PASS | 67/68 PHP files exit when `ABSPATH` is undefined (uninstall.php: `WP_UNINSTALL_PLUGIN`). The lines above each guard were checked to be comments only. index.php holds no code. |
| CI-prefix-unique | PASS | `names.js`: 1,103 function names; the 13 without the prefix are methods of the plugin's 6 classes. 0 namespaces; 5 `define` and 172 `const`, all `VERGEML_`. The plugin's own options, transients, meta, cron hooks, AJAX and admin-post actions, script handles, the shortcode, the block (`vergelabs/folder-gallery`) and the REST namespace (`vergeml/v1`) all carry the prefix. The unprefixed names found are other code's, read on purpose: core options such as `active_plugins`, EML's `wpuxss_eml_*` (migration), and the read of core's `doing_cron`. The default taxonomy `media_category` is EML's shared slug on purpose (readme FAQ); HB-61 and review 6's LQ6 own that question. |
| CI-prefix-functions | PASS | Every top-level function starts `vergeml_` (CI-prefix-unique). |
| CI-prefix-classes | PASS | `vergeml_Walker_FolderDropdown`, `vergeml_Walker_CategoryDropdown`, `vergeml_Walker_Media_Taxonomy_Checklist`, `vergeml_Walker_Media_Taxonomy_Uploader_Filter`, `VergeML_Elementor_Gallery`, `VergeML_Divi_Gallery`. |
| CI-prefix-namespace | N/A | No namespace declared. |
| CI-prefix-options | PASS | Own option keys are literals `vergeml_*` or `VERGEML_*` constants. A scan of constant values: every string constant starts `vergeml`/`_vergeml`, except `VERGEML_FILING_TO_SORT_SLUG` `'to-sort'`, a term slug and not an option. |
| CI-prefix-defines | PASS | `VERGEML_VERSION`, `VERGEML_MENU`, `VERGEML_FILE`, `VERGEML_CREDITS_TTL`, `VERGEML_TRIAL_PICTURES`, plus 172 `const VERGEML_*`. |
| CI-prefix-globals | PASS | File-scope variables (`toplevel-vars.js`) are all `$vergeml_*` (compatibility.php:57-64, smart-folders.php:571, uninstall.php:151-182). Own `global` and `$GLOBALS` names are prefixed (`$vergeml_dir`, `vergeml_force_tree`, `vergeml_organize_points`, `vergeml_meaning_meta`). The other globals are core's, read: `$wpdb`, `$pagenow`, `$current_screen`, `$wp_query`, `$wp_locale`, `$wp_version`, `$_wp_admin_css_colors`, and `$wp_filter`, read to find the plugin's own callbacks. `$wp_taxonomies` is review 6's LQ3. JS window globals: 14 `vergeml*`/`vgml*`, plus `wp`/`wpCookies` `\|\| {}` guards. |
| CI-prefix-length | PASS | `vergeml` (7), `vgml` (4), `vergelabs`. |
| CI-prefix-reserved | PASS | Own functions, hooks or options starting `wp_`, `__` or `_`: 0. `_vergeml_*` meta is hidden meta that still carries the prefix. |
| CI-function-exists | PASS | `if ( ! function_exists\|class_exists` wrapping a definition: 0 (all 43 hits are runtime checks before a call). `if ( defined( 'VERGEML_VERSION' ) ) return;` (vergelabs-media-library.php:39-41) only stops a second copy of this same plugin. |
| CI-no-short-tags | PASS | `<?=`: 0; `<? `: 0. |
| CI-no-global-ini | PASS | `ini_set`: 0. `set_time_limit` 4, each inside a cron handler behind `vergeml_can_set_time_limit()` (ai-background.php:350, guide.php:1701, plan-tree.php:348/:859). `ignore_user_abort` once, inside the export download (options-pages.php:1236). `register_shutdown_function` once, inside a run (ai-background.php:206). |
| CI-ini-scoped | PASS | Same evidence as CI-no-global-ini. |
| CI-no-default-timezone | PASS | `date_default_timezone_set`: 0. |
| CI-no-error-reporting | PASS | `error_reporting(\|display_errors`: 0. |
| CI-main-file-name | PASS | `vergelabs-media-library.php` = slug. The zip must keep its `vergelabs-media-library/` top folder; this extracted copy is flat (RUNTIME 4). |
| CI-main-file-common-names | PASS | Not `plugin.php`, `index.php`, `main.php` or `init.php`. |
| CI-headers-complete | PASS | Plugin Name, Plugin URI, Description, Version, Requires at least 6.5, Requires PHP 7.4, Author, Author URI, Text Domain, Domain Path, License, License URI (:3-14). |
| CI-headers-main-only | PASS | `Plugin Name:` appears in 1 file. |
| CI-readme-complete | PASS | Header block :1-9, Description :13, External services :51, Installation :79, FAQ :87, Screenshots :137, Upgrade Notice :146, Changelog :151. |
| CI-readme-no-surprises | PASS | No plugin dependency. The licence, the costs (credits, prices on the button, :157) and every external call are stated (:51-77). |
| CI-readme-validate | RUNTIME | Only the wordpress.org validator can say (RUNTIME 2). |
| CI-readme-txt-not-md | PASS | readme.txt is present; SECURITY.md is a separate file. |
| CI-readme-template | PASS | The template's sections in template order. Markdown `##`/`###` headings, which the parser maps to `==`/`=`. An External services section, as wordpress.org asks. |
| CI-license-declared | PASS | readme.txt:8 and vergelabs-media-library.php:13. |
| CI-license-all-content | PASS | Same evidence as G1.2. |
| CI-stable-tag-match | PASS | 4.1.0 = 4.1.0. |
| CI-stable-tag-not-wp | PASS | Stable tag 4.1.0; Tested up to 7.1. |
| CI-semver | PASS | `4.1.0` matches `^\d+\.\d+\.\d+$`. |
| CI-no-stable-trunk | PASS | Not `trunk`. |
| CI-tag-and-increment | N/A | SVN process. |
| CI-license-same | PASS | Both say `GPLv2 or later`. The URIs differ (`old-licenses/gpl-2.0.html` in the header, `licenses/gpl-2.0.html` in the readme) but both are the GPLv2 text; aligning them costs nothing. |
| CI-license-sole | PASS | One licence declared; third-party licences documented (readme FAQ, fonts/INTER-LICENSE.txt, js/eml-media-models.js:265-270). |
| CI-http-api | PASS | `curl_*`: 0; `fsockopen\|stream_socket_client`: 0; `file_get_contents`/`fopen` on http: 0 (every `file_get_contents`/`fopen` reads a local path, `php://input` or `php://temp`). 21 `wp_remote_*` call sites. |
| CI-http-api-curl-hook | N/A | No curl options needed. |
| CI-http-api-vendor-ok | N/A | No vendor libraries. |
| CI-enqueue-js | PASS | `<script` in PHP: 3. Two are `type="text/html"` Underscore templates on `print_media_templates` (media-templates.php:19/:93); one is a string inside the `script_loader_tag` filter (neighbours.php:196). `wp_add_inline_script`: 0. The four screen scripts are files (`vergeml_screen_script`, vergelabs-media-library.php:114-116). |
| CI-enqueue-css | PASS | `<style` and `<link rel=stylesheet`: 0. Two `wp_add_inline_style`, both through `wp_strip_all_tags` (admin-menu.php:88, media-list.php:300). |
| CI-enqueue-attributes | PASS | No hand-written tags. `data-no-optimize` attributes are added through `script_loader_tag` on the plugin's own handles only (neighbours.php:188-197). |
| CI-enqueue-admin | PASS | 19 `admin_enqueue_scripts` callbacks. 17 return unless the hook or screen is the plugin's own (e.g. ai-screen.php:51, media-list.php:200, quick-edit.php:121; `vergeml_is_own_admin_screen` vergelabs-media-library.php:793-812; tree-ui.php:31-70). `vergeml_rtl_styles` only marks handles already registered. `vergeml_hide_submenu` (admin-menu.php:84-89) is one rule on the plugin's own sidebar item, needed on every screen by nature. The media-modal sheet loads on `wp_enqueue_media` (:942-956). Its scope is still review 6's open LQ1: body class `.eml-media-css`, added at js/vergeml-media-views.js:943. HB-75 owns it. |
| CI-core-libs | PASS | Same evidence as G13.1. |
| CI-core-libs-addons-only | PASS | No partial or full jQuery UI copy; core's `jquery-ui-dialog` styles are used (vergelabs-media-library.php:844). |
| CI-core-libs-noconflict | PASS | Every file using `$(` binds `$` itself: the `(jQuery)` IIFE in 13 files; a local `var $ = function(id)` in vergeml-ai.js:29, vergeml-ai-background.js:18 and vergeml-health.js:34; `var $ = window.jQuery` in vergeml-tree.js:2001/:2056. |
| CI-i18n-text-literal | PASS | `i18n.js`: 1,833 PHP gettext calls (`__`, `_e`, `_x`, `_ex`, `_n`, `_nx`, `_n_noop`, `esc_html__` and the rest), 0 with non-literal text. 277 JS `__`/`_x`/`_n` calls, 0 with non-literal text. |
| CI-i18n-context-literal | PASS | Non-literal context: 0. |
| CI-i18n-domain-literal | PASS | Non-literal domain: 0. |
| CI-i18n-domain-slug | PASS | All 2,110 calls use `'vergelabs-media-library'` = `Text Domain:` (:11) = slug. |
| CI-i18n-placeholders | PASS | Placeholder strings without a `translators:` comment within the preceding 300 characters: 0. Variables concatenated inside a gettext call: 0. |
| CI-active-plugins | PASS | `activate_plugin(s)\|deactivate_plugins\|update_option( 'active_plugins'`: 0. `active_plugins` is only read (get-help.php:107-110, system-report.php:128-135, options-pages.php:901). |
| CI-activation-interfere | PASS | Hooks on `activate_plugin`, `deactivate_plugin`, `activated_plugin`, `deactivated_plugin` or `option_active_plugins`: 0. `plugin_action_links_` only adds two links through `array_merge` (vergelabs-media-library.php:1510-1521). |
| CI-self-deactivate-ok | N/A | The plugin never deactivates itself (`deactivate_plugins` 0). |
| CI-plugin-dependencies | N/A | No required plugin. |
| CI-no-update-checker | PASS | Same evidence as G8.3. |
| CI-no-updater-interference | PASS | `auto_update_plugin\|wp_update_plugins\|automatic_updater_disabled\|DISALLOW_FILE_MODS`: 0. |
| CI-3p-disclose | PASS | Hosts contacted: ai.vergelabs.nl (`/describe`, `/embed`, `/folders`, `/plan-tree` and `/refund`, `/counts`, `/known-issues`, `/support/ticket`, activate, credits, the browser stream) and vergelabsmedia.com (`/api/trial`, connect). All are in readme.txt:53-71. |
| CI-3p-even-own | PASS | The plugin's own service is documented like any third party (readme.txt:51-77). |
| CI-3p-explain | PASS | For each request the readme says what is sent and when (readme.txt:57-71). |
| CI-3p-link-service | PASS | The service URLs are at readme.txt:53. |
| CI-3p-link-terms | PASS | Terms and privacy at readme.txt:77, DPA, sub-processors and retention at :75: all HTTP 200 on 2026-10-12 (`links.mjs`). |
| CI-unneeded-devtools | PASS | `.github`, `.vscode`, `.idea`, `bin/`, `tools/`, Gruntfile, gulpfile, `phpcs.xml`, `.editorconfig`, `.eslintrc`: 0. |
| CI-unneeded-vendor | PASS | `node_modules`, `bower_components`, `vendor`: 0. |
| CI-unneeded-demos | PASS | `demo/`, `examples/`, `sample/`: 0. |
| CI-unneeded-tests | PASS | `tests/`, `*.spec.js`, `*.test.js`, `phpunit*`: 0. |
| CI-keep-composer-json | N/A | No Composer or npm build. |
| CI-not-permitted-files | PASS | Extensions present: php, js, css, txt, md, pot, xml, gif, woff2, woff, ttf, eot, svg. exe, dll, so, sh, bat, phar, zip, tar, sql, log, env, `.git`, `.DS_Store`, `.htaccess`: 0. Hidden files: 0. |
| CI-premium-source | PASS | No commercial library or font (Inter OFL; webbistro from free EML). |
| CI-gpl-non-compatible | PASS | Same evidence as G1.2. |
| CI-gpl-source-for-compressed | PASS | 0 minified or compressed files. The two de-minified files are G4.2's question. |
| CI-gpl-source-link-in-readme | PASS | readme.txt:21. |
| CI-gpl-build-public | N/A | No compiled files. |
| CI-gpl-build-directions | N/A | No build tools needed to run or modify. |
| CI-composer-json | N/A | No `vendor/autoload.php`. |

Counts: **G** (114 rows): PASS 83, FAIL 3, RUNTIME 1, N/A 27. **CI** (120 rows): PASS 102, FAIL 2, RUNTIME 1, N/A 15. A script compared these IDs against the rulebook: 234 of 234 are covered, with no extras and no duplicates.

## FAIL

### FAIL 1: G4.2, G4.3: two files are pretty-printed minifier output (likely question)

> "Obscuring code by hiding it with techniques or systems similar to … uglify's mangle, or unclear naming conventions such as `$z12sdf813d`, is not permitted in the directory." (G4.2, G4.3)

**`js/vergeml-taxonomies-options.js`** (525 lines)

- **Header.** `:4` "Recovered from eml-taxonomies-options.min.js … Formatting restored with Prettier, outer-closure identifiers renamed". Only the outer closure was renamed.
- **Single-letter bindings.** 43 of 45 declared bindings are one letter, for example `:258` `var t`, `:308-310` `var t … l … i`, and `:425-434` `l`, `i = !0`, `u`, `a`.
- **`!0`/`!1` booleans** at `:187, 280, 282, 293, 304, 328, 349, 432, 456, 459, 461, 463, 465, 480, 489, 515`.
- **Nested ternaries as control flow** at `:436-466`: `n ? o ? m \|\| s ? m ? s ? (…) && ((i = !1), …) : ((i = !1), (a = …))`.

**`js/vergeml-media-views.js`** (944 lines)

- **Header.** `:4` "Recovered from eml-media-views.min.js". `:33` still reads "the minifier reused one variable for these three unrelated things".
- **Single-letter bindings.** 38 of 55, for example `:52-53` `var e = this.get('library'), t = this.get('selection')`, `:60-61`, `:84`.
- **`!0`/`!1`** at `:87, 89, 90, 102, 217, 471, 861`.
- **Comma-chained statement groups:** 30, for example `:36-58` and `:384-387`.

`mangle.js` puts these two files at 96% and 69% single-letter bindings. Every other JS file in the plugin is at 25% or below, where the single letters are `e` for event and similar callback parameters.

**Fix: finish the recovery.**
- Rename every remaining one-letter binding to what it holds.
- Replace `!0`/`!1` with `true`/`false`.
- Unfold `(a, b, c)` comma chains and the nested `?:` control flow into statements and `if`s.
- Keep the headers' provenance note, but say the file is now the maintained source.

No behaviour changes.

### FAIL 2: G12.8: one term repeated across the tags (low; rarely sent back)

> "Repetitive use of a tag or specific term is considered to be keyword stuffing, and is not permitted." (G12.8)

**Instance.** readme.txt:3 `Tags: media library, media folders, alt text, accessibility, media categories`. "media" appears in three of the five tags; the rulebook's own test for this row is "same term in several tags".

**Fix.** `Tags: media library, folders, alt text, accessibility, categories`, or any set where "media" appears once.

### FAIL 3: CI-sanitize-input: three REST intake points use raw input (likely question, not exploitable)

> "Data that is input (either by a user or automatically) must be sanitized as soon as possible." (CI-sanitize-input; the hint lists `php://input` and `$request->get_param(`)

- **The admin-ajax bridge.**
  - `core/transport.php:64` reads `file_get_contents( 'php://input' )`, then `json_decode` at `:65`.
  - `:83` takes `$body['params']` whole, and `:87-93` hands every key to `WP_REST_Request` unsanitized. Sanitizing is left to each route's `args`.
  - 28 string args on 20 handlers declare no `sanitize_callback`.
  - The round-2 note in wordpress-org-submission.md says the bridge takes "only each route's declared arguments". This archive does not.
- **The Folders screen's proposal.**
  - `core/folder-talk.php:2867-2878` keeps `history[].text` with `substr` only.
  - `:2882` passes `instruction` with a `(string)` cast only.
  - Both go into the service payload at `:469-470`. The route args at `:2774/:2777` have no `sanitize_callback`.
- **The CSV import.**
  - `core/import-ui.php:85` parses the uploaded text. Cells are only trimmed (`core/import-csv.php:309-310`, path parts `:421-422`).
  - They are stored raw as folder names (`:455` `'name' => $part`) in the option written at `:525` (`update_option( VERGEML_CSV_OPTION, $parsed )`).
  - A raw cell is echoed back in a problem line (`:344-347`).
  - The output is safe: the JS uses `textContent` (js/vergeml-import.js:114-116), and the terms go through core's `pre_term_name` sanitizers. Storage is still unsanitized.

**Fix.**
- **(a)** In the bridge, after `vergeml_transport_is_ours()`, keep only the matched handler's declared args: `array_intersect_key( $params, $handler['args'] )`. The REST server's sanitize and validate callbacks then become the intake. This also closes FAIL 4's bridge instance.
- **(b)** Add `'sanitize_callback' => 'sanitize_textarea_field'` to `instruction` (folder-talk.php:2774), and run `sanitize_textarea_field()` on each history text at :2877.
- **(c)** In `vergeml_csv_folder()`, `sanitize_text_field()` each path part. In the problem line, `sanitize_text_field( $raw )`.
- **(d)** Cheap class-wide close: add a `sanitize_callback` to every `'type' => 'string'` REST arg that has none (list from `rest-args.js`):
  - **AI:** `/ai-run` action, scope; `/ai-index` scope; `/ai-settings` license_key, site_profile
  - **Folders:** `/folder` name, color; `/folders-propose` instruction, mode; `/folders-apply` plan_id; `/autofile-act` action
  - **Guide and commands:** `/guide/rule` rule; `/guide/answer` id, answer; `/say-plan` text
  - **Health and quarantine:** `/health-uses` ids; `/health-keep-undo` token; `/quarantine-act` action, reason
  - **Import and Librarian:** `/import` text, id; `/librarian-*` scheme
  - **Files and search:** `/file` title, alt; `/search-meaning` s; `/search-try` s

  Today 26 of these 28 are sanitized, cast or allow-listed inside the handler.

### FAIL 4: CI-whole-input: the whole $_GET stack and the whole bridge body (likely question)

> "We strongly recommend you never attempt to process the whole $_POST/$_REQUEST/$_GET stack." (CI-whole-input)

- **The redirect loop.** `core/admin-menu.php:200-205` runs `foreach ( array_keys( $_GET ) as $key )` over every query key, keeping those matching `/^(vgml\|vergeml)[-_]…/`. It runs in `vergeml_admin_menu_redirects()` on `admin_init` and `admin_page_access_denied`.
- **The bridge.** `core/transport.php:83-93` passes every key of the request body's `params` on (FAIL 3).

**Fix.**
- **The redirect loop.** Replace the loop with an explicit list of the plugin's own notice keys, read with the same `isset` + `sanitize_text_field( wp_unslash() )`. The keys: `vgml_moved`, `vgml_to`, `vgml_renamed`, `vgml_renamed_back`, `vgml_files_renamed`, `vgml_files_back`, `vgml_alt_written`, `vgml_smart`, `vgml_meaning`, `vgml_unfiled`, `vgml_trial`, `vgml-help`, `vgml-check`, `vergeml_op`, `vergeml_done`, `vergeml_skipped`, `vergeml_term`, `vergeml_connected`, `vergeml_licence`, `vergeml_refresh`.
- **The bridge.** FAIL 3 fix (a).

## RUNTIME

1. **G7.1 (no outbound request without consent).** On a clean Playground site built from this archive's zip, with WP_DEBUG on and an mu-plugin logging every `pre_http_request` and `http_api_debug`:
   - **Without a licence.** Activate, then open every plugin screen and core's Media, Upload, Posts, Plugins and Dashboard as administrator. Expect 0 requests to `ai.vergelabs.nl` or `vergelabsmedia.com`, and none on activation.
   - **With demo mode.** Expect 0.
   - **With a licence connected.** Expect only the credits read on opening a VergeLabs screen (at most once per 5 min; every time on Licence).
   - **Folders screen.** Opening it fires no model route.

   The last proof (68/68, 15 requests, all core's) was on `a47d6f0`. This archive is `5dc983a`, so the walk must run again.
2. **CI-readme-validate.** Paste readme.txt into https://wordpress.org/plugins/developers/readme-validator/. Expect no errors or warnings. Watch the Markdown `##`/`###` headings and the `###` question inside the FAQ at :125. Plugin Check's `plugin_readme` check on the zip is the second witness.
3. **G16.1 (working plugin).** The `activation` suite (`node tools/verify.mjs activation`) against this zip: no output, no notice, no HTTP request, four tables, version stamped. Also a WP_DEBUG activation, single site and Network Activate.
4. **CI-main-file-name (packaging).** `unzip -l` the built zip: the first entry must be `vergelabs-media-library/` and the main file `vergelabs-media-library/vergelabs-media-library.php`. The extracted copy audited here is flat. The last dist zip (`a47d6f0`) had the folder; the zip for `5dc983a` has not been checked.

## Outside this share, still open from review 6 (one line each, for the owners)

- **Safe mode and no-mbstring term counts (HB/DEV activation rows).** Terms registered while `vergeml_features_off()` is true still name count callbacks that live in a file not loaded then:
  - `update_count_callback => 'vergeml_update_attachment_term_count'` at vergelabs-media-library.php:660
  - `$wp_taxonomies[…]->update_count_callback = 'vergeml_update_post_term_count'` at :768

  Both callbacks are defined in core/taxonomies.php:1550/:1582, which safe mode and missing mbstring do not load. On PHP 8 a term-count update then ends in a fatal callback error. Guard both with `function_exists()`, or skip them when `vergeml_features_off()`.
- **Media-modal CSS scope (HB-75, review 6 LQ1).** It is unchanged: the `.eml-media-css` body class (js/vergeml-media-views.js:943).
