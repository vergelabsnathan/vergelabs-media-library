# Review 5: VergeLabs Media Library 4.1.0 (vergelabs-media-library)

Hello,

Thank you for the update. We reviewed the zip as uploaded: 143 files in `core/`, `css/`, `fonts/`, `images/`, `js/`, `languages/`, the main file, `uninstall.php`, `readme.txt`, `changelog.txt`, `SECURITY.md` and `wpml-config.xml`. We ignored every `phpcs:ignore` / `phpcs:disable` annotation and read the code under each one.

Both classes from our last email are gone, across the whole plugin and not only on the lines we quoted. No setting can allow a type that a browser interprets or a server runs. No SQL statement interpolates or concatenates anything except core table names and inline placeholder lists. We found nothing new that we would reject for. One new question concerns how the MIME callback is written. The other questions are carried over unchanged from the previous round. As before, we list examples, not every instance, so please check each **class** across the whole plugin.

Ranking: **WOULD-REJECT** means we will send it back again. **LIKELY-QUESTION** means a reviewer may ask you about it, and you should be ready to change it or explain it.

---

## Round 4's classes

### 1. Adding insecure MIME types: gone

**Rules:** BOT-insecure-mime, CI-upload-mimes.

The refusal list (`core/mime-types.php:126`) now also covers `css`, `xml`, `xsl`, `xslt`, `dfxp`, `xht`, `mht`, `mhtml`, `hta`, `vbs`, `ps1` and `wasm`. It is checked per part of a combined key, so `htm|html` is refused on `htm` (`:124`). It is enforced on every path that writes or reads the setting:

- **Save:** `vergeml_mimes_validate()` drops any extension `wp_get_mime_types()` does not know, stores core's MIME string rather than the posted one, and forces `upload` off for a refused type (`core/mime-types.php:66-93`).
- **Restore defaults:** same check (`:43-51`). **Activation default:** same check (`vergelabs-media-library.php:1403-1417`).
- **Copies that bypass the form:** Enhanced Media Library's old `wpuxss_eml_mimes` is copied as-is (`vergelabs-media-library.php:464`), and so are settings import, backup restore and the network-wide copy (`core/options-pages.php:928`, `:1341`, `:1392`). None of them matter, because the read side checks again. `vergeml_mimes_prepared()` skips any extension core does not know and sets `upload` to false for any refused one (`core/mime-types.php:192-203`). `vergeml_upload_mimes()` reads only that (`:302-310`), so a refused type that is stored as allowed is *unset* from the list.
- **`wp_check_filetype_and_ext`** (`:331-422`) runs only after core has already rejected a file for a content/extension mismatch. It lets through `ttf`/`otf`/`woff`/`woff2` whose content reads as a font or as `application/octet-stream`, and Excel types whose content reads as `.xlsx`, and only if the type is already in `get_allowed_mime_types()` (`:408-416`). Everything else stays rejected (`:398-405`).
- **Other routes:** the plugin hooks no `mime_types`, `ALLOW_UNFILTERED_UPLOADS`, `unfiltered_upload`, `wp_handle_upload_prefilter` or plupload settings. `post_mime_types` (`:227-269`) only adds filter labels. The file renamer (behind `VERGEML_FILE_RENAME`) keeps the original extension (`core/rename-file.php:81`, `:106`). The settings-import upload reads JSON from `tmp_name` and never stores the file (`core/options-pages.php:1289-1310`).

**What remains switchable.** WordPress 7.1.3's `wp_get_mime_types()` has 98 keys. 7 are refused: `css`, `htm|html`, `dfxp`, `js`, `swf`, `class`, `exe`. The other 91 can be switched:

- Images (12): `jpg|jpeg|jpe`, `gif`, `png`, `bmp`, `tiff|tif`, `webp`, `avif`, `ico`, `heic`, `heif`, `heics`, `heifs`
- Video (15): `asf|asx`, `wmv`, `wmx`, `wm`, `avi`, `divx`, `flv`, `mov|qt`, `mpeg|mpg|mpe`, `mp4|m4v`, `ogv`, `webm`, `mkv`, `3gp|3gpp`, `3g2|3gp2`
- Text (6): `txt|asc|c|cc|h|srt`, `csv`, `tsv`, `ics`, `rtx`, `vtt`
- Audio (10): `mp3|m4a|m4b`, `aac`, `ra|ram`, `wav|x-wav`, `ogg|oga`, `flac`, `mid|midi`, `wma`, `wax`, `mka`
- Misc (9): `rtf`, `pdf`, `tar`, `zip`, `gz|gzip`, `rar`, `7z`, `psd`, `xcf`
- MS Office (28): `doc`, `pot|pps|ppt`, `wri`, `xla|xls|xlt|xlw`, `mdb`, `mpp`, `docx`, `docm`, `dotx`, `dotm`, `xlsx`, `xlsm`, `xlsb`, `xltx`, `xltm`, `xlam`, `pptx`, `pptm`, `ppsx`, `ppsm`, `potx`, `potm`, `ppam`, `sldx`, `sldm`, `onetoc|onetoc2|onetmp|onepkg`, `oxps`, `xps`
- OpenOffice, WordPerfect, iWork (11): `odt`, `odp`, `ods`, `odg`, `odc`, `odb`, `odf`, `wp|wpd`, `key`, `numbers`, `pages`

None of the 91 is a type a browser runs as markup, style or script, or a server executes. The macro-enabled Office formats, `pdf` and `flv` carry content of their own, but they are opened by other applications, not by the site. **All 91 are already in core's own default `get_allowed_mime_types()`**, which removes only `swf` and `exe`, plus `htm|html` and `js` for users without `unfiltered_html`. So on a site with no other filters, this setting can only narrow what WordPress accepts. See LIKELY-QUESTION 1 for the part of the callback that is still written as an add.

### 2. Unsafe SQL calls: gone

**Rules:** BOT-unsafe-sql, CI-wpdb-prepare, HB-82, PCS-WordPress.DB.PreparedSQL.InterpolatedNotPrepared.

We parsed every `$wpdb->` and `$GLOBALS['wpdb']->` call in the plugin and checked the SQL argument token by token: 426 calls, not counting four mentions in comments. That is 199 `prepare`, 62 `get_results`, 66 `get_var`, 36 `get_col`, 15 `get_row`, 29 `query`, 4 `insert`, 12 `update` and 3 `delete`.

- **No SQL string interpolates or concatenates a variable, a non-core `$wpdb` property, a function result, or a fragment built elsewhere.** All 188 calls that run a query with variable parts have a single `$wpdb->prepare()` as their whole argument. Nothing is appended after it.
- **Plugin tables go in as `%i`.** The 91 references to `$wpdb->vergeml_ai_index` and those to `vergeml_librarian_moves` (11), `vergeml_organize_runs` (10) and `vergeml_librarian_batches` (9) are all `%i` arguments. None is inside a string. The same applies to the other plugins' tables read by the importer (`$wpdb->prefix . 'fbv'`, `'realmedialibrary'`, `core/import-sources.php:213-214`, `:278-279`) and by the SEO lookup (`'aioseo_posts'`, `core/seo-context.php:83`, `:298`). Column names chosen in code are `%i` too (`core/guide.php:2809`, `:2831`, `:2852`, `:2870`, `core/ai-folders.php:208`, `core/options-pages.php:1636`, `:1652`).
- **The 20 statements without `prepare()` are literal** and name only core tables: `core/ai-screen.php:117`, `:119`; `core/guide.php:270`, `:366`; `core/health.php:493`; `core/import-ui.php:230`, `:231`; `core/instrument.php:79`, `:85`; `core/options-pages.php:1699`, `:1752`; `core/smart-folders.php:879`, `:983`; `uninstall.php:36`, `:120`, `:128`, `:130`, `:171`, `:200`, `:204`.
- **Placeholder lists:** 37 `prepare()` calls build their list inline. 36 use the accepted `IN ( ' . implode( ', ', array_fill( 0, count( $x ), '%d' ) ) . ' )` shape, with `%s` in `core/seo-context.php:281-313` and `core/taxonomies.php:1621`. One builds a multi-row `VALUES` list the same way, with a row of placeholders repeated per row (`core/librarian.php:1791-1797`). No placeholder list is built in a variable and passed in.
- **Last round's examples are fixed.** The statement assembled at `core/guide.php:2785` is now four literal `prepare()` calls, one per join shape (`core/guide.php:2807-2884`). The SQL fragments returned from `core/filing.php` are gone. `core/ai-screen.php:117-155` and `core/guide.php:736-742` now use `%i` or are literal.
- **Schema:** the `CREATE TABLE` strings go only to `dbDelta()` (`core/ai-index.php:132` → `:162`, `core/librarian.php:176` → `:195`, `:241` → `:263`, `core/organize.php:259` → `:276`). `DROP TABLE IF EXISTS %i` is used at `uninstall.php:110`, `core/ai-index.php:55` and `core/options-pages.php:1612`.

Our extractor flagged all ten bad patterns we planted to test it: an interpolated plugin table, `{$t}`, `' . $table . '`, a bare `$sql`, text appended after `prepare()`, `{$where}`, a function result, `$wpdb->prefix` in a string, `{$x}` via `$GLOBALS['wpdb']`, and a core-table-only control that it correctly passed.

---

## WOULD-REJECT

None this round.

---

## 1. LIKELY-QUESTION (new): The `upload_mimes` callback still adds types from an option

**Rules:** BOT-insecure-mime ("the added list must be fixed and literal, with no … option-driven extensions"), CI-upload-mimes, SEC-7 (allow-list over block-list).

- `core/mime-types.php:300-306`: on a single site, `$ceiling` is `null`, so for every stored type marked for upload the callback runs `$types[ $ext ] = $entry['mime']`. As shown above, all 91 switchable types are already in core's default list. The add branch therefore changes something only when another filter, such as a security plugin, removed a type, and then it puts that type back. An automated pass that sees an option-driven write into `upload_mimes` can still read it as "adding MIME types".
- Refusal is by extension only (`:126`). `wp_get_mime_types()` includes other plugins' `mime_types` additions, so a type another plugin adds under a new extension with a scriptable MIME becomes switchable here. Examples are `rss` → `application/rss+xml`, or any `+xml` type, which a browser renders as an XML document that can run script in the XHTML namespace. Core adds no such type, so this matters only alongside such a plugin.

**Fix (cheap):** Make the callback subtractive on every site, as it already is on multisite: use `$types` as the ceiling always (`$ceiling = $types;`), so the setting can switch types off but never on. Also refuse by MIME value, for example any MIME containing `html`, `xml`, `css`, `javascript`, `ecmascript`, `php`, `shockwave` or `java`. Nothing a user can do today changes, because core already allows every type the screen can switch on. The callback no longer contains a line that adds.

## 2. LIKELY-QUESTION: Global replacement of core media views, the upload.php grid and core taxonomy objects (unchanged)

**Rules:** HB-64, HB-66, BOT-hijack-admin (G11), CI-enqueue-admin; HB-47.

Same as round 4, item 1, with the same lines. `js/vergeml-media-views.js` (enqueued from `wp_enqueue_media`, `vergelabs-media-library.php:904`) replaces core prototypes with `_.extend( media.view.X.prototype, … )`, for example `AttachmentCompat` at `:80`. `js/eml-media-models.js:24`, `:60` and `:118` and `js/eml-enhanced-medialist.js:51-348` do the same. `js/eml-media.js:24` renames core's grid container. `vergelabs-media-library.php:745-758` rewrites `update_count_callback` in `$wp_taxonomies`. `:786-818` enqueues admin CSS/JS on every admin screen. **Fix:** as before: subclass instead of replacing, and scope the assets to the screens that use them.

## 3. LIKELY-QUESTION: Crash watchdog on every request (unchanged)

**Rules:** BOT-php-settings-globally, CI-no-global-ini, G11.3 / G11.4.

`vergelabs-media-library.php:220` boots it on every request. It holds a 256 KB string (`core/watchdog.php:60`), registers a global shutdown function (`:62`), and enters safe mode by itself after two fatals in an hour (`:147-149`). **Fix:** as before: leave this to Recovery Mode, or make safe mode something the user switches on (`VERGEML_SAFE_MODE`, `:200`).

## 4. LIKELY-QUESTION: Direct `$wpdb` writes to other posts' meta and parents (unchanged)

**Rules:** HB-64, HB-66, REST-12.

`core/health-delete.php:397` and `core/health-keep.php:441` / `:757` rewrite matching postmeta rows of *other* posts by `meta_id` with `$wpdb->update()`. That includes other plugins' data, such as page-builder JSON. `core/health-delete.php:432` and `core/health-keep.php:449` / `:454` / `:769` set `post_parent` with `$wpdb->update( $wpdb->posts, … )`. The permission gate (`edit_post` on every affected post) is sound. **Fix:** as before: use `update_metadata_by_mid()` and `wp_update_post()`, or explain why the raw writes are needed.

## 5. LIKELY-QUESTION: `media_category` is unprefixed, and both wipes delete its terms (unchanged)

**Rules:** HB-61, BOT-custom-post-types-prefix; uninstall removes only the plugin's own data.

`vergelabs-media-library.php:1151` registers `media_category`. `:464` copies Enhanced Media Library's taxonomy settings, so EML's `media_category` is marked `eml_media`. `uninstall.php:59-98` (opt-in wipe) and `core/options-pages.php:1537-1540` (Complete Cleanup) delete every term of every `eml_media` taxonomy. **Fix:** as before: keep the shared name for migration if you need it, but leave `media_category` terms in place, or ask about them in the wipe's confirmation.

## 6. LIKELY-QUESTION: Readme length (unchanged)

**Rules:** RM-32, RM-35/RM-36, G12.5.

`readme.txt` is 27,161 bytes. The compatibility log is at `:211-227`. `:17` says "PHP 7.4 through 8.3", but `:219` lists PHP 8.5.11 as checked. The MIME lines at `:91` and `:268` now name CSS and XML, which matches the code.

## 7. LIKELY-QUESTION: Test seams and a loopback exception (unchanged)

**Rules:** BOT-unneeded-folders (spirit), FAQ-4, PCS-PluginCheck.CodeAnalysis.Localhost.

- `core/journey.php:242-249`: `vergeml_journey_facts`, "The seam the suite uses…".
- `core/ai-background.php:363-364`: `vergeml_ai_run_budget`.
- `core/guide.php:987`: the `turns` REST arg, "what a suite plants and puts back".
- `core/brief.php:533`: `'start' false … (a suite)`.
- `core/ai.php:315-316`: accepts `http://127.0.0.1` as the service URL.

`core/offer.php:26` has a user-facing reason and is fine.

## 8. LIKELY-QUESTION: REST routes reachable through admin-ajax (unchanged)

**Rules:** HB-64.

`core/transport.php:31` registers `wp_ajax_vergeml_rest`. The nonce, `upload_files`, `rest_authentication_errors`, namespace allow-list and per-route `permission_callback` checks are all in place. Expect to explain why the REST API is tunnelled. We would likely accept it as it stands.

---

## Checked and acceptable (we would not send these back)

- **WP_Query clause filters** return SQL to core, which is how those filters work. Every variable part is `prepare()`d: `core/ai.php:1743` (`%i` join) and `:1773-1792` (prepared `LIKE`s, inserted into core's clause), `core/ai-folders.php:206-208` (`%i` table, `%i` allow-listed column, bound value), and `core/search.php:200-217` (prepared `LIKE`s). The joins at `core/search.php:127-138` are static and name core tables only.
- **`insert` / `update` / `delete`** use `$wpdb`'s table API with array data. The table is a plugin table from code (`vergeml_index_table()`, `vergeml_librarian_batches_table()`, `vergeml_organize_table()`) or a core table.
- **REST permissions:** 77 `register_rest_route` calls and 86 `permission_callback`s, none `__return_true`. The two `__return_true` uses are core display filters behind settings (`core/taxonomies.php:676`, `vergelabs-media-library.php:1519`).
- **No dangerous functions:** no `eval`, `create_function`, `shell_exec`, `system`, `passthru`, `proc_open`, `assert` or `extract`. The one `maybe_unserialize` reads core's own `_wp_attachment_metadata` (`core/health.php:935`).
- **`set_time_limit`** appears only inside the plugin's own cron runs (`core/ai-background.php:351`, `core/guide.php:1702`, `core/plan-tree.php:349`, `:860`). `ignore_user_abort` appears only in the admin-only settings export (`core/options-pages.php:1237`).
- **No interference:** no `wp_dequeue_*` / `wp_deregister_*`. The `remove_action` / `remove_filter` calls touch only the plugin's own callbacks (`core/folder-talk.php:2700-2701`, `core/mime-types.php:38`, `core/admin-shell.php:499`). `active_plugins` is only read: for the help report (`core/get-help.php:107`, disclosed at `readme.txt:121`) and the system report.
- **Outgoing requests vs readme:** hosts are `ai.vergelabs.nl/v1` and `vergelabsmedia.com` only. The GitHub URLs are plain links (`vergelabs-media-library.php:1504`, `core/options-pages.php:3048`, `:3051`). The known-problems fetch is a bare GET on a button press, matched locally (`core/get-help.php:64-90`), as `readme.txt:121` says.
- **Trialware, notices, prefixing, files, licences, headers:** unchanged since round 4 and still acceptable. The round-4 fixes touched no hook, capability, nonce or remote-request line.

---

*Method (internal):* the archive at `C:\Users\viete\AppData\Local\Temp\vgml-r4\vergelabs-media-library` is byte-identical to commit `a3b489d` (143 of 143 files). Since the round-4 pass (`df693f4`), shipped code changed only in `338ff57` (MIME list, readme lines) and `c34020f` (SQL). The core MIME list was taken from WordPress 7.1.3's `wp-includes/functions.php`. The SQL extractor is `C:\Users\viete\AppData\Local\Temp\vgml-r5-scratch\sql.js`.
