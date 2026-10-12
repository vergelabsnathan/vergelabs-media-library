# Review 8: bounded verification of `809a1df` before the upload

Scope:
- the release archive of `809a1df` (`git archive 809a1df`, 146 files: 68 PHP, 43 JS);
- every review-7 LIKELY-QUESTION and every row review 7 did not fully confirm (2, 3, 4, 9, 10, 14, 16), re-checked with a class grep over the archive;
- the product diff `6c54cd0..809a1df` (18 files, +47 −127, tests/tools/docs/playground excluded), read hunk by hunk as the Plugins Team would.

Annotations were ignored throughout. Ledger rows 23–30 in `docs/wordpress-org-submission.md` (uncommitted in the working tree) are the claims being checked.

**Result: 0 would-reject.** All seven review-7 questions are closed for what they named. Three leave residue in the same class, all low. The diff adds one functional regression: unticking one of the plugin's own taxonomies no longer hides it. A reviewer will not see it, but it makes a screen sentence false. The round-6 upload comment is still not written.

Proof that runs:
- PHP parse 68/68 on php-wasm 8.3 (`token_get_all(…, TOKEN_PARSE)`). A seeded unclosed-brace file was caught, so the check bites.
- `node --check` passes on 43/43 JS files.
- `playground/vergelabs-media-library.zip` at `809a1df` unzips identical to the archive (`diff -rq`: no differences).

---

## WOULD-REJECT

None.

---

## Review-7 items, on the archive

| Item | Verdict | Class grep (archive, annotations ignored) |
|---|---|---|
| LQ1 / row 10 (other half) / ledger 23: foreign taxonomies detached | **Closed** (regression introduced, see N1) | `unregister_taxonomy` in PHP: **0**. Writes to `object_type`: **0**. The `wp_loaded` loop now only calls `register_taxonomy_for_object_type()` (`vergelabs-media-library.php:716-724`). |
| LQ2 / row 4 (screen) / ledger 24: wipe screens say folders go | **Closed** | `all folders, settings\|every site's folders\|remove all folders`: **0** (the 3 "All folders" hits are filter labels). "What will be deleted" lists: **2/2** skip `media_category` (`options-pages.php:564`, `:818`). "Your folders, and which files are in them" sits under "will remain" in both. Labels at `:599` and `:707` say the folders stay. The dialog still says "Your folders are kept" (`:390`). These match `uninstall.php:66` and `vergeml_term_relationship_cleanup()`. |
| LQ3 / row 9 (half) / ledger 25: no OpenSSL → trial and Connect report success | **Closed as named; residue** (N3) | `openssl_encrypt` guards: `offer.php:27` (trial not offered; the nonce and button print only then), `connect.php:111` (router refuses before start or exchange, banner `:263-269` drawn by the Licence screen itself, so shell_quiet cannot hide it), `offer.php:189` (an unstorable key is refused). Callers of `vergeml_ai_seal(`: **5**. **3 unguarded**: `licence-page.php:71`, `ai.php:1993`, `options-pages.php:1042`. |
| LQ4 / row 2 (JS half) / ledger 26: JS globals only for suites | **Closed** | `vgml(Folders\|Brief)App`: **0**. `window.X =` assignments: **24**. Each of the 9 `vergeml*` globals has a reader in another shipped file. |
| LQ5 / rows 3, 14 / ledger 27: dead code | **Closed; trivia left** | `vergeml_filing_words_sql\|vergeml_filing_product_sql`: **0**. `synchroni[sz]e` in PHP/JS: **0**; in CSS: **2** (dead selector, N5). `sync_warning\|in_progress_sync`: **0**. `TODO\|FIXME\|XXX\|HACK`: **0**. `two fatal\|fatal errors in an hour`: **0**. The new comment at `:1627-1631` matches the `vergeml_features_off()` guard below it. |
| LQ6 / ledger 28: trial screen silent on the email | **Closed** | `offer.php:102` now says "…and we email you once, when the free pictures are used". This is consistent with `readme.txt:65`. |
| LQ7 / row 2 (https half) / ledger 29: endpoint constants | **Closed** | 6 reads of the 4 constants, all https-or-default: `ai.php:313-318`, `get-help.php:187-189` (safe-mode copy), `connect.php:39`, `get-help.php:58`, `guide.php:856`. All **21** `wp_remote_*` calls build their URL from those functions or `site_url('wp-cron.php')`. `'http://` literals in PHP/JS: **0**. |
| Row 16 (class) / ledger 30: strings into markup | **4 named sites closed; 1 more in the class** (N2) | `new WP_Error( '…', '<literal>'`: **0**. `filing.php:3089` is now `__()`. JS lines with l10n beside markup, excluding `.text(`/`_.escape(`: **2**: `eml-media-list.js:22` (plugin string, N2) and `eml-enhanced-medialist.js:263` (core's `media.view.l10n`). `.html(` with l10n: **1**, `vergeml-media-views.js:1021` (core's `l10n.noMedia`, copied from core's own view). `attrs.singular_name` and `term_name` at `eml-enhanced-medialist.js:254` are `esc_html`ed at the source (`vergelabs-media-library.php:1026`, `taxonomies.php:1227`). |
| Row 12 | Unchanged, acceptable as review 7 said | `vergeml_can_assign()` still true for a missing taxonomy; the handler 404s. |
| "Answered" rows 5, 6, 7, 8, 22 | **Not closed (process)** | `docs/wordpress-org-submission.md` holds only round 5's upload comment (`:342`). The sentences review 7 asked for (modal is the surface extended; prototypes extended as EML 2.9.4 does, no "after approval" promise; readme length; REST via admin-ajax without "undeclared params dropped"; webbistro credited) are not written. |

---

## The diff, hunk by hunk

| File | Hunk | Verdict |
|---|---|---|
| `core/auto-file.php`, `core/folder-talk.php`, `core/guide.php:1903-1910`, `:3332` | comment text only | ok |
| `core/connect.php:39` | `VERGEML_SITE_URL` https-only | ok; also narrows `vergeml_connect_allow_host` |
| `core/connect.php:110-113` | OpenSSL refusal, after `manage_options`, before nonce/exchange | ok: it only redirects to its own screen, and `vergeml_connect_redirect_with()` exits |
| `core/connect.php:263-269` | `openssl` notice | ok: `esc_html__`, text domain correct |
| `core/filing.php` | two builders deleted, docblock, `WP_Error` translated | ok: 0 callers left |
| `core/get-help.php:58`, `core/guide.php:856` | https-only constants | ok |
| `core/offer.php:27`, `:102`, `:189-191` | OpenSSL gate, email sentence, refusal | ok: JS renders `message` with `textContent` (`vergeml-offer-trial.js:25`) |
| `core/options-pages.php:312-318` | sync l10n removed | ok; `bulk_edit_nonce` now unread on this screen (N5) |
| `core/options-pages.php:564`, `:575`, `:599`, `:707`, `:818`, `:830` | wipe lists and labels | ok: `esc_html_e`, alt-syntax `foreach … : if … continue;` parses; the network `<li>` at `:830` is mis-indented (cosmetic); label wording N4 |
| `css/eml-admin-media*.css` | TODO line removed | ok |
| `js/eml-enhanced-medialist.js:218`, `eml-media-grid.js:83`, `eml-media-list.js:17`, `vergeml-media-views.js:460` | `.text()` / `_.escape()` | ok: `$`/`_` are in scope (IIFE `(jQuery, _)`; deps `media-views`/`media-grid`/`jquery`) |
| `js/vergeml-brief.js`, `js/vergeml-folders.js` | test globals removed | ok: no reader anywhere |
| `js/vergeml-taxonomies-options.js` | sync handler and header line removed | ok: no PHP registers `vergeml-synchronize-terms`, no PHP prints the button |
| `vergelabs-media-library.php:716-724` | `unregister_taxonomy_for_object_type` removed | **regression, N1** |
| `vergelabs-media-library.php:1627-1631` | safe-mode comment | ok |

No new escaping, sanitizing, nonce, capability, SQL or i18n issue in any hunk.

---

## New findings

### N1. Unticking one of the plugin's own taxonomies no longer hides it (regression from `fbf97d9`; not a reviewer question)

- `vergeml_on_init()` registers every `eml_media` taxonomy with `attachment` always (`vergelabs-media-library.php:650-652`; `vergeml_folder_object_types()` = `array_merge( array( 'attachment' ), … )`, `post-folders.php:70`).
- Until `6c54cd0`, unticking "Assign Taxonomy" was carried out by the removed `unregister_taxonomy_for_object_type()` at `wp_loaded`. That covers a guide-made tag such as "Colour", or an extra media taxonomy.
- Now the checkbox saves (`taxonomies.php:151`) and changes nothing. The taxonomy stays on the media screens through the 14 `get_object_taxonomies( 'attachment' )` readers.
- The same screen says "Unticking hides; it never deletes." (`options-pages.php:2313`). That sentence is now false for own taxonomies. It is also false for a foreign taxonomy that its owner put on attachments.

**Cheap fix.** Restore the call for own taxonomies only, which is not someone else's registration:

```php
if ( ! (bool) $params['assigned'] && ! empty( $params['eml_media'] ) )
    unregister_taxonomy_for_object_type( $taxonomy, 'attachment' );
```

Then scope ledger row 23's guard to "no unregister of a taxonomy without `eml_media`". Optionally reword `:2313` to "Unticking hides one this plugin added".

### N2. One more translated string concatenated into markup (row 30's class; LIKELY-QUESTION, low)

`js/eml-media-list.js:22`:

```js
$('#post-query-submit').after('<input type="submit" … class="button" value="'+l10n.reset_all_filters+'">');
```

`reset_all_filters` is a top-level `wp_localize_script` value, entity-decoded on output (`vergelabs-media-library.php:877`). A translation with a `"` breaks out of the attribute. It is the same pattern as the four fixed sites. **Fix:** build the input with `$( '<input …>' ).val( l10n.reset_all_filters )`.

### N3. Pasting a key without OpenSSL still reports "connected" (LQ3 residue; low)

- `licence-page.php:71` seals the key to `''` without OpenSSL, stores `''`, and redirects with `saved`: "Licence saved. This site is connected." (`:162`).
- The requirements notice that explains this is removed on that screen by `vergeml_shell_quiet()`.
- The same silent `''` happens at `ai.php:1993` (REST settings) and `options-pages.php:1042` (network licence).
- **Also found while tracing (pre-existing, not from the diff):** `licence-page.php:76` compares `'rejected' === $state`, but `vergeml_ai_credits_state()` returns an array (`ai.php:274`). The "That key was not recognised" notice can never fire, so a made-up key also shows "Licence saved. This site is connected."

**Fix:**
- `'rejected' === $state['state']`.
- At `:71`, `if ( '' === $settings['license_key'] )` redirect with an `openssl` result that prints the connect banner's sentence.

### N4. Wipe labels name less than the wipe removes (low)

- The delete-on-uninstall labels (`options-pages.php:599`, `:707`) say "settings, tables and AI index … (your folders stay)".
- `uninstall.php` also deletes the terms of every other `eml_media` taxonomy (guide-made tags) and the plugin's `_vergeml_*` post meta and `vergeml_*` term meta.
- Complete Cleanup's list says "options" and "backups". It also drops the four tables (`vergeml_cleanup_drop_tables()`), but its dialog's "all plugin data" covers that.

**Fix:** add "and the tags it created" to both labels.

### N5. Trivia

- Dead selector `.eml-button-synchronize-terms.disabled` at `css/eml-admin.css:361` and `css/eml-admin-rtl.css:361`.
- `bulk_edit_nonce` at `options-pages.php:315` is no longer read on that screen.
- `readme.txt:75` says the four constants send requests "to a host of your choosing". An http value is now silently ignored, so add "(https only)".

---

*Method: archive `%TEMP%\r8arch.YjGx`; scripts in `%TEMP%\r8scratch` (`lint.mjs`: php-wasm 8.3 from the npx cache, `token_get_all(…, TOKEN_PARSE)` per file). Greps as quoted in each row, run over the archive only.*
