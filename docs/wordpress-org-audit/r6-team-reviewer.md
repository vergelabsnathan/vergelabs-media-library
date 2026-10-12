# Review 6 (simulated Plugins Team email): VergeLabs Media Library 4.1.0

**Subject:** [WordPress Plugin Directory] Review in Progress: VergeLabs Media Library

Hello,

Thank you for the update. We downloaded the zip as uploaded (147 files: 68 PHP, 43 JS, 22 CSS) and read it with every `phpcs:ignore` / `phpcs:disable` annotation ignored. We started from the main file and followed activation, every `admin_enqueue_scripts` / `wp_enqueue_*` site, every REST route, AJAX and admin-post handler against what it writes, every `$wpdb` call, every output statement, every outgoing request, the uninstall file and the readme.

Round 5's items are fixed where we pointed: activation calls each installer by name (`vergelabs-media-library.php:373-392`), mbstring and OpenSSL are checked (`:1581-1619`), the main admin sheet and script stop outside the media screens and your own pages (`:793-823`), and there is no `wp_add_inline_script()` left. The crash watchdog is now a constant the owner sets (`core/watchdog.php:20-23`), nothing deactivates a plugin, and both wipes leave `media_category` alone (`uninstall.php:67`, `core/options-pages.php:1534`).

We still found the issues below. As always, these are examples, not every instance: please check each **class** across the whole plugin.

Each issue says whether we would keep the plugin pending for it.

---

## Issues

### 1. Processing the whole input

**Guideline:** Security, "Processing the whole input": *"We strongly recommend you never attempt to process the whole $_POST/$_REQUEST/$_GET stack."*

- `core/admin-menu.php:200` `foreach ( array_keys( $_GET ) as $key ) {` walks every key of the request on the old-address redirect and copies any key matching `^(vgml|vergeml)[-_]`.

This is the only one left. A ripgrep over every PHP file for `\$_(GET|POST|REQUEST)\s*\)|\$_(GET|POST|REQUEST)\s+as\b|\(\s*\$_(GET|POST|REQUEST)\s*[,)]|get_params\(\)|get_json_params\(\)|get_body_params\(\)` returns 1 hit: this line. Your own earlier audit flagged the same loop (`audit-G-CI.md:267`, "Fix: allow-list of keys"). The fix added an allow-list on lines 195-198 and kept the whole-`$_GET` loop next to it.

**Fix:** list the plugin's own notice and flag keys literally (`vgml_trial`, `vgml_renamed`, `vgml_renamed_back`, `vgml_alt_written`, `vgml_files_renamed`, `vgml_files_back`, `vgml_moved`, `vgml_to`, `vgml-help`, `vgml-check`, `vgml_smart`, `vgml_meaning`, `vergeml_connected`, `vergeml_licence`, …) and read only those.

**Pend: yes.** The guideline names this exact case, and our scanner matches `( $_GET )`.

### 2. A style on every admin page

**Guideline:** the activation checklist from our last email ("only load your styles and scripts on the pages that need them"); CI-enqueue-admin.

- `core/admin-menu.php:80` hooks `vergeml_hide_submenu` on `admin_enqueue_scripts` with no screen check.
- `core/admin-menu.php:84-89` then registers a handle and prints `#adminmenu .toplevel_page_vergelabs-media .wp-submenu{display:none}` inline. It does this on the Dashboard, Plugins, Users, Settings and every other admin screen, for every administrator.
- `readme.txt:175` (changelog) says "Scripts and styles load only on the plugin's screens and media frames", and your reply to round 5 says the same. Viewing the source of the Dashboard shows otherwise.
- Your clean-site walk checks that those screens "load none of the plugin's files". An inline style is not a file, so the walk cannot catch this.

**Fix:** return early unless `vergeml_shell_current()` is non-empty. The duplicate menu only appears where your top-level item is expanded, which is on your own screens. Alternatively, register the sub-screens with an empty parent and keep the top item highlighted with the `parent_file` / `submenu_file` filters.

**Pend: likely.** It is the exact item we asked about last time, and the readme claims it is done.

### 3. Changing other plugins' and WordPress's taxonomies on every request

**Guidelines:** Guideline 11, BOT-hijack-admin; HB-64 / HB-66 (do not modify what is not yours); the "interfering with other plugins" class from our earlier review.

`vergeml_on_wp_loaded()` runs on `wp_loaded`, on every request including the front end (`vergelabs-media-library.php:680-775`):

- **`:758-763`:** for every taxonomy on attachments that is not the plugin's own, it unsets `update_count_callback` when that is `_update_generic_term_count`. A third-party media taxonomy that chose to count every file now counts only attached ones.
- **`:765-771`:** for **every** taxonomy registered on `post` (core's `category`, `post_tag`, `post_format`, and any plugin's), it either replaces the count callback with `vergeml_update_post_term_count` or unsets it. Unsetting is harmless for core's own, because core falls back to the same counter. It silently breaks any plugin that registers a post taxonomy with its own counter. Co-Authors Plus is one example: its `author` taxonomy counts posts through its own callback.
- **`:717-726`:** calls `register_taxonomy_for_object_type()` / `unregister_taxonomy_for_object_type()` on "foreign" taxonomies from a stored flag. A taxonomy first seen before its plugin attached it to media stays detached.
- **`:774`:** `update_option( 'vergeml_taxonomies', … )` on every request.

**Fix:** touch only the taxonomies this plugin registers, or ones the owner explicitly assigned to media on your settings screen. Never unset or replace a callback another plugin set. Drop the `post`-taxonomy branch entirely.

**Pend: yes, if the reviewer reads this far into the main file.** It is code that changes other plugins' behaviour site-wide. It is inherited from Enhanced Media Library, but the fork is reviewed as a new plugin.

### 4. SQL built from interpolated fragments (unsafe SQL class, round 4)

**Guideline:** "WPDB: Unsafe SQL calls"; BOT-unsafe-sql.

- `core/filing.php:1576-1590` `vergeml_filing_words_sql( $i = 'i' )` and `:1596-1613` `vergeml_filing_product_sql( $i = 'i' )` return `select` / `join` strings with a variable interpolated into SQL (`{$i}.attachment_id`, `{$i}.alt`, 7 places).
- Both functions are never called. `grep -rn "vergeml_filing_words_sql\|vergeml_filing_product_sql"` finds only the two definitions and comments in `auto-file.php:268`, `folder-talk.php:1134/1136` and `guide.php:1906-1907/3332`.
- But in round 4 we asked you to remove exactly this shape: SQL assembled from fragments with variables in it. Grepping `{$` in SQL across the plugin finds these 7 lines among the core-table names.

**Fix:** delete both functions, and the comments that cite them.

**Pend: yes, if seen.** Round 4 was 78 incidences of this class.

### 5. Fatal error when the features are off (safe mode, or no mbstring)

**Guidelines:** DEV-201 / HB-31 (no PHP errors with `WP_DEBUG` on); the activation checklist ("anything it depends on is checked before use, with an explanation instead of failing").

When `vergeml_features_off()` is true, `core/taxonomies.php` is not loaded. This happens with `VERGEML_SAFE_MODE` set (documented in `readme.txt:101`) or on a host without mbstring (your new round-5 path). The main file still names its functions:

- `vergelabs-media-library.php:660` registers `media_category` with `'update_count_callback' => 'vergeml_update_attachment_term_count'`, defined only at `core/taxonomies.php:1550`.
- `vergelabs-media-library.php:768` sets `vergeml_update_post_term_count`, defined only at `core/taxonomies.php:1582`.

`wp_update_term_count_now()` calls the callback without checking it exists. On PHP 8 that is a fatal TypeError for any term-count update on a folder:

- deleting an attachment that is in a folder (`wp_delete_attachment` → `wp_remove_object_terms`);
- ticking a Media Category in the attachment edit screen, which stays visible because `show_ui` is true.

The delete stops halfway: the attachment's term relationships are deleted, but the post and its files stay. Lines `:636` and `:653` already guard their calls with `function_exists()`; these two do not.

On the same path, without OpenSSL:

- the trial (`core/offer.php:187`) and Connect (`core/connect.php:198`) receive a key from the service, store an empty string, and report success. The free try is spent and nothing is stored.
- The offer box still shows (`core/offer.php:31-35` does not check OpenSSL).

**Fix:** name the callback only when `function_exists()`, skip the `:735-772` loop when the features are off, or move the two counters into the main file. Refuse the trial and Connect, with the notice text, when `openssl_encrypt` is missing.

**Pend: yes if found** (a fatal is always sent back). We would only find it by testing safe mode or reading the fallback; a normal test site does not hit it.

---

## Questions we may ask (not a reason to pend on their own)

1. **Media-modal CSS scope.** The selectors now carry `.eml-media-css`, but `js/vergeml-media-views.js:943` adds that class to `<body>` on every screen where a media frame can open (the block editor, the Customizer, other plugins' pages). For example, `css/eml-admin-media.css:494-505` restyles every `<select>` in core's modal there.
   - The two tree selectors `css/vergeml-tree.css:1210-1211` (`.attachment[draggable="true"]`, `#the-list tr[draggable="true"]`) have no prefix at all.
   - We would accept "the media modal is the surface this plugin extends". Prefix the two tree rules anyway.
2. **Core `wp.media` prototypes replaced globally**, inherited from Enhanced Media Library: `_.extend( media.view.X.prototype, … )` in `js/vergeml-media-views.js` (e.g. `:80`) and `js/eml-media-models.js:24`, `:60`, `:118`. Be ready to explain.
3. **REST routes through admin-ajax** (`core/transport.php:31`). The checks are sound: the `wp_rest` nonce, `upload_files`, `rest_authentication_errors`, own routes only, and each route's permission callback through `rest_do_request()`.
   - Note that `:84` passes the whole `params` object on. Your submission notes say "only each route's declared arguments", which the code does not do (REST still validates the declared ones).
4. **`wp_check_filetype_and_ext`** (`core/mime-types.php:324-415`) re-allows two files that core refused for a content/extension mismatch:
   - a font extension whose bytes read as `application/octet-stream`;
   - an Excel extension whose bytes read as xlsx. This includes `xlsm`, which is macro-enabled.

   It never adds a type, but after two MIME rounds we would ask. Consider dropping the Excel branch.
5. **Writes on logged-out admin requests.** `core/librarian.php:326-352` (installs tables, prunes batches) and `core/ai-index.php:895-919` (schema check, migration step) run on `admin_init` with no capability check, so an anonymous `admin-post.php` hit runs them. `core/organize.php:302-305`, `core/instrument.php:255` and `core/ai-background.php:105` already guard the same way. Same class, two places left.
6. **`wp_cache_flush()`** at `core/health.php:539` empties the whole object cache on a single site for a Health rescan. `delete_post_meta_by_key()` at `:524` has already cleared the affected cache.
7. **WP Rocket exclusion of core's `wp-includes/js/dist/api-fetch`** site-wide (`core/neighbours.php:172`). Your transport is its own handle now (`core/transport.php:159-166`), so this line no longer has a reason.

## Readme and accuracy (fix before upload; not a reason to pend)

- **External services omits the brief.** It omits the site profile ("brief"), which goes with every describe (`'profile' => $settings['site_profile']`, `core/ai.php:570`). It also omits the "How it describes" conversation: the catalogue summary goes to the service when its token is minted (`core/brief.php:342` → `vergeml_guide_mint`), and the browser streams from `/v1/brief/stream` (`js/vergeml-talk.js:398`).
  - `readme.txt:57` lists the describe payload without it.
  - `readme.txt:63` says only "The Folders screen talks to the service from your browser".
  - `grep -in "brief\|profile" readme.txt` → 0 hits.
- `readme.txt:175`: see issue 2.
- **Untranslated user-facing strings:**
  - `core/transport.php:68` `'The bridge needs a JSON body.'`
  - `core/transport.php:80` `'Not one of ours.'`
  - `core/filing.php:3136` `'AI not loaded.'`
  - `js/vergeml-gallery.js:123-125`: the front-end lightbox `aria-label`s ("Previous image", "Next image", "Close").
- **Dead code:** `js/vergeml-taxonomies-options.js:505` posts to a `vergeml-synchronize-terms` AJAX action that no PHP registers.
- **Leftover marker:** `css/eml-admin-media.css:550` `TODO: look deeper into this`.

---

## What we checked and accept

| Check | Grep over the archive (annotations ignored) | Hits | Why acceptable |
|---|---|---|---|
| Trialware (G5) | `licence\|license_key\|has_key\|licensed` | every empty-key branch sits directly before a `wp_remote_post` to the service: `ai.php:102→105`, `:157→166`, `:521→594`, `filing.php:1787→1794`, `:3140→3152`, `folder-talk.php:445→458`, `:2341→2346`, `guide.php:876→917`, `plan-tree.php:1033→1042`, `search-meaning.php:126→132`, `:196→200`, `instrument.php:229→233`, `brief.php:332→342` | Local work needs no key: confirm (`guide.php:1277`), growing the tree, the date-and-type scheme and filing fall back locally when there is no key (`filing.php:1787`). Credits are for the service (G6). The offer and buy links appear only on your own AI and Licence screens. |
| Changing active plugins | `deactivate_plugins\|activate_plugin(\|update_option( 'active_plugins'` | 0 | `active_plugins` is only read (`get-help.php:107`, `system-report.php:128`) |
| Core AJAX takeover | `add_action( 'wp_ajax_` with a core action name | 0 | 6 `wp_ajax_` handlers: 5 prefixed. `tb_load_editor` (`page-builders.php:262`) is Themify's action, hooked at 9 only to enqueue, never answering. `save-attachment-compat` is extended through `attachment_fields_to_save` (`taxonomies.php:1258`) with its own nonce and capability checks. |
| AJAX / admin-post nonces and capabilities | `wp_ajax_nopriv_\|admin_post_nopriv_` | 0 | Each of the 6 AJAX and 10 admin-post handlers checks a nonce (`check_ajax_referer` / `check_admin_referer` / `wp_verify_nonce`) and a capability before reading input. The connect callback (`connect.php:144-160`) uses a per-user one-time state with `hash_equals`. |
| REST permissions | `register_rest_route`; `'__return_true'` | 77 routes; 0 | Every permission callback is a named function. Library-wide work, runs, service calls and imports need `manage_options`. Single objects use `edit_post` / `delete_post` / `edit_term` / `assign_terms` (`rest-folders.php:91-170`, `rest-tree.php:228-242` plus per-file `edit_post` at `:653`, `health-delete.php:582-601`, `auto-file.php:690-700`, `private-folders.php:222-228`). Upload-level routes are reads or the caller's own tree state. |
| Escaping | tokenised every `echo` / `print` / `<?=`; every `printf`; every `wp_die` | 23 echo statements with a bare operand; 35 `printf`; 31 `wp_die` | The 23 are ternaries choosing between string literals or escaped calls (e.g. `ai-screen.php:232`, `options-pages.php:1144-1148`). Every `printf` argument is escaped or a literal. Every `wp_die` goes through `esc_html*`. `_e(` / `_ex(`: 0. Helper markup goes through `wp_kses( …, vergeml_kses_allowed() )`. |
| Inline scripts and styles | `wp_add_inline_script\|<script\|onclick=\|onload=` | 0 / 2 / 0 / 0 | The two `<script type="text/html">` are Underscore templates on `print_media_templates` (`media-templates.php:19`, `:93`). `wp_add_inline_style` ×2: `admin-menu.php:88` (issue 2) and `media-list.php:300`, both through `wp_strip_all_tags()` and `sanitize_html_class()`. |
| Admin assets | every `admin_enqueue_scripts` callback (19) | 17 screen-gated, 1 prints nothing, 1 ungated | The 17 gate on `$hook`, `vergeml_shell_current()` or `vergeml_is_own_admin_screen()`, or load only where `media-views` is queued (`tree-ui.php:29-93`; Breakdance only with its frame flag, `page-builders.php:234`). `vergeml_rtl_styles` only marks registered handles. The ungated one is `vergeml_hide_submenu` (issue 2). |
| SQL | 419 `$wpdb` calls; variables in the first argument | 0 SQL in variables; own tables all `%i` | Core tables as `{$wpdb->posts}` etc. (accepted before). IN lists as inline `implode( array_fill() )`. LIKE patterns bound with `esc_like`. `CREATE TABLE {$table}` only to `dbDelta()`. Only exception: the dead fragments in issue 4. |
| Direct writes to other posts | `$wpdb->(update\|insert\|delete)( $wpdb->(posts\|postmeta…)` | 2 + 1 | `taxonomies.php:1568`, `:1627` write `term_taxonomy.count` inside the plugin's own count callbacks, firing core's hooks around them (accepted before). `options-pages.php:1547` is Complete Cleanup, which is opt-in. Health keep/delete now use `wp_update_post` / `update_metadata_by_mid` / `update_post_meta` after `edit_post`. |
| Insecure MIME | `upload_mimes` | 1 filter | Remove-only (`mime-types.php:287-306`), and refused types are always off. See question 4 for `wp_check_filetype_and_ext`. |
| Outgoing requests | `wp_remote_\|curl_\|fsockopen\|file_get_contents( 'http\|Requests::` | 22 `wp_remote_*`; 0 others | 1 is the site's own `wp-cron.php` (`ai-background.php:220`). 2 go to vergelabsmedia.com (`connect.php:161`, `offer.php:159`). The rest go to ai.vergelabs.nl. Every one follows a button, a run the user started, a connected key's balance check, or the opt-in counts (`instrument.php:255`). None on activation. All disclosed except the brief (readme note above). |
| Offloading | `https?://` in `wp_enqueue_*` / CSS `url(` / `@import` | 0 | Fonts and images are bundled. |
| Dangerous functions | `eval\|exec\|shell_exec\|system\|passthru\|proc_open\|popen\|assert\|create_function\|unserialize(` | 0 | `maybe_unserialize` only on DB values (`health-delete.php:208`, `health.php:932`). `base64_*` only for the plugin's own data (licence seal, packed vectors, image payload, menu SVG). |
| `set_time_limit` / `ini_set` | `set_time_limit\|ini_set\|error_reporting` | 4 / 0 / 0 | Each sits inside a cron or run function behind `vergeml_can_set_time_limit()`, with a bounded value. |
| Direct file access | `defined( 'ABSPATH' )` in the first 40 lines | 67 of 68 | `index.php` is "Silence is golden". `uninstall.php` checks `WP_UNINSTALL_PLUGIN`. |
| Prefixing | unprefixed `function` / `class` / `define` / `const` / hooks fired | 0 / 0 / 0 / 0 | The only unprefixed method names are Walker overrides. Hooks fired with core names (`edited_term_taxonomy`, `the_category`, `rest_authentication_errors`, `delete_term_relationships`) and Dokan's filter are read or fired on purpose. `media_category` is the shared taxonomy, accepted before. |
| Users and roles | `add_cap\|add_role\|wp_set_current_user\|map_meta_cap` | 0 | |
| Hiding notices | `remove_action( 'admin_notices'` | 1 | `admin-shell.php:480-529` removes only callbacks defined in this plugin's files, on its own screens. |
| Uninstall | `uninstall.php` | n/a | Housekeeping by default: transients and every cron hook. The full wipe is opt-in and keeps `media_category`. Every value is placeheld. |
| Front end | `wp_enqueue_scripts\|add_shortcode\|register_block_type` | 2 / 1 / 1 | Gallery assets are enqueued only from the block's render callback. The Divi loader runs only in the builder, for `upload_files` users. No credit links. |
| Telemetry | `instrument.php` | 1 sender | Off by default, needs a key, disclosed (`readme.txt:71`). |
| Debug leftovers | `error_log\|var_dump\|print_r\|console.log` | 0 | |
| Minified code | lines over 400 characters in `js/` and `css/` | 0 | |

---

## What only a running site can show (RUNTIME)

1. **Activation, single site and Network Activate:** a fresh Playground site (plugin mounted, never loaded), `WP_DEBUG_LOG` on, an mu-plugin logging `pre_http_request`. `activate_plugin()` should give no output, 0 outgoing requests, and four `wp_vergeml_*` tables (`SHOW TABLES LIKE 'wp\_vergeml\_%'`). On a network, the same check per subsite.
2. **Issue 5:** the same site with `define( 'VERGEML_SAFE_MODE', true )` on PHP 8.x. File one image into a folder (before setting the constant), then delete it from Media. We expect "call_user_func(): Argument #1 must be a valid callback". The same with mbstring disabled.
3. **Issue 2:** Dashboard page source as administrator: we expect `<style id='vergeml-hide-submenu-inline-css'>`. Add "no `vergeml` string in the page source" to the walk, not only "no plugin file".
4. **Issue 3:** with Co-Authors Plus active, dump `get_taxonomy( 'author' )->update_count_callback` on `init` (theirs) and after `wp_loaded` (unset). The same for a test taxonomy on `post` with a custom counter.
5. **Outgoing requests without a key:** open every screen as administrator, editor and author with the request logger. We expect 0 requests from the plugin. With a key, one `/licence` check per five minutes on the plugin's screens.
6. **Media modal:** open the block editor's media modal. Check which plugin rules apply (`body.eml-media-css`) and that core's selects and toolbar render as intended.

---

## Method (internal)

- Archive: `C:\Users\viete\AppData\Local\Temp\vgml-r6-archive` (147 files). Nothing outside it was judged.
- No PHP runtime on this machine, so every verdict is static. The RUNTIME list says what a run must show.
- Scanners in `C:\Users\viete\AppData\Local\Temp\vgml-r6-team-scratch\`:
  - `echo.js`: every echo/print/`<?=` operand, with escaping calls removed.
  - `printf.js`: every `printf` / `wp_die` / `register_rest_route` with its arguments.
  - `sql.js`: every `$wpdb` call's SQL argument (interpolation, concatenation, SQL in a variable).
  - `css.js`: selectors with no plugin token.
