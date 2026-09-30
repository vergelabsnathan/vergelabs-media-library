# Submitting to WordPress.org

State of the gate as of 3.16.1 (2026-09-12). Everything here has been run, not assumed.

Updated 27-08-2026, after the Librarian shipped. The box was unreachable that day,
so the runs below are Playground runs unless they say otherwise — see "Running
Plugin Check without Docker".

> **Two corrections, 29-08-2026.**
>
> The test box is now Hetzner (`46.225.66.194`, key `~/.ssh/hetzner_vgml`); Kamatera
> is retired, and the claim above that its key "is gone" was wrong even then.
>
> More importantly, **"debug.log clean after exercising every screen" was closed on
> PHP 8.3 and did not hold on 8.5**: a single duplicate scan wrote 545
> `imagedestroy()` deprecations. Fixed in 6c2f9f4 and re-verified at zero from our
> own code. Any other item on this page verified only on 8.3 deserves the same
> suspicion — the plugin's floor is 7.4 and its ceiling is whatever a host ships
> next, and this page did not previously distinguish them.

> **Review round 1, 2026-09-30: this page's "done" claims were wrong in two places.**
>
> **"Plugin Check errors: 0" did not reflect what Plugin Check checks.** In
> Playground every Plugin Check check built on PHPCS -- late_escaping,
> i18n_usage, prefixing, enqueued_resources, direct_db, setting_sanitization
> and 8 more -- answered its AJAX call with HTTP 400 ("file_put_contents():
> Exclusive locks are not supported for this stream", PHPCS's
> Reporter.php:303), and the screen listed only the checks that ran.
> tools/plugin-check.mjs read the screen's text, so every "0 errors" above was
> the non-PHPCS half of Plugin Check. A canary plugin doing `echo $_GET['x']`
> came back clean. With the lock patched out (tools/plugin-check-blueprint.json)
> the same 4.1.0 archive (`654e089`) reports **2 errors and 80 warnings**, and
> the wordpress.org review found 62 unescaped outputs that PHPCS with our
> phpcs:ignore comments did not show at all. tools/plugin-check.mjs now fails a
> run in which any check did not answer, and tools/phpcs.mjs is the gate (see
> "Running PHPCS" below).
>
> **"No locked features or upsell" was wrong.** Local work sat behind a licence:
> growing a filled plan's tree (free, no service call) was only offered with a
> licence and under the chat cap; confirming a hand-built tree failed without a
> key; the describe parallelism followed the plan tier; and the Librarian kept a
> gate stub, a "credits can run out" pause and Enhanced Media Library's Pro
> hooks. All removed in this round (below).
>
> **The wordpress.org account is `vergelabsdev`**, not `vergelabsnathan`
> (Nathan, 2026-09-30). The Contributors line and "When you submit" below are
> corrected; `vergelabsnathan` remains the GitHub account in the source links.

## The archive to upload

**`dist/vergelabs-media-library-4.1.0.zip`** — rebuilt 2026-09-30 from `06955ab`
(review round 1), to be tagged `v4.1.0`. 146 entries, 139 files, 1,142,985 bytes,
sha256 `0fd86850092b883986b3bfbc5bc877aee1369b81a3a36c314e5847706ab97d95`; the zip
comment reads back `06955abc…`, `Version: 4.1.0`, `VERGEML_VERSION` 4.1.0 and
`Stable tag: 4.1.0` out of the zip, `Contributors: vergelabsdev`. The first 4.1.0
build, from `847026f` before the review, was 1,143,102 bytes, sha256 begins
`993dccf6c2e9` (4.0.7 was `23faaee`, 145 entries, `0df5d200c51e`; 4.0.6 was `dab340f`, 144 entries, `7b2efd20fe78`; 4.0.5 was `dfea3c5`, 144 entries, `0b8e20c4856b`; 4.0.4 was `7563c0a`, 143 entries, `3829283bb85a`; 4.0.3 was `82bd06d`, 143 entries, `95ec481210d9`; 4.0.2 was `0e03cd4`, 143 entries, `d57c3020567e`; 4.0.1 was `b6a89f1`, 143 entries, `c5510da6b16a`; 4.0.0 was `59df217`, 145
entries, `bf0d63b70056` — the two fewer entries since are `.harness/` and its
file, export-ignored since retro A-1).
Checked after building, not assumed: no `tests/`, `tools/`, `docs/`, `plans/`,
`node_modules/`, `research/` or `dist/` inside it, no hidden files, the main
plugin file present, and `Stable tag: 4.1.0` and `Version: 4.1.0` read back out
of the zip itself rather than off the working tree.

## Done

| Requirement | State |
|---|---|
| Plugin Check errors | **Corrected 2026-09-30: every "0" in this row was measured with the PHPCS checks not running (see the note at the top).** With them running, all five categories, 29 of 29 checks answering: `654e089` (4.1.0 as first built) **2 errors, 80 warnings**; `06955ab` (after review round 1) **1 error, 76 warnings**. The error left is `PluginCheck.CodeAnalysis.Offloading.OffloadedContent` at core/get-help.php:23, the `raw.githubusercontent.com` URL of known-issues.json: a JSON file read server-side on a button press and disclosed in External services, not an offloaded asset -- for Nathan to explain or move. The 76 warnings are database-query notes (DirectDB, NoCaching, PreparedSQL on the plugin's own tables), 8 NonceVerification.Recommended, all in core/connect.php (the licence connect router, whose callback checks manage_options and a per-user one-time state token with hash_equals rather than a nonce, and the notice it shows afterwards), and 3 hook names that belong to WordPress (https_local_ssl_verify, twice) and Dokan (dokan_forced_load_scripts), read on purpose. The old history, as written: **0** — all five categories, **and on 2026-09-30** against `847026f` (4.1.0), same result; the release archive at 3.16.1 (`7f2a4fe9…`), run in Playground on 2026-09-12; **run again on 2026-09-19** against a clean archive of `89ba1f1` (everything through S21), same result; **and on 2026-09-20** against `657b257` (4.0.1), same result; **and on 2026-09-21** against `0e03cd4` (4.0.2), same result |
| Plugin Check warnings | **0** at 4.1.0 (2026-09-30, archive of `847026f`, 0 errors, all five categories, Playground PHP 8.3, WordPress latest). **0** at 4.0.7 (2026-09-25, archive of `23faaee`, 0 errors: the Description warning is gone since the reference sections moved to the FAQ — the parser appends External services to the Description and cuts at 2,500 *words*). **1** at 4.0.6 (2026-09-25, archive of `dab340f`, 0 errors) and at 4.0.5 (2026-09-24, the archive of `dfea3c5`, 0 errors: `readme_parser_warnings_trimmed_section_description` only; `mismatched_plugin_name` is gone since `ed90005`). **2** at 4.0.0 through 4.0.4 (2026-09-22, the archive of `7563c0a`, 0 errors) — `mismatched_plugin_name` (readme.txt's title carries a strapline the plugin header does not; a copy call, not a blocker) and `readme_parser_warnings_trimmed_section_description` (the Description runs to 11,468 characters against the parser's 2,500; it was 13,856 before this release, so it is long-standing, and moving External services out of it shortened rather than caused it). Neither blocks. The readme title was matched to the header in `ed90005`, after that run; Plugin Check has not been rerun since |
| `php -l` on every file | clean |
| Runs on current WordPress | 15 of 18 matrix cells ✓ on 2026-09-20 against the 4.0.1 zip — WordPress 6.5.11, 7.0.5, 7.1.1 × PHP 7.4, 8.2, 8.5, multisite (subdirectory), `nl_NL` and `ar`, beside Premio Folders, Enhanced Media Library and Polylang Pro; 2 cells not run (FileBird on Playground's SQLite; the subdomain network while it holds the shop library); 1 ✗: beside FileBird on MariaDB a single-row drag files nothing — fixed by stories 4.4 and 4.5, the cell ✓ 10/10 on 2026-09-21 and shipped in 4.0.2; see [compatibility.md](compatibility.md) |
| Upgrade from Enhanced Media Library 2.9.4 | settings, taxonomies, MIME types and every term assignment carried over; 18 checks |
| Runs beside the 18 most common plugins | each alone and fourteen together; see [compatibility.md](compatibility.md) |
| `debug.log` clean after exercising every screen | yes |
| GPLv2 or later, attribution to wpUXsolutions | header, readme, admin footer |
| Unique prefix on functions, classes, options, handles, AJAX actions | `vergeml_` / `vergeml-`. **Corrected 2026-09-30:** two walker classes (`Walker_Media_Taxonomy_Checklist`, `Walker_Media_Taxonomy_Uploader_Filter`) were unprefixed until `40c22cb`, and the plugin answered core's own `delete-post` AJAX action until `42f7a9b`. `save-attachment-compat` and `save-attachment-order` are still core's action names, answered for attachments at priority 0 -- they cannot be prefixed |
| No minified code without source | both files recovered to readable source |
| External service disclosed | yes — readme.txt "External services" and the FAQ name `ai.vergelabs.nl`, what is sent and when. The upstream notice poller is still gone. **Corrected 2026-09-30:** the describe call is not the only outbound request -- External services lists each one, from docs/outbound-audit-2026-09-19.md |
| No locked features or upsell | **Corrected 2026-09-30:** the three "/ Premium Feature" blocks were gone, but local features were still licence- or tier-gated (see the note at the top); removed in review round 1, `c6d658f`. Every licence check left guards a request to the service |
| Dev files excluded from the zip | `.gitattributes` export-ignore, verified against the built archive — 135 files, the same list as the Playground zip; `/tickets` and `/pnpm-lock.yaml` were missing from the list until 2026-09-12 |
| Version consistency | header, `VERGEML_VERSION` and `Stable tag` asserted equal at build |
| Screenshots, banner, icon | six screenshots, `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, in `assets/` |

## Not done — needs you

Nothing. The six items this section carried from 3.3.0 closed as follows, checked
on 2026-09-12:

| item | closed by |
|---|---|
| `Contributors:` | `vergelabsdev`, the account that submits (Nathan, 2026-09-30). Until then this row said `vergelabsnathan` was the wordpress.org login; it is the GitHub account |
| banner and icon | `assets/` holds `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png` beside the six screenshots |
| the AI service | `https://ai.vergelabs.nl/v1/describe` answers (405 to a GET on 2026-09-12); `/api/health` reports `releases` free 3.16.1, pro 1.0.2 |
| privacy, DPA, retention | readme.txt "External services" links `/legal/dpa`, `/legal/sub-processors` and `/legal/retention` on vergelabsmedia.com; all three answer 200 |
| the upstream political statement | gone from readme.txt |
| 3.3.0 or wait | 3.16.1 is what is submitted; every planned phase through the Librarian has shipped |

What is left is the form itself, which only the account holder can send.

A new WordPress.org account can sit in manual review before the login works.
That review is separate from the plugin review queue, which only starts once a
submission exists. Neither is a signal about the plugin.

## Review round 1 (2026-09-30)

The first review asked for fixes in nine categories, every instance across the
plugin. What was done, by commit, all on `release-4.1.0`:

| category | commits | what |
|---|---|---|
| verification | `738765e`, `1852bf1`, `53ed8f9` | Plugin Check's PHPCS checks run in Playground; tools/phpcs.mjs (PHPCS 3.13.4 + WPCS 3.2.0 in php-wasm, on a fresh git archive, with and without our phpcs:ignore comments, plus tools/phpcs/markup-scan.php for __() and values concatenated into markup and inline tags, and a parse on PHP 7.4 and 8.3) |
| trialware (Guideline 5) | `c6d658f` | grow re-plan without licence or cap; confirm without a key; no librarian gate or credits pause; one parallelism for all; EML Pro hooks and the remote-notice printer removed |
| description | `74f59cb` | comparisons cut; "No custom tables" corrected (four tables named); Contributors `vergelabsdev` |
| enqueue | `db78487` | six inline scripts/styles through wp_add_inline_script / wp_add_inline_style; two onclick attributes moved into the same |
| escaping | `40c22cb` | 69 translations and 40 values in markup escaped; every helper echo through wp_kses with vergeml_kses_allowed() |
| nonces | `d1139f6` | upload-into-folder, search by meaning, REST bridge, bulk actions |
| prefixes | `40c22cb`, `42f7a9b` | two walker classes prefixed; core's delete-post AJAX action no longer answered |
| REST | `c523520`, `06955ab` | 51 permission callbacks held in variables now name vergeml_rest_can_*; /state and /assign explained or tightened |
| load_plugin_textdomain | `6934ac2` | removed |

Checker counts, same tool on both archives (`node tools/phpcs.mjs --ref <commit>`),
annotations ignored: `654e089` 285 PHPCS findings and markup-scan
Translation 69 / Value 40 / InlineTag 6; `06955ab` 248 and 0 / 0 / 0.
EscapeOutput 25 → 1 (the CSV download), NonceVerification.Missing 11 → 7,
NonPrefixedClassFound 2 → 0, I18n.MissingTranslatorsComment 1 → 0. What is left is
listed with a reason in the round's report; the SQL sniffs (128) were not part of
this review.

## Running PHPCS

    node tools/phpcs.mjs                 # HEAD: parse on 7.4 and 8.3, PHPCS twice, markup-scan
    node tools/phpcs.mjs --list          # and every finding
    node tools/phpcs.mjs --worktree      # uncommitted edits too

Run it before Plugin Check: it reads what a reviewer reads, including the lines
our phpcs:ignore comments hide.

## Running Plugin Check without Docker

Plugin Check must be run against the **built archive**, not the working
directory. Checking the repo reports errors for `.git`, the Playground zip and
the test folder -- none of which ship -- and those false positives will bury a
real finding.

This is driven rather than clicked now, because it is run often enough to be worth not
clicking. `tools/plugin-check-blueprint.json` installs Plugin Check;
`tools/plugin-check.mjs` picks the plugin, ticks **every** category and reads the result
back.

    git archive HEAD --prefix=vergelabs-media-library/ -o /tmp/clean.tar
    tar xf /tmp/clean.tar -C <somewhere>
    npx @wp-playground/cli server --port 8907 --php=8.3 --wp=latest \
      --mount-dir "<somewhere>\vergelabs-media-library" \
        /wordpress/wp-content/plugins/vergelabs-media-library \
      --blueprint=tools/plugin-check-blueprint.json
    node tools/plugin-check.mjs http://127.0.0.1:8907

**The PHPCS checks need the blueprint's patch.** Under php-wasm PHPCS's
Reporter takes an exclusive file lock, which php-wasm does not support, and every
check built on PHPCS answers HTTP 400. tools/plugin-check-blueprint.json removes
that one LOCK_EX from Plugin Check's vendored PHPCS after installing it, and
tools/plugin-check.mjs exits 2 unless every check it asked for answered success
("29 checks asked for, 29 ran, 29 answered success"). A run without that line is
not a clean run. The by-hand route below has no patch and shows the same false
"no errors" (2026-09-30).

**Not through WP-CLI.** `wp plugin check` crashes php-wasm part way through the run
(`RuntimeError: unreachable`), so the blueprint's `wp-cli` step is not an option in
Playground. The browser route is.

The old by-hand route, still valid:

    npx @wp-playground/cli server --port=9403 --php=8.3 --wp=latest --login       --mount-dir "<extracted>/vergelabs-media-library" "/wordpress/wp-content/plugins/vergelabs-media-library"       --mount-dir "<plugin-check>" "/wordpress/wp-content/plugins/plugin-check"

Then Tools -> Plugin Check. Tick **every** category: the form defaults to
"Plugin Repo" alone, which skips Security, Performance and Accessibility.

Last run, a clean archive of `badb481` at **4.0.0**, all five categories,
2026-09-19: **no errors**, two warnings (above). Earlier the same day,
`89ba1f1` and the 3.16.1 release archive both gave no errors and the one
`mismatched_plugin_name` warning.

**The version and the changelog are done.** 4.0.0, with an Upgrade Notice the
readme did not have before, written from
[release-inventory-2026-09-19.md](release-inventory-2026-09-19.md).

**External services was rewritten, and moved.** The section described two of
the plugin's eighteen external calls and contradicted the code in five places;
[outbound-audit-2026-09-19.md](outbound-audit-2026-09-19.md) is the
enumeration it was rewritten from. It is now a top-level section rather than a
subsection of the Description, because the Description is truncated by the
readme parser and a disclosure nobody can read is not a disclosure.

## When you submit

Checked against the review team's rules on 2026-09-22 (sources in the S33
handoff addendum): the account is `vergelabsdev` (corrected 2026-09-30; this line
said `vergelabsnathan` on `nathan@vergelabs.nl`, and the email of `vergelabsdev` is
not recorded here)
— a company-domain address, which a brand-led plugin name requires; turn on
two-factor login before submitting, or the form refuses; never a second
account. The first review currently comes within a day or two; what costs time
is rounds (about six on average in 2025). Reply the same day, briefly, on the
same thread, and fix every instance of a flagged category, not only the lines
quoted.

**Keep the slug `vergelabs-media-library`.** wordpress.org recognises an
installed copy by its folder, every zip we have shipped unpacks into
`vergelabs-media-library/`, and Pro 1.0.4 stops offering free updates the
moment wordpress.org's entry for that folder appears. The form proposes the
slug from the plugin name; if the reviewer offers another, ask them to keep
this one — a slug cannot be changed after approval.

**Before the upload:** build the archive from `5a27c06` or later. The English
terms and privacy pages are live at `vergelabsmedia.com/legal/terms` and
`/legal/privacy` since 2026-09-23 (service `cc3c368`), and the readme's
External services links them since `5a27c06`, no longer the Dutch consultancy
pages.

**The note to send with the first reply** (approved words are Nathan's):

> Thank you for reviewing VergeLabs Media Library. Three things you may want to
> know up front:
>
> 1. It is a fork of Enhanced Media Library 2.9.4 by wpUXsolutions (GPLv2 or
>    later), under a new name and slug. We are not affiliated with wpUXsolutions,
>    and the plugin does not present itself as theirs: the original copyright and
>    credit are kept in the plugin header, the readme and the admin footer. Since the fork it has been substantially
>    rebuilt: a folder tree in the grid, the list and the media modal; smart
>    folders; an importer for seven other folder plugins; folder galleries;
>    WordPress 7 fixes; and optional AI descriptions and alt text through a
>    service we run.
> 2. The AI features use that external service (ai.vergelabs.nl). The readme's
>    External services section says what is sent, when, and links our terms and
>    privacy policy. Without a licence key the plugin contacts nothing on
>    activation or when a screen opens; every request follows a button the user
>    presses (connecting a licence, a free 25-picture try that asks for an email
>    address, starting a run). With a key connected, it reads the credit balance
>    when one of its screens opens, at most every five minutes. All plugin code is present and unlocked; what is paid for is the
>    service.
> 3. The folder taxonomy is registered as `media_category`, without our prefix,
>    on purpose: it is the taxonomy Enhanced Media Library registers, so sites
>    moving over keep their folders, and tools that already recognise those
>    folders keep working. Everything we add — functions, options, hooks,
>    script handles, AJAX actions — carries the `vergeml_` / `vergeml-` prefix.

1. One submission at a time. If the review team replies, answer that email — do not
   open a second submission.
2. Upload the artifact built by `git archive`, not a zip of the working directory. The
   working directory carries `.wp-env.json`, `dist/` and `playground/`, which Plugin
   Check flags as hidden and compressed files. The built archive contains none of them.

```
git archive --format=zip --prefix=vergelabs-media-library/ -o dist/vergelabs-media-library-<version>.zip HEAD
```

3. Screenshots live in the SVN `assets/` directory, not inside the plugin zip. The six
   in `assets/` here are export-ignored for exactly that reason.

## Not blocking, worth knowing

- The strings that used to borrow WordPress's own translations now carry our text
  domain, because wordpress.org does not allow borrowing core's. They will show in
  English until translated at translate.wordpress.org. This was a deliberate trade.
- `eml-save-changes-message` is used twice as an id. Cosmetic, logged in
  `architecture.md`.
- Seven of the eight core-view replacements are still replacements rather than wraps.
  `createToolbar` is converted; the rest are listed in `architecture.md` with the route
  for each. Not a submission concern, a maintenance one.
