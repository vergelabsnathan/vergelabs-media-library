# Audit: HB- rows (Plugin Review handbook), VergeLabs Media Library 4.1.0

Archive: `scratchpad/audit/vergelabs-media-library/` (143 files). Paths below are relative to it. phpcs:ignore comments were ignored.

**Coverage caveat.** A background line-by-line security sweep (77 REST routes, every AJAX/admin-post handler, every superglobal, SQL and echo) did not report back before handback. Rows that depend on it are marked **UNVERIFIED**, with the evidence I checked myself. Runtime rows (WP_DEBUG, php -l, console) are also **UNVERIFIED**: there is no PHP binary on this machine and none was run.

| ID | verdict | evidence | fix if FAIL |
|---|---|---|---|
| HB-1 | N/A | Reviewer process (guidelines applied literally) | |
| HB-2 | N/A | Reviewer process (closure reasons go to the owner only) | |
| HB-3 | N/A | Conduct, not code | |
| HB-4 | N/A | Reviewer process (fix every instance, not just the quoted lines) | |
| HB-5 | PASS | readme.txt:3 has 5 accurate tags; no keyword stuffing | |
| HB-6 | PASS | No bundled jQuery in js/ (39 files, all own/EML); no `wp_deregister_script('jquery')` | |
| HB-7 | N/A | Reviewer process | |
| HB-8 | N/A | Reviewer process | |
| HB-9 | N/A | Reviewer process | |
| HB-10 | N/A | Reviewer process | |
| HB-11 | N/A | Account vetting | |
| HB-12 | FAIL | Subject OK. Security: see HB-60/147 (UNVERIFIED). Quality/consistency: see HB-53, HB-68, HB-70 | See those rows |
| HB-13 | N/A | Reviewer process | |
| HB-14 | N/A | Reviewer process | |
| HB-15 | PASS | 143 files, no .git/.env/node_modules/tests; unminified sources only | |
| HB-16 | N/A | Reviewer process | |
| HB-17 | PASS | grep `wp-(con\|blog\|load)`: only comments and strings, no require (core/ai.php:80, readme:126) | |
| HB-18 | PASS | Writes go only to `wp_tempnam()` (core/ai.php:1218, core/folder-tools.php:243); nothing in the plugin dir | |
| HB-19 | PASS | No jquery*.js in the ZIP | |
| HB-20 | PASS | No CDN enqueue; CSS `url()` local only (css/eml-admin.css:183-197, css/vergeml-shell.css:41,50) | |
| HB-21 | PASS | vergelabsmedia.com, vergelabs.nl (vergelabs-media-library.php:4,10) | |
| HB-22 | N/A | Reviewer process | |
| HB-23 | N/A | Reviewer process | |
| HB-24 | N/A | Reviewer process | |
| HB-25 | N/A | Reviewer process | |
| HB-26 | N/A | Reviewer process | |
| HB-27 | PASS | "VergeLabs Media Library", own brand first (main:3, readme:1) | |
| HB-28 | N/A | Reviewer step; file list checked under HB-15 | |
| HB-29 | FAIL | Every file listed. Findings are in the rows marked FAIL | See FAILS |
| HB-30 | PASS | readme:104-130 External services names the service and its endpoints | |
| HB-31 | UNVERIFIED | Not run: no PHP/Playground here | Install the ZIP fresh with WP_DEBUG on; activate, open every screen, deactivate, uninstall |
| HB-32 | N/A | Reviewer process | |
| HB-33 | N/A | Reviewer process | |
| HB-34 | N/A | Conduct | |
| HB-35 | FAIL | Fails listed below | Fix all FAILs |
| HB-36 | PASS* | Tested up to 7.1, Requires 6.5 (readme:4-5, main:7). *Check that 7.1 is the current major on the upload date | |
| HB-37 | PASS | Large additions (folders, AI, importer, librarian: core/ has 66 files vs EML 2.9.4's 6); readme FAQ:154-162 says what changed | |
| HB-38 | PASS | Author VergeLabs; Contributors vergelabsdev (main:9, readme:2) | |
| HB-39 | PASS | No banner/icon assets; images/ holds two core spinner GIFs; menu icon is a plain SVG (core/admin-menu.php:109) | |
| HB-40 | PASS | No `wp_insert_post`; `wp_update_post` only on attachment titles/user actions (core/rename.php:210) | |
| HB-41 | PASS | No income/ranking promises; performance figures are measured | |
| HB-42 | PASS | A working plugin, not a framework | |
| HB-43 | PASS | No instruction to edit a theme or plugin | |
| HB-44 | PASS | Not a storefront | |
| HB-45 | PASS | Extends core media (folders, filters); readme states what it adds | |
| HB-46 | PASS | MIME types: refused list blocks php/html/svg/js and others (core/mime-types.php:119-127); upload_mimes only toggles core-known types (:277-298); no raw code fields found | |
| HB-47 | N/A | Reviewer process | |
| HB-48 | PASS | GPLv2+ header (main:13-25); EML GPL; Inter OFL 1.1 (font, accepted); natural-compare-lite MIT (compatible) | |
| HB-49 | PASS | readme:8-9 License + License URI | |
| HB-50 | FAIL | Bundled fonts/images not declared anywhere: Inter woff2 (fonts/), webbistro icon font (fonts/webbistro.*), spinner GIFs. fonts/INTER-LICENSE.txt:1 reads "Google Inc." instead of the Inter copyright | Add a readme "Credits" section: Inter, SIL OFL 1.1, © The Inter Project Authors (github.com/rsms/inter); webbistro icon font from EML, GPLv2+; spinners from WordPress core, GPLv2+. Fix line 1 of INTER-LICENSE.txt to the real Inter copyright |
| HB-51 | FAIL | js/eml-media-models.js:265-312 copies natural-compare-lite (MIT) without its copyright/licence notice. css/vergeml-shell.css:20 says "Structure copied from WP Rocket's own wpr-admin.css" with no credit | Add the natural-compare-lite MIT notice above the function. Credit WP Rocket (GPLv2+) in the comment, or record the values as your own measurements |
| HB-52 | PASS | Original copyright kept (main:16-20); readme:19 and FAQ:156 credit EML; credits box (core/options-pages.php:3379) | |
| HB-53 | FAIL | readme:138 "Adjust the settings under Settings > Media". The settings are under a top-level "VergeLabs Library" menu (core/admin-menu.php:50; submenus in core/options-pages.php:117-150). readme:42 "Settings → Import Folders" is wrong too (core/import-ui.php:22, parent VERGEML_MENU). Installation does not mention the licence | Rewrite Installation: "VergeLabs Library in the admin menu → Media Library / Taxonomies / MIME Types / Import Folders; AI features: VergeLabs Library → Licence → Connect, or Try it free on the AI screen". Fix readme:42 the same way |
| HB-54 | PASS | Every host found in code is in the readme: ai.vergelabs.nl /v1 (core/ai.php:313; /counts, /licence, /file, /folders, /plan-tree, /guide/session, /known-issues, /support/ticket) and vergelabsmedia.com /api/trial and /api/connect (core/offer.php:160, core/connect.php:161). Browser streaming is disclosed (readme:116) | |
| HB-55 | PASS | readme:128-130 links terms, privacy, DPA, sub-processors | |
| HB-56 | PASS | readme:200-202 support channel | |
| HB-57 | UNVERIFIED | Not run: no PHP binary for php -l; no browser pass | Run php -l on all PHP and open each screen with the console open |
| HB-58 | UNVERIFIED | Not run | Do a WP_DEBUG walk (Playground) |
| HB-59 | UNVERIFIED | Not run | Run with Query Monitor and check HTTP calls on page load |
| HB-60 | UNVERIFIED | Spot checks pass: licence save cap+nonce+sanitize (core/licence-page.php:37-63); trial nonce+cap+sanitize_email (core/offer.php:149-157); zip cap+nonce+sanitize_key (core/folder-tools.php:229-237); bridge nonce+cap (core/transport.php:40-53). Full sweep not completed | Finish the full sweep. Note core/folder-tools.php:256: the header filename uses the term slug (safe) |
| HB-61 | FAIL | Unprefixed names: `delete_site_transient( 'eml_transient' )` deletes EML's transient (main:~521); settings-error codes `eml_mime_types_restored` and `eml_settings_*` (core/mime-types.php:33,60; core/options-pages.php:1536-1723); CSS class `wpuxss-non-eml-taxonomy` (core/options-pages.php:2766,2956). Everything else seen uses vergeml_/VERGEML_/vgml | Rename to `vergeml_*`; drop the `eml_transient` delete (it is another plugin's data) |
| HB-62 | PASS | Header fields all present; Stable tag 4.1.0 = Version 4.1.0 = VERGEML_VERSION (readme:7, main:6,35); short description 92 chars; description 2,395 words | |
| HB-63 | PASS | No writes to the plugin dir (see HB-18) | |
| HB-64 | PASS* | Uses wp_remote_*, REST, WP_Filesystem_Direct (core/rename-file.php:367-380). *Minor: `json_encode` at core/options-pages.php:1582 and core/taxonomies.php:1159 (use wp_json_encode). Requests::request_multiple bypasses WP_Http (core/ai.php:896), documented, falls back to sequential behind a proxy | Use wp_json_encode |
| HB-65 | FAIL | 60+ relative includes: `include_once( 'core/system-report.php' )` and the rest (vergelabs-media-library.php:1527-1680). These resolve through include_path, not the plugin dir | `include_once plugin_dir_path( __FILE__ ) . 'core/…';` (or `__DIR__ . '/core/…'`) |
| HB-66 | PASS | No output-buffer rewriting; the post_content rewrite on file rename is behind VERGEML_FILE_RENAME (core/rename-file.php:447) | |
| HB-67 | PASS | Adds to core; does not duplicate it | |
| HB-68 | FAIL | Header Description (main:5) "Categories, tags and custom taxonomies…MIME type management…grid filters" leaves out folders and the AI service it connects to; the readme short description (readme:11) does mention AI | Make header Description match readme:11 (folders + AI alt text via the VergeLabs service) |
| HB-69 | PASS | Integrations guarded: class_exists/function_exists (core/gallery-widgets.php:142,253,410; core/page-builders.php:184-303); Polylang/WPML only through hooks (core/multilingual.php:122-156) | |
| HB-70 | FAIL | Licence screen says "Images go to the service only to be described; nothing else leaves your site." (core/licence-page.php:147). That is false: folder names, captions, typed text, system report and plugin list, and counts also go (readme:110-124). core/help.php:93 "nothing is sent anywhere until you…start a run" is also wrong: saving a key calls /licence. The trial form (core/offer.php:91-101) does not say the email goes to vergelabsmedia.com and has no privacy link | Replace with accurate text, e.g. "Pictures, their titles and captions, folder names and what you type go to VergeLabs when you start a run. See External services." Add "Your email and site address go to vergelabsmedia.com (privacy)" under the trial input |
| HB-71 | PASS | See HB-54; no update checker; loopback to own wp-cron only (core/ai-background.php:221) | |
| HB-72 | PASS | Licence screen explains Connect and paste-a-key (core/licence-page.php:199-220); readme:118-120 | |
| HB-73 | PASS | No Theme Name header; no theme upgrader | |
| HB-74 | PASS | Only one `Plugin Name:` (main:3; core/ai-screen.php hit is a string); no Plugin_Upgrader/activate_plugin | |
| HB-75 | PASS | Front-end CSS scoped (css/vergeml-gallery.css: no unscoped selectors); no template_include | |
| HB-76 | PASS | No writes to theme or other plugins | |
| HB-77 | FAIL | Nothing phones home before consent: telemetry is opt-in (core/instrument.php:189,255); known issues load on a button press (readme:122). But consent is not *informed* on the Licence screen (see HB-70) | Same fix as HB-70 |
| HB-78 | PASS | `opted` defaults to 0 (core/instrument.php:47); without opt-in nothing is collected; opting out deletes the snapshot (:293-297) | |
| HB-79 | UNVERIFIED | Bridge passes raw php://input params to rest_do_request but limits the route to its own namespace (core/transport.php:55-75); route args validate. Remote responses are sanitized before storing (core/connect.php:197). Full sweep not done | Finish the sweep |
| HB-80 | UNVERIFIED | Screens print helper markup through wp_kses with an allow-list (main:141-189); spot-checked files escape. Full echo sweep not done | Finish the sweep |
| HB-81 | PASS | grep for bit.ly/goo.gl/t.co/tinyurl/ow.ly: none | |
| HB-82 | UNVERIFIED | uninstall.php uses prepare with %d lists (:64-102); instrument.php:79-96 static or prepared. Full $wpdb sweep not done | Finish the sweep |
| HB-83 | PASS | Buy/trial links only on own screens (core/offer.php, core/licence-page.php:149); plugin_row_meta has Docs/Support only (main:1493-1494); notices are scoped to media/own screens (core/neighbours.php:84-90) | |
| HB-84 | PASS | Front-end gallery outputs no credit link (core/gallery-block.php, core/gallery-widgets.php) | |
| HB-85 | PASS | No sponsored links | |
| HB-86 | PASS | No ad scripts | |
| HB-87 | PASS | No affiliate links | |
| HB-88 | PASS | UTM only to own domain (core/connect.php:51-60) | |
| HB-89 | PASS | No literal `<script src>/<link>/<style>`; inline JS through wp_add_inline_script (main:107-113, core/transport.php:141); `<script type="text/html">` are Backbone templates (core/media-templates.php:19,93) | |
| HB-90 | PASS | No GA/gtag/pixels | |
| HB-91 | PASS | No .min files; longest JS line 284 chars | |
| HB-92 | PASS | No packer/eval; base64_decode only for sealed-key data and packed floats (core/ai.php:353, core/organize.php:2415) | |
| HB-93 | PASS | Deps use core handles (wp-api-fetch, wp-i18n, jquery, backbone, media-views) | |
| HB-94 | PASS | All JS/CSS/fonts/images local | |
| HB-95 | FAIL (recommended) | Inputs with no label: trial email (core/offer.php:98), licence key (core/licence-page.php:222), say box (core/nl-commands.php:659), help email (core/get-help.php:364, check for a label) | Add `<label for>` or aria-label |
| HB-96 | PASS* | Text domain = slug in 1,786 calls; wp_set_script_translations (core/ai-screen.php:78, core/guide.php:89,140, core/brief.php:761). *Untranslated strings: core/transport.php:59,68 | Wrap them in __() |
| HB-97 | PASS | Requires PHP 7.4 (main:8); no 8.x syntax; str_contains is polyfilled by WP 5.9+ | |
| HB-98 | PASS | No hard dependency; optional integrations silently skip | |
| HB-99 | PASS* | HTTPS defaults; sslverify true everywhere (14 sites); key goes in the JSON body. *Overrides VERGEML_AI_STREAM / VERGEML_SITE_URL / VERGEML_KNOWN_ISSUES_URL are not forced to https (core/guide.php:857, core/connect.php:39, core/get-help.php:58); only VERGEML_AI_SERVICE is (core/ai.php:315) | Optionally apply the https check to all overrides |
| HB-100 | FAIL | readme says credits are spent but gives no sign-up/pricing path (no pricing URL, no "how to get a licence" step) | Add to Description/FAQ: "Get a licence or credits at https://vergelabsmedia.com/pricing; or Try it free (25 pictures) on the AI screen" |
| HB-101 | PASS | No iframes | |
| HB-102 | PASS | See HB-17 | |
| HB-103 | PASS | No third-party brand in name/slug | |
| HB-104 | PASS | Credits re-check at most every 5 min, only with a key (readme:120); everything else on user action | |
| HB-105 | PASS | Talks only to its own service | |
| HB-106 | PASS | See HB-78 | |
| HB-107 | PASS | No `<?=` or short open tags | |
| HB-108 | PASS | See HB-48 | |
| HB-109 | N/A | Account process | |
| HB-110 | N/A | Account process | |
| HB-111 | N/A | Reviewer process (rounds 1-2 will be re-read; previous points must be fixed) | |
| HB-112 | N/A | Forum status | |
| HB-113 | N/A | Discretion | |
| HB-114 | PASS | Shortcode `vergeml_gallery` (core/gallery-widgets.php:117) adds folder galleries; extends [gallery], does not mirror it | |
| HB-115 | PASS* | Uses core REST. *The admin-ajax bridge (core/transport.php) tunnels REST past a site's REST blocking; a reviewer may question it | Be ready to justify it, or offer a setting to turn it off |
| HB-116 | PASS | Not a storefront | |
| HB-117 | PASS | No auto-posting | |
| HB-118 | PASS | See HB-41 | |
| HB-119 | PASS | No wp_mail loops | |
| HB-120 | PASS | No user creation, eval, assert or remote include | |
| HB-121 | PASS | Benign subject | |
| HB-122 | PASS | Benign subject | |
| HB-123 | PASS | Not a 100% copy | |
| HB-124 | N/A | Applies only to copies; an adoption ticket exists anyway (2026-09-25) | |
| HB-125 | N/A | Discretion | |
| HB-126 | PASS | Slug vergelabs-media-library = folder = Text Domain | |
| HB-127 | PASS | No trademark conflict | |
| HB-128 | PASS | First term is the own brand | |
| HB-129 | PASS | URIs on own domains | |
| HB-130 | N/A | Reviewer process | |
| HB-131 | N/A | Account process | |
| HB-132 | PASS | No typos | |
| HB-133 | PASS | No version numbers or profanity | |
| HB-134 | PASS | Name has no wordpress/plugin/theme | |
| HB-135 | N/A | Reviewer process | |
| HB-136 | PASS | readme.txt present | |
| HB-137 | PASS | readme present | |
| HB-138 | FAIL | How to join/register is spread across External services; no plain step (see HB-100) | Same fix as HB-53/HB-100 |
| HB-139 | FAIL | Wrong menu paths in Installation and readme:42 (see HB-53) | Same fix as HB-53 |
| HB-140 | N/A | Reviewer method (this audit read code; full line-by-line security sweep pending) | |
| HB-141 | PASS | See HB-17 | |
| HB-142 | UNVERIFIED | Spot-checked handlers sanitize (see HB-60); full $_POST/$_GET sweep pending | Finish the sweep |
| HB-143 | UNVERIFIED | Global functions seen are vergeml_-prefixed (main, uninstall.php:29,47); full `function ` sweep pending | |
| HB-144 | N/A | Reviewer method | |
| HB-145 | PASS | No user-code execution; file renames gated (VERGEML_FILE_RENAME) | |
| HB-146 | PASS | No PHP-in-posts | |
| HB-147 | UNVERIFIED | Named callbacks manage_categories/manage_options (main:122-128); stats manage_options (core/instrument.php:274); bridge upload_files plus the route's own callback (core/transport.php:51). 77 routes not all matched to what each one writes; round 2 failed exactly here | Finish the per-route check (cap vs write) |
| HB-148 | PASS | uninstall.php:3 `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;` | |
| HB-149 | FAIL | Housekeeping clears only 2 of 7 cron hooks (uninstall.php:42-43). Left behind: vergeml_talk_refile_event, vergeml_guide_fit_event, vergeml_plan_event, vergeml_plan_inventory_refresh, vergeml_provision_site. The opt-in wipe ("everything gone", readme:198) leaves `_vergeml_*` post meta and `vergeml_color`/`vergeml_order` term meta on taxonomies it does not own, plus `_site_transient_vergeml_*`. Complete Cleanup has the same 2-hook gap (core/options-pages.php:1790) | Clear all 7 hooks in both places. In the wipe, delete postmeta/termmeta `LIKE '\_vergeml\_%'` and term meta `vergeml_color`/`vergeml_order`, plus site transients |
| HB-150 | PASS | No register_deactivation_hook; nothing deleted on deactivation | |
| HB-151 | FAIL | See HB-61 | See HB-61 |
| HB-152 | N/A | No shared PHP libraries bundled | |
| HB-153 | UNVERIFIED | See HB-60 | |
| HB-154 | UNVERIFIED | No strip_tags/json_encode-as-sanitizer seen in spot checks; sweep pending | |
| HB-155 | UNVERIFIED | See HB-60/79/80 | |
| HB-156 | UNVERIFIED | Spot checks sanitize before update_option (core/licence-page.php:61-63); sweep pending | |
| HB-157 | UNVERIFIED | Nonces on the handlers checked (licence, trial, zip, bridge, connect state with hash_equals core/connect.php:152-157); all wp_ajax_/admin_post_ not enumerated | |
| HB-158 | UNVERIFIED | Handlers checked pair nonce with current_user_can; full list pending | |
| HB-159 | PASS | No bundled or remote core libraries | |
| HB-160 | PASS | No remote offload | |
| HB-161 | PASS | Core handles + plugins_url (core/admin-menu.php:336) | |
| HB-162 | PASS | Only the service API is remote, and it is disclosed | |
| HB-163 | PASS | No jQuery UI CSS from a CDN | |
| HB-164 | PASS | No help iframes | |
| HB-165 | PASS | No remote images | |
| HB-166 | PASS | Fonts are local (stricter than required) | |
| HB-167 | PASS | Service API calls disclosed | |
| HB-168 | PASS | Disclosed (readme:110-116) | |
| HB-169 | N/A | No oEmbed use | |
| HB-170 | PASS | See HB-18 | |
| HB-171 | PASS | Temp files only; no persistent files written | |
| HB-172 | PASS | Temp files deleted on success and error (core/ai.php:1225-1229; core/folder-tools.php:207,249,262) | |
| HB-173 | PASS* | No libraries; Inter version unknown (woff2 only) | Note the Inter version in Credits |
| HB-174 | PASS | No unmaintained libraries | |
| HB-175 | PASS | grep timthumb/csstidy/titan/magnific: none | |
| HB-176 | N/A | No Bootstrap-type library | |
| HB-177 | N/A | Reviewer process | |
| HB-178 | PASS | See HB-48 | |
| HB-179 | N/A | Reviewer process | |
| HB-180 | PASS | See HB-128 | |
| HB-181 | N/A | Reviewer process | |
| HB-182 | PASS | Not branded with a third party; FAQ:156 says it is not affiliated with wpUXsolutions | |
| HB-183 | N/A | Reviewer process | |
| HB-184 | N/A | Reviewer process | |
| HB-185 | UNVERIFIED | Bridge is open to upload_files users (Author); depends on each route's callback (HB-147) | |
| HB-186 | N/A | Conduct | |
| HB-187 | N/A | Conduct | |
| HB-188 | N/A | Conduct | |
| HB-189 | N/A | Conduct | |
| HB-190 | N/A | Reviewer process | |
| HB-191 | PASS | No review prompts or incentives (grep wordpress.org/support/plugin + reward words: none) | |
| HB-192 | PASS | Same as HB-191 | |
| HB-193 | N/A | Reviewer process | |
| HB-194 | N/A | Reviewer process | |
| HB-195 | N/A | Reviewer process | |
| HB-196 | N/A | Reviewer process | |
| HB-197 | N/A | Reviewer process | |
| HB-198 | N/A | SVN, not in archive | |
| HB-199 | PASS | 4.1.0 in all three places | |
| HB-200 | N/A | Reviewer process | |
| HB-201 | FAIL | Would not pass today: see FAILS | |
| HB-202 | N/A | SVN process | |
| HB-203 | N/A | SVN filters | |
| HB-204 | N/A | Reviewer process | |
| HB-205 | UNVERIFIED | Depends on HB-147/60/80/82 | |
| HB-206 | PASS | GPL-compatible; the free plugin's non-AI features are not licence-gated (readme:108) | |
| HB-207 | N/A | Conduct | |
| HB-208 | N/A | Account | |
| HB-209 | N/A | Account | |
| HB-210 | N/A | Reviewer process | |
| HB-211 | N/A | Conduct | |
| HB-212 | N/A | Conduct | |
| HB-213 | PASS | See HB-5 | |
| HB-214 | PASS | Competitor names appear only as real import sources (readme:42) | |
| HB-215 | N/A | SVN history | |
| HB-216 | N/A | Reviewer process | |
| HB-217 | N/A | Reviewer process | |
| HB-218 | PASS | No `global $tag`; globals are core or vergeml_ | |
| HB-219 | PASS | No http enqueue | |
| HB-220 | PASS | No known library filenames (datepicker etc.) | |

## FAILS

1. **HB-50**: undeclared font/image licences, and a wrong copyright line in INTER-LICENSE.txt. Fix: add a readme Credits section and correct line 1.
2. **HB-51**: natural-compare-lite (MIT) notice missing (js/eml-media-models.js:265); "copied from WP Rocket" comment has no credit (css/vergeml-shell.css:20).
3. **HB-53 / HB-139**: readme sends users to Settings > Media and Settings → Import Folders. The real menu is the top-level VergeLabs Library. Installation says nothing about the licence.
4. **HB-61 / HB-151**: unprefixed `eml_transient` delete (main ~521; another plugin's data), `eml_*` settings-error codes, `wpuxss-*` classes.
5. **HB-65**: 60+ relative `include_once( 'core/…' )` (main:1527-1680). Use plugin_dir_path(__FILE__).
6. **HB-68**: header Description omits folders and the AI service.
7. **HB-70 / HB-77**: Licence screen says "nothing else leaves your site" (core/licence-page.php:147), which is false. core/help.php:93 is also inaccurate, and the trial form has no disclosure.
8. **HB-95** (recommended): unlabeled inputs (core/offer.php:98, core/licence-page.php:222, core/nl-commands.php:659).
9. **HB-100 / HB-138**: no plain registration/pricing path in the readme.
10. **HB-149**: uninstall clears 2 of 7 cron hooks; the opt-in wipe leaves `_vergeml_*` post/term meta and site transients.
11. Roll-ups: HB-12, HB-29, HB-35, HB-201 fail because of the items above.

**UNVERIFIED (must close before upload):** HB-31, 57-60, 79, 80, 82, 142, 143, 147, 153-158, 185, 205. These are the security sweep (each of the 77 REST routes against what it writes, every AJAX/admin-post nonce+cap, superglobals, SQL, escaping) and the runtime WP_DEBUG walk. Round 2 was rejected on REST permissions, so HB-147 is the riskiest open row.
