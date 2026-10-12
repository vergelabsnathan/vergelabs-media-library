# Round 6 audit: PC, PCS, SEC, REST rows (VergeLabs Media Library 4.1.0)

Archive judged: `C:/Users/viete/AppData/Local/Temp/vgml-r6-archive` (147 files: 68 PHP, 43 JS, 22 CSS). Nothing else was read as evidence. Audit date 2026-10-12.

Scope: every rulebook row whose ID starts with PC (85), PCS (79, including the 14 PCS-WCA rows), SEC (17) and REST (12). The rulebook has no row IDs that start with "S" other than SEC, and none that start with "URL" (the 12 `URL:` lines are source notes under Part A headings, and "URL" is a column header in both coverage tables). Both get one N/A row at the end of the table.

**Verdicts: 180 PASS, 4 FAIL, 5 RUNTIME, 6 N/A (195 rows).**

## How the evidence was produced

Every PASS cites one of these runs or a grep. The scripts and raw outputs are in `C:/Users/viete/AppData/Local/Temp/vgml-r6-pcaudit/`.

- **[GREP]** Greps over the archive and over two mirrors that `strip.js` built from it, with line numbers kept: `code/` has comments and string contents blanked, so a hit is real code; `nc/` has only comments blanked, so string literals are kept.
- **[PHPCS-I]** PHPCS 3.13.6 run in php-wasm 8.3 on all 68 PHP files with `--ignore-annotations`, using the standards bundled in Plugin Check 2.1.0 (WPCS 3.4.1, VIPCS 3.1.0, the PluginCheck sniffs). Ruleset `r6.xml`: Plugin Check's `plugin-review.xml`, plus the sniff of every PHPCS-based Plugin Check check (EscapeOutput, SafeRedirect, I18n with text domain `vergelabs-media-library`, PrefixAllGlobals with `vergeml`/`vergelabs`/`vgml`, DirectDatabaseQuery, SlowDBQuery, WPQueryParams, EnqueuedResources, EnqueuedResourceParameters, Localhost, Offloading, EnqueuedResourceOffloading, SettingSanitization, WriteFile, AIProvider, PHPErrorReporting, DirectDB, and the unregistered VerifyNonce). **Totals: 22 errors, 558 warnings.** Full list: `out/ignored-list.tsv`.
- **[PHPCS-H]** The same run with annotations honoured: 2 errors (both VerifyNonce, a sniff Plugin Check does not register) and 0 warnings. The plugin carries 137 `phpcs:ignore` lines and 119 `phpcs:disable` blocks.
- **[CANARY]** The same PHPCS stack run on a canary file with one violation per sniff (`canary/canary.php`). Every sniff named in the PCS rows fired except VerifyNonce's isset-AND form, and VerifyNonce fired twice on the archive anyway, so a 0 in [PHPCS-I] is a real 0.
- **[PC]** Plugin Check 2.1.0, the current wordpress.org stable, run in Playground (PHP 8.3, WordPress 7.1.x) with all five categories ticked. A driver (`pc-drive.mjs`) recorded every per-check AJAX reply. With the plugin inactive: 29 of 29 checks answered "Checks run successfully", **0 errors and 0 warnings**. With the plugin active, the same 29 again gave 0, but the 5 runtime checks answered HTTP 400 "0" and did not run. On the canary plugin in the same server, 18 checks reported findings (late_escaping 4, plugin_review_phpcs 34, direct_db 2 and so on), so this Playground run can fail.
- **[PROBE]** `probe/run.mjs` uses a Playground blueprint to activate the archive and assign a media folder to an attachment, once without `VERGEML_SAFE_MODE` and once with it.
- **[WP-API]** `api.wordpress.org/core/version-check/1.7/` currently offers 7.1.3. profiles.wordpress.org/vergelabsdev returns 200; a made-up username returns 404, which shows the check can tell them apart.

## Verdict table

| ID | Verdict | Evidence |
|---|---|---|
| PC-i18n_usage | PASS | [PHPCS-I] WordPress.WP.I18n with text_domain=vergelabs-media-library: 0 over 68 files. [CANARY]: TextDomainMismatch and MissingTranslatorsComment fire. [PC] i18n_usage: 0. |
| PC-php_error_reporting | PASS | [GREP] code/: `error_reporting(`, `ini_set(`, `ini_alter(`, `define(` or `const` of WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY or SCRIPT_DEBUG: 0. [PHPCS-I] PHPErrorReporting: 0. [PC]: 0. |
| PC-react_usage | PASS | [GREP] js/: `ReactDOM`, `findDOMNode`, `createFactory`, `react-dom`, `__SECRET_INTERNALS`, `react.production`: 0 files. The block uses the `wp.element` global (js/vergeml-gallery-block.js). This check is trunk-only; Plugin Check 2.1.0 does not ship it. |
| PC-ai_provider | PASS | [GREP] every file, case-insensitive: openai.com, anthropic.com, generativelanguage, api.x.ai, mistral.ai, cohere, groq.com, perplexity.ai, deepseek.com, openrouter: 1 hit, a comment (core/ai.php:16), which the sniff does not read. The service host is ai.vergelabs.nl (core/ai.php:313,317). [PHPCS-I] AIProvider: 0. [PC]: 0. |
| PC-enqueued_scripts_size | RUNTIME | Plugin Check's runtime check answered HTTP 400 in Playground and did not run. Static: no script loads on every front-end page (see RUNTIME section). |
| PC-enqueued_styles_size | RUNTIME | As above. |
| PC-enqueued_scripts_scope | RUNTIME | As above. The 2 `wp_enqueue_scripts` callbacks are vergeml_rtl_styles (vergelabs-media-library.php:93, which only adds RTL data to registered handles) and vergeml_builder_divi (core/page-builders.php:160, which runs only in the Divi builder and for users with upload_files). |
| PC-enqueued_styles_scope | RUNTIME | As above. The gallery style is registered on `init` and enqueued only from the block's render callback (core/gallery-block.php:30-51). |
| PC-non_blocking_scripts | RUNTIME | As above. The front-end gallery script is registered with in_footer=true and no strategy (core/gallery-block.php:45-51), so a page holding the block would likely get the "loaded in the footer, consider defer" advisory. |
| PC-enqueued_scripts_in_footer | PASS | [PHPCS-I] EnqueuedResourceParameters: 0 over 46 script and 22 style register/enqueue calls. [CANARY] MissingVersion and NotInFooter fire. [PC]: 0. |
| PC-enqueued_resources | PASS | [PHPCS-I] EnqueuedResources: 0. [PC]: 0. The only raw `<script` tags in PHP are 2 `type="text/html"` Underscore templates (core/media-templates.php:19,93). |
| PC-performant_wp_query_params | PASS | [PHPCS-I] 16 warnings, no errors. tax_query: folder-tools.php:168, gallery-block.php:220, medialist.php:141, nl-commands.php:274,290, taxonomies.php:523. meta_query: quarantine.php:157,273,339, smart-folders.php:128,136,147, utilities.php:197. meta_key and meta_value: guide.php:1986. exclude: private-folders.php:171. suppress_filters=true: 0. These are advisory warnings, and filtering media by taxonomy and meta is what the plugin does. [PC] with annotations: 0. |
| PC-code_obfuscation | PASS | [GREP] every file: `@Zend;`, `This file was encoded by`, `sourceguardian`, `sg_load`, `$__x=`, `ionCube`, `eval(function(p,a,c,k,e`: 0. [PC]: 0. |
| PC-direct_file_access | PASS | [GREP] code/ first statement of each PHP file: 67 of 68 open with the guard (66 `defined( 'ABSPATH' )`, plus uninstall.php:3 `WP_UNINSTALL_PLUGIN`). index.php contains only a comment. [PC]: 0. |
| PC-external_admin_menu_links | PASS | [GREP] `add_menu_page(`: 1 hit (core/admin-menu.php:50). Its slug is VERGEML_MENU = 'vergelabs-media' (vergelabs-media-library.php:105), not a URL. [PC]: 0. |
| PC-menu_image_icon | PASS | The icon is vergeml_menu_icon(), which returns `data:image/svg+xml;base64,...` (core/admin-menu.php:102-119). No raster file. |
| PC-file_type | PASS | Sub-rows below. [PC] file_type: 0. |
| PC-file_type/compressed_files | PASS | `find` for zip, gz, tgz, rar, tar or 7z: 0. The archive's extensions are css, eot, gif, js, md, php, pot, svg, ttf, txt, woff, woff2 and xml. |
| PC-file_type/phar_files | PASS | `*.phar`: 0. |
| PC-file_type/vcs_present | PASS | `find -name '.*'`: 0, so no .git, .svn, .hg or .bzr. |
| PC-file_type/hidden_files | PASS | Hidden files: 0. |
| PC-file_type/application_detected | PASS | exe, sh, bin, dmg, iso, so, pkg, deploy, dll, bat, cmd, ps1, log, sql, env: 0. |
| PC-file_type/badly_named_files | PASS | Paths matching `[^A-Za-z0-9._/-]`: 0. |
| PC-file_type/case_sensitive | PASS | Paths that collide when lowercased: 0. |
| PC-file_type/library_core_files | PASS | File names matching the core-library list (jquery*, backbone, underscore, moment, lodash, getid3, PHPMailer and so on): 0. |
| PC-file_type/missing_composer_json_file | N/A | No vendor/ directory and no Composer use. |
| PC-file_type/ai_instruction_directory | PASS | Directories in the archive: core, css, fonts, images, js, languages. No .cursor, .claude, .aider, .continue, .windsurf or .ai. |
| PC-file_type/github_directory | PASS | No .github directory. |
| PC-file_type/unexpected_markdown_file | PASS | One markdown file, SECURITY.md, which Plugin Check 2.1.0 allows (File_Type_Check.php:581 lists README.md, LICENSE.md, CHANGELOG.md, CONTRIBUTING.md and SECURITY.md). [PC]: 0. |
| PC-plugin_header_fields | PASS | Header at vergelabs-media-library.php:2-14. [PC] plugin_header_fields: 0. |
| PC-plugin_header_fields/name | PASS | "VergeLabs Media Library": 21 latin letters. |
| PC-plugin_header_fields/uri_domain | PASS | Plugin URI https://vergelabsmedia.com and Author URI https://vergelabs.nl are the owner's own domains. |
| PC-plugin_header_fields/missing | PASS | Description (line 5) and Version 4.1.0 (line 6) are present and not template text. |
| PC-plugin_header_fields/version | PASS | `4.1.0`. |
| PC-plugin_header_fields/network | PASS | No `Network:` header (grep 0), so it is left out as required. |
| PC-plugin_header_fields/requires | PASS | Requires at least: 6.5, Requires PHP: 7.4. |
| PC-plugin_header_fields/license | PASS | License: GPLv2 or later, License URI: gnu.org gpl-2.0. |
| PC-plugin_header_fields/restricted | PASS | Plugin Check 2.1.0 reserves only "Restricted Label", which is absent. The free-text lines 16-25 are not `Key:` headers. |
| PC-plugin_header_fields/textdomain | PASS | Text Domain: vergelabs-media-library, which equals the slug. |
| PC-plugin_header_fields/domain_path | PASS | Domain Path: /languages, and languages/vergelabs-media-library.pot exists. |
| PC-plugin_header_fields/requires_plugins | N/A | No `Requires Plugins:` header. |
| PC-late_escaping | PASS | [PHPCS-I] WordPress.Security.EscapeOutput: 0 across 204 echo/print and 35 printf, with every annotation ignored. [CANARY]: OutputNotEscaped, UnsafePrintingFunction and ExceptionNotEscaped fire. [PC]: 0. The one EscapeOutput annotation (core/icons.php:34) sits on a `return sprintf()`, not on output. |
| PC-safe_redirect | PASS | [GREP] code/ `wp_redirect(`: 0, and `wp_safe_redirect(`: 18. [PC]: 0. |
| PC-plugin_updater | PASS | [GREP] every file, case-insensitive: Update URI, plugin-update-checker, WP_GitHub_Updater, _Plugin_Updater, PluginUpdateChecker, PucFactory, site_transient_update_, pre_set_site_transient_update, auto_update_plugin, update_plugins_, upgrader_pre_download, plugins_api: 0. No files named *updater*. [PC]: 0. |
| PC-plugin_uninstall | PASS | uninstall.php:3 `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }`. [PC]: 0. |
| PC-plugin_review_phpcs | PASS | [PHPCS-H] and [PC]: 0. [PHPCS-I] under plugin-review.xml: 8 errors, all AlternativeFunctions.file_system_operations (each justified under that PCS row), plus warnings that are each justified under their PCS rows: Squiz DiscouragedFunctions 4, ReplacementsWrongNumber 5, NonceVerification 103. |
| PC-direct_db_queries | PASS | [PHPCS-I] warnings only: DirectQuery 217, NoCaching 210, SchemaChange 3. The 3 schema changes are `DROP TABLE IF EXISTS %i` on the plugin's own tables during cleanup and uninstall (core/ai-index.php:55, core/options-pages.php:1616, uninstall.php:116). Every query is prepared or literal (see the PreparedSQL rows). [PC]: 0. |
| PC-direct_db | PASS | [PHPCS-I] PluginCheck.Security.DirectDB: 0. [CANARY]: UnescapedDBParameter fires. [PC]: 0. |
| PC-plugin_readme | PASS | [PC] plugin_readme: 0. Sub-rows below. |
| PC-plugin_readme/name | PASS | readme.txt:1 `=== VergeLabs Media Library ===` matches Plugin Name. |
| PC-plugin_readme/tested_up_to | PASS | readme.txt:5 `Tested up to: 7.1`, and [WP-API] current is 7.1.3, so 7.1 is the current major. |
| PC-plugin_readme/tested_up_to_header | PASS | `Tested up to` in vergelabs-media-library.php: 0. |
| PC-plugin_readme/default_text | PASS | [GREP] readme for the template phrases ("Here is a short description", "Arbitrary section", "A brief description", Lorem, "Upload the plugin files to the"): 0. |
| PC-plugin_readme/license | PASS | readme.txt:8 GPLv2 or later, the same as the header. |
| PC-plugin_readme/stable_tag | PASS | readme.txt:7 `Stable tag: 4.1.0` equals Version. |
| PC-plugin_readme/upgrade_notice | PASS | One entry (readme.txt:149), 139 characters, which is under 300. |
| PC-plugin_readme/contributors | PASS | `Contributors: vergelabsdev`. [WP-API]: the profile returns 200, while the control username returns 404. |
| PC-plugin_readme/tags | PASS | 5 tags (readme.txt:3), within the limit of 5. [PC]: no tag ignored or refused. |
| PC-plugin_readme/requires | PASS | Requires at least 6.5 and Requires PHP 7.4 in both the readme and the header. |
| PC-plugin_readme/short_description | PASS | readme.txt:11 is 92 characters, under 150. |
| PC-plugin_readme/donate_link | N/A | No `Donate link:`. |
| PC-plugin_readme/english | PASS | Common Dutch function words in the readme: 0. [PC]: 0. |
| PC-plugin_readme/parser | PASS | [PC] plugin_readme: 0 parser warnings. |
| PC-localhost | PASS | [GREP] every file, `localhost`, `127.0.0.1`, `.local`: 1 hit, core/offer.php:26. It is a regex that keeps the trial offer off local and test hosts, not an address the plugin calls. The sniff's pattern `//(localhost...)/` does not match it. [PHPCS-I] Localhost: 0. [PC]: 0. |
| PC-no_unfiltered_uploads | PASS | [GREP] `ALLOW_UNFILTERED_UPLOADS` or `unfiltered_upload` in any file: 0. [PC]: 0. |
| PC-trademarks | PASS | [PC] trademarks: 0. The name and slug contain no restricted term. |
| PC-offloading_files | PASS | [GREP] tally of every URL host in PHP, JS and CSS: vergelabsmedia.com 4, github.com 4 (links and a licence comment), ai.vergelabs.nl 4 (the service), wordpress.org 2, w3.org 2 (SVG namespace), wpuxsolutions.com 1, vergelabs.nl 1, opensource.org 1, gnu.org 1. CDN hosts (raw.githubusercontent, jsdelivr, unpkg, googleapis, gstatic, cloudflare, amazonaws, bootstrapcdn, fontawesome, s.w.org, gravatar): 0. Fonts are bundled. [PHPCS-I] Offloading and EnqueuedResourceOffloading: 0. [PC]: 0. |
| PC-write_file | PASS | [PHPCS-I] WriteFile: 0. The only `file_put_contents` writes to `php://output` (core/import-csv.php:228). Temporary files come from `wp_tempnam()` (core/ai.php:1078, core/folder-tools.php:243). [PC]: 0. |
| PC-setting_sanitization | PASS | 6 `register_setting()` calls (core/options-pages.php:20-62), each with a named sanitize callback that exists: vergeml_lib_options_validate (taxonomies.php:299), vergeml_taxonomies_validate (:16), vergeml_tax_options_validate (:363), vergeml_mimes_validate (mime-types.php:16), and vergeml_sanitize_option_array twice (options-pages.php:81). [PHPCS-I] SettingSanitization: 0. [PC]: 0. |
| PC-prefixing | PASS | [PHPCS-I] PrefixAllGlobals: 12 hits, all NonPrefixedHooknameFound, where the plugin fires core or third-party hook names on purpose. Plugin Check downgrades prefixing to warnings. See PCS-WordPress.NamingConventions.PrefixAllGlobals for the list. 1,090 global functions and 6 classes are prefixed vergeml, constants VERGEML_, no namespace. [PC]: 0. |
| PC-minified_files | PASS | PHP lines over 1,000 characters: 0. `*.min.*` files: 0. [PHPCS-I] tokenised all 68 files with 0 Internal.Tokenizer.Exception. [PC]: 0. |
| PC-plugin_content | **FAIL** | Five-star links: 0 (grep `filter=5`, `#new-post`). block.json files: 0, so Plugin Check 2.1.0 has nothing to inspect and reports 0. But the plugin's one editor block is registered with `'api_version' => 2` (core/gallery-block.php:112), and the JS registration has no apiVersion (js/vergeml-gallery-block.js:209), while the plugin declares Tested up to 7.1. See FAIL 4. |
| PC-wp_functions_compatibility | PASS | [PC] wp_functions_compatibility against Requires at least 6.5: 0. Spot checks: `%i` placeholders need WP 6.2; `str_contains` (3 calls) is polyfilled since WP 5.9. |
| PC-nonce_verification (UNREGISTERED) | PASS | [PHPCS-I] and [PHPCS-H] VerifyNonce: 2 hits. core/get-help.php:289 and core/options-pages.php:1071 assign `isset( ... ) && wp_verify_nonce( ... )` to $check and $refresh, and each only decides whether to refresh a read-only view. The return value is used, so both are false positives of the "unconditional call" heuristic. The sniff is not registered in Plugin Check. |
| PC-ai_mode | N/A | Describes how Plugin Check sends findings to AI triage. It is not a rule about plugin code. |
| PC-ai-late-escaping | PASS | [PHPCS-I] 0 echoed variables, so nothing is escaped early and output later. `_e(`, `_ex(`, `echo __(`: 0. Every printf argument that reaches HTML is wrapped in esc_*() or wp_kses() (see SEC-13). |
| PC-ai-nonce | PASS | 6 wp_ajax handlers. 5 check a nonce and a capability: taxonomies.php:1353 and :1415/:1421, options-pages.php:884, transport.php:46+51, offer.php:147-148. The sixth, vergeml_builder_load_tree on Themify's `wp_ajax_tb_load_editor` (core/page-builders.php:262), reads no input, changes nothing, checks upload_files (:50) and only enqueues assets. wp_ajax_nopriv: 0. |
| PC-ai-sanitization | PASS | [PHPCS-I] ValidatedSanitizedInput: 0. Licence keys go through `sanitize_text_field` before being sealed (core/ai.php:1993, core/connect.php:198). `$_FILES` fields are sanitised and the upload is checked with `is_uploaded_file()` (core/options-pages.php:1287-1293). |
| PC-ai-direct-db | PASS | [PHPCS-I] PreparedSQL NotPrepared and InterpolatedNotPrepared: 0. IN lists use `implode( array_fill( ..., '%d' ) )`. Variable columns are picked from literals and bound as `%i` (core/guide.php:2799-2801). [GREP] SQL built by concatenating a variable: 0. |
| PC-ai-obfuscation | PASS | [GREP] code/ `eval(`, `str_rot13(`, `create_function(`: 0. The one `base64_decode` decodes the plugin's own packed floats (core/organize.php:2403). `base64_encode` builds data URIs only (core/admin-menu.php:119, core/ai.php:1084,1094). |
| PC-ai-updater | PASS | Updater and auto_update hooks: 0 (see PC-plugin_updater). |
| PC-ai-setting-sanitization | PASS | See PC-setting_sanitization: 6 of 6 calls have callbacks. |
| PC-proposed-trialware (open #1313) | PASS | [GREP] code/ `is_pro`, `premium`, `quota`, tier or plan functions: 0. 30 functions read `license_key`. 17 of them send a request to the service with the key (for example vergeml_ai_describe_request, vergeml_plan_ask, vergeml_meaning_vector). The other 13 are status, UI flags or the licence screen (vergeml_shell_credits, vergeml_journey_facts, vergeml_licence_page and so on). vergeml_guide_can_profile (guide.php:1278) only decides whether the service is asked: "Without a licence key the confirm is local" (:1189). The full guideline-5 audit belongs to the G5 and BOT-trialware owner. |
| PC-proposed-ob_start (open #1314) | PASS | [GREP] `ob_start(`: 2. Both are closed by ob_end_clean in the same flow (vergelabs-media-library.php:1003-1012, core/taxonomies.php:1003-1020). |
| PC-proposed-runtime-fatals (open #1273) | **FAIL** | `safe.js` traced every feature-file function that code still loaded in safe mode or without mbstring refers to. Two references are unguarded: callback strings that point at functions in core/taxonomies.php, a file that is not loaded then (vergelabs-media-library.php:660 and :768). [PROBE] proves the first one is fatal on PHP 8.3. Every require/include path is literal or comes from a literal list (vergelabs-media-library.php:374-381). See FAIL 1. |
| PCS-WordPress.DB.PreparedSQL.NotPrepared | PASS | [PHPCS-I]: 0 across 415 `$wpdb` method calls (query 30, get_results 62, get_var 66, get_row 15, get_col 36, prepare 199, insert 4, update 4, delete 2). [CANARY] fires. The 1 stale annotation (uninstall.php:183) sits over a query that is in fact prepared (:184-188). |
| PCS-WordPress.DB.PreparedSQL.InterpolatedNotPrepared | PASS | [PHPCS-I]: 0. The only interpolations are core `$wpdb->posts`, postmeta, options and term tables, and `CREATE TABLE {$table}` strings that go only to dbDelta (ai-index.php:132, librarian.php:176,241, organize.php:259). The 5 stale annotations for this sniff cover statements that no longer need them. |
| PCS-WordPress.DB.PreparedSQLPlaceholders | PASS | [PHPCS-I]: 5 ReplacementsWrongNumber warnings, all where an array is spread into prepare(): librarian.php:1785, search-try.php:58,76, smart-folders.php:323,398. Counted by hand at search-try.php: 50 placeholders and 50 values (:58), 18 and 18 (:76). Quoted placeholders, LIKE without a bound wildcard, `%i` inside IN(), and unescaped `%`: 0. |
| PCS-WordPress.Security.NonceVerification | PASS | [PHPCS-I] Missing 9 (mime-types.php:29, quarantine.php:239, smart-folders.php:1201-1202, taxonomies.php:38,368). These are register_setting sanitize callbacks that options.php has already nonce-checked, or `ajax_query_attachments_args` filters inside core's query-attachments AJAX that only read a view key, run it through sanitize_key and check it against an allow-list. Recommended 94 on 65 lines in 18 files, for example admin-menu.php:167-203, journey.php:443-1184, media-list.php:353-745, taxonomies.php:428-897. Each reads screen state, a post-redirect notice count, a list filter, or the OAuth state and code, which are authenticated by a per-user single-use state compared with hash_equals after manage_options (connect.php:96-160). None of them writes. Every handler that writes has a nonce (see SEC-15). |
| PCS-WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-WordPress.Security.ValidatedSanitizedInput.InputNotValidated | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.Security.ValidatedSanitizedInput.MissingUnslash | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.Security.ValidatedSanitizedInput.InputNotValidatedNotSanitized | PASS | [PHPCS-I]: 0. |
| PCS-Generic.PHP.BacktickOperator | PASS | [GREP] code/ backticks (comments and strings blanked): 0. [PHPCS-I]: 0. |
| PCS-PluginCheck.CodeAnalysis.Heredoc | PASS | [GREP] `<<<` in PHP: 0. |
| PCS-PluginCheck.CodeAnalysis.ShortURL | PASS | [GREP] every file for the 19 shortener hosts: 0. [PHPCS-I]: 0. |
| PCS-Generic.PHP.DiscourageGoto.Found | PASS | [GREP] `goto` as a word in PHP: 0. |
| PCS-WordPress.PHP.DevelopmentFunctions | PASS | [GREP] code/ error_log, var_dump, var_export, print_r, trigger_error, set_error_handler, debug_backtrace, phpinfo, error_reporting: 0. [PHPCS-I]: 0. Not in the sniff's list: one `register_shutdown_function` inside a cron continuation, booked once per request (core/ai-background.php:206). |
| PCS-Generic.PHP.DisallowShortOpenTag | PASS | [GREP] `<?` not followed by php, and `<?=`: 0. |
| PCS-Generic.PHP.DisallowAlternativePHPTags | PASS | [GREP] `<%` and `<script language=php`: 0. |
| PCS-WordPress.Security.PluginMenuSlug | PASS | 12 add_*_page calls (admin-menu.php:50, ai.php:2028, get-help.php:31, guide.php:59, health.php:1237, import-ui.php:22, licence-page.php:20, options-pages.php:117,126,135,172,196), with no `__FILE__` slug. [PHPCS-I]: 0. |
| PCS-WordPress.DB.RestrictedClasses | PASS | [GREP] code/ `new mysqli`, `new PDO`, `PDOStatement`: 0. |
| PCS-WordPress.DB.RestrictedFunctions | PASS | [GREP] code/ mysql_, mysqli_, mysqlnd_, maxdb_ calls: 0. |
| PCS-WordPress.WP.AlternativeFunctions.curl | PASS | [GREP] code/ `curl_*(`: 0. |
| PCS-WordPress.WP.AlternativeFunctions.parse_url | PASS | [GREP] code/ `parse_url(`: 0 (wp_parse_url is used). |
| PCS-WordPress.WP.AlternativeFunctions.unlink | PASS | [GREP] code/ `unlink(`: 0 (wp_delete_file is used). |
| PCS-WordPress.WP.AlternativeFunctions.rename | PASS | [GREP] code/ `rename(`: 0. Files are moved with `WP_Filesystem_Direct::move()` (core/rename-file.php:388), behind the off-by-default VERGEML_FILE_RENAME (:604). |
| PCS-WordPress.WP.AlternativeFunctions.file_system_operations | PASS | [PHPCS-I] 8 hits, which are errors when annotations are ignored. Each is annotated, and none has a WP_Filesystem equivalent. core/folder-tools.php:260 `readfile($tmp)` streams a ZIP just built in `wp_tempnam()`, after upload_files and a nonce (:229-233). core/health.php:183/194/208 is an fopen/fread/fclose chunked byte scan that counts GIF frames without loading the file. core/import-csv.php:258/264/295/366 is fopen/fwrite/fclose on `php://temp`, so fgetcsv can parse a CSV string. Nothing is written to disk, and no path comes from the request. |
| PCS-WordPress.WP.AlternativeFunctions.strip_tags | PASS | [GREP] code/ `strip_tags(`: 0. |
| PCS-WordPress.WP.AlternativeFunctions.rand | PASS | [GREP] code/ rand, mt_rand, srand, mt_srand: 0. |
| PCS-WordPress.WP.AlternativeFunctions (excluded) | PASS | Raw `json_encode(`: 0. `wp_json_encode` with JSON_ flags: 0. file_get_contents: 4, all on local or stream sources: ai.php:1084 and :1094 (an attachment file and its resized temp copy), transport.php:64 (`php://input`), options-pages.php:1309 (an upload that `is_uploaded_file()` checked and capped at 1 MB). file_put_contents: 1, `php://output`. |
| PCS-Generic.PHP.ForbiddenFunctions | PASS | [GREP] code/ move_uploaded_file, passthru, proc_open, create_function, eval, str_rot13, the 8 private core functions, shell_exec, exec, system, popen, assert: 0. |
| PCS-Squiz.PHP.DiscouragedFunctions | PASS | [PHPCS-I] 4 hits, all `set_time_limit` inside cron event callbacks, behind vergeml_can_set_time_limit() and bounded: ai-background.php:351, guide.php:1702, plan-tree.php:349,860. ini_set, ini_alter, dl: 0. The function-scoped ignore_user_abort (options-pages.php:1236) is on the settings export download, after a nonce and manage_options. |
| PCS-WordPress.WP.DeprecatedClasses / DeprecatedFunctions / DeprecatedParameters / DeprecatedParameterValues | PASS | [PHPCS-I] plugin-review.xml includes all four: 0. [PC] plugin_review_phpcs: 0. |
| PCS-WordPress.DateTime.RestrictedFunctions | PASS | [GREP] code/ `date(` and `date_default_timezone_set(`: 0. gmdate, mysql2date and wp_date are used. |
| PCS-WordPress.WP.DiscouragedConstants | PASS | [GREP] code/ PLUGINDIR, MUPLUGINDIR, STYLESHEETPATH, TEMPLATEPATH, HEADER_IMAGE*, BACKGROUND_*: 0. |
| PCS-WordPress.WP.DiscouragedFunctions | PASS | [GREP] code/ query_posts and wp_reset_query: 0. |
| PCS-Generic.Files.ByteOrderMark | PASS | BOM scan of all 133 PHP, JS and CSS files: 0. Every PHP file starts with `<?php`. |
| PCS-PluginCheck.CodeAnalysis.RequiredFunctionParameters | PASS | [GREP] code/ `parse_str(`: 0. |
| PCS-PluginCheck.CodeAnalysis.DiscouragedFunctions | PASS | [GREP] code/ `load_plugin_textdomain(`: 0. |
| PCS-WordPress.WP.I18n.TextDomainMismatch | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-WordPress.WP.I18n.NonSingularStringLiteral* | PASS | [PHPCS-I]: 0, so no variable or constant text or domain. |
| PCS-WordPress.WP.I18n.InterpolatedVariableText | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.WP.I18n.MissingTranslatorsComment | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-WordPress.WP.I18n.UnorderedPlaceholders / MixedOrderedPlaceholders | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.WP.I18n.MissingArgDomain / EmptyTextDomain | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.WP.I18n.NoEmptyStrings / NoHtmlWrappedStrings / MissingSingularPlaceholder / MismatchedPlaceholders / TranslatorsCommentWrongStyle / LowLevelTranslationFunction / SingleUnderscoreGetTextFunction / TooManyFunctionArgs | PASS | [PHPCS-I]: 0 for every code. |
| PCS-WordPress.WP.EnqueuedResources | PASS | [PHPCS-I]: 0. [CANARY] NonEnqueuedScript fires. |
| PCS-WordPress.WP.EnqueuedResourceParameters | PASS | [PHPCS-I]: 0. |
| PCS-WordPress.DB.SlowDBQuery | PASS | [PHPCS-I] 15 warnings, listed under PC-performant_wp_query_params. They are advisory, and taxonomy and meta filtering of the media list is the plugin's purpose. |
| PCS-WordPressVIPMinimum.Performance.WPQueryParams | PASS | [PHPCS-I] 1 warning, `exclude` in get_posts (private-folders.php:171). suppress_filters=true: 0. |
| PCS-WordPress.DB.DirectDatabaseQuery | PASS | [PHPCS-I] warnings only (DirectQuery 217, NoCaching 210, SchemaChange 3), on the plugin's 4 own tables and on core tables through core-style statements. Core-table writes: the term_taxonomy count update copies core's own counter (taxonomies.php:1568,1627), options-pages.php:1547 removes a taxonomy in Complete Cleanup, and the rest are uninstall and cleanup deletes. All prepared or literal. The schema changes are DROPs of own tables (see PC-direct_db_queries). |
| PCS-WordPress.Security.EscapeOutput.OutputNotEscaped | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-WordPress.Security.EscapeOutput.UnsafePrintingFunction | PASS | [PHPCS-I]: 0, and [GREP] code/ `_e(` and `_ex(`: 0. |
| PCS-WordPress.Security.EscapeOutput.ExceptionNotEscaped | PASS | [GREP] `throw new X(` in PHP: 0. |
| PCS-WordPress.Security.EscapeOutput.HeredocOutputNotEscaped | PASS | No heredoc (0). |
| PCS-WordPress.Security.EscapeOutput.UnsafeSearchQuery | PASS | [GREP] `get_search_query( false`: 0. |
| PCS-WordPress.Security.SafeRedirect | PASS | `wp_redirect(` 0, as in PC-safe_redirect. |
| PCS-WordPress.NamingConventions.PrefixAllGlobals | PASS | [PHPCS-I] 12 NonPrefixedHooknameFound. Each fires a core or third-party hook on purpose, the way core does around the same write or read. options-pages.php:1571 and :1588 delete_term_relationships and deleted_term_relationships, around the direct relationship delete. taxonomies.php:1567,1569,1626,1628 edit_term_taxonomy and edited_term_taxonomy, in the count callbacks. taxonomies.php:1059 list_cats and :1179,1227,1228 the_category, in walkers that copy core's. transport.php:56 rest_authentication_errors, asked the way WP_REST_Server::check_authentication asks. page-builders.php:310 dokan_forced_load_scripts, which reads Dokan's own filter. Functions, classes, constants and global variables: 0 unprefixed. |
| PCS-PluginCheck.CodeAnalysis.Localhost | PASS | [PHPCS-I]: 0 (see PC-localhost for the one grep hit). |
| PCS-Internal.Tokenizer.Exception | PASS | [PHPCS-I]: 0 over 68 files. |
| PCS-PluginCheck.CodeAnalysis.EnqueuedResourceOffloading | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-PluginCheck.CodeAnalysis.Offloading | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-PluginCheck.CodeAnalysis.SettingSanitization | PASS | [PHPCS-I]: 0 (see PC-setting_sanitization). |
| PCS-PluginCheck.CodeAnalysis.WriteFile | PASS | [PHPCS-I]: 0. |
| PCS-PluginCheck.Security.DirectDB | PASS | [PHPCS-I]: 0. The 8 annotations for this sniff are stale. |
| PCS-PluginCheck.CodeAnalysis.AIProvider | PASS | [PHPCS-I]: 0. [CANARY] fires on openrouter.ai. |
| PCS-PluginCheck.CodeAnalysis.PHPErrorReporting | PASS | [PHPCS-I]: 0. [CANARY] fires. |
| PCS-PluginCheck.Security.VerifyNonce | PASS | 2 false positives (see PC-nonce_verification). An optional rewrite silences both: `$check = false; if ( isset( ... ) && wp_verify_nonce( ... ) ) { $check = true; }`. |
| PCS-PluginCheck.Security.AIConnectorAPIKey | PASS | [GREP] every file `connectors_ai_` and `wp_ai_client`: 0. The sniff is trunk-only and absent from Plugin Check 2.1.0. |
| PCS-WCA-Generic.PHP.DiscourageGoto | PASS | `goto` 0. |
| PCS-WCA-Squiz.PHP.Eval.Discouraged | PASS | code/ `eval(`: 0. |
| PCS-WCA-WordPress.PHP.RestrictedPHPFunctions | PASS | code/ `create_function(`: 0. |
| PCS-WCA-WordPress.Security.SafeRedirect | PASS | `wp_redirect(` 0. |
| PCS-WCA-WordPress.DB.PreparedSQLPlaceholders | PASS | Same 5 spread-array warnings as the WPCS row, each with a matching count. |
| PCS-WCA-Generic.PHP.DisallowShortOpenTag | PASS | 0. |
| PCS-WCA-Generic.PHP.DisallowAlternativePHPTags | PASS | 0. |
| PCS-WCA-Internal.Tokenizer.Exception | PASS | 0. No minified PHP. |
| PCS-WCA-WordPress.Security.EscapeOutput | PASS | [PHPCS-I] EscapeOutput: 0. Every echo uses a WPCS escaping function (esc_*, wp_kses*, wp_json_encode, number_format_i18n, casts). |
| PCS-WCA-Generic.PHP.BacktickOperator | PASS | 0. |
| PCS-WCA-Generic.PHP.ForbiddenFunctions | PASS | 0, including wp_unregister_GLOBALS. |
| PCS-WCA-WordPress.NamingConventions.PrefixAllGlobals | PASS | The same 12 deliberate hook names as the WPCS row. Nothing else is unprefixed. |
| PCS-WCA-WordPress.WP.Deprecated* / DiscouragedConstants / DeprecatedParameterValues | PASS | 0 (see the WPCS rows). |
| PCS-WCA-DirectDB / OutputEscaping / VerifyNonce | PASS | Plugin Check's ports of these scanner sniffs: DirectDB 0, EscapeOutput 0, VerifyNonce 2 false positives. |
| SEC-1 | PASS | Service responses are sanitised at the boundary before they are stored: caption, alt, title, tags, model and hash through sanitize_text_field (core/ai.php:684-697, :729). Licence exchange: core/connect.php:198. Stored values are escaped at output ([PHPCS-I] EscapeOutput 0). |
| SEC-2 | PASS | Input: [PHPCS-I] ValidatedSanitizedInput 0, and all 124 REST arg definitions are typed (args.js), so WordPress validates and sanitises them by schema (16 also have explicit callbacks). Output: EscapeOutput 0. |
| SEC-3 | **FAIL** | 14 `admin_init` callbacks. Two of them change state with no capability check: vergeml_index_maybe_migrate (core/ai-index.php:897-919: schema install plus a migration step that writes index rows) and vergeml_librarian_housekeeping (core/librarian.php:328-354: schema install, a daily prune of batches, `update_option`). Both run on a logged-out request to admin-post.php. Their sibling vergeml_organize_housekeeping is guarded (core/organize.php:303). Every user-facing handler checks a capability (see SEC-15). See FAIL 3. |
| SEC-4 | PASS | wp_ajax_nopriv and admin_post_nopriv: 0. All 77 REST routes (85 method arrays) need a capability. The plugin has no front-end form or handler; the block render is read-only. |
| SEC-5 | PASS | The capability matches the action. Library-wide runs, applies, imports, undos and AI calls need manage_options (66+3 method arrays, vergelabs-media-library.php:126). Object writes ask about the object: edit_post (vergelabs-media-library.php:144), edit_post plus delete_post (health-delete.php:582-601), edit_term and delete_term (rest-folders.php:115-155), edit_post per attachment (rest-tree.php, quick-edit.php, taxonomies.php:1415-1432, rename.php:345-353). See REST-2 for the one fallback. |
| SEC-6 | PASS | Every handler validates first: the 16 ajax and admin-post handlers and 6 settings handlers check nonce and capability in their first statements (listed under SEC-15). The REST args schema runs before the callback. |
| SEC-7 | PASS | Safelists: 146 of 167 `in_array` calls are strict. Each of the 21 loose ones compares option values or core taxonomy object_type arrays, never request input. Taxonomy, method and action parameters are checked against allow-lists (for example rest-tree.php:566, transport.php:74-80, quarantine.php:395). |
| SEC-8 | PASS | `validate_file`: 0 uses, and no file path is built from request input. Paths come from get_attached_file(), wp_tempnam() or is_uploaded_file(), and the ZIP path is checked with vergeml_path_in_uploads (folder-tools.php). |
| SEC-9 | PASS | Database values are escaped at output ([PHPCS-I] 0). Option-derived taxonomy keys are run through sanitize_key before registration (vergelabs-media-library.php:719). |
| SEC-10 | PASS | Validation is preferred where a finite set exists (the allow-lists in SEC-7). Free text is sanitised: sanitize_text_field 86, sanitize_key 54, sanitize_textarea_field 7. |
| SEC-11 | PASS | Each sanitiser fits its type: sanitize_file_name and sanitize_mime_type for upload fields (options-pages.php:1288-1289), sanitize_email (get-help.php:206, offer.php:154), sanitize_key for slugs and keys, absint and (int) for ids, wp_kses for HTML. |
| SEC-12 | PASS | [PHPCS-I] EscapeOutput 0. HTML built by helpers is escaped when echoed, through `wp_kses( ..., vergeml_kses_allowed() )` (35 wp_kses calls). |
| SEC-13 | PASS | [GREP] href output: 17 `href="<?php echo esc_url`, 15 `href="' . esc_url(`, and 10 sprintf/printf `href="%s"` whose arguments are each esc_url(...) (admin-shell.php:324,415, ai-screen.php:218, connect.php:276, journey.php:448, rename.php:328,435, search-meaning.php:710, smart-folders.php:1361). An href, src or action escaped with esc_attr or esc_html: 0. |
| SEC-14 | PASS | `_e(`, `_ex(`, `echo __(`: 0. esc_html_e, esc_html__ and esc_attr_e are used. |
| SEC-15 | PASS | Every nonce check is paired with a capability in the same function or behind a capability-gated screen. Pairs: bulk-terms.php:150, folder-tools.php:46,233, get-help.php:200, import-csv.php:189, journey.php:414,431,1086,1166, licence-page.php:40, media-list.php:672, neighbours.php:130, offer.php:147, options-pages.php:884,1032,1166,1219,1264,1366,1419,1443, rename.php:353,452, search-meaning.php:736, taxonomies.php:1269,1353,1415,1421, transport.php:46. Behind a gated screen: ai-screen.php:323 (menu cap manage_options, ai.php:2028), get-help.php:289 (manage_options at :285), options-pages.php:1071 (network page, manage_network_options in its add_submenu_page at :196-202), connect.php:121 (router requires manage_options, connect.php:106). |
| SEC-16 | PASS | Every `wp_verify_nonce` result is tested (14 calls: 12 in an `if`, 2 assigned and used). The one `check_ajax_referer( ..., false )` is tested in an `if` (taxonomies.php:1269). check_admin_referer: 13. check_ajax_referer: 6. |
| SEC-17 | PASS | Nonce actions are action-specific: 30 distinct names (vergeml_zip, vergeml_export_csv, vergeml_do_alt, vergeml-term-counts and so on). No nonce action is shared across features. |
| REST-1 | PASS | `routes.js` found 77 `register_rest_route` calls with 85 method arrays, and 85 of 85 have `permission_callback`. |
| REST-2 | **FAIL** | No callback uses is_user_logged_in. But vergeml_can_manage_folders, the permission callback of the `/folder` write route (create, rename, delete, move, order, color), returns `current_user_can( 'manage_categories' )` when the posted taxonomy does not exist (core/rest-folders.php:97-99). The handler then refuses (rest-folders.php vergeml_rest_folder: 404), so nothing is written. Still, it is the capability the round-2 email named as too weak for a write, sitting in a permission callback. See FAIL 2. |
| REST-3 | PASS | All 11 permission callbacks return bool: the bodies are `current_user_can()`, `&&` and `\|\|` chains, `return false`, `return true` (vergelabs-media-library.php:126-146, rest-tree.php:215-243, rest-folders.php:86-156, health-delete.php:582-601, private-folders.php:222-228, import-ui.php:61-63, auto-file.php:690-700). |
| REST-4 | PASS | No callback returns 0, '', an array or null. Every return is a bool expression (same evidence). |
| REST-5 | PASS | Missing permission_callback: 0 of 85. `__return_true` appears twice, both on unrelated core filters (taxonomies.php:676 disable_months_dropdown, vergelabs-media-library.php:1566 infinite scrolling), never on a route. |
| REST-6 | PASS | The namespace is `'vergeml/v1'` (core/rest-tree.php:30), versioned with no leading or trailing slash. The 77 calls sit in 29 registrar functions, and each is hooked exactly once on `rest_api_init` (grep per function: 29 of 29 have 1 hook). |
| REST-7 | PASS | `vergeml/v1` is unique to the plugin. |
| REST-8 | PASS | 69 `apiFetch` calls (core nonce middleware). The admin-ajax fallback sends `X-WP-Nonce` (js/vergeml-transport.js:118) and the server verifies `wp_rest` (core/transport.php:46). |
| REST-9 | PASS | No REST callback verifies a nonce by hand. The 14 wp_verify_nonce calls are all in admin screens, admin_init handlers or the admin-ajax bridge. Every route still checks a capability (REST-1). |
| REST-10 | PASS | `args.js`: 124 arg definitions, all with `type` (so the core schema validates and sanitises them), 36 required and 16 with explicit callbacks. Bare definitions: 0. Of the 26 no-args callbacks, 25 read no parameter, and librarian-why reads only the `\d+` URL id, cast to int (librarian.php:3184). |
| REST-11 | PASS | Scan of all 76 named callbacks and 4 inline closures for echo, print, wp_send_json, exit, die, wp_die or header(): 0. The one hit is `$response->header()` on a WP_REST_Response (folders-version.php:118). |
| REST-12 | PASS | Object routes check the object. `/file/(?P<id>\d+)` and `/librarian-why/(?P<id>\d+)` check edit_post(id), and the callback refuses anything that is not an attachment (quick-edit.php:178, librarian.php:3189). `/assign` checks edit_post per attachment and is_object_in_taxonomy (rest-tree.php:648-655). `/health-delete` and `/health-keep` check edit_post(keep), delete_post per dropped id, and the attachment type. `/autofile-act` checks edit_post, assign_terms and term existence. `/folder` checks edit_term or delete_term per term. `/folder-privacy` checks edit_term. Watch item: the bulk routes on manage_options that take ids (`/quarantine-act` quarantine.php:389, `/librarian-apply-step`, `/librarian-undo-step`, `/organize-step`, `/import`) check the attachment type but not edit_post per id. Administrators hold edit_others_posts, so this is defensible. A loop of `current_user_can( 'edit_post', $id )` would close it. |
| S (IDs other than SEC) | N/A | The rulebook has no row ID starting with "S" except the 17 SEC rows above. |
| URL | N/A | No rulebook row ID starts with "URL". The `URL:` lines are source notes for Part A sections. |

## FAIL

### FAIL 1 (medium, proven at runtime): safe mode and the no-mbstring mode make assigning a folder a fatal error

- **Rule:** PC-proposed-runtime-fatals, "Invoking dynamic callbacks without `is_callable`", and "Calling plugin/theme integration functions without `function_exists` guard". This is also review 6's open item ("The term-count callbacks named at `:656` and `:764` ... fatal on the first term assignment"), which is still present.
- **Where:**
  - `vergelabs-media-library.php:660`: `'update_count_callback' => 'vergeml_update_attachment_term_count'`, inside vergeml_on_init (:599), which runs in every mode.
  - `vergelabs-media-library.php:768`: `$wp_taxonomies[$taxonomy]->update_count_callback = 'vergeml_update_post_term_count'`, inside vergeml_on_wp_loaded (:684), which also runs in every mode. It applies to any taxonomy on both posts and attachments, for example Categories after they are switched on for media.
  - Both functions are defined only in `core/taxonomies.php:1550` and `:1582`. That file is included only when `! vergeml_features_off()` (:1634-1637), that is, not when `VERGEML_SAFE_MODE` is set and not when mbstring is missing (:1597-1598).
- **Proof ([PROBE], PHP 8.3, WordPress latest):** the control run, without safe mode, ends with `assign=ok`. With `define( 'VERGEML_SAFE_MODE', true )`, `wp_set_object_terms()` on a `media_category` term throws `TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, function "vergeml_update_attachment_term_count" not found`. In a real request that is a fatal error on the attachment edit screen, in the media modal, and, through :768, on saving any post with a category when categories are switched on for media. The mode that is meant to keep a site up is the one that breaks saving.
- **Fix:** move `vergeml_update_attachment_term_count()` and `vergeml_update_post_term_count()` (self-contained, taxonomies.php:1540-1631) into a file that loads in every mode, such as the main file before the guard. Or register them only when `function_exists()` is true and fall back to core's `_update_generic_term_count` / `_update_post_term_count`. Then rerun `probe/run.mjs 1`: it must print `assign=ok`.

### FAIL 2 (low; reviewer optics on the round-2 class): `manage_categories` is still in a REST permission callback

- **Rule:** REST-2, "whether they can perform the action (authorization)". Round 2 flagged `manage_categories` guarding a write, and the submission log says "no route left on `manage_categories`".
- **Where:** `core/rest-folders.php:97-99`, in vergeml_can_manage_folders, the permission_callback of `POST vergeml/v1/folder` (rest-folders.php:28-31): `if ( ! $tax ) { return current_user_can( 'manage_categories' ); }`. Nothing calls the no-request form (grep: the only reference is the route itself). With a real taxonomy the check is the taxonomy's manage_terms plus per-term edit_term and delete_term, which is correct. With a made-up taxonomy, manage_categories lets the request through, and the handler then answers 404 without writing. It is not exploitable, but a reviewer who greps the permission callbacks for capability names will find it.
- **Fix:** `if ( ! $tax ) { return false; }` (or `current_user_can( 'manage_options' )` if a screen ever needs the no-request form). Related, not a permission: the tree's UI flag `'canManage' => current_user_can( 'manage_categories' )` (core/tree-ui.php:233) can show controls the server then refuses for media taxonomies whose manage_terms differs. Base it on the taxonomy's `cap->manage_terms` too.

### FAIL 3 (low): two `admin_init` housekeeping callbacks change state without a capability check

- **Rule:** SEC-3, "make sure to run your code only when the current user has the necessary capabilities". The release-time AI security review also lists "REST/AJAX/admin endpoints without capability checks" and "Unprotected options/metadata modifications".
- **Where:**
  - `core/ai-index.php:897-919` vergeml_index_maybe_migrate: no capability check. It runs vergeml_index_maybe_install() (schema check, dbDelta when the version differs) and vergeml_index_migrate_step(), which writes index rows.
  - `core/librarian.php:328-354` vergeml_librarian_housekeeping: no capability check. It runs vergeml_librarian_maybe_install(), a daily vergeml_librarian_prune() (DELETEs), and `update_option( VERGEML_LIBRARIAN_OPTION, ... )`.
  - Both skip AJAX only. `admin_init` fires for logged-out requests to `wp-admin/admin-post.php`, so anyone can drive them. No input is involved and the work is idempotent, so nothing leaks, but the class is plain to a scanner. The sibling `vergeml_organize_housekeeping` already has the guard (core/organize.php:303).
- **Fix:** add `|| ! current_user_can( 'manage_options' )` to the early return in both, as at organize.php:303. Activation and version changes still lay down the tables through vergeml_provision_site() (vergelabs-media-library.php:354-393), so this backstop only moves to the first admin request.

### FAIL 4 (low; not detected by Plugin Check 2.1.0): the editor block declares apiVersion 2

- **Rule:** PC-plugin_content, "Editor blocks must define "apiVersion" 3 or higher in block.json for WordPress 7.0+ iframe editor compatibility." (`block_api_version_too_low`).
- **Where:** `core/gallery-block.php:112` `'api_version' => 2` for `vergelabs/folder-gallery`. `js/vergeml-gallery-block.js:209` registerBlockType has no `apiVersion`. readme.txt:5 declares Tested up to 7.1. Plugin Check reads only block.json files, and the plugin ships none, so the automated check passes. The requirement it encodes is not met.
- **Fix:** set `'api_version' => 3` in PHP and `apiVersion: 3` in the JS registration. The block already uses `useBlockProps()` (js/vergeml-gallery-block.js:204) and renders on the server. Then insert, configure and save the block in the WordPress 7.1 editor (iframed) to confirm it works, before claiming it.

## RUNTIME

| ID | What only a running site proves | The run that proves it |
|---|---|---|
| PC-enqueued_scripts_size | Total size of scripts on front-end pages under 293 KB | Plugin Check with the plugin active, on a host where its runtime environment can be set up (it installs an object-cache drop-in). In Playground the 5 runtime checks answered HTTP 400 "0" and did not run. Run it on the Hetzner box or Kamatera, plugin active, all categories, and require a "Checks run successfully" reply for each of the 5. Add one page holding the Folder gallery block, because Plugin Check's default URLs never include it. Static expectation: 0 plugin scripts on a page without the block; on a page with it, js/vergeml-gallery.js and css/vergeml-gallery.css. |
| PC-enqueued_styles_size | Total size of styles on the same pages | Same run. |
| PC-enqueued_scripts_scope | No script on every front-end page | Same run. Static: the 2 `wp_enqueue_scripts` callbacks add nothing on an ordinary page (vergelabs-media-library.php:93-99 only sets RTL data; page-builders.php:160 needs the Divi builder and upload_files). |
| PC-enqueued_styles_scope | No style on every front-end page | Same run. Static: the gallery style is enqueued only from the block render (core/gallery-block.php:326-329). |
| PC-non_blocking_scripts | Footer or defer strategy for front-end scripts | Same run, on the gallery page. Expect at most the "loaded in the footer; consider defer" advisory for `vergeml-gallery`. To pre-empt it, register with `array( 'in_footer' => true, 'strategy' => 'defer' )` (core/gallery-block.php:45-51). |

Proven at runtime and folded into a verdict above: FAIL 1 ([PROBE]). The Plugin Check run itself (29 of 29 static checks, 0 findings, with a canary showing the run can fail).

## Seen outside these rows (for the owners of those rows)

- BOT-processing-whole-input: `foreach ( array_keys( $_GET ) as $key )` (core/admin-menu.php:200-205) copies keys matching `^(vgml\|vergeml)[-_]` into a redirect, each sanitised. It is defensible, but it is the literal pattern that row describes.
- The comment at vergelabs-media-library.php:1620-1625 still says safe mode starts "after two fatal errors in an hour". Since round 6 it is only switched on by `VERGEML_SAFE_MODE` (core/watchdog.php). A reviewer reading it will look for a watchdog that is no longer there.

## Tools and raw output

All in `C:/Users/viete/AppData/Local/Temp/vgml-r6-pcaudit/`:
- `strip.js`: builds the code/ and nc/ mirrors
- `routes.js`: REST routes and their method arrays
- `args.js`: REST arg schemas
- `safe.js`: feature functions referenced from code that loads in safe mode
- `r6.xml` and `run-phpcs.mjs`: the PHPCS runs; output in `out/honoured.json`, `out/ignored.json` and `out/ignored-list.tsv`
- `canary/`: the PHPCS canary
- `pc-drive.mjs`: the Plugin Check driver; replies in `out/pc-replies*.json`
- `probe/run.mjs`: the safe-mode probe; output in `probe/normal.txt` and `probe/safe.txt`

Both Playground servers (ports 8917 and 8918) were stopped after the runs.
