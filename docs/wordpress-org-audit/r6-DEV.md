# Round 6 audit, DEV rows: VergeLabs Media Library 4.1.0

**Archive judged:** `C:/Users/viete/AppData/Local/Temp/vgml-r6-archive`. 147 files: 68 PHP, 43 JS, 22 CSS, 3 txt, 1 md, 1 pot, 1 xml, 6 font, 2 gif. `diff -rq` against `git archive 5dc983a` (HEAD, 2026-10-12 12:00) reports no difference. Paths below are relative to the archive root.

**Rulebook:** `docs/wordpress-org-rulebook.md`, rows DEV-1 to DEV-269. Every row has a verdict below.

**Method.** I read no `phpcs:ignore` or `phpcs:disable` comment as evidence. The scanners read the code itself. They live in `C:/Users/viete/AppData/Local/Temp/vgml-r6-devaudit/scripts/`:

| Scanner | What it does |
|---|---|
| `newapi.js` | Diffs WP 6.5.11 against 7.1.3 (Playground's cached zips) and finds plugin uses of anything newer than 6.5 |
| `i18n.js` | Parses the text-domain argument of every gettext call |
| `superglobals.js` | Classifies every `$_GET`/`$_POST`/`$_REQUEST`/`$_FILES`/`$_SERVER` read by its wrapper |
| `fninput.js` | For every function that reads input, says whether it checks a nonce and a capability |
| `fnbody.js` | Extracts a function body |
| `rest.js` | Lists every `register_rest_route` with its `permission_callback` |
| `wpdb.js`, `prepvars.js` | Classify every `$wpdb` query by its first argument, and find variables inside `prepare()` strings |
| `echo-scan.php` | Classifies each output statement with `token_get_all` (run in php-wasm 8.3) |
| `safemode.js` | Finds always-loaded code that calls functions which exist only in feature files |
| `probe.js`, `probe2.js` | Fetch live facts from wordpress.org and the header URLs |

The PHP syntax check is `tools/phpcs.mjs --ref 5dc983a --parse-only`, run in php-wasm 7.4.33 and 8.3.33.

**Counts:** PASS 79 · FAIL 2 · RUNTIME 4 · N/A 184 (269 rows). DEV-201 is FAIL, and it also owes the runtime proof listed under RUNTIME.

## Verdicts

| ID | Verdict | Evidence |
|---|---|---|
| DEV-1 | PASS | `grep -rln "Plugin Name:"` over the whole archive gives 1 hit: `vergelabs-media-library.php`, at the root. The header is a `/* … */` block at `:2-27`. |
| DEV-2 | PASS | `:3` reads `Plugin Name: VergeLabs Media Library`. I scanned the first 8 KB the way `get_file_data()` does, case-insensitive and anchored. It finds 12 header fields, each once, at `:3-14`. |
| DEV-3 | PASS | Matches `readme.txt:1` `=== VergeLabs Media Library ===`. |
| DEV-4 | PASS | `:4` is `Plugin URI: https://vergelabsmedia.com`. `probe.js` gets 200 with the title "VergeLabs Media Library", the plugin's own site. |
| DEV-5 | PASS | No `wordpress.org` in the Plugin URI. It differs from `Author URI: https://vergelabs.nl` (`:10`). The `wordpress.org` URL at `:17` is free text in the comment: the header scan matched no field on it. |
| DEV-6 | **FAIL** | The `Description:` at `:5` is **177 characters** (`wc -m`). The rule says fewer than 140. It has been unchanged since `ddf38c4` (2026-10-04). Plugin Check 2.1.0 (`Plugin_Header_Fields_Check.php:171-199`) checks only for an empty or boilerplate value, so no automated run has caught this. See FAIL 1. |
| DEV-7 | PASS | `Version: 4.1.0` (`:6`), `Stable tag: 4.1.0` (`readme.txt:7`) and `define( 'VERGEML_VERSION', '4.1.0' )` (`:43`) all agree. A grep for quoted `4.x.y` literals in PHP/JS gives 2 hits: `:43`, and `core/compatibility.php:67` `'4.8.4'`, which is the Enfold theme's version in a `version_compare`. The SVN tag does not exist until approval. |
| DEV-8 | PASS | `Requires at least: 6.5` (`:7`, and `readme.txt:4`). `newapi.js` finds 349 global functions and 227 classes that exist in WP 7.1.3 but not in 6.5.11. The plugin's PHP calls **0** of them. The script's 207 raw hits are all string keys, SQL or comments that collide with generic vendor class names (`caption`, `source`, `message`, `date`, `mysql`). The JS uses only APIs that 6.5 has: `wp.element.createElement/useState/useEffect/Fragment`, `wp.blockEditor.useBlockProps/InspectorControls`, `wp.components.*`, `wp.blocks.registerBlockType`, `wp.i18n.*`. Script dependencies such as `wp-server-side-render`, `wp-block-editor` and `media-views` are all in 6.5. No `react-jsx-runtime` (a 6.6 handle) and no ES modules. |
| DEV-9 | PASS | `Requires PHP: 7.4` (`:8`, and `readme.txt:6`). The parse-only run reports `parse: 68 files, 0 with a syntax error, PHP 7.4.33` (and the same on 8.3.33). PHP 8-only functions: `str_contains` ×3 (`core/medialist.php:57`, `core/mime-types.php:387`, `core/taxonomies.php:494`), which WP 6.5 polyfills at `wp-includes/compat.php:468`, and `array_key_first` ×2, which is PHP 7.3. 0 attributes, `match`, `?->`, `ValueError`, `WeakMap` or `Stringable`. mbstring is checked before use (`vergelabs-media-library.php:1581-1599`). |
| DEV-10 | PASS | `:9` `Author: VergeLabs`. |
| DEV-11 | PASS | `:10` `https://vergelabs.nl` answers 200 (it redirects to www.vergelabs.nl). |
| DEV-12 | PASS | `:13` `License: GPLv2 or later` matches `readme.txt:8`. |
| DEV-13 | PASS | `:14` `http://www.gnu.org/licenses/old-licenses/gpl-2.0.html` answers 200, "GNU General Public License v2.0". `readme.txt:9` uses `…/licenses/gpl-2.0.html`, which redirects to the same page. Cosmetic: the two URLs differ and both use http. |
| DEV-14 | PASS | `Text Domain: vergelabs-media-library` (`:11`) is the slug: the main file's name and the slug in the review IDs. `i18n.js` parsed 1,837 PHP and 277 JS gettext calls. All **2,114** pass the literal `'vergelabs-media-library'`; 0 have no domain, a variable domain or another domain. The 4 JS files that call `__()` each get `wp_set_script_translations( …, 'vergelabs-media-library' )`: `core/ai-screen.php:78`, `core/brief.php:757`, `core/guide.php:89`, `:139`. |
| DEV-15 | PASS | `Domain Path: /languages` (`:12`). The folder exists and holds `languages/vergelabs-media-library.pot`. The `.pot` header is stale (advisory A4). |
| DEV-16 | PASS | No `Network:` line (8 KB header scan). The plugin activates per site and network-wide alike (`:304`, `:318-320`, `wp_initialize_site` `:406`, `wpmu_drop_tables` `:424`). |
| DEV-17 | PASS | `Network:` is absent, never `false`. |
| DEV-18 | PASS | No `Update URI:`. A grep for `update_plugins_`, `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `plugins_api`, `upgrader_`, `auto_update_plugin`, `Plugin_Upgrader`, `WP_Upgrader`, `download_url(`, `install_plugin`, `activate_plugin(` and `unzip_file` over PHP and JS returns 0 hits. |
| DEV-19 | N/A | No `Requires Plugins:` header. |
| DEV-20 | N/A | No `Requires Plugins:` header. |
| DEV-21 | N/A | No `Requires Plugins:` header. |
| DEV-22 | N/A | The header opens with `/*` (`:2`). No PHPDoc DocBlock precedes it. |
| DEV-23 | PASS | `changelog.txt` has 24 version headings. Under numeric comparison they strictly descend: 4.1.0 > 4.0.7 > … > 3.7.0. This is the first wordpress.org release, so no earlier SVN tag exists to compare with. |
| DEV-24 | PASS | `grep -iE "complian\|compliant\|GDPR\|WCAG\|HIPAA\|PCI\|CCPA\|VAT\|guarantee\|section 508\|EAA\|accessibility act\|legally\|AVG"` over php/js/txt/md/pot/xml gives 6 hits. Five are code comments: `core/folder-talk.php:63`, `:1745`, `core/tree-ui.php:449`, `js/vergeml-tree.js:1208`, `:2504`. The sixth is `core/offer.php:82` "VAT at checkout", a price statement. `grep -w ADA` gives 0. |
| DEV-25 | PASS | Same grep. The readme tag `accessibility` (`readme.txt:3`) and "alt text written for you" (`:15`) name a feature. Neither claims compliance. |
| DEV-26 | PASS | `grep "100 *%"` over readme, changelog, SECURITY.md and gettext lines gives 0. |
| DEV-27 | PASS | There is no compliance claim, so responsibility is never shifted to the plugin. |
| DEV-28 | N/A | No compliance wording to reword. |
| DEV-29 | PASS | Readme and `Description:` contain no compliance wording (DEV-24 grep). Banners and screenshots are SVN-only and outside the zip. See advisory A1 for the banner's "successor" line. |
| DEV-30 | PASS | `grep -iE "will make your (website\|site).*compliant\|makes your site compliant"` gives 0. |
| DEV-31 | N/A | No compliance claim, so no "no plugin can provide 100%" note is needed. |
| DEV-32 | PASS | "VergeLabs Media Library" claims no compliance. |
| DEV-33 | PASS | The name is brand-prefixed ("VergeLabs"). |
| DEV-34 | N/A | Neither the plugin nor the service claims compliance. The readme links the service's sub-processors, retention, DPA, terms and privacy pages (`readme.txt:75-77`). |
| DEV-35 | N/A | No compliance claim to prove. |
| DEV-36 | PASS | No third-party compliance claim is repeated (DEV-24 grep). |
| DEV-37 | PASS | `grep -iE "100% (XHTML\|W3C\|valid)"` gives 0. |
| DEV-38 | N/A | Process: answer a claims warning within 60 days. |
| DEV-39 | N/A | Process: reopening a closed plugin. |
| DEV-40 | N/A | Process: reporting other plugins. |
| DEV-41 | PASS | The plugin's own readme and strings are audited in DEV-24 to DEV-37. |
| DEV-42 | PASS | The whole archive is the "diff", since this is the first SVN release. **Dangerous calls:** `eval(`, `assert(`, `create_function(`, `unserialize(`, the exec family, `extract(`, `$$` and include/require of a variable: 0 each. `base64_decode` ×2 (DEV-45). **Input:** 202 superglobal reads. 192 are wrapped on their own statement: 100 `isset`, 10 `empty`, 5 `absint`, 4 `max(0,(int))`, 10 `(int)` casts, and 63 `wp_unslash` inside `sanitize_text_field` ×30, `sanitize_key` ×28, `map_deep(…,'sanitize_text_field'/'intval')` ×5, `sanitize_email` ×2, `sanitize_textarea_field`, `sanitize_mime_type` and `sanitize_file_name`. I read the other 10 by hand. All are `map_deep` sanitizers, `is_string`/`is_array` type tests, whitelisted keys (`core/admin-menu.php:196-205`), or a string literal that only looks like `$_GET` (`vergelabs-media-library.php:870`). **Output:** `echo-scan.php` (token_get_all, php 8.3) classifies 268 echo/print/`<?=`/printf/wp_die/exit/_e statements. 4 are flagged and all 4 are acceptable. Three are ternaries with both branches escaped (`core/admin-shell.php:740`, `core/journey.php:1311`, `core/options-pages.php:1146`). The fourth is `core/options-pages.php:1243` `echo wp_json_encode( $settings )`, a JSON attachment download that WPCS lists as auto-escaped (`EscapingFunctionsTrait.php:92`). No `_e(`/`_ex(` and no debug output. The CSV goes out through `file_put_contents( 'php://output', … )` (`core/import-csv.php:228`) as `text/csv` with `nosniff` and an attachment header (`:205-209`). **SQL:** 207 query/get_* calls. 187 have `$wpdb->prepare(` as their first argument. The 20 that do not are literal SQL whose only interpolation is a core table property (`{$wpdb->posts}`, `{$wpdb->options}` and so on), for example `core/ai-screen.php:117` and `uninstall.php:126`. `prepvars.js` checks all 198 `prepare()` calls: none carries a variable other than `$wpdb->table` or an `array_fill( …, '%d' )` placeholder list. **REST:** 77 routes, 0 `__return_true` (DEV-43). |
| DEV-43 | PASS | `nopriv`: 0 hits. There are 16 `wp_ajax_`/`admin_post_` handlers (`grep -rnE "add_action\(\s*'(wp_ajax_\|admin_post_)"`). 15 check a nonce **and** a capability: `core/folder-tools.php:229/233`, `core/get-help.php:196/200`, `core/import-csv.php:185/189`, `core/journey.php:410/414`, `:427/431`, `:1082/1086`, `:1162/1166`, `core/licence-page.php:37/40`, `core/offer.php:147/148`, `core/options-pages.php:884/887`, `core/rename.php:349/353`, `:448/452`, `core/taxonomies.php:1353/1355`, `:1415/1417` and `:1421/1433`, and `core/transport.php:46/51` (the `wp_rest` nonce plus `upload_files`, then `rest_do_request` applies each route's own callback). The 16th, `vergeml_builder_load_tree` (`core/page-builders.php:44-82`, hooked on Themify's `wp_ajax_tb_load_editor`), checks `upload_files` and only enqueues assets. `fninput.js` finds every other input-reading function with a state change carrying both checks (`core/options-pages.php` handlers at `:1027`, `:1161`, `:1214`, `:1259`, `:1361`, `:1414`, `:1438`; `core/bulk-terms.php:144`; `core/media-list.php:665`; `core/neighbours.php:123`; `core/search-meaning.php:730`; `core/folder-tools.php:33`). The connect callback (`core/connect.php:96-210`) checks `manage_options` and a single-use per-user state compared with `hash_equals`. `rest.js`: all 77 routes have a real capability callback. 64 use `manage_options`; the rest use `upload_files`, `edit_post` per file, `manage_terms`/`assign_terms` per taxonomy, or `delete_post` per dropped file (`core/health-delete.php:582-601`). |
| DEV-44 | N/A | Process: the cooldown starts after release. |
| DEV-45 | PASS | 0 each for `eval(`, `assert(`, `create_function(`, `preg_replace` with `/e`, `str_rot13`/`gzinflate`/`gzuncompress`, `exec`/`shell_exec`/`system`/`passthru`/`proc_open`/`popen`, `extract(`, `move_uploaded_file(` and `unserialize(`. `base64_decode` ×2: `core/ai.php:355` (the plugin's own sealed licence key) and `core/organize.php:2403` (its own packed floats). `maybe_unserialize` ×2: `core/health.php:932` reads attachment meta from the database, and `core/health-delete.php:208` writes back meta rows read with `SELECT … FROM postmeta` (`core/health-delete.php:395-417`, `core/health-keep.php:425-441`, `:745-756`). Neither takes user input. `file_put_contents` ×1 is `php://output` (DEV-42). In JS, `eval(`, `new Function(` and string timers: 0. External `<script src>` and remote enqueues: 0. |
| DEV-46 | N/A | Process: no email means no action. |
| DEV-47 | N/A | Process: committers watch their inboxes. |
| DEV-48 | PASS | Same sweep as DEV-42 and DEV-43: no unescaped output, unsanitized input, missing nonce or capability, or SQL outside `prepare()` that carries a variable. |
| DEV-49 | N/A | Process: fix each finding from a block email. |
| DEV-50 | N/A | Process. |
| DEV-51 | N/A | Process. |
| DEV-52 | N/A | Process. |
| DEV-53 | N/A | Process. |
| DEV-54 | N/A | Process. |
| DEV-55 | N/A | Process. |
| DEV-56 | N/A | No block exists. A fix release would be 4.1.1. |
| DEV-57 | N/A | Process. |
| DEV-58 | N/A | Process. |
| DEV-59 | N/A | Process. |
| DEV-60 | N/A | Process. |
| DEV-61 | N/A | Process: re-run the full review on any fix release. |
| DEV-62 | N/A | Process. |
| DEV-63 | PASS | `find . -iname "banner-*" -o -iname "icon-*" -o -iname "screenshot-*" -o -iname "blueprint*.json" -o -iname icon.svg` gives 0 in the archive. |
| DEV-64 | PASS | `find . -type d -iname assets` gives 0. |
| DEV-65 | N/A | Process: CDN delay. |
| DEV-66 | N/A | SVN `/assets/` is not part of the upload. Advisory A2: the repo's `assets/` files all match their names. |
| DEV-67 | N/A | SVN asset. Advisory A2: `banner-772x250.png` is 772×250. |
| DEV-68 | N/A | SVN asset. Advisory A2: `icon-256x256.png` is 256×256. |
| DEV-69 | N/A | SVN asset. Advisory A2: only `banner-772x250`/`banner-1544x500`. |
| DEV-70 | N/A | SVN asset. Advisory A2. |
| DEV-71 | N/A | SVN asset, visual RTL check. |
| DEV-72 | N/A | Optional RTL banner; none exists. |
| DEV-73 | N/A | SVN asset. Advisory A2: `banner-772x250.png` exists. |
| DEV-74 | N/A | No localized banners. |
| DEV-75 | N/A | SVN asset. Advisory A2: `banner-1544x500.png` is 1544×500. |
| DEV-76 | N/A | No localized retina banners. |
| DEV-77 | N/A | No locale suffixes. |
| DEV-78 | N/A | No RTL or locale copies. |
| DEV-79 | N/A | SVN asset. Advisory A2: the 772×250 companion exists. |
| DEV-80 | N/A | SVN asset. Advisory A2: banners are 15.7 KB and 41.1 KB. |
| DEV-81 | N/A | SVN asset. Advisory A2: PNG icons. |
| DEV-82 | N/A | No `icon.svg`. |
| DEV-83 | N/A | Icons exist (advisory A2). |
| DEV-84 | N/A | SVN asset. Advisory A2: icons are 1.7 KB and 3.3 KB. |
| DEV-85 | N/A | SVN asset. Advisory A2: `icon-128x128.png` is 128×128. |
| DEV-86 | N/A | SVN asset. Advisory A2: 256×256. |
| DEV-87 | N/A | No SVG icon. |
| DEV-88 | N/A | Screenshots are SVN-only. The readme side has 6 caption lines (`readme.txt:139-144`). Advisory A2: the repo's `assets/` has `screenshot-1..6.png`, so 6 = 6. |
| DEV-89 | PASS | `readme.txt:139-144` are `1.` … `6.` caption lines. |
| DEV-90 | PASS | `## Screenshots ##` (`readme.txt:137-144`) holds 0 `http`. |
| DEV-91 | N/A | SVN asset. Advisory A2: screenshots are 41-65 KB. |
| DEV-92 | N/A | SVN asset. Advisory A2: `screenshot-N.png`. |
| DEV-93 | N/A | SVN asset. Advisory A2: all names lowercase. |
| DEV-94 | N/A | No localized screenshots. |
| DEV-95 | N/A | Process: `svn propget svn:mime-type` at commit. |
| DEV-96 | N/A | Process: `svn propset` at commit. |
| DEV-97 | N/A | Process: subversion auto-props. |
| DEV-98 | N/A | Process: Release Management opt-in. |
| DEV-99 | N/A | Process. |
| DEV-100 | N/A | Process. |
| DEV-101 | N/A | Process. |
| DEV-102 | PASS | `Stable tag: 4.1.0` (`readme.txt:7`), not `trunk`. |
| DEV-103 | PASS | Stable tag 4.1.0 = `Version: 4.1.0`. |
| DEV-104 | PASS | Archive side: `Tested up to: 7.1` (`readme.txt:5`). The api.wordpress.org version check reports 7.1.3 as the latest release, so the readme is final before tagging. The rest of the row is process. |
| DEV-105 | N/A | Process: committer email. |
| DEV-106 | N/A | Process. |
| DEV-107 | N/A | Process. |
| DEV-108 | N/A | Process: SVN commit hygiene. |
| DEV-109 | N/A | SVN tags exist only after approval. |
| DEV-110 | N/A | Process. |
| DEV-111 | N/A | Process. |
| DEV-112 | N/A | Process. |
| DEV-113 | PASS | 0 banner, icon or screenshot files in the zip (DEV-63). |
| DEV-114 | N/A | Process. |
| DEV-115 | N/A | Process. |
| DEV-116 | PASS | The main file sits at the archive root. The root PHP files are `index.php`, `uninstall.php` and `vergelabs-media-library.php`, with no nested plugin folder. The zip's single top folder is checked under RUNTIME DEV-211. |
| DEV-117 | N/A | Process: what goes into trunk. |
| DEV-118 | N/A | Process. |
| DEV-119 | PASS | `Version:` 4.1.0 = `Stable tag:` 4.1.0. The tag name is set at commit. |
| DEV-120 | PASS | `Stable tag` is not `trunk`. |
| DEV-121 | PASS | The version string is bare `4.1.0` with no `v`, so the tag will be `/tags/4.1.0`. |
| DEV-122 | PASS | `4.1.0` is MAJOR.MINOR.PATCH. |
| DEV-123 | PASS | 0 `screenshot-*` files in the zip. |
| DEV-124 | N/A | Process. |
| DEV-125 | N/A | Process. |
| DEV-126 | N/A | Process. |
| DEV-127 | N/A | Process. |
| DEV-128 | PASS | `Stable tag` is not `trunk`. |
| DEV-129 | PASS | Changelog versions strictly increase (DEV-23). |
| DEV-130 | N/A | Process: `svn cp trunk tags/4.1.0`. |
| DEV-131 | N/A | Process. |
| DEV-132 | N/A | Process. |
| DEV-133 | N/A | Process. |
| DEV-134 | N/A | Process. |
| DEV-135 | N/A | The readme changelog holds only 4.1.0, which will be the first tag. Earlier versions in `changelog.txt` were GitHub releases, not wordpress.org ones. |
| DEV-136 | PASS | `4.1.0` matches `^[0-9]+(\.[0-9]+)*$`. |
| DEV-137 | N/A | Process. |
| DEV-138 | PASS | `Stable tag` 4.1.0 = the newest version. |
| DEV-139 | PASS | `find` for `.git*`, `.github`, `node_modules`, `tests`/`test`, `phpunit.xml*`, `.env*`, `.DS_Store`, `composer.*`, `package*.json`, `vendor`, `*.map`, `*.log`, `*.sh`, `*.mjs`, `*.ts`, `*.bak`, `*.orig` and any dotfile gives 0. By extension: 68 php, 43 js, 22 css, 3 txt (readme, changelog, INTER-LICENSE), 1 md (`SECURITY.md`), 1 pot, 1 xml (`wpml-config.xml`), 6 font files, 2 gif. All are runtime or documentation files. |
| DEV-140 | PASS | 0 `*.zip`, `*.tar*` or `*.gz`. |
| DEV-141 | N/A | Block Directory only; this is not a Block Plugin (DEV-148). |
| DEV-142 | N/A | Block Directory only. Checked anyway: the block's render callback escapes every part (`core/gallery-block.php:241-339`: `esc_attr`, `esc_url`, `wp_kses`, `wp_kses_post`, `get_block_wrapper_attributes`). |
| DEV-143 | N/A | Block Directory only (DEV-148). |
| DEV-144 | N/A | Block Directory only. The block registers on init (`core/gallery-block.php:111`). |
| DEV-145 | N/A | Block Directory only. |
| DEV-146 | N/A | Block Directory only. |
| DEV-147 | PASS | The route is the Plugin Directory, not the Block Directory. |
| DEV-148 | PASS | Classified: not a Block Plugin. It has one block (`vergelabs/folder-gallery`, `core/gallery-block.php:111`, `js/vergeml-gallery-block.js:209`, no `block.json`) beside 13 `add_*_page` calls and 77 REST routes, so DEV-150 to DEV-188 do not apply. |
| DEV-149 | N/A | Meta row: the Detailed Guidelines are audited in the G/CI/HB shares. |
| DEV-150 | N/A | Block Directory only (DEV-148). |
| DEV-151 | N/A | Block Directory only. `readme.txt` exists anyway. |
| DEV-152 | N/A | Block Directory only. |
| DEV-153 | N/A | Block Directory only. |
| DEV-154 | N/A | Block Directory only. |
| DEV-155 | N/A | Block Directory only. |
| DEV-156 | N/A | Block Directory only. |
| DEV-157 | N/A | Block Directory only. |
| DEV-158 | N/A | Block Directory only. |
| DEV-159 | N/A | Block Directory only. |
| DEV-160 | N/A | Block Directory only. |
| DEV-161 | N/A | Block Directory only. |
| DEV-162 | N/A | Block Directory only. |
| DEV-163 | N/A | Block Directory only. |
| DEV-164 | N/A | Block Directory only. |
| DEV-165 | N/A | Block Directory only. |
| DEV-166 | N/A | Block Directory only. |
| DEV-167 | N/A | Block Directory only. The block title "Folder gallery" carries no trademark anyway. |
| DEV-168 | N/A | Block Directory only. The name `vergelabs/folder-gallery` is unique to the author anyway. |
| DEV-169 | N/A | Block Directory only. The namespace is `vergelabs`, the author's own. |
| DEV-170 | N/A | Block Directory only. The namespace matches the author's handle. |
| DEV-171 | N/A | Block Directory only. Not `core/` or `wordpress/`. |
| DEV-172 | N/A | Block Directory only. No `block.json`. |
| DEV-173 | N/A | Block Directory only. |
| DEV-174 | N/A | Block Directory only. |
| DEV-175 | N/A | Block Directory only. |
| DEV-176 | N/A | Block Directory only. |
| DEV-177 | N/A | Block Directory only. |
| DEV-178 | N/A | Block Directory only. |
| DEV-179 | N/A | Block Directory only. |
| DEV-180 | N/A | Block Directory only. |
| DEV-181 | N/A | Block Directory only. |
| DEV-182 | PASS | A paid service exists (the AI features), so the right place is the Plugin Directory. That is the route taken. |
| DEV-183 | N/A | Block Directory only. |
| DEV-184 | N/A | Block Directory only. |
| DEV-185 | N/A | Block Directory only. |
| DEV-186 | N/A | Block Directory only. |
| DEV-187 | N/A | Block Directory only. |
| DEV-188 | N/A | Block Directory only. |
| DEV-189 | N/A | Process: push code after approval. |
| DEV-190 | N/A | Process. |
| DEV-191 | N/A | Process. |
| DEV-192 | N/A | Process. |
| DEV-193 | N/A | Meta row: the full guideline audit is the G/CI/HB shares. |
| DEV-194 | PASS | `License: GPLv2 or later`. Bundled third-party code: Enhanced Media Library, GPLv2+ (`vergelabs-media-library.php:16-20`, `readme.txt:117-123`). Inter, OFL 1.1, with `fonts/INTER-LICENSE.txt`; WordPress 7.1.3 ships the same font under the same licence in Twenty Twenty-Four (`wp-content/themes/twentytwentyfour/readme.txt:81-84`). natural-compare-lite, MIT, notice kept (`js/eml-media-models.js:266-268`). WP Rocket admin CSS structure, GPLv2+, attributed (`css/vergeml-shell.css:4`). Core spinner GIFs, GPLv2+. Trademark: "VergeLabs" is the author's own brand; no third-party brand in the name or slug. |
| DEV-195 | N/A | Not a core feature project. |
| DEV-196 | PASS | Same security sweep as DEV-42 and DEV-43. |
| DEV-197 | N/A | Process. |
| DEV-198 | PASS | `Tested up to: 7.1`. api.wordpress.org reports latest 7.1.3, then 7.0.7 and 6.9.10. |
| DEV-199 | N/A | Process: bump at each WordPress major. |
| DEV-200 | PASS | `Tested up to: 7.1` = the latest major. |
| DEV-201 | **FAIL** | Static proof of a fatal error in a mode the readme presents as safe. When `VERGEML_SAFE_MODE` is defined, or mbstring is missing, `vergeml_features_off()` (`:1597-1599`) keeps `core/taxonomies.php` unloaded (`:1634-1636`). But `vergeml_on_init` is still hooked unconditionally (`:597`) and registers every media taxonomy, `media_category` by default, with `'update_count_callback' => 'vergeml_update_attachment_term_count'` (`:660`). That function exists only in `core/taxonomies.php:1550`. `vergeml_on_wp_loaded` (`:680`) does the same with `vergeml_update_post_term_count` (`:768`, defined at `core/taxonomies.php:1582`). Deleting a media file that sits in a folder goes `wp_delete_attachment` → `wp_delete_object_term_relationships` (WP 6.5 `post.php:6373`) → `wp_remove_object_terms` → `wp_update_term_count` → `call_user_func( $taxonomy->update_count_callback )` (`taxonomy.php:3561`). On PHP 8 that is a TypeError fatal ("function not found"); on 7.4 it is a warning. Review 6 named this instance (its `:656`/`:764`). The fix commit guarded `vergeml_enqueue_media` (`:949`) and `vergeml_sanitize_slug` (`:636`) but not these two. A full WP_DEBUG run is also owed (RUNTIME). See FAIL 2. |
| DEV-202 | PASS | Neither the name nor the slug contains a third-party brand. |
| DEV-203 | PASS | No third-party brand is used in the name. |
| DEV-204 | N/A | Process. The slug `vergelabs-media-library` is already in the review IDs, and api.wordpress.org `plugin_information` answers 404 for it (no other plugin has it). |
| DEV-205 | PASS | `VergeLabs Media Library` is spelled and capitalised the same in the header, the readme title and the banner. |
| DEV-206 | PASS | The short description at `readme.txt:11` is 92 characters and plain. `## Description ##` starts with the function (`:13-17`). |
| DEV-207 | PASS | `## Installation ##` exists (`readme.txt:79-85`). Step 3 names the in-plugin Licence screen and free trial, which is where registration happens. The service URL appears at `readme.txt:53` (as a code span) and links to `vergelabsmedia.com/legal/*` at `:75-77`. Cheap improvement: make step 3 a real link. |
| DEV-208 | PASS | The FAQ names the channels: GitHub issues, `support@vergelabsmedia.com` for licence holders with a one-business-day answer, and Patchstack VDP plus email for security (`readme.txt:109-115`). |
| DEV-209 | N/A | Process: account email. |
| DEV-210 | N/A | Process: allow-list plugins@wordpress.org. |
| DEV-211 | RUNTIME | The extracted archive is flat (a `git archive` without prefix), so the zip's top folder cannot be read from it. `dist/vergelabs-media-library-4.1.0.zip` is **stale**: it is the a47d6f0 round-5 build and differs from this archive in 18 paths. See RUNTIME. |
| DEV-212 | PASS | Static side: no build step. js/ has 0 ES `import`/`export` and 0 `.min` files. Every `js/`, `css/`, `fonts/` and `images/` path named in PHP, CSS or JS exists. The 4 apparent misses are comments: `css/eml-admin-media.css:561`, `css/vergeml-tree.css:789`, `js/eml-media.js:4`, `core/options-pages.php:268`. Install by upload is under RUNTIME DEV-211. |
| DEV-213 | N/A | Process. |
| DEV-214 | N/A | Process. |
| DEV-215 | PASS | `readme.txt` sits at the root with the code. |
| DEV-216 | N/A | Process. |
| DEV-217 | N/A | Process. |
| DEV-218 | PASS | The changelog carries 4.1.0, and `Tested up to` is current (DEV-198). |
| DEV-219 | N/A | Process. Note that 4.0.0 to 4.0.7 shipped within about a week in September 2026; at that pace on wordpress.org, users stop updating. |
| DEV-220 | N/A | Process: preview is opt-in after approval. |
| DEV-221 | N/A | No blueprint and no `Requires Plugins`. |
| DEV-222 | N/A | No preview requested. |
| DEV-223 | N/A | Process. |
| DEV-224 | N/A | Process. |
| DEV-225 | PASS | 0 `blueprint*.json` in the zip (DEV-63 find). |
| DEV-226 | N/A | No blueprint. |
| DEV-227 | N/A | Process. Playground walks are recorded in the history. |
| DEV-228 | N/A | Previews are not enabled. |
| DEV-229 | N/A | Process. |
| DEV-230 | N/A | Process. |
| DEV-231 | N/A | No blueprint. |
| DEV-232 | N/A | No blueprint. |
| DEV-233 | N/A | Process: individual accounts. |
| DEV-234 | N/A | Process. |
| DEV-235 | N/A | Process. |
| DEV-236 | N/A | Process. |
| DEV-237 | N/A | Process. |
| DEV-238 | N/A | Process. |
| DEV-239 | N/A | Process. |
| DEV-240 | N/A | Process. |
| DEV-241 | PASS | `Contributors: vergelabsdev` (`readme.txt:2`) is the submitting account (`docs/wordpress-org-submission.md:45`; review IDs `…/vergelabsdev/…`). |
| DEV-242 | N/A | Process. |
| DEV-243 | N/A | Process. |
| DEV-244 | N/A | Process. |
| DEV-245 | N/A | Process. |
| DEV-246 | PASS | `https://profiles.wordpress.org/vergelabsdev/` answers 200. A made-up name answers 404 as a control. |
| DEV-247 | N/A | Process. |
| DEV-248 | N/A | Process (MCP). |
| DEV-249 | N/A | Meta row: full guideline audit. |
| DEV-250 | N/A | Process. |
| DEV-251 | N/A | Process. |
| DEV-252 | N/A | Process. |
| DEV-253 | PASS | `grep -rnIE "WP_API_PASSWORD\|mcpServers\|\.mcp\.json\|claude_desktop_config\|sk_live_\|sk_test_\|pk_live_\|rk_live_\|PRIVATE KEY\|AKIA…\|ghp_…\|xox[bp]-\|sk-or-v1-\|sk-ant-\|AIza…"` over the archive gives 0. |
| DEV-254 | PASS | No `.mcp.json` (0 dotfiles). |
| DEV-255 | PASS | No `.vscode/`. |
| DEV-256 | RUNTIME | Readme validator. See RUNTIME. |
| DEV-257 | N/A | Process. |
| DEV-258 | PASS | The slug `vergelabs-media-library` is the author's brand plus generic words. It contains no `wordpress`, `wp`, `woo`, `plugin` or other third-party mark. api.wordpress.org gives 404 (not taken), and the review IDs already use it. |
| DEV-259 | RUNTIME | Plugin Check with the directory's flags must run on this archive. See RUNTIME. Evidence so far: PHPCS on 5dc983a with annotations ignored gives 120 findings, 0 errors under plugin-review. |
| DEV-260 | N/A | Process. |
| DEV-261 | N/A | Process. Note on review 6's classes in this archive: mbstring/OpenSSL done (`vergelabs-media-library.php:1581-1615`, `core/ai.php:332`, `:351`). Raw `$wpdb` writes to posts/postmeta are gone: `grep "\$wpdb->(update\|insert\|delete\|replace\|query)( \$wpdb->(posts\|postmeta)"` gives 0, and the code now uses `update_metadata_by_mid` (`core/health-delete.php:208`) and `wp_update_post` (`:196`). Watchdog self-entry is gone (`core/watchdog.php:20-23`). The `127.0.0.1` seam is gone. **Not done:** the safe-mode instance (DEV-201 FAIL); `.eml-media-css` still sits on `<body>` (`js/vergeml-media-views.js:943`, 101 uses in js/css), so the reply must explain it; 24 `_.extend( media.*.prototype …` remain in js/, which also needs an explanation. |
| DEV-262 | N/A | Meta row. |
| DEV-263 | PASS | GPLv2+ (DEV-12); security sweep clean (DEV-42/43/45); bundled code licensed and attributed (DEV-194). |
| DEV-264 | PASS | No third-party brand in the name or slug; licences kept (DEV-194). See advisory A1 on the banner. |
| DEV-265 | PASS | 21 server-side `wp_remote_post`/`wp_remote_get` calls (`core/ai-background.php:220`, `core/ai.php:105`, `:166`, `:594`, `core/brief.php:420`, `core/connect.php:161`, `:186`, `core/filing.php:1794`, `:3152`, `core/folder-talk.php:458`, `:2346`, `core/get-help.php:76`, `:260`, `core/guide.php:917`, `core/instrument.php:233`, `core/licence-page.php:55`, `core/offer.php:159`, `core/plan-tree.php:1042`, `:1098`, `core/search-meaning.php:132`, `:200`) and 1 browser stream (`js/vergeml-talk.js:398`). Every hard-coded external host is `ai.vergelabs.nl/v1` (default at `core/ai.php:313`, https enforced at `:316-318`) or `vergelabsmedia.com`. Both are disclosed, with what is sent and when, in `readme.txt:51-77`. 0 curl or fsockopen calls; other JS `fetch`/`$.post` calls go to `ajaxurl`, same-origin hrefs or Backbone models. Whether anything is sent without a user action is a runtime question (G7 share). |
| DEV-266 | N/A | Process. |
| DEV-267 | RUNTIME | Plugin Check run locally. See RUNTIME. |
| DEV-268 | N/A | Process. |
| DEV-269 | N/A | Process. |

## FAIL

### FAIL 1: DEV-6, the header Description is 177 characters

- **Rule:** "Description: A short description of the plugin, as displayed in the Plugins section in the WordPress Admin. Keep this description to fewer than 140 characters."
- **Where:** `vergelabs-media-library.php:5`. This is the only instance; there is one header.

  > Folders, categories and tags for the media library, with filters and MIME type settings. Optional AI alt text and descriptions through the VergeLabs service, with a licence key.

  That is 177 characters (`wc -m`), unchanged since `ddf38c4`. Plugin Check does not test the length, so no automated run will catch it.
- **Fix:** cut it below 140. The copy is Nathan's to choose. Two drafts:
  - "Folders, categories and tags for the media library. Optional AI alt text and descriptions through the VergeLabs service." (120)
  - "Folders, categories and tags for the media library, with optional AI alt text from the VergeLabs service (licence key)." (119)
- **Severity:** low. It is a written header rule, cheap to fix, and a reviewer reading the header will see it.

### FAIL 2: DEV-201, safe mode and no-mbstring hosts end in a fatal error

- **Rule:** "You'll want to make sure you've tested your plugin to make sure it works in any situation and doesn't frustrate your users." The rulebook check is "no notices or fatals".
- **Instances.** I searched the whole class: every reference from the always-loaded files (the main file, `core/watchdog.php`, `core/system-report.php`, `core/get-help.php`) to a function that only a guarded feature file defines (`safemode.js`). It found 2 unguarded instances. All the others are guarded: `:379`, `:383`, `:450`, `:636`, `:949` (covering `:977` and `:1005`), `:1392`, and `core/get-help.php:182`, `:248`, `:302`.
  1. `vergelabs-media-library.php:660`, inside `vergeml_on_init` (hooked unconditionally at `:597`): `'update_count_callback' => 'vergeml_update_attachment_term_count'`. It is defined only at `core/taxonomies.php:1550`, which is not loaded when `vergeml_features_off()` (`:1597`) is true. It applies to every `eml_media` taxonomy, including the default `media_category` (`:1198-1209`).
  2. `vergelabs-media-library.php:768`, inside `vergeml_on_wp_loaded` (`:680`): `$wp_taxonomies[$taxonomy]->update_count_callback = 'vergeml_update_post_term_count'`. It is defined only at `core/taxonomies.php:1582`. This applies to a non-plugin taxonomy that is on both posts and attachments.
- **Failure:** set `define( 'VERGEML_SAFE_MODE', true )` as the readme FAQ tells users to (`readme.txt:101`), or run on a host without mbstring. Then delete a media file that is in a folder. The path is `wp_delete_attachment` → `wp_delete_object_term_relationships` (`post.php:6373`) → `wp_remove_object_terms` → `wp_update_term_count` → `wp_update_term_count_now` → `call_user_func( 'vergeml_update_attachment_term_count', … )` (WP 6.5 `taxonomy.php:3560-3561`). On PHP 8 that is an uncaught TypeError. Assigning or removing a folder in core's attachment screen takes the same path. With instance 2, publishing a post in such a shared taxonomy fails the same way.
- **Fix:** register each count callback only when it exists. At `:660`: `'update_count_callback' => function_exists( 'vergeml_update_attachment_term_count' ) ? 'vergeml_update_attachment_term_count' : '_update_generic_term_count'`. At `:768`, assign only `if ( function_exists( 'vergeml_update_post_term_count' ) )`. Alternatively, move both counters (`core/taxonomies.php:1550-1610`) into the main file, which loads in every mode. Then prove it with the RUNTIME run below in safe mode.

## RUNTIME

- **DEV-201** (also FAIL 2 above). Build the zip from 5dc983a or later, after the FAIL 2 fix. Run it on a clean Playground site with `WP_DEBUG` and `WP_DEBUG_LOG` on, in two cells:
  - WP 6.5.11 on PHP 7.4
  - WP 7.1.3 on PHP 8.5

  In each cell:
  1. Single-site Activate, then Network Activate on a multisite.
  2. Open every plugin screen, `upload.php` in grid and list modes, the block editor with the media modal, and the Customizer's media picker.
  3. Delete one filed attachment.
  4. Repeat with `define( 'VERGEML_SAFE_MODE', true )`: open the media screens and delete a filed attachment.

  Proof is an empty `debug.log` in every cell and the plugin's four tables present (`SHOW TABLES LIKE '%vergeml%'`).

  Also found statically, not a DEV FAIL: `core/options-pages.php:1290` passes `$_FILES['import_file']['tmp_name']` through `wp_unslash()`. WordPress never slashes `$_FILES` (WP 6.5 `load.php:1249-1252` slashes only `$_GET`, `$_POST`, `$_COOKIE` and `$_SERVER`, and WPCS exempts `$_FILES` in `ValidatedSanitizedInputSniff.php:57-62`). So on a Windows server, `C:\…\php1A2B.tmp` loses its backslashes, `is_uploaded_file()` fails at `:1295`, and every settings import is refused. **Fix:** drop `wp_unslash()` on the three `$_FILES` string reads (`:1288-1290`). `is_uploaded_file()` stays the validation.
- **DEV-211.** The extracted archive has no top folder, so the zip's layout cannot be judged from it. `dist/vergelabs-media-library-4.1.0.zip` is the **a47d6f0** round-5 build: its zip comment is `a47d6f06…`, it dates from 10:42, and `diff -rq` against this archive lists 18 differing paths. **Do not upload it.** Rebuild with `git archive --format=zip --prefix=vergelabs-media-library/ -o dist/vergelabs-media-library-4.1.0.zip <fixed HEAD>`. Then check two things:
  - `unzip -l` shows one top entry, `vergelabs-media-library/`, with `vergelabs-media-library.php` and `readme.txt` directly under it, and a file count of 147 (+ directories).
  - The zip installs through Plugins › Add New › Upload on a clean site and activates.
- **DEV-256.** Paste the final `readme.txt` into the wordpress.org readme validator, or run Plugin Check's `plugin_readme` category, and get 0 errors. The `## Section ##` headings and the `### … ###` subsection inside the FAQ (`readme.txt:125`) are what to look at.
- **DEV-259 / DEV-267.** Run `node tools/plugin-check.mjs <url-of-the-rebuilt-zip>`: Plugin Check 2.1.0 in Playground, all categories, the plugin-review flags, on the zip that will be uploaded. Proof is 0 errors, with every warning listed and answered. The last recorded run was on an earlier commit, not on 5dc983a.
  - PHPCS on 5dc983a (`node tools/phpcs.mjs --ref 5dc983a`, PHPCS 3.13.4 and WPCS 3.2.0 in php-wasm, 68 files):
    - Annotations honoured: **0 findings**.
    - Annotations ignored: **120 findings**, and **0 ERRORS under plugin-review**. The 120 break down as:
      - NonceVerification.Recommended: 94 (GET reads for notices and views)
      - NonceVerification.Missing: 9 (read by hand below)
      - NonPrefixedHooknameFound: 12 (core and Dokan hooks fired on purpose)
      - PreparedSQLPlaceholders.ReplacementsWrongNumber: 5 (`array_fill` placeholder lists counted at runtime)
    - `markup-scan`: Translation=0, Value=0, InlineTag=0.
    - The 9 Missing are the 9 `$_POST` reads that `fninput.js` lists without a nonce:
      - 3 are `register_setting` sanitize callbacks, which options.php reaches only after its own nonce and capability check: `core/mime-types.php:16`, `core/taxonomies.php:16` and `:363`, registered at `core/options-pages.php:30`, `:37`, `:44`.
      - The rest are read-only view filters on core's `ajax_query_attachments_args`: `core/quarantine.php:229-240` and `core/smart-folders.php:1189-1191`.
    - None of them changes state.

## Advisory: outside the archive, so not verdicts

- **A1. Banner wording** (`assets/banner-1544x500.png`, SVN-only). It says "the maintained successor to Enhanced Media Library" and "Built for WordPress 7 and PHP 8". The first can read as a claim to be the official successor of a plugin that is still listed and whose adoption request is open (G9/G17 territory). The second contradicts `Requires at least: 6.5` / `Requires PHP: 7.4`. The archive itself has 0 "successor", "official" or "endorsed" claims. Suggest "a fork of Enhanced Media Library" and "WordPress 6.5+, PHP 7.4+".
- **A2. SVN assets** in the repo's `assets/`, measured by reading the PNG IHDR:

  | File | Size in pixels | File size |
  |---|---|---|
  | `banner-772x250.png` | 772×250 | 15.7 KB |
  | `banner-1544x500.png` | 1544×500 | 41.1 KB |
  | `icon-128x128.png` | 128×128 | 1.7 KB |
  | `icon-256x256.png` | 256×256 | 3.3 KB |
  | `screenshot-1..6.png` | 1280×800 each | 41-65 KB |

  All are real PNGs with lowercase names. There are no RTL or locale copies and no `icon.svg`. 6 screenshots match the 6 readme captions. After `svn add`, run `svn propset svn:mime-type image/png *.png` (DEV-95/96).
- **A3. Licence URIs.** The header (`:14`, old-licenses) and the readme (`readme.txt:9`) use two different http URLs for the same GPLv2 text. Use one https URL in both.
- **A4. Stale POT.** `languages/vergelabs-media-library.pot:5` says `Project-Id-Version: VergeLabs Media Library 3.13.2`, created 2026-09-01. It has 1,060 msgids against 2,114 gettext calls in the code. wordpress.org builds translations from the code, so this does not block, but regenerate it or drop it.
