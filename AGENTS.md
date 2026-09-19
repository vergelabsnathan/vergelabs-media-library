<!-- bmad:context -->
<!-- Verified 2026-09-19 against de680ba. Managed by bmad-project-context; edits inside this block are replaced on refresh. Keep anything you want preserved outside the markers. -->

## vergelabs-media-library

A WordPress media library plugin — folders, categories, and AI alt text — forked from Enhanced Media Library 2.9.4, GPLv2 or later. PHP and vanilla JS with no build step: the files in the repo are the files that run. Planning lives in `plans/`, session records in `docs/handoffs/`, specs and mocks in `docs/superpowers/`.

## Policy

- Never re-enable `/rename-files`. It is gated behind `VERGEML_FILE_RENAME` (`core/journey.php:355`) and the reference rewrite underneath it is unfinished.
- Never store folder structure outside the `media_category` taxonomy. Folders being ordinary terms is the product's central promise — a customer can remove the plugin and keep them.
- Never widen what the plugin sends to the service without updating `readme.txt`'s External services section in the same commit. `docs/outbound-audit-2026-09-19.md` enumerates every outbound call.

## Where things are

- Filing decisions — which folder a picture goes in: `core/filing.php`. The matcher, the gates and the product rule all live there.
- The Folders screen's server side: `core/guide.php`; its run and conversation: `core/folder-talk.php`; its browser side: `js/vergeml-folders.js`.
- Adding a suite? Register it in `tools/verify.mjs` — the list there is what `verify` can run.
- Submitting to wordpress.org: `docs/wordpress-org-submission.md` carries the evidence table and the archive procedure.

## Running and verifying

- Suites run through `node tools/verify.mjs <name>`, not a test runner. 48 are registered; naming none runs all.
- Run `node tools/deploy.mjs --check` before trusting any result from the box. It compares the box's manifest against this working tree and names both digests; a STALE box means the suite is testing yesterday's code.
- Ship to the box with `node tools/deploy.mjs --box`. A suite alone does not deploy the plugin — `verify.mjs` copies only the suite file.
- Run one PHP file on the box with `node tools/box-eval.mjs <file>.php [--site shop|realshop]`. `--site shop` is the multisite main site; `--site realshop` is the WooCommerce shop. They are one word apart and a describe run went to the wrong one once.
- Plugin Check runs against a clean `git archive` in Playground, never the working directory: `node tools/plugin-check.mjs <url>`. Checking the repo reports errors for files that do not ship.

## Conventions that differ from defaults

- Every global is prefixed `vergeml_`, every dash-form `vergeml-`, text domain `vergelabs-media-library`.
- Anything not shipped to customers is `export-ignore` in `.gitattributes` — `tools`, `tests`, `docs`, `plans`, `_bmad`. A new top-level directory needs a line there or it lands in the release zip.
- User-facing strings are the maintainer's, verbatim. Propose copy; do not write it into a screen unasked.

## Known pitfalls

- `wp eval-file` runs a suite inside a function, so `global $pass` binds to nothing and the suite reports passed whatever fails. Bind counters to `$GLOBALS` explicitly.
- Never compute a restore list with the code under test. A snapshot read through a mutated reader deleted 100 real alt texts on the box; take snapshots with literal SQL.
- Playground's SQLite makes queries cost roughly 6 ms each, so timings there mean nothing. Gate the query count, print the milliseconds.
- Heredocs in this shell lose tabs and double backslashes; write files with the Write tool rather than `cat <<EOF`.
- A deploy is not done when the copy succeeds. Confirm the destination is serving the new build — a content marker unique to it — because an edge can serve the previous one for minutes after a push.

<!-- /bmad:context -->
