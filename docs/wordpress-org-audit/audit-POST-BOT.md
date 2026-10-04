# Audit: POST / BOT / SEC / REST rows -- VergeLabs Media Library 4.1.0 archive

Archive: `scratchpad/audit/vergelabs-media-library/` (143 files, 54,030 PHP lines). phpcs:ignore annotations ignored.
Status: PARTIAL. Rows marked **PENDING** depend on two read-heavy sweeps (all 77 `register_rest_route` calls vs their handlers; every non-REST handler, SQL, escaping and file op) that were still running when this report was forced out. They have no verdict yet; do not read them as PASS.

Verdicts: PASS / FAIL / N/A (process row, not checkable in the archive) / PENDING.

## POST rows

| ID | Verdict | Evidence | Fix if FAIL |
|---|---|---|---|
| POST-1 | PASS | Header `Plugin Name: VergeLabs Media Library` (vergelabs-media-library.php:3), readme `=== VergeLabs Media Library ===` (readme.txt:1). wordpress.org search "media library" (2026-10-04): no identical name; nearest are Enhanced Media Library (the upstream) and "Media Library *". Brand comes first. | |
| POST-2 | PASS | No third-party marks in the name or slug. "Enhanced Media Library" appears only as attribution (readme.txt:19, FAQ :146-156, plus "not affiliated", :156). | |
| POST-3 | N/A | Process. Author VergeLabs / vergelabs.nl, Plugin URI vergelabsmedia.com, Contributors `vergelabsdev`: the submitting account must match. | |
| POST-4 | PENDING (partial) | Forbidden functions: no `eval(`, `create_function`, `system/exec/shell_exec/passthru/proc_open/popen`, `assert(` (grep, all *.php/*.js). `base64_*` is data only (admin-menu.php:118 icon, ai.php:344/353 key seal, ai.php:1224/1234 image data URI, organize.php:1665/2415 packed floats). No `*.min.*` files. No `wp_redirect(` (only `wp_safe_redirect`, e.g. watchdog.php:361). The nonce and prepare halves are PENDING. | |
| POST-5 | FAIL | Prefixing is clean: every global function starts `vergeml_` (only Walker methods are unprefixed), classes are `VergeML_*`/`vergeml_Walker_*`, every define is `VERGEML_*`, options/transients/meta are `vergeml_`/`_vergeml_` (exceptions are core keys or read-only legacy `wpuxss_eml_*`). **Localhost detection:** `http://localhost` and `http://127.0.0.1` are allowed as AI service hosts at core/ai.php:315. **Text domain:** 3 JS strings have no domain: js/eml-media.js:127, :132 (`wp.i18n.__( 'That did not load. Reload the page.' )`), and :240. | Delete the localhost/127.0.0.1 allowance at ai.php:315 so only `https://` is accepted (a dev box can use https or a mu-plugin). Add `'vergelabs-media-library'` as the domain at eml-media.js:127/132 and register `wp_set_script_translations` for that handle. :240 reuses a core string on purpose and can stay. |
| POST-6 | PASS | `License: GPLv2 or later` in the header (:12) and readme (:8). Bundled: Inter fonts under OFL 1.1 (fonts/INTER-LICENSE.txt); `webbistro` IcoMoon icon font from upstream EML, no licence file; natural-compare-lite snippet (MIT) in js/eml-media-models.js:266. Note: the License URIs differ (header old-licenses/gpl-2.0, readme gpl-2.0); both are GPLv2. | (Advisory) Add a short licence note for Inter (OFL) and webbistro in the readme. |
| POST-7 | N/A | Process (run PCP every release). | |
| POST-8 | N/A | Process (WPCS + CI). | |
| POST-9 | N/A | Process. | |
| POST-10 | N/A | Process. | |
| POST-11 | N/A | Process. | |
| POST-12 | PENDING | Forbidden-call greps are clean (see POST-4). Escaping, SQL and capability checks are PENDING. | |
| POST-13 | N/A | Process. | |
| POST-14 | N/A | Process. | |
| POST-15 | N/A | Process. | |
| POST-16 | PENDING | Depends on the REST, AJAX, file-write and prepare sweeps. | |
| POST-17 | N/A | Process. | |
| POST-18 | N/A | Process. | |
| POST-19 | N/A | Process (Version 4.1.0 = Stable tag 4.1.0). | |
| POST-20 | N/A | Process. | |
| POST-21 | N/A | Process. | |
| POST-22 | N/A | Process. | |
| POST-23 | N/A | Process. | |
| POST-24 | PASS | No plugin-install code. Usage stats are opt-in: `opted => 0` default (instrument.php:45), sent only when opted in (:251-259). Notices are dismissible: the neighbour notice has `is-dismissible` plus a per-user dismissal (neighbours.php:101-114). | |
| POST-25 | PASS | grep of `Plugin_Upgrader`, `->install(`, `installPlugin`, `activate_plugin`: 0 hits. | |
| POST-26 | PASS | 0 hits for `download_url`, `unzip_file`, `plugins_api`, `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`. | |
| POST-27 | PASS | No installer exists (as POST-25). | |
| POST-28 | PASS | No Pro download or install inside the plugin. Licence purchase is an outbound link only (licence-page.php:148, offer.php "See prices"). | |
| POST-29 | N/A | No required plugin. | |
| POST-30 | N/A | No `Requires Plugins:`, and none is needed. | |
| POST-31 | PASS | Other plugins are only detected and warned about (neighbours.php:44-58), never required or installed. | |
| POST-32 | PASS | No programmatic install. | |
| POST-33 | N/A | No installs. | |
| POST-34 | N/A | No installs. | |
| POST-35 | N/A | No installs. | |
| POST-36 | PASS | No `Plugin_Upgrader` or `activate_plugin` on any hook. | |
| POST-37 | N/A | No installs. | |
| POST-38 | N/A | No installs. | |
| POST-39 | N/A | No installs. | |
| POST-40 | N/A | No installs. | |
| POST-41 | N/A | No installs. | |
| POST-42 | N/A | No installs. | |
| POST-43 | N/A | No installs. | |
| POST-44 | N/A | No installs. | |
| POST-45 | N/A | No installs. | |
| POST-46 | N/A | No installs. | |
| POST-47 | N/A | No installs. | |
| POST-48 | N/A | Process. | |
| POST-49 | N/A | Process (tool run, covered by the PC rows). | |
| POST-50 | N/A | Process. | |
| POST-51 | N/A | Process. | |
| POST-52 | FAIL | PHP: about 1,760 gettext calls, all with a literal `'vergelabs-media-library'` domain; no variable text or domain found by grep. JS: eml-media.js:127/:132 have no domain (see POST-5). | As POST-5. |
| POST-53 | PENDING | Escaping is PENDING. Assets are enqueued: the only `<script` tags in PHP are `text/html` Backbone templates (media-templates.php:19,93). | |
| POST-54 | N/A | Process (dynamic activation run). | |
| POST-55 | N/A | Process. | |
| POST-56 | N/A | Process (no .github in the zip, which is correct). | |
| POST-57 | N/A | Process. | |
| POST-58 | N/A | Process. | |
| POST-59 | N/A | Process. Note: the archive carries many `phpcs:ignore` annotations; they were ignored in this audit. | |
| POST-60 | N/A | Process. | |
| POST-61 | N/A | Process (2FA). | |
| POST-62 | N/A | Process. | |
| POST-63 | N/A | Process. | |
| POST-64 | N/A | Process. | |
| POST-65 | N/A | Process. | |
| POST-66 | N/A | Process (PCP plugin_repo run). | |
| POST-67 | PASS | `Version: 4.1.0` (:6) = `Stable tag: 4.1.0` (readme :7) = `VERGEML_VERSION` (:35). | |
| POST-68 | FAIL | `Text Domain: vergelabs-media-library` = slug. Exception: the 3 JS calls in POST-5. | As POST-5. |
| POST-69 | PASS | `Tested up to: 7.1`. api.wordpress.org version-check reports current 7.1.2, so major 7.1 is right. | |
| POST-70 | N/A | Process. | |
| POST-71 | N/A | Process. | |
| POST-72 | N/A | Process. | |
| POST-73 | N/A | Process. | |
| POST-74 | N/A | Process. | |
| POST-75 | N/A | Process. | |
| POST-76 | N/A | Process. | |
| POST-77 | N/A | Process. | |
| POST-78 | N/A | Process. | |
| POST-79 | N/A | Process. | |
| POST-80 | N/A | Process. | |
| POST-81 | N/A | Process. | |
| POST-82 | N/A | Process. | |
| POST-83 | PASS (note) | `Requires at least: 6.5`, so 6.5-site auto-updates ignore a phased-release delay. This is informational, not a violation. | |
| POST-84 | N/A | Process. | |
| POST-85 | N/A | No own updater (0 hits for `auto_update_plugin` and the update transients). | |
| POST-86 | N/A | Process. | |
| POST-87 | N/A | Superseded. | |
| POST-88 | N/A | Process. | |
| POST-89 | PASS | Readme gives private channels: support@vergelabsmedia.com for licence holders and a security email (readme.txt:200-206). | |
| POST-90 | N/A | Process. | |
| POST-91 | N/A | Process. | |
| POST-92 | PASS | Readme has name, Contributors, Stable tag, Description, Installation, FAQ, Screenshots, Upgrade Notice, Changelog; values match the header. | |
| POST-93 | PASS | Source strings are English (grep for Dutch words in gettext calls: 0); `.pot` ships. | |
| POST-94 | PASS | Readme is English (grep for Dutch words: 0). | |
| POST-95 | PASS | No localized readme; translations come from the .pot. | |
| POST-96 | PASS | No parallel-language sections. | |

## BOT rows

| ID | Verdict | Evidence | Fix if FAIL |
|---|---|---|---|
| BOT-changing-active-plugins | PASS (risk note) | grep `deactivate_plugins`, `activate_plugin(s)`, `update_option( 'active_plugins'`: 0 writes. `active_plugins` / `active_sitewide_plugins` are only read (get-help.php:107,110; system-report.php:128,135; options-pages.php:1241). Watchdog: after 2 fatals it sets a safe-mode flag and the plugin stays active (watchdog.php:146-153). Deactivation is a user button carrying core's nonce (:276-286). Risk: the rulebook grep list names "a watchdog/crash handler that turns the plugin off". Safe mode turns off its features without the user's action, and the safe-mode notice cannot be dismissed (:248). | (Advisory) In the review reply, state that safe mode only stops this plugin's own feature files and never changes activation. Optionally, make safe mode ask the user before it engages. |
| BOT-permission-callback | PENDING | Tally of the 77 `register_rest_route` calls: 65 `vergeml_rest_can_manage_options` (= `current_user_can('manage_options')`, vergelabs-media-library.php:126); 4 `vergeml_can_read_tree` (upload_files, rest-tree.php:209); 1 `vergeml_can_assign` (upload_files OR edit_posts, then per object in the handler, rest-tree.php:222); 1 `vergeml_can_manage_folders` (**manage_categories** plus per-term edit_term/delete_term, rest-folders.php:91); 1 `vergeml_private_can_edit` (**manage_categories** plus edit_term, private-folders.php:226); 1 `vergeml_autofile_can_act` (**manage_categories** plus edit_post(id) plus assign_terms, auto-file.php:678); 1 `vergeml_can_import` (manage_options); 1 own-state (upload_files); plus closures: upload_files on ai.php:1829 and rename.php:501, edit_post($id) on librarian.php:2522 and quick-edit.php:164. Three routes still sit behind manage_categories. That matches core's term caps, but it was the round-2 trigger. Handler-vs-capability matching is PENDING. | |
| BOT-insecure-mime | FAIL | Code is now safe: `upload_mimes` (mime-types.php:275-298) only switches types that `wp_get_mime_types()` knows, `vergeml_mime_refused()` (:120-131) blocks php/phtml/phar/js/html/svg/exe and similar, the sanitize callback drops unknown types (:200-207), and they are dropped again on read (:180). The settings import goes through `update_option`, so the sanitize callback runs. No `mime_types` filter and no `ALLOW_UNFILTERED_UPLOADS`. **But the readme still advertises adding types:** readme.txt:92 "Add or remove file types, allow or disallow uploading" and :247 screenshot caption "add, remove, rename and allow or disallow file types". The bot reads the readme. Minor: `wp_check_filetype_and_ext` (:314) passes ttf/otf/woff/woff2 whose content reads as `application/octet-stream`. | Change readme :92 to "Choose which of WordPress's own file types may be uploaded and which appear as media filters. Types that can run code (PHP, HTML, SVG, JS, executables) are always refused." Change :247 to "MIME Types settings: rename, filter, and allow or disallow WordPress's built-in file types." |
| BOT-undocumented-3rd-party | PASS | Hosts in code: ai.vergelabs.nl/v1 (ai.php:313, get-help.php:187, guide.php:862) and vergelabsmedia.com (connect.php:39). The browser stream (VERGEML_AI_STREAM, guide.php:857) and every `wp_remote_post` site (ai.php:105,166,592; brief.php:427; connect.php:161,186; filing.php:1796,3154; folder-talk.php:454,2320; get-help.php:76,260; guide.php:922; instrument.php:233; licence-page.php:50; offer.php:160; plan-tree.php:1042,1098; search-meaning.php:132,200) are covered in readme `## External services ##` (:104-128), with terms and privacy links (:128). ai-background.php:221 is a loopback to the site's own wp-cron.php. | |
| BOT-calling-files-remotely | PASS | No CDN, remote JS/CSS or remote font. Fonts are local (fonts/). Host list = API endpoints and plain links only. | |
| BOT-libraries-in-core | PASS | No bundled jQuery, Backbone, Underscore or Lodash. Scripts depend on core handles (`wp-api-fetch`, `media-grid`, `wp-plupload`). | |
| BOT-direct-file-access | PASS | Every *.php has an `ABSPATH` guard (uninstall.php has `WP_UNINSTALL_PLUGIN`). Checked that only comments come before each guard. index.php is a silence file. | |
| BOT-generic-names | PASS | See POST-5: functions, classes, defines, options, transients, meta, cron hooks (`VERGEML_*_HOOK`, `vergeml_provision_site`, `vergeml_meaning_convert`), AJAX actions (`vergeml_*`), shortcode `vergeml_gallery`, block `vergelabs/folder-gallery`. The unprefixed hooks fired are core's own (options-pages.php:300-304). | |
| BOT-sanitize | PENDING | | |
| BOT-sanitize-escape-confusion | PENDING | | |
| BOT-sanitize-filter-functions | PENDING | | |
| BOT-sanitize-nonces | PENDING | Spot checks pass: options-pages.php:1557,1700 and transport.php:41-47 use `sanitize_text_field( wp_unslash() )`. | |
| BOT-nonces-user-permissions | PENDING | | |
| BOT-processing-whole-input | FAIL (risk) | transport.php:31-97 `wp_ajax_vergeml_rest` reads the whole `php://input` JSON and forwards `params` unfiltered to `rest_do_request()` for any route in the namespace. It checks nonce `wp_rest` plus `upload_files`, and each route's permission_callback still runs. This is exactly the "process the whole input" shape. It also re-opens REST for sites where a security plugin deliberately blocked it. | Remove the bridge from the wordpress.org build. If it stays: allow-list the routes the bridge may reach, and pass only the arg keys each route declares (`$server->get_routes()[ $route ][0]['args']`) instead of the whole `params` array. |
| BOT-escape | PENDING | | |
| BOT-escape-esc_url_raw | PASS | grep `echo esc_url_raw`: 0. | |
| BOT-escape-__ | PASS | grep `echo __(` / `echo _x(` / `echo _n(`: 0. | |
| BOT-escape-_e | PASS | grep bare `_e(` / `_ex(`: 0. | |
| BOT-escape-json | FAIL | options-pages.php:1582 `echo json_encode( $settings );` (settings export download). taxonomies.php:1159 `json_encode` goes into an internal buffer that is decoded again (vergelabs-media-library.php:940-955), never output, but the bot sees it. | :1582 → `echo wp_json_encode( $settings );`. taxonomies.php:1159 → `wp_json_encode( $el )`. |
| BOT-escape-html | PENDING | | |
| BOT-escape-sanitizing-functions | PASS | grep `echo sanitize_`: 0. | |
| BOT-file-uploader | PASS | grep `move_uploaded_file`: 0. The only `$_FILES` use is the settings import (options-pages.php:1627-1631), which checks `is_uploaded_file` (:1634) and only reads the file, then JSON-decodes it. | |
| BOT-unfiltered-uploads | PASS | grep `ALLOW_UNFILTERED_UPLOADS`, `unfiltered_upload`, `map_meta_cap`, `user_has_cap`: 0. | |
| BOT-libraries-dev-versions | N/A | No bundled libraries, no composer.lock or package.json. | |
| BOT-libraries-not-maintained | N/A | Same. | |
| BOT-libraries-out-of-date | N/A | Same. | |
| BOT-unsafe-sql | PENDING | | |
| BOT-sql-array-placeholders | PENDING | | |
| BOT-heredoc | PASS | grep `<<<`: 0. | |
| BOT-php-short-tags | PASS | grep `<?` not followed by `php`: 0. | |
| BOT-php-settings-globally | PASS (note) | `set_time_limit` only inside cron job functions (ai-background.php:351, guide.php:1706, plan-tree.php:348,859). `ignore_user_abort` only in the export handler (options-pages.php:1575). No `ini_set`, `ini_alter`, `dl`, `error_reporting` or `date_default_timezone_set`. Note: watchdog.php:62 registers a shutdown function and holds a 256 KB string on every request. That is not a PHP setting, but a reviewer may question it. | |
| BOT-php-timezone | PASS | grep `date_default_timezone_set`: 0. | |
| BOT-php-error-reporting | PASS | grep `error_reporting`, `display_errors`, `set_error_handler`: 0. | |
| BOT-debug-code | PASS | grep `error_log`, `var_dump`, `print_r`, `var_export`, `console.log`, `debugger`: 0. | |
| BOT-main-file-convention | PASS | `vergelabs-media-library/vergelabs-media-library.php`. | |
| BOT-incomplete-headers | PASS | Name, URI, Description, Version, Requires at least, Requires PHP, Author, Author URI, Text Domain, Domain Path, License, License URI (:3-14). | |
| BOT-incomplete-readme | PASS | All sections present (see POST-92). | |
| BOT-no-gpl-license | PASS | See POST-6. | |
| BOT-stable-tag | PASS | 4.1.0 = 4.1.0. | |
| BOT-license-mismatch | PASS | Both say "GPLv2 or later". | |
| BOT-curl | PASS (note) | grep `curl_`: 0. ai.php:850-900 uses core's bundled `WpOrg\Requests\Requests::request_multiple` with `verify => true`, falling back to sequential `wp_remote_post`. It bypasses `pre_http_request`, as its own comment at ai.php:825 says. | (Advisory) Be ready to explain this, or use only `wp_remote_post`. |
| BOT-wp-enqueue | PASS | No echoed `<script>`, `<style>` or `<link>`. Inline JS goes through `wp_add_inline_script` (transport.php:139). | |
| BOT-i18n-variables | PASS | grep for gettext with a `$` first argument: 0. Labels typed by users are built without gettext (mime-types.php:244-266). | |
| BOT-text-domain | FAIL | js/eml-media.js:127,132 have no domain (see POST-5). | As POST-5. |
| BOT-load-plugin-textdomain | PASS | grep: 0. | |
| BOT-contributors | N/A | `vergelabsdev`: the account's existence cannot be checked from the zip. | |
| BOT-tested-up-to | PASS | See POST-69. | |
| BOT-update-checker | PASS | 0 update-transient or `plugins_api` hooks. | |
| BOT-unneeded-folders | PASS | Zip has core/ css/ fonts/ images/ js/ languages/ only. No tests, node_modules, .github, docs, bin or .claude. | |
| BOT-not-permitted-files | PASS | Only php, js, css, woff2/ttf/eot/svg/woff fonts, gif, txt, md, xml, pot. | |
| BOT-premium-source | PASS | No premium libraries. | |
| BOT-gpl-non-compatible | PASS (note) | Inter is OFL (the usual font exception). webbistro has no licence file (it is upstream EML's GPL icon font). | (Advisory) Name both in the readme. |
| BOT-gpl-no-public-source | N/A | Nothing is minified or compiled. | |
| BOT-gpl-composer-json | N/A | No vendor/. | |
| BOT-saving-in-plugin-folder | PASS | No `file_put_contents`/`fopen`/`mkdir` writes under the plugin path (grep). Temp files use `wp_tempnam` (ai.php:1218, folder-tools.php:243). The plugin dir is only read (transport.php:135). | |
| BOT-determine-locations | PASS | `WP_PLUGIN_DIR` is used only to read `get_plugin_data` of active plugins (get-help.php:114, system-report.php:129,136). The `'wp-content/uploads'` literal is a comparison against core's `upload_path` option (options-pages.php:497). Own files go through `plugin_dir_path`/`plugins_url`. | |
| BOT-uninstall | PASS | uninstall.php:3 checks `WP_UNINSTALL_PLUGIN`. | |
| BOT-trialware | PASS | The licence gate is `vergeml_ai_ready()` (ai.php:365) and is used only before remote AI calls (ai.php, ai-background.php, brief.php). Folders, smart folders, health, import, galleries, MIME settings and the date/type Librarian run without a key (readme :108). | |
| BOT-serviceware | PASS | The service does model inference (describe, plan, talk, embeddings), and the readme documents it at length. | |
| BOT-tracking-consent | PASS | Stats are opt-in, default 0 (instrument.php:45). Every other remote call follows a user action or a key the user entered (readme :106). | |
| BOT-executable-code | PASS | No eval, no include of a variable path, no remote code, no iframe (grep `<iframe`: 0). | |
| BOT-hijack-admin | PASS (note) | Notices: bulk-terms.php:224, media-list.php:721 and rename.php:399/458 are result notices with `is-dismissible`. neighbours.php:76 is dismissible and limited to the media and plugin screens. The watchdog notice (watchdog.php:235,298) cannot be dismissed but shows only in a failure state and gives the fix. Menus: 1 top-level menu plus submenus, all `manage_options`. options-pages.php:233-268 re-points core's Settings → Media entry to the plugin's page (inherited from EML). | |
| BOT-wp-load | PASS | grep `wp-load`, `wp-config.php` require, `wp-blog-header`: no includes (only 3 comments mention wp-config.php). | |
| BOT-wp-debug-clean | N/A | Needs a runtime WP_DEBUG run, which is a tool step. | |
| BOT-url-shorteners | PASS | grep bit.ly, tinyurl, goo.gl, t.co, ow.ly: 0. | |
| BOT-third-party-ads | PASS | grep gtag, GTM, GA, fbq, hotjar, mixpanel: 0. | |
| BOT-credit-links | PASS | Gallery block and shortcode output has no "powered by" or vendor link (grep gallery-block.php, gallery-widgets.php, js/vergeml-gallery*). The credits block is admin-only (options-pages.php:3373-3379). | |
| BOT-minification | PASS | No *.min.*, no packer. | |
| BOT-core-scripts | PASS | No CDN scripts. | |
| BOT-service-docs | PASS | See BOT-undocumented-3rd-party. | |
| BOT-service-iframe | PASS | No iframe. Calls use https by default with `sslverify => true` (brief.php:430, instrument.php:239). | |
| BOT-raw-code-input | PASS | No setting stores raw HTML, JS or CSS. Free text uses `sanitize_textarea_field` (ai.php:2139, brief.php:87, get-help.php:204, guide.php:1562). | |
| BOT-include-plugins-themes | PASS | No bundled plugins, no TGMPA. | |
| BOT-custom-post-types-prefix | FAIL | Default folder taxonomy `media_category` is unprefixed (vergelabs-media-library.php:1141, inherited from EML on purpose for compatibility). guide.php:2305-2312 registers a **new taxonomy keyed from user-typed text** (`sanitize_key( $name )`) and adds the `vgml_` prefix only on collision, so a key like `products` gets registered. | guide.php:2306: always use `$key = 'vgml_' . substr( …, 0, 27 )`. For `media_category`, explain the EML/FileBird compatibility in the review reply. |
| BOT-sessions | PASS | grep `session_start`, `$_SESSION`: 0. | |
| BOT-five-star | PASS | No review links (grep `reviews`, `rate=5`, `filter=5`: 0). | |
| BOT-name-generic | PASS | Original brand first ("VergeLabs") plus a descriptive part. | |
| BOT-name-unrelated | PASS | The name matches what the plugin does. | |
| BOT-name-keyword-stuffing | PASS | Name has 3 words. 5 tags. | |
| BOT-name-too-similar | PASS (risk) | "VergeLabs Media Library" vs "Enhanced Media Library" share the "<word> Media Library" shape. The brand prefix makes them distinct. | |
| BOT-name-trademark | PASS | No WordPress, WP or other marks in the name. | |
| BOT-name-owner | N/A | Process: the account must be VergeLabs-owned. | |
| BOT-description-superlatives | PASS | grep readme for best, #1, "the only", "the most", "better than", unique: 0 claims. "market leaders" appears only in a code comment (transport.php:10). | |
| BOT-rel-endpoints | PENDING | | |
| BOT-rel-prepare | PENDING | | |
| BOT-rel-file-ops | PENDING | | |
| BOT-rel-unserialize | PENDING (partial) | grep `unserialize(`: 0. One `maybe_unserialize` on core's `_wp_attachment_metadata` read from the DB (health.php:938). That is internal data, not external, so it is likely fine. | |
| BOT-rel-options-meta | PENDING | | |

## SEC rows

| ID | Verdict | Evidence | Fix if FAIL |
|---|---|---|---|
| SEC-1 | PENDING | | |
| SEC-2 | PENDING | | |
| SEC-3 | PENDING | | |
| SEC-4 | PASS (partial) | grep `wp_ajax_nopriv`, `admin_post_nopriv`: 0. Capability checks on the REST and AJAX handlers are PENDING. | |
| SEC-5 | PENDING | | |
| SEC-6 | PENDING | | |
| SEC-7 | PENDING | | |
| SEC-8 | PENDING | | |
| SEC-9 | PENDING | | |
| SEC-10 | PENDING | | |
| SEC-11 | PENDING | | |
| SEC-12 | PENDING | | |
| SEC-13 | PENDING | Spot check: options-pages.php:3286-3287 uses `esc_html` inside `value=""` attributes (should be `esc_attr`). | |
| SEC-14 | PASS | No bare `_e(` or `echo __(` (see BOT-escape-_e and BOT-escape-__). | |
| SEC-15 | PENDING | | |
| SEC-16 | PENDING | | |
| SEC-17 | PENDING | | |

## REST rows

| ID | Verdict | Evidence | Fix if FAIL |
|---|---|---|---|
| REST-1 | PASS (partial) | 77 `register_rest_route` calls, 77+ `permission_callback` entries (tally in BOT-permission-callback). Per-method completeness is PENDING. | |
| REST-2 | PENDING | 3 manage_categories gates are listed in BOT-permission-callback. | |
| REST-3 | PENDING | The named callbacks read so far return `current_user_can()` booleans. | |
| REST-4 | PENDING | | |
| REST-5 | PASS (partial) | grep `__return_true`: none in a permission_callback in the tally. | |
| REST-6 | PENDING | All 77 use `VERGEML_REST_NS`. rest-folders.php registers inside `rest_api_init` (:24). The rest are not yet verified. | |
| REST-7 | PENDING | The namespace constant value is not yet confirmed. | |
| REST-8 | PENDING | transport.php checks the `wp_rest` nonce; JS apiFetch use not yet verified. | |
| REST-9 | PENDING | | |
| REST-10 | PENDING | rest-folders.php:29-71 has typed args with `sanitize_key`; `name` and `color` have no sanitize_callback. | |
| REST-11 | PENDING | | |
| REST-12 | PENDING | Per-object checks seen: librarian.php:2522 and quick-edit.php:164 (`edit_post` id), auto-file.php:684, rest-folders.php:105-128 (edit_term/delete_term). | |

## FAILS

1. **POST-5 / POST-52 / POST-68 / BOT-text-domain.** core/ai.php:315 allows `http://localhost` and `http://127.0.0.1` as the service URL (Plugin Check's localhost detection). js/eml-media.js:127,132 call `wp.i18n.__` with no text domain. Fix: accept `https://` only; add the domain plus `wp_set_script_translations`.
2. **BOT-insecure-mime.** The code is fixed, but readme.txt:92 ("Add or remove file types…") and :247 ("add, remove, rename … file types") still describe adding MIME types, and the bot reads the readme. Fix: reword both lines to "WordPress's own types only; types that can run code are always refused".
3. **BOT-processing-whole-input.** core/transport.php:31-97 forwards the whole `php://input` `params` into any `vergeml/v1` route through admin-ajax, and works around REST blocks. Fix: drop the bridge from the wordpress.org build, or allow-list routes and pass only declared args.
4. **BOT-escape-json.** core/options-pages.php:1582 `echo json_encode( $settings )`; core/taxonomies.php:1159 `json_encode`. Fix: `wp_json_encode`.
5. **BOT-custom-post-types-prefix.** core/guide.php:2306 registers a taxonomy keyed from user text, unprefixed unless it collides. `media_category` is unprefixed (deliberate). Fix: always prefix `vgml_`, and justify `media_category` in the reply.

Risks that are not FAILs: watchdog safe mode turns off features without user action and its notice cannot be dismissed (watchdog.php); three REST routes still gated on manage_categories with per-term checks (rest-folders.php:91, private-folders.php:226, auto-file.php:678); `Requests::request_multiple` bypasses the HTTP API filters (ai.php:850+); `esc_html` used in attributes (options-pages.php:3286-3287).

PENDING: REST-2..12 handler matching, all SEC rows except SEC-4 and SEC-14, BOT-sanitize*, BOT-nonces-user-permissions, BOT-escape and BOT-escape-html, BOT-unsafe-sql, BOT-sql-array-placeholders, BOT-rel-*, POST-4/12/16/53 (their SQL, nonce and escape halves). These need the full REST-route sweep and the non-REST handler/SQL/escaping sweep.
