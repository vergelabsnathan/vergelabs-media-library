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

## Done

| Requirement | State |
|---|---|
| Plugin Check errors | **0** — all five categories, the release archive at 3.16.1 (`7f2a4fe9…`), run in Playground on 2026-09-12 |
| Plugin Check warnings | **1** — `mismatched_plugin_name`: readme.txt's title carries a strapline the plugin header does not. A copy call, not a blocker |
| `php -l` on every file | clean |
| Runs on current WordPress | 18 of 18 matrix cells on 2026-09-11 — WordPress 6.5, 7.0, 7.1 × PHP 7.4, 8.2, 8.5, multisite, `nl_NL` and `ar`; see [compatibility.md](compatibility.md) |
| Upgrade from Enhanced Media Library 2.9.4 | settings, taxonomies, MIME types and every term assignment carried over; 18 checks |
| Runs beside the 18 most common plugins | each alone and fourteen together; see [compatibility.md](compatibility.md) |
| `debug.log` clean after exercising every screen | yes |
| GPLv2 or later, attribution to wpUXsolutions | header, readme, admin footer |
| Unique prefix on functions, classes, options, handles, AJAX actions | `vergeml_` / `vergeml-` |
| No minified code without source | both files recovered to readable source |
| External service disclosed | yes — readme.txt "External services" and the FAQ name `ai.vergelabs.nl`, what is sent and when. The upstream notice poller is still gone; the AI describe call is the only outbound request and it needs a licence key |
| No locked features or upsell | the three "/ Premium Feature" blocks are gone |
| Dev files excluded from the zip | `.gitattributes` export-ignore, verified against the built archive — 135 files, the same list as the Playground zip; `/tickets` and `/pnpm-lock.yaml` were missing from the list until 2026-09-12 |
| Version consistency | header, `VERGEML_VERSION` and `Stable tag` asserted equal at build |
| Screenshots, banner, icon | six screenshots, `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png`, in `assets/` |

## Not done — needs you

Nothing. The six items this section carried from 3.3.0 closed as follows, checked
on 2026-09-12:

| item | closed by |
|---|---|
| `Contributors:` | `vergelabsnathan` confirmed as the wordpress.org login (Nathan, 2026-09-12) |
| banner and icon | `assets/` holds `banner-772x250.png`, `banner-1544x500.png`, `icon-128x128.png`, `icon-256x256.png` beside the six screenshots |
| the AI service | `https://ai.vergelabs.nl/v1/describe` answers (405 to a GET on 2026-09-12); `/api/health` reports `releases` free 3.16.1, pro 1.0.2 |
| privacy, DPA, retention | readme.txt "External services" links `/legal/dpa`, `/legal/sub-processors` and `/legal/retention` on vergelabsmedia.com; all three answer 200 |
| the upstream political statement | gone from readme.txt |
| 3.3.0 or wait | 3.16.1 is what is submitted; every planned phase through the Librarian has shipped |

What is left is the form itself, which only the account holder can send.

A new WordPress.org account can sit in manual review before the login works.
That review is separate from the plugin review queue, which only starts once a
submission exists. Neither is a signal about the plugin.

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

**Not through WP-CLI.** `wp plugin check` crashes php-wasm part way through the run
(`RuntimeError: unreachable`), so the blueprint's `wp-cli` step is not an option in
Playground. The browser route is.

The old by-hand route, still valid:

    npx @wp-playground/cli server --port=9403 --php=8.3 --wp=latest --login       --mount-dir "<extracted>/vergelabs-media-library" "/wordpress/wp-content/plugins/vergelabs-media-library"       --mount-dir "<plugin-check>" "/wordpress/wp-content/plugins/plugin-check"

Then Tools -> Plugin Check. Tick **every** category: the form defaults to
"Plugin Repo" alone, which skips Security, Performance and Accessibility.

Last run, 3.16.1, the release archive, all five categories, 2026-09-12: one
warning, `mismatched_plugin_name` on readme.txt line 0; no errors.

## When you submit

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
