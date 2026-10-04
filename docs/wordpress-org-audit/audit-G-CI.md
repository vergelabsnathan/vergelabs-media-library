# Audit: G and CI rows — VergeLabs Media Library 4.1.0 archive

Archive: `scratchpad/audit/vergelabs-media-library/` (68 PHP files, 41 JS, readme.txt, changelog.txt). Rulebook: `docs/wordpress-org-rulebook.md`, rows G0.1–G18.5 (114) and CI-* (120). Audited 2026-10-04, every phpcs:ignore/disable treated as absent. Paths below are relative to the archive root. "Sweep" = whole-archive ripgrep plus reading of every hit in context (223 echo/print sites, 1,858 gettext calls, 136 superglobal reads, 77 REST routes, 7 AJAX actions, 13 admin_post handlers, every wp_remote_* call).

**Counts: PASS 170 · FAIL 18 · N/A 46** (234 rows; matches the rulebook ID list exactly).

| ID | verdict | evidence (file:line) | fix if FAIL |
|---|---|---|---|
| G0.1 | N/A | Process (committer accounts) — not in the archive | |
| G0.2 | N/A | Consequence clause | |
| G0.3 | N/A | Process (wordpress.org profile email) | |
| G0.4 | N/A | Process. Note: readme:202 names support@vergelabsmedia.com for licence holders; the wordpress.org profile email must not be that helpdesk address | |
| G0.5 | FAIL | Full security pass: no exploitable endpoint found, but the round-1 categories are not fully cleared — core AJAX takeover (taxonomies.php:1180, :1335), raw superglobals (taxonomies.php:1254-1260, options-pages.php:1257 …), whole-$_GET loops (admin-menu.php:189, vergelabs-media-library.php:812), 17 REST closures, unprepared query assembly (search-try.php:80-91). Also `vergeml-apply-settings-to-network` (options-pages.php:1217-1246) checks no capability on single site and never `restore_current_blog()` after `switch_to_blog( get_main_site_id() )` (1243) | See CI-sanitize-input, CI-whole-input, CI-prefix-unique, CI-wpdb-prepare, F-REST below |
| G0.6 | PASS | No exploitable endpoint: all 7 AJAX actions nonce+cap (none nopriv), all 13 admin_post handlers nonce+cap, all 77 REST routes have a real permission_callback, no `__return_true`. Weakest: health-delete.php:481 `manage_options && delete_posts`, no per-file `delete_post` (admin-only, so not exploitable) | |
| G0.7 | N/A | Process | |
| G1.1 | PASS | vergelabs-media-library.php:13 `License: GPLv2 or later`; readme.txt:8 same | |
| G1.2 | PASS | fonts/INTER-LICENSE.txt (SIL OFL 1.1, accepted in the directory); fonts/webbistro.* = EML's own IcoMoon icon font, shipped under EML's GPL; images/spinner*.gif from EML | |
| G1.3 | PASS | Only third-party code: natural-compare-lite snippet (MIT) js/eml-media-models.js:265-268, credited inline. No vendor/, no node_modules | |
| G2.1 | PASS | Whole archive swept (this table) | |
| G2.2 | FAIL | Round 1 asked for "every instance" of: core AJAX hijack (fixed only for delete-post per docs/wordpress-org-submission.md:131) — still taxonomies.php:1180 `wp_ajax_save-attachment-compat` and :1335 `wp_ajax_save-attachment-order`, both priority 0 ending in wp_send_json so core's handler never runs; REST closures (fixed only for callbacks held in variables) — still 17 closures: ai.php:1826,1829,1835,1848,2009; folder-talk.php:2784; health-delete.php:481; instrument.php:274; librarian.php:2522; quick-edit.php:164; rename-file.php:605,612,618,640; rename.php:498,501,507 | Hook a prefixed action (`wp_ajax_vergeml_save_attachment_compat` / `_order`) and point the JS at it (or filter core's data instead of replacing the handler); replace every closure with a named `vergeml_*` function |
| G2.3 | PASS | Third-party inventory = Inter (OFL), natural-compare-lite (MIT), EML-origin font/images (GPL) | |
| G2.4 | PASS | Hosts contacted: ai.vergelabs.nl/v1 (ai.php:313, get-help.php:187, guide.php:862) and vergelabsmedia.com (connect.php:39) — both the author's own service | |
| G2.5 | PASS | ToS/privacy linked readme.txt:128-130 | |
| G3.1 | PASS | readme `Stable tag: 4.1.0` (readme.txt:7) = `Version: 4.1.0` (main:6) | |
| G3.2 | PASS | No Update URI header (main:1-26), no updater hooks (grep `update_plugins|plugins_api|Upgrader|download_url` = 0). Note: per project memory, Pro 1.0.4 currently carries this plugin's updates — the Pro add-on must stop doing so once the listing is live | |
| G4.1 | PASS | grep `eval\(|create_function|gzinflate|str_rot13|p,a,c,k,e` = 0. `base64_decode` only ai.php:353 (unseal own key) and organize.php:2415 (own packed vectors) | |
| G4.2 | PASS | No *.min.js; no line >500 chars in js/ or css/ (awk sweep) | |
| G4.3 | PASS | 1,096 functions all `vergeml_*` readable names; no ionCube/SourceGuardian | |
| G4.4 | PASS | All JS/CSS shipped as source | |
| G4.5 | PASS | readme.txt:21 GitHub link | |
| G4.6 | PASS | No build step needed; only the `*-rtl.css` files are generated (tools/rtl.mjs, per main:83-87) from the shipped sources. Optional: one readme line naming it | |
| G5.1 | PASS | Every licence check guards a service request: ai.php:101-103,516-519; plan-tree.php:1032-1034; folder-talk.php:439-446; guide.php:877-882,1283; brief.php:329-333,469,549; filing.php:3141-3143; search-meaning.php:127,193; instrument.php:229. Local features (tree, smart folders, health, importer, galleries, MIME, date/type Librarian, confirm, grow) need none | |
| G5.2 | PASS | No install-date/trial expiry; the 25-credit trial is a service credit (offer.php:145-172) | |
| G5.3 | PASS | Live endpoints only (ai.php:313) | |
| G5.4 | PASS | Paid work happens in the service (describe, embed, plan, talk); no local path gated on credits | |
| G5.5 | PASS | No pro/premium code. Comments mention a "pro half" and its column id `vgmlpro_source` (media-list.php:41,52; js/vergeml-quick-edit.js:55; auto-file.php:182; ai.php:391) — add-on integration, not locked code | |
| G5.6 | N/A | Not a developer tool | |
| G5.7 | PASS | Upsells only on own screens: ai-screen.php:394, licence-page.php:149, offer.php:66,134, connect.php:281 | |
| G6.1 | PASS | AI service client; licence settings ai.php:2121-2131, connect.php | |
| G6.2 | PASS | Service does image description, embeddings, tree planning, conversation (ai.php:592, search-meaning.php:132, plan-tree.php:1042, folder-talk.php:454) | |
| G6.3 | PASS | readme.txt:104-130 External services, ToS + privacy at :130 | |
| G6.4 | PASS | `/licence` (ai.php:106,167) only checks/saves the key; no local feature unlocks on it | |
| G6.5 | PASS | Service returns AI output, not config/HTML | |
| G6.6 | PASS | Plugin is a media manager, not a storefront | |
| G7.1 | PASS | Every wp_remote_* call traced: user presses (describe, plan, fill, talk, search-by-meaning link search-meaning.php:668-705, trial, connect, Get help, known issues get-help.php:63-90) or, with a key set, credits check on own screens (admin-shell.php:359-366, disclosed readme:120). Nothing on activation/init without a key | |
| G7.2 | PASS | Stats off by default: instrument.php:47 `'opted' => 0`, checkbox unchecked :359, sends only when opted :189,255 | |
| G7.3 | FAIL | readme.txt:122 lists what Get help sends, but get-help.php:220-232 also sends `site_token` (a persistent random id, get-help.php:168-178, option `vergeml_support_token`) and the matched known-issues list; Tree-step fit after a paste/edit sends folder paths to `/embed` (guide.php:1544→1625→2192→search-meaning.php:132) — not in the readme's list of triggers (readme:112) | Add to readme:122 "a random identifier the plugin keeps for this site, so replies reach it, and the known problems that match your site"; add to readme:112 "or paste/edit a draft tree (its folder paths are sent to count how pictures would fit)" |
| G7.4 | PASS | Plugin list/system report only with consent tick (get-help.php:205-214 refuses without it) | |
| G7.5 | PASS | Trial email optional path; nothing blocks settings | |
| G7.6 | PASS | No remote enqueues/images (grep `wp_(enqueue|register)_(script|style)` with http = 0; `url(http` in css = 0) | |
| G7.7 | PASS | Known-issues feed disclosed readme:122, fetched only on button (get-help.php:63-90) | |
| G7.8 | PASS | grep gtag/googletagmanager/analytics/hotjar/mixpanel/posthog/adsbygoogle = 0 | |
| G7.9 | PASS | Service calls follow key setup or explicit press | |
| G8.1 | PASS | https enforced ai.php:313-316, get-help.php:189; `sslverify => true` instrument.php:237; Requests `verify => true` ai.php:~898 | |
| G8.2 | PASS | No eval/new Function/unserialize of remote data (sweep J); remote JSON only json_decode'd | |
| G8.3 | PASS | No updater/installer code (grep = 0) | |
| G8.4 | PASS | No Pro download/install | |
| G8.5 | PASS | No CDN | |
| G8.6 | PASS | No scheduled list fetch; known-issues on demand from own service | |
| G8.7 | PASS | grep `iframe` = 0 | |
| G8.8 | N/A | No remote management | |
| G9.1 | FAIL (judgement) | admin-shell.php:485-508 `vergeml_shell_quiet` removes every non-core `admin_notices`/`all_admin_notices` callback on the plugin's screens — hides other plugins' alerts (security, backup, licence warnings). Related: compatibility.php:90+ dequeues Enhanced Media Library's scripts when both are active | Stop removing other plugins' callbacks (contain them with CSS inside the shell, or move them below the shell header) |
| G9.2 | PASS | readme Tags 5 distinct (readme:3); no hidden front-end links | |
| G9.3 | PASS | No traffic promises | |
| G9.4 | PASS | grep review/stars/rate = 0 | |
| G9.5 | PASS | No Unlock/Pro/lock labels on local features (grep) | |
| G9.6 | N/A | Process | |
| G9.7 | PASS | Fork credited: main:16-20 (copyright kept), readme:19,156, options-pages.php:3379 | |
| G9.8 | PASS | grep GDPR/CCPA/WCAG/ADA/compliant/legally = 0 | |
| G9.9 | PASS | No miners; cron jobs are own work (ai-background, plan-tree, guide fit) | |
| G9.10 | N/A | Conduct | |
| G9.11 | N/A | Conduct | |
| G9.12 | N/A | Process. Note readme:202 sends questions to GitHub issues; keep answering the wordpress.org forum too | |
| G9.13 | PASS | readme:156-162 describes EML neutrally (fixes, "not affiliated"); no attacks | |
| G9.14 | N/A | Process | |
| G9.15 | PASS | Opt-in unticked; licence service does real work | |
| G10.1 | PASS | No front-end credit: gallery render paths (gallery-block.php, gallery-widgets.php, page-builders.php) grep "powered|vergelabs|credit" = only block name | |
| G10.2 | PASS | Nothing to opt into | |
| G10.3 | PASS | Nothing required | |
| G10.4 | N/A | No service-rendered front-end output | |
| G11.1 | PASS | 7 notice callbacks, each contextual: bulk-terms.php:224 (upload screen + query arg), media-list.php:721 (upload + arg), neighbours.php:76 (media/own screens), rename.php:399,458 (only after own redirect), watchdog.php:235,298 (only in safe mode) | |
| G11.2 | PASS | Upgrade prompts only on own screens (G5.7 lines); none in notices | |
| G11.3 | PASS | neighbours.php:105-118 dismissible, persisted in user meta (:121-138); watchdog notice disappears when safe mode is resumed (watchdog.php:239) | |
| G11.4 | PASS | Watchdog notice gives resume, system report and deactivate actions (watchdog.php:258-290) | |
| G11.5 | PASS | No ads; plugin_row_meta Docs/Support only (main:1493-1494) | |
| G11.6 | PASS | connect.php:51-59 adds `utm_source/medium/campaign` to own buy links — screen name only, no site identifier, not a referral/affiliate. Optional: drop the UTM params to remove any doubt | |
| G11.7 | PASS | No ad networks | |
| G11.8 | PASS | Images local only | |
| G12.1 | PASS | readme and .pot carry no spam links | |
| G12.2 | PASS | No affiliate links | |
| G12.3 | PASS | Tags: media library, media folders, alt text, accessibility, media categories — no competitor names | |
| G12.4 | PASS | 5 tags | |
| G12.5 | PASS | No stuffing in title/short description (readme:1,11) | |
| G12.6 | PASS | Links: EML, GitHub, legal pages, Patchstack | |
| G12.7 | PASS | No product tags | |
| G12.8 | PASS | "media" in 3 of 5 tags is natural phrasing, not repetition of one tag. Low risk | |
| G12.9 | PASS | Description is prose | |
| G12.10 | PASS | No affiliate links | |
| G13.1 | PASS | No bundled jQuery/PHPMailer/SimplePie/PHPass | |
| G13.2 | PASS | No bundled underscore/backbone/moment/react; JS uses core handles | |
| G14.1 | N/A | Process | |
| G14.2 | N/A | Process | |
| G14.3 | N/A | Process | |
| G14.4 | N/A | Process | |
| G14.5 | N/A | Process | |
| G14.6 | N/A | Process | |
| G15.1 | PASS | 4.1.0 > 4.0.7 (readme:263,279); VERGEML_VERSION 4.1.0 (main:35) | |
| G15.2 | PASS | Stable tag 4.1.0 = Version 4.1.0 | |
| G16.1 | PASS | Complete working plugin | |
| G16.2 | PASS | Real functionality | |
| G16.3 | N/A | Process | |
| G17.1 | PASS | Name/slug start with own brand "VergeLabs" | |
| G17.2 | PASS | Slug `vergelabs-media-library` has no "wordpress" | |
| G17.3 | PASS | Same | |
| G17.4 | N/A | Not named after a third-party product | |
| G17.5 | PASS | Not named after a library | |
| G17.6 | PASS | Own branding | |
| G18.1 | N/A | Process | |
| G18.2 | N/A | Process | |
| G18.3 | N/A | Process | |
| G18.4 | N/A | Process. Tested up to 7.1 (readme:5) is current (7.1 released 2026-08-19) | |
| G18.5 | N/A | Process | |
| CI-sanitize-input | FAIL | Raw reads: taxonomies.php:1254-1260 `array_keys( $_REQUEST['tax_input'][ $taxonomy ], 1 )` (no unslash/sanitize); options-pages.php:1257 `switch ( $_REQUEST['settings'] )`; guide.php:45 `$_GET['page']`; librarian.php:3173 `$_REQUEST['action']`; media-list.php:457-462 `$_REQUEST[ $taxonomy ]`, `$_REQUEST['attachment-filter']`; taxonomies.php:521,752 `$_REQUEST['attachment-filter']`; get-help.php:295 `sanitize_key( $_GET['vgml-help'] )` without wp_unslash; transport.php:55-80 php://input `params` forwarded unsanitized | Wrap each in `sanitize_key( wp_unslash( … ) )` / `sanitize_text_field( wp_unslash( … ) )`; for tax_input: `array_map( 'absint', array_keys( wp_unslash( (array) $_REQUEST['tax_input'][ $taxonomy ] ), 1 ) )`; in transport.php sanitize `route` with `sanitize_text_field` and rely on typed args (see CI-validate) |
| CI-validate | FAIL | REST args with no type/schema: `draft` (brief/session, brief/turn, guide/session, guide/turn), `branches` (librarian-preflight, librarian-apply-step), `ids` (quarantine-act), `plan` (say-run, merge-run); arrays without `items`: assign `attachments/add/remove/batch` (rest-tree.php), folders-apply `folders`, folders-propose `history`, state `open`. These also arrive raw through the bridge (transport.php:75-80) | Give each arg `type` + `items`/`properties` and a `sanitize_callback`/`validate_callback` |
| CI-escape-output | FAIL | import-csv.php:217 `echo vergeml_csv_line( $row );`; options-pages.php:1582 `echo json_encode( $settings );` (both file downloads, both unescaped echo) | Write downloads without `echo`: `$out = fopen( 'php://output', 'w' ); fputcsv( $out, $row );` for CSV; for JSON `fwrite( $out, wp_json_encode( $settings ) )` (or `wp_send_json`-style helper) |
| CI-context-functions | FAIL | `esc_html` in attribute context: options-pages.php:3286-3288 (`value=`), 3289,3293,3295 (`title="<?php esc_html_e`), 2791-2801 (11 `value="' . esc_html(`) | Use `esc_attr` / `esc_attr_e` |
| CI-escape-not-sanitize | PASS | No `esc_*` on input before saving (sweep 3/4) | |
| CI-sanitize-then-escape | PASS | Echoed superglobals sanitized then escaped: bulk-terms.php:236-262, media-list.php:736-746, rename.php:403-433 | |
| CI-filter-param | N/A | No filter_input/filter_var calls (grep = 0) | |
| CI-filter-default | N/A | Same | |
| CI-nonce-sanitize | PASS | All 13 wp_verify_nonce calls wrap `sanitize_text_field( wp_unslash() )` or sanitize a variable first (transport.php:40-46) | |
| CI-whole-input | FAIL | admin-menu.php:189 `foreach ( wp_unslash( $_GET ) as $key => $value )`; vergelabs-media-library.php:812 `map_deep( wp_unslash( $_GET ), 'sanitize_text_field' )` sent to JS | Read only the named keys each needs (an allow-list array of keys) |
| CI-escape-echoed-vars | FAIL | Same two echoes as CI-escape-output (import-csv.php:217, options-pages.php:1582); every other of 223 output statements escaped | Same fix as CI-escape-output |
| CI-escape-late | PASS | Helpers return HTML printed through `wp_kses( …, vergeml_kses_allowed() )` (main:141; e.g. options-pages.php:2906,3008, smart-folders.php:1342) | |
| CI-escape-options | PASS | No raw `echo get_option(`/meta (sweep 1) | |
| CI-return-not-echo-json | PASS | AJAX/REST use wp_send_json*/rest_ensure_response (131 sites); only echoed JSON is the export download | |
| CI-sanitize-json | PASS | Stored REST text sanitized: ai.php:2131,2138; quick-edit.php:193,202; quarantine.php:88; brief.php:702,715 | |
| CI-escape-html-too | PASS | HTML output via wp_kses allow-list | |
| CI-esc-url-raw | PASS | Only connect.php:139 inside wp_safe_redirect | |
| CI-esc-url | PASS | All href/src via esc_url (sweep 1) | |
| CI-escape-__ | PASS | 0 unescaped `echo __(`/`printf( __(` | |
| CI-escape-_e | PASS | grep `_e(` = 0 | |
| CI-escape-_ex | PASS | grep `_ex(` = 0 | |
| CI-wp-json-encode | FAIL | options-pages.php:1582 `json_encode( $settings )`; taxonomies.php:1159 `$output .= json_encode( $el );` | Use `wp_json_encode` |
| CI-json-flags | PASS | No JSON_UNESCAPED_* flags | |
| CI-kses-html | PASS | `vergeml_kses_allowed()` main:141 (post list + form controls + SVG, no script/on*) | |
| CI-esc-html-on-html | PASS | 37 `esc_html( $var )` sites all plain text | |
| CI-sanitize-not-escape | PASS | grep `echo … sanitize_` = 0 | |
| CI-wp-handle-upload | PASS | No move_uploaded_file; settings import reads `is_uploaded_file()` tmp (options-pages.php:1627-1652) | |
| CI-unfiltered-uploads | PASS | grep ALLOW_UNFILTERED_UPLOADS/unfiltered_upload = 0 | |
| CI-upload-mimes | PASS | mime-types.php:275-298 only toggles types core knows; refusal list :119-126 blocks php/phtml/html/svg/js/exe etc.; wp_check_filetype_and_ext :316-390 re-allows only ttf/otf/woff/woff2 and xlsx | |
| CI-remote-assets | PASS | No remote assets (G7.6 grep) | |
| CI-include-locally | PASS | Fonts local (fonts/), core handles used | |
| CI-remote-service-exception | N/A | No remote assets claimed | |
| CI-no-remote-jquery-css | PASS | none | |
| CI-no-help-iframe | PASS | grep iframe = 0 | |
| CI-no-own-domain-images | PASS | No `<img src="http` (grep = 0) | |
| CI-ok-google-fonts | N/A | No Google Fonts | |
| CI-ok-service-api | PASS | Service calls documented readme:104-130 | |
| CI-ok-offload-comments | N/A | | |
| CI-ok-oembed | N/A | | |
| CI-service-readme | PASS | readme:106 names both hosts and that a licence key is needed | |
| CI-lib-stable | N/A | No versioned libraries | |
| CI-lib-beta-explain | N/A | | |
| CI-lib-unmaintained | N/A | | |
| CI-lib-outdated | N/A | | |
| CI-wpdb-prepare | FAIL | search-try.php:80-91 user `s` term in LIKE fragments prepared separately, then concatenated into `get_var`/`get_results` without a final prepare; seo-context.php:258-259,287,338 `esc_sql` + implode IN, query unprepared; same fragment assembly guide.php:2793, ai-screen.php:136 | Build one `$wpdb->prepare()` per query with all placeholders (`%s` with `'%' . $wpdb->esc_like( $w ) . '%'`) |
| CI-wpdb-array-placeholders | FAIL | IN() via implode of cast ints: ai-index.php:256-259, folder-talk.php:2252, guide.php:3227, librarian.php:1495-1496, media-list.php:121, search-meaning.php:421, search-try.php:142, auto-file.php:764-771 | `implode( ',', array_fill( 0, count( $ids ), '%d' ) )` inside `$wpdb->prepare( …, $ids )` |
| CI-no-heredoc | PASS | grep `<<<` = 0 | |
| CI-direct-access | PASS | Every PHP file's first statement is the ABSPATH guard (uninstall.php:3 WP_UNINSTALL_PLUGIN; main:30); index.php has no code | |
| CI-prefix-unique | FAIL | (a) core AJAX actions answered: `wp_ajax_save-attachment-compat` taxonomies.php:1180, `wp_ajax_save-attachment-order` :1335 (also `wp_ajax_tb_load_editor` page-builders.php:262, enqueue only); (b) legacy transient `eml_transient` main:521; (c) JS: unprefixed `wp.media.view.AttachmentFilters.Taxonomy` / `.Authors` / `Button.resetFilters` (js/vergeml-media-views.js:254,325,354); (d) overwrites core JS globals `window.findPosts` (js/eml-media.js:16), `adminpage`/`pagenow` (js/eml-admin.js:14,16); (e) taxonomy `media_category` unprefixed (main:1141) | (a) see G2.2; (b) drop or rename `vergeml_…`; (c) rename to `vergemlTaxonomy` etc.; (d) don't reassign core globals; (e) keep but be ready to justify (shared EML/FileBird slug = migration path) |
| CI-prefix-functions | PASS | 1,096 top-level functions all `vergeml_` | |
| CI-prefix-classes | PASS | 6 classes, all `VergeML_`/`vergeml_` (gallery-widgets.php:263,414; media-list.php:385; taxonomies.php:973,1078,1127) | |
| CI-prefix-namespace | N/A | No namespaces | |
| CI-prefix-options | PASS | ~40 options, all `vergeml_*`; transients `vergeml_*` except `eml_transient` (counted under CI-prefix-unique) | |
| CI-prefix-defines | PASS | 5 define + 173 const, all `VERGEML_*` | |
| CI-prefix-globals | PASS | `$vergeml_dir/_slug/_filename/_basename` (main:200), `$GLOBALS['vergeml_*']` | |
| CI-prefix-length | FAIL | 3-char `eml` names: `eml_transient` (main:521), global `emlIsFilterBased` (js/eml-enhanced-medialist.js:6), `wp.media.view.emlGridAttachmentDetails`, `emlGridSelection`, `emlSelectAllButton`, `emlDeselectButton`, `emlDeleteSelectedButton`, `emlDeleteSelectedPermanentlyButton`, `MediaFrame.emlGrid` (js/eml-media-grid.js:73,121,133,162,199,291,347), `emlAttachmentDetailsEditMessage` (js/vergeml-media-views.js:376) | Rename to `vergeml…` |
| CI-prefix-reserved | PASS | Nothing starts `wp_`/`_`/`__` | |
| CI-function-exists | FAIL | main:208-1684 `if ( ! function_exists( 'vergeml_get_slug' ) ) {` wraps 20 own functions; taxonomies.php:1076 and :1125 `if ( ! class_exists( 'vergeml_Walker_…' ) )` | Remove the three wrappers |
| CI-no-short-tags | PASS | grep `<?=` = 0 | |
| CI-no-global-ini | PASS | No ini_set; `@set_time_limit` only in cron callbacks (ai-background.php:352, guide.php:1707, plan-tree.php:349,860); `ignore_user_abort` only in the export handler (options-pages.php:1575) | |
| CI-ini-scoped | PASS | Same lines, all handler-scoped | |
| CI-no-default-timezone | PASS | grep = 0 | |
| CI-no-error-reporting | PASS | grep = 0 | |
| CI-main-file-name | PASS | vergelabs-media-library/vergelabs-media-library.php | |
| CI-main-file-common-names | PASS | not plugin.php/index.php | |
| CI-headers-complete | PASS | main:3-14 Plugin Name, Version, Description, Author, License, Text Domain, Requires at least, Requires PHP | |
| CI-headers-main-only | PASS | `Plugin Name:` in one file only | |
| CI-readme-complete | PASS | readme header block :1-9, Description, Installation, FAQ, External services | |
| CI-readme-no-surprises | PASS | Accounts, hosts, costs (readme:106-130, 256), no required plugins | |
| CI-readme-validate | PASS | Headers parse; Tested up to 7.1 is a released version | |
| CI-readme-txt-not-md | PASS | readme.txt present | |
| CI-readme-template | PASS | Standard sections in template order (markdown `##` headings are accepted) | |
| CI-license-declared | PASS | readme:8, main:13 | |
| CI-license-all-content | PASS | See G1.2 | |
| CI-stable-tag-match | PASS | 4.1.0 = 4.1.0 | |
| CI-stable-tag-not-wp | PASS | 4.1.0 ≠ 7.1 | |
| CI-semver | PASS | 4.1.0 | |
| CI-no-stable-trunk | PASS | not trunk | |
| CI-tag-and-increment | N/A | SVN process; version increased (G15.1) | |
| CI-license-same | PASS | both "GPLv2 or later" | |
| CI-license-sole | PASS | One licence; third-party licences in fonts/INTER-LICENSE.txt and inline (eml-media-models.js:265) | |
| CI-http-api | PASS | All requests via wp_remote_*; ai.php:~890 uses core's bundled `WpOrg\Requests\Requests::request_multiple` (no own curl), skipped behind a proxy (ai.php:851). Risk: the comment at ai.php:825 says it bypasses WP_Http — a reviewer may still ask for wp_remote_post | |
| CI-http-api-curl-hook | N/A | No custom curl | |
| CI-http-api-vendor-ok | N/A | No vendor | |
| CI-enqueue-js | PASS | No `<script>` printed except core-style `type="text/html"` Underscore templates on print_media_templates (media-templates.php:19-100); inline code via wp_add_inline_script (main:105-111, transport.php:141, instrument.php:401, licence-page.php:304) | |
| CI-enqueue-css | PASS | No `<style>`/`<link>` printed | |
| CI-enqueue-attributes | PASS | Attributes added via `script_loader_tag` filter (neighbours.php:184-191), not hand-written tags. Optional: move to `wp_script_attributes` | |
| CI-enqueue-admin | PASS | Admin assets on admin_enqueue_scripts. Note: options-pages.php:290-305 overwrites global `$pagenow` and fires core `admin_print_*`/`admin_head-options-media.php` itself — EML legacy that a reviewer may question | |
| CI-core-libs | PASS | none bundled | |
| CI-core-libs-addons-only | PASS | none | |
| CI-core-libs-noconflict | PASS | All jQuery code wrapped `( function( $ ) { … } )( jQuery )` (e.g. js/eml-admin.js:4-110, eml-media-grid.js:…622) | |
| CI-i18n-text-literal | PASS | 1,858 gettext calls, 0 non-literal text | |
| CI-i18n-context-literal | PASS | 0 non-literal context | |
| CI-i18n-domain-literal | PASS | 0 variable domains | |
| CI-i18n-domain-slug | PASS | all `vergelabs-media-library` = header main:11 = slug; no load_plugin_textdomain | |
| CI-i18n-placeholders | FAIL | brief.php:609-610 `/* translators: */` sits on a placeholder-less `__()` while the `%s` is in a concatenated `_n()` — one sentence built from three translations; split sentences options-pages.php:3373, 908-909, 1161-1162; taxonomies.php:556 | One gettext string per sentence with placeholders and a `/* translators: */` directly above each |
| CI-active-plugins | PASS | No activate/deactivate calls; `active_plugins` only read (get-help.php:107, system-report.php:128) | |
| CI-activation-interfere | PASS | action links only added (main:1453-1462, array_merge keeps Deactivate); no activation hooks on others | |
| CI-self-deactivate-ok | N/A | Plugin never deactivates itself (watchdog.php:273-287 offers core's link) | |
| CI-plugin-dependencies | N/A | No required plugins | |
| CI-no-update-checker | PASS | grep = 0 | |
| CI-no-updater-interference | PASS | No auto_update/DISALLOW_FILE_MODS | |
| CI-3p-disclose | PASS | Both hosts in readme:106 | |
| CI-3p-even-own | PASS | Own service documented | |
| CI-3p-explain | FAIL | Same gaps as G7.3: Get help also sends `site_token` and matched known issues (get-help.php:220-232); the Tree-step fit sends folder paths to `/embed` on paste/edit (guide.php:1544, 2192) | Same readme edits as G7.3 |
| CI-3p-link-service | PASS | readme:106 | |
| CI-3p-link-terms | PASS | readme:130 terms + privacy | |
| CI-unneeded-devtools | PASS | No .github/tools/tests/config files in archive (file list) | |
| CI-unneeded-vendor | PASS | No node_modules/vendor | |
| CI-unneeded-demos | PASS | none | |
| CI-unneeded-tests | PASS | none | |
| CI-keep-composer-json | N/A | No built/composer code | |
| CI-not-permitted-files | PASS | Types: php, js, css, txt, md, pot, xml, gif, svg, eot, ttf, woff, woff2 — fonts/images are normal | |
| CI-premium-source | PASS | No premium libraries | |
| CI-gpl-non-compatible | PASS | see G1.2/G1.3 | |
| CI-gpl-source-for-compressed | N/A | No compressed files | |
| CI-gpl-source-link-in-readme | PASS | readme:21 | |
| CI-gpl-build-public | PASS | Source is the shipped code; repo linked readme:21 | |
| CI-gpl-build-directions | N/A | No build step (RTL CSS generation is optional to document) | |
| CI-composer-json | N/A | No vendor/autoload.php | |


## FAILS

1. **G0.5 — security pass not clean.** Not exploitable, but every round-1 category still has instances. Details and fixes in G2.2, CI-sanitize-input, CI-whole-input, CI-validate, CI-wpdb-*. Plus `vergeml-apply-settings-to-network` (options-pages.php:1217-1246): add `current_user_can( 'manage_network_options' )` unconditionally, and `restore_current_blog()` after the `switch_to_blog( get_main_site_id() )` at :1243.

2. **G2.2 — instances of round-1 categories remain (reviewer reads as "asked to fix every instance").**
   - Core AJAX takeover: `add_action( 'wp_ajax_save-attachment-compat', 'vergeml_save_attachment_compat', 0 )` (taxonomies.php:1180) and `add_action( 'wp_ajax_save-attachment-order', 'vergeml_save_attachment_order', 0 )` (:1335). Both end in `wp_send_json`, so core's handler at priority 1 never runs. Round 1 fixed the delete-post one only. Fix: register `wp_ajax_vergeml_save_attachment_compat` / `_order`, send the JS requests there (js/eml-media-*.js), or hook core's data filters (`attachment_fields_to_save`) instead.
   - REST closures: 17 anonymous callbacks/permission callbacks (ai.php:1826,1829,1835,1848,2009; folder-talk.php:2784; health-delete.php:481; instrument.php:274; librarian.php:2522; quick-edit.php:164; rename-file.php:605,612,618,640; rename.php:498,501,507). Fix: named `vergeml_rest_can_*` / `vergeml_*_rest` functions, as round 1 did for the other 51.

3. **G7.3 / CI-3p-explain — readme omits two data items and one trigger.** Get help also posts `site_token` (persistent random id, get-help.php:168-178, 220) and the matched known-issues list (:221-231). Editing or pasting a draft tree computes the fit and sends folder paths to `ai.vergelabs.nl/v1/embed` (guide.php:1544 → 1625 → 2192 → search-meaning.php:132). Fix: add both items to readme:122 and the draft-edit trigger to readme:112 (its line "each of them is a button you press" is otherwise inaccurate).

4. **G9.1 (judgement) — hides other plugins' notices.** admin-shell.php:485-508 removes every non-core admin_notices/all_admin_notices callback on the plugin's screens, including security and backup alerts. Fix: stop removing third-party callbacks; restyle or relocate them instead.

5. **CI-sanitize-input — raw superglobals.** taxonomies.php:1254-1260 (`$_REQUEST['tax_input']`), options-pages.php:1257, guide.php:45, librarian.php:3173, media-list.php:457-462, taxonomies.php:521,752, get-help.php:295 (no wp_unslash), transport.php:55-80 (php://input `route`/`params`). Fix: `sanitize_key( wp_unslash() )` / `sanitize_text_field( wp_unslash() )` / `absint` at first read.

6. **CI-validate — untyped REST args.** draft, branches, ids, plan, and arrays without `items` (list in table). Fix: full `args` schema with sanitize/validate callbacks.

7. **CI-escape-output / CI-escape-echoed-vars — two unescaped echoes.** import-csv.php:217, options-pages.php:1582 (downloads). Fix: write through `fopen( 'php://output', 'w' )` with `fputcsv` / `fwrite`.

8. **CI-wp-json-encode.** options-pages.php:1582, taxonomies.php:1159 → `wp_json_encode`.

9. **CI-context-functions.** 17 `esc_html` in attributes, options-pages.php:2791-2801, 3286-3295 → `esc_attr`.

10. **CI-whole-input.** admin-menu.php:189, vergelabs-media-library.php:812 iterate the whole `$_GET`. Fix: allow-list of keys.

11. **CI-wpdb-prepare.** search-try.php:80-91 (user input), seo-context.php:258-259/287/338 (`esc_sql` IN), guide.php:2793, ai-screen.php:136. Fix: one prepare per query.

12. **CI-wpdb-array-placeholders.** ai-index.php:256-259, folder-talk.php:2252, guide.php:3227, librarian.php:1495-1496, media-list.php:121, search-meaning.php:421, search-try.php:142, auto-file.php:764-771. Fix: `array_fill` placeholders inside prepare.

13. **CI-prefix-unique.** Core AJAX actions (see 2); `eml_transient` main:521; unprefixed `wp.media.view` names js/vergeml-media-views.js:254,325,354; reassigned core JS globals js/eml-media.js:16, js/eml-admin.js:14,16; `media_category` (justify, don't rename).

14. **CI-prefix-length.** 3-char `eml` names: main:521, js/eml-enhanced-medialist.js:6, js/eml-media-grid.js:73,121,133,162,199,291,347, js/vergeml-media-views.js:376. Fix: `vergeml` prefix.

15. **CI-function-exists.** main:208-1684 wraps 20 own functions in `if ( ! function_exists( 'vergeml_get_slug' ) )`; taxonomies.php:1076,1125 wrap own classes in `class_exists`. Fix: remove the wrappers.

16. **CI-i18n-placeholders.** brief.php:609-610 (translators comment on the wrong call, sentence from three strings); split sentences options-pages.php:908-909, 1161-1162, 3373; taxonomies.php:556. Fix: one string per sentence with placeholders.

Risks noted as PASS but worth a look: Requests::request_multiple bypassing WP_Http (ai.php:825-898), `$pagenow` overwrite in options-pages.php:290, UTM params on buy links (connect.php:51-59), Pro add-on still serving this plugin's updates (memory) — must stop at listing.
