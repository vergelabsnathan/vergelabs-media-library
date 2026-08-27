# Watchdog tests

These break the plugin on purpose and check that the site comes back.

    npx @wp-playground/cli server --port 8899 \
      --mount-dir 'C:\dev\media-plugin\plugin' /wordpress/wp-content/plugins/vergelabs-media-library \
      --blueprint blueprint.json

    node recovery.js      # the whole ladder, 10 assertions
    node guard-proof.js   # asks the plugin whether the guarded files loaded

**Use `127.0.0.1`, not `localhost`.** WordPress builds admin links from its own
siteurl, so browsing the other name means every nonce fails with "The link you
followed has expired" and the resume button appears broken when it is not. That
cost an hour once already.

`recovery.js` does not assert that request #1 is the one that fatals: a broken
page view is several PHP requests across six workers, and which one picks up the
changed file first is not the plugin's business. What it asserts is that the
crash is caught, the site serves again, the plugin stays *active*, the notice
quotes the real error, the feature screens are gone while in safe mode, and they
come back after resuming.

`guard-proof.js` is the more direct instrument: it temporarily appends a probe to
core/watchdog.php that prints `vergeml_safe_mode()` alongside
`function_exists('vergeml_search_columns')` â€” a function defined only inside the
guarded includes. Expected:

    healthy       safe=no  features=LOADED
    after crashes safe=YES features=skipped
    after resume  safe=no  features=LOADED

Infer nothing from admin menus. `register_taxonomy` runs in the main plugin file,
which loads in safe mode too, so "Media Categories" is present either way.
