# DEV- rows audit: VergeLabs Media Library 4.1.0

Archive: `scratchpad/audit/vergelabs-media-library/` (143 files, 54,030 PHP lines, 19,189 JS lines). Paths below are relative to that folder. Rulebook: `docs/wordpress-org-rulebook.md`, rows DEV-1 to DEV-269.
Method: I read the header, readme and block code, and grepped every PHP/JS file. I parsed all 77 `register_rest_route` calls, all 18 `wp_ajax_*`/`admin_post_*` handlers, all 199 `$wpdb` read/query calls and all 133 superglobal reads with scripts, and opened each suspicious hit. I ignored `phpcs:ignore`: the archive has 273 `phpcs:ignore`/`disable` lines, 104 of them for PreparedSQL, 50 for NonceVerification and 3 for EscapeOutput.
"Process" means the row describes an SVN, account or email step that a release archive cannot show.

| ID | Verdict | Evidence | Fix if FAIL |
|---|---|---|---|
| DEV-1 | PASS | `vergelabs-media-library.php:2-27` opens with a `/* */` header block containing `Plugin Name:`. `grep -l "Plugin Name:"` over root and core matches only this file. | |
| DEV-2 | PASS | `vergelabs-media-library.php:3` `Plugin Name: VergeLabs Media Library` | |
| DEV-3 | PASS | Header name matches readme title `=== VergeLabs Media Library ===` (`readme.txt:1`) | |
| DEV-4 | PASS | `Plugin URI: https://vergelabsmedia.com` (`:4`) is the product's own site | |
| DEV-5 | PASS | No wordpress.org URL. It differs from `Author URI: https://vergelabs.nl` (`:10`). | |
| DEV-6 | PASS | `Description:` (`:5`) is 120 characters, under the 140 limit | |
| DEV-7 | PASS | `Version: 4.1.0` (`:6`) equals readme `Stable tag: 4.1.0` (`readme.txt:7`) and `VERGEML_VERSION '4.1.0'` (`:36`). SVN tag: n/a until approval. | |
| DEV-8 | PASS | `Requires at least: 6.5` in both header and readme. No 6.6+ API found: I grepped about 50 recent functions and the newest hit is `wp_cache_flush_group` (6.1). `\WpOrg\Requests` (`core/ai.php:887`) needs 6.2. | |
| DEV-9 | PASS | `Requires PHP: 7.4` in both. No `match`, `?->`, enum, readonly, constructor promotion, union types, `catch (X)` without a variable, or trailing-comma parameters. `str_contains` (3 uses) is polyfilled by WP 5.9+, and `array_key_first` is PHP 7.3. | |
| DEV-10 | PASS | `Author: VergeLabs` (`:9`) | |
| DEV-11 | PASS | `Author URI: https://vergelabs.nl` (`:10`) is a valid URL | |
| DEV-12 | PASS | `License: GPLv2 or later` (`:13`) is the same in `readme.txt:8` | |
| DEV-13 | PASS | `License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html` (`:14`) points to the full GPLv2 text. Note: the readme uses `.../licenses/gpl-2.0.html`. Both are valid, but it would be cleaner to use one https URL in both places. | |
| DEV-14 | PASS | `Text Domain: vergelabs-media-library` matches the folder and file slug. 1,785 PHP gettext calls and 261 JS `__()` calls use it, and no other literal domain appears. `wp_set_script_translations(...,'vergelabs-media-library')` is used at `core/ai-screen.php:78`, `core/brief.php:761` and `core/guide.php:89,140`. | |
| DEV-15 | PASS | `Domain Path: /languages`. The folder exists and holds `vergelabs-media-library.pot`. | |
| DEV-16 | PASS | No `Network:` line, and the plugin works per site | |
| DEV-17 | PASS | `Network:` is absent (never `false`) | |
| DEV-18 | PASS | No `Update URI:` header. I grepped `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `plugins_api`, `upgrader_*`, `auto_update_plugin`, `Plugin_Upgrader` and `download_url(` with zero hits, so there is no custom updater. | |
| DEV-19 | N/A | No `Requires Plugins:` header | |
| DEV-20 | N/A | No `Requires Plugins:` header | |
| DEV-21 | N/A | No `Requires Plugins:` header | |
| DEV-22 | N/A | The header is a plain `/* */` block. No PHPDoc DocBlock comes before it. | |
| DEV-23 | PASS | `version_compare('4.1.0','4.0.7','>')` is true. 4.0.7 is the previous changelog entry (`readme.txt:279`). | |
| DEV-24 | PASS | I grepped the readme, header and UI strings for compliance, GDPR, WCAG, ADA, HIPAA, PCI, guarantee and "100%". The only hits are code comments, CSS widths and `readme.txt:128` "legal/sub-processors" links. No dishonest claims. | |
| DEV-25 | PASS | None of those words make a claim. The `accessibility` tag (`readme.txt:3`) has no compliance wording attached. | |
| DEV-26 | PASS | No "100%" near compliance anywhere | |
| DEV-27 | PASS | The readme never shifts legal responsibility onto the plugin | |
| DEV-28 | N/A | No compliance wording to reword | |
| DEV-29 | PASS | The readme and `Description:` make no compliance claim. Assets are not in the archive. | |
| DEV-30 | PASS | No match for "will make your site … compliant" | |
| DEV-31 | N/A | No compliance claim, so the "no plugin can provide 100%" note is not required | |
| DEV-32 | PASS | The name "VergeLabs Media Library" implies no compliance | |
| DEV-33 | PASS | The name carries the brand prefix "VergeLabs" | |
| DEV-34 | N/A | Neither the plugin nor the service claims compliance. The readme `External services` section (`readme.txt:104-130`) only describes data flows and links terms and privacy. | |
| DEV-35 | N/A | No compliance claim to prove | |
| DEV-36 | PASS | No third-party compliance claims repeated | |
| DEV-37 | PASS | No "100% XHTML/W3C/valid" | |
| DEV-38 | N/A | Process (answering a warning email within 60 days) | |
| DEV-39 | N/A | Process (plugin reopen) | |
| DEV-40 | N/A | Process (reporting other plugins) | |
| DEV-41 | PASS | Own readme audited in DEV-24 to DEV-37 | |
| DEV-42 | **FAIL** | (a) 33 `$wpdb` calls run SQL built from variables without `prepare()` at the call site. Examples: `core/guide.php:2793`, `core/search-try.php:80,84,85,87,142`, `core/ai-index.php:259`, `core/ai-screen.php:122-143`, `core/librarian.php:1496`, `core/folder-talk.php:2252`, `core/search-meaning.php:417`, `core/auto-file.php:764`, `core/plan-tree.php:109,383`, `core/options-pages.php:1939`, `uninstall.php:112`. Another 46 are static SQL with interpolated `{$wpdb->…}`. I traced the pieces: they are int-cast or prepared one by one, and I found no injectable path. All of them only pass because of PreparedSQL suppressions. (b) Unsanitized or unslashed input: `core/options-pages.php:1257` `switch ( $_REQUEST['settings'] )`, `core/taxonomies.php:1260` `array_keys( $_REQUEST['tax_input'][ $taxonomy ], 1 )`, `core/get-help.php:295` `sanitize_key( $_GET['vgml-help'] )` without `wp_unslash`. (c) Unescaped output: `core/options-pages.php:1582` `echo json_encode( $settings );`, and `core/import-csv.php:214,217` echo raw CSV (formula-neutralised at `:151`, but echoed unescaped). Clean: no `eval`, `assert`, `create_function`, `unserialize` of input, or `extract`; no REST write route uses `__return_true`. | (a) Use `$wpdb->prepare()` with `%i` for table names (WP 6.2+; the plugin requires 6.5) and `implode(',', array_fill(0, count($ids), '%d'))` placeholders for `IN ()`. Example: `$wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE attachment_id IN ($ph)", array_merge( array( $wpdb->vergeml_ai_index ), $chunk ) ), ARRAY_A )`. Wrap static queries the same way, then delete the PreparedSQL suppressions. (b) `$which = sanitize_key( wp_unslash( $_REQUEST['settings'] ) ); switch ( $which )`; `$term_ids = array_map( 'absint', array_keys( array_filter( (array) wp_unslash( $_REQUEST['tax_input'][ $taxonomy ] ) ) ) );`; `sanitize_key( wp_unslash( $_GET['vgml-help'] ) )`. (c) Replace `echo json_encode( $settings )` with `wp_send_json( $settings );` after the Content-Disposition header. Write the CSV through `$fh = fopen( 'php://output', 'w' ); fwrite( $fh, … )` instead of `echo`. |
| DEV-43 | PASS | No `wp_ajax_nopriv_` or `admin_post_nopriv_` hooks. All 18 AJAX and admin-post handlers verify a nonce (`check_ajax_referer`, `check_admin_referer` or `wp_verify_nonce`). 17 also check a capability. `wp_ajax_tb_load_editor` (`core/page-builders.php:262`) only enqueues assets behind `upload_files`. All 77 REST routes have a `permission_callback` with a real capability: 65 use `manage_options`, the rest `upload_files`, `manage_categories`, `edit_post` or `assign_terms`. Every `admin_init` POST handler (`core/options-pages.php:1367,1502,1555,1600,1694,1742,1766`) checks nonce plus `manage_options`. | |
| DEV-44 | N/A | Process (release timing for the cooldown) | |
| DEV-45 | PASS | No `eval(`, `assert(`, `create_function(`, `preg_replace /e`, `file_put_contents(` or `unserialize(` of input. `base64_decode` appears only on the plugin's own data: the sealed licence key (`core/ai.php:353`) and packed floats (`core/organize.php:2415`). `maybe_unserialize` reads only stored attachment meta (`core/health.php:938`). The settings import reads only an `is_uploaded_file()` temp file (`core/options-pages.php:1636`). No remote code: no external `<script src>`, no `new Function` or `eval` in JS, and model output never reaches `innerHTML` (`js/vergeml-talk.js:47`). | |
| DEV-46 | N/A | Process | |
| DEV-47 | N/A | Process (committers watching their inboxes) | |
| DEV-48 | **FAIL** | Same findings as DEV-42. Unprepared dynamic SQL, unslashed or unsanitized `$_REQUEST` reads and `echo json_encode` are the accidental-vulnerability patterns this row treats as blockers. | Same as DEV-42 |
| DEV-49 | N/A | Process (fix each finding from the email) | |
| DEV-50 | N/A | Process | |
| DEV-51 | N/A | Process | |
| DEV-52 | N/A | Process | |
| DEV-53 | N/A | Process | |
| DEV-54 | N/A | Process | |
| DEV-55 | N/A | Process | |
| DEV-56 | N/A | Process. Note: if 4.1.0 is ever blocked, the next upload must be 4.1.1. | |
| DEV-57 | N/A | Process | |
| DEV-58 | N/A | Process | |
| DEV-59 | N/A | Process | |
| DEV-60 | N/A | Process | |
| DEV-61 | N/A | Process (re-run the full review on each fix release). This audit is one such run. | |
| DEV-62 | N/A | Process | |
| DEV-63 | N/A | SVN `/assets/` does not exist before approval | |
| DEV-64 | PASS | No `assets/` folder and no `banner-*`, `icon-*` or `screenshot-*` files in the archive | |
| DEV-65 | N/A | Process (CDN delay) | |
| DEV-66 | N/A | No asset files to measure | |
| DEV-67 | N/A | No banner in the archive (lives in SVN assets) | |
| DEV-68 | N/A | No icon in the archive | |
| DEV-69 | N/A | No banners | |
| DEV-70 | N/A | No banners or icons | |
| DEV-71 | N/A | No banner | |
| DEV-72 | N/A | Optional RTL banner, none present | |
| DEV-73 | N/A | Banner lives in SVN assets, not the archive | |
| DEV-74 | N/A | No localized banners | |
| DEV-75 | N/A | No high-DPI banner | |
| DEV-76 | N/A | No localized banners | |
| DEV-77 | N/A | No locale-suffixed assets | |
| DEV-78 | N/A | No RTL or locale banners | |
| DEV-79 | N/A | No banners | |
| DEV-80 | N/A | No banners | |
| DEV-81 | N/A | No icons | |
| DEV-82 | N/A | No `icon.svg` | |
| DEV-83 | N/A | No icon assets in the archive (allowed) | |
| DEV-84 | N/A | No icons | |
| DEV-85 | N/A | No icons | |
| DEV-86 | N/A | No icons | |
| DEV-87 | N/A | No SVG icon | |
| DEV-88 | N/A | `== Screenshots ==` (`readme.txt:237`) has captions, but the image files belong in SVN assets. Check the count when uploading assets. | |
| DEV-89 | N/A | Screenshot files are not in the archive. Check the caption numbering against the assets at SVN time. | |
| DEV-90 | N/A | Screenshot files live in SVN assets | |
| DEV-91 | N/A | No screenshots in the archive | |
| DEV-92 | N/A | No screenshots in the archive | |
| DEV-93 | N/A | No `assets/` | |
| DEV-94 | N/A | No localized screenshots | |
| DEV-95 | N/A | SVN mime-type property, process | |
| DEV-96 | N/A | Process (svn propset) | |
| DEV-97 | N/A | Process (svn auto-props) | |
| DEV-98 | N/A | Process (release confirmation) | |
| DEV-99 | N/A | Process | |
| DEV-100 | N/A | Process | |
| DEV-101 | N/A | Process | |
| DEV-102 | PASS | `Stable tag: 4.1.0` is a version number, not `trunk`. The tag folder: n/a before SVN. | |
| DEV-103 | PASS | `Stable tag: 4.1.0` equals `Version: 4.1.0` | |
| DEV-104 | N/A | Process (freeze readme before tagging). `Tested up to: 7.1` is already set. | |
| DEV-105 | N/A | Process | |
| DEV-106 | N/A | Process | |
| DEV-107 | N/A | Process | |
| DEV-108 | N/A | Process (svn log) | |
| DEV-109 | N/A | No SVN repository yet (pre-approval) | |
| DEV-110 | N/A | Process | |
| DEV-111 | N/A | Process | |
| DEV-112 | N/A | Process | |
| DEV-113 | PASS | No banner, icon or screenshot files in the archive | |
| DEV-114 | N/A | SVN trunk does not exist yet | |
| DEV-115 | N/A | SVN tags do not exist yet | |
| DEV-116 | PASS | `vergelabs-media-library.php` sits at the archive root folder `vergelabs-media-library/`, not nested | |
| DEV-117 | N/A | SVN trunk does not exist yet | |
| DEV-118 | N/A | SVN diff, process | |
| DEV-119 | N/A | No tags yet. Header and Stable tag agree (DEV-103). | |
| DEV-120 | PASS | `Stable tag:` is not `trunk` | |
| DEV-121 | N/A | No tag folders yet | |
| DEV-122 | PASS | `4.1.0` follows MAJOR.MINOR.PATCH | |
| DEV-123 | PASS | No `screenshot-*` files in the archive | |
| DEV-124 | N/A | Process (branches) | |
| DEV-125 | N/A | Process | |
| DEV-126 | N/A | Process | |
| DEV-127 | N/A | SVN trunk does not exist yet | |
| DEV-128 | PASS | `Stable tag: 4.1.0` | |
| DEV-129 | N/A | Process (increment per release). 4.1.0 is greater than 4.0.7 (DEV-23). | |
| DEV-130 | N/A | Process | |
| DEV-131 | N/A | Process | |
| DEV-132 | N/A | Process | |
| DEV-133 | N/A | Process | |
| DEV-134 | N/A | Process | |
| DEV-135 | N/A | The changelog lists 4.0.0 to 4.0.7, which shipped through GitHub before the listing. Only versions released through SVN can have tags. | |
| DEV-136 | N/A | No tags yet | |
| DEV-137 | N/A | Process | |
| DEV-138 | N/A | No tags yet | |
| DEV-139 | PASS | `find` shows no `.git`, `.gitignore`, `.github`, `node_modules`, `tests`, `phpunit.xml`, `.env`, `.DS_Store`, `composer.lock` or `vendor`. The extra files are `SECURITY.md`, `changelog.txt` and `wpml-config.xml`, all deliberate. | |
| DEV-140 | PASS | No `*.zip` in the archive | |
| DEV-141 | N/A | Not a Block Plugin (see DEV-148). The general guidelines are covered by the G rows. | |
| DEV-142 | PASS | Block render `vergeml_render_gallery_block` (`core/gallery-block.php`): every link goes through `esc_url`, images through `wp_kses( $img, vergeml_kses_allowed() )`, and wrapper class and style through `esc_attr`. The folder route `/gallery-folders` (`core/gallery-block.php:355`) requires `upload_files`. | |
| DEV-143 | PASS | The block needs only an existing folder. No key, account or setup (`js/vergeml-gallery-block.js`). | |
| DEV-144 | PASS | `register_block_type( 'vergelabs/folder-gallery', … )` runs on `init` priority 14 (`core/gallery-block.php:56,111`). Advisory: it is registered in PHP without `block.json` and with `api_version` 2. Moving to `block.json` with `apiVersion: 3` would avoid WP 6.9+/7.x iframe-editor deprecation notices. | |
| DEV-145 | PASS | No Pro, upgrade, buy, licence or credit strings in `js/vergeml-gallery-block.js`, `js/vergeml-gallery.js`, `core/gallery-block.php` or `core/gallery-widgets.php` | |
| DEV-146 | N/A | Not submitted to the Block Directory | |
| DEV-147 | PASS | Decided: ordinary Plugin Directory listing. It is not eligible for the Block Directory: it has admin menus, a shortcode and a licensed service. | |
| DEV-148 | PASS | Classified as a plugin that contains a block, not a Block Plugin (66 core PHP files, `add_menu_page` at `core/admin-menu.php:50`, a shortcode). So DEV-150 to DEV-188 do not apply. | |
| DEV-149 | N/A | Covered by the G-row audit | |
| DEV-150 | N/A | Not a Block Plugin | |
| DEV-151 | N/A | Not a Block Plugin (`readme.txt` is present anyway) | |
| DEV-152 | N/A | Not a Block Plugin | |
| DEV-153 | N/A | Not a Block Plugin | |
| DEV-154 | N/A | Not a Block Plugin | |
| DEV-155 | N/A | Not a Block Plugin | |
| DEV-156 | N/A | Not a Block Plugin | |
| DEV-157 | N/A | Not a Block Plugin | |
| DEV-158 | N/A | Not a Block Plugin | |
| DEV-159 | N/A | Not a Block Plugin. The block name `vergelabs/folder-gallery` is its own namespace anyway. | |
| DEV-160 | N/A | Not a Block Plugin | |
| DEV-161 | N/A | Not a Block Plugin. It has one block, without `block.json`. | |
| DEV-162 | N/A | Not a Block Plugin | |
| DEV-163 | N/A | Not a Block Plugin | |
| DEV-164 | N/A | Not a Block Plugin. The block title "Folder gallery" describes what it does. | |
| DEV-165 | N/A | Not a Block Plugin | |
| DEV-166 | N/A | Not a Block Plugin. "Folder gallery" is distinct from core "Gallery". | |
| DEV-167 | N/A | Not a Block Plugin. No third-party trademark in the block name or title. | |
| DEV-168 | N/A | Not a Block Plugin. `vergelabs/folder-gallery` is unique in the code. | |
| DEV-169 | N/A | Not a Block Plugin. The namespace `vergelabs` is the author's own. | |
| DEV-170 | N/A | Not a Block Plugin. The namespace is the author handle. | |
| DEV-171 | N/A | Not a Block Plugin. The namespace is not `core/` or `wordpress/`. | |
| DEV-172 | N/A | `block.json` is required only for Block Directory plugins. There is none (registered in PHP). See the DEV-144 advisory. | |
| DEV-173 | N/A | Block Directory only | |
| DEV-174 | N/A | Block Directory only | |
| DEV-175 | N/A | Block Directory only. `editor_script` is set in PHP. | |
| DEV-176 | N/A | Block Directory only | |
| DEV-177 | N/A | Not a Block Plugin. No `Requires Plugins:` either. | |
| DEV-178 | N/A | Not a Block Plugin | |
| DEV-179 | N/A | Not a Block Plugin. The block itself needs no key. | |
| DEV-180 | N/A | Not a Block Plugin. The block code has no licence checks. | |
| DEV-181 | N/A | Not a Block Plugin | |
| DEV-182 | N/A | Plugin Directory only, as this row allows | |
| DEV-183 | N/A | Not a Block Plugin. The block makes no external calls. | |
| DEV-184 | N/A | Not a Block Plugin | |
| DEV-185 | N/A | Not a Block Plugin | |
| DEV-186 | N/A | Not a Block Plugin | |
| DEV-187 | N/A | Not a Block Plugin | |
| DEV-188 | N/A | Not a Block Plugin | |
| DEV-189 | N/A | Process (upload code soon after approval) | |
| DEV-190 | N/A | Process | |
| DEV-191 | N/A | Process | |
| DEV-192 | N/A | Process | |
| DEV-193 | N/A | Process (full guideline audit, covered by the sibling G, CI, RM and HB audits) | |
| DEV-194 | PASS | `License: GPLv2 or later`. EML origin is credited (`vergelabs-media-library.php:16-21`, `readme.txt:19`). Inter fonts carry `fonts/INTER-LICENSE.txt` (SIL OFL 1.1, accepted on wp.org). The `fonts/webbistro.*` IcoMoon icon font is inherited from GPL Enhanced Media Library. No third-party trademark leads the name or slug. Advisory: add a line naming webbistro's origin and licence to the readme or a licence file. | |
| DEV-195 | N/A | Process (not a core feature project) | |
| DEV-196 | **FAIL** | Same as DEV-42: unprepared dynamic SQL, `$_REQUEST` used without sanitizing or unslashing, `echo json_encode`. Nonces and capabilities are clean (DEV-43). | Same as DEV-42 |
| DEV-197 | N/A | Process | |
| DEV-198 | PASS | `Tested up to: 7.1` (`readme.txt:5`) is current | |
| DEV-199 | N/A | Process | |
| DEV-200 | PASS | The readme in the archive (future trunk and tag) says 7.1 | |
| DEV-201 | N/A | Runtime test on the 6.5/7.4 minimums with `WP_DEBUG`. A static archive cannot show it. | |
| DEV-202 | PASS | Name and slug `vergelabs-media-library` contain no third-party brand | |
| DEV-203 | PASS | Enhanced Media Library is named only as "Based on" in the header comment and readme body, never in the name | |
| DEV-204 | N/A | Process (slug chosen at submission) | |
| DEV-205 | PASS | `Plugin Name: VergeLabs Media Library`: spelling and capitalisation consistent with the readme | |
| DEV-206 | PASS | Readme short description (`:11`) and `## Description ##` (`:13-23`) state the function plainly | |
| DEV-207 | **FAIL** | `## Installation ##` step 3 (`readme.txt:138`) says "Adjust the settings under **Settings > Media**". The settings actually sit under the top-level "VergeLabs Library" menu (`core/admin-menu.php:50`, submenus `core/options-pages.php:126-150` with parent `VERGEML_MENU`; old Settings slugs are redirected away at `core/admin-menu.php:179-181`), with Media Utilities under Settings (`core/options-pages.php:184`). Installation also never says that the AI features need a licence key or trial, or where to get one. That information appears only in External services (`readme.txt:108,118,120`). | Replace step 3 with "Open **VergeLabs Library** in the admin menu; library, taxonomy and MIME settings are its sub-pages." Add step 4: "AI features (alt text, filing, search by meaning) need a licence key: press Start trial on the AI screen, or connect a key under **VergeLabs Library > Licence** (https://vergelabsmedia.com). Everything else works without one." |
| DEV-208 | PASS | `readme.txt:202` names the GitHub issue tracker and the licence-holder route. The Get help screen is described at `:122`. | |
| DEV-209 | N/A | Process (account email) | |
| DEV-210 | N/A | Process (allow-list plugins@wordpress.org) | |
| DEV-211 | PASS | The archive unpacks to `vergelabs-media-library/` with `vergelabs-media-library.php` and `readme.txt` at its root | |
| DEV-212 | PASS | No build step: plain JS and CSS in `js/` and `css/`, RTL CSS shipped, no `src/`-only sources, no `package.json` needed | |
| DEV-213 | N/A | Process | |
| DEV-214 | N/A | Process | |
| DEV-215 | N/A | SVN, after approval | |
| DEV-216 | N/A | Process. Note: GitHub is linked only as source and issue tracker, with no updater (DEV-18). | |
| DEV-217 | N/A | Process | |
| DEV-218 | PASS | Changelog `readme.txt:261-` shows 4.1.0 back to 4.0.0, all recent. `Tested up to` is current. | |
| DEV-219 | N/A | Process (cadence). Nine releases in about 5 weeks predate the listing. | |
| DEV-220 | N/A | Preview is optional and not enabled | |
| DEV-221 | N/A | No blueprint, and no `Requires Plugins:` | |
| DEV-222 | N/A | Preview not enabled | |
| DEV-223 | N/A | Process | |
| DEV-224 | N/A | Process | |
| DEV-225 | PASS | No `blueprint.json` in the archive (must live only in SVN `assets/blueprints/`) | |
| DEV-226 | N/A | No blueprint | |
| DEV-227 | N/A | Process (Playground test) | |
| DEV-228 | N/A | Preview not enabled. If enabled later, note the MySQL-only `SELECT @i:=0` (`core/taxonomies.php:1405`) and `REGEXP` (`core/guide.php:742-743`) under Playground SQLite. | |
| DEV-229 | N/A | Process | |
| DEV-230 | N/A | Process | |
| DEV-231 | N/A | No blueprint | |
| DEV-232 | N/A | No blueprint | |
| DEV-233 | N/A | Process (individual accounts) | |
| DEV-234 | N/A | Process | |
| DEV-235 | N/A | Process | |
| DEV-236 | N/A | Process | |
| DEV-237 | N/A | Process | |
| DEV-238 | N/A | Process | |
| DEV-239 | N/A | Process | |
| DEV-240 | N/A | Process | |
| DEV-241 | PASS | `Contributors: vergelabsdev` (`readme.txt:2`). Not verified: that it is the submitting or committer account. | |
| DEV-242 | N/A | Process | |
| DEV-243 | N/A | Process | |
| DEV-244 | N/A | Process | |
| DEV-245 | N/A | Process | |
| DEV-246 | PASS | `Contributors:` holds one lowercase profile slug, `vergelabsdev` | |
| DEV-247 | N/A | Process | |
| DEV-248 | N/A | Process (MCP report is not approval) | |
| DEV-249 | N/A | Process (full audit regardless of route) | |
| DEV-250 | N/A | Process | |
| DEV-251 | N/A | Process | |
| DEV-252 | N/A | Process | |
| DEV-253 | PASS | No `WP_API_PASSWORD`, `.mcp.json`, `.vscode/mcp.json` or `claude_desktop_config.json` anywhere in the archive | |
| DEV-254 | PASS | No `.mcp.json` in the archive | |
| DEV-255 | PASS | No `.vscode/` in the archive | |
| DEV-256 | PASS | Static check, validator not run: all readme headers are present (Contributors, 5 Tags, Requires at least, Tested up to, Requires PHP, Stable tag, License, License URI). The short description is 92 characters. Sections use the `## … ##` form, which the parser accepts. | |
| DEV-257 | N/A | Process (post-approval releases go through SVN) | |
| DEV-258 | PASS | Slug `vergelabs-media-library` uses the owner's brand and is not reserved or trademarked by others | |
| DEV-259 | **FAIL** | Predicted result with annotations ignored (not run here). 273 `phpcs:ignore`/`disable` lines hide findings: 104 PreparedSQL (ERROR class), 50 NonceVerification and 3 EscapeOutput. Plugin Check judged without the annotations will not be clean (DEV-42). | Fix the code behind the suppressions (DEV-42 fixes) and delete the annotations. Then run `wp plugin check vergelabs-media-library` with the directory's flags and annotations ignored, and upload only at zero errors. |
| DEV-260 | N/A | Process (author reviews every file) | |
| DEV-261 | N/A | Process (fix each reviewer point plugin-wide). Relevant here: the DEV-42 patterns occur across 15 files, not in one place. | |
| DEV-262 | N/A | Process | |
| DEV-263 | **FAIL** | GPL-compatible licence passes (DEV-12). "Security greps clean" does not (DEV-42). | Same as DEV-42 |
| DEV-264 | PASS | No third-party brand in the name. EML attribution is in the header and readme. The Inter licence file is shipped. See the DEV-194 webbistro advisory. | |
| DEV-265 | PASS | 23 `wp_remote_post`/`wp_remote_get` calls, 1 `Requests::request_multiple` (`core/ai.php:896`, `verify => true`) and the browser stream (`js/vergeml-talk.js:398`) all go to ai.vergelabs.nl or vergelabsmedia.com. Each flow is documented in `## External services ##` (`readme.txt:104-130`): describe, filing, plan, browser stream, trial, connect, help ticket and known issues, opt-in counts, and wp-config overrides. Nothing is contacted on activation without a key. No `sslverify => false`. | |
| DEV-266 | N/A | Process (human review of AI changes) | |
| DEV-267 | N/A | Process. See DEV-259 for the predicted outcome. | |
| DEV-268 | N/A | Process | |
| DEV-269 | N/A | Process | |

**Counts:** 269 rows. 70 PASS, 6 FAIL (DEV-42, 48, 196, 207, 259, 263), 193 N/A.

## FAILS

1. **DEV-42 (also DEV-48, DEV-196, DEV-263, same root cause): security self-review not clean once `phpcs:ignore` is ignored.**
   - **Problem (a), SQL:** 33 `$wpdb` calls run variable-built SQL without `prepare()` at the call site, and 46 more interpolate `{$wpdb->table}` into unprepared SQL. Examples: `core/guide.php:2793`, `core/search-try.php:80-142`, `core/ai-index.php:259`, `core/ai-screen.php:122-143`, `core/librarian.php:1496`, `core/folder-talk.php:2252`, `core/search-meaning.php:417`, `core/auto-file.php:764`, `uninstall.php:112`. Every value I traced is int-cast or pre-prepared, so I found no injection. They still score as unprepared SQL.
     - **Fix:** use `$wpdb->prepare()` with `%i` for identifiers (WP ≥ 6.2, and the plugin requires 6.5) and `%d` placeholder lists for `IN()`. Then delete the 104 PreparedSQL suppressions.
   - **Problem (b), input:**
     - `core/options-pages.php:1257` `switch ( $_REQUEST['settings'] )` is not sanitized.
     - `core/taxonomies.php:1260` `array_keys( $_REQUEST['tax_input'][ $taxonomy ], 1 )` is neither unslashed nor sanitized.
     - `core/get-help.php:295` reads `$_GET['vgml-help']` without `wp_unslash`.
     - **Fix:** `sanitize_key( wp_unslash( … ) )` for the two keys; `array_map( 'absint', array_keys( array_filter( (array) wp_unslash( … ) ) ) )` for the term ids.
   - **Problem (c), output:**
     - `core/options-pages.php:1582` `echo json_encode( $settings );`. **Fix:** `wp_send_json( $settings );`.
     - `core/import-csv.php:214,217` echo the raw CSV. **Fix:** write to `fopen( 'php://output', 'w' )`.
2. **DEV-207: Installation section is wrong and incomplete.**
   - **Problem:** step 3 points to "Settings > Media", but the settings live under the top-level "VergeLabs Library" menu (`core/admin-menu.php:50`, `core/options-pages.php:126-150`). Installation also does not mention the licence key or trial the AI features need.
   - **Fix:** rewrite step 3 and add a licence/trial step (text in the table row).
3. **DEV-259: Plugin Check would not be clean with annotations ignored.**
   - **Problem:** the result is predicted, not run here. 273 suppression lines hide findings: 104 PreparedSQL, 50 NonceVerification, 3 EscapeOutput.
   - **Fix:** apply the DEV-42 fixes, delete the annotations, and re-run Plugin Check with annotations ignored before upload.

**Checked and clean** (relevant to the two automated rejections):
- No `Update URI` and no custom updater.
- No `nopriv` handlers.
- All 77 REST routes have a capability-based `permission_callback`, none `__return_true`.
- All 18 AJAX and admin-post handlers verify a nonce.
- No `eval`, `unserialize` of input, or remote code.
- The settings import accepts only `is_uploaded_file()` files and goes through the `register_setting` sanitizers.
- The MIME settings refuse executable and HTML/SVG types (`core/mime-types.php:96-103`).
- The ZIP download stays within uploads (`core/folder-tools.php:102-112`).
- Every external call is documented in the readme.

**Advisories (not FAILs):**
- The block is registered in PHP with `api_version` 2 and no `block.json` (DEV-144).
- The licence URI differs between header and readme (DEV-13).
- The webbistro font has no stated licence line (DEV-194).
