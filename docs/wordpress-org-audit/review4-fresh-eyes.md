# Review 4: VergeLabs Media Library 4.1.0 (vergelabs-media-library)

Hello,

Thank you for the update. We reviewed the zip as uploaded (a clean archive: `core/`, `css/`, `fonts/`, `images/`, `js/`, `languages/`, the main file, `uninstall.php`, `readme.txt`, `changelog.txt`, `SECURITY.md`, `wpml-config.xml`). We ignored every `phpcs:ignore` / `phpcs:disable` annotation (265 of them) and read the code under each one.

The two classes we said we would send back for are gone everywhere, not only on the lines we quoted. The plugin no longer answers any core AJAX action, and it no longer switches off another plugin's or theme's scripts, styles or hooks. We found nothing new that we would reject for. What remains are questions, most of them carried over from the previous round, and several of those are now partly fixed. As before, we list examples, not every instance, so please check each **class** across the whole plugin.

Ranking: **WOULD-REJECT** means we will send it back again. **LIKELY-QUESTION** means a reviewer may ask you about it, and you should be ready to change it or explain it.

---

## Previous round's classes

1. **Taking over core AJAX actions: gone.** The only `wp_ajax_` registrations are the plugin's own (`vergeml_trial`, `vergeml-apply-settings-to-network`, `vergeml-term-counts`, `vergeml-save-attachment-order`, `vergeml_rest`), plus `core/page-builders.php:262`, which hooks Themify's `tb_load_editor` at priority 9 only to enqueue scripts and does not answer it. Term saving now goes through `attachment_fields_to_save` while core's handler runs (`core/taxonomies.php:1262`). The ordering handler has its own action (`core/taxonomies.php:1389`). The `wp_kses_post` override of `unfiltered_html` is gone.
2. **Interfering with other plugins and themes: gone.** There is no `wp_dequeue_*` / `wp_deregister_*` anywhere. The only `remove_action` / `remove_filter` calls remove the plugin's own callbacks (`core/folder-talk.php:2678-2679`, `core/mime-types.php:38`, and `core/admin-shell.php:499`, which is limited to callbacks defined in the plugin's own files at `:509-530`). `core/compatibility.php` now only adds filters to Enfold's and FooGallery's own shortcode-attribute filters. The grid template has its own ID (`tmpl-vergeml-attachment-grid-view`, `core/media-templates.php:19`).
3. **Overriding globals and replacing core screens and scripts: partly fixed.** Gone: the writes to `$pagenow`, `$title`, `$hook_suffix` and `$submenu`, the copy of Settings → Media (`core/admin-menu.php:171` now redirects the old slug to core's `options-media.php`), `wp_dequeue_script( 'media' )`, `window.pagenow` / `window.adminpage` / `window.findPosts`, and the global `toggleSelection` patch (it is now a subclass, `js/eml-media-grid.js:353`). Still there: global `wp.media` prototype replacements on every admin media modal, the swap of core's media-grid container, and the rewrite of `$wp_taxonomies`. See LIKELY-QUESTION 1.
4. **Crash watchdog on every request: still there, unchanged.** See LIKELY-QUESTION 2.
5. **Bypassing the HTTP API: gone.** There is no `Requests::`, `request_multiple` or `curl_` left. Every outbound call is `wp_remote_post` / `wp_remote_get` (`core/ai.php:768` states it).
6. **SQL outside `prepare()`: partly fixed.** Gone: the `vergeml_smart_count_branches` raw-SQL filter, and the `implode()` of prepared fragments in `core/search-try.php` (now two full `prepare()` calls, `:58-89`). Still there: `core/guide.php:2785` assembles a statement from separately prepared parts, and static SQL fragments are interpolated from helper functions. See LIKELY-QUESTION 3.
7. **Direct writes to other posts' content: partly fixed.** `post_content` now goes through `wp_update_post()` with an `edit_post` check per post (`core/health-delete.php:184-198`, `core/health-keep.php:381-410`). Other posts' **meta** and `post_parent` are still written with `$wpdb->update()`. See LIKELY-QUESTION 4.
8. **Unprefixed taxonomy and an uninstall that can delete another plugin's data: still there.** See LIKELY-QUESTION 5.
9. **Script injected into core's `wp-api-fetch`: gone.** `js/vergeml-transport.js` is a registered script of its own that depends on `wp-api-fetch` (`core/transport.php:157-180`). It is pulled in only as a dependency of the plugin's own scripts. `core/instrument.php:400` and `core/licence-page.php:309` now attach their inline JS to `vergeml-transport`, not to a core handle.
10. **Readme: partly fixed.** Gone: the "What does this fork fix in the original features?" comparison. Fixed: the credit-check claim, which now matches the code (`readme.txt:119` against `core/licence-page.php:104-106`). Still there: size (27 KB). See LIKELY-QUESTION 6.
11. **Test and development code: partly fixed.** Gone: `vergeml_ai_describe_answers`, `vergeml_ai_run_should_nudge`, and the loopback-to-production reroute in `core/guide.php`. Still there: three seams that exist for your suites, and a `127.0.0.1` exception. See LIKELY-QUESTION 7.

---

## WOULD-REJECT

None this round.

---

## 1. LIKELY-QUESTION: Global replacement of core media views, the upload.php grid, and core taxonomy objects

**Rules:** HB-64, HB-66, BOT-hijack-admin (G11), CI-enqueue-admin; HB-47 (upstream code is no precedent).

- `js/vergeml-media-views.js` is enqueued from `wp_enqueue_media` on every admin screen that opens a media modal (`vergelabs-media-library.php:904`, `:1001`), including the block editor and other plugins' screens. It replaces core methods outright with `_.extend( media.view.X.prototype, … )`. For example `AttachmentsBrowser.prototype.createToolbar` is replaced at `:405` / `:493`, and `AttachmentCompat.prototype` at `:80`. The file's own header (`:11-22`) says these "may REPLACE core outright" and already broke the toolbar on WordPress 7.0. `js/eml-media-models.js:24`, `:60` and `:118` do the same to `Attachment`, `Attachments` and `Query`. `js/eml-enhanced-medialist.js:51-348` patches `MediaFrame.Post`, the gallery and playlist settings, and `GalleryEdit` / `CollectionEdit`.
- `js/eml-media.js:24` renames core's `#wp-media-grid` container while the page loads, so that core's `media.js` finds nothing to open. It then opens the plugin's own frame in that place. This is cleaner than dequeuing `media`, but it still replaces core's media grid.
- `vergelabs-media-library.php:745-758` changes `update_count_callback` on objects in the global `$wp_taxonomies`, including core's `category` and `post_tag` when they are assigned to attachments.
- `vergelabs-media-library.php:786-818` enqueues `eml-admin.css`, `eml-admin-media.css`, `wp-jquery-ui-dialog` and `js/eml-admin.js` on **every** admin screen, with no screen check.

**Fix:** Subclass core views and give the subclasses to your own frames (as you now do for `emlGridAttachment`), or wrap core methods and call through to them. Do not replace them for every modal on the site. Scope the admin assets to the screens that use them.

## 2. LIKELY-QUESTION: Crash watchdog still runs on every request and still switches the plugin's features off by itself

**Rules:** BOT-php-settings-globally, CI-no-global-ini, G11.3 / G11.4.

- `vergelabs-media-library.php:220` calls `vergeml_watchdog_boot()` at file load. On every front-end and admin request this holds 256 KB of memory (`core/watchdog.php:60`) and registers a global shutdown function (`core/watchdog.php:62`).
- `core/watchdog.php:147-148`: after two fatals in an hour, the plugin puts itself into safe mode without the user doing anything. The notice at `:249` has no dismiss. It does tell the user how to resolve it, and it goes away on resume.

**Fix:** Same as last round. WordPress Recovery Mode does this job. If you keep safe mode, let the user enter it (`VERGEML_SAFE_MODE` already exists, `core/watchdog.php:200`). Drop the automatic entry, the memory reserve and the global shutdown function.

## 3. LIKELY-QUESTION: SQL still assembled outside a single `prepare()`

**Rules:** BOT-unsafe-sql, CI-wpdb-prepare, HB-82.

- `core/guide.php:2785`: `$wpdb->get_results( "SELECT {$select} FROM {$t} i{$join} WHERE {$where}{$group} ORDER BY i.attachment_id ASC{$limit_sql}", ARRAY_A )`. The pieces are prepared one at a time on `:2763-2782`, but the final statement goes to `get_results` without `prepare()`.
- `core/filing.php:1578-1617` returns SQL fragments (`$words['select']`, `$product['select']`, `…['join']`) that are interpolated into `prepare()` strings at `core/guide.php:1908`, `:3220`, `core/folder-talk.php:1132-1144` and `core/auto-file.php:271`. They are static and contain only table names, so they are not injectable, but a reviewer reads them as `InterpolatedNotPrepared`.
- About 40 more statements interpolate table names (`{$t}`, `{$wpdb->vergeml_ai_index}`) with no `prepare()` at all, for example `core/ai-screen.php:117-155` and `core/guide.php:736-742`.

**Fix:** Build `core/guide.php:2785` as one `prepare()` call. For identifiers, use `%i` (you require WP 6.5, so it is available). This item is now a clean-up request, not a security finding.

## 4. LIKELY-QUESTION: Direct `$wpdb` writes to other posts' meta and parents

**Rules:** HB-64, HB-66, REST-12.

- `core/health-delete.php:375-399` searches **all** postmeta for the old file's URL stem and rewrites each matching row with `$wpdb->update( $wpdb->postmeta, … )` by `meta_id`. That includes other plugins' data such as `_elementor_data` and `_fl_builder_data`. `core/health-keep.php:441` and `:757` do the same for one source post and for undo.
- `core/health-delete.php:432`, `core/health-keep.php:449`, `:454` and `:769` set `post_parent` with `$wpdb->update( $wpdb->posts, … )`.

The permission side is now sound: `vergeml_health_repoint_refused()` (`core/health-delete.php:209-262`) refuses the delete unless the user may `edit_post` every post whose meta matches. The concern is the method. A raw update skips `update_post_meta` / `wp_update_post`, the `update_post_metadata` and `updated_post_meta` hooks, and any cache or index the owning plugin keeps (Elementor's CSS cache, for example). **Fix:** Use `update_metadata_by_mid()` (it keeps the meta hooks and cache) and `wp_update_post( array( 'ID' => …, 'post_parent' => … ) )`. Or explain why the raw writes are needed.

## 5. LIKELY-QUESTION: `media_category` is unprefixed, and both wipes delete all its terms

**Rules:** HB-61, BOT-custom-post-types-prefix, uninstall must remove only the plugin's own data.

- `vergelabs-media-library.php:1151-1153` registers `media_category` with `eml_media => 1`. `vergelabs-media-library.php:458-472` also copies Enhanced Media Library's `wpuxss_eml_taxonomies` into `vergeml_taxonomies`, so EML's own `media_category` is flagged as this plugin's.
- `uninstall.php:61-94` (opt-in wipe) and `core/options-pages.php:1531-1544` (Complete Cleanup) delete **every** term of every `eml_media` taxonomy, `media_category` included. On a site that has Enhanced Media Library, or that goes back to it, those are EML's folders as well. Your own readme says other plugins read this taxonomy (`readme.txt:189`).

**Fix:** Keep the shared name if you need it for migration, and say so when you reply. But make both wipes leave `media_category` terms in place, and remove only your own term meta and relationships, or ask in the wipe's confirmation whether to delete the shared folders too.

## 6. LIKELY-QUESTION: Readme length

**Rules:** RM-32, RM-35/RM-36, G12.5.

- `readme.txt` is 27 KB (the guideline is 10 KB). Description is about 1,200 words and External services about 1,250.
- `readme.txt:211-227` lists thirteen third-party products under "checked against". Combined with the seven folder plugins named for the importer (`:42`), a reviewer may read the list as name-dropping. The importer list is functional and fine. The compatibility log belongs in `changelog.txt` or on your site.
- A small inconsistency: `:17` says "PHP 7.4 through 8.3", while `:219` lists PHP 8.5.11 as checked.

**Fix:** Move the compatibility log and the longer feature prose to your site. Keep External services as it is: it is complete and matches the code.

## 7. LIKELY-QUESTION: Remaining test seams and loopback code

**Rules:** BOT-unneeded-folders (spirit), FAQ-4, PCS-PluginCheck.CodeAnalysis.Localhost.

- `core/journey.php:237-245`: `apply_filters( 'vergeml_journey_facts', … )`, documented as "The seam the suite uses to put the dashboard into a state the live library is not in".
- `core/ai-background.php:363-364`: `vergeml_ai_run_budget`, "a suite reaches the tail's other branch through the filter".
- `core/guide.php:985`: the REST arg `turns`, "what a suite plants and puts back". `core/brief.php:532`: `'start' false … (a suite)`.
- `core/ai.php:316` accepts `http://127.0.0.1` as a service URL, for a local copy of the service. `core/offer.php:26` matches `localhost|127\.` to refuse the trial on local sites. The second has a real user-facing purpose and is fine with an explanation. The first is development-only.

These are much milder than last round's: none of them lets another plugin replace AI answers. **Fix:** Remove the suite-only seams and the `127.0.0.1` exception from the release, or keep the filters as documented public hooks with a user-facing reason.

## 8. LIKELY-QUESTION: A second route to every REST endpoint through admin-ajax

**Rules:** HB-64 ("own JSON endpoints instead of REST API").

`core/transport.php:31-115` registers `wp_ajax_vergeml_rest`. It re-dispatches any `vergeml/v1` route through `rest_do_request()` when `/wp-json/` is blocked or broken on the host. The checks are correct: the `wp_rest` nonce (`:46`), `upload_files` (`:51`), core's `rest_authentication_errors` filter (`:56`), a route allow-list restricted to the plugin's namespace (`:120-139`), and each route's own `permission_callback`. A reviewer may still ask why the REST API is tunnelled through admin-ajax. Expect to explain it. We would likely accept it as it stands.

---

## Checked and acceptable (we would not send these back)

- **REST permissions**: 77 `register_rest_route` calls, 85 endpoints, 85 `permission_callback`s, none `__return_true`. 69 use `manage_options`. The rest check per object (`edit_post` in `vergeml_rest_can_edit_this_file`, `vergelabs-media-library.php:149`; `delete_post` per dropped file, `core/health-delete.php:564-582`; `edit_term` / `manage_terms` / `assign_terms` in `core/rest-folders.php:91` and `core/rest-tree.php:228`) or are read-only with `upload_files`. The `upload_files` GETs (`/ai-alt`, `/rename`) only return counts. Their POSTs need `manage_options` (`core/ai.php:1708`).
- **AJAX / admin_post / admin_init handlers**: each one checks a nonce and a capability (zip, help send, CSV export, journey alt/rename, rename/undo, licence save, connect start/finish with per-user state and `hash_equals`, trial, network settings and licence, settings import/export/restore/cleanup/wipe, watchdog resume, neighbour dismiss, term counts, attachment order). The ordering handler checks `edit_post` per attachment (`core/taxonomies.php:1432-1442`).
- **Input and output**: every superglobal read is unslashed and sanitised, or cast. Every `echo` / `printf` with a variable is escaped. The bare echoes are literals or literal ternaries. There are no `<script>` / `<style>` tags except underscore `text/html` templates. The two remaining inline snippets go through `wp_add_inline_script` (`vergelabs-media-library.php:115-121`).
- **Outgoing requests vs readme**: hosts are `ai.vergelabs.nl/v1` and `vergelabsmedia.com` only (overridable by wp-config constants, not filters). Every call follows a button, a saved key, or an opt-in: describe, file, plan / refund, talk, name-group, embed (search by meaning is a nonced link for admins only, `core/search-meaning.php:676-750`), licence check (cached, plus forced on the Licence screen as the readme now says), trial, connect, help ticket, known issues (on button press, `core/get-help.php:64-90`), and counts (opt-in, `core/instrument.php:253-260`). Nothing is sent on upload, on activation, or without a key, except the help screen and the known-issues list, both disclosed. The browser-side stream (`js/vergeml-talk.js:398`) and its IP exposure are disclosed (`readme.txt:115`). Terms, privacy, sub-processors and DPA are linked.
- **Trialware / serviceware**: folders, smart folders, importer, galleries, MIME settings and the date / type Librarian work without a key. Licence checks only gate requests to the service (`core/guide.php:1276`, `core/ai.php:368`). The 25-credit trial is a real service trial. Buy links carry `utm_*` only, with no site identifier (`core/connect.php:51-60`).
- **Admin notices**: all are scoped to the upload screen or the plugin's own screens, and are dismissible (neighbours, persisted per user) or one-shot redirect results. The safe-mode notice is the exception (item 2).
- **Prefixing**: functions, classes, options, transients, meta keys, script handles, AJAX actions, REST namespace (`vergeml/v1`) and shortcode (`vergeml_gallery`) are all prefixed. `media_category` is the one exception (item 5).
- **No interference on activation or deactivation**: no `deactivate_plugins`, no `active_plugins` writes, no `plugin_action_links` removal. Activation copies EML's options and never removes them (`vergelabs-media-library.php:456-482`).
- **MIME types**: still limited to `wp_get_mime_types()`, with the deny-list in place (`core/mime-types.php`).
- **Script tags from other optimisers**: `core/neighbours.php:170-199` touches only `vergeml-` handles, and adds exclusions through WP Rocket's own filters.
- **Files and licences**: there are no tests, docs, tooling, `.git`, `vendor/` or minified bundles in the zip, and no `console.log` / `error_log` / `var_dump`. Every PHP file has an ABSPATH or `WP_UNINSTALL_PLUGIN` guard. Inter (OFL) and webbistro (GPL) are credited, and natural-compare-lite (MIT) is credited in the readme and in the file. GPLv2-or-later headers match.
- **Headers**: `Stable tag` 4.1.0 matches `Version`. 5 tags, no competitor names. The short description is 89 characters. The main file name matches the slug. `Tested up to: 7.1` is fine if 7.1 is the current release when you upload.
- **Optional**: there is no `wp_add_privacy_policy_content()`. G7.3 says "preferably", so we would not ask for it, but it would be good to add.
