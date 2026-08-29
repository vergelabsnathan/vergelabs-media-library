# Compatibility

Tested 2026-08-20 against WordPress (wp-env latest), PHP 8.3, `WP_DEBUG` and
`WP_DEBUG_LOG` on. Reproduce with `tests/compat/matrix.sh`.

## Upgrading from Enhanced Media Library

The path every existing install takes, and the one where breakage costs most --
a library of thousands of files and hundreds of terms. A fresh-install test
cannot cover it, so it has its own suite: `tests/compat/upgrade.js`.

Enhanced Media Library 2.9.4 is installed and activated, its settings are moved
off their defaults through its own option shapes, terms are created and files
filed under them; then EML is deactivated and this plugin activated. Eighteen
checks pass:

- every value EML held is present and unchanged afterwards -- library options,
  taxonomy definitions, taxonomy options, custom MIME types
- EML's own `wpuxss_eml_*` options are left untouched, so rolling back still
  finds its settings
- every term assignment on every file survives
- both taxonomies are still registered against attachments
- the site survives **both plugins active at once** -- the careless upgrade,
  where someone installs this before deactivating EML. Media library and
  settings screens both return 200 with no fatal.

Two notes on reading that result. The migrated options are a *superset* of
EML's, not identical: activation merges in this plugin's own defaults, including
settings EML never had. The test asserts nothing is lost or altered rather than
asserting equality, because equality would fail on a correct migration. And the
settings are deliberately moved off their defaults first -- a setting that
already equals the default cannot show whether it was carried over or merely
re-created.

### Running it without Docker

This suite runs on WordPress Playground, which is real WordPress on PHP-wasm in
Node -- no Docker, no MySQL, boots in seconds:

    npx @wp-playground/cli server --port=9400 --php=8.3 --wp=latest --login \
      --define-bool WP_DEBUG true --define-bool WP_DEBUG_DISPLAY false \
      --define VGML_TEST_KEY "s3cr3t" \
      --mount-dir "<repo>" "/wordpress/wp-content/plugins/vergelabs-media-library" \
      --mount-dir "<eml-2.9.4>" "/wordpress/wp-content/plugins/enhanced-media-library" \
      --mount-dir "<dir holding upgrade-helper.php>" "/wordpress/wp-content/mu-plugins"

    node tests/compat/upgrade.js

Under Git Bash, set `MSYS_NO_PATHCONV=1` first or the `/wordpress/...` mount
targets are rewritten into Windows paths and every mount fails.

GD is not present in the Playground build, so the fixtures write a literal PNG
rather than drawing one.

## What is actually checked

Nineteen assertions per plugin, aimed at the surfaces this plugin touches,
because that is where a conflict would show:

- media grid toolbar renders, with its filters, on two rows and no duplicate ids
- core's `filters-heading` screen-reader element survives
- attachments load through the real `query-attachments` endpoint
- search by taxonomy term, by title, and a nonsense term returning nothing
- list view, the bulk term picker, the bulk action, the taxonomy column
- all three settings screens
- the media modal inside the editor
- no JavaScript error **attributed to this plugin's files**
- no PHP notice, warning or fatal naming this plugin in `debug.log`

JavaScript errors are attributed by source URL. A companion shouting about its
own missing API key is not this plugin's failure, and lumping the two together
would either hide a real conflict or invent one.

## Results

Each plugin was activated alone, tested, and deactivated.

| Plugin | Result |
| --- | --- |
| Elementor | pass |
| WooCommerce | pass |
| Advanced Custom Fields | pass |
| Beaver Builder (Lite) | pass |
| Classic Editor | pass |
| FooGallery | pass |
| MetaSlider | pass |
| NextGEN Gallery | pass |
| Polylang | pass |
| Yoast SEO | pass |
| Rank Math | pass |
| LiteSpeed Cache | pass |
| Smush | pass |
| ShortPixel | pass |
| Jetpack | pass (alone; see below) |
| WPML (Sitepress) | pass |
| WP Rocket 3.23.2.2 | pass |
| Divi Builder 4.27.4 | pass (see note) |

Then **fourteen of them activated together**, which is the configuration real
sites are in: all nineteen checks pass. Divi Builder, WPML and WP Rocket
together with this plugin also pass all nineteen. `debug.log` for the whole campaign
contains six lines, none from this plugin — Smush exhausting memory in its own
helper, a WooCommerce textdomain notice, and cron-schedule errors left by
activating and deactivating plugins in a loop.

Errors observed from companions, present with this plugin switched off and so
not ours: NextGEN's `photocrati_ajax is not defined` on non-NextGEN admin
screens, Yoast's React `defaultProps` deprecation warning, and ShortPixel's
"No API Key set for this site".

## Not covered

- **Jetpack in the full stack.** Jetpack makes blocking outbound calls to
  wordpress.com. In an offline container those never resolve and the whole
  bootstrap hangs — with this plugin switched off as well, so it is the sandbox
  and not a conflict. Jetpack passes when tested on its own. A stack test with
  Jetpack needs an environment with outbound network.
- **Multisite.** Single-site only so far.
- **WPML's media translation add-on.** The base Sitepress plugin was tested;
  the separate media translation module, which duplicates attachments, was not.
- **Divi as a theme.** The Divi Builder *plugin* was tested. The Divi theme
  extracted only partially here and is untested.

## Two languages: Polylang, and what it implies for WPML

Run on the Hetzner box, 29-08-2026, Polylang 3.8.7 with English and Dutch
configured and **`media_category` switched on as a translated taxonomy** —
which is the state that matters, and the state the first attempt at this was
not in. `tests/compat/polylang.php`, twelve checks, all green.

What it found, and it answers the whole question:

- **The folder tree shows every folder, in both languages.** Polylang's
  language filter on `get_terms()` does not apply on the admin side, so a Dutch
  editor still sees the English folders. That is the outcome worth having: the
  alternative — half the tree silently missing, files apparently vanished into
  folders nobody can see — would have looked exactly like working software.
- **A file can sit in folders of different languages at once**, and both count
  it correctly. Nothing about a folder being a translated term changes what
  filing means.
- **The CSV export writes both languages out**, and the round trip is
  unaffected.
- **No shim was needed.** This is the taxonomy decision paying for itself:
  FileBird keeps folders in its own tables and needs a `Support/WPML.php` to
  hold them in step. Folders here are terms, and both plugins already translate
  terms.

One thing to know before writing a suite of your own here: Polylang decides
which taxonomies are translated during `init`, so switching the setting on and
using it in the same request does nothing. The suite writes the option, reports
itself SKIPPED, and asks to be run again. The version that did not notice
reported all-green while Polylang was inert.

**WPML is not proven by this.** The base Sitepress plugin passed the stack test
above, and both plugins translate taxonomies through the same core machinery,
so the structural argument carries — but it is an argument and not a
measurement, and WPML is paid, so it cannot go on the box without a licence.
Treat WPML as *expected to work, untested at this depth*.

## Divi Builder and `WP_DEBUG_DISPLAY`

Divi Builder fails every check on a site with `WP_DEBUG_DISPLAY` on, including
logging in — and it fails with this plugin deactivated too, so it is not a
conflict. The chain: something loads a textdomain before `init`, WordPress
prints a `_doing_it_wrong` notice, that output precedes the auth cookie, and
`wp-login.php` then cannot set headers. With display off, which is how
production runs, Divi Builder passes all nineteen checks alongside this plugin.

Worth knowing because a developer running a debug site will see a broken login
and reasonably suspect whichever plugin they installed last.

## Why this matters here

This plugin filters `posts_search`, `posts_join` and `posts_distinct` on
attachment queries and *replaces* core's search clause — necessary so search
columns can be switched off, but it means every attachment query in the admin
passes through it. It also wraps core media views rather than replacing them
(see [architecture.md](architecture.md)); seven replacements remain and are the
most likely source of a future conflict with a plugin that ships its own media
frame.
