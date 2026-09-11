# Markup built from data we did not write

**Generated. Do not edit.** `node tools/escaping.mjs` writes this file;
`node tools/escaping.mjs --check` fails when the code has moved and this file has
not. Phase 5.4 of `plans/four-yesses.md`.

Plugin Check reports zero unescaped-output errors on the clean tree. That is a good
start and not an audit: Plugin Check reads PHP, and a folder name does not reach the
tree, the modal or the list table through PHP — it arrives as JSON and is put into
the page by JavaScript, where `esc_html()` does not exist and nothing warns you.

## The count

| | count |
|---|---|
| PHP output sites with a non-literal argument | 179 |
| · of those, read by hand with the reason | 7 |
| · of those, **nothing accounted for them** | **0** |
| JavaScript HTML sinks (`innerHTML`, `insertAdjacentHTML`, `document.write`, `.html()`) | 48 |
| · of those, given anything but a literal | 18 |
| · of those, read by hand with the reason | 18 |
| · of those, **still unread** | **0** |
| JavaScript text sinks (`textContent`, `.text()`, `createTextNode`, `setAttribute`) | 219 |

Over 66 PHP files and 34 JavaScript files that ship.

## The four journeys the plan names

A folder name, a file name, a caption or tag the model wrote, and anything out of an
imported file. Where each one becomes markup:

| the value | how it reaches a screen | what puts it in the page |
|---|---|---|
| **folder name** (a term name) | `/vergeml/v1/tree` and `/vergeml/v1/folder` as JSON | `textContent` in the tree rows and the breadcrumb, jQuery `.text()` on the drag helper and the list-table cell |
| **file name and title** | the same tree and list responses | `textContent`, and `setAttribute` for a title attribute |
| **caption and tags, written by the model** | `/vergeml/v1/ai-status`, `/similar`, `/search-try` | `textContent` on the AI screen and the look-alike cards |
| **a row out of an imported CSV** | becomes a term, then the tree | the same path as any other folder name |

None of the four reaches an `innerHTML`. That is the finding of this task, and the
table below is what it rests on rather than a recollection.

## JavaScript HTML sinks

All 48 are given a literal — an empty string, an inline SVG, or an
HTML entity. Not one is given a variable, a template literal with a substitution,
or a concatenation. Every dynamic string in this plugin goes to `textContent`,
jQuery `.text()`, `createTextNode` or `setAttribute` instead.

### The 18 read by hand

Each is given something other than a literal and each was read. Keyed by a hash of
what it is given, so changing any of them expires its reason.

| where | fingerprint | sink | why it is safe |
|---|---|---|---|
| js/eml-admin.js:33 | `950a39b6c2` | `jQuery .html()` | vergemlConfirmDialog() and vergemlAlertDialog() inject their html argument. All sixteen call sites pass vergeml.l10n strings -- our own translated copy, never a value out of the database or the request. Until 2026-09-11 every call site misspelled them and they were dead code; see "Two dialog helpers that had no callers" below |
| js/eml-admin.js:74 | `950a39b6c2` | `jQuery .html()` | vergemlConfirmDialog() and vergemlAlertDialog() inject their html argument. All sixteen call sites pass vergeml.l10n strings -- our own translated copy, never a value out of the database or the request. Until 2026-09-11 every call site misspelled them and they were dead code; see "Two dialog helpers that had no callers" below |
| js/eml-media-grid.js:106 | `3b644de74c` | `jQuery .html()` | a jQuery .html( fn ) returning one of two vergeml.l10n strings with an arrow character appended |
| js/eml-media.js:130 | `5075804ae4` | `jQuery .html()` | core's own Find Posts dialog: x.data is the HTML table core builds in wp_ajax_find_posts, behind core's nonce and capability. This mirrors core's media.js |
| js/vergeml-gallery.js:36 | `8e5f448d16` | `innerHTML` | glyph is a parameter of mk(), called exactly twice, both times with an HTML entity literal |
| js/vergeml-media-views.js:127 | `7e52d60663` | `jQuery .html()` | i.item is the rendered output of a wp.media template, not a value out of the database |
| js/vergeml-media-views.js:270 | `922a72803e` | `jQuery .html()` | $('<div/>').html( t.term_row ).text() -- a detached node used to decode entities, with only .text() read back; term_row is built by our PHP with esc_html() |
| js/vergeml-media-views.js:385 | `56d3889526` | `jQuery .html()` | this.text comes from options.text, and the only construction of this view passes a vergeml.l10n string |
| js/vergeml-media-views.js:914 | `0531e42831` | `jQuery .html()` | l10n.noMedia, one of our own translated strings |
| js/vergeml-taxonomies-options.js:299 | `ed5afe42cb` | `jQuery .html()` | a jQuery .html( fn ) returning one of two vergeml.l10n strings with an arrow |
| js/vergeml-tree-view.js:132 | `be95315174` | `innerHTML` | an inline SVG assembled from literal path strings chosen by a boolean |
| js/vergeml-tree-view.js:1024 | `7524bab4c0` | `innerHTML` | chevron() or an empty string, and chevron() returns a literal SVG |
| js/vergeml-tree.js:473 | `120a036696` | `innerHTML` | shard() returns a literal SVG |
| js/vergeml-tree.js:853 | `b757a8c8a1` | `innerHTML` | chevron() returns a literal SVG -- three sites, same expression |
| js/vergeml-tree.js:885 | `b757a8c8a1` | `innerHTML` | chevron() returns a literal SVG -- three sites, same expression |
| js/vergeml-tree.js:3688 | `b757a8c8a1` | `innerHTML` | chevron() returns a literal SVG -- three sites, same expression |
| js/vergeml-tree.js:4747 | `a85ef70f6b` | `innerHTML` | an HTML entity chosen by a boolean, inside literal markup |
| js/vergeml-tree.js:4753 | `379ac53d9c` | `innerHTML` | one of two HTML entities |

#### Two dialog helpers that had no callers

`js/eml-admin.js` defines `window.vergemlConfirmDialog` and
`window.vergemlAlertDialog`, both of which inject their `html` argument. On
2026-09-10 neither had a caller: all sixteen call sites spelled them, and the two
spinner helpers, without the `vergeml` prefix, and those names were defined nowhere.
Every call threw a ReferenceError, and because `event.preventDefault()` ran first,
Complete Cleanup, Restore default MIME types, Apply settings to the network and six
taxonomy dialogs did nothing at all.

Renamed on 2026-09-11. `tests/security/globals.mjs` now fails on any bare call to an
`eml`- or `vergeml`-prefixed name that no script defines. Every caller passes a
`vergeml.l10n` string, which is the only thing that may go into these two.

<details><summary>All 48 JavaScript HTML sinks</summary>

| where | sink | given | what |
|---|---|---|---|
| js/eml-admin.js:33 | `jQuery .html()` | **not a literal** | `html` |
| js/eml-admin.js:74 | `jQuery .html()` | **not a literal** | `html` |
| js/eml-media-grid.js:106 | `jQuery .html()` | **not a literal** | `function(i, html) { return ! collapsed ? vergeml.l10n.less_details+' \` |
| js/eml-media.js:130 | `jQuery .html()` | **not a literal** | `x.data` |
| js/vergeml-ai.js:339 | `innerHTML` | a literal | `''` |
| js/vergeml-autofile.js:43 | `innerHTML` | a literal | `''` |
| js/vergeml-autofile.js:156 | `innerHTML` | a literal | `''` |
| js/vergeml-brief.js:206 | `innerHTML` | a literal | `''` |
| js/vergeml-folders.js:748 | `innerHTML` | a literal | `''` |
| js/vergeml-folders.js:816 | `innerHTML` | a literal | `''` |
| js/vergeml-gallery.js:36 | `innerHTML` | **not a literal** | `glyph` |
| js/vergeml-gallery.js:120 | `innerHTML` | a literal | `'<img alt="" />' + '<p class="vgml-lightbox-caption"></p>' + '<button ` |
| js/vergeml-health.js:383 | `innerHTML` | a literal | `''` |
| js/vergeml-health.js:676 | `innerHTML` | a literal | `''` |
| js/vergeml-health.js:1029 | `innerHTML` | a literal | `''` |
| js/vergeml-import.js:110 | `innerHTML` | a literal | `''` |
| js/vergeml-import.js:297 | `innerHTML` | a literal | `''` |
| js/vergeml-import.js:612 | `innerHTML` | a literal | `''` |
| js/vergeml-media-views.js:127 | `jQuery .html()` | **not a literal** | `i.item` |
| js/vergeml-media-views.js:270 | `jQuery .html()` | **not a literal** | `t.term_row` |
| js/vergeml-media-views.js:385 | `jQuery .html()` | **not a literal** | `'<p><strong>' + this.text + '</strong></p>'` |
| js/vergeml-media-views.js:914 | `jQuery .html()` | **not a literal** | `l10n.noMedia` |
| js/vergeml-quarantine.js:97 | `innerHTML` | a literal | `''` |
| js/vergeml-quick-edit.js:61 | `innerHTML` | a literal | `''` |
| js/vergeml-say.js:45 | `innerHTML` | a literal | `''` |
| js/vergeml-talk.js:60 | `innerHTML` | a literal | `''` |
| js/vergeml-talk.js:160 | `innerHTML` | a literal | `'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-` |
| js/vergeml-talk.js:240 | `innerHTML` | a literal | `''` |
| js/vergeml-talk.js:275 | `innerHTML` | a literal | `''` |
| js/vergeml-taxonomies-options.js:299 | `jQuery .html()` | **not a literal** | `function (e, t) { return t == vergeml.l10n.edit + ' ↓' ? vergeml.l10n.` |
| js/vergeml-tree-view.js:132 | `innerHTML` | **not a literal** | `'<svg viewBox="0 0 20 16" width="20" height="16">' + '<path class="vgm` |
| js/vergeml-tree-view.js:1024 | `innerHTML` | **not a literal** | `entry.kids ? chevron() : ''` |
| js/vergeml-tree-view.js:1455 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:213 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:473 | `innerHTML` | **not a literal** | `shard()` |
| js/vergeml-tree.js:559 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:623 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:759 | `innerHTML` | a literal | `'<svg viewBox="0 0 20 16" width="20" height="16">' + '<rect x="0.5" y=` |
| js/vergeml-tree.js:853 | `innerHTML` | **not a literal** | `chevron()` |
| js/vergeml-tree.js:885 | `innerHTML` | **not a literal** | `chevron()` |
| js/vergeml-tree.js:954 | `innerHTML` | a literal | `'<svg viewBox="0 0 20 16" width="20" height="16">' + '<path d="M1.5 1h` |
| js/vergeml-tree.js:2464 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:2668 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:2941 | `innerHTML` | a literal | `'&#8943;'` |
| js/vergeml-tree.js:3688 | `innerHTML` | **not a literal** | `chevron()` |
| js/vergeml-tree.js:4597 | `innerHTML` | a literal | `''` |
| js/vergeml-tree.js:4747 | `innerHTML` | **not a literal** | `'<span aria-hidden="true">' + ( collapsed ? '&#9656;' : '&#9666;' ) + ` |
| js/vergeml-tree.js:4753 | `innerHTML` | **not a literal** | `now ? '&#9656;' : '&#9666;'` |

</details>

## The CSV export, and spreadsheet formulas

`core/import-csv.php` exports folder names. `vergeml_csv_line()` quotes a field that
holds a comma, a quote or a newline, per RFC 4180, which is correct for a CSV reader
and does nothing about a spreadsheet.

A folder named `=HYPERLINK("http://example.test","Click")` holds none of those three
characters, so it is written to the file unquoted, and Excel, Numbers and Sheets all
evaluate a cell beginning `=`. The same goes for a name beginning `+`, `-`, `@`, a
tab or a carriage return. It takes somebody who can name a folder — `manage_categories`
— and an administrator who exports and opens the file.

**This is a finding, and the fix changes the bytes of a file customers receive**, so
it is not made here: the export feeds the import, and that round trip is the migration
story. The fix is to prefix a field that begins with one of those characters with a
single quote, and to teach the importer to strip one leading `'` back off.

## PHP output read by hand

| where | fingerprint | what is printed | why it is safe |
|---|---|---|---|
| core/gallery-widgets.php:396 | `e60edee9bb` | `$html` | vergeml_render_gallery_block() builds every part with wp_get_attachment_image(), esc_url(), esc_attr() and an (int) cast, and puts the caption through wp_kses_post(). The front-end path, so the one that matters most |
| core/import-csv.php:192 | `7700958a44` | `vergeml_csv_line( $row )` | a CSV download, not markup: text/csv with Content-Disposition attachment, quoted per RFC 4180. Not an XSS sink -- but see "The CSV export, and spreadsheet formulas", which is a separate finding |
| core/licence-page.php:151 | `fbcf74ab5e` | `'<div class="vgml-status-band">' . $band . '</div>'` | $band is assembled on three branches, each from esc_html__() or esc_html() plus literal markup |
| core/options-pages.php:1593 | `cd9e4aee76` | `json_encode( $settings )` | json_encode() into a download: application/json with Content-Disposition attachment, so not an HTML context. wp_json_encode() would be the house style |
| core/options-pages.php:2916 | `e60edee9bb` | `$html` | assembled from __() translations and literal form markup; no value out of the request or the database is interpolated unescaped. Two sites, the media and non-media post-type branches, with the same expression |
| core/options-pages.php:3019 | `e60edee9bb` | `$html` | assembled from __() translations and literal form markup; no value out of the request or the database is interpolated unescaped. Two sites, the media and non-media post-type branches, with the same expression |
| core/smart-folders.php:1337 | `dde83623fc` | `implode( '<br>', $lines )` | each $lines[] entry is sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( … ) ), esc_html( $title ) ) |

## PHP output with nothing accounting for it

None. Every non-literal output goes through an escaper, a cast to a number, or a
printer that escapes inside itself.

