# Readme draft, round 6: notes

Draft: `docs/readme-draft-round6.txt`. Changelog draft: `docs/changelog-draft-round6.txt`. `readme.txt` and `changelog.txt` are untouched.

## Byte counts

| File | Old | New |
|---|---|---|
| readme (`readme.txt` -> draft) | 27,161 | 18,997 |
| changelog (`changelog.txt` -> draft) | 28,093 | 31,533 |

The readme is 8,164 bytes smaller (30%). It is not at 16 KB because the External services section is 7,543 bytes of it (40%) and is kept word for word. A script checks that it is byte-identical to the old section, and that the header block is too. Everything else is 11,454 bytes.

Levers if you want to go further (none applied):
- Drop the "checked against" list (932 bytes). `tools/watch/triage.mjs` then edits nothing, because it looks for the line "What passed is recorded here, newest first:" followed by a blank line.
- Drop the Librarian section (745).
- Drop Screenshots (580). The six images are in `assets/`, so they would show without captions.
- Tighten External services wording (about 100 bytes available without losing a fact). Not worth it.

## Corrections made

- a. "PHP 7.4 through 8.3" is now "PHP 7.4 through 8.5". The readme's own list says "PHP 8.5.11 -- contract intact (2026-09-25)". `docs/compatibility.md` shows nine-step passes on PHP 7.4, 8.2 and 8.5 for WP 6.5.11, 7.0.5 and 7.1.1. "Contract intact" is a hook-and-field grep, not a suite run. If you want no upper bound, use "PHP 7.4 and later".
- b. The crash FAQ now says WordPress's recovery mode handles a fatal and the plugin never deactivates itself. It then gives the `VERGEML_SAFE_MODE` line and says what is off (folders, media views, AI), what stays (System report, Get help), that nothing is deleted, that a dashboard notice says so, and that removing the line switches it back on. Checked against `core/watchdog.php`.
- c. The changelog line no longer mentions crashes or a Deactivate button: "The plugin never deactivates itself. Delete All Data no longer deactivates the plugin either; ...".
- d. The uninstall FAQ, second paragraph: "a switch that makes deleting the plugin take all its data" is now: both wipes keep the folders (the `media_category` terms and which files are in them), because folders are ordinary terms shared with other folder plugins; they remove the plugin's own settings, tables and other data. Checked against `uninstall.php`.
- e. Five lines added to 4.1.0: tables made on activation; mbstring and OpenSSL checked with a notice; scripts and styles only on the plugin's screens and media frames; safe mode switched only in wp-config.php; the wipes keep your folders.

## Moves

- `changelog.txt` already existed with 4.0.7 and older, and the readme already held only 4.1.0. So the changelog draft is that file verbatim from 4.0.7 down, with the full 4.1.0 entry (with c and e) on top and a new header ("changelog" instead of "earlier versions"). 4.1.0 is therefore in both files; replace the readme entry when 4.1.1 ships.
- "It is not made by, or affiliated with, wpUXsolutions" moved from the FAQ "What is it built on?" into the description.

## Cuts, description

- Folders intro: cut "-- which is where a media library is most used".
- More than one folder: cut the Products/Autumn Campaign example and the sentence about the setting that makes every drop a move.
- Folder gallery: cut "Grid or carousel, with a built-in lightbox" and the "stays a folder rather than a list of files" sentences.
- Cut the paragraph "One set of folders in every language" (Polylang/WPML).
- Smart folders: cut "a click, a progress count, done", the list of what counts as referenced, and the "1,400 images / 2 GB" sentence.
- Cut the paragraph "Uploads land where you are".
- Cut the paragraph "A folder can leave as a ZIP".
- Import: cut "says which folders will merge with ones you already have" and "Nothing is taken from the other plugin ... go back at any time".
- Librarian, first paragraph: cut the folder counts, thumbnails and "why grouped" line.
- Librarian, Undo: cut everything after the first sentence (moved files reported, folders deleted only if empty, only touches unfiled files).
- Cut the Librarian paragraphs "And once it has looked, the descriptions become folders" and "Nothing that cannot be interrupted".
- Merged Categorize, Configurable Filters, The media grid, Dynamic Galleries, MIME and Developer-Friendly into one "More" list of three bullets.
- Categorize bullets: three became one ("custom and third-party taxonomies").
- Cut the whole Configurable Filters section and The media grid section (six bullets).
- Cut the Dynamic Galleries section (`media_category`, `monthnum`, `year`, `limit`).
- MIME: kept the sentence about types that cannot be allowed; cut "and add any of them to the media filters".
- Developer-Friendly: cut "Core hooks just work", "All taxonomies supported", "REST API supported" and "Deactivation makes no harm to data". Kept the ordinary-terms and four-tables bullet.

## Cuts, install and FAQ

- Installation: blank lines between steps removed; no words changed.
- FAQ cut: "Will my EML settings carry over?" (the description says so).
- FAQ cut: "Can I run this alongside EML?" (Installation says deactivate it first).
- FAQ cut: "What is it built on?" (the feature list, the upstream documentation link; the disclaimer moved).
- FAQ cut: "Can I run it next to another folder plugin?" (Import Folders is in the description). The "listed near the end of this page" pointer went with it.
- FAQ cut: "Can I take my settings to another site?"
- FAQ cut: "Does it send anything to an external service?" The three paragraphs restate External services, which sits directly above.
- AI service down: kept the first sentence; cut the retry timing, the failed-picture rule, the word-search fallback and the credit-balance note.
- Security bugs: cut "The Patchstack team will assist you with verification, CVE assignment, and notify the developers".
- Problem reporting: cut "which is read by the person who maintains the plugin".
- Credits: all five items kept; wording shortened ("the natural sort in" cut, and the WP Rocket and spinner lines reordered).
- Uninstall, first paragraph: unchanged. Second paragraph rewritten per d.

## Cuts, checked-against list

Kept the intro and the marker sentence, plus seven lines (Yoast, Polylang, Beaver Builder, Elementor, PHP 8.5.11, WooCommerce, Rank Math). Cut, verbatim:
- Dokan 5.1.1 -- contract intact, stage suites passed (2026-09-10)
- Brizy 2.8.23 -- contract intact, stage suites passed (2026-09-09)
- Divi 5.11.0 -- Folder gallery module renders from a built page (2026-09-01)
- Polylang Pro 3.8.6 -- a translated image keeps its folders (2026-09-01)
- Yoast SEO 28.4, Rank Math 1.0.277.2, SEOPress 10.1, All in One SEO 5.0.1.1 -- page context reaches the describe request (2026-09-01)
- WooCommerce, Advanced Custom Fields -- product context and duplicate repoint (2026-09-01)

## Cuts, screenshots, notice and changelog

- Screenshot 1 caption: cut ", plus Reset All Filters and Bulk select".
- Upgrade Notice: cut the price formula and the credits-back clause. The "Plan my folders" line in the changelog has the price.
- Readme 4.1.0, "Plan my folders": cut "and it needs at least five described pictures".
- Readme 4.1.0, "A plan that does not beat your folders": cut the second sentence (plan does not arrive, tree confirmed while it runs).
- Readme 4.1.0, "audience is asked": cut "before splitting folders that way".
- Readme 4.1.0, admin addresses: cut the `admin.php?page=vergeml-ai` example.
- Readme 4.1.0, "Library-wide work": cut the Editors sentence and the private-folder, scan and search sentence.
- Readme 4.1.0, "File types": cut the switch-on-and-off sentence, the list of blocked types and "A type saved earlier ... the screen names it".
- Readme 4.1.0: cut the bullets "Settings > Media is WordPress's own screen again" and "With EML active, nothing of it is switched off any more", and "A run takes longer than before". All remain in full in the changelog draft.

## Check before using

- The 4.1.0 text has not been through a wordpress.org Plugin Check or the readme validator.
- External services is unchanged, so no outbound fact moved. The draft does not mention `VERGEML_SAFE_MODE` in that section, as before.
