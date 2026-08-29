# Submitting to WordPress.org

State of the gate as of 3.3.0. Everything here has been run, not assumed.

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
| Plugin Check errors | **0** — all five categories, clean archive at 3.3.0, run in Playground |
| Plugin Check warnings | **0** |
| `php -l` on every file | clean |
| Runs on current WordPress | verified on 7.1 / PHP 8.3 |
| Upgrade from Enhanced Media Library 2.9.4 | settings, taxonomies, MIME types and every term assignment carried over; 18 checks |
| Runs beside the 18 most common plugins | each alone and fourteen together; see [compatibility.md](compatibility.md) |
| `debug.log` clean after exercising every screen | yes |
| GPLv2 or later, attribution to wpUXsolutions | header, readme, admin footer |
| Unique prefix on functions, classes, options, handles, AJAX actions | `vergeml_` / `vergeml-` |
| No minified code without source | both files recovered to readable source |
| External service disclosed | yes — readme.txt "External services" and the FAQ name `ai.vergelabs.nl`, what is sent and when. The upstream notice poller is still gone; the AI describe call is the only outbound request and it needs a licence key |
| No locked features or upsell | the three "/ Premium Feature" blocks are gone |
| Dev files excluded from the zip | `.gitattributes` export-ignore, verified against the built archive |
| Version consistency | header, `VERGEML_VERSION` and `Stable tag` asserted equal at build |
| Screenshots | six, captured from a real install, in `assets/` |

## Not done — needs you

**1. `Contributors:` in readme.txt.** It currently reads `vergelabsnathan`, which is a
GitHub handle. It must be a real WordPress.org username, and that account needs
two-factor enabled before it can submit anything.

Confirm the username, then:

```
readme.txt  ->  Contributors: <your-wordpress-org-username>
```

**2. A banner and an icon.** `assets/` holds six screenshots and nothing else. The
directory listing wants `icon-256x256.png` (and ideally `icon-128x128.png`), and the
plugin page wants `banner-772x250.png` (and `banner-1544x500.png`). Neither blocks
approval; both are the difference between a listing that looks maintained and one that
looks abandoned. There is on-brand geometry to build the icon from — the shard fan in
`vergeml_menu_icon()` in `core/admin-menu.php` — but nothing exists for the banner, so
that one needs a concept agreed before it is drawn.

**3. The AI service is not live.** `https://ai.vergelabs.nl` does not resolve. The
readme now tells reviewers the plugin talks to it, and a reviewer who enters a key and
presses Describe gets a connection failure. Either the service answers before
submission, or the AI screens ship with demo mode as the only route — demo mode
already works and sends nothing anywhere.

**4. The privacy and terms pages do not mention the AI service.**
`https://vergelabs.nl/privacy` and `https://vergelabs.nl/voorwaarden` both resolve and
are now linked from readme.txt, but they are the general site pages. The roadmap's
standing rule asks for more than a link: an Art. 28 DPA, a published sub-processor list
and a stated retention position, before the first hosted call. A reviewer following the
link should find the service described there.

**5. A political statement inherited from upstream** sits in the 2.9.x changelog
("Please do not buy into ruzzian lies and propaganda..."). It is upstream's message in a
historical entry, not this fork's, and it was left alone rather than edited out of
somebody else's release history without asking. Worth a decision before submission:
wordpress.org has acted on readme content of this kind.

**6. Whether to submit 3.3.0 at all, or wait.** Phase 4 (AI smart folders, auto-filing,
natural-language commands) is planned and unbuilt. Submitting now means the review queue
runs in parallel with Phase 4 and the first update ships to real installs; waiting means
one submission of a bigger plugin. A call, not a defect.

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

Last run, 3.3.0, clean archive, all five categories: *Checks complete. No errors
found.*

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
