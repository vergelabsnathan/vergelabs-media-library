# Review 7: VergeLabs Media Library 4.1.0 (vergelabs-media-library), fresh eyes on `6c54cd0`

Read as the Plugins Team would: the release archive of `6c54cd0` only (`git archive 6c54cd0`, 146 files: 68 PHP, 43 JS, 22 CSS, readme, changelog, fonts, images), every `phpcs:ignore` / `phpcs:disable` ignored, every finding checked by a grep across the whole archive, not only the quoted line. Rulebook: `docs/wordpress-org-rulebook.md`. Inputs: the round-6 ledger in `docs/wordpress-org-submission.md`, `review6-fresh-eyes.md`, `r6-team-reviewer.md`. No PHP runtime here, so every verdict is static.

Result: **0 would-reject.** Every class an email named is still gone. Ten of the fourteen *fixed* rows hold for the whole class. Four hold only for the lines they name: 2, 4, 14 and 16. The ledger also dropped two halves of the team-reviewer's issues, the foreign-taxonomy detach and the OpenSSL trial. Neither is a would-reject. Both are cheap to fix.

---

## WOULD-REJECT

None.

---

## LIKELY-QUESTION (new: not in the ledger)

### 1. Other plugins' taxonomies detached from media on every request (the other half of team-reviewer issue 3)

**Rules:** G11 / BOT-hijack-admin; HB-64 / HB-66 (do not change what is not yours); the "interfering with other plugins" class.

`vergelabs-media-library.php:717-726`, on `wp_loaded` (every request, front end included):

```php
if ( (bool) $params['assigned'] )   register_taxonomy_for_object_type( $taxonomy, 'attachment' );
if ( ! (bool) $params['assigned'] ) unregister_taxonomy_for_object_type( $taxonomy, 'attachment' );
```

`$vergeml_taxonomies` holds every taxonomy the plugin ever discovered (`:690-714`). `assigned` is frozen at the state it had on the first request that saw the taxonomy. Suppose another plugin attaches its taxonomy to attachments later. Two examples: Polylang's `language` after media translation is switched on, or a "categories for media" plugin installed afterwards. This plugin then detaches it again on every request. Ledger row 10 fixed only the count-callback half of the same issue (`:735-779`). `aa02c46` says "foreign taxonomies left alone", but this loop is unchanged since `a47d6f0`. This is the only `unregister_*` call in the archive.

**Cheap fix.** Call `unregister_taxonomy_for_object_type()` only for a taxonomy the owner unticked on the Media Taxonomies screen. Store a flag such as `'owner_set' => 1` when that screen saves. On discovery, re-read `assigned` from the live `object_type` unless that flag is set.

### 2. The wipe screens still say the folders are deleted

**Rules:** HB-70 (options explained accurately); Nathan's own rule that a deleting button says what it deletes.

Row 4's code is right: `uninstall.php:66` and `core/options-pages.php:1536` skip `media_category`. The screen around it is wrong in four places:

- `core/options-pages.php:569-572` and `:822-825`, under "What will be deleted:", list every `eml_media` taxonomy, so **"All Media Categories"** appears. On the same screen the confirm dialog says "Your folders are kept" (`:396`).
- `:604` reads "Also remove **all folders**, settings and the AI index when the plugin is deleted". The paragraph right above it (`:598`) says deleting keeps the folders.
- `:712` (network) reads "Remove every site's **folders**, settings and AI index…"

The readme FAQ and changelog say both wipes keep the folders.

**Cheap fix.** Skip `media_category` in the two lists, as the delete does. Reword `:604` and `:712` to "Also remove this plugin's settings, tables and AI index (your folders stay)".

### 3. Without OpenSSL, the free try and Connect report success and store no key (the other half of team-reviewer issue 5)

**Rules:** the activation checklist ("anything it depends on is checked before use, with an explanation instead of failing"); HB-69.

- `vergeml_ai_seal()` returns `''` without OpenSSL (`core/ai.php:332`). `core/offer.php:187` and `core/connect.php:198` then store `''` and answer success. The trial even returns a "next" URL that auto-starts a run.
- The service has already spent the site's and the email's single free try.
- `vergeml_offer_applies()` (`core/offer.php:34`) and `vergeml_offer_trial_possible()` (`:24-28`) never check OpenSSL, so the offer shows.
- The explaining notice cannot be seen on the AI and Licence screens. `vergeml_requirements_notice` is defined in the plugin's own file, and `vergeml_shell_quiet()` removes the plugin's own notices on those screens (`core/admin-shell.php:482-503`).

A reviewer is unlikely to hit this, because Playground has OpenSSL. A reader of the requirements notice will expect it to hold.

**Cheap fix.**
- `function_exists( 'openssl_encrypt' )` in `vergeml_offer_trial_possible()`.
- The same check at the top of `vergeml_offer_ajax_trial()` and `vergeml_connect_start()`, answering with the notice's sentence.
- Optionally, print that sentence on the Licence screen.

### 4. Two JS globals exist only for the suites

**Rules:** FAQ-4 ("production-ready … without development tools"); BOT-unneeded-folders (spirit).

- `js/vergeml-folders.js:2618-2619`:
  ```js
  // progress: the one row every long step renders through (S10.0), driven by the suite with models of its own.
  window.vgmlFoldersApp = { state, view, preview, stop, setStep, render, progress, rows }
  ```
- `js/vergeml-brief.js:371`: `window.vgmlBriefApp = { state: state, talk: talk };`

A grep of `js/`, `core/` and the main file finds no reader of either. They are test hooks, the JS counterpart of the PHP seams row 2 removed. The comment says so.

**Cheap fix.** Delete both assignments and have the suites read state through the DOM. Or keep the objects and drop "driven by the suite", if Nathan wants the hooks kept.

### 5. Dead code a reviewer's grep lands on

**Rules:** FAQ-4; BOT-unsafe-sql (shape); readme/accuracy.

- `core/filing.php:1576` `vergeml_filing_words_sql()` and `:1596` `vergeml_filing_product_sql()` are never called. Their only mentions are comments (`auto-file.php:268`, `folder-talk.php:1134/1136`, `guide.php:1906-1907/3332`), and they still return SELECT/JOIN fragments. Round 4 was this shape. Row 14 removed the variable but kept the functions, although the team-reviewer asked for them to be deleted.
- `js/vergeml-taxonomies-options.js:571-595` posts to the AJAX action `vergeml-synchronize-terms`. No PHP registers it, and no PHP prints the `.eml-button-synchronize-terms` button. The file header (`:7`) still advertises it.
- `css/eml-admin-media.css:550` and `css/eml-admin-media-rtl.css:514`: `/* == TODO: look deeper into this == */`.
- `vergelabs-media-library.php:1629-1634` says "after two fatal errors in an hour none of it loads … the notice in core/watchdog.php explains why". That describes the self-entered safe mode row 3 removed. A reviewer checking row 3 reads this comment first.

**Cheap fix.** Delete the two functions and the comments that cite them, the synchronize handler, and the TODO line. Rewrite the comment as "Safe mode (VERGEML_SAFE_MODE) or no mbstring stops here."

### 6. The trial screen does not say the email address will be written to

**Rules:** HB-77 / HB-78 (informed consent for data collection); G7.

The readme (`:65`) says the trial email "receives one message, once the 25 credits are used, with the key and a way to buy more". The screen the address is typed into (`core/offer.php:101`) says only "Your email and this site's address go to VergeLabs to make the key."

**Cheap fix.** Add one sentence to `:101`: "We email you once, when the 25 are used."

### 7. "https only" covers one of the four endpoint constants

**Rules:** G7; part of ledger row 2.

`vergeml_ai_service_url()` forces https (`core/ai.php:316`), and so does the safe-mode copy (`core/get-help.php:189`). The other three constants the readme lists (`:75`) are used as given:

- `VERGEML_SITE_URL` (`core/connect.php:39`) carries the licence-key exchange and the trial email.
- `VERGEML_AI_STREAM` (`core/guide.php:856-857`).
- `VERGEML_KNOWN_ISSUES_URL` (`core/get-help.php:58`).

All three are set by the site owner in wp-config, so the risk is low. The ledger's "https only" claim is narrower than it reads.

**Cheap fix.** Apply the same `0 === strpos( $url, 'https://' )` rule to the other three.

---

## LEDGER CHECK (row by row, on the archive)

| # | Status in ledger | Verdict | Evidence (class grep, annotations ignored) |
|---|---|---|---|
| 1 | fixed | **Confirmed** | 0 `$wpdb->update/insert/delete/replace` and 0 raw `UPDATE/DELETE/INSERT` on `posts`/`postmeta` outside `uninstall.php`. Health keep/delete/undo go through `update_metadata_by_mid()` / `wp_update_post()` (`health-delete.php:196-219`, `health-keep.php:441-456`, `:752-767`). The reset uses `delete_post_meta_by_key()` (`health.php:525`). Reorder uses `wp_update_post()` (`taxonomies.php:1449-1456`). The remaining core-table writes are `term_taxonomy.count` in the plugin's own counters (`taxonomies.php:1568`, `:1627`) and Complete Cleanup's `term_taxonomy` delete (`options-pages.php:1549`), both accepted before. |
| 2 | fixed | **Confirmed for the named seams; class only partly done** | `vergeml_journey_facts` / `vergeml_ai_run_budget` filters: 0 hits. `127.0.0.1`: 0 hits (the `localhost\|127\.` regex at `offer.php:26` only refuses trials on local hosts). The budget is a tick parameter (`ai-background.php:316`, `:364`). Not covered: the JS hooks `vgmlFoldersApp` / `vgmlBriefApp` (LQ 4), and https-only covers 1 of 4 endpoint constants (LQ 7). Tuning filters (`vergeml_talk_slice/pass`, `vergeml_guide_fit_budget`, `vergeml_meaning_budget_ms`) have a stated use for hosts and are acceptable. |
| 3 | decided, fixed | **Confirmed** | `core/watchdog.php` is 39 lines: `vergeml_safe_mode()` reads only the constant, and the notice shows only when it is set. 0 `register_shutdown_function` for crashes (the one hit is the cron continuation, `ai-background.php:206`). `delete_option( 'vergeml_watchdog' )` runs on every version change (`vergelabs-media-library.php:356`). Leftover: the stale comment at `:1629-1634` (LQ 5). |
| 4 | decided, fixed | **Code confirmed, screen not** | Both wipes skip `media_category` and any taxonomy without `eml_media` (`uninstall.php:66`, `options-pages.php:1536`). Four UI strings still say the folders go (LQ 2). |
| 5 | answered | **Would satisfy** | ACF rules: 0 hits in `css/`. `wp_enqueue_style( 'common' )` is gone from `compatibility.php`. The modal sheet loads from `wp_enqueue_media` only, admin only, and not in safe mode (`vergelabs-media-library.php:956-968`). The team-reviewer pass said it would accept "the media modal is the surface this plugin extends". The upload comment that should carry that sentence is not written yet. |
| 6 | decided (answer now) | **Would satisfy, if phrased as the design** | 16 `_.extend( media.*.prototype … )` in 4 files, all inherited from Enhanced Media Library 2.9.4. Say that core's media views are extended as Enhanced Media Library 2.9.4 does. Do not promise a rewrite "after approval": a promise invites "please do it before approval". |
| 7 | fixed in part, answered | **Would likely satisfy** | `readme.txt` is 19,644 bytes against RM-32's 10 KB, which is advisory ("may result in errors"). The changelog sits in `changelog.txt`. No email in five rounds has raised length. Further cuts are listed in `readme-draft-round6-notes.md` (about 2.3 KB). |
| 8 | answered | **Would satisfy** | `core/transport.php:33-123` checks the `wp_rest` nonce, `upload_files` and `rest_authentication_errors`. It accepts only routes `rest_get_server()->get_routes( VERGEML_REST_NS )` matches, and only if the method is registered. `rest_do_request()` then runs each route's permission callback and args. The answer must not say undeclared params are dropped: they pass through, as on REST. |
| 9 | fixed | **Confirmed** (the OpenSSL half of the same issue is open, LQ 3) | Counters are named only when defined (`vergelabs-media-library.php:660`, `:773`). `vergeml_enqueue_media` returns early when features are off (`:956`). `vergeml_on_init` guards `vergeml_sanitize_slug` / `vergeml_folder_object_types` (`:635`, `:652`). A script pass over every `vergeml_*()` call in the main file, `get-help.php` and `system-report.php` finds no unguarded call into an unloaded file. |
| 10 | fixed | **Confirmed for the counters; the other half is open** | `:763-778`: only taxonomies on attachments still on core's counter, and another plugin's callback is never replaced. The register/unregister loop at `:717-726` is untouched (LQ 1). |
| 11 | fixed | **Confirmed** | `vergeml_hide_submenu` returns unless `vergeml_shell_current()` (`admin-menu.php:86-88`). All 19 `admin_enqueue_scripts` callbacks gate on hook, shell, own screen or a queued `media-views`; Breakdance only with its frame flag. `vergeml_rtl_styles` only marks handles. Whole-input reads (`$_GET )`, `$_POST as`, `array_keys( $_`, `get_params()`, `get_json_params()`, `get_body_params()`): **0**. The redirect copies a literal key list (`admin-menu.php:199-211`). |
| 12 | fixed | **Confirmed** | The named lines are sanitized: `folder-talk.php:2877`, `:2882`; `import-csv.php:422`; `rest-folders.php:197`. `/folder` refuses a missing taxonomy (`rest-folders.php:95-98`). Class sweep of all 156 `get_param` / `$request[...]` reads: every free-text value that is stored or sent is `sanitize_text_field` / `sanitize_textarea_field`ed, either at the read or at storage (quarantine reason `quarantine.php:88`, brief turns `brief.php:698-711`, AI settings `ai.php:1993-2000`). Same shape left: `vergeml_can_assign()` still returns true for a missing taxonomy (`rest-tree.php:240-242`). The handler 404s it (`:566-568`), so it is acceptable. |
| 13 | fixed | **Confirmed** | All 13 `admin_init` callbacks either need a nonce and a capability (settings export/import/restore/cleanup/wipe-save, neighbour dismiss) or `manage_options` (librarian, index, organize housekeeping, AI revive, stats, connect router). The redirect callback only redirects. |
| 14 | fixed | **Confirmed (no variable left); delete the functions** | 0 `{$var}` in SQL other than `{$wpdb->core_table}`. The two fragment builders are dead code (LQ 5). |
| 15 | fixed | **Confirmed** | `readme.txt:57` names "whether the site is production or staging, and the brief". `:69` covers the brief conversation and the library summary. That matches the payload `ai.php:551-570` (`environment`, `profile`, `context`). |
| 16 | fixed | **Confirmed for the named strings; class not fully swept** | Gallery labels are localized (`gallery-block.php:53-59`) and set with `setAttribute`/`textContent` (`js/vergeml-gallery.js:129-143`). The transport strings are `__()`. No `languages/`, and no `Domain Path`. Left in the same class: `core/filing.php:3136` `new WP_Error( 'no_ai', 'AI not loaded.' )` (unreachable in practice; wrap it in `__()`). Localized strings concatenated into markup, the same pattern the lightbox fix removed: `js/eml-media-list.js:17`, `js/eml-media-grid.js:83`, `js/eml-enhanced-medialist.js:218`, `js/vergeml-media-views.js:460`. Low risk: these are translations. `esc_html__` on the PHP side closes it. |
| 17 | fixed | **Confirmed** | `js/vergeml-media-views.js` and `js/vergeml-taxonomies-options.js` have comments, named functions and no single-letter locals. 0 lines over 400 characters in `js/` or `css/`. |
| 18 | fixed | **Confirmed** | Excel branch: gone. `wp_check_filetype_and_ext` keeps only a font-extension branch, which still requires an allowed type (`mime-types.php:324-400`). `wp_cache_flush`: 0. api-fetch exclusion: 0 (`neighbours.php` excludes only the plugin's `js/`). Tree draggable rules are under `body.vgml-has-tree` (`vergeml-tree.css:1212-1213`). |
| 19 | fixed | **Confirmed** | Header Description is about 120 characters. Readme tags: 5. Wording is Nathan's. |
| 20 | fixed | **Confirmed** | `Copyright 2013-2024 wpUXsolutions  (email : wpUXsolutions@gmail.com)` (`vergelabs-media-library.php:18`). `api_version => 3` (`gallery-block.php:119`) and `apiVersion: 3` (`js/vergeml-gallery-block.js:210`). |
| 21 | Nathan's | Not in the zip; cannot be judged here | — |
| 22 | answered | **Would satisfy** | Credited in the readme (`:121`). Contributors may only list consenting wordpress.org accounts. |

**Gap in the ledger's own rule.** "Answered" is defined as "the sentence in the upload comment". No round-6 upload comment exists yet: `wordpress-org-submission.md` holds only round 5's. Rows 5, 6, 7, 8 and 22 need their sentences written before the zip is built.

---

## Checked and acceptable

- **Email classes, all still gone:**
  - trialware: every empty-licence branch sits directly before a `wp_remote_post`, and demo mode is local;
  - escaping;
  - enqueue: 0 `wp_add_inline_script`; 2 `wp_add_inline_style`, both `wp_strip_all_tags` + `sanitize_html_class`; the only `<script` tags are the 2 `text/html` templates;
  - nonces;
  - prefixes: classes, functions, options, meta, handles and the shortcode are all `vergeml`/`VergeML`;
  - REST: 77 routes, every permission callback a named function, library-wide work on `manage_options`, single objects on `edit_post` / `delete_post` / `edit_term` / `assign_terms`;
  - `load_plugin_textdomain`: 0;
  - deactivation: 0 `deactivate_plugins` / `activate_plugin(`;
  - `upload_mimes`: remove-only;
  - SQL: plugin tables `%i`, IN lists inline `implode( array_fill() )`;
  - activation: installers called by name, version stamped only after them, no output, no remote call;
  - inline JS: none.
- **Outgoing requests.** Only to `ai.vergelabs.nl`, `vergelabsmedia.com` and the site's own `wp-cron.php`. Each follows a button, a run the user started, a connected key's balance check, or the opt-in counts (off by default, `instrument.php:253`). All are disclosed. No remote assets in CSS/JS (0 `url(http`, 0 `@import`).
- **Uninstall.** Housekeeping clears all 7 cron hooks the plugin schedules (cross-checked against every `wp_schedule_*`). The wipe is opt-in and per site on a network.
- **Notices.**
  - The neighbour notice is scoped to the media and plugin screens and can be dismissed with a nonce.
  - The requirements and safe-mode notices appear only when there is something to report, and both say how to resolve it.
  - `vergeml_shell_quiet` removes only callbacks defined in the plugin's own files.
- **Filesystem.** No core load files. No hard-coded `wp-content`. No writes to the plugin folder: the ZIP and the image payload use `wp_tempnam`, and the CSV goes to `php://output`.
- **Query filters.** `posts_join` / `posts_search` / `posts_clauses` are admin- or attachment-scoped and use `prepare` / `esc_like`.
- **`js/vergeml-tree.js:1377-1379`.** It sets FileBird's `fbv` grid prop to "all" when the user clicks a folder in this plugin's tree. That is interop on a user action, not disabling FileBird.
- **`wp.media` prototype extension.** See row 6.
- **Licences and credits.** EML, the webbistro font, Inter (OFL), natural-compare-lite (MIT), WP Rocket layout and core spinners are all credited (`readme.txt:119-125`).

*Method: archive `%TEMP%\r7arch`; scripts in `%TEMP%\r7scratch` (`routes.js`: every `register_rest_route` with its methods and permission callback). Grep classes as listed in each row.*
