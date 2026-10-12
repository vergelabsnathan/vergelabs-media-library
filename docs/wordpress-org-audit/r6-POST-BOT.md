# Round 6 audit: POST and BOT rows, VergeLabs Media Library 4.1.0

Judged: the extracted release archive `C:/Users/viete/AppData/Local/Temp/vgml-r6-archive` (147 files: 68 PHP, 43 JS, 22 CSS; the same 147 entries as `dist/vergelabs-media-library-4.1.0.zip`, top folder `vergelabs-media-library/`). Every `phpcs:ignore` / `phpcs:disable` was ignored; the code under each was read. Rulebook: `docs/wordpress-org-rulebook.md`, rows POST-1 to POST-96 and BOT-changing-active-plugins to BOT-rel-options-meta (184 rows).

Notation: `A` is the archive root. Greps are `grep -rnE '<pattern>' --include=*.php A` unless another scope is named (`js/`, `css/`, `readme.txt`). Pipes inside patterns are written `\|`. Scanners written for this audit sit in `C:/Users/viete/AppData/Local/Temp/r6pb-scratch/`:
- `rest.js` lists every `register_rest_route` handler with its methods, permission callback and args, and every `get_param` that is undeclared or untyped.
- `sql.js` classifies every `$wpdb->query/get_results/get_var/get_col/get_row` call.
- `i18n.js` checks every gettext call in PHP and JS for literal text and domain.
- `fn.js` extracts a function body.
- `css-hide.js` finds CSS rules that hide core notices or chrome.

The echo operand classifier is the round-6 one (`vgml-r6-scratch/echo.js`), re-run on this archive.

Verdicts: PASS (evidence given), FAIL (see the FAIL section, by finding), RUNTIME (see the RUNTIME section), N/A (one-line reason). Process rows that no file in the zip can show are N/A.

## Table

| ID | Verdict | Evidence |
|---|---|---|
| POST-1 | PASS | `Plugin Name: VergeLabs Media Library` (vergelabs-media-library.php:3) = `=== VergeLabs Media Library ===` (readme.txt:1). Directory API `query_plugins` search "VergeLabs" and "VergeLabs Media Library": 0 results. `plugin_information` slug `vergelabs-media-library`: 404, so the slug is free. The brand comes first. |
| POST-2 | PASS | Name and slug carry no third-party mark. Checked against Plugin Check's `Trademarks_Check::TRADEMARK_SLUGS` (112 entries): 0 hits. "Enhanced Media Library" appears only as attribution (readme.txt:19 plus "not affiliated"). Banner and icon are SVN `/assets`, not in the zip. |
| POST-3 | N/A | This is a process check on the submitting account. In the zip: Author `VergeLabs`, Author URI `https://vergelabs.nl`, Plugin URI `https://vergelabsmedia.com`, `Contributors: vergelabsdev` (profiles.wordpress.org/vergelabsdev answers 200). The account's email domain is not visible from the zip. |
| POST-4 | PASS | Nonces: 14 `wp_verify_nonce`, 13 `check_admin_referer`, 6 `check_ajax_referer`, and every handler is gated (see BOT-nonces-user-permissions). Direct DB: `sql.js` finds 207 calls, 187 through `prepare()`; the 20 others are literals with only core `{$wpdb->table}` interpolated (see BOT-unsafe-sql). Forbidden functions `eval(\|assert(\|create_function(\|system(\|exec(\|shell_exec(\|passthru(\|proc_open(\|popen(\|goto`: 0. `find A -name '*.min.*'`: 0. `wp_redirect(`: 0; `wp_safe_redirect(`: 18. |
| POST-5 | PASS | Prefixing: every global function starts `vergeml_`; the only other `function` hits are the Walker methods `start_el/start_lvl/end_lvl/end_el` (media-list.php:392, taxonomies.php:1053-1235). Classes: `VergeML_*` / `vergeml_Walker_*` (6). Namespace: 0. Every `define`/`const` is `VERGEML_*`. Options and transients are `vergeml_*`, apart from read-only core and EML legacy keys. Text domain: see POST-68. Localhost: Plugin Check's LocalhostSniff pattern `(https?:)?//(localhost\|127.0.0.1\|*.local)/` matches 0 times. `core/offer.php:26` is a host regex that refuses trials on local sites; it is not a URL. The unprefixed taxonomy key is F5. |
| POST-6 | PASS | `License: GPLv2 or later` in the header (:13) and the readme (:8). Bundled: Inter (OFL 1.1, fonts/INTER-LICENSE.txt), the webbistro icon font (from GPL EML), natural-compare-lite (MIT, credited at js/eml-media-models.js:265-270). All three are listed at readme.txt:119-121. |
| POST-7 | RUNTIME | A Plugin Check run on this zip proves it (R1). |
| POST-8 | N/A | Process (WPCS and CI). The zip correctly ships no `.github/`. |
| POST-9 | N/A | Process. |
| POST-10 | N/A | Process (queue time). |
| POST-11 | N/A | Process (reply to every email). |
| POST-12 | FAIL | Release-scan greps: `eval(\|base64_decode(\|system(\|exec(\|shell_exec(` all execution-free (base64 is data only, see BOT-executable-code). Unescaped output: 0. Unprepared SQL with a variable: 0. Missing `current_user_can`: 2 state-changing `admin_init` callbacks. See F4. |
| POST-13 | N/A | Process (6-hour cooldown). |
| POST-14 | N/A | Process. |
| POST-15 | N/A | Process (committers' email). |
| POST-16 | FAIL | Same greps on the new code paths. 85 REST handlers are gated (BOT-permission-callback), 7 AJAX handlers are gated, 0 file writes come from request data (BOT-rel-file-ops), and SQL is prepared (BOT-rel-prepare). The two ungated `admin_init` writes are F4. |
| POST-17 | N/A | Process. |
| POST-18 | N/A | Process. |
| POST-19 | N/A | Process. `Version: 4.1.0` = `Stable tag: 4.1.0`. |
| POST-20 | N/A | Process. |
| POST-21 | N/A | Process. |
| POST-22 | N/A | Process. |
| POST-23 | N/A | Process. |
| POST-24 | PASS | `Plugin_Upgrader\|->install(\|installPlugin\|activate_plugin` in PHP and JS: 0. Telemetry is opt-in: `opted => 0` default (core/instrument.php:45), sent only when `vergeml_stats_opted()` (:255-258). The other-plugin notice has `is-dismissible` and a per-user dismissal (core/neighbours.php:102, :109, :143). No feature needs another plugin. |
| POST-25 | PASS | `Plugin_Upgrader\|Theme_Upgrader\|->install(\|installPlugin\|activate_plugins?(`: 0. |
| POST-26 | PASS | `download_url(\|unzip_file(\|plugins_api\|pre_set_site_transient_update_plugins\|site_transient_update_plugins\|auto_update_\|Update URI`: 0. |
| POST-27 | PASS | No installer exists (POST-25: 0). |
| POST-28 | PASS | No Pro download or install. Licence purchase and connect are outbound links and an OAuth-style redirect only (core/licence-page.php:153, core/connect.php:38-60). |
| POST-29 | N/A | No plugin is required. |
| POST-30 | N/A | No `Requires Plugins:` header, and none is needed. |
| POST-31 | PASS | Other folder plugins are only detected and warned about (core/neighbours.php:44-58, :78-115), never recommended for install or required. |
| POST-32 | PASS | No programmatic install (POST-25). |
| POST-33 | N/A | No install UI. |
| POST-34 | N/A | No install UI. |
| POST-35 | N/A | No install UI or wizard. |
| POST-36 | PASS | `Plugin_Upgrader\|activate_plugin` on any hook: 0. The activation hook (vergelabs-media-library.php:304-344) provisions options and tables only. |
| POST-37 | N/A | No installs. |
| POST-38 | N/A | No installs. |
| POST-39 | N/A | No installs. |
| POST-40 | N/A | No installs. |
| POST-41 | N/A | No installs. |
| POST-42 | N/A | No installs. |
| POST-43 | N/A | No installs. |
| POST-44 | N/A | No install checkboxes. |
| POST-45 | N/A | No installs. |
| POST-46 | N/A | No installs. |
| POST-47 | N/A | No installs. |
| POST-48 | N/A | Process (3-month window). |
| POST-49 | RUNTIME | A Plugin Check run on this zip proves it (R1). |
| POST-50 | N/A | Process (PCP covers most checks, not all). |
| POST-51 | RUNTIME | A Plugin Check run before submitting (R1). |
| POST-52 | PASS | `i18n.js`: 2,110 gettext calls in PHP and JS, 0 non-literal text, 0 missing or wrong domain. The 4 JS files that use `wp.i18n` each have `wp_set_script_translations( …, 'vergelabs-media-library' )` (ai-screen.php:78, brief.php:757, guide.php:89, guide.php:139). |
| POST-53 | FAIL | Escaping, nonces and enqueuing PASS (BOT-escape, BOT-nonces-user-permissions, BOT-wp-enqueue). Sanitising has open instances: F2. Accessibility and labelled fields are RUNTIME (R1, accessibility category). |
| POST-54 | RUNTIME | Activation on a clean site with WP_DEBUG (R2), plus PCP (R1). |
| POST-55 | N/A | Process. |
| POST-56 | N/A | Process (GitHub Action). The zip correctly ships no `.github/`. |
| POST-57 | N/A | Process (the guideline audit is this document set). |
| POST-58 | N/A | Process (Pro is not in this zip). |
| POST-59 | PASS | 137 `phpcs:ignore` and 119 `phpcs:disable`; 0 `phpcs:ignoreFile`; 0 blanket (every one names its sniffs). Security sniffs suppressed: NonceVerification ×48, EscapeOutput ×1 (core/icons.php:34, literal SVG path data), InterpolatedNotPrepared ×5 and NotPrepared ×1 (core-table interpolation inside `prepare()`), UnescapedDBParameter ×8. Each was verified independently with annotations ignored; see BOT-escape and BOT-unsafe-sql. Advisory A5 covers annotations with no reason. |
| POST-60 | N/A | Process. |
| POST-61 | N/A | Account setting (2FA), not in the zip. Confirm before submitting. |
| POST-62 | N/A | Account setting (2FA). |
| POST-63 | N/A | Account setting (SVN password). |
| POST-64 | N/A | Process (committer list). |
| POST-65 | N/A | Process (Release Confirmation). |
| POST-66 | RUNTIME | `--categories=plugin_repo` with 0 ERROR (R1). |
| POST-67 | PASS | `Version: 4.1.0` (vergelabs-media-library.php:6) = `Stable tag: 4.1.0` (readme.txt:7). `VERGEML_VERSION` is 4.1.0. |
| POST-68 | PASS | `Text Domain: vergelabs-media-library` = the folder slug. Every gettext domain matches (POST-52: 0 mismatches). |
| POST-69 | PASS | `Tested up to: 7.1` (readme.txt:5). api.wordpress.org version-check today offers 7.1.3, so 7.1 is the current major, with no minor. |
| POST-70 | N/A | Process. |
| POST-71 | RUNTIME | `--categories=security` clean (R1). |
| POST-72 | N/A | The plugin was never closed for a vulnerability. |
| POST-73 | N/A | Process (CI). |
| POST-74 | RUNTIME | PCP minimum checks (R1). |
| POST-75 | RUNTIME | PCP before tagging (R1). |
| POST-76 | RUNTIME | PCP security, plugin_repo and compatibility findings (R1). |
| POST-77 | N/A | Process. |
| POST-78 | N/A | Process. |
| POST-79 | RUNTIME | PCP before each update (R1). |
| POST-80 | N/A | Process (Release Confirmation and rollout). |
| POST-81 | N/A | Process. |
| POST-82 | N/A | Process. |
| POST-83 | N/A | Informational only. `Requires at least: 6.5` means 6.5.x sites ignore a delayed-auto-update flag; nothing to change. |
| POST-84 | N/A | Process (statistics). |
| POST-85 | PASS | No updater code in the free zip: `auto_update_plugin\|site_transient_update_plugins\|pre_set_site_transient_update_plugins\|Update URI\|PucFactory\|plugin-update-checker`: 0. |
| POST-86 | N/A | Process. |
| POST-87 | N/A | Superseded by POST-88. |
| POST-88 | N/A | Process. |
| POST-89 | PASS | readme.txt:111: "Licence holders write to support@vergelabsmedia.com". Service terms and privacy are linked at readme.txt:77. |
| POST-90 | N/A | Process. |
| POST-91 | N/A | Process (reply in English). |
| POST-92 | PASS | readme `=== VergeLabs Media Library ===`, `Contributors: vergelabsdev`, `Stable tag: 4.1.0`, `Requires at least: 6.5` and `Requires PHP: 7.4` all equal the header. `## Description ##` is at :13. |
| POST-93 | PASS | Source strings are English (no Dutch words found in string literals in PHP or JS). Text Domain = slug. Advisory A6: 5 front-end strings in js/vergeml-gallery.js are English but cannot be translated. |
| POST-94 | PASS | readme.txt is English throughout. Its only non-ASCII characters are `—` ×9 and `→` ×1. |
| POST-95 | PASS | No localized readme, no non-English UI strings. `languages/vergelabs-media-library.pot` ships. |
| POST-96 | PASS | readme sections at :13, :51, :79, :87, :137, :146, :151; no parallel sections in another language. |
| BOT-changing-active-plugins | PASS | `deactivate_plugins(\|activate_plugins?(\|update_option( 'active_plugins'`: 0. `active_plugins`/`active_sitewide_plugins` are read only (get-help.php:107, :110; options-pages.php:901; system-report.php:128, :135). The activation callback (vergelabs-media-library.php:318-344) deactivates nothing. A missing extension shows a notice and the features stay off (:1585-1616, `vergeml_features_off()`); the plugin never deactivates itself. |
| BOT-permission-callback | PASS | `rest.js`: 77 `register_rest_route` calls, 85 handlers, every one with a named `permission_callback`; `__return_true` on a route: 0 (its 2 hits are core filters, taxonomies.php:676 and vergelabs-media-library.php:1566). Writes require `manage_options` (vergelabs-media-library.php:126) or object-level checks: `vergeml_health_can_drop` (`edit_post` keep and `delete_post` each drop, health-delete.php:582-601), `vergeml_autofile_can_act` (manage_options + `edit_post` + `assign_terms` + the term exists, auto-file.php:690-700), `vergeml_can_manage_folders` (the taxonomy's `manage_terms`/`edit_terms` and `edit_term`/`delete_term` per id, default false, rest-folders.php:91-155), `vergeml_can_assign` (upload_files or edit_posts plus `assign_terms`; then per object in the handler, rest-tree.php:228-243, :635+), `vergeml_rest_can_edit_this_file` (`edit_post` on the route id, plus an attachment check in quick-edit.php:178), `vergeml_private_can_edit` (manage_options + `edit_term`). The `upload_files`-only writes are `/state`, which writes the caller's own user option (rest-folders.php:510-560). |
| BOT-insecure-mime | PASS | `upload_mimes`: 1 callback (mime-types.php:285-306), remove-only: `unset()` and no other write. `vergeml_mime_refused()` (:122-132) always removes php*, phtml, phar, htm*, svg*, js, css, xml, exe and the rest of its list. `wp_check_filetype_and_ext` (:324-415) can re-allow only ttf/otf/woff/woff2 or an xls type, and only if that type is in `get_allowed_mime_types()` (:401-408). `ALLOW_UNFILTERED_UPLOADS\|unfiltered_upload`: 0. |
| BOT-undocumented-3rd-party | PASS | Outgoing calls: 21 `wp_remote_*` (0 `curl_`). Hosts: `vergeml_ai_service_url()` = https://ai.vergelabs.nl/v1 (ai.php:311-321, https forced); `vergeml_connect_base()` = https://vergelabsmedia.com (connect.php:38-42); a loopback `site_url('wp-cron.php')` (ai-background.php:220). Browser-side: one `fetch` to the stream URL (js/vergeml-talk.js:398). Every host and payload is in `## External services ##` (readme.txt:51-77), with terms and privacy links at :77 and the browser stream at :63. |
| BOT-calling-files-remotely | PASS | Every `wp_register/enqueue_*` source is `plugins_url(…)` or `$vergeml_dir` (= `plugin_dir_url(__FILE__)`, vergelabs-media-library.php:520). CSS `url(http`: 0. `@import`: 0. `src="http`: 0. The hard-coded URLs in PHP, JS and CSS are links, XML namespaces or service endpoints only. |
| BOT-libraries-in-core | PASS | No bundled jQuery, Underscore, Backbone, Lodash, Moment or similar: no library licence headers in `js/` other than natural-compare-lite. No `vendor/`. Scripts depend on core handles (`media-grid`, `wp-plupload`, `wp-i18n`, jQuery). |
| BOT-direct-file-access | PASS | 66 PHP files open with `if ( ! defined( 'ABSPATH' ) ) exit;` as their first statement. uninstall.php opens with `WP_UNINSTALL_PLUGIN`. index.php holds only a comment. All 68 checked by script. |
| BOT-generic-names | FAIL | Functions, classes, defines, globals (`$vergeml_*`, `$GLOBALS['vergeml_*']`), options, transients, meta keys (`_vergeml_*`; the rest are core or Yoast keys), user options, cron hooks, AJAX actions (`vergeml_*`/`vergeml-*`; `tb_load_editor` is Themify's own action, hooked at page-builders.php:262), REST namespace `vergeml/v1` (rest-tree.php:30), block `vergelabs/folder-gallery`, shortcode `vergeml_gallery`, script handles `vergeml-*`: all prefixed. The exception is the default taxonomy key `media_category`. See F5. |
| BOT-sanitize | FAIL | Superglobals: 134 reads, each wrapped in `sanitize_*( wp_unslash() )`, `absint`, `(int)`, an `isset`/`empty` test or a strict comparison. `$_FILES` is read field by field (options-pages.php:1288-1292), behind `is_uploaded_file` (:1295). REST: every declared arg is typed; the undeclared reads are `reset`/`draft` on the shared session callbacks and the `(int)(bool)` flag loop at ai.php:2005. Unsanitised free text remains at 3 places: F2. |
| BOT-sanitize-escape-confusion | PASS | `esc_*( $_POST\|$_GET\|$_REQUEST`: 0. `esc_*` inside `update_option/…meta/wp_insert_term` arguments: 0. `esc_*` in the 5 settings validators: 0. `esc_url_raw`: 1 hit, connect.php:139, into `wp_safe_redirect` (correct use). |
| BOT-sanitize-filter-functions | PASS | `filter_input(\|filter_var(`: 0. |
| BOT-sanitize-nonces | PASS | 14 `wp_verify_nonce` calls; 13 take `sanitize_text_field( wp_unslash( … ) )` or `sanitize_key( wp_unslash( … ) )` inline. The 14th (transport.php:46) takes `$nonce`, built that way at :40 and :43. |
| BOT-nonces-user-permissions | PASS | AJAX (7): offer.php:146 (nonce + manage_options), options-pages.php:882 (nonce + manage_network_options), taxonomies.php:1351 (nonce + upload_files), taxonomies.php:1391 (nonce by branch + `edit_post` on the post and on every attachment), transport.php:33 (wp_rest nonce + upload_files + the route's own permission), page-builders.php:44 (upload_files; enqueues assets, changes nothing). admin_post (10): each has a capability check and `check_admin_referer`. admin_init form handlers (export, import, restore, wipe switch, cleanup, neighbour dismiss, connect): nonce or single-use state, plus capability. Network forms (options-pages.php:1027, :1160): nonce + manage_network_options. Bulk actions: core's `check_admin_referer('bulk-media')`, plus `edit_post` per id. |
| BOT-processing-whole-input | FAIL | `foreach ( array_keys( $_GET ) as $key )` at core/admin-menu.php:200. See F1. Other whole-stack patterns (`foreach ( $_`, `wp_unslash( $_POST )`, `= $_POST;`, `get_params()`): 0. |
| BOT-escape | PASS | The echo classifier over all 68 PHP files: 268 output statements (echo, print, printf, `<?=`, wp_die, exit). 265 contain only literals, `esc_*`, `wp_kses*`, casts or `number_format_i18n`. The other 3 are ternaries with both branches escaped (admin-shell.php:740, journey.php:1311, options-pages.php:1146). Multi-line `printf` spot-checked (admin-menu.php:293, connect.php:243-262, help.php:134). `wp_die/exit/die( $var )` unescaped: 0. |
| BOT-escape-esc_url_raw | PASS | `(echo\|print) esc_url_raw(`: 0. |
| BOT-escape-__ | PASS | `(echo\|print\|<?=) (__\|_x\|_n\|_nx)(`: 0. Concatenated `__()` in output: 0 (echo classifier). |
| BOT-escape-_e | PASS | `(^\|[^a-z_])(_e\|_ex)\s*\(`: 0. |
| BOT-escape-json | PASS | `json_encode(` without `wp_`: 0. `JSON_UNESCAPED\|JSON_HEX`: 0. `wp_json_encode` is echoed twice: import-ui.php:342 inside `esc_attr()`, and options-pages.php:1243, a JSON file download with an attachment disposition. |
| BOT-escape-html | PASS | `esc_html*( '…<tag` (HTML pushed through esc_html): 0. HTML goes through `wp_kses( …, vergeml_kses_allowed() )` (42 calls); the allow-list has no `script`, `iframe` or `on*`. |
| BOT-escape-sanitizing-functions | PASS | `(echo\|print) sanitize_`: 0. |
| BOT-file-uploader | PASS | `move_uploaded_file\|wp_handle_upload\|copy( $_FILES`: 0. The one upload read is a settings JSON: `is_uploaded_file()` + `file_get_contents` capped at 1 MB, never saved (options-pages.php:1295-1310). |
| BOT-unfiltered-uploads | PASS | `ALLOW_UNFILTERED_UPLOADS\|unfiltered_upload\|map_meta_cap\|user_has_cap\|add_cap(`: 0. |
| BOT-libraries-dev-versions | PASS | No composer.lock, package.json or vendor/. The only bundled code library, natural-compare-lite, is from stable 1.4.0, not a beta or RC. |
| BOT-libraries-not-maintained | PASS | natural-compare-lite released 1.4.1 on 2026-09-02 (npm registry), so it is maintained. The fonts (Inter, webbistro) are not code libraries. |
| BOT-libraries-out-of-date | FAIL | js/eml-media-models.js:265-320 carries natural-compare-lite 1.4.0's `getCode` (`code = alphabet && alphabet.indexOf(…)`, :286); 1.4.1 is the latest stable. See F6. |
| BOT-unsafe-sql | PASS | `sql.js`: 207 query calls; 187 have `$wpdb->prepare(` as their argument. The other 20 are string literals whose only interpolation is a core table property (`{$wpdb->posts}`, `{$wpdb->options}`, `{$wpdb->postmeta}`, `{$wpdb->usermeta}`, `{$wpdb->termmeta}`, `{$wpdb->sitemeta}`), for example ai-screen.php:117 and uninstall.php:36. Plugin tables are bound as `%i` (Requires at least 6.5 ≥ 6.2). Interpolation inside `prepare()` is only core table properties or placeholder lists. `$wpdb->insert/update/delete` use format arrays. |
| BOT-sql-array-placeholders | PASS | Every `IN (` built with `implode` is `implode( ', ', array_fill( 0, count( $x ), '%d'\|'%s' ) )`, 45 lines across 17 files; one of them is the multi-row VALUES template at librarian.php:1790 (placeholders only). |
| BOT-heredoc | PASS | `<<<`: 0. |
| BOT-php-short-tags | PASS | `<?` not followed by `php`, and `<?=`: 0. |
| BOT-php-settings-globally | PASS | `ini_set\|ini_alter\|dl(\|wp_raise_memory_limit\|set_error_handler\|set_exception_handler`: 0. `set_time_limit` ×4, each inside a cron job function and guarded by `vergeml_can_set_time_limit()` (ai-background.php:351, guide.php:1702, plan-tree.php:349, :860). `register_shutdown_function` ×1, booked only during a run (ai-background.php:206). `ignore_user_abort` ×1, inside the export download (options-pages.php:1236). The crash watchdog is gone: core/watchdog.php is 39 lines with a constant-only safe mode. Advisory A1: a stale comment still describes the old watchdog. |
| BOT-php-timezone | PASS | `date_default_timezone_set`: 0. |
| BOT-php-error-reporting | PASS | `error_reporting\|display_errors\|set_error_handler`: 0. |
| BOT-debug-code | PASS | PHP `error_log\|var_dump\|print_r\|var_export\|debug_print_backtrace\|debug_backtrace\|trigger_error`: 0. JS `console.*\|debugger;\|alert(`: 0. |
| BOT-main-file-convention | PASS | The zip's top folder is `vergelabs-media-library/` and its main file is `vergelabs-media-library.php`. |
| BOT-incomplete-headers | PASS | Plugin Name, Plugin URI, Description, Version, Requires at least, Requires PHP, Author, Author URI, Text Domain, Domain Path (`/languages` exists), License and License URI are all present (vergelabs-media-library.php:3-14). No `Update URI`, no `Network:`, no `Tested up to`. |
| BOT-incomplete-readme | PASS | Header block (:1-9), short description (:11, 92 characters), Description, External services, Installation, FAQ, Screenshots, Upgrade Notice, Changelog. |
| BOT-no-gpl-license | PASS | `GPLv2 or later` in the header and the readme. |
| BOT-stable-tag | PASS | 4.1.0 = 4.1.0. |
| BOT-license-mismatch | PASS | `License:` is identical (GPLv2 or later). Plugin Check compares `License`, not the URI (Plugin_Readme_Check `license_mismatch`). Advisory A2: the License URIs differ. |
| BOT-curl | PASS | `curl_`: 0. |
| BOT-wp-enqueue | PASS | `<script` ×2: the Underscore templates `type="text/html"` on `print_media_templates` (media-templates.php:19, :93), which is core's own pattern. The `script_loader_tag` string at neighbours.php:196 only adds attributes to the plugin's own tags. `<style\|<link…stylesheet`: 0. `wp_add_inline_script`: 0. `wp_add_inline_style` ×2, both constant or `sanitize_html_class` built, wrapped in `wp_strip_all_tags` (admin-menu.php:88, media-list.php:300). JS `createElement('script'\|'style'\|'link')`: 0. |
| BOT-i18n-variables | PASS | `i18n.js`: 0 non-literal text or domain arguments in 2,110 calls. |
| BOT-text-domain | PASS | Header `vergelabs-media-library` = slug; 0 mismatched domains. |
| BOT-load-plugin-textdomain | PASS | `load_plugin_textdomain\|load_textdomain`: 0. |
| BOT-contributors | PASS | `Contributors: vergelabsdev`; profiles.wordpress.org/vergelabsdev answers 200. |
| BOT-tested-up-to | PASS | `Tested up to: 7.1` = the current major (7.1.3 is offered). |
| BOT-update-checker | PASS | See POST-85: 0. |
| BOT-unneeded-folders | PASS | Top level: SECURITY.md, changelog.txt, core/, css/, fonts/, images/, index.php, js/, languages/, readme.txt, uninstall.php, the main file, wpml-config.xml. No tests/, docs/, node_modules/, .github/, .claude/ or vendor/. Hidden files: 0. |
| BOT-not-permitted-files | PASS | Extensions: php 68, js 43, css 22, txt 3, woff2 2, gif 2, md 1, pot 1, xml 1, eot 1, svg 1, ttf 1, woff 1. SECURITY.md is on Plugin Check's allowed root markdown list (File_Type_Check:581). No .sh, .exe, .zip, .phar, .log, .sql or .env. |
| BOT-premium-source | PASS | No premium library or add-on code (`vgmlpro`/Pro grep: only the column name reference, A3). |
| BOT-gpl-non-compatible | PASS | GPLv2+ (EML fork), MIT (natural-compare-lite), OFL 1.1 (Inter, licence file shipped). Credited at readme.txt:119-121. |
| BOT-gpl-no-public-source | PASS | Nothing is minified or compiled: 0 `*.min.*`, 0 lines over 1,000 characters in js/ and css/. The source repo is linked at readme.txt:21. |
| BOT-gpl-composer-json | N/A | No `vendor/`, no Composer. |
| BOT-saving-in-plugin-folder | PASS | File writes: `wp_tempnam()` (ai.php:1078, folder-tools.php:243), `php://output` and `php://temp` (import-csv.php:228, :258-264), `WP_Filesystem_Direct::move` inside uploads (rename-file.php:388, off unless `VERGEML_FILE_RENAME`). No write targets `plugin_dir_path`, `__DIR__` or `WP_PLUGIN_DIR`. |
| BOT-determine-locations | FAIL | `WP_PLUGIN_DIR` ×3 (get-help.php:114, system-report.php:129, :136). `ABSPATH . 'wp-admin/includes/…'` ×8 is core's documented include pattern, so it is acceptable. Literal `wp-content` and `/uploads/` appear in comments only. See F3. |
| BOT-uninstall | PASS | uninstall.php:3-5 `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;`. |
| BOT-trialware | PASS | Every licence-key gate fronts a service call: `vergeml_ai_unseal(…license_key)` at ai.php:518 (describe), filing.php:1768/:3139, folder-talk.php:443/:2341, guide.php:875, plan-tree.php:1032/:1093, search-meaning.php:125/:194, brief.php:331; `vergeml_guide_can_profile` says "Confirming never needs one" (guide.php:1277-1280). JS gates on `licensed` only on buttons that call the service (vergeml-brief.js:269/:277 re-describe and test; vergeml-folders.js:189/:659 the AI planner). The local features (tree, smart folders, health, importer, galleries, MIME settings, date-and-type Librarian) have no gate. 0 hits for a Pro or quota hook. Advisory A3. |
| BOT-serviceware | PASS | The service does the AI work (describe, embed, plan, folder talk, name-group); the plugin sends pictures or labels and stores the answers. The licence endpoints (/licence, /api/trial, /api/connect/exchange) only administer access to that service. Documented at readme.txt:51-77. |
| BOT-tracking-consent | PASS | Nothing is contacted without a user action or a key the user entered. `admin_init` remote senders: only `vergeml_stats_maybe_refresh`, which needs the opt-in (off by default, instrument.php:45, :251-258). Credit checks need a stored key (ai.php:150-160). Known-issues runs only on a nonce'd button press (get-help.php:289). Trial, connect and help each start from a button (offer.php:146, connect.php:96, get-help.php:194). Activation makes no remote call (vergelabs-media-library.php:318-344, :354-392). Proven at runtime by R3. |
| BOT-executable-code | PASS | `eval(\|create_function(\|assert(`, backticks, `include` of downloaded files: 0. `base64_decode` ×2, both data (the licence seal ai.php:355, packed floats organize.php:2403). `<iframe`: 0 (PHP and JS). `Plugin_Upgrader`: 0. |
| BOT-hijack-admin | PASS | 9 `admin_notices` callbacks, each conditional (screen, query flag or capability). The success notices carry `is-dismissible`. The safe-mode and requirements notices (watchdog.php:29-38, vergelabs-media-library.php:1604-1616) are error states that say how to resolve them and disappear when resolved. `remove_all_actions`: 0. The plugin removes only its own notice callbacks on its own screens (admin-shell.php:479-503). `css-hide.js`: the only hidden core chrome is `#wpfooter` on the media grid (css/eml-admin-media.css:91). External menu links: 0. Review 6 LIKELY-QUESTION 1 (media-modal CSS, the submenu style) is unchanged; see A8. |
| BOT-wp-load | PASS | `wp-load\|wp-config\|wp-blog-header\|wp-settings.php` as code: 0; all 12 hits are comments or a translated string naming wp-config.php. No standalone endpoint: every PHP file is ABSPATH-guarded. |
| BOT-wp-debug-clean | RUNTIME | R2. |
| BOT-url-shorteners | PASS | The 19 shortener hosts from PCS ShortURL, in all files: 0. |
| BOT-third-party-ads | PASS | `gtag\|googletagmanager\|google-analytics\|fbq(\|hotjar\|mixpanel\|segment.com\|clarity.ms\|posthog\|sentry`: 0 in code. |
| BOT-credit-links | PASS | No `wp_footer`/`wp_head`/`the_content` output hooks. The front-end output is the gallery block and shortcode only, with no link back (`powered by\|made with`: 0). |
| BOT-minification | PASS | 0 `*.min.*`; 0 lines over 1,000 characters; `p,a,c,k,e\|eval(\|new Function(`: 0 in js/. |
| BOT-core-scripts | PASS | All assets are local (BOT-calling-files-remotely). Core scripts are used as dependencies. |
| BOT-service-docs | PASS | readme.txt:51-77 names each host, what is sent, when, and the terms and privacy links. The screens state the requirement: ai.php:1954-1957 ("No licence on this site yet…"), the Licence screen (licence-page.php), and the trial box (offer.php). Advisory A4. |
| BOT-service-iframe | PASS | `<iframe`: 0. `sslverify => false`: 0. `'http://` endpoints: 0. The service URL is forced to https (ai.php:315-318, get-help.php:189). Advisory A4. |
| BOT-raw-code-input | PASS | No field stores or echoes raw HTML, JS, CSS or PHP. Textareas are the help question (`sanitize_textarea_field`, get-help.php:204), readonly reports (`esc_textarea`) and alt text (`sanitize_text_field`, quick-edit.php:200). Colours are validated as `#rrggbb` (rest-tree.php:78-83). |
| BOT-include-plugins-themes | PASS | No bundled plugin zips, no TGMPA, no installer. |
| BOT-custom-post-types-prefix | FAIL | `register_post_type`: 0. Taxonomies are registered from the `vergeml_taxonomies` option. New ones get `vgml_` (guide.php:2341-2345), but the default key is `media_category` (vergelabs-media-library.php:1198, rewrite slug :1227). See F5. |
| BOT-sessions | PASS | `session_start\|$_SESSION`: 0. |
| BOT-five-star | PASS | Plugin Check's pattern `(wordpress\|wp)\.org/.*reviews/\?filter=5` and `rate=5\|#new-post\|five star`: 0. |
| BOT-name-generic | PASS | "VergeLabs Media Library": an original brand placed first, then what it is. |
| BOT-name-unrelated | PASS | The name (media library) matches the description (folders, categories and AI alt text for the media library; header :5, readme :11). |
| BOT-name-keyword-stuffing | PASS | The name is 3 words. Tags are 5 distinct terms (readme.txt:3). The short description has no repeated keywords. |
| BOT-name-too-similar | PASS | Directory search: no plugin named or slugged "VergeLabs"; the nearest names (FileBird, Media Library Folders, Enhanced Media Library) do not share the distinctive part. |
| BOT-name-trademark | PASS | Plugin Check trademark list: 0 hits. No "WordPress", "WP", "Plugin", "Free" or "Best" in the name or slug. |
| BOT-name-owner | PASS | The brand is consistent across Author, Author URI (vergelabs.nl), Plugin URI (vergelabsmedia.com), Contributors (vergelabsdev) and SECURITY.md contacts. The account email domain still has to match (POST-3). |
| BOT-description-superlatives | PASS | `best\|#1\|number one\|the only\|the most\|first option\|better than\|unique\|ultimate\|fastest\|leading\|perfect` in readme.txt and the header Description: 0. |
| BOT-rel-endpoints | FAIL | REST (85 handlers), AJAX (7) and admin_post (10) all check capability (above). Two `admin_init` callbacks change state with no `current_user_can`, reachable logged out via `/wp-admin/admin-post.php`. See F4. |
| BOT-rel-prepare | PASS | See BOT-unsafe-sql: 0 queries with an interpolated variable outside `prepare()`. |
| BOT-rel-file-ops | PASS | 17 file-operation calls (see BOT-saving-in-plugin-folder). No path or URL comes from request data. The ZIP reads `get_attached_file()` of attachments in a term id the caller names, and refuses paths outside uploads (folder-tools.php:177-188). Settings import reads only `is_uploaded_file()` tmp files. `download_url`, `wp_handle_sideload`, `unlink`, `rename(`, `copy(`: 0. |
| BOT-rel-unserialize | PASS | `unserialize(`: 0. `maybe_unserialize` ×2, both on postmeta values read from the database (health-delete.php:208 via :395-417; health.php:932), never on remote, cookie or request data. |
| BOT-rel-options-meta | FAIL | Request-reachable writes are gated: REST, AJAX and admin_post as above; settings forms with nonce + manage_options (options-pages.php:1214-1456); `attachment_fields_to_save` only inside core's nonce'd `save-attachment-compat` (taxonomies.php:1262). Two `admin_init` callbacks call `update_option` with no capability check. See F4. |

## FAIL

Six findings. F1 to F4 would most likely be raised; F5 is a known product decision that needs an explanation ready; F6 is low.

### F1. Processing the whole `$_GET` stack

**Rows:** BOT-processing-whole-input.

**Rule:** "We strongly recommend you never attempt to process the whole $_POST/$_REQUEST/$_GET stack."

**Instance (1):** `core/admin-menu.php:200`, `foreach ( array_keys( $_GET ) as $key )`, inside `vergeml_admin_menu_redirects()` (admin_init). It walks every query key, keeps those matching `^(vgml|vergeml)[-_]`, and carries them into a 301.

**Fix:** replace the loop with a literal list of the plugin's own query keys, read the way the loop above it (:196-198) reads its own list. The keys the plugin reads anywhere are:

`vergeml_bulk_term`, `vergeml_connect`, `vergeml_connected`, `vergeml_dismiss_neighbours`, `vergeml_done`, `vergeml_licence`, `vergeml_op`, `vergeml_refresh`, `vergeml_skipped`, `vergeml_term`, `vgml-check`, `vgml-help`, `vgml_alt_written`, `vgml_files_back`, `vgml_files_renamed`, `vgml_meaning`, `vgml_moved`, `vgml_renamed`, `vgml_renamed_back`, `vgml_smart`, `vgml_to`, `vgml_trial`, `vgml_unfiled`.

Re-run `grep -rnF 'array_keys( $_' --include=*.php` afterwards; it must return 0.

### F2. Free-text input not sanitised at first use

**Rows:** BOT-sanitize, POST-53.

**Rule:** "Input data must be Sanitized, Validated, and Escaped on output." CI-sanitize-input: "Data that is input … must be sanitized as soon as possible."

**Instances.** Each param is typed only as `string`, which REST casts but does not clean.

1. `core/rest-folders.php:193`: `$name = trim( (string) $request->get_param( 'name' ) );` goes to `wp_insert_term()` at :206 and `wp_update_term()` at :227. Core's `pre_term_name` filters clean it later, but a reviewer reads the handler.
2. `core/folder-talk.php:2877`: `'text' => (string) substr( (string) $turn['text'], 0, 400 )` (conversation history).
3. `core/folder-talk.php:2882`: `(string) $request->get_param( 'instruction' )`. Both 2 and 3 are sent to the service in `vergeml_talk_propose()` (:430-470) with only a trim.
4. `core/import-ui.php:85`: `vergeml_csv_parse( (string) $request->get_param( 'text' ) )`. The cells are trimmed only (`core/import-csv.php:309-310`; folder parts at :422). They are stored whole in the option `vergeml_import_csv` (`core/import-csv.php:525`) and later passed to `wp_insert_term()` (`core/import.php:176`). The raw cell `$raw` is also put into a "Line %1$d: “%2$s” is not a file id." message returned to the browser (`core/import-csv.php:343-347`).

**Fix:**
- `sanitize_text_field()` on the folder name (1), each CSV path part and the id cell (4).
- `sanitize_textarea_field()` on the instruction and each history text (2, 3), as `vergeml_guide_turn_apply()` already does for the same kind of text (guide.php:1557).
- Or declare `'sanitize_callback' => 'sanitize_text_field'` / `'sanitize_textarea_field'` on the args `name` (rest-folders.php:28-44), `instruction` (folder-talk.php:2769-2785) and `text` (import-ui.php:37-58).

### F3. Plugin location from an internal constant

**Rows:** BOT-determine-locations.

**Rule:** "Using WordPress content directory functions to determine locations of folders and files." The developer handbook page it points to says WP_PLUGIN_DIR and the like "should not be used directly by plugins".

**Instances (3):** `get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false )` at `core/get-help.php:114`, `core/system-report.php:129` and `core/system-report.php:136`.

**Fix:** read the headers from `get_plugins()`, which is already loaded there (`require_once ABSPATH . 'wp-admin/includes/plugin.php'`). Use `$all = get_plugins();`, then `$all[ $file ]['Version']` / `['Name']`. Network-active plugins are in the same array. No constant is needed.

### F4. State changed on `admin_init` without a capability check

**Rows:** BOT-rel-options-meta, BOT-rel-endpoints, POST-12, POST-16.

**Rule:** the release-time AI review's risk patterns "Unprotected options/metadata modifications" and "REST/AJAX/admin endpoints without capability checks".

**Instances (2).** `admin_init` also fires for a logged-out request to `/wp-admin/admin-post.php`.

1. `core/librarian.php:326-354`, `vergeml_librarian_housekeeping()`: `vergeml_librarian_maybe_install()` (dbDelta), `vergeml_librarian_prune()` (deletes old batches), then `update_option( VERGEML_LIBRARIAN_OPTION, … )` at :353.
2. `core/ai-index.php:895-919`, `vergeml_index_maybe_migrate()`: `vergeml_index_maybe_install()` (dbDelta), then `vergeml_index_migrate_step()`, which writes index rows and `update_option( VERGEML_INDEX_OPTION, … )` at :874.

Neither takes input, so nothing is exploitable. But an anonymous request can trigger writes, which is the pattern the release scanner names. Review 6 listed it as acceptable and suggested parity; the release scan runs on every update, so close it.

**Fix:** start both with the guard `core/organize.php:303` already uses: `if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) { return; }`. The alternative is to move the schema check to the version-change path `vergeml_provision_site()` and the pruning to a cron event.

### F5. Unprefixed default taxonomy `media_category`

**Rows:** BOT-custom-post-types-prefix, BOT-generic-names.

**Rule:** "Use of a unique prefix for everything the plugin defines in the public namespace (options, functions, global variables, constants, post meta, etc.)" and "All plugins must have unique function names, namespaces, defines, class and option names."

**Instances:**
- `vergelabs-media-library.php:1198` (default taxonomy key) and `:1227` (rewrite slug), registered at `:651`.
- `wpml-config.xml:7` names it.
- The fallbacks `'media_category'` in brief.php:375, filing.php:1346, guide.php:784, health.php:915, journey.php:156, :1545, media-list.php:108, :453, :634, :674, multilingual.php:72, plan-tree.php:1323.

Taxonomies the plugin creates later are prefixed (`vgml_`, guide.php:2345).

**Fix:** this is a product decision. The key is shared with Enhanced Media Library, and FileBird detects it, so renaming would orphan every migrating user's folders. Keep it and have the explanation ready for the reply: "media_category is the taxonomy Enhanced Media Library 2.x registered and that existing sites already hold terms in; this fork keeps it so a site's folders survive the switch in both directions; every taxonomy this plugin creates itself is prefixed vgml_ (core/guide.php:2345)". Point to readme.txt:105, :107 and :172, and to uninstall.php:65-67, which keeps those terms. The alternative is `vgml_media_category` plus a migration.

### F6. Bundled natural-compare-lite is one release behind

**Rows:** BOT-libraries-out-of-date.

**Rule:** "Libraries: Out of Date": "Please upgrade to the latest stable version for better support and security."

**Instance (1):** `js/eml-media-models.js:265-320`. The `getCode` body matches 1.4.0 (2015-10-26): `code = alphabet && alphabet.indexOf(str.charAt(pos))` at :286. The latest stable is 1.4.1 (2026-09-02). Its only change: an `alen` term, a `pos < str.length` guard, and "push the rest past the alphabet", which affects a custom `String.alphabet`. The plugin never sets `String.alphabet`, so behaviour is unchanged today. Low likelihood of being raised.

**Fix:** port the three changed lines from 1.4.1 and keep the local `leadingZeros` lines (:278, :300-301). Name the version in the credit comment ("natural-compare-lite 1.4.1, modified: leading zeros stripped") and in readme.txt:121.

## RUNTIME

- **R1. Plugin Check on the built zip.** Rows: POST-7, POST-49, POST-51, POST-53 (accessibility part), POST-54, POST-66, POST-71, POST-74, POST-75, POST-76, POST-79.
  - Run: `node tools/plugin-check.mjs <url of dist/vergelabs-media-library-4.1.0.zip>`, with the PHPCS lock patch (`tools/plugin-check-blueprint.json`), all five categories (accessibility, general, performance, plugin_repo, security).
  - It proves the rows when every check answers (29/29 asked = ran = answered) and there are 0 ERROR in plugin_repo and security.
  - Read every warning; none may be explained away by an annotation. Since round 1, `phpcs:ignore` counts as nothing.
- **R2. Clean-site WP_DEBUG walk.** Rows: BOT-wp-debug-clean, POST-54.
  - Run: `node tools/clean-walk.mjs` on a fresh Playground site from the zip with `WP_DEBUG` and `WP_DEBUG_LOG` on.
  - Cover single-site activation and multisite "Network Activate"; all plugin screens plus core media, upload, list and the block editor's media modal as administrator; the media screens as editor and author.
  - It proves the rows when `debug.log` is empty (no notice, deprecation or `_doing_it_wrong`, for example on `register_rest_route`), the four plugin tables exist after activation, and there is no console error.
  - Repeat on PHP 7.4 and 8.5. Round history shows 8.5-only deprecations.
- **R3. Outbound logger, no licence.** Confirms the static PASS of BOT-tracking-consent, BOT-undocumented-3rd-party and BOT-service-docs.
  - Run R2's walk with the mu-plugin that logs `pre_http_request`.
  - It proves the rows when the plugin makes 0 requests from activation through all screens without a key. With the stats opt-in left off, it makes 0 requests to `ai.vergelabs.nl` or `vergelabsmedia.com`.
  - Then press Check for known problems once: exactly one GET to `https://ai.vergelabs.nl/v1/known-issues`.

## Advisories (not rule failures; cheap to close before upload)

- **A1.** `vergelabs-media-library.php:1622-1626` says "after two fatal errors in an hour none of it loads -- the site comes back, the plugin stays active, and the notice in core/watchdog.php explains why". The code no longer does that (core/watchdog.php is constant-only; readme.txt:99 says recovery mode handles fatals). A reviewer reading the main file would re-raise review 6's watchdog question. Rewrite the comment.
- **A2.** The License URI is `http://www.gnu.org/licenses/old-licenses/gpl-2.0.html` in the header (:14) but `http://www.gnu.org/licenses/gpl-2.0.html` in the readme (:9). Make them identical.
- **A3.** `core/media-list.php:41` ("this plugin and its pro half") and `:52` (`'vgmlpro_source', // pro/includes/provenance.php`), plus `js/vergeml-quick-edit.js:55` (`.column-vgmlpro_source`), reference a paid add-on's column. This is harmless, but it invites a trialware question. Reword the comment so it says "a column another plugin may add".
- **A4.** `VERGEML_SITE_URL` (connect.php:39), `VERGEML_AI_STREAM` (guide.php:856) and `VERGEML_KNOWN_ISSUES_URL` (get-help.php:58) accept `http://`, while `VERGEML_AI_SERVICE` is forced to https. Apply the same https rule to all four.
- **A5.** 84 annotations name sniffs but give no reason, for example import-sources.php:374 (`InterpolatedNotPrepared` on `SHOW TABLES LIKE %s`). rename.php:95 and :149 give the reason "this plugin's own table", but the interpolated part is `{$wpdb->posts}`. Add or correct the reasons; annotations were round 1's lost class.
- **A6.** `js/vergeml-gallery.js:42`, `:43`, `:123-125` hard-code "Previous images", "Next images", "Previous image", "Next image" and "Close" on the front end. Pass them through the existing `wp_localize_script` data or `wp.i18n`.
- **A7.** `core/transport.php:64` reads `php://input` and hands `params` whole to `rest_do_request()`. This is safe, because each route's own schema and permission run (review 6 LIKELY-QUESTION 9), but have the one-sentence explanation ready.
- **A8.** Review 6 LIKELY-QUESTION 1 is unchanged:
  - `$('body').addClass('eml-media-css')` at js/vergeml-media-views.js:943, so the 49 `.eml-media-css` rules in css/eml-admin-media.css reach every media modal
  - `vergeml_hide_submenu` on every admin screen (admin-menu.php:80-89)
  - `#wpfooter` hidden on the grid (css/eml-admin-media.css:91)
