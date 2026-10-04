# Audit RM- and FAQ- rows, VergeLabs Media Library 4.1.0

Archive: scratchpad/audit/vergelabs-media-library (143 files, 3.9 MB). Date 2026-10-04. Rows: 220.

| ID | verdict | evidence | fix if FAIL |
|---|---|---|---|
| RM-1 | PASS | readme.txt at archive root, line 1 `=== VergeLabs Media Library ===` |  |
| RM-2 | N/A | Validator not run (needs web form). Known validator warnings: readme 37,289 bytes (RM-32), HTML comment line 214 (RM-4). |  |
| RM-3 | PASS | Header lines 7-8: `Requires at least: 6.5`, `Requires PHP: 7.4`; readme.txt lines 4,6 identical; numeric only |  |
| RM-4 | FAIL | readme.txt:214 `<!-- watch:verified -->` is an HTML block; the rest is `###`/`=` markdown (valid). The list under it is rewritten by a script. | Remove the HTML comment (keep the marker in a non-shipped file or use a plain-text marker). |
| RM-5 | PASS | readme.txt:2 `Contributors: vergelabsdev` - single lowercase username, matches submitting account. Profile case not checkable offline. |  |
| RM-6 | FAIL | Contributors lists only vergelabsdev. Fork of Enhanced Media Library by wpUXsolutions (header lines 17-21, readme.txt:19 credit it in text only). Original wp.org username (wpuxsolutions) not listed; no evidence of a removal request (adoption request sent 2026-09-25, no reply). | Add `wpuxsolutions` to Contributors (verify exact username on the EML plugin page) unless they ask to be removed; or record their refusal. |
| RM-7 | PASS | readme.txt:2 `Contributors: vergelabsdev` - single lowercase username, matches submitting account. Profile case not checkable offline. |  |
| RM-8 | N/A | No `Donate link:` in readme (optional). |  |
| RM-9 | PASS | readme.txt:3 `Tags: media library, media folders, alt text, accessibility, media categories` = 5 tags (limit 5) |  |
| RM-10 | PASS | None of the 5 tags names another plugin (line 3). |  |
| RM-11 | PASS | Tags are generic terms used by other media plugins; none is "vergelabs" or the plugin name. (Directory-side overlap not checkable offline.) |  |
| RM-12 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| RM-13 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| RM-14 | PASS | Header lines 7-8: `Requires at least: 6.5`, `Requires PHP: 7.4`; readme.txt lines 4,6 identical; numeric only |  |
| RM-15 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| RM-16 | PASS | readme.txt:7 `Stable tag: 4.1.0` numbers and periods only, not `trunk`. |  |
| RM-17 | PASS | readme.txt:8 `License: GPLv2 or later` == header line 11 `License: GPLv2 or later` |  |
| RM-18 | PASS | readme.txt:9 and header line 12 both carry a License URI (URLs differ in path, both GPLv2 pages). |  |
| RM-19 | PASS | readme.txt:11 "Folders, categories and tags for the WordPress media library, with AI alt text and captions." = 92 characters, plain text |  |
| RM-20 | PASS | readme.txt:11 "Folders, categories and tags for the WordPress media library, with AI alt text and captions." = 92 characters, plain text |  |
| RM-21 | PASS | `== Installation ==` present (readme.txt:132); AI needs a licence key, explained in External services; no mandatory setup. |  |
| RM-22 | PASS | Non-standard sections: 1 (External services). Description, Installation, FAQ, Screenshots, Upgrade Notice, Changelog are standard. |  |
| RM-23 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-24 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-25 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-26 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-27 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| RM-28 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| RM-29 | PASS | readme.txt:7 `Stable tag: 4.1.0` numbers and periods only, not `trunk`. |  |
| RM-30 | PASS | Last line of FAQ (readme.txt:210-235) is a version-watch bullet, not a bare video URL. |  |
| RM-31 | FAIL | Free header line 4 `Plugin URI: https://vergelabsmedia.com`; Pro plugin pro/vergelabs-media-library-pro.php:4 `Plugin URI: https://vergelabsmedia.com` - identical. | Give the free plugin its own URI (for example the GitHub repo or https://vergelabsmedia.com/media-library) distinct from Pro. |
| RM-32 | FAIL | readme.txt is 37,289 bytes (rule: >10k may error). Changelog ~2,182 words, FAQ compat list, External services ~1,200 words. | Cut the changelog to 4.1.0 (+ short 4.0.x summary), move 4.0.1-4.0.7 to changelog.txt, shorten the 21-line version-watch list, compress External services prose without dropping disclosures. |
| RM-33 | PASS | Description (readme.txt:13-102) states what each feature does; no pricing tables. Risk: FAQ "How is this different from Enhanced Media Library?" (line 154) is a comparison to another plugin. |  |
| RM-34 | PASS | Installation is 3 steps (lines 132-142). FAQ entries are real questions. Reword line 154 entry as "what does it add". |  |
| RM-35 | FAIL | readme changelog lines 261-385 = ~2,182 words: 4.1.0 plus eight 4.0.x entries; changelog.txt holds <4.0. Within current+previous major but far more than "current release". | Keep 4.1.0 and one line for 4.0.x; move 4.0.0-4.0.7 to changelog.txt. |
| RM-36 | FAIL | Readme carries long reference material: 21-line compatibility matrix (210-235), ~1,200-word External services, 2,182-word changelog. | Link the compat matrix and long docs to vergelabsmedia.com; keep External services (required) but compress. |
| RM-37 | PASS | readme.txt lines 1-9 hold the template fields (Contributors, Tags, Requires at least, Tested up to, Requires PHP, Stable tag, License, License URI); Donate omitted (optional). |  |
| RM-38 | PASS | readme.txt:11 "Folders, categories and tags for the WordPress media library, with AI alt text and captions." = 92 characters, plain text |  |
| RM-39 | PASS | `## Description ##` at readme.txt:13. Word count Description + appended External services (lines 13-131): 2,395 words (limit 2,500, margin 105). Description-only 1,197; External services 1,198. Any addition over 105 words trips the cut-off. |  |
| RM-40 | PASS | `## Description ##` at readme.txt:13. Word count Description + appended External services (lines 13-131): 2,395 words (limit 2,500, margin 105). Description-only 1,197; External services 1,198. Any addition over 105 words trips the cut-off. |  |
| RM-41 | PASS | readme.txt:2 `Contributors: vergelabsdev` - single lowercase username, matches submitting account. Profile case not checkable offline. |  |
| RM-42 | PASS | Tags comma separated, relevant (line 3). |  |
| RM-43 | PASS | Floor 6.5: grep found no functions newer than 6.5 (no wp_admin_notice, ability/connector APIs). Not a runtime test on 6.5. |  |
| RM-44 | PASS | 7.1 is current stable; changelog 4.0.x notes the WP 7.0 toolbar fix, so 7.x was exercised. Nightly watch list covers PHP/plugins, not WP core - confirm a 7.1 run exists before tagging. |  |
| RM-45 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| RM-46 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-47 | N/A | SVN state, not in archive. Pre-upload note: tag folder 4.1.0 must contain this exact readme.txt (Stable tag 4.1.0). |  |
| RM-48 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| RM-49 | PASS | Screenshots section readme.txt:237-250: 6 numbered captions; screenshot files not in the archive (correct, belong in SVN /assets). Confirm assets/screenshot-1..6 exist in SVN. |  |
| RM-50 | PASS | Changelog order 4.1.0, 4.0.7 ... 4.0.0 descending; current 4.1.0 present (line 263). |  |
| RM-51 | PASS | Upgrade Notice 4.1.0 = 249 chars, 4.0.0 = 264 chars (limit 300). |  |
| RM-52 | PASS | All readme links use [text](url) form; only bare URL is the License URI header line 9 (header, fine). |  |
| FAQ-1 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-2 | N/A | Zip not built here; extracted tree is 3.9 MB, 143 files, far under 10 MB. |  |
| FAQ-3 | PASS | readme.txt and vergelabs-media-library.php at root; header has Plugin Name, Plugin URI, Description. |  |
| FAQ-4 | PASS | No console.log/var_dump/print_r/error_log/debugger in .php/.js; no .map, no SCSS/JSX source, no tests dir. Nit: SECURITY.md ships (harmless). |  |
| FAQ-5 | FAIL | core/compatibility.php:10 `@TODO: temporary solution` (Elementor shim). No alpha/beta/coming-soon markers in readme or header. | Delete or reword the @TODO comment in core/compatibility.php:10. |
| FAQ-6 | PASS | Archive has no tests/, node_modules/, vendor/, docs/, .git*, package.json, Makefile, build configs. SECURITY.md only. |  |
| FAQ-7 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-8 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-9 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-10 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-11 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-12 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-13 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-14 | PASS | readme.txt present. |  |
| FAQ-15 | N/A | Runtime check, not done in this audit. |  |
| FAQ-16 | PASS | No bundled jquery/lodash/moment/select2; no .min.* files; scripts use WP core handles. Only vendored assets are Inter and webbistro fonts. |  |
| FAQ-17 | PASS | All wp_remote_post/get/fetch calls go to ai.vergelabs.nl or vergelabsmedia.com, user-initiated, each documented in External services (readme.txt:104-130). No third-party hosts, no remote CSS/JS/fonts. |  |
| FAQ-18 | PASS | No admin_footer_text/update_footer filters, no powered-by output. |  |
| FAQ-19 | PASS | Activation hook (vergelabs-media-library.php:291,305) only provisions options/tables, no HTTP. Daily /counts send (core/instrument.php) is off by default and needs a licence key; disclosed in readme Library counts paragraph. |  |
| FAQ-20 | PASS | Header Description and readme short description state concrete functionality. |  |
| FAQ-21 | PASS | No scraping/spinning/black-hat features; AI captions/alt text describe the user own images. |  |
| FAQ-22 | PASS | No eval/exec/shell_exec/system/create_function (only a JS regex .exec at js/vergeml-talk.js:69); AI output is text applied as alt/captions/folder names, not code. |  |
| FAQ-23 | PASS | No plugins_api/Plugin_Upgrader/download_url/unzip_file; no remote code fetched; known-issues list is data. |  |
| FAQ-24 | PASS | Single standalone plugin; no dependency chain, no template. |  |
| FAQ-25 | PASS | All functions/classes/options prefixed vergeml_/VERGEML_; readme.txt:152 states prefixing. |  |
| FAQ-26 | PASS | Fork of EML 2.9.4 with substantial additions, credited in header lines 17-21; GPL copyright intact. Whether the reviewer sees enough differentiation is judgment. |  |
| FAQ-27 | PASS | Readme describes folders, smart folders, Librarian, AI describing: differentiated. |  |
| FAQ-28 | PASS | Version 4.1.0 is a major-line release of the same plugin. |  |
| FAQ-29 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-30 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-31 | PASS | No vulgarity in Plugin Name "VergeLabs Media Library", slug vergelabs-media-library, readme, header. Does not claim to be official; credits EML as origin. |  |
| FAQ-32 | PASS | Slug vergelabs-media-library contains neither "wordpress" nor "plugin". |  |
| FAQ-33 | PASS | No version number in name/slug. |  |
| FAQ-34 | PASS | Slug characters [a-z-] only. |  |
| FAQ-35 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-36 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-37 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-38 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-39 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-40 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-41 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-42 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-43 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-44 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-45 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-46 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-47 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-48 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-49 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-50 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-51 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-52 | PASS | Archive layout: readme.txt and main php at root of vergelabs-media-library/; version 4.1.0 numbers and periods only; assets not in zip. (SVN layout itself not verifiable here.) |  |
| FAQ-53 | N/A | SVN state, not in archive. The archive contains no externals. |  |
| FAQ-54 | N/A | SVN state, not in archive. The archive contains no externals. |  |
| FAQ-55 | PASS | No .zip/.tar/.gz inside the archive. |  |
| FAQ-56 | PASS | No minified JS/CSS: no .min.* files, no lines over 1,500 chars in any .js/.css. |  |
| FAQ-57 | PASS | No minified JS/CSS: no .min.* files, no lines over 1,500 chars in any .js/.css. |  |
| FAQ-58 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-59 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-60 | PASS | Header uses Plugin Name, Plugin URI, Description, Version, Requires at least, Requires PHP, Author, Author URI, Text Domain, Domain Path, License, License URI. |  |
| FAQ-61 | PASS | Header uses Plugin Name, Plugin URI, Description, Version, Requires at least, Requires PHP, Author, Author URI, Text Domain, Domain Path, License, License URI. |  |
| FAQ-62 | PASS | readme.txt:7 `Stable tag: 4.1.0` == main file header `Version: 4.1.0` == VERGEML_VERSION 4.1.0. (Tag folder itself is SVN, outside archive.) |  |
| FAQ-63 | PASS | readme.txt:7 `Stable tag: 4.1.0` numbers and periods only, not `trunk`. |  |
| FAQ-64 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| FAQ-65 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| FAQ-66 | PASS | Version bumped to 4.1.0 in header and constant; readme-only edits need no bump. |  |
| FAQ-67 | PASS | Version bumped to 4.1.0 in header and constant; readme-only edits need no bump. |  |
| FAQ-68 | PASS | `== Changelog ==` present (readme.txt:261). |  |
| FAQ-69 | PASS | Readme changelog holds 4.1.0 down to 4.0.0 (current major); <4.0 moved to changelog.txt (starts 3.16.1) and the readme says so (line 385). Length issue is under RM-35. |  |
| FAQ-70 | PASS | Readme changelog holds 4.1.0 down to 4.0.0 (current major); <4.0 moved to changelog.txt (starts 3.16.1) and the readme says so (line 385). Length issue is under RM-35. |  |
| FAQ-71 | N/A | No video in readme. |  |
| FAQ-72 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| FAQ-73 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| FAQ-74 | PASS | readme.txt:5 `Tested up to: 7.1`, major.minor only. api.wordpress.org version-check on 2026-10-04 lists 7.1.2 as current stable, so 7.1 is released and not above current. |  |
| FAQ-75 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-76 | N/A | Banners/icons are SVN /assets, not in archive. Before upload: icon must be original VergeLabs art, not an EML logo. |  |
| FAQ-77 | N/A | Banners/icons are SVN /assets, not in archive. Before upload: icon must be original VergeLabs art, not an EML logo. |  |
| FAQ-78 | N/A | Banners/icons are SVN /assets, not in archive. Before upload: icon must be original VergeLabs art, not an EML logo. |  |
| FAQ-79 | N/A | Banners/icons are SVN /assets, not in archive. Before upload: icon must be original VergeLabs art, not an EML logo. |  |
| FAQ-80 | PASS | readme.txt:3 `Tags: media library, media folders, alt text, accessibility, media categories` = 5 tags (limit 5) |  |
| FAQ-81 | PASS | readme.txt:3 `Tags: media library, media folders, alt text, accessibility, media categories` = 5 tags (limit 5) |  |
| FAQ-82 | PASS | Tags are generic terms used by other media plugins; none is "vergelabs" or the plugin name. (Directory-side overlap not checkable offline.) |  |
| FAQ-83 | N/A | Slug is permanent once approved; vergelabs-media-library is final. |  |
| FAQ-84 | PASS | Display name identical in header line 2 and readme line 1. |  |
| FAQ-85 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-86 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-87 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-88 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-89 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-90 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-91 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-92 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-93 | N/A | Slug is permanent once approved; vergelabs-media-library is final. |  |
| FAQ-94 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-95 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-96 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-97 | PASS | Display name identical in header line 2 and readme line 1. |  |
| FAQ-98 | PASS | No vulgarity in Plugin Name "VergeLabs Media Library", slug vergelabs-media-library, readme, header. Does not claim to be official; credits EML as origin. |  |
| FAQ-99 | PASS | Plugin Name "VergeLabs Media Library" (header line 2, readme line 1) leads with the author own brand (account vergelabsdev), not a third-party mark; no Woo/WP/Elementor/EML prefix; "Enhanced Media Library" appears only in credit/compat text, never in name or slug. No phonetic/hyphen variants. |  |
| FAQ-100 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-101 | PASS | Name does not contain "WordPress" or "Plugin". |  |
| FAQ-102 | PASS | No TM/R symbol in name. |  |
| FAQ-103 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-104 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-105 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-106 | PASS | Plugin name is 3 words (limit 5). |  |
| FAQ-107 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-108 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-109 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-110 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-111 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-112 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-113 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-114 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-115 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-116 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-117 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-118 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-119 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-120 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-121 | PASS | GPLv2+ licence; EML copyright preserved and credited (header lines 17-21, readme.txt:19). |  |
| FAQ-122 | PASS | GPLv2+ licence; EML copyright preserved and credited (header lines 17-21, readme.txt:19). |  |
| FAQ-123 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-124 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-125 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-126 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-127 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-128 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-129 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-130 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-131 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-132 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-133 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-134 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-135 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-136 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-137 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-138 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-139 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-140 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-141 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-142 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-143 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-144 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-145 | FAIL | Contributors lists only vergelabsdev. Fork of Enhanced Media Library by wpUXsolutions (header lines 17-21, readme.txt:19 credit it in text only). Original wp.org username (wpuxsolutions) not listed; no evidence of a removal request (adoption request sent 2026-09-25, no reply). | Add `wpuxsolutions` to Contributors (verify exact username on the EML plugin page) unless they ask to be removed; or record their refusal. |
| FAQ-146 | PASS | Only vergelabsdev listed; no supporters or non-developers. |  |
| FAQ-147 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-148 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-149 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-150 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-151 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-152 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-153 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-154 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-155 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-156 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-157 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-158 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-159 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-160 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-161 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-162 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-163 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-164 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-165 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-166 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-167 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |
| FAQ-168 | N/A | Process / account / policy row with no artifact in the release archive (support, closure, transfer, adoption, SLA or reporting guidance). |  |

Counts: {"PASS":109,"FAIL":8,"N/A":103}

## FAILS

### RM-4
Evidence: readme.txt:214 `<!-- watch:verified -->` is an HTML block; the rest is `###`/`=` markdown (valid). The list under it is rewritten by a script.
Fix: Remove the HTML comment (keep the marker in a non-shipped file or use a plain-text marker).

### RM-6
Evidence: Contributors lists only vergelabsdev. Fork of Enhanced Media Library by wpUXsolutions (header lines 17-21, readme.txt:19 credit it in text only). Original wp.org username (wpuxsolutions) not listed; no evidence of a removal request (adoption request sent 2026-09-25, no reply).
Fix: Add `wpuxsolutions` to Contributors (verify exact username on the EML plugin page) unless they ask to be removed; or record their refusal.

### RM-31
Evidence: Free header line 4 `Plugin URI: https://vergelabsmedia.com`; Pro plugin pro/vergelabs-media-library-pro.php:4 `Plugin URI: https://vergelabsmedia.com` - identical.
Fix: Give the free plugin its own URI (for example the GitHub repo or https://vergelabsmedia.com/media-library) distinct from Pro.

### RM-32
Evidence: readme.txt is 37,289 bytes (rule: >10k may error). Changelog ~2,182 words, FAQ compat list, External services ~1,200 words.
Fix: Cut the changelog to 4.1.0 (+ short 4.0.x summary), move 4.0.1-4.0.7 to changelog.txt, shorten the 21-line version-watch list, compress External services prose without dropping disclosures.

### RM-35
Evidence: readme changelog lines 261-385 = ~2,182 words: 4.1.0 plus eight 4.0.x entries; changelog.txt holds <4.0. Within current+previous major but far more than "current release".
Fix: Keep 4.1.0 and one line for 4.0.x; move 4.0.0-4.0.7 to changelog.txt.

### RM-36
Evidence: Readme carries long reference material: 21-line compatibility matrix (210-235), ~1,200-word External services, 2,182-word changelog.
Fix: Link the compat matrix and long docs to vergelabsmedia.com; keep External services (required) but compress.

### FAQ-5
Evidence: core/compatibility.php:10 `@TODO: temporary solution` (Elementor shim). No alpha/beta/coming-soon markers in readme or header.
Fix: Delete or reword the @TODO comment in core/compatibility.php:10.

### FAQ-145
Evidence: Contributors lists only vergelabsdev. Fork of Enhanced Media Library by wpUXsolutions (header lines 17-21, readme.txt:19 credit it in text only). Original wp.org username (wpuxsolutions) not listed; no evidence of a removal request (adoption request sent 2026-09-25, no reply).
Fix: Add `wpuxsolutions` to Contributors (verify exact username on the EML plugin page) unless they ask to be removed; or record their refusal.
