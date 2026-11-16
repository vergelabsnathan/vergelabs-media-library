# Review 6: VergeLabs Media Library 4.1.0 (vergelabs-media-library)

Hello,

Thank you for the update. We reviewed the zip as uploaded: 147 files (68 PHP, 43 JS, 22 CSS). We ignored every `phpcs:ignore` / `phpcs:disable` annotation and read the code under each one.

Both classes from our last email are fixed where we pointed at them. The activation installer now runs every schema installer, and no script is built from a string any more. Across the whole plugin, every PHP output path escapes late. One part of the activation checklist is still open, the CSS scoping. The selectors added this round carry a class, but that class is not a scope. We found nothing we would send back on its own. As before, we list examples, not every instance, so please check each **class** across the whole plugin.

Ranking: **WOULD-REJECT** means we will send it back again. **LIKELY-QUESTION** means a reviewer may ask you about it, and you should be ready to change it or explain it.

---

## Round 5's classes

### 1. The plugin has problems when it is activated: gone as reported, with one item of the checklist still open

**Rules:** FAQ-15, HB-31, HB-58, DEV-201 (WP_DEBUG clean); HB-97 / HB-98 (requirements and dependencies checked); CI-enqueue-admin, HB-75 (assets and styles scoped); G7.1, HB-77 (no calls on activation).

**The reported instance is fixed.** `vergeml_provision_site()` now calls each installer by name, and loads its file first if this request has not loaded it yet (`vergelabs-media-library.php:374-387`). It stamps the version only after that (`:389`). We traced every way in:

- **plugins.php / WP-CLI.** `activate_plugin()` includes the main file before it fires the hook. The main file includes every feature file at file scope (`:1582-1733`), so `vergeml_index_install`, `vergeml_organize_install` and `vergeml_librarian_install` all exist when `vergeml_on_activation()` runs (`:308`, `:322`). The include happens inside a function scope. No file relies on an implicit global from that: the only file-scope variables are used in place (`core/compatibility.php:58`, `core/smart-folders.php:571`) or written through `$GLOBALS` (`core/organize.php:2363`).
- **Network Activate.** `$network_wide` is honoured. With 100 sites or fewer, each one is provisioned inside `switch_to_blog()` (`:324-338`). With more, the rest provision on their first admin, cron or CLI request (`:540-553`), or get options plus a booked event on a front-end request (`:554-567`). A site created later is provisioned on `wp_initialize_site` (`:403-418`), and a deleted site's tables are dropped through `wpmu_drop_tables` (`:421-434`).
- **Prefix after switching blogs.** The three tables are registered on `$wpdb->tables` (`core/ai-index.php:84-88`, `core/librarian.php:90-97`, `core/organize.php:190-195`), so `switch_to_blog()` re-prefixes them. The installers read the name from that property.
- **Schema.** All four `CREATE TABLE` statements use `$wpdb->prefix` and `$wpdb->get_charset_collate()`, and go only to `dbDelta()`. They follow dbDelta's format: one field per line, `PRIMARY KEY  (` with two spaces, `KEY` rather than `INDEX`, no `DEFAULT` on TEXT/BLOB, and no reserved words as columns (`core/ai-index.php:132-162`, `core/librarian.php:176-195`, `:241-263`, `core/organize.php:259-276`).
- **No output.** No PHP file has a BOM, text before `<?php`, anything after a closing `?>`, or output at file scope (all 68 files scanned). The activation path echoes nothing. dbDelta's `DESCRIBE` probe has its errors suppressed.
- **No remote calls on activation.** None of the 22 `wp_remote_*` calls is reachable from activation or from the first admin load after it. The one `admin_init` sender, `vergeml_stats_maybe_refresh`, is opt-in and off by default (`core/instrument.php:251-258`, `:55-58`).
- **Requirements.** No PHP 8-only syntax for a 7.4 floor. `str_contains` is polyfilled by core since 5.9. ZipArchive (`core/folder-tools.php:136`) and GD (`core/health.php:287`, `:369-381`) are guarded. Other plugins' tables are only read behind `vergeml_table_exists()` or `defined( 'AIOSEO_VERSION' )` (`core/import-sources.php:216`, `:281`; `core/seo-context.php:76`, `:279`). See LIKELY-QUESTION 2 for mbstring and OpenSSL.
- **Admin assets.** All 19 `admin_enqueue_scripts` callbacks check the screen. `vergeml_admin_enqueue_scripts` now stops outside the media library, attachment, media-taxonomy and own screens (`vergelabs-media-library.php:789-819`). The others match their own hook suffix or `vergeml_shell_current()`. `vergeml_tree_assets` loads only on `upload.php`, on post lists with folders switched on, or where `media-views` is already queued (`core/tree-ui.php:31-93`). The exception is `vergeml_hide_submenu` (`core/admin-menu.php:80`, `:84-89`), covered in LIKELY-QUESTION 1.

**Still open: CSS reaching core's media modal on every screen that opens one.** See LIKELY-QUESTION 1. The `.eml-media-css` prefix added this round is not a scope.

**One more instance, which a reviewer will not hit on a clean site.** Safe mode loads only `core/system-report.php` and `core/get-help.php` (`vergelabs-media-library.php:1579-1582`). But callbacks in the main file that are hooked whatever the mode still call into `core/taxonomies.php`:

- `vergeml_enqueue_media` calls `vergeml_get_media_term_pairs()` at `:972` (defined at `core/taxonomies.php:1515`) and `new vergeml_Walker_Media_Taxonomy_Uploader_Filter()` at `:1000` (defined at `core/taxonomies.php:1200`). In safe mode, the Media Library grid and every screen that opens a media frame end in "Call to undefined function".
- `vergeml_on_init` calls `vergeml_sanitize_slug()` at `:632` when taxonomy archives are on.
- The term-count callbacks named at `:656` and `:764` (`core/taxonomies.php:1558`, `:1590`) fatal on the first term assignment.

The mode meant to keep a site up takes its media screens down. **Fix:** return early from `vergeml_enqueue_media` and `vergeml_on_init`'s archive branch when `vergeml_safe_mode()` is true. Register the count callbacks only when `function_exists()` says they exist, or move these four functions and the walker into a file safe mode keeps.

### 2. Variables and options must be escaped when echo'd: gone

**Rules:** BOT-escape, BOT-escape-__, BOT-escape-_e, BOT-escape-json, BOT-wp-enqueue, CI-enqueue-js, CI-enqueue-css, HB-89.

- **The example is fixed.** `vergeml_inline_script( $handle, $js )` is gone. In its place, `vergeml_screen_script( $handle, $file, $deps )` (`vergelabs-media-library.php:114-116`) enqueues a file from `js/`. All four callers pass a literal filename: `core/ai-screen.php:326`, `core/get-help.php:380`, `core/instrument.php:392`, `core/licence-page.php:307`. The two scripts that were inline strings in round 5 are now files with `wp_localize_script` data (`js/vergeml-stats-opt.js`, `js/vergeml-demo-mode.js`).
- **`wp_add_inline_script`: 0 calls** in the plugin.
- **`wp_add_inline_style`: 2 calls, both safe.** `core/admin-menu.php:88` is a constant selector with `sanitize_html_class( VERGEML_MENU )`. `core/media-list.php:300` builds selectors from `sanitize_html_class()` column names (`:224`, `:258`) and literal declarations. Both are wrapped in `wp_strip_all_tags()`.
- **`wp_localize_script`: 27 calls.** Data only, no script text. Values the JS renders as HTML are escaped before they leave PHP: user names (`vergelabs-media-library.php:984`), taxonomy labels (`:1016-1017`), term names (`core/taxonomies.php:1227-1228`).
- **echo / print / printf / `<?=` / `wp_die` / `exit`:** we tokenised every PHP file and classified each operand of 276 output statements. 273 contain only literals, `esc_*()`, `wp_kses*()`, casts or `number_format_i18n()`. The other three are ternaries whose branches are both escaped (`core/admin-shell.php:740`, `core/journey.php:1318`, `core/options-pages.php:1147`). We found no `_e()`, `_ex()`, raw `__()`, bare variable, constant or helper call echoed unescaped. Helper-built markup is echoed through `wp_kses( …, vergeml_kses_allowed() )` (`vergelabs-media-library.php:159-207`), whose allow-list has no `script`, `iframe` or `on*` attributes.
- **Returned HTML that core prints:**
  - row actions: `core/quick-edit.php:51-57`, `core/rename.php:327-337`
  - attachment fields: `core/taxonomies.php:1008-1017`, `core/librarian.php:3132-3168`, `core/smart-folders.php:1092-1147`
  - walkers: `core/taxonomies.php:1067-1082`, `:1174-1179`, `core/media-list.php:396-403`
  - the gallery block: `core/gallery-block.php:241-333`

  All of these escape each part. Media templates use `{{ }}` only (`core/media-templates.php`).
- **JS sinks:** we checked 77 `innerHTML` / jQuery `.html()` / `.append()` sinks across `js/`. None receives database, REST, AI or URL data unescaped. The plugin builds DOM nodes, and its text helpers use `textContent` (for example `js/vergeml-tree.js:82`, `js/vergeml-tree-view.js:38`, `js/vergeml-talk.js:30`).

---

## WOULD-REJECT

None this round.

---

## 1. LIKELY-QUESTION (new): Media-modal CSS reaches core UI on every screen that opens a media frame

**Rules:** the activation checklist ("CSS selectors scoped so they cannot reach WordPress's own interface, except on screens the plugin is for"), HB-75, CI-enqueue-admin, BOT-hijack-admin (G11).

- **Where it loads.** `css/eml-admin-media.css` is enqueued from `wp_enqueue_media`, with only an `is_admin()` check (`vergelabs-media-library.php:936-956`). It is also enqueued in the Elementor editor (`core/compatibility.php:18-32`). So it loads in the block editor, the Customizer, the widgets screen, and any other plugin's page that opens a media picker.
- **What the "scope" is.** This round, 15 selectors gained the prefix `.eml-media-css`:
  - lines `:268`, `:271-272`, `:347`, `:355`, `:451-452`, `:461`, `:465`, `:487`, `:529`, `:538`, `:568`, `:576`
  - the same set in the RTL file

  That class is put on `<body>` by the plugin's own `js/vergeml-media-views.js:943` on every screen where that script loads, which is every screen above. So these rules still apply to core's modal on all of those screens. Examples:
  - every `<select>` in every media modal (`.eml-media-css .media-modal.wp-core-ui select`, `:529-541`)
  - the attachments wrapper's position (`:271-279`)
  - the toolbar (`:347-358`)
  - sidebar headings (`:451-459`)
  - spinners (`:565-579`)

  The `body.eml-media-css` rules at `:4-79` and `:435-682` behave the same way. `:52-79` restyle another plugin's (ACF's) fields.
- **Other global styles.**
  - `core/compatibility.php:25` enqueues core's admin `common` stylesheet into Elementor's editor, which is another plugin's screen.
  - `vergeml_hide_submenu` adds a style on every admin screen (`core/admin-menu.php:80`, `:84-89`). Its one rule targets only the plugin's own menu item. That is defensible, but a reviewer checking the "only on the screens that need them" point will see an unconditional `admin_enqueue_scripts` callback.

**Fix (pick one):**
- **(a) Real scope.** Have the plugin's own frame view add a class to its `.media-modal` element only when the plugin's filters render in it, and prefix the rules with that class instead of the body class. Drop the `common` enqueue and the ACF rules.
- **(b) Explanation.** Keep the rules and say that the media modal is the surface the plugin extends, so these are "screens the plugin is for".

Either way, do not present `.eml-media-css` as a scope: a reviewer who greps for it will find `addClass` on `body`. On the menu rule, explain it in one sentence (the submenu is drawn inside the plugin's pages, and `remove_submenu_page()` makes the pages answer 403).

## 2. LIKELY-QUESTION (new): mbstring and OpenSSL are used without a check

**Rules:** the activation checklist ("anything it depends on (PHP extension) is checked before use with an explanation instead of failing"), HB-97, DEV-201.

- **mbstring.** WordPress polyfills only `mb_substr` and `mb_strlen`. `mb_strtolower`, `mb_strpos`, `mb_strtoupper` and `mb_stripos` are called unguarded on 54 lines in 6 files. Examples: `core/filing.php:199`, `:230`, `:276`; `core/folder-talk.php:551`; `core/guide.php:215`; `core/plan-tree.php:1148`; `core/search-try.php:113`, `:184`; `core/brief.php:144`. The plugin guards the same calls elsewhere (`core/folder-talk.php:106`, `:2847`; `core/librarian.php:1152`; `core/organize.php:1263`, `:1465`), which shows it knows the extension can be absent. On a host without mbstring, the Folders screen, the planner, Try a query and the brief end in a fatal error.
- **OpenSSL.** `openssl_encrypt()` / `openssl_decrypt()` seal the licence key (`core/ai.php:339`, `:361`). Without OpenSSL, saving a key, and every later read of a stored key, is a fatal error.

**Fix (cheap):** one helper, `vergeml_lower( $s )` (and `_strpos` / `_stripos` / `_upper`), that falls back to `strtolower()` / `strpos()` / `stripos()` / `strtoupper()`, used on all 54 lines. Also check `function_exists( 'openssl_encrypt' )` in `vergeml_ai_seal()` / `vergeml_ai_unseal()`, with an admin notice on the licence screen that says why a key cannot be stored. Alternatively, test once for `extension_loaded( 'mbstring' )` and show a notice instead of loading the features that need it.

## 3. LIKELY-QUESTION: Global replacement of core media views and core taxonomy objects (partly addressed)

**Rules:** HB-64, HB-66, BOT-hijack-admin (G11), CI-enqueue-admin; HB-47.

The "admin CSS/JS on every admin screen" part of round 5's item is fixed (`vergelabs-media-library.php:817`). The rest is unchanged:
- `js/vergeml-media-views.js` (enqueued from `wp_enqueue_media`, `vergelabs-media-library.php:1043`) replaces core prototypes with `_.extend( media.view.X.prototype, … )`, for example `AttachmentCompat` at `:80`. `js/eml-media-models.js:24`, `:60` and `:118` and `js/eml-enhanced-medialist.js:51-348` do the same.
- `js/eml-media.js:24` renames core's grid frame.
- `vergelabs-media-library.php:754-767` rewrites `update_count_callback` in `$wp_taxonomies`.

**Fix:** as before: subclass instead of replacing.

## 4. LIKELY-QUESTION: Crash watchdog on every request (unchanged)

**Rules:** BOT-php-settings-globally, CI-no-global-ini, G11.3 / G11.4.

`vergelabs-media-library.php:214-215` boots it on every request. It holds a 256 KB string (`core/watchdog.php:60`), registers a global shutdown function (`:62`), and enters safe mode by itself after two fatals in an hour (`:147-149`). The safe-mode fatals described under class 1 above make this item weaker still. **Fix:** as before: leave this to Recovery Mode, or make safe mode something the user switches on (`VERGEML_SAFE_MODE`, `:200`).

## 5. LIKELY-QUESTION: Direct `$wpdb` writes to other posts' meta and parents (unchanged)

**Rules:** HB-64, HB-66, REST-12.

`core/health-delete.php:397` and `core/health-keep.php:441` / `:757` rewrite postmeta rows of other posts by `meta_id`. `core/health-delete.php:432` and `core/health-keep.php:449` / `:454` / `:769` set `post_parent` with `$wpdb->update( $wpdb->posts, … )`. The `edit_post` gate is sound. **Fix:** as before: use `update_metadata_by_mid()` and `wp_update_post()`, or explain why the raw writes are needed.

## 6. LIKELY-QUESTION: `media_category` is unprefixed, and both wipes delete its terms (unchanged)

**Rules:** HB-61, BOT-custom-post-types-prefix.

The default taxonomy is `media_category` (`vergelabs-media-library.php:1193`, registered at `:647`). `uninstall.php:59-98` (opt-in) and `core/options-pages.php:1537` (Complete Cleanup) delete the terms of every `eml_media` taxonomy, including the one Enhanced Media Library left. **Fix:** as before: keep the terms, or name them in the wipe's confirmation.

## 7. LIKELY-QUESTION: Readme length (unchanged)

**Rules:** RM-32, RM-35/RM-36, G12.5.

`readme.txt` is still 27,161 bytes. `:17` says "PHP 7.4 through 8.3", and the compatibility log lists 8.5.

## 8. LIKELY-QUESTION: Test seams and a loopback exception (unchanged)

**Rules:** BOT-unneeded-folders (spirit), FAQ-4, PCS-PluginCheck.CodeAnalysis.Localhost.

These are in the same places as in round 5:
- `core/journey.php:242-250` (`vergeml_journey_facts`)
- `core/ai-background.php:363-364`
- `core/guide.php:987-988`
- `core/brief.php:533`
- `core/ai.php:316` (`http://127.0.0.1`)

## 9. LIKELY-QUESTION: REST routes reachable through admin-ajax (unchanged)

**Rules:** HB-64.

`core/transport.php:31`. The checks are in place, and we would likely accept it with an explanation.

---

## Checked and acceptable (we would not send these back)

- **Round 5's question on `upload_mimes` is resolved.** The callback is now remove-only: `unset()` and nothing else (`core/mime-types.php:286-306`). A type another plugin added is left alone.
- **Front end.** The gallery stylesheet and script are registered at `init` and enqueued only from the block's render callback (`core/gallery-block.php:30-51`, `:326-329`). Every selector in `css/vergeml-gallery.css` is under a plugin class. The page-builder loaders fire only on a builder's own hook or with `upload_files` (`core/page-builders.php:30-60`, `:160-170`).
- **CSS on the plugin's own screens and on `upload.php`.**
  - `css/vergeml-tree.css` restyles core's toolbar, list table and uploader only under `body.upload-php` (`:1604-2196`) or body classes the tree adds when it mounts.
  - `css/eml-admin.css:40` is `.upload-php`-only.
  - `css/vergeml-media-list.css` loads on `upload.php` only.
  - The shell's sheet loads only on the plugin's pages.

  The media library is the screen the plugin is for.
- **`script_loader_tag`** adds optimiser opt-out attributes to the plugin's own handles only (`core/neighbours.php:188-197`). The WP Rocket filters exclude only the plugin's `js/` path and api-fetch.
- **Downloads.**
  - The ZIP streams a file the plugin just built, gated by `upload_files` and a nonce (`core/folder-tools.php:227-261`).
  - The CSV is written to `php://output` instead of echoed, as `text/csv` with `nosniff` and `attachment`. Cells are neutralised against spreadsheet formulas and quoted per RFC 4180 (`core/import-csv.php:151-174`, `:183-230`).
  - The settings export is `wp_json_encode` of the plugin's own options as an attachment (`core/options-pages.php:1239-1244`).

  If asked why the CSV is not echoed, the comment at `core/import-csv.php:212-221` is the answer.
- **Raw tags in PHP:** only the two Underscore templates as `<script type="text/html">` on `print_media_templates` (`core/media-templates.php:19`, `:93`), which is how core ships its own.
- **Housekeeping on `admin_init`.** `vergeml_librarian_housekeeping` and `vergeml_index_maybe_migrate` (`core/librarian.php:326-354`, `core/ai-index.php:895-919`) have no capability check, so a logged-out `admin-post.php` request runs their schema check and pruning. Nothing is exposed. `core/organize.php:302-305` already shows the guard to copy if you want parity.
- **Unchanged since round 5 and still acceptable:** SQL (all `%i` / prepared), REST permissions, no dangerous functions, `set_time_limit` scoped to the plugin's own runs, no interference with other plugins' hooks, outgoing hosts as disclosed, trialware, notices, prefixing, licences and headers.

---

*Method (internal):*
- The archive is `C:\Users\viete\AppData\Local\Temp\vgml-r5\vergelabs-media-library`, 147 files.
- Against the round-5 archive (`…\vgml-r4\…`), shipped changes are in:
  - the main file and `core/admin-menu.php`, `ai-index.php`, `ai-screen.php`, `get-help.php`, `instrument.php`, `librarian.php`, `licence-page.php`, `media-list.php`, `mime-types.php`, `organize.php`
  - `css/eml-admin(-media)(-rtl).css` and `js/eml-admin.js`
  - four new JS files
- Scanners are in `C:\Users\viete\AppData\Local\Temp\vgml-r6-scratch\`:
  - `toplevel.js`: file-scope statements, BOM, output outside PHP
  - `echo.js`: operand classifier for every output statement
  - `css.js` / `css2.js`: selectors whose target is a core element, grouped by scoping ancestor
  - `fn.js`: function extractor
- The JS sink audit was a separate read-only pass over all 43 files.
- No PHP runtime was available here, so the activation trace is static. Run a WP_DEBUG activation in Playground (single site and Network Activate) before replying, to show the four tables exist.
