# Session handoff — 2026-10-04 — wordpress.org review 2: rulebook running, fixes not started

## Goal
Clear wordpress.org review 2 (watchdog self-deactivation, /ai-run behind manage_categories, upload_mimes open to any extension) per docs/handoffs/2026-10-04-wporg-review-round-2.md, in its order: rulebook → rule-by-rule audit of the archive → every fix → proof. No upload while a rule row is open.

## State
- **Fixes go on branch `release-4.1.0`, worktree `Media Plugin\wt\release-4.1.0`, NOT main.** The submitted archive is 4.1.0 built from `0752e82` on that branch (docs/wordpress-org-submission.md there); main has none of the round-1 fixes (merge-base 86e1085, release-4.1.0 is 43 commits ahead). HEAD `2636135` (docs only after 0752e82). Clean.
- The worktree has no `.claude/skills`, so open the session in `plugin\` (BMAD loads there) and edit `..\wt\release-4.1.0\…` by path; `git -C ..\wt\release-4.1.0`.
- No code changed yet: the session guard blocked the first edit (transcript 1.2 MB counted the pre-/clear session).
- Extracted archive of 0752e82 (143 files, matches the record): `C:\Users\viete\AppData\Local\Temp\claude\c--Users-viete-Desktop----Claude-Projects-Media-Plugin\342fbe1f-2e05-49e0-a8fe-224f295e2656\scratchpad\arch\vergelabs-media-library`
- Three agents were writing into that scratchpad when the session stopped: `rulebook-A.md` (18 guidelines, common-issues, readme, handbook), `rulebook-B.md` (Plugin Check checks + sniffs, bot issue titles, security/REST docs), `permission-sweep.md` (every REST endpoint/AJAX/admin_post: what it writes, which screen calls it, recommended cap). If a file is missing or truncated, rerun that agent.

## Decisions made this session
- Name this round "wordpress.org review 2 (2026-10-03)" in the submission doc: that doc already uses "round 2" for the 09-30 internal pass.
- Watchdog: two rungs only. Rung 3 (deactivate_plugins at core/watchdog.php:194) goes; the network branch moves to a `vergeml_watchdog_tell_network()` called when safe mode starts. The notice gets a Deactivate button: core's `plugins.php?action=deactivate` link with nonce `deactivate-plugin_{basename}`, hidden when the plugin is network-active. Its "System report" link (`options-general.php?page=vergeml-settings&tab=report`) points at no page; use `admin.php?page=vergeml-help`. Update tests/tree/watchdog.php (it asserts the escalation at lines 104–119) and tests/watchdog/recovery.js.
- Second deactivate path: core/options-pages.php:1816 `vergeml_settings_cleanup()` ("Delete All Data & Deactivate", lines 932/1186). It is user-clicked, but it is still the grep the bot matches. Decide from the rulebook's exact "Changing Active Plugins" text; the safe default is to drop the call, redirect to plugins.php, and offer core's Deactivate link.
- REST principle (Nathan's): a write across many attachments/terms, a run/apply/import/undo, or a call that spends the site's credits → `manage_options` (`vergeml_rest_can_manage_options`). A single-object write keeps core's cap, object-scoped. A screen whose actions are raised is raised too (vergeml-ai, vergeml-librarian, vergeml-import-folders, vergeml-health are manage_categories today). docs/security-surface.md lists all 85 endpoints and the 14 unscoped single-id writes.
- MIME: the allowlist is `wp_get_mime_types()` with our own `mime_types` filter removed. Hard-refuse php*/phtml/phar/cgi/pl/py/sh/asp*/jsp/htm*/shtml/svg/js/exe/htaccess. Drop `vergeml_mime_types()` (it adds extensions core does not know), drop "Add a file type" (options-pages.php:3145), drop on save and on read, and say on the screen which were dropped. Narrow `vergeml_check_filetype_and_ext()`: no octet-stream pass for every application/* type. Drop the SVG dimension fallback that `simplexml_load_file()`s a URL (mime-types.php:440).

## Open questions / blockers
- New screen copy (dropped types, the Deactivate button) is Nathan's to approve: propose it and mark it in the summary.
- Audit leads not yet judged: options-pages.php:1428 refreshes credits for up to 200 sites when the network settings screen opens (an outbound call per site with a key?); check against guideline 7 and the reviewer note.

## Files to read first
1. docs/handoffs/2026-10-04-wporg-review-round-2.md: the brief and its order
2. the three scratchpad files above: the rulebook goes verbatim to `wt/release-4.1.0/docs/wordpress-org-rulebook.md`
3. wt/release-4.1.0/docs/security-surface.md and wordpress-org-submission.md: the inventory and the evidence table

## Next concrete step
Invoke vergelabs-loop (one-session fix, Build → Prove → Review → Ship), then bmad-build. Assemble docs/wordpress-org-rulebook.md from rulebook-A/B, then audit the extracted archive row by row into wordpress-org-submission.md. Then fix: watchdog first (design above), then MIME, then the REST sweep from permission-sweep.md.

## What NOT to redo
- Don't build on main or check the working directory; audit the git archive.
- Don't silence anything with phpcs:ignore.
