# Session handoff — 2026-10-04 — wordpress.org review 2: fixes done, two would-reject classes left

## Goal
Clear wordpress.org review 2 with no further round (brief: docs/handoffs/2026-10-04-wporg-review-round-2.md). Rulebook → audit → fixes → proof are done for everything except the classes below. No upload yet.

## State
- Branch `release-4.1.0`, worktree `Media Plugin\wt\release-4.1.0`, HEAD `ae74ba0`, clean, NOT pushed. Open the session in `plugin\` (BMAD loads there), work in `..\wt\release-4.1.0`.
- Done and committed (f2b2d86 … ae74ba0): no deactivate_plugins anywhere; 39 REST routes + 3 handlers + 5 screens on manage_options, object checks elsewhere; MIME allowlist; ~45 audit fixes (security, SQL IN() placeholders, readme, credits, uninstall, notices, structure); code review (4 layers) fixed.
- Rulebook: docs/wordpress-org-rulebook.md (1,320 rows). Audit tables + fresh-eyes pass: docs/wordpress-org-audit/. Evidence: docs/wordpress-org-submission.md, section "wordpress.org review 2".
- Proof at 752614e/78f1a5f: Plugin Check 0 errors 0 warnings (29/29); PHPCS annotations honoured 0, ignored 261; clean-site walk (21 admin screens 200, editor/author refused raised screens, empty debug.log, 0 plugin outbound requests); suites green: watchdog-php, mime-allowlist, route-caps, uninstall, settings-safety (newly registered), surface, db-calls, copy, get-help, ai-outage, csv-local, archive-hygiene + the brief's set.

## Open — the fresh-eyes reviewer (docs/wordpress-org-audit/review3-fresh-eyes.md) would still reject
1. WOULD-REJECT: core AJAX takeover — `wp_ajax_save-attachment-compat` (core/taxonomies.php ~1254) and `wp_ajax_save-attachment-order` (~1421) at priority 0, the same class as round 1's delete-post. Fix: move compat term saving onto core's `attachment_fields_to_save`/own prefixed action; order onto a `vergeml-` action with the JS changed. Needs a media-modal walk after.
2. WOULD-REJECT: interfering with other plugins/themes — core/compatibility.php dequeues/deregisters Enhanced Media Library's assets and template (~88-126), removes Impreza's (~47-50) and SimpLy Gallery's (~63-66) filters. Fix: stop interfering; rely on the neighbours notice telling the owner to deactivate EML.
3. Likely questions: options-pages.php overrides $pagenow/$title/$hook_suffix and $submenu, dequeues core `media` script and redefines findPosts/adminpage/pagenow (the hidden Media Settings page); ai.php ~821-900 calls Requests::request_multiple directly (HTTP API bypass); smart-folders.php filters that accept raw SQL; health-keep.php direct $wpdb->update of post_content without edit_post per post; test-only filters in shipped code (vergeml_ai_describe_answers lets any plugin replace AI answers, vergeml_ai_run_should_nudge, brief's vergeml_brief_describe); readme 33 KB and the "What does this fork fix" FAQ; 15 PreparedSQL.NotPrepared (error-level in plugin_review) + 1 CSV echo with annotations ignored.
4. Test gaps from the code review (Verification Gap layer): import multi-pass + forged resume; admin-allowed rows for health-keep/delete, autofile-act, folder-privacy; private-folder gate as editor; bulk move/terms as author with a page id; admin_init as logged-out user; REST bridge suite.
5. Copy for Nathan to approve: File types safety line and "left out" messages, Deactivate / Network Deactivate, Not scanned yet, licence/trial/help disclosures, readme FAQ credits and 4.1.0 changelog bullets.

## Decisions made
- Strict reading everywhere (search by meaning, folder privacy, smart scan are admin-only now; editors keep the tree).
- Refused file types are always off, even where core would allow them for admins.
- `eml_*` form-field/error-code/CSS names left (not globals); media_category unprefixed on purpose (reviewer note).

## Next concrete step
Nathan's go on fixing 1–3 (estimate in the session summary), then: fix → rerun Plugin Check + phpcs + walk + suites → fresh-eyes pass again → build dist/vergelabs-media-library-4.1.0.zip from the final commit → Nathan uploads and sends the three-line reply.
