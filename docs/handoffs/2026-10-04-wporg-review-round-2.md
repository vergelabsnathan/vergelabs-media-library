# Session handoff — 2026-10-04 — wordpress.org automated review, round 2

## Goal
Clear the three findings in the wordpress.org bot review (Review ID: AUTO vergelabs-media-library/vergelabsdev/26Sep26/T2 3Oct26/4.3), upload a corrected zip, and send a short reply. Comply; do not argue (memory: respect-platform-policies).

## State
- Repo: Media Plugin\plugin (open the session HERE; the parent folder loads no BMAD skills)
- Branch: main, last commit 86e1085 (feat(tree): plan price 30 + 15 per 100 labels)
- Uncommitted: two old untracked files (docs/handoffs/2026-09-26-filing-to-the-max-kickoff.md, a mock); not part of this work
- Tree-planner work moved on in other sessions (stories 1–10 in wt/tree-planner, 4.1.0); its state is in docs/handoffs/2026-09-29-tree-planner-built-merge-pending.md and 2026-09-29-tree-planner-story-8-guard.md, not here

## The three findings and the fix to make
1. **The watchdog deactivates the plugin by itself** after repeated fatal errors. Not allowed. Fix: it never calls deactivate_plugins(). It switches the plugin's own features off (safe mode) and shows an admin notice with a Deactivate button the owner presses. Grep `deactivate_plugins` across core/ and find every path.
2. **`/ai-run` POST** (core/ai-background.php:511) starts a library-wide run that writes alt text, but checks only `manage_categories`. Fix: the write method requires `manage_options`; GET status may stay. The team says they don't list every case, so sweep every `register_rest_route` whose write changes many attachments or terms (refile/apply/run/import/undo) and raise each to `manage_options`. List the routes and the decision for each in the commit.
3. **`upload_mimes`** (core/mime-types.php:223, `vergeml_upload_mimes`) lets settings allow any extension, with a short blocklist. Fix: an allowlist. Only extensions WordPress core itself knows (`wp_get_mime_types()`) can be enabled. Also hard-refuse php*/phtml/phar/cgi/pl/py/sh/asp*/jsp/htm*/shtml/svg/js/exe/htaccess even if core knows them. Settings that name anything else are dropped on save and on read, and the screen says which ones were dropped.

## Decisions already made
- Policy is final: fix all three, plus the same class elsewhere in the code.
- Judge with Plugin Check using `--ignore-annotations` on a clean `git archive` in Playground (memory: plugin-check-ignores-hide-findings; AGENTS.md: never check the working directory).

## Files to read first
1. AGENTS.md: suites, deploy, the Plugin Check procedure
2. docs/wordpress-org-submission.md: the evidence table and the archive procedure
3. core/ai-background.php:500–530, core/mime-types.php around 223, and the watchdog (grep `watchdog`, `deactivate_plugins`)

## Next concrete step
Invoke vergelabs-loop (one-session fix: Build → Prove → Ship), then bmad-build with this file as the intent.

## Prove before uploading
- Suites for the touched files (`node tools/verify.mjs <name>`), plus a check that /ai-run POST answers 403 for an editor and 200 for an admin, and that a `php` MIME setting cannot be saved.
- A clean WP install with WP_DEBUG true, the plugin activated, every screen opened with no notices.
- Plugin Check `--ignore-annotations` on the archive: 0 errors.
- Then the release train for the zip, upload it at "Add your plugin", and a reply email of three lines (one per finding, what changed). The upload and the email are Nathan's to send, or say what goes out first.

## What NOT to redo
- Don't silence findings with phpcs:ignore (the 4.0.7 lesson: 280 ignores made Plugin Check "clean" and the review pended).
