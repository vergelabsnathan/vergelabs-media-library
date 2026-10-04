# Permission sweep: release-4.1.0 (read-only)

Worktree `wt/release-4.1.0` @ 2636135. Shipped code only: `vergelabs-media-library.php`, `core/`, `js/`, `uninstall.php`.
Every row below was checked in the code. The line numbers in `docs/security-surface.md` still match each `register_rest_route` / `add_action`.

**The owner's rule as applied here**
- **MO** = `manage_options`, **MC** = `manage_categories`, **UF** = `upload_files`.
- A write that changes many attachments or terms, runs, applies, imports or undoes something library-wide, or calls ai.vergelabs.nl in a way that spends or meters → **MO**.
- A write to one object the user names → the core capability for that edit, checked against that object.
- A pure read keeps its current capability.

**Tags in the "change?" column**
- **CHANGE (rule)**: the rule requires it.
- **CHANGE (consistency)**: the write only touches the plugin's own site-wide option or table. The rule does not force it. It is raised because:
  - every caller sits on a screen that is being raised;
  - leaving it lower lets an Editor reset an administrator's shared session over REST.
- **CHANGE (strict)**: the route sends a typed phrase to ai.vergelabs.nl `/embed`. The service meters that call but never debits it (`service/app/api/ai/embed/route.ts:19-21,110-112`). It is raised under the literal reading of "outbound call to ai.vergelabs.nl". No caller below MO breaks.
- **SCOPE**: the capability level stays the same. The check becomes object-scoped.

## Totals
| | count |
|---|---|
| REST endpoints (route × method config) | 85 |
| raised to MO: CHANGE (rule) | 32 |
| raised to MO: CHANGE (consistency) | 5 (audience-split, guide/rule, guide/session POST, guide/unconfirm, organize-cancel) |
| raised to MO: CHANGE (strict) | 2 |
| SCOPE (single-object check added, level unchanged) | 3 |
| REST unchanged | 43 |
| `wp_ajax_` actions | 7: 0 change |
| `admin_post_` handlers | 11: 3 change (do_alt, do_rename, rename_undo) |
| admin screens | 13 registrations: 5 change (dashboard, Folders, AI, Health, Import) |
| permission_callback `__return_true` / missing / bare `true` | 0 |
| writes reachable over GET | 4: ai-run, guide/plan, guide/progress, folders-progress (plus librarian-preflight, which is GET+POST but read-only) |

## REST endpoints

Callers:
- **folders.js** = `js/vergeml-folders.js`. It builds paths as `'/' + ns + '/' + route` (`api()` at :48, `persist()` at :131) and is enqueued on `vergeml-librarian` (core/guide.php:88).
- **tree.js** = `js/vergeml-tree.js` on `upload.php`, every media modal, post list screens with folders, and the page builders (core/tree-ui.php:31-177, core/page-builders.php). Gate: UF, or `edit_posts` on post screens (tree-ui.php:56-61). Folder controls show only when `canManage` = MC (tree-ui.php:233).
- **ai.js** = `js/vergeml-ai.js` on `vergeml-ai`, every tab except `how` (core/ai-screen.php:71).
- **ai-bg.js** = `js/vergeml-ai-background.js` on `vergeml-ai` (core/ai-background.php:609).
- **brief.js** = `js/vergeml-brief.js` on `vergeml-ai&tab=how`, which is MO-only (ai-screen.php:37,64-67). It builds paths like folders.js (:34-39).
- **journey.js** = `js/vergeml-journey.js` on the dashboard `toplevel_page_vergelabs-media` (core/journey.php:1616).
- **health.js** and **quarantine.js** are on `vergeml-health` (health.php:1258, quarantine.php:494).

"no caller" means no file in `js/` and no inline script in `core/` names the route. The route is still reachable by anyone who holds the capability.

| route | method | file:line | current cap | writes (scope, file:line) | called from (JS → screen/context → that context's cap) | recommended cap | change? | breakage note |
|---|---|---|---|---|---|---|---|---|
| `/ai-alt` | GET | core/ai.php:1823 | UF (closure :1829) | none (count, ai.php:1827) | no caller | UF | no | — |
| `/ai-alt` | POST | core/ai.php:1823 | MO (closure :1848) | many attachments: `_wp_attachment_image_alt` across the library (ai.php:1796-1812, `vergeml_ai_apply_alt`) | folders.js:1541 → vergeml-librarian → MC | MO | no | **Already broken for Editors**: the Folders screen (MC) offers an alt step that answers 403 below MO. It is fixed by raising that screen. |
| `/ai-index` | POST | core/ai.php:1996 | MC (closure :2000) | **spends credits**: outbound `/describe` per picture (ai.php:550); writes the plugin index table and, with `apply_alt`, alt meta on many attachments (ai.php:1444 `vergeml_ai_index_step`) | ai.js:211 → vergeml-ai → MC | MO | CHANGE (rule) | The AI screen goes to MO with it. |
| `/ai-run` | GET | core/ai-background.php:511 | MC (closure :515) | **write on GET**: `vergeml_ai_run_revive()` (ai-background.php:120,540) books the paid run's next pass (`wp_schedule_single_event`, :98) and `spawn_cron()` (:174). It only re-books a run already started. | journey.js:42,102 → dashboard → MC; ai-bg.js:114,138,170 → vergeml-ai → MC; brief.js:350,363 → vergeml-ai&tab=how → MO | MO | CHANGE (rule) | Can re-book a paid run, so it is not a pure read. Every caller sits on a screen being raised. |
| `/ai-run` | POST | core/ai-background.php:511 | MC (closure :523) | **the rejected route.** It starts or stops a library-wide background run (ai-background.php:246 `vergeml_ai_run_start`). Each tick calls `/describe` (spends) and writes alt on many attachments when `apply_alt` (tick :317 → `vergeml_ai_index_step` :370). It schedules cron (:98,497). | journey.js:62 → dashboard → MC; ai-bg.js → vergeml-ai → MC; brief.js:244 → how tab → MO | MO | CHANGE (rule) | Dashboard "Describe / Write alt / Re-describe" quick actions and the AI screen are raised with it. |
| `/ai-settings` | POST | core/ai.php:2010 | MO (+ MNO for the network lock, :2132) | plugin option `vergeml_ai` (ai.php:2151); returns status, which does a no-spend `/licence` check (ai.php:166) | ai.js:75 (behind `canConfigure`) → vergeml-ai; inline script licence-page.php:311 → vergeml-licence → MO | MO | no | — |
| `/ai-status` | GET | core/ai.php:1984 | MC (closure :1987) | plugin's own cache only: `vergeml_ai_credits` option via a no-spend `/licence` "check" (ai.php:150-179) | ai.js:117 → vergeml-ai → MC | MC | no | A read with a cache refresh. No caller is left below MO once the AI screen is raised, so raising it would be harmless. |
| `/alt-fill-step` | POST | core/utilities.php:417 | MC (`vergeml_rest_can_manage_categories`, vergelabs-media-library.php:122) | many attachments: alt and `_vergeml_alt_filled` on every image with empty alt (utilities.php:187-258, write :244,:248) | no caller | MO | CHANGE (rule) | none |
| `/alt-undo` | POST | core/utilities.php:424 | MC | many attachments: deletes alt on every stamped file (utilities.php:266-313, :303) | no caller | MO | CHANGE (rule) | none |
| `/assign` | POST | core/rest-tree.php:184 | UF or `edit_posts`, plus the taxonomy's `assign_terms` (`vergeml_can_assign` :222-237) | the named attachments only, each checked with `edit_post($id)` (rest-tree.php:647). Term relationships. A batch = an undo of the same named files (:776). | tree.js:2631,2722 → upload.php / media modal / post lists / builders → UF (or edit_posts) | unchanged (UF/edit_posts + assign_terms + per-object edit_post) | no | Raising it would break filing for every Author and Editor. The write is the user's own selection, checked per object. That is core's bulk-edit pattern, not a library-wide run. Note: `vergeml_can_assign` returns true when `taxonomy` does not resolve (:236), but the handler then refuses with 404 (:560). |
| `/autofile-act` | POST | core/auto-file.php:664 | MC (unscoped) | one attachment into one term, with **no `edit_post` check** (auto-file.php:798 → `vergeml_autofile_file` :413, `wp_set_object_terms` :434); `dismiss` = plugin term-meta record (:779) | no live caller. js/vergeml-autofile.js is enqueued on vergeml-librarian (:845), but its card hooks `vergeml_librarian_page_cards` (:817), which nothing fires, so the button never renders. | `edit_post($attachment_id)` AND the taxonomy's `assign_terms` | SCOPE | none |
| `/autofile-step` | POST | core/auto-file.php:655 | MC | many attachments: the sweep files pictures into every folder that has "earned" it (auto-file.php:548, :595) | no live caller (same dead card) | MO | CHANGE (rule) | none |
| `/brief/adopt` | POST | core/brief.php:661 | MO | site profile option, plugin table, outbound, can book a re-describe run (brief.php:543) | brief.js:317 → how tab → MO | MO | no | — |
| `/brief/discard` | POST | core/brief.php:666 | MO | plugin option (brief.php:743) | brief.js:334 | MO | no | — |
| `/brief/session` | GET | core/brief.php:629 | MO | none of note (boot) | brief localize; GET unused | MO | no | — |
| `/brief/session` | POST | core/brief.php:629 | MO | plugin option (brief.php:673) | brief.js:175 | MO | no | — |
| `/brief/test` | POST | core/brief.php:656 | MO | **spends**: describes test pictures (brief.php:464) | brief.js:299 | MO | no | — |
| `/brief/token` | POST | core/brief.php:641 | MO | outbound mint, metered (guide.php:921) | brief.js:143 | MO | no | — |
| `/brief/turn` | POST | core/brief.php:646 | MO | plugin option (brief.php:695) | brief.js:58 | MO | no | — |
| `/file/(?P<id>\d+)` | POST, PUT, PATCH | core/quick-edit.php:161 | `edit_post($id)` (:164) | one attachment: title and alt (quick-edit.php:175) | js/vergeml-quick-edit.js:150 → upload.php list → UF | unchanged | no | Already object-scoped. |
| `/folder` | POST | core/rest-folders.php:28 | MC unscoped (`vergeml_can_manage_folders` :91) | one term per call: create :143, rename :164, colour :174, move :199, delete :293 (children re-parented :289). `order` rewrites order meta (and parent) of the named sibling list (:217-257). | tree.js:2766 (create/rename/colour/move/order/delete) → upload.php etc. → controls only when MC | create: `$tax->cap->edit_terms`; rename/colour/move: `edit_term($id)`; delete: `delete_term($id)`; order: `edit_term` on each id | SCOPE | For `media_category` these map to MC, so nothing changes for Editors. They become correct for a folder taxonomy registered with other caps. |
| `/folder-privacy` | POST | core/private-folders.php:206 | MC unscoped (closure :215) | one term: plugin option `vergeml_private_folders` (private-folders.php:250 → :87). It hides the folder from everyone but the owner and administrators (:110-131). | tree.js:3202 → upload.php etc. → MC | `edit_term($id)` | SCOPE | Same level for `media_category`. Owner rule is preserved (:242). |
| `/folders-apply` | POST | core/folder-talk.php:2760 | MC | many terms and attachments: creates/deletes folders and re-files the library (folder-talk.php:2915 → `vergeml_talk_apply` :626), books the refile cron | no caller (the Folders screen uses `guide/apply`) | MO | CHANGE (rule) | none |
| `/folders-progress` | GET | core/folder-talk.php:2782 | MC | **write on GET**: `vergeml_talk_progress()` (:1926) books the refile (:1940) and runs one pass inline (`vergeml_talk_refile_kick` :1945 → term assignments on many attachments) | no caller | MO | CHANGE (rule) | none |
| `/folders-propose` | POST | core/folder-talk.php:2742 | MC | outbound `/folders` (folder-talk.php:454). Metered by the service (`meterCall … 'folders', 10`, service folders/route.ts:210). Transient plan id (:2866). | no caller | MO | CHANGE (rule) | none |
| `/folders-undo` | POST | core/folder-talk.php:2770 | MC | many terms and attachments: undoes the last re-file (folder-talk.php:1957) | no caller | MO | CHANGE (rule) | none |
| `/folders/version` | GET | core/folders-version.php:102 | UF | none | js/vergeml-tree-view.js:1985 (tree) and folders.js:1650 | UF | no | — |
| `/gallery-folders` | GET | core/gallery-block.php:355 | UF | none | js/vergeml-gallery-block.js:62 → block editor → UF | UF | no | — |
| `/guide/answer` | POST | core/guide.php:1060 | MC | many attachments and terms: each answer moves a question's group of pictures and can make a folder; `id=rest` answers all of them (guide.php:1092-1108 → `vergeml_talk_answer`, folder-talk.php:2575) | folders.js:1427,1456 → vergeml-librarian → MC | MO | CHANGE (rule) | Folders screen raised. |
| `/guide/apply` | POST | core/guide.php:1016 | MC | many attachments and terms: the Move, library-wide (guide.php:2566 → `vergeml_talk_apply`), books cron | folders.js:2510 | MO | CHANGE (rule) | — |
| `/guide/audience-split` | POST | core/plan-tree.php:1853 | MC | plugin option only: the shared guide session (plan-tree.php:1843-1844) | folders.js:709 | MO | CHANGE (consistency) | — |
| `/guide/confirm` | POST | core/guide.php:1037 | MC | **spends** (planner profile batches are charged past the first 100 folders: guide.php:1186-1217 → filing.php:3135, service folders/route.ts:245-250). Profile term meta on many terms (guide.php:1253,1267). | folders.js:811 | MO | CHANGE (rule) | — |
| `/guide/plan` | GET | core/plan-tree.php:1849 | MC | **write on GET**: poll saves session state (:795-796), can ask the service for a refund (:820 → :1098), books cron (:798) | folders.js:937 | MO | CHANGE (rule) | — |
| `/guide/plan` | POST | core/plan-tree.php:1849 | MC | **spends**: books the plan job (plan-tree.php:760-762), which calls `/plan-tree` and is charged (:1042, refund path :1098) | folders.js:916 | MO | CHANGE (rule) | — |
| `/guide/product-categories` | GET | core/guide.php:997 | MC | none (guide.php:3324) | folders.js:731 | MC | no | Read. |
| `/guide/profiles-restore` | POST | core/guide.php:1048 | MC | many terms: swaps profile meta on every folder (guide.php:1423-1436) | folders.js:842 | MO | CHANGE (rule) | — |
| `/guide/progress` | GET | core/guide.php:1021 | MC | **write on GET**: `vergeml_talk_progress()` runs a refile pass (term assigns), revives the fit job (guide.php:2661), saves session/turns when a run ends (:2589-2613) | folders.js:2377,2553,2610 | MO | CHANGE (rule) | — |
| `/guide/questions` | GET | core/guide.php:1054 | MC | none (guide.php:1071) | folders.js:1477 | MC | no | Read. |
| `/guide/rule` | POST | core/guide.php:1007 | MC | plugin option: adopts a rule draft into the shared session (guide.php:3333-3339) | no caller | MO | CHANGE (consistency) | none |
| `/guide/rules` | GET | core/guide.php:1002 | MC | none | no caller | MC | no | Read. |
| `/guide/session` | GET | core/guide.php:967 | MC | none of note (boot, guide.php:1485) | no direct caller (the screen boots from localize) | MC | no | Read. |
| `/guide/session` | POST | core/guide.php:967 | MC | plugin option: shared session draft / reset (guide.php:1465-1482, save :577) | folders.js:1694,2048 (via `persist`) | MO | CHANGE (consistency) | — |
| `/guide/stop` | POST | core/guide.php:1026 | MC | stops the library-wide refile (guide.php:2670 → folder-talk.php:1858) | folders.js:2579 | MO | CHANGE (rule) | — |
| `/guide/token` | POST | core/guide.php:979 | MC | **spends**: mints a stream token, "metered like a turn on the service's side" (guide.php:914-929). Each streamed turn spends from the site's allowance. | folders.js:1930 (`mint`) | MO | CHANGE (rule) | — |
| `/guide/turn` | POST | core/guide.php:984 | MC | session option (guide.php:1552); dry fit sends phrases to `/embed` and books the fit cron (guide.php:1544 → :1625-1689) | folders.js:1937 (`persist`) | MO | CHANGE (rule) | The outbound call and the cron are part of the paid conversation. |
| `/guide/unconfirm` | POST | core/guide.php:1042 | MC | plugin option (guide.php:1371-1386) | folders.js:830 | MO | CHANGE (consistency) | — |
| `/guide/undo` | POST | core/guide.php:1031 | MC | many attachments and terms: undoes the Move (guide.php:2676 → folder-talk.php:1957) | folders.js:2587 | MO | CHANGE (rule) | — |
| `/health-delete` | POST | core/health-delete.php:472 | MO and `delete_posts` | deletes attachments and edits other posts to repoint (health-delete.php:362) | health.js:699 → vergeml-health → MC | MO and `delete_posts` | no | Shown to Editors today and answers 403. Fixed by raising the screen. |
| `/health-keep` | POST | core/health-keep.php:821 | MO | rewrites other people's posts and sets copies aside (health-keep.php:523) | health.js:458 | MO | no | Same as above. |
| `/health-keep-undo` | POST | core/health-keep.php:831 | MO | reverses the above | health.js:464 | MO | no | — |
| `/health-report` | GET | core/health.php:1211 | MC | none | health.js:1026 | MC | no | Read. |
| `/health-retire` | POST | core/health-keep.php:840 | MO | plugin meta on the named set (health-keep.php:108) | health.js:498 | MO | no | — |
| `/health-scan` | POST | core/health.php:1197 | MC | many attachments: plugin hash meta on every attachment (health.php:485); reset deletes all hashes (:527); progress option (:504) | health.js:1091 → vergeml-health | MO | CHANGE (rule) | A library-wide run. Its only caller is the Health screen, which is being raised. |
| `/health-uses` | GET | core/health-delete.php:492 | MC | none | no caller (`vgml-health-uses` in health.js:207 is a CSS class) | MC | no | Read. |
| `/import` | POST | core/import-ui.php:37 | MC (`vergeml_can_import` :61) | one route, several actions. Reads: `found` :70, `history` :79, `plan` :122. Writes: `csv` stashes an option (:91, import-csv.php:516); `run` creates terms and assigns many attachments (:129 → import.php:123); `undo` undoes an import (:115 → import.php:358). | js/vergeml-import.js:81 → vergeml-import-folders → MC | MO | CHANGE (rule) | The whole screen is import, so the screen goes to MO with it. Optional: keep the read actions at MC by splitting the route. |
| `/librarian-apply-step` | POST | core/librarian.php:2464 | MC | many terms and attachments: applies a scheme to the library (librarian.php:2660) | no caller | MO | CHANGE (rule) | none |
| `/librarian-batches` | GET | core/librarian.php:2498 | MC | none | no caller | MC | no | — |
| `/librarian-pause` | POST | core/librarian.php:2480 | MC | plugin table: the batch status of a library-wide apply (librarian.php:2685) | no caller | MO | CHANGE (rule) | Controls a library-wide run, like ai-run `stop`. |
| `/librarian-preflight` | GET, POST | core/librarian.php:2453 | MC | none. POST only carries a long body (comment :2445-2451). | no caller | MC | no | Read on both methods. |
| `/librarian-schemes` | GET | core/librarian.php:2433 | MC | none | no caller | MC | no | — |
| `/librarian-undo-step` | POST | core/librarian.php:2489 | MC | many terms and attachments: undoes an apply batch (librarian.php:2703) | no caller | MO | CHANGE (rule) | none |
| `/librarian-why/(?P<id>\d+)` | GET | core/librarian.php:2519 | `edit_post($id)` | none | js/vergeml-why-view.js:148 → every admin media modal (`wp_enqueue_media`, librarian.php:3246) → per object | unchanged | no | — |
| `/merge-plan` | GET | core/utilities.php:404 | MC | none | no caller | MC | no | — |
| `/merge-run` | POST | core/utilities.php:410 | MC | many attachments: sets aside every id in a plan **the request supplies** (utilities.php:448 → :125-168, quarantine meta quarantine.php:85) | no caller | MO | CHANGE (rule) | none |
| `/organize-cancel` | POST | core/organize.php:2894 | MC | plugin table: run status (organize.php:3061) | no caller | MO | CHANGE (consistency) | none |
| `/organize-quote` | GET | core/organize.php:2917 | MC | none | no caller | MC | no | — |
| `/organize-run` | GET | core/organize.php:2905 | MC | none | no caller | MC | no | — |
| `/organize-step` | POST | core/organize.php:2878 | MC | plugin table only. It clusters the whole library's vectors with no outbound call (organize.php:1581). | no caller | MO | CHANGE (rule) | A library-wide run, and the feed for librarian-apply-step. |
| `/quarantine` | GET | core/quarantine.php:388 | MC | none | quarantine.js:99 → vergeml-health | MC | no | — |
| `/quarantine-act` | POST | core/quarantine.php:399 | MC | a batch of attachments with **no `edit_post` check**: set aside / take back meta on every id given (quarantine.php:427-455 → :85,:105). A set-aside file disappears from every user's library. | quarantine.js:73 (take-back, one id) → vergeml-health | MO | CHANGE (rule) | Its only caller is the Health screen, which is being raised. |
| `/quarantine-manifest` | GET | core/quarantine.php:410 | MC | none | quarantine.js:116 | MC | no | — |
| `/rename` | GET | core/rename.php:488 | UF | none | no caller | UF | no | — |
| `/rename` | POST | core/rename.php:488 | UF at the route. MO inside for library-wide/undo (:510-514). With `ids`: per-file `edit_post` (:194). | titles on many attachments (rename.php:173-232); undo reverts the last run (:244) | no caller | MO (route level) | CHANGE (rule) | none. The per-row link and the list bulk action stay per-object (see admin_post). |
| `/rename-files` | GET | core/rename-file.php:602 | MO | none | no caller. Registered only when `VERGEML_FILE_RENAME` is set (:596). | MO | no | — |
| `/rename-files` | POST | core/rename-file.php:602 | MO | files on disk and posts library-wide | no caller (gated) | MO | no | — |
| `/say-plan` | POST | core/nl-commands.php:550 | MC | none: returns a plan (nl-commands.php:570) | no live caller. js/vergeml-say.js is enqueued on vergeml-librarian (:683), but its card hooks the unfired `vergeml_librarian_page_cards` (:629). | MC | no | Read over POST. |
| `/say-run` | POST | core/nl-commands.php:559 | MC | many attachments: `move`/`tag` sets the folder on every id in a plan the **request supplies**. There is no `edit_post` check and no post-type check: `get_post($id)` accepts any post (nl-commands.php:501-511). `create`/`rename` touch one term. | no live caller | MO | CHANGE (rule) | none |
| `/search-meaning` | GET | core/search-meaning.php:603 | UF | outbound `/embed` with the typed phrase (search-meaning.php:200), not debited. Plugin table conversion plus cron (:538). | no caller. The list screen's meaning search uses `pre_get_posts` (:713), not this route. | MO | CHANGE (strict) | Breaks nothing. Owner decision: the same `/embed` call stays reachable at UF through the list-screen takeover (search-meaning.php:713-721). |
| `/search-try` | GET | core/search-try.php:193 | UF | as above | ai.js:456 → vergeml-ai → MC | MO | CHANGE (strict) | Its only caller is the AI screen, which is being raised. |
| `/similar` | GET | core/utilities.php:430 | MC | none (vector arithmetic, utilities.php:332) | no caller | MC | no | Read. The id is unscoped, but the answer is only titles and thumbnails. |
| `/smart-scan` | POST | core/smart-folders.php:1127 | MC | many attachments: plugin meta `unused` / `used_in` / `filesize` on every attachment (smart-folders.php:935-946), progress and finished options (:858,959) | tree.js:1026 → upload.php / modal / builders (UF context); health.js:577 → vergeml-health | MO | CHANGE (rule) | **Breaks the tree's Smart folders for Editors.** Clicking an unscanned smart folder (tree.js:983-984) starts the scan. It already 403s for Authors, Contributors and Dokan vendors today. Narrowest fix: localize `canScan = current_user_can('manage_options')` in tree-ui.php:229; for non-admins draw an unscanned row as "not scanned yet" with no click (no request). After one admin scan, counts stay current per upload (smart-folders.php:1014-1018), so Editors keep the feature. |
| `/state` | GET | core/rest-folders.php:46 | UF | none | tree.js | UF | no | — |
| `/state` | POST | core/rest-folders.php:46 | UF | the caller's own user option | tree.js:260 | UF | no | Own UI state. |
| `/stats-opt` | POST | core/instrument.php:269 | MO | option; outbound `/counts` (instrument.php:233), not AI credits | inline script instrument.php:410 → dashboard foot (box shown only to MO, :328) | MO | no | — |
| `/tree` | GET | core/rest-tree.php:163 | UF | none | tree.js:162; folders.js:1668 | UF | no | — |

## AJAX actions (`wp_ajax_`, none nopriv)

| action | file:line | current cap | writes | called from | recommended | change? |
|---|---|---|---|---|---|---|
| `vergeml_trial` | core/offer.php:145 | MO (:149) + nonce | option; outbound trial start | AI screen offer (js/vergeml-offer-trial.js) | MO | no |
| `vergeml-apply-settings-to-network` | core/options-pages.php:1221 | MNO (:1241) + nonce | network option copy | network utilities | MNO | no |
| `tb_load_editor` | core/page-builders.php:262 | UF (page-builders.php:50) | none: enqueues the tree into Themify's editor response | Themify builder | UF | no |
| `save-attachment-compat` | core/taxonomies.php:1180 | nonce + `edit_post($id)` (:1197,:1217) | one attachment: fields and terms (:1236,:1265) | js/eml-media-*.js in the modal | unchanged | no |
| `vergeml-term-counts` | core/taxonomies.php:1299 | UF + nonce | none | js/eml-media-models.js | UF | no |
| `save-attachment-order` | core/taxonomies.php:1335 | nonce + `edit_post` on the parent and on each item (:1365,:1381) | menu_order of the named attachments | grid drag-sort | unchanged | no |
| `vergeml_rest` | core/transport.php:31 | `wp_rest` nonce + UF, then the route's own callback | whatever the target route writes | the REST fallback inlined into wp-api-fetch on every admin screen for UF users (transport.php:114-154) | unchanged | no; see the bridge section |

## admin_post handlers

| action | file:line | current cap | writes | called from | recommended | change? | note |
|---|---|---|---|---|---|---|---|
| `vergeml_zip` | core/folder-tools.php:225 | UF (:229) + nonce | temp file only | tree "Download" (tree-ui.php:281) | UF | no | Read. |
| `vergeml_help_send` | core/get-help.php:192 | MO + nonce | outbound support ticket | vergeml-help | MO | no | — |
| `vergeml_export_csv` | core/import-csv.php:181 | MC + nonce | none | dashboard quick action (journey.php:1544) | MC | no | Read. Becomes admin-only in practice because the dashboard is raised. |
| `vergeml_do_file_rename` | core/journey.php:404 | MO (:412) + nonce | files on disk and posts | dashboard, only when `VERGEML_FILE_RENAME` is set (:403) | MO | no | — |
| `vergeml_undo_file_rename` | core/journey.php:405 | MO (:429) + nonce | the same | the same | MO | no | — |
| **`vergeml_do_alt`** | core/journey.php:1078 | **UF** (:1082) + nonce | **many attachments: `vergeml_ai_apply_alt(0)` writes alt on every pending image in the library**, with no per-file check (journey.php:1088 → ai.php:1796-1812) | dashboard to-do "Write the alt text" (journey.php:926) | MO | **CHANGE (rule)** | **The same write `/ai-alt` POST was raised to MO for** (ai.php:1843-1847). It is still open to any Author who can mint the nonce. The nonce action is site-wide (`vergeml_do_alt`), so any logged-in UF user can obtain it. |
| **`vergeml_do_rename`** | core/journey.php:1157 | **UF** (:1161) + nonce | titles on every pending file the user can `edit_post` (journey.php:1167 → rename.php:173) | dashboard to-do "Rename from descriptions" (journey.php:972) | MO | **CHANGE (rule)** | A library-wide run. REST `/rename` already demands MO for that (rename.php:510-514). For an Editor (`edit_others_posts`) that means every file. |
| `vergeml_licence_save` | core/licence-page.php:33 | MO + nonce | option; outbound `/licence` | vergeml-licence | MO | no | — |
| `vergeml_rename` | core/rename.php:340 | `edit_post($id)` + per-id nonce (:346,:350) | one attachment's title | media list row action (rename.php:305) | unchanged | no | Single object. |
| **`vergeml_rename_undo`** | core/rename.php:434 | **UF** (:438) + nonce | **reverts the last rename run across every author, with no per-file check** (rename.php:244-292) | the notice after a rename (rename.php:417) | MO | **CHANGE (rule)** | An Author can undo an administrator's library-wide rename. REST undo already demands MO (rename.php:510). |
| `vergeml_watchdog_resume` | core/watchdog.php:345 | `activate_plugins` + nonce | option | safe-mode notice | `activate_plugins` | no | — |

Also on the request path, but outside this table's scope (filters and bulk actions):
- `handle_bulk_actions-upload` → `vergeml_rename_handle_bulk` (rename.php:372) and `vergeml_handle_bulk_terms` (bulk-terms.php:142) are user-selected batches with per-object `edit_post`. That is core's pattern; keep them.
- `pre_get_posts` → `vergeml_meaning_take_over` (search-meaning.php:713) is the UF path to `/embed` noted above.

## Screens

| page slug | registered at | current cap | raised routes / handlers it calls | recommended | change? |
|---|---|---|---|---|---|
| `vergelabs-media` (dashboard, top level) | core/admin-menu.php:50 (callback journey.php:1225, own check :1227) | MC | ai-run GET/POST (journey.js); admin_post do_alt, do_rename (to-do list, journey.php:926,972); links into the Folders, AI and Health screens. stats-opt is already MO. | MO | CHANGE |
| `vergeml-librarian` (Folders) | core/guide.php:59 (own check :457) | MC | guide/answer, apply, audience-split, confirm, plan GET/POST, profiles-restore, progress, session POST, stop, token, turn, unconfirm, undo; ai-alt POST (already MO); autofile-* and say-* (scripts enqueued, cards dead) | MO | CHANGE |
| `vergeml-ai` | core/ai.php:2170 (own check ai-screen.php:184) | MC | ai-index, ai-run GET/POST, search-try; ai-settings is already MO; the `how` tab is already MO-only (ai-screen.php:37) | MO | CHANGE |
| `vergeml-health` | core/health.php:1247 (own check :1413) | MC | health-scan, smart-scan, quarantine-act; health-keep/keep-undo/retire/delete are already MO and today answer Editors with 403 | MO | CHANGE |
| `vergeml-import-folders` | core/import-ui.php:22 (own check :311) | MC | /import (run/undo/csv) | MO | CHANGE |
| `vergeml-licence` | core/licence-page.php:20 | MO | ai-settings (inline :311) | MO | no |
| `vergeml-help` | core/get-help.php:31 | MO | admin_post help_send | MO | no |
| `vergeml-library` | core/options-pages.php:126 | MO | — | MO | no |
| `vergeml-taxonomies` | core/options-pages.php:135 | MO | — | MO | no |
| `vergeml-mime-types` | core/options-pages.php:144 | MO | — | MO | no |
| `vergeml-media-settings` (hidden) | core/options-pages.php:117 | MO | — | MO | no |
| `vergeml-utilities` (Settings) | core/options-pages.php:184 | MO | — | MO | no |
| `vergeml-utilities` (network) | core/options-pages.php:208 | MNO | — | MNO | no |
| upload.php / media modal / post lists / builders (not ours, the tree) | core/tree-ui.php:31 | UF (`edit_posts` on post lists) | smart-scan (see its row); everything else stays | unchanged | no; hide the scan affordance for non-MO |

Once the five screens are raised, every plugin screen is MO, so Editors get no plugin menu. They keep the tree in the media library: folders, filing, privacy, quick edit, the "why here" pill and the gallery block.

Places that hard-code MC for these screens and must move with them:
- **Menu:** the menu gate (admin-menu.php:46) and `add_menu_page` cap (:53); each `add_submenu_page` cap (guide.php:63, ai.php:2174, health.php:1251, import-ui.php:26).
- **Screens' own checks:** journey.php:1227, guide.php:457, ai-screen.php:184, health.php:1413, import-ui.php:311.
- **In-page nav caps:** admin-shell.php:64,71,78,85,92.
- **Home cards** in the unused `vergeml_admin_home` (admin-menu.php:239-263).
- **Tree link:** `librarianUrl` (tree-ui.php:273), or the media library's "N files are not in a folder · Sort them" link sends Editors to a 403.

## The transport bridge (`wp_ajax_vergeml_rest`)

Yes. Each route's `permission_callback` still runs.
- `core/transport.php:75` builds a `WP_REST_Request` for the posted route and method.
- `:85` hands it to `rest_do_request()`. That calls `WP_REST_Server::dispatch()`, which matches the handler and runs its `permission_callback` before the callback.
- Arguments are validated and sanitized the same way.
- An error comes back as the route's own error (`:89-96`).

The bridge adds three gates in front:
- the `wp_rest` nonce, the one apiFetch sends (`:40-48`);
- `current_user_can('upload_files')` (`:51-53`);
- our namespace only (`:67-69`).

So raising a `permission_callback` closes both doors at once. Nothing in the bridge needs to change.

One nuance: a GET body is set as query params and anything else as body params (`:77-83`). A caller can send any method, but a route only answers a method it registered.

## Routes that need an object-scoped check (single-object writes, currently unscoped)

| route | today | what to check | where |
|---|---|---|---|
| `/autofile-act` (`accept`) | MC, files any attachment id into any term | `current_user_can('edit_post', $attachment_id)` and `current_user_can( get_taxonomy($tax)->cap->assign_terms )` | core/auto-file.php:666 (or inside `vergeml_autofile_rest_act` :765) |
| `/folder` | MC for every action on any term | create: `$tax->cap->edit_terms`; rename/colour/move: `edit_term`,`$id` (and `$parent`); delete: `delete_term`,`$id`; order: `edit_term` per id | core/rest-folders.php:31 / handler :133-294 |
| `/folder-privacy` | MC on any term id | `current_user_can('edit_term', $id)`; the owner rule at :242 stays | core/private-folders.php:215 |

These were flagged by the register but **do not** need an object check, because they become MO or are reads:
- `brief/turn` and `guide/turn`: the "id" is the turn text.
- `folders-apply`, `guide/answer`, `health-retire`, `import`, `librarian-apply-step`, `librarian-undo-step`, `organize-step`, `quarantine-act`, `rename-files`: MO.
- `/similar` and `/health-uses`: reads.

## Other findings
- **Two routes accept a list of ids from the request with no per-object check, and act on whatever they are given:**
  - `/say-run`: any post type, nl-commands.php:501-511.
  - `/merge-run`: utilities.php:448-457.

  MO closes both. A post-type check (`attachment`) belongs in `/say-run` regardless.
- **Two dead cards.** `vergeml_librarian_page_cards` is never fired, so the autofile and say cards never render, yet their scripts still load on the Folders screen (auto-file.php:845, nl-commands.php:683). Remove the enqueues or fire the action.
- **Existing 403s Editors can already reach:**
  - the Folders screen's alt step (`/ai-alt` POST);
  - the Health screen's keep/delete/retire buttons;
  - the tree's smart-folder scan for Authors.

  Raising the screens removes the first two.
