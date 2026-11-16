# SEC audit, part b: core/ i–z and js/

STATUS: PARTIAL. I checked the instances below myself, by grep and by reading the code around each one. Eight full-read passes were started: options-pages group, librarian+journey, organize+plan-tree, taxonomies+smart-folders group, rest/rename/import group, search/nl/tree/misc group, JS part 1 and JS part 2. None had reported when this run was told to hand back. Their results are NOT in this file. Rows marked OPEN still need a full read of every line.

## Rows

| ID | Verdict (these files) | Evidence | Fix |
|---|---|---|---|
| SEC-1 | OPEN | Storage of AI strings in librarian/organize/plan-tree is not yet read | Full-read pass |
| SEC-2 | WARN | Every superglobal read is listed in the INSTANCES section. Most are unslashed and sanitized. The exceptions are raw `===` reads and `tax_input` | See I-5..I-8 |
| SEC-3 / SEC-4 / SEC-5 | PASS (checked parts) | No `wp_ajax_nopriv_`. Every REST route in i–z has a permission_callback (see list below). No `__return_true` permission. The admin_init handlers checked use nonce + `manage_options` | — |
| SEC-6..SEC-8, SEC-10, SEC-11 | WARN | `$_FILES` names use sanitize_file_name / sanitize_mime_type. The import does not validate type or size (I-4) | I-4 |
| SEC-9 / SEC-12 / SEC-13 / SEC-14 | OPEN | Outputs found by grep: watchdog, journey, rename, smart-folders, licence-page and options-pages escape their dynamic values. Template blocks between `?>`/`<?php` are not yet read | Full-read pass |
| SEC-15 | PASS (checked parts) | Every nonce check found is paired with current_user_can | — |
| SEC-16 | PASS | Every wp_verify_nonce result found is used in an `if`. check_ajax_referer dies by default | — |
| SEC-17 | PASS | Nonces are specific to each action (`vergeml_settings_import_nonce`, `update-post_{id}`, `vergeml_dismiss_neighbours`, `vergeml_meaning` …). transport uses `wp_rest` on purpose | — |
| BOT-sanitize | WARN | I-5, I-6, I-7, I-8 | unslash + sanitize before comparing |
| BOT-sanitize-escape-confusion | PASS (grep) | No `esc_*` applied to `$_` input | — |
| BOT-sanitize-filter-functions | PASS | No filter_input / filter_var in i–z | — |
| BOT-sanitize-nonces | PASS | Every nonce read is `sanitize_text_field( wp_unslash() )`: neighbours.php:130, options-pages.php:1369/1408/1557/1602/1696/1744/1768, search-meaning.php:725, transport.php:40-43 | — |
| BOT-nonces-user-permissions | PASS (checked parts) / OPEN (options-pages 1220, rename.php 344) | — | — |
| BOT-escape | **FAIL** | options-pages.php:1581 `echo json_encode( $settings );` | `echo wp_json_encode( $settings );` |
| BOT-escape-html | **FAIL** | taxonomies.php:1159 `$output .= json_encode( $el );` | `wp_json_encode( $el )` |
| BOT-unsafe-sql | PASS (no injection found) | Every unprepared query checked uses only $wpdb tables, literals or int-cast ids | — |
| BOT-sql-array-placeholders | **FAIL** | librarian.php:1495-1496 and search-try.php:142 build `IN (` from `implode(',', array_map('intval', …))` | array_fill %d + prepare (I-3) |
| BOT-rel-prepare | WARN | Literal or table-only queries without prepare (I-9). search-try.php:80/84/85/87 are assembled from prepared pieces | Wrap in prepare where there is a value. Literal-only queries can stay |
| BOT-rel-file-ops | PASS (checked parts) | options-pages.php:1651 reads only `is_uploaded_file()` tmp_name. transport.php:138 reads the plugin's own file. import-csv.php:248 uses php://temp. rename-file.php:376 move uses WP_Filesystem_Direct. Where its paths come from is OPEN | — |
| BOT-rel-unserialize | PASS | No `unserialize(` / `maybe_unserialize(` in i–z | — |
| BOT-rel-options-meta | WARN | Settings import (I-4) writes four options. Values pass the register_setting sanitize callbacks through update_option, because vergeml_register_setting runs earlier on the same admin_init. Keys are not checked | I-4 |
| POST-4 / POST-12 / POST-16 | WARN | No eval, create_function or exec. Every redirect is wp_safe_redirect. organize.php:2415 `base64_decode` decodes the plugin's own packed floats (not obfuscation, but the review greps for it) | Consider `pack`/hex storage, or document it in the readme |
| POST-53 | WARN | transport.php:141 prints the plugin's own JS inline through wp_add_inline_script. That is allowed, but review prefers an enqueued file | Keep, or enqueue js/vergeml-transport.js with a priority-0 dependency |

REST permission_callbacks in i–z (all present): import-ui:40 vergeml_can_import; instrument:274 closure; librarian:2435-2500 manage_options, 2522 closure; nl-commands:553/562; organize:2883-2913; plan-tree:1850-1854; private-folders:217 vergeml_private_can_edit; quarantine:390-412; quick-edit:164 closure; rename-file:612/640 closures; rename:501 closure, 533; rest-folders:31/50/59; rest-tree:166/187; search-meaning:607; search-try:197; smart-folders:1132; utilities:406-432. The bodies of the closures and the custom callbacks are OPEN.

## INSTANCES

- I-1 FAIL options-pages.php:1581 `echo json_encode( $settings );` → `echo wp_json_encode( $settings );`
- I-2 FAIL taxonomies.php:1159 `$output .= json_encode( $el );` → `$output .= wp_json_encode( $el );`
- I-3 FAIL librarian.php:1495-1496 `$in = implode(',', array_map('intval',$ids)); … IN ($in)` → `$ph = implode(',', array_fill(0, count($ids), '%d')); $wpdb->get_results( $wpdb->prepare( "SELECT … FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL AND attachment_id IN ($ph)", array_map('intval',$ids) ), ARRAY_A );`
- I-3b FAIL search-try.php:142, the same pattern → the same fix.
- I-4 WARN options-pages.php:1626-1666 settings import. Nonce, manage_options and is_uploaded_file are all present. Missing: an extension/type check, a size cap, and a check that each of `taxonomies`, `lib_options`, `tax_options`, `mimes` is set and is an array before update_option. Fix: `if ( 'json' !== strtolower( pathinfo( $import_file['name'], PATHINFO_EXTENSION ) ) || $import_file['size'] > 1048576 ) { error; return; }`, then `foreach ( array('taxonomies','lib_options','tax_options','mimes') as $k ) if ( ! isset( $settings[$k] ) || ! is_array( $settings[$k] ) ) { error; return; }`.
- I-5 WARN taxonomies.php:1254-1260 `$_REQUEST['tax_input'][ $taxonomy ]` is read without wp_unslash. Its keys are intval'd afterwards, and the nonce + edit_post checks run first. Fix: `$tax = map_deep( wp_unslash( (array) $_REQUEST['tax_input'][ $taxonomy ] ), 'absint' ); $term_ids = array_map( 'intval', array_keys( $tax, 1 ) );`
- I-6 WARN librarian.php:3173 `'query-attachments' === $_REQUEST['action']` is a raw read (read-only, so acceptable). Fix: `sanitize_key( wp_unslash( $_REQUEST['action'] ) )`.
- I-7 WARN media-list.php:457-462 and taxonomies.php:521, 752 compare `$_REQUEST[...]` raw against literals (read-only list filters, so acceptable). Fix: `sanitize_key( wp_unslash( … ) )` before `===`.
- I-8 WARN read-only flag reads that use only isset/empty: mime-types.php:29 (inside the sanitize callback, after options.php has checked the nonce), page-builders.php:234, post-folders.php:244, options-pages.php:1366/1501/1554/1599/1693/1741/1765 (submit-button presence only, with the nonce checked next). Acceptable.
- I-9 WARN unprepared queries that hold only literals or $wpdb tables: import-ui.php:230-231; librarian.php:2805; organize.php:2810; plan-tree.php:109, 383, 566, 1447; options-pages.php:1938 (`DROP TABLE … {$table}`: $table must come from a fixed list, OPEN), 2023; taxonomies.php:1405. No injection. Optionally wrap them in prepare.
- I-10 PASS read-only int/sanitized display reads: journey.php:445-469, 1112-1142, 1182-1186 (int casts); rename.php:403-466 (int); licence-page.php:154-155 (sanitize_key); media-list.php:348, 732-737; quarantine.php:239, 261; smart-folders.php:1168-1200; search-meaning.php:681-688; options-pages.php:245; page-builders.php:154; import-csv.php:191; taxonomies.php:350, 480, 756-760, 818-819.
- I-11 PASS state changes with nonce + capability: neighbours.php:126-140; licence-page.php:46-79 (nonce/cap OPEN at lines 46-63); options-pages.php:1366-1375, 1408, 1554-1581, 1599-1666, 1693, 1741-1750, 1765; search-meaning.php:725 (nonce + manage_options); taxonomies.php:1185-1279 (check_ajax_referer update-post_{id} + edit_post); taxonomies.php:1352-1410 (nonce + edit_post on each item; IN() through %d array_fill).
- TRANSPORT transport.php:33-101: wp_verify_nonce('wp_rest') on a header or `_wpnonce` that is sanitized and unslashed, plus current_user_can('upload_files'). The route is limited to the `/vergeml/v1/` prefix. WP route regexes are anchored with `^…$`, so `../` cannot reach another namespace. Methods are allowlisted. The php://input params are passed on unsanitized, and the target route's args or callback must sanitize them (rest-folders and rest-tree declare sanitize_callback; the other routes are OPEN). The bridge's verdict is PASS with a WARN. Fix: `if ( ! preg_match( '#^/' . preg_quote( VERGEML_REST_NS, '#' ) . '/[a-z0-9/_-]+$#i', $route ) || false !== strpos( $route, '..' ) )` reject.
- OPEN options-pages.php:1220-1256 `switch ( $_REQUEST['settings'] )`: not yet confirmed as compared only against literals.
- OPEN rename.php:344 `$_GET['id']` (int) followed by a redirect at 354: the handler's nonce and capability are not yet confirmed.
- OPEN JS: every innerHTML/.html()/insertAdjacentHTML sink in js/ is unjudged. The two JS passes did not report.

## Added: full-read passes that reported (librarian.php + journey.php, organize.php + plan-tree.php)

Every line of these four files was read. No FAIL that a reviewer would reject on. Their WARNs:

- W-A BOT-nonces-user-permissions / BOT-rel-options-meta. Two `admin_init` housekeeping handlers have no capability check:
  - librarian.php:332-360: vergeml_librarian_housekeeping runs dbDelta (152-269), update_option at 268/359, and a daily prune that DELETEs from the plugin tables.
  - organize.php:304-339: vergeml_organize_housekeeping runs dbDelta, a daily DELETE of old runs, and update_option('vergeml_organize') at 281/338.
  admin_init fires for any logged-in user and for admin-post.php. No value in these writes comes from the request.
  FIX: make `if ( ! current_user_can( 'manage_options' ) ) { return; }` the first line of each. Better: run the schema check on activation/upgrade and the prune from WP-Cron.
- W-B librarian.php:1485-1496: the `IN ($in)` implode (I-3) sits in dead code. vergeml_librarian_apply_step never declares `global $wpdb;`, so `isset( $wpdb->vergeml_ai_index )` is always false. This is a functional bug: the veto never runs. Add `global $wpdb;` together with the I-3 fix.
- W-C SEC-1. AI-derived strings are stored without sanitize_text_field. They are escaped on output (librarian.php:3120/3186), but should be sanitized when stored. Locations and fixes:
  - librarian.php:1726-1795 (move reasons). Fix: sanitize_text_field in vergeml_librarian_move_reason.
  - organize.php:718 (tags feeding the labels and why/reason at 1241, 1817, 1969, 2196). Fix: `'tags' => array_map( 'sanitize_text_field', vergeml_index_tags_out( $row['tags'] ) )`.
  - plan-tree.php:1505. Fix: `$name = sanitize_text_field( $name )`.
  - plan-tree.php:956. Fix: `(int) $result['runs']`.
  - plan-tree.php:1056/1272. Fix: shape-check `is_array( $f ) && isset( $f['path'] )`, and run `$f['parent']` through sanitize_text_field.
- W-D REST args lack sanitize_callback; the callbacks cast or whitelist the values themselves, so the end result is safe:
  - librarian.php:2441-2472: scheme and branches (branches is untyped).
  - organize.php:2886-2907: run_id, parent_run_id, compare.
  - plan-tree.php:1851-1854: no args at all; price and answer are read in the callback.
  FIX: declare args, with 'absint' for the ints, 'sanitize_key' for scheme and answer, and a count cap on branches.
- W-E organize.php:2921 / 444: the `refine` object is checked only with is_array, then stored raw in the run params. Store the filtered `$wanted` instead.
- W-F plan-tree.php:787-809: GET /guide/plan can write the session, refund credits through a remote call, and reschedule cron. CSRF is blocked by the REST nonce. Move the writes to POST.
- W-G organize.php:2415: base64_decode on the plugin's own packed floats. Pass strict=true, or store them as bin2hex.
- W-H plan-tree.php:349/860: `@set_time_limit` silences errors. Guard it with function_exists and drop the `@`.
- W-I librarian.php:3183-3195: `$html` is built from escaped parts. Rename it `$html_escaped` (SEC-12).
- W-J organize.php:683/2841: these use a correct %d array_fill inside prepare. PASS.
- Every journey.php admin_post handler (408, 427, 1080, 1161) has a manage_options check and check_admin_referer before it runs. All output in journey.php is escaped late.
- No superglobal reads in organize.php or plan-tree.php.
- Every REST route in all four files is behind manage_options, except librarian-why, which checks edit_post on the id.
- Still to verify outside these four files: vergeml_guide_clean_draft, vergeml_guide_save, vergeml_filing_classes_of_object and vergeml_ai_service_url (is it overridable by a user?).
