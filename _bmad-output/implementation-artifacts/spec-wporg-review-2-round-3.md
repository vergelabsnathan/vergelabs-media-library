---
title: 'wordpress.org review 2, round 3: the two would-reject classes and the likely questions'
type: 'bugfix'
created: '2026-10-05'
status: 'in-progress'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/docs/wordpress-org-audit/review3-fresh-eyes.md'
  - '{project-root}/docs/wordpress-org-rulebook.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The fresh-eyes pass on ae74ba0 (docs/wordpress-org-audit/review3-fresh-eyes.md) would send 4.1.0 back again: the plugin answers two core AJAX actions in place of core, and it switches off code of EML, Impreza and SimpLy Gallery. It also lists nine likely questions.

**Approach:** Nathan's brief of 2026-10-05, items 1–4, in order: (1) stop answering `save-attachment-compat` / `save-attachment-order`; (2) stop interfering with other plugins and themes, rely on the neighbours notice; (3) the likely questions: core globals, `$submenu`, the core `media.js` replacement, `Requests::request_multiple`, raw-SQL filters, health writes to `post_content`, test-only filters, readme size and the "what this fork fixes" FAQ, PHPCS error-level findings with annotations ignored at 0; (4) the code review's missing tests, registered in tools/verify.mjs. Then the proof, a fresh-eyes pass against the rulebook, and only then the zip.

## Boundaries & Constraints

**Always:** folders stay `media_category` terms; every outbound call keeps matching readme's External services; user-facing strings are Nathan's (no new screen copy, readme cuts only remove or shorten); suites restore what they write; run Plugin Check on a clean `git archive`.

**Never:** answer a core `wp_ajax_*` action name; dequeue, deregister or remove another plugin's or theme's scripts, templates or hooks; build or upload the zip before the reviewer pass finds nothing it would reject; push to main.

**Defaults recorded (Nathan's go covers these):**
- Compat term saving runs inside core's own handler, through `attachment_fields_to_save`; the term counts ride on `wp_prepare_attachment_for_js`, only during that action.
- Ordering moves to `wp_ajax_vergeml-save-attachment-order`; the JS posts there.
- The hidden Media Settings page (a copy of core's options-media.php) is removed with its globals, fired core hooks, `$submenu` rewrite and the `adminpage`/`pagenow` overrides; old addresses redirect to core's options-media.php.
- Core's `media` script stays on upload.php; the plugin's grid script loads after it and only opens its own frame; `findPosts` is core's again.
- The bulk-select toggle moves from core's `Attachment` prototype into a grid-only subclass.
- Parallel describing goes; every call is `wp_remote_post` (sequential). This costs throughput; the run measures it.
- The three test-only filters go; suites use `pre_http_request` instead.
- Health rewrites of other posts go through `wp_update_post` after `edit_post` per post; a post the person may not edit, or whose markup kses would strip, is not rewritten and the file stays.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Compat save | editor ticks a folder checkbox in the modal | core handler saves; term set; reply carries `tcount` | no assign_terms cap → terms untouched |
| Order | drag in grid, post_id 0 or a post | `vergeml-save-attachment-order` renumbers | bad nonce → -1 / 403 |
| EML active | both plugins on upload.php | nothing of EML dequeued; notice shows | — |
| Health keep | admin may not edit a referencing post | that post untouched, file stays | message lists it |

</frozen-after-approval>

## Code Map

- `core/taxonomies.php` ~1254 compat handler, ~1421 order handler -- replace / rename.
- `js/eml-media-models.js:108` -- order action name.
- `core/compatibility.php` 38-126 -- delete Impreza, SimpLy, EML blocks.
- `core/media-templates.php:19`, `js/eml-media-grid.js:38` -- template id to `vergeml-attachment-grid-view` so EML's copy cannot shadow it.
- `core/options-pages.php` 117-124, 154-155, 222-542 -- hidden page, `$submenu`, globals; `core/admin-menu.php:136` redirect map; `vergelabs-media-library.php:1473` action link; `js/eml-admin.js:13-16`.
- `vergelabs-media-library.php:855` media dequeue; `js/eml-media.js` -- frame opener only.
- `js/vergeml-tree.js:4552` toggle patch → `js/eml-media-grid.js` subclass + `AttachmentView` in createStates.
- `core/ai.php:816-925` parallel path; `core/ai-background.php:175`; `core/brief.php:417`.
- `core/smart-folders.php:292,354`, `core/ai-folders.php:247`, `core/quarantine.php:330,353`.
- `core/health-keep.php:381-460,720-770`, `core/health-delete.php:196-260,420-440`.
- `readme.txt` -- 33 KB, FAQ at ~158.
- `tools/verify.mjs` registry; tests under `tests/`.

## Tasks & Acceptance

**Execution:** one commit per item 1–4, then proof and docs.

**Acceptance Criteria:**
- Given the shipped archive, when grepping `wp_ajax_save-attachment`, `wpuxss`, `us_attachment`, `pgc_sgb`, `request_multiple`, `$submenu`, `wp_dequeue_script( 'media' )`, then nothing matches.
- Given `node tools/phpcs.mjs`, then annotations-ignored error-level findings are 0.
- Given Plugin Check on a clean archive, then 0 errors 0 warnings.
- Given the clean-site walk with the outbound logger, then every screen 200, empty debug.log, 0 unexpected outbound.
- Given the media modal as admin and as editor, when ticking a folder and dragging order, then both save.
- Given the fresh-eyes reviewer against the rulebook, then no WOULD-REJECT.

## Implementation Notes

## Spec Change Log

## Review Triage Log

## Verification

**Commands:**
- `node tools/verify.mjs <suite>` per suite, then all -- expected: green.
- `node tools/phpcs.mjs` / `node tools/plugin-check.mjs` -- expected: 0 error-level / 0 errors.
