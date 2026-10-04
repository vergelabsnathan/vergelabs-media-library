# Security audit A: main file, uninstall.php, core/a*–h*

Archive: `scratchpad/audit2/vergelabs-media-library/` (4.1.0). 27 files, 24,491 lines.
Method: I enumerated every superglobal read, every `$request->get_param`, every echo/print/printf/HTML return, every `$wpdb` call, every file op, every unserialize and every option/meta write. I read output-bearing and handler code in full. phpcs:ignore/disable comments were ignored and the code judged on its own.

Totals: **FAIL 11 instances** (9 IN-list, 1 unslash, 1 GET-triggered credit spend). **WARN 14 groups** (hardening that a strict reviewer may raise). Everything else PASS.

## Verdict per row

| ID | Verdict (these files) | Evidence file:line | Fix |
|---|---|---|---|
| SEC-1 | PASS (3 WARN) | Describe/folder/planner answers sanitised: ai.php:681-703, folder-talk.php:509-516, 2338, filing.php:3183-3191, ai.php:131 (sanitize_key). Raw remote data stored: get-help.php:82-90 (issues → transient), guide.php:904-906 and brief.php:346-348 (token string → option) | Sanitise on intake (see W-8, W-9) |
| SEC-2 | FAIL (1) | get-help.php:295 | F-10 |
| SEC-3 | PASS (1 WARN) | Every REST write route has a capability callback; admin_post handlers check caps (folder-tools.php:229, get-help.php:196); connect.php:106. admin_init jobs with no cap guard: ai-index.php:896, ai-background.php:118 | W-11 |
| SEC-4 | PASS | No `wp_ajax_nopriv_`; every `register_rest_route` in these files has a non-`__return_true` permission_callback; the shortcode and block are read-only renderers | — |
| SEC-5 | WARN | health-delete.php:481-483 (`delete_posts`, not per-id `delete_post`); bulk-terms.php:165-175 (no `assign_terms`); folder-tools.php:229/245 (any `upload_files` user can zip any folder id, including another user's private folder, because `get_term()` and the tax_query bypass the `get_terms_args` filter in private-folders.php:146) | W-5, W-6, W-7 |
| SEC-6 | PASS | Validation at the top of each handler (e.g. ai.php:2100, ai-background.php:248, health-keep.php:881) | — |
| SEC-7 | PASS | Safelists: ai-screen.php:34, ai.php:2100, ai-background.php:248, folder-talk.php:477, gallery-block.php:184/201, gallery-widgets.php:89/92, admin-menu.php:171, folder-tools.php:59/239, auto-file.php:789/802 | — |
| SEC-8 | PASS | No path comes from input. Every attachment path is confined by `vergeml_path_in_uploads()` (folder-tools.php:103-118, used at :182 and ai.php:1199) | — |
| SEC-9 | PASS | DB values are escaped at echo (bulk-terms.php:123-125, get-help.php:339-345, ai-screen.php:210-404) | — |
| SEC-10 | PASS | — | — |
| SEC-11 | PASS | Type-specific sanitisers: sanitize_email (get-help.php:206), sanitize_textarea_field (get-help.php:204, ai.php:2138, brief.php:87), sanitize_key (slugs/tabs), absint/int (ids) | — |
| SEC-12 | PASS (advisory) | Built-escaped HTML returned, never echoed raw: gallery-block.php:291-337, admin-shell.php:322-335/414-422. Echoes go through wp_kses at admin-shell.php:257/274, ai-screen.php:208, get-help.php:303, health.php:1430, gallery-widgets.php:396 | Optional: rename `$items` → `$items_safe` (gallery-block.php:255) per the `_safe` convention |
| SEC-13 | PASS | `href` → esc_url everywhere (e.g. admin-shell.php:226, ai-screen.php:215/394, get-help.php:334/355); attributes → esc_attr; textarea → esc_textarea (get-help.php:379) | — |
| SEC-14 | PASS | No `_e(` or echoed `__(`; esc_html_e/esc_attr_e/esc_html__ used throughout | — |
| SEC-15 | FAIL (1) | ai-screen.php:305-308: GET `vgml_trial` auto-clicks the credit-spending alt-text run with no nonce | F-11 |
| SEC-16 | PASS | Every `wp_verify_nonce` sits inside an `if` (connect.php:120-124, folder-tools.php:46, get-help.php:289). `check_admin_referer` at folder-tools.php:233, get-help.php:200, bulk-terms.php:150 | — |
| SEC-17 | PASS | Action-specific nonces: `vergeml_zip`, `vergeml_help_send`, `vergeml_help_check`, `vergeml_connect`, core `media-form`/`bulk-media`; main file mints `vergeml-bulk-edit-nonce`, `vergeml-term-counts` | — |
| BOT-sanitize | FAIL (1) | get-help.php:295 is not unslashed. All other reads are unslashed and sanitised, or int-cast: main:812; admin-menu.php:168, 189-191; admin-shell.php:155; ai-screen.php:33; bulk-terms.php:154, 234, 248-250; connect.php:102, 121, 150-151, 237; folder-tools.php:46, 50-51, 235-236; get-help.php:204-206, 289, 296-297. REST params are sanitised or safelisted (see the REST table below) | F-10. Hardening: W-1, W-2, W-3 |
| BOT-sanitize-escape-confusion | PASS | No esc_* used on input. esc_url_raw at connect.php:139 is a redirect target, which is its correct use | — |
| BOT-sanitize-filter-functions | PASS | No filter_input/filter_var in these files | — |
| BOT-sanitize-nonces | PASS | connect.php:121 and folder-tools.php:46 use sanitize_text_field(wp_unslash()). get-help.php:289 uses sanitize_key(wp_unslash()) | Optional: switch get-help.php:289 to sanitize_text_field to match the documented pattern exactly |
| BOT-nonces-user-permissions | FAIL (1) | admin_post_vergeml_zip (folder-tools.php:225-233): cap + nonce. admin_post_vergeml_help_send (get-help.php:192-200): cap + nonce. admin_init connect (connect.php:93-124/150-157): cap + nonce/state. bulk handler (bulk-terms.php:150/175): nonce + per-item cap. Exception: ai-screen.php:305 | F-11 |
| BOT-escape | PASS | Full output enumeration below; no unescaped variable output | — |
| BOT-escape-html | PASS | HTML helpers are printed through `wp_kses( …, vergeml_kses_allowed() )`; captions through wp_kses_post (gallery-block.php:288) | — |
| BOT-unsafe-sql | WARN | Every value is placeheld. Unprepared static queries and local-variable table names (`$t`, `$table`) are what the bot flags | W-4 (instance list) |
| BOT-sql-array-placeholders | **FAIL (9)** | `IN (` built with `implode(',', ints)`: ai-index.php:257-259; auto-file.php:573 and 770 (list from :639); filing.php:1143/1153, 1177/1183; folder-talk.php:1127, 2252; guide.php:1914, 3227 | F-1…F-9 |
| BOT-rel-prepare | WARN | Same as BOT-unsafe-sql | W-4 |
| BOT-rel-file-ops | PASS | ZipArchive writes only to a wp_tempnam() file (folder-tools.php:243/148) and adds only paths confined to uploads (:182). readfile/wp_delete_file are on that temp file only (:260-262). ai.php:1218-1229 uses wp_tempnam, and the source path is confined (:1199). health.php:183 fopen is read-only on an attachment path (no input). No download_url/sideload | — |
| BOT-rel-unserialize | PASS | Only maybe_unserialize at health.php:938, on core `_wp_attachment_metadata` read from the DB. No unserialize of remote, cookie or request data. The base64_decode at ai.php:353 is licence-key decryption (sealed with wp_salt) | Note for POST-12 greps: ai.php:353 base64_decode is legitimate |
| BOT-rel-options-meta | PASS (1 WARN) | Every input-driven write is behind manage_options REST + wp_rest nonce: ai.php:2148, brief.php:60, guide session, folder-talk apply, health-keep undo, connect.php:199. Option/meta keys are all `vergeml_`/`_vergeml_` prefixed (verified every constant) | W-11 (admin_init writes without a cap guard) |
| POST-4 (nonce/SQL halves) | Nonce PASS · SQL FAIL | No `wp_redirect(`; every redirect is wp_safe_redirect. No eval/create_function | F-1…F-9; W-4 |
| POST-12 (nonce/SQL/escape) | FAIL (SQL) | As above. No eval/system/exec/shell_exec. base64_decode only at ai.php:353 (decrypt) | F-1…F-9 |
| POST-16 | FAIL (SQL) | REST permission_callbacks: all real caps. File writes: temp only. prepare: IN lists | F-1…F-9 |
| POST-53 | FAIL (sanitise 1) | Escaping PASS. Inline JS via wp_add_inline_script (main:107-113; ai-screen.php:308; get-help.php:380). Nonces PASS | F-10 |

### REST params (BOT-sanitize, `$request->get_param`)

| file:line | param | Handling | Verdict |
|---|---|---|---|
| ai-background.php:573,579,580 | action, scope, apply_alt | `'stop' ===`; scope safelisted in run_start:248; bool | PASS |
| ai.php:1836 | limit | int, clamped 1–200 | PASS |
| ai.php:2044 | line | truthiness only | PASS |
| ai.php:2098-2107 | scope, limit, apply_alt | in_array safelist; int; bool | PASS |
| ai.php:2127-2145 | license_key, site_profile, flags | sanitize_text_field; sanitize_textarea_field + substr; (int)(bool) | PASS |
| auto-file.php:680-691, 779-781 | term_id, attachment_id, limit, action | int; int clamp; action compared to 'dismiss'/'accept' | PASS |
| brief.php:675-722 | reset, draft, said, say | bool; clean_lines (sanitize_textarea_field); kind safelisted; sanitize_textarea_field/sanitize_text_field | PASS |
| folder-talk.php:2840-2857 | history, instruction, mode | history substr only; instruction trim only (folder-talk.php:428); mode safelisted at :477 | WARN W-2 |
| folder-talk.php:2879-2899 | folders, plan_id | sanitize_text_field/sanitize_key per field; preg_replace alnum | PASS |
| gallery-block.php:368 | taxonomy | sanitize_callback sanitize_key + safelist | PASS |
| guide.php:1089-1090 | id, answer | sanitize_text_field | PASS |
| guide.php:1466-1526 | reset, draft, turns, said, say | bool; vergeml_guide_clean_draft (sanitize_text_field per field, key regex) | PASS |
| guide.php:3339 | rule, options | (string)/(array) → vergeml_guide_rule_args safelist | PASS |
| health-delete.php:506-507, 525 | keep, drop, ids | int; array_map intval | PASS |
| health-keep.php:854-879 | keep, drop, token, ids, undo | int; token used only as an array key (not sanitised); intval; bool | WARN W-3 |
| health.php:1219-1224 | reset, cursor | bool; int | PASS |

### Output enumeration (BOT-escape)

Every echo/print/printf in these files, checked one by one:
- admin-menu.php: 215, 277-296, 281-286, 313-317. All escaped.
- admin-shell.php: 209-283. Each variable is escaped. 227-228, 555 and 765 are literal strings. 257 and 274 use wp_kses. 754-759 escapes each part.
- ai-screen.php: 185-521. All escaped. The literal ternaries at 215, 261, 296 and 385 are safe.
- bulk-terms.php: 119-129, 240-243, 267-270. All escaped.
- connect.php: 243-283. All escaped.
- folder-tools.php: 230, 240, 251. All esc_html in wp_die. Line 256 sends the term slug in a header; slugs are WP-sanitised.
- gallery-widgets.php:396 uses wp_kses.
- get-help.php: 197-413. All escaped.
- guide.php: 458, 491-511. All escaped. 503 is a literal ternary.
- health.php: 1410, 1430-1436. Escaped, then wp_kses.
- help.php:134-138. Escaped.
- main: 1449-1485 escaped.

**No unescaped output found.**

## INSTANCES

### FAIL

All nine IN-list fixes use the same pattern:
```php
$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
$wpdb->prepare( "… IN ( $ph ) …", ...$ids )
```
When the query already has other placeholders, merge the arguments, e.g. `array_merge( array( VERGEML_FILING_LOCKED, $taxonomy ), $term_ids )`.

- **F-1 ai-index.php:257-259.** `$in = implode( ',', $chunk )` goes into `get_results( "… WHERE attachment_id IN ($in)" )` without prepare.
  - Fix: `$ph = implode( ',', array_fill( 0, count( $chunk ), '%d' ) ); $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN ( $ph )", $chunk ), ARRAY_A );`
- **F-2 auto-file.php:573** (inside the prepare at :563). `IN ( {$tt} )`, where `$tt` is the imploded string from `vergeml_autofile_tt_ids()` (auto-file.php:639).
  - Fix: change `vergeml_autofile_tt_ids()` to return the int array (use `array( 0 )` when empty). At the call site, build `$ph` from it and pass `array_merge( $tt_ids, array( (int) $limit ) )` to prepare.
- **F-3 auto-file.php:770** (the get_var at :764 has no prepare at all). Same `IN ( {$tt} )`.
  - Fix: same as F-2, and wrap the query in `$wpdb->prepare( …, $tt_ids )`.
- **F-4 filing.php:1143/1153.** `$in = implode( ',', $term_ids )` goes into `tt.term_id IN ({$in})`.
  - Fix: `$ph` from `$term_ids`, then `$wpdb->prepare( "… IN ( $ph ) …", array_merge( array( VERGEML_FILING_LOCKED, $taxonomy ), $term_ids ) )`.
- **F-5 filing.php:1177/1183.** `$in = implode( ',', array_keys( $stale ) )`.
  - Fix: `$keys = array_map( 'intval', array_keys( $stale ) )`, build `$ph`, then `prepare( …, array_merge( array( $taxonomy ), $keys ) )`.
- **F-6 folder-talk.php:1127.** `' AND i.attachment_id IN (' . implode( ',', array_map( 'intval', $state['round_ids'] ) ) . ')'` is spliced into the prepared SQL.
  - Fix: build `$only` with `$ph` placeholders and append the ids to that prepare's argument list. Or prepare the fragment on its own: `$only = $wpdb->prepare( " AND i.attachment_id IN ( $ph )", $round_ids );`
- **F-7 folder-talk.php:2252.** `get_results( "… WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')' )`, with no prepare.
  - Fix: `$ph` from `$chunk`, then `$wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, embedding, kind, filing, caption FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN ( $ph )", array_map( 'intval', $chunk ) ), ARRAY_A )`.
- **F-8 guide.php:1914.** `… WHERE i.attachment_id IN (" . implode( ',', $chunk ) . ')'` inside a prepare that also binds `VERGEML_FILING_PLACED_BY`.
  - Fix: `$ph` from `$chunk`, and pass `array_merge( array( VERGEML_FILING_PLACED_BY ), $chunk )`.
- **F-9 guide.php:3227.** `get_results( "… WHERE i.attachment_id IN (" . implode( ',', $chunk ) . ')' )`, with no prepare.
  - Fix: `$ph` from `$chunk`, then `$wpdb->get_results( $wpdb->prepare( "SELECT i.attachment_id, i.embedding, i.tags, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.attachment_id IN ( $ph )", $chunk ), ARRAY_A )`.
- **F-10 get-help.php:295.** `sanitize_key( $_GET['vgml-help'] )` is missing wp_unslash.
  - Fix: `$state = isset( $_GET['vgml-help'] ) ? sanitize_key( wp_unslash( $_GET['vgml-help'] ) ) : '';`
- **F-11 ai-screen.php:305-308** (link minted at offer.php:195). The mere presence of `?vgml_trial` prints a script that auto-clicks `#vgml-ai-alt`, which starts a library-wide alt-text run that spends credits. There is no nonce, so a crafted link sent to an admin starts it.
  - Fix in offer.php:195: `wp_nonce_url( add_query_arg( 'vgml_trial', '1', admin_url( 'admin.php?page=vergeml-ai' ) ), 'vergeml_ai_trial' )`.
  - Fix in ai-screen.php:305: change the condition to `isset( $_GET['vgml_trial'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vergeml_ai_trial' )`.

### WARN (hardening; values are already safe)

- **W-1 guide.php:45.** `'media-guide' === $_GET['page']` compares the raw value. Fix: `isset( $_GET['page'] ) && 'media-guide' === sanitize_key( wp_unslash( $_GET['page'] ) )`.
- **W-2 folder-talk.php:2855** (and :428), plus the history text at :2847. The instruction and history are sent to the service unsanitised. Fix:
  - `sanitize_textarea_field( (string) $request->get_param( 'instruction' ) )`
  - `'text' => substr( sanitize_textarea_field( (string) $turn['text'] ), 0, 400 )`
  - Add `'sanitize_callback' => 'sanitize_textarea_field'` to the `instruction` arg at :2747.
- **W-3 health-keep.php:836/866.** The `token` arg has no sanitize_callback. Fix: add `'sanitize_callback' => 'sanitize_key'` to the arg (tokens are alnum from wp_generate_password(12,false)).
- **W-4 Unprepared SQL with no variable values.** The bot flags these, so prepare them, using `%i` for table names (WP ≥ 6.2; the plugin requires 6.5):
  - uninstall.php:36, 112 (`DROP TABLE … {$wpdb->prefix}{$table}`), 122, 189. Also uninstall.php:165, which escapes LIKE by hand: use `$wpdb->esc_like( $wpdb->base_prefix )`.
  - ai-index.php:55, 666-672
  - ai-screen.php:117, 119, 122, 123, 124, 125, 128, 136, 142, 143 (`{$t}` local)
  - ai.php:1783 (helper-built FROM), 2029
  - brief.php:197, 201
  - filing.php:517
  - folder-talk.php:183-185, 238, 239, 274-277, 954-957
  - guide.php:271-274, 367, 417, 523, 737-743 (`{$t}`), 2793 (assembled from prepared fragments plus `{$t}`)
  - health.php:493-495
  - auto-file.php:764-772
  - Local table variables inside prepare: guide.php:406-414, 786-791 (`{$t}`); auto-file.php:563-576 (`{$table}`); ai-folders.php:205 (`{$table}` in a posts_clauses JOIN).
  - Fix: a `$wpdb->prepare()` around each. Example: `$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE error = %s AND embedding IS NOT NULL', $wpdb->vergeml_ai_index, '' ) )`. Literal LIKE patterns become `%s` args, e.g. `'%' . $wpdb->esc_like( '"audience":"' ) . '%'`. Replace `{$t}`/`{$table}` with `{$wpdb->vergeml_ai_index}` or `%i`.
- **W-5 health-delete.php:481-483.** The capability is the generic `delete_posts`. Fix: keep the route callback, and in `vergeml_health_delete_copies()` refuse when `! current_user_can( 'delete_post', $id )` for any `$drop` id (and `edit_post` for the repointed sources).
- **W-6 bulk-terms.php:165.** No `assign_terms` check before wp_set_object_terms/wp_remove_object_terms. Fix: after the `$term` check add `$tax = get_taxonomy( $taxonomy ); if ( ! $tax || ! current_user_can( $tax->cap->assign_terms ) ) return add_query_arg( 'vergeml_op', 'noterm', $location );`
- **W-7 folder-tools.php:229-245.** Any `upload_files` user can zip any folder id, including another user's private folder (`get_term()` and the tax_query bypass the private-folders `get_terms_args` filter). Fix: after the taxonomy check, refuse unless the term is visible to the current user (the private-folders owner check), or require `manage_categories`.
- **W-8 get-help.php:82-90.** The remote issues list is cached raw (SEC-1). Fix: map each issue through `array_map( 'sanitize_text_field', … )` for name/version/summary/workaround/status/slug/dependency/issue before `set_transient`.
- **W-9 guide.php:905 and brief.php:347.** The remote token is stored without sanitising. Fix: `sanitize_text_field( (string) $data['token'] )`.
- **W-10 get-help.php:289.** The nonce is sanitised with sanitize_key; the documented pattern is sanitize_text_field. Optional alignment.
- **W-11 ai-index.php:896 (vergeml_index_maybe_migrate) and ai-background.php:118 (vergeml_ai_run_revive).** These admin_init handlers write options and schedule cron for any user who loads wp-admin. They take no input, but reviewers read admin_init as a handler. Fix: start each with `if ( ! current_user_can( 'manage_options' ) ) { return; }`. The `wp_doing_cron()`/CLI provisioning paths are unaffected.
- **W-12 gallery-block.php:255/335.** The HTML is escaped while it is built, in a variable without a `_safe` suffix (SEC-12 convention). Optional: rename to `$items_safe`.
- **W-13 POST-12 grep note, ai.php:353.** `base64_decode` is AES-GCM licence-key decryption, not obfuscation. Keep it, and be ready to explain it if asked.
- **W-14 vergelabs-media-library.php:812.** The whole `$_GET` is localised to JS via `map_deep( wp_unslash( $_GET ), 'sanitize_text_field' )` → wp_json_encode. This is read-only and sanitised, so PASS. Listed so no reviewer is surprised by it.
