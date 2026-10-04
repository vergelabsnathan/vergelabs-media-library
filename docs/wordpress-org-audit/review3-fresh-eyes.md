# Review 3: VergeLabs Media Library 4.1.0 (vergelabs-media-library)

Hello,

Thank you for the update. We reviewed the zip as uploaded, with every `phpcs:ignore` / `phpcs:disable` annotation ignored (279 of them). The previous fixes hold: nothing deactivates a plugin any more, the library-wide REST writes are behind `manage_options`, and `upload_mimes` only toggles types WordPress already knows (with a deny-list). Some issues remain. As before, we list examples, not every instance, so please check each **class** of issue across the whole plugin.

Ranking: **WOULD-REJECT** means we will send it back again. **LIKELY-QUESTION** means a reviewer may ask you about it, and you should be ready to change it or explain it.

---

## 1. WOULD-REJECT: Taking over core AJAX actions (same class as `delete-post` in round 1)

**Rules:** G0.5, HB-64 ("Using WordPress functionality and features first"), HB-66, BOT-hijack-admin (G11 spirit), round-1 finding "hijacking core's delete-post AJAX action".

You removed the `delete-post` takeover. Two siblings of that pattern are still there. Each one hooks a **core** `wp_ajax_` action at priority 0 and ends with `wp_send_json_*()`, so core's own handler never runs:

- `core/taxonomies.php:1254`: `add_action( 'wp_ajax_save-attachment-compat', 'vergeml_save_attachment_compat', 0 );`. This replaces core's `wp_ajax_save_attachment_compat()`. It also changes what core does: all fields go through `wp_kses_post` (line 1289), even for users who have `unfiltered_html`.
- `core/taxonomies.php:1421`: `add_action( 'wp_ajax_save-attachment-order', 'vergeml_save_attachment_order', 0 );`. This replaces core's ordering handler and adds its own nonce action (`vergeml-bulk-edit-nonce`, line 1455) and its own raw SQL ordering (line 1480 onwards).

**Why it fails:** Every other plugin that relies on core's handler (or on filters inside it) silently gets your copy instead. Your copy also falls out of date whenever core changes. We asked for this class to be removed, not one instance of it.

**Fix:** Do not answer core's action names. Register your own actions (for example `wp_ajax_vergeml_save_attachment_compat`) and point your media JS at them, the way you already did with `vergeml-term-counts`. Another option is to let core's handler run and add your term saving through `attachment_fields_to_save` / `edit_attachment`.

## 2. WOULD-REJECT: Interfering with other plugins and themes

**Rules:** CI-activation-interfere (spirit: "not allowed to interfere"), G9.1 ("abusing the freedoms … of other plugin developers"), HB-75/HB-76.

- `core/compatibility.php:88-126`: when Enhanced Media Library is active, this plugin **dequeues and deregisters** nine of its scripts and two of its styles (`wpuxss-eml-*`). It also removes EML's `print_media_templates` callback at line 95. The readme changelog confirms it: "its media scripts and grid template are now set aside".
- `core/compatibility.php:47-50`: `remove_filter( 'attachment_fields_to_edit', 'us_attachment_fields_to_edit_categories' )` removes a hook that belongs to the Impreza theme.
- `core/compatibility.php:63-66`: `remove_filter( 'ajax_query_attachments_args', 'pgc_sgb_ajaxQueryAttachmentsArgs', 20 )` removes a hook that belongs to the SimpLy Gallery plugin.

**Why it fails:** A plugin may not switch off another active plugin's or theme's code. If two plugins conflict, the user decides what to do. Your plugin does not get to disable the other one's assets or hooks. In this case the plugin you disable is a direct competitor.

**Fix:** Remove all three. Keep the admin notice you already have (`core/neighbours.php:76`), which tells the user that both plugins are active and asks them to deactivate one. If your own scripts break while EML's run, make yours tolerate it, for example with unique template IDs instead of `tmpl-attachment-grid-view`.

---

## 3. LIKELY-QUESTION: Overriding WordPress globals and replacing core screens and scripts

**Rules:** HB-64, HB-66, BOT-hijack-admin. WPCS also has the `WordPress.WP.GlobalVariablesOverride` sniff.

- `core/options-pages.php:284-289`: writes to the core globals `$pagenow`, `$title` and `$hook_suffix`. Lines 300-304 then fire core's `load-options-media.php`, `admin_enqueue_scripts`, `admin_print_*` and `admin_head-*` with a fake hook suffix.
- `core/options-pages.php:233-269`: rewrites `$submenu['options-general.php']` so that core's **Settings → Media** item points to the plugin's page instead (line 254).
- `vergelabs-media-library.php:847`: `wp_dequeue_script( 'media' )` removes a core script from upload.php. `js/eml-media.js:16` then redefines core's global `window.findPosts`.
- `js/eml-admin.js:13-16`: overwrites `window.adminpage` and `window.pagenow`.
- `js/vergeml-tree.js:4559`: monkey-patches `wp.media.view.Attachment.prototype.toggleSelection` globally, which changes it for every plugin's media modal.

Most of this came from the upstream plugin. Even so, a new submission is reviewed on its own, and existing directory plugins are not a precedent (HB-47). **Fix:** Add your settings as your own page and leave core's Media settings item and core's globals alone. Enqueue your scripts alongside `media` rather than in place of it. Scope any prototype patches to your own frame or subclass.

## 4. LIKELY-QUESTION: Crash watchdog runs globally on every request and can switch the plugin off by itself

**Rules:** BOT-php-settings-globally ("in a crash watchdog check `register_shutdown_function`/`set_error_handler`/`ini_set` on load"), CI-no-global-ini, round-2 "Changing Active Plugins" (applied to self).

- `vergelabs-media-library.php:212` calls `vergeml_watchdog_boot()` at file load. On **every** front-end and admin request this reserves 256 KB of memory (`core/watchdog.php:60`) and registers a global shutdown function (`core/watchdog.php:62`).
- `core/watchdog.php:147-151`: after two fatal errors the plugin puts itself into "safe mode" and stops loading its own features. That is no longer a deactivation, but the plugin still decides on its own to disable itself, and the site-wide notice (line 249) has no dismiss.

**Fix:** Remove the global shutdown function and the memory reserve. Since WordPress 5.2, Recovery Mode does exactly this job. If you keep a safe mode, it should only be entered by the user (for example a constant or a setting), not by the plugin itself.

## 5. LIKELY-QUESTION: Bypassing the WordPress HTTP API

**Rules:** CI-http-api ("WordPress comes with an extensive HTTP API that should be used instead"), HB-64, BOT-curl.

`core/ai.php:821-900` calls `\WpOrg\Requests\Requests::request_multiple()` directly. The comment at line 825 says so itself: "It does bypass the WP_Http layer … pre_http_request filter and WP_PROXY_HOST do not apply". As a result, `http_request_args`, `pre_http_request`, `http_api_debug`, block-external-host settings (`WP_HTTP_BLOCK_EXTERNAL`/`WP_ACCESSIBLE_HOSTS`) and Query Monitor do not see these requests.

**Fix:** Use `wp_remote_post()`. If you need parallel requests, accept the slower sequential path, which you already have at lines 859-866.

## 6. LIKELY-QUESTION: SQL assembled outside `prepare()`, including raw SQL from a filter

**Rules:** BOT-unsafe-sql, CI-wpdb-prepare, PCS-WordPress.DB.PreparedSQL.NotPrepared (an error once annotations are ignored), HB-82.

With annotations ignored, 23 `NotPrepared` and 103 `InterpolatedNotPrepared` findings remain. Most of them interpolate only table names, but these build the query from variables:

- `core/smart-folders.php:353-371`: `apply_filters( 'vergeml_smart_count_branches', … )` returns raw SQL strings that are concatenated into `$wpdb->prepare( $core_sql . $extra_sql, … )`. Any plugin can inject SQL into this statement.
- `core/guide.php:2792`: `"SELECT {$select} FROM {$t} i{$join} WHERE {$where}{$group} … {$limit_sql}"`.
- `core/search-try.php:80-89`: the query is built by `implode()`-ing separately prepared fragments that come from the search terms.
- `core/seo-context.php:345`, `core/ai.php:1783`, `core/ai-screen.php:136`: the full statement is built by a helper function and passed to `get_var` without `prepare()`.

**Fix:** Call `prepare()` once on the full statement, with placeholders (`%i` for identifiers on WP 6.2+). Remove the raw-SQL filter, or have it accept only structured data.

## 7. LIKELY-QUESTION: Direct writes to other posts' content

**Rules:** HB-64, HB-66 ("editing post content in DB instead of … filters"), REST-12 (object-level permission).

`core/health-keep.php:404` and `:741` run `$wpdb->update( $wpdb->posts, array( 'post_content' => … ) )` on the posts that reference a merged duplicate. This skips `wp_update_post()`, revisions, `save_post`, cache invalidation, and any `edit_post` check on **those** posts. The permission callback (`core/health-delete.php:473`) only checks the kept attachment and the dropped attachments.

**Fix:** Use `wp_update_post()` and check `current_user_can( 'edit_post', $source_id )` for each post you rewrite.

## 8. LIKELY-QUESTION: Unprefixed taxonomy, and an uninstall that can delete another plugin's data

**Rules:** HB-61, BOT-generic-names / BOT-custom-post-types-prefix, uninstall must remove only the plugin's own data.

- `vergelabs-media-library.php:1148` registers `media_category` with no prefix. You say this is deliberate, for migration from EML and FileBird. We may accept that with an explanation, but expect the question.
- `uninstall.php:59-92`: the opt-in wipe deletes **every** term of any taxonomy flagged `eml_media`, and that includes `media_category`. The same terms belong to Enhanced Media Library if the user still has it, or switches back. **Fix:** if you keep the shared name, make the wipe leave `media_category` terms in place (unlink only your own meta).

## 9. LIKELY-QUESTION: Script injected into core's `wp-api-fetch` on every admin screen

**Rules:** CI-enqueue-js, HB-89, G11.

`core/transport.php:154-196` runs on `admin_enqueue_scripts` with **no screen check**. It reads `js/vergeml-transport.js` with `file_get_contents()` and inlines it into **core's** `wp-api-fetch` handle (line 183). It also localizes a global onto that core handle (line 185). The result is that core's apiFetch gets a middleware on every admin page that loads it, including the block editor. `core/instrument.php:400` and `core/licence-page.php:309` also attach their inline JS to `wp-api-fetch`. **Fix:** Enqueue `js/vergeml-transport.js` as your own script, with `wp-api-fetch` as a dependency, and only on your own screens.

## 10. LIKELY-QUESTION: Readme

**Rules:** RM-32 ("larger than 10k may result in errors … brief and to the point"), RM-33 ("not a sales pitch"), round-1 "comparisons in readme", HB-54 / G7.3 (disclosure must be accurate).

- The readme is 33 KB. The Description alone is about 2,450 words and External services about 1,240.
- `readme.txt:158-162`, "What does this fork fix in the original features?", is a list of defects in a competing plugin that is still listed in the directory. We read that as a comparison. Describe what your plugin does, not what the other one gets wrong.
- `readme.txt:120` says credits are checked "at most once every five minutes". But `core/licence-page.php:104-106` calls `vergeml_ai_refresh_credits( true )`, which contacts the service **every time** the Licence screen is opened. Make the code match the text, or change the text.

## 11. LIKELY-QUESTION: Test and development code in the release

**Rules:** BOT-unneeded-folders (spirit), PCS-PluginCheck.CodeAnalysis.Localhost.

- `core/guide.php:861` and `core/offer.php:26` contain `localhost` / `127.` host checks. `guide.php` reroutes a loopback service URL to the production host ("the test box does").
- Hooks that exist only for your test suites: `vergeml_ai_describe_answers` (`core/ai.php:846`, "A seam for the suites") and `vergeml_ai_run_should_nudge` (`core/ai-background.php:175`). The first one lets any plugin replace the AI answers.

**Fix:** Remove these from the shipped build.

---

## Checked and acceptable (we would not send these back)

- **MIME types** (`core/mime-types.php:16-131, 280-417`): only `wp_get_mime_types()` entries are allowed, and there is a deny-list for executables, html, svg and js. The font/octet-stream path is now limited to font extensions. Fixed.
- **No self-deactivation**: there is no `deactivate_plugins`/`active_plugins` write. The watchdog's Deactivate button is a normal, nonced `plugins.php` link the user presses.
- **REST permissions**: 85 callbacks, all present, none `__return_true`. Library-wide writes use `manage_options`. Per-object routes use `edit_post`/`delete_post`/`edit_term`. `/assign` checks `edit_post` per attachment (`core/rest-tree.php:653`).
- **REST args without `sanitize_callback`** (58 of them): each value is sanitized or allow-listed in its handler. Acceptable.
- **AJAX / admin_post / admin_init handlers**: all carry a nonce plus a capability check (`transport`, `trial`, `zip`, `licence`, `settings import/export/cleanup`, `watchdog resume`, `neighbour dismiss`).
- **Opt-in telemetry** (`core/instrument.php`): off by default, saved only by the user's toggle, disclosed in the readme. Acceptable under G7.
- **Outgoing requests**: every one follows a user action or a saved licence key, and all hosts (ai.vergelabs.nl, vergelabsmedia.com) are disclosed with links to terms and privacy. The loopback to the site's own `wp-cron.php` (`core/ai-background.php:229`) is fine.
- **Trialware**: AI features are refused without a key, but the processing really happens on the service. Folders, smart folders, importer, galleries and the date/type Librarian work without a key. The 25-credit trial is a real service trial. The free key's email is disclosed and entered by the user.
- **Admin notices**: all are scoped to the upload screen or the plugin's own screens, dismissible, or self-resolving. The shell only removes the plugin's **own** notice callbacks (`core/admin-shell.php:509-530`).
- **Escaping, i18n, enqueueing, files**: three echoes are suppressed (an icon path literal and CSV download bytes), and they are fine. The text domain is consistent. There is no `load_plugin_textdomain`, and `wp_set_script_translations` is used. There are no `<script>`/`<style>` tags except underscore `text/html` templates. There are no minified bundles, no CDN or remote assets, no bundled core libraries, and no writes to the plugin folder. Every PHP file has an ABSPATH guard. Fonts are Inter (OFL) and webbistro (GPL), with credits in the readme.
- **Headers**: `Stable tag` 4.1.0 matches `Version`. Tested up to 7.1. 5 tags. Short description is under 150 characters. Licence matches. Main file name matches the slug.
