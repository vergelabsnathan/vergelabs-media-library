# Round 6 audit: HB rows (Plugin Review Team handbook), VergeLabs Media Library 4.1.0

Archive judged: `C:/Users/viete/AppData/Local/Temp/vgml-r6-archive` (147 files: 68 PHP, 43 JS, 22 CSS; dirs `core css fonts images js languages`; no hidden files). Paths below are relative to it. Every `phpcs:ignore` / `phpcs:disable` was ignored; code under each was read. No PHP binary exists on this machine, so nothing was executed; rows that only a running site can prove are RUNTIME.

Greps were run from the archive root (`grep -rnE … --include=*.php .`, likewise `js/`, `css/`). Two scanners were run from a separate folder against the archive: `vgml-r6-scratch/echo.js` (classifies every PHP output operand) and `vgml-r6-hb-scratch/safemode.js` (finds references from the files that load in safe mode to functions defined only in guarded files). "Process" means the row describes reviewer or account conduct, not something in the archive.

Roll-up rows (HB-12, 29, 35, 200, 201) are FAIL because the specific rows they summarise are FAIL. They need no work of their own.

| ID | verdict | evidence |
|---|---|---|
| HB-1 | N/A | Process: reviewers apply the guidelines literally. |
| HB-2 | N/A | Process: closure reasons go only to the owner. |
| HB-3 | N/A | Process: conduct. |
| HB-4 | N/A | Process. This audit searched by class. |
| HB-5 | PASS | readme.txt:3 has 5 tags, all describing features: `media library, media folders, alt text, accessibility, media categories`. Third-party names in the readme (readme:35 importer sources, readme:129-135 compatibility log) each have code behind them. A grep of `core/` per product found integration files for all 16 names, e.g. `core/import-sources.php`, `core/seo-context.php`, `core/page-builders.php`. |
| HB-6 | PASS | `find . -iname "*jquery*"` gives 0 files. `wp_deregister_script` gives 0 hits. Deps use core handles: `'jquery'`, `'jquery-ui-dialog'` (vergelabs-media-library.php:851), `'jquery-ui-draggable'`, `'jquery-ui-droppable'` (core/tree-ui.php:165). |
| HB-7 | N/A | Process. |
| HB-8 | N/A | Process. |
| HB-9 | N/A | Process. |
| HB-10 | N/A | Process. This audit covered all 147 files. |
| HB-11 | N/A | Process: account vetting. |
| HB-12 | FAIL | Roll-up. Subject is fine and SQL, nonces, capabilities and escaping pass, but HB-60/79 (sanitizing) and HB-57/58 (a reachable fatal) fail. |
| HB-13 | N/A | Process. |
| HB-14 | N/A | Process. |
| HB-15 | PASS | `find . -type f` gives 147 files and `find . -name ".*"` gives 0. No `.git`, `.env`, `node_modules`, `tests`, `tools`, `docs`, `vendor`, `composer.*` or `package.json`. No JS or CSS line is over 400 characters (awk over js/*.js and css/*.css), so nothing is minified. |
| HB-16 | N/A | Process. |
| HB-17 | PASS | `grep -rnE "wp-(con\|blog\|load)"` gives 21 hits, all in comments, strings or readme. None is an include. Every `require`/`include` is either `plugin_dir_path( __FILE__ )` or `ABSPATH . 'wp-admin/includes/…'` (9 hits, e.g. core/ai-index.php:121). |
| HB-18 | PASS | Write-op grep (`file_put_contents\|fopen\|fwrite\|mkdir\|copy\|rename\|move_uploaded_file\|put_contents\|->save\|->open`): core/ai.php:1078-1089 (`wp_tempnam` plus image editor save, deleted on both paths); core/folder-tools.php:148/243-262 (`wp_tempnam` ZIP, deleted on error and after streaming); core/import-csv.php:228/258 (`php://output`, `php://temp`); core/rename-file.php:388 (`WP_Filesystem->move` inside uploads, feature gated); core/health.php:183 (read-only `fopen 'rb'`). Nothing targets `plugin_dir_path`, `__DIR__` or `WP_PLUGIN_DIR`. `WP_PLUGIN_DIR` is used only for `get_plugin_data` reads (core/get-help.php:114, core/system-report.php:129/136). |
| HB-19 | PASS | Same as HB-6. Header scan of all 43 JS files: every file is the plugin's own or EML's. The only third-party code is a 40-line natural-compare-lite function with its notice (js/eml-media-models.js:262-268). |
| HB-20 | PASS | `grep -rnEi "cdn\.\|bootstrapcdn\|cdnjs\|jsdelivr\|unpkg\|googleapis\|code\.jquery\|gstatic\|fonts\.google"` gives 0. Enqueues with an `http` src give 0. Every enqueue/register src is `plugins_url(…, VERGEML_FILE)` or `$vergeml_dir` (`plugin_dir_url( __FILE__ )`, vergelabs-media-library.php:520). Every CSS `url()` is `../fonts`, `../images` or `data:`. |
| HB-21 | PASS | Plugin URI `https://vergelabsmedia.com`, Author URI `https://vergelabs.nl` (vergelabs-media-library.php:4,10). No author domain contains "wordpress". |
| HB-22 | N/A | Process. |
| HB-23 | N/A | Process. |
| HB-24 | N/A | Process. |
| HB-25 | N/A | Process. |
| HB-26 | N/A | Process. |
| HB-27 | PASS | Name "VergeLabs Media Library" (main:3, readme:1). The first term is the owner's brand and the subject is media management. |
| HB-28 | N/A | Process. The file list was checked under HB-15. |
| HB-29 | FAIL | Roll-up. See the FAIL section. |
| HB-30 | PASS | readme:51-77 (External services) says what the service is, that a licence or free trial key is needed, and where to get one (readme:65-67, :83). |
| HB-31 | RUNTIME | No PHP here. See the RUNTIME section. |
| HB-32 | N/A | Process. |
| HB-33 | N/A | Process. |
| HB-34 | N/A | Process: forum and community conduct. |
| HB-35 | FAIL | Roll-up. 100% adherence is not met: see the FAIL section. |
| HB-36 | PASS | `Tested up to: 7.1` (readme:5). api.wordpress.org/core/version-check/1.7 returned 7.1.3 on 2026-10-12, so 7.1 is the current major. Requires at least 6.5 (main:7, readme:4). A grep for 40 deprecated WP functions and deprecated jQuery methods gives 0 (`_.bind` and plupload `.bind` are not jQuery). |
| HB-37 | PASS | Fork of EML 2.9.4 (`research/enhanced-media-library.2.9.4`). EML has 32 files, 6,233 PHP lines and 2,217 JS lines. The archive has 147 files, 53,570 PHP lines and 19,055 JS lines. Only 7 files are byte-identical (sha1): the 4 webbistro fonts, 2 spinners and index.php. readme:15-49 describes what is new. |
| HB-38 | PASS | Author VergeLabs (main:9), Contributors `vergelabsdev` (readme:2). EML credited at main:16-19, readme:19, readme:119 and core/options-pages.php:3058. |
| HB-39 | PASS | Images in the ZIP are images/spinner.gif and spinner-2x.gif (byte-identical to EML's) and the plain menu SVG (core/admin-menu.php:109). No banners or icons are in the ZIP (they go in SVN assets). |
| HB-40 | PASS | `wp_insert_post\|wp_insert_comment\|wp_new_comment` gives 0. `wp_update_post` is used only on user action (core/health-delete.php:196,216). |
| HB-41 | PASS | `grep -niE "guarantee\|[0-9]+ ?%\|[0-9]+x \|earn\|boost\|rank\|best\|#1\|fastest\|ultimate\|revenue\|traffic" readme.txt` gives 4 hits, all harmless: "Unused", "describing a picture", and 2 product names (Yoast SEO, Rank Math SEO). |
| HB-42 | PASS | A working end-user plugin with no "framework" or "library" positioning. |
| HB-43 | PASS | readme Installation (:79-85) tells nobody to edit a theme or plugin. `get_template_directory\|get_stylesheet_directory` gives 0. |
| HB-44 | PASS | Not a storefront. Buy links appear only on the plugin's AI and Licence screens (core/ai-screen.php:412, core/offer.php:65/133 via ai-screen.php:277/402, core/licence-page.php:154, core/connect.php:281). |
| HB-45 | PASS | Extends core media with folders, taxonomy filters and AI text, and readme:15-49 states the additions. The only shortcode is `vergeml_gallery` (core/gallery-widgets.php:117). `shortcode_atts_gallery` is filtered to add taxonomy queries, not replaced (core/medialist.php:8). |
| HB-46 | PASS | No raw code fields. `site_profile` goes through `sanitize_textarea_field` (core/ai.php:2000). The MIME screen refuses executable and browser-interpreted types (readme:48, core/mime-types.php). `eval\|create_function\|assert\|exec\|system\|passthru\|shell_exec\|popen\|proc_open\|unserialize\|extract` gives 0. |
| HB-47 | N/A | Process. |
| HB-48 | PASS | `License: GPLv2 or later` (main:13, readme:8). Licence grep (`MIT\|Apache\|BSD\|CC-BY\|All rights reserved\|proprietary\|@license\|Copyright`) gives 3 hits: natural-compare-lite MIT (js/eml-media-models.js:268, GPL-compatible) and the two copyright lines (main:19-20). fonts/INTER-LICENSE.txt is SIL OFL 1.1, accepted for fonts. No composer.json or package.json ships. |
| HB-49 | PASS | readme:8-9 `License: GPLv2 or later` and `License URI: http://www.gnu.org/licenses/gpl-2.0.html`. |
| HB-50 | PASS | Bundled resources are declared in readme:117-123: EML and its webbistro font (GPLv2+), Inter (OFL 1.1, fonts/INTER-LICENSE.txt:1 "Copyright 2020 The Inter Project Authors"), natural-compare-lite (MIT), the WP Rocket stylesheet layout (GPLv2+) and the WP spinners (GPLv2+). Note: the rule says "plugin header". The rulebook accepts the readme, but a literal reviewer may ask, and one comment line in main:16-20 would answer it. |
| HB-51 | PASS | Copied material is credited: natural-compare (js/eml-media-models.js:262-268) and the WP Rocket layout (readme:122, css/vergeml-shell.css header). |
| HB-52 | FAIL | The original header line `Copyright 2013-2024 wpUXsolutions  (email : wpUXsolutions@gmail.com)` (EML enhanced-media-library.php:13) became `Copyright 2013-2024 wpUXsolutions  (original work)` at vergelabs-media-library.php:19. Part of the copyright notice was removed. |
| HB-53 | PASS | readme:83 says where to find the plugin and the licence. FAQ:89-115 covers multisite, outages, safe mode and uninstall. |
| HB-54 | FAIL | External services (readme:51-77) does not disclose the "How it describes" brief on the AI screen. (1) Every describe request sends `profile` = the site brief (core/ai.php:568). (2) The brief conversation mints a token with a library summary (core/brief.php:342 → core/guide.php:917 `/guide/session`: kinds counted, the 25 most frequent AI tags with counts) and streams from the browser to `/brief/stream` with the brief, draft, typed input and conversation (js/vergeml-brief.js:145-147), so the user's IP reaches the service. (3) The brief test describes 5 pictures with a draft brief (core/brief.php:413-425). Also, licence activate/check send `environment` (core/ai.php:115, :175), but readme:67 says only "the key and your site's address". `grep -niE "profile\|brief\|how it describes" readme.txt` gives 0. |
| HB-55 | PASS | readme:75-77 links terms, privacy, sub-processors, retention and DPA on vergelabsmedia.com. Both hosts (ai.vergelabs.nl, vergelabsmedia.com) belong to the same operator. |
| HB-56 | PASS | readme:109-111 says where to report problems and gives support terms for licence holders. |
| HB-57 | FAIL | A reachable PHP fatal in safe mode or without mbstring (vergelabs-media-library.php:660, :768). See F1. JS console errors are RUNTIME. |
| HB-58 | FAIL | The same fatal. On PHP 7.4 it is a `call_user_func` warning under WP_DEBUG. See F1. A full WP_DEBUG walk is RUNTIME. |
| HB-59 | RUNTIME | Query Monitor walk: see the RUNTIME section. |
| HB-60 | FAIL | Superglobals pass (134 reads, see HB-142). PHP output passes (see HB-80). Free-text REST input is stored or forwarded without `sanitize_*`: core/import-csv.php:455/:525 (CSV folder names saved raw to an option), :346 (raw cell value into a message); core/folder-talk.php:2877/:2882 (`history[].text` and `instruction` forwarded raw); core/rest-folders.php:193 (`name`). See F4. |
| HB-61 | PASS | No global function lacks `vergeml_`: `grep -rhoE "^\s*function\s+…"` finds only walker methods (start_el/end_el/start_lvl/end_lvl). Classes are `vergeml_Walker_*` ×4 and `VergeML_*_Gallery` ×2. Every `define`/`const` is `VERGEML_`. Options and transients are `vergeml_*`; the only non-prefixed keys are core options and EML's `wpuxss_eml_*`, read for migration (main:471-493). Handles are `vergeml-*` plus core `media-grid`/`wp-plupload`. AJAX and admin-post actions are `vergeml*`, the REST namespace is `vergeml/v1`, the block is `vergelabs/folder-gallery`, globals are `$vergeml_*`, JS globals are `window.vergeml*`/`vgml*`. One exception: the `media_category` taxonomy (main:1193). It is acceptable as an interop name shared with EML, documented at readme:27,105, and uninstall now keeps it (uninstall.php:66). Expect a question; have the one-line answer ready. |
| HB-62 | PASS | readme header complete (:1-9). `Stable tag: 4.1.0` = `Version: 4.1.0` (main:6) = `VERGEML_VERSION` (main:43). Short description is 92 characters (:11). Description plus External services is 1,868 words (under 2,500). |
| HB-63 | PASS | Same as HB-18. `vergeml_asset_ver` only stats files (main:59,77). |
| HB-64 | PASS | All HTTP goes through `wp_remote_post/get` (21 calls). `curl_\|fsockopen\|file_get_contents('http` gives 0. SQL goes through `$wpdb`. The REST fallback over admin-ajax dispatches through core `rest_do_request` and each route's own permission_callback (core/transport.php:33-117). Expect a question (review6 LQ9); have an explanation ready. Settings import reads `is_uploaded_file()` tmp only and saves through the `register_setting` validators (core/options-pages.php:1295, :1336-1340). |
| HB-65 | PASS | `wp-content` appears only in comments or translators notes (core/import-ui.php:49, core/neighbours.php:168, js/vergeml-import.js:498). Paths come from `plugin_dir_path`/`plugins_url`/`wp_tempnam`/`get_attached_file`. |
| HB-66 | PASS | `ob_start` ×2 only capture `wp_terms_checklist` output for JS (main:1003, core/taxonomies.php:1003). Post content and meta are written through `wp_update_post` / `update_metadata_by_mid` (core/health-delete.php:196,208,216), so review6 LQ5 is fixed. Term-count `$wpdb->update` mirrors core's callback and fires core's hooks (core/taxonomies.php:1568,1627). |
| HB-67 | PASS | Same as HB-45. |
| HB-68 | PASS | Tags (readme:3) and the short description (:11) match the code. Both contacted hosts are named (readme:53). |
| HB-69 | FAIL | Requirements are checked (main:1581-1594, notice :1601-1611), and without mbstring the guarded files stay unloaded (:1634). But the main file still registers count callbacks from an unloaded file (:660, :768), so the graceful path ends in a fatal on the first term-count update. See F1. ZipArchive (core/folder-tools.php:136), Elementor, Divi and Dokan (core/gallery-widgets.php, core/page-builders.php:302) are guarded. |
| HB-70 | PASS | Options carry help bubbles (js/vergeml-help.js, core/help.php). Limits are in the readme: credits (:149,157), admin-only work (:168), file types (:48,169). |
| HB-71 | FAIL | The remote-call inventory (`wp_remote_*` ×21: 18 service calls, 2 trial/connect calls, 1 loopback `wp-cron.php` at core/ai-background.php:220; JS `fetch` to the service at js/vergeml-talk.js:398) against readme:51-77: the brief paths are undisclosed. See HB-54 and F3. |
| HB-72 | PASS | Registration is explained in the readme (:65-67,:83) and on the Licence screen (core/licence-page.php:142-154 "Not connected…", :224-225 "From the licence tab of your account at vergelabsmedia.com", connect banner core/connect.php:275-283). |
| HB-73 | PASS | `Theme Name:\|switch_theme\|Theme_Upgrader` gives 0. |
| HB-74 | PASS | One `Plugin Name:` header in the ZIP (main:3). `activate_plugin\|deactivate_plugins\|Plugin_Upgrader\|install_plugin` gives 0. |
| HB-75 | PASS | Front-end hooks: only `wp_enqueue_scripts` (main:101 RTL marking; core/page-builders.php:160 Divi builder). No `template_include`/`template_redirect`/`the_content`. css/vergeml-gallery.css: all 44 rule blocks are under `vgml`, and keyframes are `vgml-*`. The admin media-modal CSS scope (`body.eml-media-css`, js/vergeml-media-views.js:943) is unchanged since review6 LQ1. That belongs to CI-enqueue-admin, not to theme territory. |
| HB-76 | PASS | Same as HB-43. |
| HB-77 | RUNTIME | Statically clean. No remote call on activation, `admin_init` or cron without user action: the stats sender is opt-in and off by default (core/instrument.php:47, :255; `opted` is written only by the REST toggle at :288). The credit check needs a stored key (core/ai.php:157). Known-issues is fetched only with the nonce'd button (core/get-help.php:289,73). The Folders and brief token is minted only on a turn (js/vergeml-talk.js:317-323, :397). An outbound walk still has to confirm it. |
| HB-78 | PASS | `vergeml_stats` defaults `opted => 0` (core/instrument.php:47). Nothing sends before `opted` (core/instrument.php:189, :255). |
| HB-79 | FAIL | Same instances as HB-60. See F4. |
| HB-80 | PASS | echo.js classifier: 268 PHP output statements, 3 flagged, all ternaries whose branches are escaped (core/admin-shell.php:740, core/journey.php:1311, core/options-pages.php:1146; read). `_e(`/`_ex(` raw gives 0. printf calls (31) checked, with escaped arguments (e.g. core/connect.php:275). Non-empty JS `innerHTML` sinks: 15, all constant SVG or entity markup (e.g. js/vergeml-tree.js:773). jQuery concat sinks (js/eml-admin.js:97, js/eml-media-grid.js:83, js/eml-media-list.js:17/22, js/vergeml-media-views.js:385) carry only localized `__()` strings. |
| HB-81 | PASS | `bit\.ly\|goo\.gl\|t\.co/\|tinyurl\|ow\.ly\|is\.gd\|buff\.ly` gives 0. |
| HB-82 | PASS | 209 `$wpdb->query/get_*` calls. All use `$wpdb->prepare` with `%i`/placeholders, or are literal SQL naming only `$wpdb` core tables (core/ai-screen.php:117,119; core/guide.php:270,366; core/health.php:493; core/import-ui.php:230-231; core/instrument.php:79,85; core/smart-folders.php:879,983; core/options-pages.php:1703,1756; uninstall.php:36,126,134,136,177,206,210). `mysqli_\|mysql_\|PDO` gives 0. |
| HB-83 | PASS | Upsell and buy links appear only on the plugin's own AI and Licence screens (see HB-44). `plugin_row_meta` has Docs/Support (main:1550-1551). The 8 `admin_notices` callbacks are results, safe-mode or requirements notices, or the neighbour warning limited to media and own screens (core/neighbours.php:85-91). None is an upsell. |
| HB-84 | PASS | Front-end output (gallery block, shortcode, widgets): `powered by\|vergelabsmedia\.com\|vergelabs\.nl` gives 0 in core/gallery-*.php, js/vergeml-gallery.js and css/vergeml-gallery.css. |
| HB-85 | PASS | No sponsored links: 0 third-party commercial links in readme or admin screens. |
| HB-86 | PASS | `adsbygoogle\|googlesyndication\|doubleclick` gives 0. |
| HB-87 | PASS | `[?&](ref\|aff\|affiliate)=` gives 0. |
| HB-88 | PASS | UTM only on the developer's own domain (`vergeml_buy_url`, core/connect.php:51-59). |
| HB-89 | PASS | `<(script\|link\|style\|iframe)` in PHP gives 3 hits: core/media-templates.php:19,:93 (`type="text/html"` Underscore templates, as core ships them) and core/neighbours.php:196 (`script_loader_tag` on the plugin's own handles). `wp_add_inline_script` gives 0. `wp_add_inline_style` gives 2, both `wp_strip_all_tags`-wrapped with sanitized selectors (core/admin-menu.php:88, core/media-list.php:300). |
| HB-90 | PASS | `google-analytics\|gtag(\|googletagmanager\|hotjar\|mixpanel\|segment\|sentry\|fbq(\|plausible\|matomo\|clarity\|posthog\|amplitude` gives 1 hit, a false positive ("plausible" in a comment, core/ai.php:187). |
| HB-91 | PASS | No `*.min.*` files, and no line over 400 characters in any JS or CSS. The two upstream minified files were recovered to source (js/vergeml-media-views.js:1-5, js/vergeml-taxonomies-options.js header). |
| HB-92 | PASS | `eval(\|new Function\|p,a,c,k,e` gives 0. `base64_decode` ×2 is data, not code: licence seal at core/ai.php:355 and packed floats at core/organize.php:2403. |
| HB-93 | PASS | Same as HB-6/19. |
| HB-94 | PASS | Same as HB-20. All scripts, styles, fonts and images are local. |
| HB-95 | RUNTIME | Needs an axe and keyboard walk. Static notes: the file input at core/options-pages.php:507 has no label, and the front-end gallery's aria-labels are English-only (see F5). |
| HB-96 | FAIL | Hard-coded English user-facing strings: js/vergeml-gallery.js:42,43 (`'Previous images'`, `'Next images'`), :123-125 (`aria-label="Previous image"/"Next image"/"Close"`). The script is registered with no `wp-i18n` and no localization (core/gallery-block.php:46-52). Also core/transport.php:68,80 (`'The bridge needs a JSON body.'`, `'Not one of ours.'`). Text domain otherwise consistent: 1,770 calls, all `vergelabs-media-library`. languages/vergelabs-media-library.pot is stale (Project-Id 3.13.2, POT date 2026-09-01; contains removed strings, e.g. "Store uploads in this folder"). |
| HB-97 | PASS | `Requires PHP: 7.4` (main:8). PHP 8-only syntax: `match(` 0, `?->` 0, enums/readonly 0, union/mixed/never types 0. `str_contains` ×3 is polyfilled by WP ≥5.9 (floor 6.5). |
| HB-98 | PASS | No required plugin. Every integration is guarded: `class_exists('ET_Builder_Module')` (core/gallery-widgets.php:409), `function_exists('dokan_is_seller_dashboard')` (core/page-builders.php:302), `class_exists('WooCommerce')` (core/ai.php:449). |
| HB-99 | FAIL | Transport is fine. The AI service URL is forced to https (core/ai.php:313-318, core/get-help.php:189). `sslverify` is never false (15 explicit `true`). Keys travel in the JSON body or an `Authorization` header, never a URL (`add_query_arg` with key gives 0). Not fine: the outgoing payload carries unsanitized free text (core/folder-talk.php:2877,2882). See F4. Note: `VERGEML_SITE_URL`, `VERGEML_AI_STREAM` and `VERGEML_KNOWN_ISSUES_URL` overrides are not https-checked (core/connect.php:39, core/guide.php:856, core/get-help.php:58). These are wp-config only. |
| HB-100 | PASS | readme:53-71 covers usage, registration (licence/trial), credits and pricing pointers. |
| HB-101 | PASS | `iframe` in PHP/JS gives 1 hit, a comment (core/health-delete.php:182). |
| HB-102 | PASS | Same as HB-17. Every PHP file except index.php has an `ABSPATH` / `WP_UNINSTALL_PLUGIN` guard in its first 40 lines (scripted check over 68 files). |
| HB-103 | PASS | Slug, name and readme title start with the owner's brand. No third-party logos ship. |
| HB-104 | PASS | No remote config, notices or news feeds. Known issues are fetched only on the button and cached 12 h (core/get-help.php:64-95). The credit balance is refreshed at most every TTL, and only with a key (core/ai.php:150-163). |
| HB-105 | PASS | The plugin calls only its operator's own service. Third-party plugin data (Yoast/Rank Math/SEOPress/AIOSEO) is read locally (core/seo-context.php). |
| HB-106 | RUNTIME | Same evidence as HB-77/78: opt-in only, default off. Confirm with the outbound walk. |
| HB-107 | PASS | `<?=` and `<? ` (non-`<?php`) give 0. |
| HB-108 | PASS | Same as HB-48. |
| HB-109 | N/A | Process. |
| HB-110 | N/A | Process. |
| HB-111 | N/A | Process. |
| HB-112 | N/A | Process. |
| HB-113 | N/A | Process. |
| HB-114 | PASS | `add_shortcode` gives 1 (`vergeml_gallery`). No core shortcode or widget name is reused. |
| HB-115 | PASS | No parallel JSON API: the admin-ajax bridge re-enters core REST (core/transport.php:103). |
| HB-116 | PASS | Same as HB-44. |
| HB-117 | PASS | Same as HB-40. |
| HB-118 | PASS | Same as HB-41. |
| HB-119 | PASS | `wp_mail(` gives 0. |
| HB-120 | PASS | `wp_(insert\|create\|update)_user\|set_role\|add_cap\|add_role\|grant_super_admin\|wp_set_auth_cookie\|wp_set_current_user` gives 0. No eval or remote include (see HB-92). 86 `permission_callback` entries, all named `vergeml_*` functions. `__return_true` appears only as two UI filters (core/taxonomies.php:676, main:1566). |
| HB-121 | PASS | Subject: media folders and alt text. |
| HB-122 | PASS | Same as HB-121. |
| HB-123 | PASS | Not a 100% copy (see HB-37 figures). |
| HB-124 | N/A | Applies only to 100% copies. An adoption request was sent 2026-09-25 (outside the archive; per project memory). |
| HB-125 | N/A | Process. |
| HB-126 | PASS | Slug `vergelabs-media-library` = `Text Domain` (main:11). No trademark first term, no "wordpress" or "plugin". |
| HB-127 | PASS | Same as HB-27. |
| HB-128 | PASS | First term "VergeLabs" is the owner's brand. |
| HB-129 | PASS | Plugin URI, Author URI, readme contacts (`support@vergelabsmedia.com`, `security@in.vergelabs.nl`) and the GitHub source all point to VergeLabs. The account email domain is outside the archive. |
| HB-130 | N/A | Process. |
| HB-131 | N/A | Process: account `vergelabsdev`. |
| HB-132 | PASS | No typos in the name or slug. |
| HB-133 | PASS | Three words, no version number, no profanity. |
| HB-134 | PASS | `grep -iE "wordpress\|plugin\|theme"` on slug and Plugin Name gives 0. |
| HB-135 | N/A | Process. Covered by HB-134. |
| HB-136 | PASS | readme.txt is present (18,997 bytes). |
| HB-137 | PASS | Same as HB-53. |
| HB-138 | PASS | Same as HB-30/100. The service, how to join (Connect / trial) and why (AI features) are at readme:53-55,65-67. |
| HB-139 | PASS | Installation steps are complete and the menu names match the code ("VergeLabs Library" core/admin-menu.php:51; "Import Folders" core/import-ui.php:24; "Licence" core/licence-page.php:22). |
| HB-140 | N/A | Process. This audit grepped all 68 PHP and 43 JS files. |
| HB-141 | PASS | Same as HB-17: 21 hits, 0 includes. |
| HB-142 | PASS | `grep -rnE '\$_(POST\|GET\|REQUEST\|COOKIE\|SERVER\|FILES)'` gives 134 lines in 26 files. Each read is wrapped in `sanitize_key`/`sanitize_text_field`/`sanitize_textarea_field`/`sanitize_email`/`sanitize_file_name`/`absint`/`(int)`/`map_deep(…, 'sanitize_text_field'\|'intval')` after `wp_unslash`, or used only in `isset`/`empty`. State changes are preceded by nonce and capability (see HB-157/158). |
| HB-143 | PASS | Same as HB-61: 0 unprefixed global functions. |
| HB-144 | N/A | Process. phpcs annotations were ignored here. |
| HB-145 | PASS | No user-code execution. The file-read endpoint is limited to uploads (`vergeml_path_in_uploads`, core/folder-tools.php:181) and gated by `upload_files` plus a nonce (:227-233). |
| HB-146 | PASS | No PHP-in-content: `eval` 0, `php://` only for output/temp streams (core/import-csv.php:228,258). |
| HB-147 | PASS | 77 `register_rest_route` and 86 `permission_callback`, none open. Library-wide work requires `manage_options` (main:126-128). Object routes check `edit_post`/`delete_post`/term caps (core/autofile:690-697, core/health-delete.php:582-600, core/private-folders.php:222, core/rest-folders.php:91, core/rest-tree.php:228). The 16 `wp_ajax_`/`admin_post_` handlers all check a capability (scripted list). |
| HB-148 | PASS | uninstall.php:3-5 `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }` is the first statement. |
| HB-149 | FAIL | The opt-in wipe deletes attachment links of every foreign taxonomy the plugin auto-discovered: uninstall.php:96-106 and the Complete Cleanup at core/options-pages.php:1550-1590 (called at :1460). Discovery adds all taxonomies with object types (main:687-713, saved :774), e.g. Polylang `language`/`post_translations` and the HappyFiles, Premio or Wicked folder taxonomies. That is other plugins' data, against readme:107 ("they remove the plugin's own settings, tables and other data"). See F2. Otherwise sane: own options by `vergeml\_%`, own meta, own 4 tables `%i`, all 7 cron hooks, multisite via `get_sites( number 0 )`, `media_category` kept. |
| HB-150 | PASS | `register_deactivation_hook` gives 0, so nothing is deleted on deactivation. |
| HB-151 | PASS | Same as HB-61. |
| HB-152 | N/A | No shared library is bundled (see HB-175). |
| HB-153 | PASS | Same as HB-142 for POST/GET/REQUEST. |
| HB-154 | PASS | `stripslashes\|strip_tags\|htmlspecialchars\|htmlentities\|filter_input\|filter_var` gives 0. |
| HB-155 | FAIL | Same instances as HB-60. See F4. |
| HB-156 | FAIL | Same pattern as the rule's example: request data to `update_option` unsanitized. core/import-ui.php:85 `(string) $request->get_param( 'text' )` → `vergeml_csv_parse` → folder names `'name' => $part` (core/import-csv.php:455) → `update_option( VERGEML_CSV_OPTION, $parsed )` (:525). See F4. |
| HB-157 | PASS | Every form/AJAX/admin-post write has a nonce: `check_admin_referer`/`check_ajax_referer`/`wp_verify_nonce` in all 15 write handlers (scripted list), and in the admin_init handlers (core/options-pages.php:1219,1264,1366,1419,1443; core/neighbours.php:130; core/connect.php:121). REST uses core's cookie nonce, and the bridge verifies `wp_rest` (core/transport.php:46). |
| HB-158 | PASS | Every nonce'd handler also checks `current_user_can` (see HB-147, HB-157). |
| HB-159 | PASS | Same as HB-6/19/20. |
| HB-160 | PASS | Same as HB-20/94. |
| HB-161 | PASS | Core handles or `plugins_url` files only (see HB-20). |
| HB-162 | FAIL | Remote loads are only to the service, but the browser stream for the brief is undisclosed (js/vergeml-brief.js:145). See F3. |
| HB-163 | PASS | Same as HB-20. |
| HB-164 | PASS | Same as HB-101. |
| HB-165 | PASS | No remote `<img>` or CSS `url(http`. The only `http` in CSS is the SVG xmlns inside `data:` URIs. |
| HB-166 | N/A | Fonts are local (css/vergeml-shell.css:41,50). |
| HB-167 | PASS | Service calls use `wp_remote_post`. The disclosure gap is tracked at HB-54/71. |
| HB-168 | N/A | No content offloading. |
| HB-169 | N/A | No oEmbed use. |
| HB-170 | PASS | Same as HB-18/63. |
| HB-171 | PASS | No persistent file writes outside uploads. Temp files use `wp_tempnam`. |
| HB-172 | PASS | Temp files are deleted on success and error paths (core/ai.php:1085-1089; core/folder-tools.php:205-208,247-250,262). |
| HB-173 | N/A | No bundled library. |
| HB-174 | N/A | No bundled library. |
| HB-175 | PASS | `timthumb\|csstidy\|titan-framework\|TitanFramework\|magnific\|MVC2` gives 0. |
| HB-176 | N/A | No Bootstrap-type library. |
| HB-177 | N/A | Process. |
| HB-178 | PASS | Same as HB-48. |
| HB-179 | N/A | Process. |
| HB-180 | PASS | Same as HB-128. |
| HB-181 | N/A | Process. |
| HB-182 | N/A | No third-party product in the name. readme:19 already states it is not affiliated with wpUXsolutions. |
| HB-183 | N/A | Process. |
| HB-184 | N/A | Process. |
| HB-185 | PASS | No capability is widened beyond core's. Writes ask core's own caps (see HB-147). |
| HB-186 | N/A | Process. |
| HB-187 | N/A | Process. |
| HB-188 | N/A | Process. |
| HB-189 | N/A | Process. |
| HB-190 | N/A | Process. |
| HB-191 | PASS | `support/plugin/[a-z-]+/reviews\|leave a review\|rate (us\|this plugin)\|5[- ]star\|review us` gives 0. |
| HB-192 | PASS | Same as HB-191. |
| HB-193 | N/A | Process. |
| HB-194 | N/A | Process. |
| HB-195 | N/A | Process. |
| HB-196 | N/A | Process. |
| HB-197 | N/A | Process. |
| HB-198 | N/A | Not yet in SVN. |
| HB-199 | PASS | `Version: 4.1.0` (main:6) = `VERGEML_VERSION` 4.1.0 (main:43) = `Stable tag: 4.1.0` (readme:7). |
| HB-200 | FAIL | Roll-up. See the FAIL section. |
| HB-201 | FAIL | Roll-up. See the FAIL section. |
| HB-202 | N/A | Covers fixes to listed plugins (SVN, version bump). The "pass a review today" part is HB-201. |
| HB-203 | N/A | Process. |
| HB-204 | N/A | Process. |
| HB-205 | PASS | No capability or nonce gap, no unescaped PHP output, no unprepared SQL, and no open write endpoint (HB-80/82/147/157). |
| HB-206 | PASS | GPL-compatible throughout (HB-48). No licence key gates local code: every licence check guards a service request (`vergeml_ai_unseal` → service calls; folders, smart folders, importer, galleries and the date/type Librarian need none, readme:55). |
| HB-207 | N/A | Process. |
| HB-208 | N/A | Process. |
| HB-209 | N/A | Process. |
| HB-210 | N/A | Process. |
| HB-211 | N/A | Process. |
| HB-212 | N/A | Process. |
| HB-213 | PASS | Same as HB-5. |
| HB-214 | PASS | No keyword stuffing or hidden text. Every product name in the readme has integration code (HB-5). |
| HB-215 | N/A | Process (SVN history). |
| HB-216 | N/A | Process. |
| HB-217 | N/A | Process. |
| HB-218 | PASS | `grep -rhoE 'global[[:space:]]+[^;]+;'`: no `$tag`. Non-prefixed globals used are core's only (`$wpdb`, `$pagenow`, `$current_screen`, `$wp_taxonomies`, `$wp_filter`, `$wp_query`, `$wp_locale`, `$wp_version`, `$_wp_admin_css_colors`). `$wp_filter` is used only to remove the plugin's own notice callbacks (core/admin-shell.php:488-503). |
| HB-219 | PASS | `wp_(enqueue\|register)_(style\|script)\s*\(.*["']https?://` gives 0. |
| HB-220 | PASS | `*datepicker*`, `*jquery*`, `*.min.*`, underscore, backbone, moment, lodash, react, masonry, imagesloaded, thickbox, clipboard: 0 files. |

Counts: PASS 126, FAIL 19 (14 rows across six defects F1-F6, plus 5 roll-ups), RUNTIME 5, N/A 70. Total 220.

---

## FAIL

### F1. A fatal error in safe mode and without mbstring, the mode meant to keep the site up (HB-57, HB-58, HB-69)

> HB-57 "No PHP or JS errors." · HB-58 "No errors when run with `WP_DEBUG` set to true." · HB-69 "Requirement checks fail gracefully when not present."

- **Where.**
  - `vergeml_on_init` is hooked whatever the mode (vergelabs-media-library.php:597). It registers every plugin taxonomy, `media_category` included, with `'update_count_callback' => 'vergeml_update_attachment_term_count'` (:660).
  - `vergeml_on_wp_loaded` is hooked whatever the mode (:680). For a taxonomy on both posts and attachments (for example core `category` assigned to media on the Media Taxonomies screen), it sets `update_count_callback = 'vergeml_update_post_term_count'` (:768).
  - Both functions are defined only in core/taxonomies.php (:1550, :1582). That file is included only when `! vergeml_features_off()` (:1634-1637). `vergeml_features_off()` is true with `VERGEML_SAFE_MODE` or when mbstring is missing (:1597-1599).
  - safemode.js lists these two as the only unguarded references left from the always-loaded files. Review6 asked for this fix and it was applied to `vergeml_sanitize_slug` (:636) and `vergeml_enqueue_media` (:949), but not here.
- **What happens.** Core's `wp_update_term_count_now()` calls `call_user_func( $taxonomy->update_count_callback, … )`. On PHP 8 an undefined function there is a fatal `TypeError`; on PHP 7.4 it is a warning under WP_DEBUG. Any of these triggers it on a host without mbstring, or with safe mode on:
  - assigning a Media Category on the attachment edit screen;
  - deleting a filed attachment (core removes its term relationships);
  - with `category` assigned to media, publishing any post that has a category.
- **Fix.** Move `vergeml_update_attachment_term_count()` and `vergeml_update_post_term_count()` (core-only code: `$wpdb` and core hooks) out of core/taxonomies.php into the main file, or into any file loaded before the guard. As a fallback, register the callback only when `function_exists()` is true, and otherwise leave core's `_update_post_term_count`.

### F2. The opt-in wipes delete other plugins' taxonomy data (HB-149)

> HB-149 "And make sure the process is sane." (grep hint: deletes only the plugin's own options, tables, post meta, files)

- **Where.**
  - uninstall.php:59-106. For every taxonomy in `vergeml_taxonomies` that is not the plugin's own (`eml_media` 0), it runs `DELETE tr FROM term_relationships … WHERE p.post_type = 'attachment'` (:96-106).
  - core/options-pages.php:1526-1590. Complete Cleanup does the same (`elseif ( ! empty( $term_pairs ) )` at :1550, DELETE at :1576), and is called at :1460.
  - The option is filled automatically: `vergeml_on_wp_loaded` adds every registered taxonomy that has object types (main:687-713) and saves it on each request (:774).
- **What happens.** The list includes Polylang's `language` and `post_translations` (with media translation on), and the folder taxonomies of HappyFiles, Premio Folders and Wicked Folders. The plugin's own importer and neighbour notice target exactly those plugins. A user who runs Complete Cleanup, or deletes the plugin with the wipe switch on, loses those plugins' media assignments. uninstall.php reads `term_taxonomy` directly, so this happens even after the other plugin has been deactivated. readme:107 promises the wipes "remove the plugin's own settings, tables and other data".
- **Fix.** In both places, touch only taxonomies with `eml_media` = 1, and skip every foreign taxonomy. If removing links for core `category`/`post_tag` assigned to media is wanted, restrict it to those two names explicitly and say so in readme:107 and on the Utilities screen.

### F3. External services does not disclose the "How it describes" brief (HB-54, HB-71, HB-162)

> HB-54 "If 3rd party services are used, the `readme` must… Clearly disclose as to what is used, when, and why." · HB-71 "Any remote calls … must be disclosed in the `readme`."

- **Where.**
  - core/ai.php:568: every `/describe` request carries `'profile' => site_profile`, the site brief written on the AI screen.
  - core/brief.php:327-360 → core/guide.php:917: opening the brief conversation mints a token, sending a library catalogue (picture kinds with counts, the 25 most frequent AI tags with counts, brief-word counts; core/brief.php:187-222).
  - js/vergeml-brief.js:145-147: the browser streams `conversation`, `in_use` (the brief), `draft`, `input` and `summary` to `/brief/stream`. This is a browser request, so the user's IP and headers reach the service.
  - core/brief.php:413-425: the brief test describes `VERGEML_BRIEF_TEST_N` = 5 pictures with a draft brief.
  - readme:57 lists what describing sends without the brief. readme:59 and :63 cover only the Folders screen. `grep -niE "profile|brief|how it describes" readme.txt` gives 0.
  - Smaller: licence activate/check also send `environment` (core/ai.php:115, :175), but readme:67 says "sends the key and your site's address".
- **Fix.** In External services:
  - add "the site brief you wrote under AI › How it describes" to the describing paragraph (readme:57);
  - add a short paragraph: "How it describes, on the AI screen, works like the Folders screen: when you send a message it posts your message, the brief and the draft, the conversation so far and a summary of your library (picture kinds and the most frequent tags, with counts), from your browser. Test describes five pictures with the draft brief, as Describing does.";
  - add "and whether the site is production or staging" to readme:67.

### F4. Free-text REST input is saved or forwarded without `sanitize_*` (HB-60, HB-79, HB-155, HB-156, HB-99)

> HB-79 "Validate and sanitize untrusted data before processing." · HB-156 "Examples of unsafe usage of POST data: update_option( 'my_plugin_data', $_POST['my-data'] );"

- **Where.**
  - CSV import (saved). The `text` arg has no sanitize_callback (core/import-ui.php:50). It flows `(string) $request->get_param( 'text' )` (:85) → `vergeml_csv_folder()`, where `'name' => $part` (core/import-csv.php:422-455) → `update_option( VERGEML_CSV_OPTION, $parsed, false )` (:525). The raw cell `$raw` is also put into a returned message (:341-347).
  - Folders conversation (forwarded). `'instruction'` is typed string with no sanitize_callback (core/folder-talk.php:2774) and is read as `(string) $request->get_param( 'instruction' )` (:2882). `history[].text` is read as `(string) substr(…, 0, 400)` (:2877). Both go into the JSON sent to the service (:458-470). This is also why HB-99 fails ("transmission … sanitized").
  - Folder name (lower risk). `trim( (string) $request->get_param( 'name' ) )` (core/rest-folders.php:193) → `wp_insert_term` / `wp_update_term`. Core does filter term names, but a reviewer reading line by line sees no sanitizer.
  - Every other REST free-text field is sanitized at read, for example core/guide.php:1557, core/brief.php:697-708, core/quick-edit.php:191/200, core/nl-commands.php:573. So these are the stragglers of a class that is otherwise done.
- **Fix.**
  - `$part = sanitize_text_field( $part )` in core/import-csv.php:422, and `sanitize_text_field( $raw )` in the message at :346 (or `'sanitize_callback' => 'sanitize_textarea_field'` on `text`).
  - `'sanitize_callback' => 'sanitize_textarea_field'` on `instruction` (core/folder-talk.php:2774), and `sanitize_textarea_field()` on each `history` text (:2877).
  - `sanitize_text_field()` on `name` (core/rest-folders.php:193; or `'sanitize_callback'` at :37).

### F5. Front-end strings that cannot be translated (HB-96)

> HB-96 "Code should be written to support internationalization and automatic translations via translate.wordpress.org."

- **Where.**
  - js/vergeml-gallery.js:42-43 `'Previous images'`, `'Next images'`, and :123-125 `aria-label="Previous image"`, `"Next image"`, `"Close"`. These are visitor-facing: the gallery block and lightbox.
  - The script is registered with no `wp-i18n` dependency and no localization (core/gallery-block.php:46-52).
  - core/transport.php:68 and :80 return English-only messages to the browser.
  - languages/vergelabs-media-library.pot is from 3.13.2 (2026-09-01) and no longer matches the code.
- **Fix.**
  - Register `vergeml-gallery` with `array( 'wp-i18n' )`, call `wp_set_script_translations( 'vergeml-gallery', 'vergelabs-media-library' )`, and use `wp.i18n.__( '…', 'vergelabs-media-library' )` for the five labels. Alternatively, pass them with `wp_localize_script` and `esc_html__()`.
  - Wrap the two transport.php messages in `__()`.
  - Regenerate the .pot with `wp i18n make-pot`, or drop it (translate.wordpress.org builds its own).

### F6. The fork's copyright line was altered (HB-52)

> HB-52 "Forks must be appropriately credited; no copyright information may be removed."

- **Where.** EML 2.9.4 enhanced-media-library.php:13 reads `Copyright 2013-2024 wpUXsolutions  (email : wpUXsolutions@gmail.com)`. vergelabs-media-library.php:19 reads `Copyright 2013-2024 wpUXsolutions  (original work)`. The contact part of the notice is gone.
- **Fix.** Restore the original line verbatim and keep `Copyright 2026 VergeLabs  (modifications)` beneath it.

Roll-ups HB-12, HB-29, HB-35, HB-200 and HB-201 close when F1-F6 do.

---

## RUNTIME

- **HB-31, HB-58, HB-59 (and HB-57's runtime half): clean-site walk.**
  - Setup: Playground with WordPress 7.1.3, run once on PHP 8.3 and once on PHP 7.4. `WP_DEBUG`, `WP_DEBUG_LOG` and `SCRIPT_DEBUG` on. Query Monitor active. Install this exact ZIP.
  - Walk: activate on a single site, then Network Activate on a subdirectory network. Open as administrator:
    - every plugin screen;
    - upload.php in grid and list;
    - an attachment edit screen;
    - the block editor's media modal;
    - Dashboard, Plugins and Settings.
  - Then deactivate, and delete twice: once with the wipe off, once with it on.
  - Pass:
    - debug.log is empty;
    - Query Monitor shows 0 PHP errors and 0 doing_it_wrong;
    - the browser console has 0 errors on every screen;
    - after activation, `SHOW TABLES` lists the 4 `vergeml_` tables;
    - `php -l` (tools/phpcs.mjs parse) is clean on 7.4 and 8.3.
- **F1 repro (proves HB-57/58/69 before and after the fix).** Same site on PHP 8.3, with `define( 'VERGEML_SAFE_MODE', true )` in wp-config:
  - upload an image, tick a Media Category on its edit screen, then Update;
  - delete a filed attachment;
  - assign `category` to media under Media Taxonomies (before turning safe mode on), then publish a post with a category.

  Today each step is expected to end in `TypeError: call_user_func(): … "vergeml_update_*_term_count" not found`. After the fix, none should.
- **F2 repro (proves HB-149).** Activate HappyFiles or Premio Folders, or Polylang with media translation, and file 3 attachments with it. Run Complete Cleanup. `SELECT COUNT(*)` on `term_relationships` joined to that plugin's taxonomy for attachments should stay 3. Today it is expected to drop to 0.
- **HB-77, HB-106: outbound walk.**
  - Setup: an mu-plugin logging `pre_http_request` and `http_api_debug` (method, host, path, body keys). Clean site with no licence key.
  - Walk: activate, open every screen as administrator, editor and author, deactivate, uninstall.
  - Pass: 0 requests to `ai.vergelabs.nl` or `vergelabsmedia.com`; only core's own requests. Then save a licence key and open VergeLabs screens: only `/licence check`, at most once per TTL (the Licence screen each time), plus the loopback to `wp-cron.php` during a run you started.
- **HB-95: accessibility.** Run axe-core (or the WP a11y checks) on each plugin screen and on a front-end page with the folder gallery block. Then do a keyboard-only walk of the folder tree (tab, arrows, Enter to file), and open and close the lightbox with the keyboard. Known static items to confirm: the file input with no label (core/options-pages.php:507) and the English-only gallery labels (F5).
- **HB-147 (static PASS; optional runtime confirmation).** Run the route-caps suite: every `vergeml/v1` route called as author, editor and administrator. Expect 403 below `manage_options` on library-wide routes, and object checks on `/folder`, `/assign`, `/file/{id}`, `/folder-privacy`, `/autofile-act`, `/health-delete` and `/health-keep`.
