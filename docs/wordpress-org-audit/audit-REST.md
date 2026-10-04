# Strict wordpress.org audit: REST rows

Archive: `scratchpad/audit2/vergelabs-media-library/` (HEAD of release-4.1.0). Read-only. All `phpcs:ignore` comments ignored.
Method: every `register_rest_route` extracted whole (77 calls, 85 method configs), every callback and permission callback read, the helpers that write or return errors followed one level down, plus every `wp_ajax_`/`admin_post_`/`admin_init` hook for BOT-rel-endpoints. Line numbers in `docs/security-surface.md` match the archive.

Paths below are relative to the archive root.

## Verdicts

| ID | Verdict | Evidence (file:line) | Fix if FAIL |
|---|---|---|---|
| REST-1 | PASS | All 85 method configs carry `permission_callback` (77 calls; the 8 two-method routes give one per method: core/ai-background.php:511, core/ai.php:1823, core/brief.php:629, core/guide.php:967, core/plan-tree.php:1849, core/rename-file.php:602, core/rename.php:495, core/rest-folders.php:46). Named callbacks: vergelabs-media-library.php:126, core/rest-tree.php:209,222, core/rest-folders.php:86,91, core/auto-file.php:678, core/private-folders.php:226, core/import-ui.php:61. | — |
| REST-2 | PASS | No `is_user_logged_in` gate. No route writes across the library below `manage_options`. Below MO only: `/state` POST (the caller's own user option, rest-folders.php:548), `/assign` (per-file `edit_post` + taxonomy `assign_terms`, rest-tree.php:222-237, 642-650), `/folder` (per-term `edit_term`/`delete_term`/`edit_terms`, rest-folders.php:91-129), `/folder-privacy` (`edit_term`, private-folders.php:226-235), `/autofile-act` (see REST-12), `/file/{id}` (`edit_post`, quick-edit.php:176-178), and reads at `upload_files`. `manage_categories` guards no library-wide write anywhere. | — |
| REST-3 | PASS | Every permission callback returns a `current_user_can()` boolean or a boolean expression (`&&`, `\|\|`, `!`). No string, array or int is returned. | — |
| REST-4 | PASS | No `return 0;`, `return '';` or `return array();` in any permission callback. The early `return true` in `vergeml_can_manage_folders` (rest-folders.php:98-100) and the `! $tax \|\|` / `! $taxonomy \|\|` branches (auto-file.php:684, rest-tree.php:236) fire only after `manage_categories`/`upload_files` passed, and the handler then 404s an unknown taxonomy or term. | — |
| REST-6 | PASS | Namespace `vergeml/v1` (core/rest-tree.php:30): prefixed, versioned, no leading or trailing slash. All 77 calls sit inside one of 29 functions hooked to `rest_api_init` (checked by script; the `/rename-files` hook is conditional on `VERGEML_FILE_RENAME`, rename-file.php:596-598, which is not defined anywhere in the archive). | — |
| REST-7 | PASS | `vergeml` is the plugin's own prefix (`vergeml_` on every function). | — |
| REST-8 | PASS | Every JS caller uses `wp.apiFetch` (16 files; inline scripts core/instrument.php:410, core/licence-page.php:311), which sends core's `wp_rest` nonce. The admin-ajax fallback sends `X-WP-Nonce` from `apiFetch.nonceMiddleware` (js/vergeml-transport.js:90-118) and verifies it against `wp_rest` (core/transport.php:46). No raw `fetch`/`$.ajax` to `/wp-json/`. | — |
| REST-9 | PASS | No REST callback verifies a nonce itself; each relies on its capability check. The only `wp_verify_nonce( …, 'wp_rest' )` is the AJAX bridge (core/transport.php:46), which is an AJAX handler and must do it. The bridge then calls `rest_do_request()` (transport.php:85), so every route's own `permission_callback` still runs. | — |
| REST-10 | **FAIL** | 9 args take input with no `type`, no `sanitize_callback`, no `validate_callback`: `draft` on brief/session POST (brief.php:130), brief/turn (brief.php:150), guide/session POST (guide.php:246), guide/turn (guide.php:267); `branches` on librarian-preflight (librarian.php:2500) and librarian-apply-step (librarian.php:2513); `plan` on say-run (nl-commands.php:569) and merge-run (utilities.php:424); `ids` on quarantine-act (quarantine.php:659). 2 params are read but not declared at all: `price` in guide/plan POST (plan-tree.php:754; route plan-tree.php:1850 has no args) and `answer` in guide/audience-split (plan-tree.php:1839; route :1854 has no args). Fixed-choice strings have no `enum` (handlers whitelist them, so this part is advisory): ai-run `action`/`scope`, ai-index `scope`, autofile-act `action`, quarantine-act `action`, import `action`, folder `action`, rename and rename-files `action`, assign `mode`, folders-propose `mode`, librarian `scheme` ×3, guide/answer `answer`, state `skin`/`density`. Handlers do clean most of this (`vergeml_brief_clean_lines` brief.php:78, `vergeml_guide_clean_draft` guide.php:631, say-run rebuild nl-commands.php:600-615). `branches` is passed through as a raw decoded array (librarian.php:2637-2645). | Declare every param: `draft` → `'type' => array('array','string','null')` + `sanitize_callback` that calls the existing cleaner; `branches` → `'type' => array('array','string')` + `sanitize_callback` that wraps `vergeml_librarian_branch_arg` and keeps only scalar keys; `plan` → `'type' => 'object'` (say-run) / `'type' => 'array'` (merge-run); `ids` → `'type' => 'array', 'items' => array('type' => 'integer')`; add `'price' => array('type' => 'integer', 'minimum' => 0)` to guide/plan POST and `'answer' => array('type' => 'string', 'enum' => array('yes','no'), 'required' => true)` to audience-split; add `enum` to the fixed-choice strings listed. |
| REST-11 | PASS | No `echo`, `print`, `wp_send_json`, `exit`, `die`, `wp_die` or `readfile` in any of the 77 callbacks or the helpers they call (script scan; the hits are all admin_post/admin screens). Callbacks return `rest_ensure_response()`/`WP_Error`. One returns a bare array (`vergeml_librarian_rest_why`, librarian.php:3224), which WordPress wraps. Advisory: wrap it in `rest_ensure_response()`. | — |
| REST-12 | **FAIL** | (a) `/autofile-act`: `vergeml_autofile_can_act` (auto-file.php:678-685) checks `edit_post` on `attachment_id` but never that it is an attachment, and `vergeml_autofile_file` only checks `get_post()` (auto-file.php:424) before `wp_set_object_terms` (auto-file.php:434). An Editor can file any post they may edit into a media folder. The `dismiss` action writes term meta (`vergeml_autofile_record`, auto-file.php:378-390, called at :791) on any `term_id`, including terms outside the folder taxonomy and ids that do not exist, because the permission callback skips the taxonomy check when the term does not resolve (auto-file.php:684). (b) `/health-delete`: `wp_delete_attachment( $id, true )` (health-delete.php:436) runs on each dropped id with only the global `manage_options && delete_posts` (health-delete.php:375-377). There is no per-file `delete_post` check, which is the row's own detection pattern. Advisory, not FAIL under the stated MO policy: quarantine-act take-back (`vergeml_quarantine_release`, quarantine.php:101-109) and health-retire (health-keep.php:108-138) do not check that the ids are attachments (they only touch plugin-owned meta/option). health-keep rewrites other posts (health-keep.php:404,436) with no per-post `edit_post`. | (a) In `vergeml_autofile_can_act`, require `'attachment' === get_post_type( $attachment_id )` and require the term to be in `vergeml_librarian_taxonomy()` (`get_term( $term_id, vergeml_librarian_taxonomy() ) instanceof WP_Term`) before any cap check. Or, since nothing renders its card (dead `vergeml_librarian_page_cards` hook), stop registering `/autofile-act` and `/autofile-step`. (b) In `vergeml_health_delete_copies`, before the delete loop: `foreach ( $drop as $id ) if ( ! current_user_can( 'delete_post', $id ) ) return new WP_Error( 'forbidden', …, array( 'status' => 403 ) );`. Cheap insurance: an `edit_post` loop on ids in quarantine-act, merge-run and health-keep. |
| BOT-permission-callback | **FAIL** | Same two object-level gaps as REST-12: `/autofile-act` (object type and term not checked) and `/health-delete` (no `delete_post` per id). Everything else matches its write: every library-wide, run, apply, import, undo or AI-service route is on `vergeml_rest_can_manage_options`, `vergeml_can_import` or an inline `manage_options` check (appendix), `/health-delete` at MO + `delete_posts`, and the single-object routes on the policy's object checks. No `__return_true` anywhere (only two unrelated `add_filter` uses: taxonomies.php:598, vergelabs-media-library.php:1500). | As REST-12. |
| BOT-rel-endpoints | **FAIL** (low) | REST: all 85 have a capability. AJAX (7): each has a cap and a nonce (offer.php:148-149, options-pages.php:1223/1236, page-builders.php:50, taxonomies.php:1197/1217, 1303/1305, 1363/1365/1381, transport.php:46/51). admin_post (11): each has a cap and `check_admin_referer`; do_alt, do_rename and rename_undo are now `manage_options` (journey.php:1084,1164; rename.php:445). admin_init: `vergeml_ai_run_revive` (ai-background.php:118-136) has **no capability check**. It runs on every wp-admin load for any logged-in user, Subscribers on profile.php included, and re-books the paid describe run (`vergeml_ai_run_schedule()` + `vergeml_ai_run_nudge()`). By the policy in the code, advancing a run that calls ai.vergelabs.nl is MO-only, and the same revive over REST (`/ai-run` GET → ai-background.php:537) is MO-gated. The other admin_init hooks either check caps (connect.php:106, options-pages.php:1560/1605/1699/1747/1771), write only the caller's own user meta behind a nonce (neighbours.php:130-137), or are input-free housekeeping (ai-index.php:898, instrument.php:253, librarian.php:334, organize.php:306). Advisory: `vergeml_apply_settings_to_network` checks its cap only `if ( is_multisite() )` (options-pages.php:1236). That is correct, because the write is inside `is_multisite()` (:1242), but a reader has to work it out. | Gate the revive: `add_action( 'admin_init', function () { if ( current_user_can( 'manage_options' ) ) { vergeml_ai_run_revive(); } } );`, or put the check at the top of a wrapper. Cron keeps the run going for everyone else. Optional: rewrite the network check as `if ( ! is_multisite() \|\| ! current_user_can( 'manage_network_options' ) ) wp_send_json_error();`. |
| (asked) errors carry a status | **FAIL** (minor) | 65 of 189 `new WP_Error(` in the archive have no `status`. These reach a REST response unwrapped and answer HTTP 500 instead of 4xx. `/import`: `vergeml_import_read` (import-sources.php:113,131) via import_plan/import_run (import.php:44,128), `vergeml_unknown_taxonomy` (import.php:48,132) and `vergeml_no_import` (import.php:375), all returned straight by `vergeml_rest_import` (import-ui.php:116,124,130). `/autofile-act` and `/say-run` pass core's `wp_set_object_terms`/`wp_insert_term`/`wp_update_term` errors through (auto-file.php:436-437; nl-commands.php:467-479). Everything else is wrapped with a status (`vergeml_talk_fail` folder-talk.php:2794, the health wrappers, guide/apply and guide/undo re-wraps, the guide/confirm 402/502 mapping guide.php:1219). | In `vergeml_rest_import`, re-wrap: `if ( is_wp_error( $x ) ) return new WP_Error( $x->get_error_code(), $x->get_error_message(), array( 'status' => 400 ) );` (404 for `vergeml_no_import`). Do the same where autofile-act and say-run pass a core error through. |

### The four GET routes that write

`/ai-run` GET (ai-background.php:537 → `vergeml_ai_run_revive`: re-books the run and spawns cron), `/guide/plan` GET (plan-tree.php:787-802: saves the session, can ask the service for a refund via `vergeml_plan_lost` :809-826, books the plan cron), `/guide/progress` GET (guide.php:2658-2663 → `vergeml_guide_fit_revive` :1742-1773 saves the session and re-books the fit job; `vergeml_guide_progress_out` → `vergeml_talk_progress`), and `/folders-progress` GET (folder-talk.php:2782 → `vergeml_talk_progress` :1926-1949, which re-books the refile and, when cron has stalled, **runs one refile pass inline**, so term assignments happen on a GET; `vergeml_talk_refile_kick` :1728-1750).

**Verdict: none of these breaks any REST row.**
- REST-1, REST-2, REST-9: each is gated at `manage_options`, which is the level of the run it advances.
- REST-8: the GETs are not CSRF-able. With no `wp_rest` nonce, `rest_cookie_check_errors()` sets the user to 0 for every HTTP method, so a cross-site `<img>` arrives unauthenticated and fails the MO check. The AJAX bridge verifies the same nonce.
- No row in the rulebook requires GET to be side-effect-free. None of the four takes input, and none starts new work. Each only continues, re-books or settles a run an administrator already started.
- What remains is HTTP semantics (GET should be a "safe" method) and how it looks to a reader: an AI reviewer scanning for BOT-rel-options-meta could read "GET handler writes options" and flag it. The writes are capability- and nonce-protected, so that flag would be wrong.
- The register shows the same pattern on five more GETs: `/ai-status` refreshes the credits cache with a `/licence` call; `/brief/session` saves the opener turn on first boot (brief.php:302); `/guide/session` boots the session; `/search-meaning` and `/search-try` write the embedding cache and book the conversion cron (security-surface.md:51,59,85,122,123). Same verdict.
- Optional hardening: move the revive/kick into the existing POST routes (an `action=nudge` on `/ai-run` POST, a POST `/guide/progress`), or say in one line in readme/SECURITY.md that a poll resumes a run already started.

## Appendix: all 77 `register_rest_route` calls

Permission key: **MO** = `vergeml_rest_can_manage_options` or an inline `current_user_can('manage_options')`; **UF** = `upload_files`. Verdict notes: **10** = REST-10 arg gap; **12** = REST-12 gap; **E** = status-less error; **G** = writes on GET (no row broken).

| # | route | methods | permission | writes | verdict |
|---|---|---|---|---|---|
| 1 | /ai-run (ai-background.php:511) | GET; POST | MO; MO | GET re-books paid run; POST starts/stops run, alt meta, cron | PASS (G) |
| 2 | /ai-alt (ai.php:1823) | GET; POST | UF (closure); MO (closure) | GET none; POST alt meta library-wide | PASS |
| 3 | /ai-status (ai.php:1984) | GET | MO | credits cache option, /licence call | PASS (G) |
| 4 | /ai-index (ai.php:1994) | POST | MO | index table, alt meta, /describe (spends) | PASS |
| 5 | /ai-settings (ai.php:2006) | POST | MO (closure; MNO for network key) | `vergeml_ai` option | PASS |
| 6 | /autofile-step (auto-file.php:655) | POST | MO | term assigns library-wide | PASS |
| 7 | /autofile-act (auto-file.php:665) | POST | `vergeml_autofile_can_act` (MC + edit_post + assign_terms) | one term assign; term meta on dismiss | **FAIL (12, E)** |
| 8 | /brief/session (brief.php:629) | GET; POST | MO; MO | session option (GET on first boot) | **FAIL (10)** (G) |
| 9 | /brief/token (brief.php:641) | POST | MO | session option, metered mint | PASS |
| 10 | /brief/turn (brief.php:646) | POST | MO | session option | **FAIL (10)** |
| 11 | /brief/test (brief.php:656) | POST | MO | describes test pictures (spends) | PASS |
| 12 | /brief/adopt (brief.php:661) | POST | MO | profile option, can book re-describe | PASS |
| 13 | /brief/discard (brief.php:666) | POST | MO | session option | PASS |
| 14 | /folders-propose (folder-talk.php:2742) | POST | MO | transient, /folders call (metered) | PASS |
| 15 | /folders-apply (folder-talk.php:2760) | POST | MO | terms + re-file library, cron | PASS |
| 16 | /folders-undo (folder-talk.php:2770) | POST | MO | undo re-file | PASS |
| 17 | /folders-progress (folder-talk.php:2782) | GET | MO | re-books refile, inline refile pass | PASS (G) |
| 18 | /folders/version (folders-version.php:102) | GET | UF | none | PASS |
| 19 | /gallery-folders (gallery-block.php:355) | GET | UF | none | PASS |
| 20 | /guide/session (guide.php:967) | GET; POST | MO; MO | session option | **FAIL (10)** |
| 21 | /guide/token (guide.php:979) | POST | MO | session, metered mint | PASS |
| 22 | /guide/turn (guide.php:984) | POST | MO | session, /embed fit, fit cron | **FAIL (10)** |
| 23 | /guide/product-categories (guide.php:997) | GET | MO | none | PASS |
| 24 | /guide/rules (guide.php:1002) | GET | MO | none | PASS |
| 25 | /guide/rule (guide.php:1007) | POST | MO | session draft | PASS |
| 26 | /guide/apply (guide.php:1016) | POST | MO | Move library-wide, cron | PASS |
| 27 | /guide/progress (guide.php:1021) | GET | MO | session, fit re-book, refile pass | PASS (G) |
| 28 | /guide/stop (guide.php:1026) | POST | MO | stops refile | PASS |
| 29 | /guide/undo (guide.php:1031) | POST | MO | undoes Move | PASS |
| 30 | /guide/confirm (guide.php:1037) | POST | MO | profile term meta, charged batches | PASS |
| 31 | /guide/unconfirm (guide.php:1042) | POST | MO | session | PASS |
| 32 | /guide/profiles-restore (guide.php:1048) | POST | MO | profile meta on every folder | PASS |
| 33 | /guide/questions (guide.php:1054) | GET | MO | none | PASS |
| 34 | /guide/answer (guide.php:1060) | POST | MO | moves groups, may create folders | PASS |
| 35 | /health-delete (health-delete.php:472) | POST | MO && delete_posts (closure) | deletes attachments, repoints posts | **FAIL (12)** |
| 36 | /health-uses (health-delete.php:492) | GET | MO | none | PASS |
| 37 | /health-keep (health-keep.php:821) | POST | MO | sets aside, rewrites posts | PASS (12 advisory) |
| 38 | /health-keep-undo (health-keep.php:831) | POST | MO | reverses keep | PASS |
| 39 | /health-retire (health-keep.php:840) | POST | MO | kept-pairs option | PASS |
| 40 | /health-scan (health.php:1197) | POST | MO | hash meta on every attachment | PASS |
| 41 | /health-report (health.php:1209) | GET | MO | none | PASS |
| 42 | /import (import-ui.php:37) | POST | `vergeml_can_import` (MO) | terms, assigns, undo, csv stash | **FAIL (E)** |
| 43 | /stats-opt (instrument.php:269) | POST | MO (closure) | stats option, /counts call | PASS |
| 44 | /librarian-schemes (librarian.php:2433) | GET | MO | none | PASS |
| 45 | /librarian-preflight (librarian.php:2453) | GET, POST | MO | none | **FAIL (10)** |
| 46 | /librarian-apply-step (librarian.php:2464) | POST | MO | applies scheme library-wide | **FAIL (10)** |
| 47 | /librarian-pause (librarian.php:2480) | POST | MO | batch status | PASS |
| 48 | /librarian-undo-step (librarian.php:2489) | POST | MO | undoes batch | PASS |
| 49 | /librarian-batches (librarian.php:2498) | GET | MO | none | PASS |
| 50 | /librarian-why/{id} (librarian.php:2519) | GET | edit_post(id) (closure) + attachment check (:3216) | none | PASS |
| 51 | /say-plan (nl-commands.php:551) | POST | MO | none | PASS |
| 52 | /say-run (nl-commands.php:560) | POST | MO + per-id attachment & edit_post (nl-commands.php:504) | term assigns, term create/rename | **FAIL (10, E)** |
| 53 | /organize-step (organize.php:2878) | POST | MO | plugin table | PASS |
| 54 | /organize-cancel (organize.php:2892) | POST | MO | run status | PASS |
| 55 | /organize-run (organize.php:2901) | GET | MO | none | PASS |
| 56 | /organize-quote (organize.php:2911) | GET | MO | none | PASS |
| 57 | /guide/plan (plan-tree.php:1849) | GET; POST | MO; MO | GET session/refund/cron; POST books paid plan | **FAIL (10)** (G) |
| 58 | /guide/audience-split (plan-tree.php:1853) | POST | MO | session | **FAIL (10)** |
| 59 | /folder-privacy (private-folders.php:206) | POST | `vergeml_private_can_edit` (MC + edit_term) + owner rule | private-folders option | PASS |
| 60 | /quarantine (quarantine.php:388) | GET | MO | none | PASS |
| 61 | /quarantine-act (quarantine.php:399) | POST | MO | quarantine meta on ids | **FAIL (10)** (12 advisory) |
| 62 | /quarantine-manifest (quarantine.php:410) | GET | MO | none | PASS |
| 63 | /file/{id} (quick-edit.php:161) | POST, PUT, PATCH | edit_post(id) (closure) + attachment check (:180) | title, alt of one file | PASS |
| 64 | /rename-files (rename-file.php:602) | GET; POST | MO; MO (closures); not registered unless `VERGEML_FILE_RENAME` | files on disk, posts | PASS |
| 65 | /rename (rename.php:495) | GET; POST | UF (closure); MO + per-id edit_post (rename.php:194) | titles | PASS |
| 66 | /folder (rest-folders.php:28) | POST | `vergeml_can_manage_folders` (MC + edit_terms/edit_term/delete_term per term) | one term / sibling order | PASS |
| 67 | /state (rest-folders.php:46) | GET; POST | UF; UF (own user option) | caller's user option | PASS |
| 68 | /tree (rest-tree.php:163) | GET | UF | none | PASS |
| 69 | /assign (rest-tree.php:184) | POST | `vergeml_can_assign` (UF/edit_posts + assign_terms) + per-object type & edit_post (:642-650) | term assigns on named files | PASS |
| 70 | /search-meaning (search-meaning.php:603) | GET | MO | embedding cache, cron, /embed | PASS (G) |
| 71 | /search-try (search-try.php:193) | GET | MO | embedding cache, cron, /embed | PASS (G) |
| 72 | /smart-scan (smart-folders.php:1127) | POST | MO | usage meta on every attachment | PASS |
| 73 | /merge-plan (utilities.php:404) | GET | MO | none | PASS |
| 74 | /merge-run (utilities.php:410) | POST | MO (quarantine_add checks attachment, quarantine.php:77) | quarantine meta | **FAIL (10)** |
| 75 | /alt-fill-step (utilities.php:417) | POST | MO | alt meta library-wide | PASS |
| 76 | /alt-undo (utilities.php:424) | POST | MO | deletes stamped alt | PASS |
| 77 | /similar (utilities.php:430) | GET | MO | none | PASS |

Totals: 77 calls / 85 endpoints. 63 PASS, 14 FAIL. FAIL routes: 10 on REST-10 only; `/autofile-act` (REST-12 and E); `/health-delete` (REST-12); `/import` (E); `/say-run` (REST-10 and E). No route has a missing permission callback, a `__return_true`, or a capability weaker than its write.

## FAILS

1. **REST-12 / BOT-permission-callback: `/autofile-act`** (auto-file.php:665, 678-685, 424, 791). It does not check that `attachment_id` is an attachment, and `dismiss` writes term meta on any term id. Require `get_post_type()==='attachment'` and a term in `vergeml_librarian_taxonomy()` in the permission callback, or unregister the route: no screen calls it.
2. **REST-12 / BOT-permission-callback: `/health-delete`** (health-delete.php:375-377, 436). `wp_delete_attachment` runs on each dropped id with no per-file `current_user_can( 'delete_post', $id )`. Add the check before the loop and return 403 if any id fails.
3. **REST-10: untyped or undeclared args.** `draft` ×4, `branches` ×2, `plan` ×2 and `ids` ×1 have no type, sanitize or validate. `price` (guide/plan POST) and `answer` (audience-split) are read but not declared. Declare them with `type` plus `sanitize_callback`/`enum` as given in the table.
4. **BOT-rel-endpoints (low): `admin_init` → `vergeml_ai_run_revive`** (ai-background.php:118-136). It re-books the paid AI run on any wp-admin load with no capability check. Gate it on `manage_options`.
5. **Errors without status (minor):** `/import` returns 5 plugin errors without `status` (import-sources.php:113,131; import.php:48,132,375), so the client gets HTTP 500. `/autofile-act` and `/say-run` pass core term errors straight through. Re-wrap them with 400/404.

Not a FAIL: the four GET routes that re-book or advance runs (and five more like them) break no REST row. They are MO-gated, need a nonce, and take no input. Converting them to POST is optional hardening.
