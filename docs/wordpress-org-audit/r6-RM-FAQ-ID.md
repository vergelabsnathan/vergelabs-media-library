# Round 6 audit: RM, FAQ and ID rows

**Archive judged:** `C:/Users/viete/AppData/Local/Temp/vgml-r6-archive`, 147 files, 3,648,452 bytes. Every file is byte-identical to commit `5dc983a` (each of the 147 compared with `git show 5dc983a:<path> | cmp`, 0 differ).

**Rows covered:** RM-1 to RM-52 and FAQ-1 to FAQ-168, 220 rows. No row in the rulebook has an `ID-` prefix. The 40 lines that start with `| ID |` are table headers, so the "ID" share is empty.

**Tools and runs** (scratch dir `C:/Users/viete/AppData/Local/Temp/vgml-r6-rmfaq/`):

- **Plugin Check 2.1.0 checks, run directly** in Playground (WP 7.1.3, PHP 8.3) through `Check_Context` / `Check_Result`: `Plugin_Readme_Check`, `Plugin_Header_Fields_Check`, `Trademarks_Check`, `File_Type_Check`, `Plugin_Updater_Check`, `Code_Obfuscation_Check`, `Direct_File_Access_Check` and `Minified_Files_Check`, plus its readme `Parser`. Result: `out/pc-result.json`.
  - **Positive control:** a mutated copy (`vgml-mut`) with `Stable tag: trunk`, `Tested up to: 9.9`, 7 tags, a WooCommerce name and an `extra.zip`. It raised `nonexistent_tested_upto_header`, `trunk_stable_tag`, `mismatched_plugin_name`, `readme_parser_warnings_too_many_tags`, `trademarked_term` ×2 and `compressed_files`. So these checks do report, and their silence on the archive counts as evidence.
  - **Not available here:** `Offloading_Files_Check` and `Localhost_Check` fail under php-wasm (PHPCS LOCK_EX), so they are not used as evidence.
- **Activation with `WP_DEBUG`** on WP 7.1.3 with PHP 7.4.33 and with PHP 8.5.10 (`act/`). A `trigger_error` control proves the log is written. Result: `act/act-74.json`, `act/act-85.json`.
- **Clean-site walk:** `node tools/clean-walk.mjs --ref 5dc983a` (the same tree as the archive) on WP 7.1.3 / PHP 8.5.10. It covers every screen as admin, editor and author, with `WP_DEBUG`, and logs outbound requests from an mu-plugin. Result: 68/68, 0 debug lines, 0 requests from the plugin. Log: `clean-walk.log`.
- **Overlap with Enhanced Media Library 2.9.4:** `overlap.js`, comparing against `research/enhanced-media-library.2.9.4`.
- **Live lookups** (2026-10-12):
  - `api.wordpress.org/core/version-check/1.7`: latest WordPress is 7.1.3.
  - `profiles.wordpress.org/vergelabsdev`: the profile exists.
  - The plugins API: Enhanced Media Library's contributor is `webbistro`, and tag counts.

Greps below ran over the archive root with `grep -rnE` (PHP/JS unless stated). "n hits" is the count.

## Table

| ID | Verdict | Evidence |
|---|---|---|
| RM-1 | PASS | `find . -maxdepth 1 -name readme.txt` → 1 hit, lowercase, at the root, 18,997 B, UTF-8 without BOM (`od -c` starts `= = =`). |
| RM-2 | PASS | Plugin Check 2.1.0 `Plugin_Readme_Check` on WP 7.1.3: 0 errors and 0 warnings. The control mutant raised 4, so the check is live. The parser's only warning is `contributor_ignored: [vergelabsdev]`. That is a local artifact: the parser resolves names with `get_user_by()` against the local site, and the mutant's fake user got the same warning. The profile exists on wordpress.org (RM-5). |
| RM-3 | PASS | Main file `vergelabs-media-library.php:7` `Requires at least: 6.5` and `:8` `Requires PHP: 7.4`. Both equal `readme.txt:4` and `:6`. |
| RM-4 | PASS | `grep -nE '<[a-zA-Z!/][^>]*>' readme.txt` → 0. `<!--` → 0. Sections use the Markdown form `## X ##`, which the parser accepts: it returned sections description, installation, faq, changelog and upgrade_notice. Subsections use `### X ###` and `= X =`. |
| RM-5 | PASS | `readme.txt:2` `Contributors: vergelabsdev`. https://profiles.wordpress.org/vergelabsdev/ exists (username `@vergelabsdev`, member since 2026-08-19). The case matches. |
| RM-6 | FAIL | Advisory: the rule says "generally considered respectful", so it is not a rejection reason. Enhanced Media Library's wordpress.org contributor is `webbistro` (plugins API). `Contributors:` lists only `vergelabsdev`. The original authors are credited in text instead: `readme.txt:19`, `:119`, header `:16-20`, `core/options-pages.php:3058`. See FAIL. |
| RM-7 | PASS | The one `Contributors` entry is a wordpress.org username, not a display name, email or company (RM-5). |
| RM-8 | N/A | `grep -c '^Donate link' readme.txt` → 0. The field is optional and absent. |
| RM-9 | PASS | `readme.txt:3` has 5 tags. The parser kept all 5. |
| RM-10 | PASS | The tags are media library, media folders, alt text, accessibility and media categories. None is a brand. One hit to note: "media categories" is also the exact name of the plugin `media-categories-2`, last updated 2012-12-18, 300 installs. Why it is acceptable: it is the generic name of the feature this plugin provides (its own taxonomy label is "Media Categories"), and 6 directory plugins share the tag, including EML and Media Library Organizer. Cheap hardening: swap it for `folders`. |
| RM-11 | PASS | Directory tag counts on 2026-10-12: media-library 371, accessibility 653, alt-text 221, media-folders 26, media-categories 6. None is unique to this plugin. |
| RM-12 | PASS | `Tested up to: 7.1`. The current WordPress is 7.1.3 (version-check API), so the major matches. |
| RM-13 | PASS | `7.1` matches `^\d+\.\d+$`. The parser kept it. |
| RM-14 | PASS | `Requires PHP: 7.4`, numeric. |
| RM-15 | PASS | `readme.txt:7` `Stable tag: 4.1.0` = header `:6` `Version: 4.1.0` = `define( 'VERGEML_VERSION', '4.1.0' )` at `:43`. |
| RM-16 | PASS | `4.1.0` matches `^[0-9.]+$`. |
| RM-17 | PASS | readme `License: GPLv2 or later` = header `:13` `License: GPLv2 or later`. |
| RM-18 | PASS | Present: readme `:9` (`.../gpl-2.0.html`) and header `:14` (`.../old-licenses/gpl-2.0.html`). Both are GPLv2 URIs. |
| RM-19 | PASS | `readme.txt:11` is 92 characters with no markup. The parser kept it unchanged. |
| RM-20 | PASS | 92 ≤ 150 (`readme-stats.js`). |
| RM-21 | PASS | `## Installation ##` at `:79-85` names the licence step (`:83`) and the EML switch (`:85`). |
| RM-22 | PASS | `## … ##` sections: 7. Non-standard: 1 (External services, `:51`), which the guidelines expect. |
| RM-23 | N/A | SVN step. No SVN repository exists before approval. |
| RM-24 | N/A | SVN step (`/tags/4.1.0/`). Not until after approval. |
| RM-25 | PASS | The tag folder will be this archive, and its `readme.txt:7` says `Stable tag: 4.1.0` = the tag name. |
| RM-26 | N/A | SVN step (fixes landing in the tag folder). Applies after approval. |
| RM-27 | PASS | The archive's header `Version: 4.1.0` = the tag name 4.1.0. |
| RM-28 | PASS | Same as RM-27. |
| RM-29 | PASS | `grep -ci 'stable tag: *trunk' readme.txt` → 0. The control proved `trunk_stable_tag` fires. |
| RM-30 | PASS | The last FAQ line (`:135`) is a list item, not a URL. `grep -ciE 'youtube\|vimeo' readme.txt` → 0. |
| RM-31 | **FAIL** | Free `vergelabs-media-library.php:4` `Plugin URI: https://vergelabsmedia.com`. Pro `pro/vergelabs-media-library-pro.php:4` has the same URI, on every Pro branch (`main`, `origin/main`, `story/e-min-free-gate`). See FAIL. |
| RM-32 | **FAIL** | `wc -c readme.txt` → 18,997 bytes, against the 10 KB in the rule. See FAIL. |
| RM-33 | PASS | `grep -noiE 'best\|ultimate\|powerful\|amazing\|revolutionary\|#1\|leading\|€\|\$[0-9]\|pricing\|discount\|premium\|\bpro\b\|buy\|price'` gives 5 hits, all acceptable: `:65` "a way to buy more" (trial email disclosure), `:129`/`:130` "Premium"/"Pro" (Yoast/Polylang editions in the compatibility log), `:149`/`:157` price shown on the button (changelog/upgrade notice). There is no pricing table. Description `:15-49` says what the plugin does and how. |
| RM-34 | PASS | Installation has 3 steps (`:81-83`). The 8 FAQ entries are real questions: multisite, outage, crash, uninstall, support, security, third-party code, versions checked. The crash answer (`:99-101`) matches the code: safe mode only via `VERGEML_SAFE_MODE` (`core/watchdog.php:20-22`), no automatic switch. |
| RM-35 | PASS | The readme changelog holds 4.1.0 only (`:153-176`). `:178` points to `changelog.txt`, which ships (31,533 B, newest first). |
| RM-36 | PASS | `grep -cE '!\['` → 0 images. Long documentation lives on the website (legal links `:75-77`, the Docs row meta). |
| RM-37 | PASS | Line 1 is `=== VergeLabs Media Library ===`, and `:2-9` carry Contributors, Tags, Requires at least, Tested up to, Requires PHP, Stable tag, License and License URI. Donate link is optional and absent. |
| RM-38 | PASS | Same as RM-19/RM-20. |
| RM-39 | PASS | `## Description ##` at `:13`. The parser's description is 11,182 B (Description plus External services, 1,858 words, under the parser's 2,500-word cut). |
| RM-40 | PASS | Description is present, so the short-description fallback is not used. |
| RM-41 | PASS | Same as RM-5. |
| RM-42 | PASS | Comma-separated and relevant (RM-9 to RM-11). |
| RM-43 | PASS | `Requires at least: 6.5`. I grepped 47 WP/PHP functions added after 6.5 or after PHP 7.4, including `wp_get_wp_version`, `wp_unique_id_from_values`, `wp_register_block_types_from_metadata_collection`, `wp_is_heic_image_mime_type`, `array_find`/`array_any`/`array_all`, `json_validate`, `wp_register_ability`, `wp_prime_network_option_caches` and `wp_is_valid_utf8`. Result: 0 hits. The only newer-than-PHP-7.4 function is `str_contains` (3 hits), which core has polyfilled since 5.9. No `Requires Plugins:` header. |
| RM-44 | PASS | Proven by running it on 7.1:<br>• The clean walk of `5dc983a` on WP 7.1.3 / PHP 8.5.10 passed 68/68 checks: every plugin screen as admin, editor denials, the media modal save, order saving.<br>• Activation on WP 7.1.3 with PHP 7.4.33 and with 8.5.10 is clean: 4 tables, `vergeml_version` 4.1.0.<br>See RUNTIME. |
| RM-45 | N/A | SVN tags exist only after approval. The value to use is 4.1.0. |
| RM-46 | N/A | SVN step. Applies after approval. |
| RM-47 | N/A | SVN step. Applies after approval. |
| RM-48 | PASS | `Stable tag:` line present (`:7`). |
| RM-49 | **FAIL** | Applies to the SVN assets, not the zip. There are 6 numbered captions (`:139-144`) and 6 files in the repo (`assets/screenshot-1..6.png`, 1280×800). But screenshots 3 and 5 show UI the plugin no longer has, and 0 hits for those strings in the archive: "The problem is resolved in the upcoming major update v3.0", "+ Add New MIME Type", "Restore WordPress Default MIME Types", "Advanced role management is coming", "Report a filetype". See FAIL. |
| RM-50 | PASS | The readme has one entry, the current 4.1.0. `changelog.txt` runs newest first: `:5` 4.1.0, `:32` 4.0.7, `:41` 4.0.6 … |
| RM-51 | PASS | Upgrade notice 4.1.0 is 139 characters (≤300). The parser read it under key `4.1.0`. |
| RM-52 | PASS | 9 links, all `[text](url)`. Bare URLs appear only in code spans (`:53`, `:65`, `:67`, `:69`) and the License URI header. |
| FAQ-1 | N/A | Process: the team's email SLA. |
| FAQ-2 | PASS | The archive is 3,648,452 bytes uncompressed, so any zip of it is under 10 MB. **Upload risk:** `wt/release-4.1.0/dist/vergelabs-media-library-4.1.0.zip` (zip comment `a47d6f0`, 154 entries) is the round-5 build, and 18 files in it differ from this archive. Build the upload zip from `5dc983a`. |
| FAQ-3 | PASS | `readme.txt` and `vergelabs-media-library.php` sit at the root. Header `:3` Plugin Name, `:4` Plugin URI, `:5` Description. The upload zip must wrap them in `vergelabs-media-library/` (`git archive --prefix=vergelabs-media-library/`). |
| FAQ-4 | PASS | 0 hits each: `console\.(log\|debug\|info\|warn\|error\|trace\|table\|dir)\(`, `var_dump\(`, `print_r\(`, `error_log\(`, `debug_print_backtrace\(`, `debug_backtrace\(`, `var_export\(`, `\bdebugger\b`, `trigger_error\(`, `wp_trigger_error\(`. 0 `.map`, `.scss`, `.less`, `.jsx`, `.ts` files. 0 files named test/spec/fixture. `VERGEML_AI_MOCK` (`core/ai.php:369`, `:584`) is the user-facing demo mode, disclosed at `readme.txt:55` and `core/licence-page.php:286`. One leftover: commented-out arguments at `core/options-pages.php:268-271`. That is not a log or a development tool. |
| FAQ-5 | PASS | `grep -rnoiE 'coming soon\|is coming\|under construction\|work in progress\|lorem ipsum\|not yet implemented'` → 0 user-facing hits. `\b(TODO\|FIXME\|XXX)\b` (case-insensitive) → 10 hits, all `$todo` variables in `core/journey.php` for the dashboard's to-do list. `\bbeta\b` → 1 hit, a comment about EML's old links (`vergelabs-media-library.php:1541`). `readme.txt:57` "in progress" describes a running job. |
| FAQ-6 | PASS | `find . -name '.*'` → 0. No `tests/`, `vendor/`, `node_modules/`, `docs/`, `Makefile`, `composer.*`, `package*.json`, `*.config.js` or `phpunit*`. Directories: `core css fonts images js languages`. `File_Type_Check` reported 0 (the control raised `compressed_files`). `SECURITY.md` is the only `.md`, a disclosure policy rather than development content. |
| FAQ-7 | N/A | Process: the team's approval criteria. |
| FAQ-8 | N/A | Process: review SLA. |
| FAQ-9 | N/A | Process: reply SLA. |
| FAQ-10 | N/A | Process: an open review is kept while progress is made. Review open since 26 Sep 2026. |
| FAQ-11 | N/A | Process: rejection after 3 months, which would be 2026-12-26. |
| FAQ-12 | N/A | Process: one submission at a time. `vergelabsdev` has 0 plugins listed (profile). |
| FAQ-13 | N/A | Process: no secondary accounts. |
| FAQ-14 | PASS | `readme.txt` is present (RM-1). |
| FAQ-15 | PASS | Proven by running it with `WP_DEBUG` on:<br>• Activation on WP 7.1.3 with PHP 7.4.33 and 8.5.10: 0 log lines, and the control `trigger_error` was written to the same log.<br>• Clean walk of `5dc983a` (WP 7.1.3, PHP 8.5.10): 68/68 checks over 21 admin screens and the modal and grid walks as admin, editor and author. "debug.log lines from this plugin: 0", "in all: 0".<br>Multisite is still open; see RUNTIME. |
| FAQ-16 | PASS | `ls js css \| grep -iE 'jquery\|underscore\|backbone\|lodash\|moment\|react\|select2\|sortable'` → 0. Library fingerprints (`jQuery.fn.jquery`, `Backbone.VERSION`, `_.VERSION`, `moment.version`) → 0. CDN or remote script/style URLs → 0. Dependencies are core handles (`jquery` ×8, `underscore` ×5, `media-views` ×6, `jquery-ui-draggable/droppable/dialog`, `wp-api-fetch`, `wp-element`, …). The one embedded third-party snippet is natural-compare-lite (MIT), inlined as a method at `js/eml-media-models.js:266-268` with its licence. It is not a library WordPress ships. |
| FAQ-17 | PASS | `wp_remote_(get\|post\|request\|head)\|wp_safe_remote_*\|download_url\|curl_\|fsockopen\|file_get_contents('http` → 21 calls. One is a loopback to the site's own `wp-cron.php` (`core/ai-background.php:220`). The other 20 go to `ai.vergelabs.nl` or `vergelabsmedia.com`, each inside a user action or behind a licence key: licence save/connect/trial (`core/ai.php:105`, `core/connect.php:161`, `:186`, `core/licence-page.php:55`, `core/offer.php:159`); credits refresh (`core/ai.php:166`, returns early with no key, `:157`); describe/brief/file/folders/name/plan/refund/embed (`core/ai.php:594`, `core/brief.php:420`, `core/filing.php:1794`, `:3152`, `core/folder-talk.php:458`, `:2346`, `core/guide.php:917`, `core/plan-tree.php:1042`, `:1098`, `core/search-meaning.php:132`, `:200`); help (`core/get-help.php:76` only when `$fetch`, `:260` on Send); opt-in counts (`core/instrument.php:233`). Each is disclosed in `readme.txt:51-77`. Runtime check: on the clean walk with no key, the outbound logger recorded 17 requests, 0 of them made by this plugin. All 17 came from core: update checks, dashboard feeds, events, patterns and `spawn_cron`. |
| FAQ-18 | PASS | `admin_footer_text\|update_footer\|Powered by` → 0. The front-end gallery output (`core/gallery-block.php`, `core/gallery-widgets.php`, `core/page-builders.php`, `js/vergeml-gallery*.js`) has 0 `https?://`. Attribution is only a sidebar box on the plugin's own settings screens (`core/options-pages.php:3043-3065`, called at `:625`, `:858`). |
| FAQ-19 | PASS | No phoning home. Library counts are opt-in, with `'opted' => 0` by default (`core/instrument.php:46`), sent only when opted in, by a `manage_options` user, outside AJAX (`:253-258`), and only with a licence key (`:228-231`). The one activation hook (`vergelabs-media-library.php:304`) has 0 `wp_remote_*` on its path. Cron events (10 `wp_schedule_single_event`) are booked only by runs the user starts. Runtime check: activation plus every screen on the clean walk made 0 requests from this plugin (FAQ-17). |
| FAQ-20 | PASS | The header Description (`:5`) and readme `:11-49` describe concrete features: folders as terms, filters, smart folders, importer, galleries, MIME settings, AI alt text. No placeholder words (FAQ-5). |
| FAQ-21 | PASS | `scrap(e\|er\|ing)\|spin(ner\|ning)\|paraphras\|article generat\|auto[_ -]?post\|keyword stuff` → 1 hit, a comment saying a loop "would spin" (`core/ai-background.php:428`). `wp_insert_post(` → 0, so the plugin creates no content. AI output is captions, alt text, tags and titles on the site's own attachments, only in runs the user starts. |
| FAQ-22 | PASS | 0 hits each: `eval(`, `shell_exec(`, `system(`, `passthru(`, `proc_open(`, `popen(`, `assert(`, `create_function(`, `/e` modifier, `new Function(`, string `setTimeout`/`setInterval`, `include/require` of a variable, `unserialize(`, theme/plugin editor. `exec(` → 1 hit, the JS `RegExp.exec` at `js/vergeml-talk.js:69`. `base64_decode(` → 2 hits, both data: the sealed licence key (`core/ai.php:355`) and packed vectors (`core/organize.php:2403`). The natural-language commands use a verb allowlist and never execute text (`core/nl-commands.php:15-44`). |
| FAQ-23 | PASS | 0 hits: `Plugin_Upgrader\|Theme_Upgrader\|WP_Upgrader\|install_plugin\|plugins_api\|unzip_file\|activate_plugin\|pre_set_site_transient_update_plugins\|site_transient_update_plugins\|Update URI`, dynamic `import(`, and `createElement('script')`. `Plugin_Updater_Check` reported 0. Browser fetches (`js/vergeml-talk.js:398`, `js/vergeml-transport.js:115`, `js/vergeml-offer-trial.js:21`, `js/vergeml-tree.js:1600`) read data, not code. |
| FAQ-24 | PASS | One plugin with a single `Plugin Name:`. No templates or boilerplate. `grep -ri boilerplate` → 0. |
| FAQ-25 | PASS | 1,090 global functions, all `vergeml_`. Classes: 6, all `VergeML_`/`vergeml_`. No namespaces needed. `define()` constants not prefixed `VERGEML_` → 0. Globals: `$vergeml_*` plus core's. Bundled third-party code: natural-compare-lite is scoped as `media.natCompare`, not a global. Non-prefixed option reads are EML's `wpuxss_eml_version` / `wpuxss_eml_network_options`, read-only, for migration (`vergelabs-media-library.php:471`, `:493`, `core/system-report.php:44`). |
| FAQ-26 | PASS | Overlap with Enhanced Media Library 2.9.4 (`overlap.js`, code lines of 25 characters or more, comments excluded, prefixes normalised): 1,834 of 27,040 lines (6.8%) match an EML line. EML-heavy files are the inherited core (`core/taxonomies.php` 312/493, `js/eml-media-grid.js` 194/221, …). The plugin is a credited fork (FAQ-122), not a copy. Media folders and categories are not a core feature. |
| FAQ-27 | PASS | `readme.txt:17` (folders as terms, AI alt text only on demand), `:27` (structure kept in WordPress, survives removal), `:39-43` (Librarian previews before acting; undo), `:35` (imports 7 folder plugins). |
| FAQ-28 | N/A | `vergelabsdev` owns no directory plugin (profile, 2026-10-12), so this is a new plugin, not a rebrand of the owner's existing one. If wpUXsolutions grants the pending EML adoption (sent 2026-09-25), this row applies and the answer becomes "update EML instead". |
| FAQ-29 | N/A | Process: files are replaced from the submission page. |
| FAQ-30 | N/A | Process: the slug can change once after submission. |
| FAQ-31 | PASS | Name "VergeLabs Media Library" (header `:3`, readme `:1`). No vulgarity. |
| FAQ-32 | PASS | Slug `vergelabs-media-library` (text domain `:11`, main file name). The regex `(^\|-)(wordpress\|plugin)(-\|$)` → 0. |
| FAQ-33 | PASS | `v?\d+(\.\d+)*` on the slug and on both name lines → 0. |
| FAQ-34 | PASS | The slug matches `^[a-z0-9-]+$`. |
| FAQ-35 | PASS | The name starts with the submitter's own brand (`Author: VergeLabs`, account `vergelabsdev`). `Trademarks_Check` reported 0 on header and readme (the control raised `trademarked_term` for "woocommerce" and "plugin"). |
| FAQ-36 | PASS | Same as FAQ-35. |
| FAQ-37 | PASS | No phonetic variant of a trademark. `Trademarks_Check` reported 0. |
| FAQ-38 | PASS | Same as FAQ-35: no term from the blocklist. |
| FAQ-39 | PASS | Same as FAQ-35, for both `Plugin Name:` lines. |
| FAQ-40 | PASS | Same as FAQ-37. |
| FAQ-41 | N/A | Process. Submitted as `vergelabsdev`, the brand account (submission doc `:45`). |
| FAQ-42 | N/A | Process: the account's email domain is not visible in the archive. |
| FAQ-43 | N/A | Process: same as FAQ-42. |
| FAQ-44 | N/A | Process: wrong-account fix. |
| FAQ-45 | N/A | Process: same as FAQ-44. |
| FAQ-46 | N/A | SVN layout. Applies after approval. |
| FAQ-47 | N/A | SVN step. The value will be 4.1.0. |
| FAQ-48 | N/A | SVN step. `assets/` holds banners, icons and screenshots ready for it (FAQ-76/77). |
| FAQ-49 | PASS | The archive (the future trunk) has the main file and `readme.txt` at its root, not in a subfolder. |
| FAQ-50 | PASS | Same as FAQ-49. |
| FAQ-51 | N/A | SVN tag naming, after approval. The planned tag `4.1.0` matches `[0-9.]+`. |
| FAQ-52 | N/A | Same as FAQ-51 (SemVer 4.1.0). |
| FAQ-53 | N/A | SVN housekeeping. |
| FAQ-54 | N/A | No SVN yet. The archive inlines everything and has no externals. |
| FAQ-55 | PASS | `find -iname '*.zip' -o '*.tar' -o '*.gz' -o '*.rar' -o '*.7z' -o '*.phar'` → 0. `File_Type_Check` reported 0 (the control fired on `extra.zip`). |
| FAQ-56 | PASS | `*.min.*` → 0. No JS or CSS line is over 400 characters. The highest average line length is 51 (`js/vergeml-media-views.js`). |
| FAQ-57 | PASS | Same as FAQ-56. `Minified_Files_Check` and `Code_Obfuscation_Check` reported 0. |
| FAQ-58 | N/A | Process: SVN pushes go live. |
| FAQ-59 | N/A | Process. |
| FAQ-60 | N/A | Informational. |
| FAQ-61 | PASS | Header `:3-14`: Plugin Name, Plugin URI, Description, Version, Requires at least, Requires PHP, Author, Author URI, Text Domain, Domain Path, License, License URI. `Plugin_Header_Fields_Check` reported 0 (the control fired `textdomain_mismatch`). |
| FAQ-62 | PASS | `Stable tag: 4.1.0` (`:7`). |
| FAQ-63 | PASS | Same as RM-29. |
| FAQ-64 | PASS | 7.1 is not above the current release 7.1.3. The control proved `nonexistent_tested_upto_header` fires for 9.9. |
| FAQ-65 | PASS | `7.1`, not `7`. |
| FAQ-66 | N/A | Process: readme-only updates after release. |
| FAQ-67 | PASS | Version 4.1.0 = Stable tag 4.1.0 (RM-15). |
| FAQ-68 | PASS | `## Changelog ##` at `:151`, pointing to the full history in `changelog.txt` (`:178`). |
| FAQ-69 | PASS | The readme keeps the current release only. Older ones are in `changelog.txt`. |
| FAQ-70 | PASS | Same as FAQ-69. |
| FAQ-71 | N/A | No video in the readme (RM-30 grep → 0). |
| FAQ-72 | PASS | Same as RM-12. |
| FAQ-73 | PASS | Major.minor `7.1`. |
| FAQ-74 | PASS | 7.1 is released (7.1.3 is current). |
| FAQ-75 | N/A | Process. |
| FAQ-76 | PASS | `assets/banner-772x250.png` (772×250) and `assets/banner-1544x500.png` (1544×500), measured from the PNG headers. For SVN `/assets` after approval. |
| FAQ-77 | PASS | `assets/icon-128x128.png` (128×128) and `assets/icon-256x256.png` (256×256). |
| FAQ-78 | PASS | The icon and banner use the plugin's own three-square mark. No third-party logo. Advisory on the banner's wording: see FAIL › Advisory. |
| FAQ-79 | PASS | Same as FAQ-78: original mark. |
| FAQ-80 | PASS | 5 tags. |
| FAQ-81 | PASS | 5 tags, none beyond the fifth. The control showed 7 tags triggering `too_many_tags`. |
| FAQ-82 | PASS | Same as RM-11: every tag is shared with 6 to 653 plugins. |
| FAQ-83 | N/A | Process: renaming after approval. |
| FAQ-84 | N/A | Same as FAQ-83. |
| FAQ-85 | PASS | Same as FAQ-35. |
| FAQ-86 | PASS | The slug does not begin with a third-party trademark. |
| FAQ-87 | PASS | The name does not lead with a third-party service. |
| FAQ-88 | PASS | No "Brand Product" pattern for another company's brand. |
| FAQ-89 | PASS | Same as FAQ-35. |
| FAQ-90 | N/A | Not a WooCommerce add-on. The slug does not start with `woo`. |
| FAQ-91 | N/A | Process. |
| FAQ-92 | PASS | The slug and name lead with the submitter's own brand. |
| FAQ-93 | N/A | Process: the slug is final (`vergelabs-media-library`, assigned at submission). |
| FAQ-94 | N/A | Process. |
| FAQ-95 | N/A | Process. |
| FAQ-96 | N/A | Process. |
| FAQ-97 | PASS | Header `:3` and readme `:1` are both "VergeLabs Media Library". Plugin Check raised no `mismatched_plugin_name` (the control did). |
| FAQ-98 | PASS | `grep -niE 'official\|™\|®'` on readme and header → 0. Not a vulgar name. Advisory on the banner: see FAIL. |
| FAQ-99 | N/A | Not a connector to someone else's service. The service is the author's own. |
| FAQ-100 | N/A | Not a multi-service combination. |
| FAQ-101 | PASS | Neither "WordPress" nor "Plugin" in the name. `Trademarks_Check` reported 0. |
| FAQ-102 | PASS | No ™ or ® (FAQ-98 grep). |
| FAQ-103 | N/A | Process. |
| FAQ-104 | N/A | Process. |
| FAQ-105 | N/A | Process. |
| FAQ-106 | PASS | "VergeLabs Media Library" is 3 words. |
| FAQ-107 | N/A | Process: sockpuppeting. |
| FAQ-108 | N/A | Process: closing a plugin. |
| FAQ-109 | N/A | Process. |
| FAQ-110 | N/A | Process. |
| FAQ-111 | N/A | Process. |
| FAQ-112 | N/A | Process. |
| FAQ-113 | N/A | Process. |
| FAQ-114 | N/A | Not a beta, and the readme does not say it is (FAQ-5). |
| FAQ-115 | N/A | Process. |
| FAQ-116 | N/A | Process. |
| FAQ-117 | N/A | Process. |
| FAQ-118 | N/A | Process. The readme does not say "deprecated" (`grep -ci deprecat readme.txt` → 0). |
| FAQ-119 | N/A | Process. |
| FAQ-120 | N/A | Process for reporters. If EML's author reported this plugin, the credits (FAQ-122) would class it as a credited fork. |
| FAQ-121 | PASS | Header `License: GPLv2 or later` (`:13`). The original copyright is intact: `Copyright 2013-2024 wpUXsolutions (original work)` (`:19`). Credited at `:16-17`, readme `:19`, `:119`. |
| FAQ-122 | PASS | Credits in the header (`:16-20`), the readme (`:19`, `:119-123`, naming EML, Inter, natural-compare-lite, WP Rocket's stylesheet and core spinners) and the settings sidebar (`core/options-pages.php:3058`). |
| FAQ-123 | N/A | Process. |
| FAQ-124 | N/A | Process. |
| FAQ-125 | N/A | Process. |
| FAQ-126 | N/A | Informational: generic names attract similar ones. |
| FAQ-127 | N/A | Process. |
| FAQ-128 | N/A | Process. The plugin's own security contact is at `readme.txt:113-115` and `SECURITY.md`. |
| FAQ-129 | N/A | Process. |
| FAQ-130 | N/A | Process. |
| FAQ-131 | N/A | Process. |
| FAQ-132 | N/A | Process. |
| FAQ-133 | N/A | Process. |
| FAQ-134 | N/A | Process. |
| FAQ-135 | N/A | Process. |
| FAQ-136 | N/A | Process. |
| FAQ-137 | N/A | Process. |
| FAQ-138 | N/A | Process. |
| FAQ-139 | N/A | Process. |
| FAQ-140 | N/A | Process. |
| FAQ-141 | N/A | Process. |
| FAQ-142 | N/A | Process. |
| FAQ-143 | N/A | Process. |
| FAQ-144 | N/A | Process: committers are added on the Advanced tab after approval. |
| FAQ-145 | PASS | `Contributors: vergelabsdev`, the developer account. |
| FAQ-146 | PASS | Only the developer account is listed, no supporters or testers. |
| FAQ-147 | N/A | Process. |
| FAQ-148 | N/A | Process. |
| FAQ-149 | N/A | Process. |
| FAQ-150 | N/A | Process. |
| FAQ-151 | N/A | Process. |
| FAQ-152 | N/A | Process. |
| FAQ-153 | N/A | Process. |
| FAQ-154 | N/A | Process. |
| FAQ-155 | N/A | Process. |
| FAQ-156 | N/A | Process. The EML adoption request has been pending since 2026-09-25. |
| FAQ-157 | N/A | Process (adoption steps). |
| FAQ-158 | N/A | Process. |
| FAQ-159 | N/A | Process. |
| FAQ-160 | N/A | Process. |
| FAQ-161 | N/A | Process. |
| FAQ-162 | N/A | Process. |
| FAQ-163 | N/A | Process. |
| FAQ-164 | N/A | Process. |
| FAQ-165 | N/A | Process. |
| FAQ-166 | N/A | Process. |
| FAQ-167 | N/A | Process. |
| FAQ-168 | N/A | Process. |

## FAIL

### RM-31: Plugin URI shared with the Pro plugin

- **Rule:** "The Plugin URI should be unique to each plugin. Do not use the same URI for your free and pro plugin."
- **Instances (the whole class):**
  - `vergelabs-media-library.php:4`: `Plugin URI: https://vergelabsmedia.com`
  - `pro/vergelabs-media-library-pro.php:4` (main, origin/main, story/e-min-free-gate): `Plugin URI: https://vergelabsmedia.com`
- **Fix:** give the two plugins different URIs. The cheapest option is to change the Pro header to a Pro page (for example `https://vergelabsmedia.com/pricing`), which does not touch the archive under review. If you change the free header instead, use a page that exists now, not the wordpress.org URL before approval. Make sure the free `Plugin URI` and `Author URI` (`https://vergelabs.nl`) stay different.

### RM-32: readme over 10 KB

- **Rule:** "having a file larger than 10k may result in errors. Your readme should be brief and to the point."
- **Instance:** `readme.txt`, 18,997 bytes. By section:
  - External services: 7,519 B
  - FAQ: 3,766 B
  - Description: 3,639 B
  - Changelog: 2,312 B
  - Screenshots: 562 B
  - Installation: 502 B
- **Severity:** the parser trims nothing (description 1,858 words against the 2,500 cut), and Plugin Check is silent. Review 6 rated this LIKELY-QUESTION, not a rejection.
- **Fix, if you choose to make it:**
  - Move the FAQ entry "Which versions … checked against?" (`:125-135`) and "What does it include from other projects?" (`:117-123`) to the website, keeping a one-line link.
  - Cut the 4.1.0 changelog to its headings, since `changelog.txt` has the long form.
  - Do not shorten External services below what the outbound audit requires (AGENTS.md policy).
  - Even then, about 8 KB of disclosure remains, so getting under 10 KB is not achievable without weakening it. Say so if asked.

### RM-49: screenshots 3 and 5 show removed features (SVN assets, post-approval)

- **Rule:** "This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Screenshots are stored in the /assets directory."
- **Instances:**
  - `assets/screenshot-3.png`. Caption `readme.txt:141`. The image shows "Disable this if it slows down your site admin. The problem is resolved in the upcoming major update v3.0". `grep -rn "upcoming major\|v3\.0"` over the archive → 0.
  - `assets/screenshot-5.png`. Caption `readme.txt:143`. The image shows "+ Add New MIME Type", "Restore WordPress Default MIME Types", "Advanced role management is coming." and "Report a filetype". Each string → 0 hits in the archive. Adding MIME types was a round-2/3 rejection class, and the 4.1.0 changelog (`:169`) says the screen "no longer adds new types". This image would advertise exactly what was removed.
  - `assets/screenshot-1, -2, -4, -6`: EML-era screens from 2026-09-02, before the 09-04 admin redesign. None shows the folder tree, the Folders screen or the AI screen that the Description leads with. That is not a rule breach, but the captions describe only inherited settings.
- **Fix:** before the first commit to SVN `/assets`, re-shoot 3 and 5 on the 4.1.0 build. Better: replace the set with the current screens (tree in the grid, Folders screen, AI screen, MIME screen as remove-only) and rewrite `readme.txt:139-144` to match. That is a readme-only change and can land in trunk and `tags/4.1.0` together (RM-26).

### RM-6: original authors not in Contributors (advisory)

- **Rule:** "It is generally considered respectful to include the names of people who worked on forked projects. Some developers will ask to be removed from the list … It's best to honor those requests."
- **Instance:** `readme.txt:2` `Contributors: vergelabsdev`. Enhanced Media Library's wordpress.org author is `webbistro` (plugins API, 2026-10-12).
- **Fix:** this is a decision, not a defect, and five rounds never raised it. Either:
  - (a) add `webbistro` (`Contributors: vergelabsdev, webbistro`). This lists them on this plugin's page and their profile, and `readme.txt:19` already says the plugin is not made by or affiliated with them, so check first with the pending adoption ticket; or
  - (b) keep the text credits (`readme.txt:19`, `:119`; header `:16-20`; settings sidebar) and be ready to say why if asked: the original author has not been asked about being listed, and the adoption ticket is open.

### Advisory (assets and upload, not a rule FAIL)

- **Banner wording.** `assets/banner-772x250.png` reads "Folders, filters and AI alt text — the maintained successor to Enhanced Media Library. Built for WordPress 7 and PHP 8."
  - "The maintained successor" reads as an official succession that does not exist (adoption pending; `readme.txt:19` says not affiliated). That invites the brand-confusion complaint FAQ-89 describes.
  - "PHP 8" conflicts with `Requires PHP: 7.4`.
  - **Fix:** drop the successor clause, for example "Folders, filters and AI alt text for the WordPress media library." Do the same for `banner-1544x500.png` if it carries the text.
- **Stale zip in `dist/`.** `wt/release-4.1.0/dist/vergelabs-media-library-4.1.0.zip` is the round-5 build (zip comment `a47d6f0`), and 18 of its files differ from this archive, including `readme.txt` and the main file.
  - **Fix:** build the upload from `5dc983a` (`git archive 5dc983a --prefix=vergelabs-media-library/ -o …zip`) and check the zip comment before uploading.

## RUNTIME

No row is left at RUNTIME. I ran the three runs below this session, and they moved FAQ-15, FAQ-17, FAQ-19 and RM-44 to PASS. Two runs are still not done; each would add evidence but none is needed for a verdict.

**Runs done**

- **Activation with `WP_DEBUG`** (`act/act-74.json`, `act/act-85.json`, blueprint `act/bp-7.4.json` and `act/bp-8.5.json`):
  - WP 7.1.3 with PHP 7.4.33 and with 8.5.10, `WP_DEBUG` + `WP_DEBUG_LOG` on, through Playground's `activatePlugin` step.
  - The plugin is active and `vergeml_version` = 4.1.0.
  - Tables `wp_vergeml_ai_index`, `wp_vergeml_librarian_batches`, `wp_vergeml_librarian_moves` and `wp_vergeml_organize_runs` exist.
  - The debug log stays absent through activation and one load. The control `trigger_error` was then written to `/wordpress/wp-content/debug.log`, so logging was live.
- **Clean-site walk** (`node tools/clean-walk.mjs --ref 5dc983a`, log `C:/Users/viete/AppData/Local/Temp/vgml-r6-rmfaq/clean-walk.log`, artefacts `…/vgml-walk-VB5HG5/out`):
  - WP 7.1.3, PHP 8.5.10. `checks: 68/68 as expected`.
  - 21 screens open as admin. The raised screens answer 403 to the editor and the author.
  - Core screens load none of the plugin's files.
  - The folder tick saves through core's `save-attachment-compat`. The order saves through `vergeml-save-attachment-order`.
  - No console error in the modal walks.
  - `debug.log lines from this plugin: 0`, `in all: 0`.
  - Caveat: this log's path had no control of its own. The activation runs above prove that `WP_DEBUG_LOG` writes in this Playground build.
  - The walk's server was gone afterwards: port 8908 shows TIME_WAIT only, nothing listening.
- **Outgoing requests** (same walk; an mu-plugin logs `pre_http_request` with a backtrace):
  - 17 requests in all, 0 made by this plugin.
  - Core made all 17: core/plugin/theme update checks, browse-happy, serve-happy, events, news/planet feeds, patterns, and 5 `spawn_cron` loopbacks.

**Not run yet**

- **Network Activate with `WP_DEBUG` (FAQ-15, multisite):**
  - The run: the same activation blueprint with `{"step":"enableMultisite"}` before activating network-wide, then a second site created with `wp_initialize_site`.
  - It would prove: 0 log lines, and four `wp_N_vergeml_*` tables per site.
- **Clean walk on PHP 7.4 (FAQ-15, RM-44):**
  - The run: the clean walk with the Playground CLI's `--php=7.4`.
  - Activation on 7.4 is already proven clean; this would extend that to the screens.
