# The security surface

**Generated. Do not edit.** `node tools/security-surface.mjs` writes this file;
`node tools/security-surface.mjs --check` fails when the code has moved and this
file has not, which is what keeps a new route from arriving undocumented.

Read from 66 shipped PHP files — `git ls-files` minus the directories
tools/deploy.mjs refuses to ship, so nothing in `tests/` or `tools/` is counted as a
way in, because none of it reaches a site.

Phase 5.1 of `plans/four-yesses.md`. The three questions of 5.2 — **can this caller
do this at all**, **may they do it to _this_ object**, **did they mean to** — are the
capability, object and nonce columns. A row marked **read me** is one the generator
could not answer; it is not yet a finding and it is not yet safe.

## The count

| | count |
|---|---|
| `register_rest_route` calls | 69 |
| REST routes | 69 |
| REST **endpoints** (route × method config) | **76** |
| AJAX actions | 7 (0 nopriv) |
| `admin_post` handlers | 11 (0 nopriv) |
| cron hooks | 4 |
| admin screens | 13 |
| front-end entries (shortcode, block render) | 2 |
| registered settings | 7 |
| meta fields core exposes over REST | 2 |
| filters/actions whose callback reads the request | 36 |
| entries with no capability the reader could find | 2 |
| AJAX/admin_post entries that change something with no nonce check | 0 |
| REST entries with an unscoped capability over an id from the request | 13 |

## REST endpoints

Capability is what `current_user_can` was called with, in the permission callback or
in the handler beneath it; a name in brackets is the object it was asked about.
Anything without brackets is a site-wide yes.

| route | method | permission callback | capability | object-scoped | reads | writes | id from request | where |
|---|---|---|---|---|---|---|---|---|
| `/vergeml/v1/ai-alt` | GET | closure | upload_files | no | — | db query | no | core/ai.php:1787 |
| `/vergeml/v1/ai-alt` | POST | closure | manage_options | no | `limit`, $request | meta/option write, db query | no | core/ai.php:1787 |
| `/vergeml/v1/ai-index` | POST | closure | manage_categories | no | `scope`, `limit`, `apply_alt`, $request | db query, meta/option write, db write, outbound http, schedules cron | no | core/ai.php:1956 |
| `/vergeml/v1/ai-run` | GET | closure | manage_categories | no | — | schedules cron, outbound http | no | core/ai-background.php:454 |
| `/vergeml/v1/ai-run` | POST | closure | manage_categories | no | `action`, `scope`, `apply_alt`, $request | db query, meta/option write, schedules cron, outbound http | no | core/ai-background.php:454 |
| `/vergeml/v1/ai-settings` | POST | closure | manage_options, manage_network_options | no | `license_key`, `site_profile`, `auto_alt`, `enrich_search`, `page_context`, `mock`, $request | meta/option write, db query, outbound http | no | core/ai.php:1970 |
| `/vergeml/v1/ai-status` | GET | closure | manage_categories | no | — | db query, meta/option write, outbound http | no | core/ai.php:1948 |
| `/vergeml/v1/alt-fill-step` | POST | $can | manage_categories | no | `limit`, $request | meta/option write, db query | no | core/utilities.php:420 |
| `/vergeml/v1/alt-undo` | POST | $can | manage_categories | no | — | db query, meta/option write | no | core/utilities.php:427 |
| `/vergeml/v1/assign` | POST | vergeml_can_assign | upload_files, edit_posts, edit_post($attachment_id), edit_post($attachment_id) | yes (2) | `taxonomy*`, `post_type`, `attachments`, `add`, `remove`, `mode`, `batch`, $request | term assign, db query | yes | core/rest-tree.php:184 |
| `/vergeml/v1/autofile-act` | POST | $can | manage_categories | no | `attachment_id*`, `term_id*`, `action*`, $request | meta/option write, term assign, db write, db query | yes | core/auto-file.php:645 |
| `/vergeml/v1/autofile-step` | POST | $can | manage_categories | no | `limit`, $request | db query, meta/option write, term assign, db write | no | core/auto-file.php:636 |
| `/vergeml/v1/brief/adopt` | POST | $may | manage_options | no | — | meta/option write, db write, db query, outbound http, schedules cron | no | core/brief.php:662 |
| `/vergeml/v1/brief/discard` | POST | $may | manage_options | no | — | meta/option write | no | core/brief.php:667 |
| `/vergeml/v1/brief/session` | GET | $may | manage_options | no | $request | meta/option write, db query | no | core/brief.php:630 |
| `/vergeml/v1/brief/session` | POST | $may | manage_options | no | `reset`, `draft`, $request | meta/option write, db query | no | core/brief.php:630 |
| `/vergeml/v1/brief/test` | POST | $may | manage_options | no | — | db query, outbound http, meta/option write | no | core/brief.php:657 |
| `/vergeml/v1/brief/token` | POST | $may | manage_options | no | — | db query, outbound http, meta/option write | no | core/brief.php:642 |
| `/vergeml/v1/brief/turn` | POST | $may | manage_options | no | `said`, `say`, `draft`, $request | meta/option write | yes | core/brief.php:647 |
| `/vergeml/v1/file/(?P<id>\d+)` | POST, PUT, PATCH | closure | edit_post((int) $request['id']) | yes (1) | `title`, `alt`, $request | post/term write, meta/option write | no | core/quick-edit.php:161 |
| `/vergeml/v1/folder` | POST | vergeml_can_manage_folders | manage_categories | no | `taxonomy*`, `action*`, `id`, `parent`, `name`, `color`, `ids`, `post_type`, $request | post/term write, delete, meta/option write, db query | yes | core/rest-folders.php:28 |
| `/vergeml/v1/folder-privacy` | POST | closure | manage_categories | no | `id*`, `private`, $request | meta/option write | yes | core/private-folders.php:205 |
| `/vergeml/v1/folders-apply` | POST | $may | manage_categories | no | `folders*`, `plan_id*`, $request | db query, post/term write, meta/option write, outbound http, term assign, delete, schedules cron | yes | core/folder-talk.php:1819 |
| `/vergeml/v1/folders-progress` | GET | $may | manage_categories | no | — | outbound http, schedules cron | no | core/folder-talk.php:1841 |
| `/vergeml/v1/folders-propose` | POST | $may | manage_categories | no | `instruction*`, `history`, `mode`, $request | outbound http, db query | no | core/folder-talk.php:1801 |
| `/vergeml/v1/folders-undo` | POST | $may | manage_categories | no | — | post/term write, delete, term assign, meta/option write, db query | no | core/folder-talk.php:1829 |
| `/vergeml/v1/folders/version` | GET | vergeml_can_read_tree | upload_files | no | — | — | no | core/folders-version.php:102 |
| `/vergeml/v1/gallery-folders` | GET | vergeml_can_read_tree | upload_files | no | `taxonomy`, $request | — | no | core/gallery-block.php:351 |
| `/vergeml/v1/guide/apply` | POST | $may | manage_categories | no | — | outbound http, schedules cron, meta/option write, db query, post/term write, term assign, delete | no | core/guide.php:716 |
| `/vergeml/v1/guide/progress` | GET | $may | manage_categories | no | — | outbound http, schedules cron, meta/option write | no | core/guide.php:721 |
| `/vergeml/v1/guide/rule` | POST | $may | manage_categories | no | `rule*`, `options`, $request | db query | no | core/guide.php:707 |
| `/vergeml/v1/guide/rules` | GET | $may | manage_categories | no | — | db query | no | core/guide.php:702 |
| `/vergeml/v1/guide/session` | GET | $may | manage_categories | no | $request | meta/option write, db query | no | core/guide.php:673 |
| `/vergeml/v1/guide/session` | POST | $may | manage_categories | no | `draft`, `reset`, $request | meta/option write, db query | no | core/guide.php:673 |
| `/vergeml/v1/guide/stop` | POST | $may | manage_categories | no | — | outbound http, schedules cron, meta/option write | no | core/guide.php:726 |
| `/vergeml/v1/guide/token` | POST | $may | manage_categories | no | — | db query, meta/option write, outbound http | no | core/guide.php:685 |
| `/vergeml/v1/guide/turn` | POST | $may | manage_categories | no | `said`, `say`, `draft`, `turns`, $request | meta/option write, db query, outbound http | yes | core/guide.php:690 |
| `/vergeml/v1/guide/undo` | POST | $may | manage_categories | no | — | post/term write, delete, term assign, meta/option write, db query | no | core/guide.php:731 |
| `/vergeml/v1/health-delete` | POST | closure | manage_options, delete_posts | no | `keep*`, `drop*`, $request | delete, db query, db write, meta/option write | no | core/health-delete.php:470 |
| `/vergeml/v1/health-keep` | POST | $can | manage_options | no | `keep*`, `drop*`, $request | db write, db query, meta/option write | no | core/health-keep.php:824 |
| `/vergeml/v1/health-keep-undo` | POST | $can | manage_options | no | `token*`, $request | db write, db query, meta/option write | no | core/health-keep.php:834 |
| `/vergeml/v1/health-report` | GET | closure | manage_categories | no | — | db query | no | core/health.php:1211 |
| `/vergeml/v1/health-retire` | POST | $can | manage_options | no | `ids*`, `undo`, $request | meta/option write | yes | core/health-keep.php:843 |
| `/vergeml/v1/health-scan` | POST | closure | manage_categories | no | `cursor`, `reset`, $request | db write, meta/option write, db query | no | core/health.php:1197 |
| `/vergeml/v1/health-uses` | GET | closure | manage_categories | no | `ids*`, $request | — | yes | core/health-delete.php:490 |
| `/vergeml/v1/import` | POST | vergeml_can_import | manage_categories | no | `action*`, `source`, `taxonomy`, `id`, `resume`, `text`, $request | db query, meta/option write, delete, term assign, post/term write | yes | core/import-ui.php:37 |
| `/vergeml/v1/librarian-apply-step` | POST | $can | manage_categories | no | `batch_id`, `scheme`, `run_id`, `branches`, $request | db write, meta/option write, db query, term assign, post/term write | yes | core/librarian.php:2535 |
| `/vergeml/v1/librarian-batches` | GET | $can | manage_categories | no | — | db query | no | core/librarian.php:2569 |
| `/vergeml/v1/librarian-pause` | POST | $can | manage_categories | no | `batch_id*`, $request | db query | yes | core/librarian.php:2551 |
| `/vergeml/v1/librarian-preflight` | GET, POST | $can | manage_categories | no | `scheme`, `run_id`, `branches`, $request | db query | yes | core/librarian.php:2524 |
| `/vergeml/v1/librarian-schemes` | GET | $can | manage_categories | no | `scheme`, $request | db query | no | core/librarian.php:2504 |
| `/vergeml/v1/librarian-undo-step` | POST | $can | manage_categories | no | `batch_id*`, $request | term assign, db query, delete, meta/option write | yes | core/librarian.php:2560 |
| `/vergeml/v1/librarian-why/(?P<id>\d+)` | GET | closure | edit_post((int) $request['id']) | yes (1) | — | db query | no | core/librarian.php:2590 |
| `/vergeml/v1/merge-plan` | GET | $can | manage_categories | no | — | db query | no | core/utilities.php:407 |
| `/vergeml/v1/merge-run` | POST | $can | manage_categories | no | `plan*`, $request | meta/option write | no | core/utilities.php:413 |
| `/vergeml/v1/organize-cancel` | POST | closure | manage_categories | no | `run_id*`, $request | db query | yes | core/organize.php:2894 |
| `/vergeml/v1/organize-quote` | GET | closure | manage_categories | no | — | db query | no | core/organize.php:2917 |
| `/vergeml/v1/organize-run` | GET | closure | manage_categories | no | `run_id`, `compare`, $request | db query | yes | core/organize.php:2905 |
| `/vergeml/v1/organize-step` | POST | closure | manage_categories | no | `run_id`, `parent_run_id`, `refine`, $request | db query, db write | yes | core/organize.php:2878 |
| `/vergeml/v1/quarantine` | GET | $can | manage_categories | no | — | — | no | core/quarantine.php:391 |
| `/vergeml/v1/quarantine-act` | POST | $can | manage_categories | no | `ids*`, `action*`, `reason`, $request | meta/option write | yes | core/quarantine.php:402 |
| `/vergeml/v1/quarantine-manifest` | GET | $can | manage_categories | no | — | — | no | core/quarantine.php:413 |
| `/vergeml/v1/rename` | GET | closure | upload_files | no | — | db query | no | core/rename.php:485 |
| `/vergeml/v1/rename` | POST | closure | upload_files, manage_options, edit_post($id) | yes (1) | `action`, `limit`, `ids`, $request | post/term write, meta/option write, db query | yes | core/rename.php:485 |
| `/vergeml/v1/rename-files` | GET | closure | manage_options | no | — | db query | no | core/rename-file.php:565 |
| `/vergeml/v1/rename-files` | POST | closure | manage_options | no | `action`, `limit`, `ids`, $request | meta/option write, filesystem, post/term write, db query | yes | core/rename-file.php:565 |
| `/vergeml/v1/say-plan` | POST | $can | manage_categories | no | `text*`, $request | filesystem | no | core/nl-commands.php:551 |
| `/vergeml/v1/say-run` | POST | $can | manage_categories | no | `plan*`, $request | post/term write, term assign, meta/option write, db write, db query | no | core/nl-commands.php:560 |
| `/vergeml/v1/search-meaning` | GET | closure | upload_files | no | `s*`, `limit`, $request | db query, outbound http, db write, schedules cron | no | core/search-meaning.php:509 |
| `/vergeml/v1/search-try` | GET | closure | upload_files | no | `s*`, $request | db query, outbound http, db write, schedules cron | no | core/search-try.php:193 |
| `/vergeml/v1/similar` | GET | $can | manage_categories | no | `id*`, `limit`, $request | db query | yes | core/utilities.php:433 |
| `/vergeml/v1/smart-scan` | POST | closure | manage_categories | no | `resume`, $request | db query, meta/option write | no | core/smart-folders.php:1111 |
| `/vergeml/v1/state` | GET | vergeml_can_read_tree | upload_files | no | `taxonomy*`, $request | — | no | core/rest-folders.php:46 |
| `/vergeml/v1/state` | POST | vergeml_can_read_tree | upload_files | no | `taxonomy*`, `open`, `selected`, `width`, `collapsed`, `filtersOpen`, `aiOpen`, `skin`, `density`, $request | — | no | core/rest-folders.php:46 |
| `/vergeml/v1/stats-opt` | POST | closure | manage_options | no | `opted*`, $request | meta/option write, db query, outbound http | no | core/instrument.php:264 |
| `/vergeml/v1/tree` | GET | vergeml_can_read_tree | upload_files | no | `taxonomy*`, `post_type`, $request | db query | no | core/rest-tree.php:163 |

`*` on an argument name means the route declares it required.

### REST rows a person must read

| route | method | why | where |
|---|---|---|---|
| `/vergeml/v1/autofile-act` | POST | unscoped capability on an id from the request | core/auto-file.php:645 |
| `/vergeml/v1/brief/turn` | POST | unscoped capability on an id from the request | core/brief.php:647 |
| `/vergeml/v1/folders-apply` | POST | unscoped capability on an id from the request | core/folder-talk.php:1819 |
| `/vergeml/v1/guide/turn` | POST | unscoped capability on an id from the request | core/guide.php:690 |
| `/vergeml/v1/health-retire` | POST | unscoped capability on an id from the request | core/health-keep.php:843 |
| `/vergeml/v1/import` | POST | unscoped capability on an id from the request | core/import-ui.php:37 |
| `/vergeml/v1/librarian-apply-step` | POST | unscoped capability on an id from the request | core/librarian.php:2535 |
| `/vergeml/v1/librarian-undo-step` | POST | unscoped capability on an id from the request | core/librarian.php:2560 |
| `/vergeml/v1/organize-step` | POST | unscoped capability on an id from the request | core/organize.php:2878 |
| `/vergeml/v1/folder-privacy` | POST | unscoped capability on an id from the request | core/private-folders.php:205 |
| `/vergeml/v1/quarantine-act` | POST | unscoped capability on an id from the request | core/quarantine.php:402 |
| `/vergeml/v1/rename-files` | POST | unscoped capability on an id from the request | core/rename-file.php:565 |
| `/vergeml/v1/folder` | POST | unscoped capability on an id from the request | core/rest-folders.php:28 |

## AJAX actions

Every one of these answers on `admin-ajax.php`. A `wp_ajax_` action requires a logged-in
user and nothing more; `wp_ajax_nopriv_` requires nothing at all.

| action | nopriv | callback | capability | nonce | reads | writes | where |
|---|---|---|---|---|---|---|---|
| `vergeml-apply-settings-to-network` | no | vergeml_apply_settings_to_network | manage_network_options | check_ajax_referer | $_REQUEST | meta/option write | core/options-pages.php:1228 |
| `vergeml-admin-notice-dismiss` | no | vergeml_admin_notice_dismiss | — | check_ajax_referer | $_POST | meta/option write | core/options-pages.php:3575 |
| `tb_load_editor` | no | vergeml_builder_load_tree | upload_files, upload_files, edit_posts, upload_files, manage_categories, manage_categories | — | $request | db query | core/page-builders.php:262 |
| `save-attachment-compat` | no | vergeml_save_attachment_compat | edit_post($id) | check_ajax_referer | $_REQUEST | post/term write, term assign, db query | core/taxonomies.php:1173 |
| `delete-post` | no | vergeml_delete_post | delete_post($id) | check_ajax_referer | $_POST | delete, db query | core/taxonomies.php:1289 |
| `save-attachment-order` | no | vergeml_save_attachment_order | edit_post($post_id), edit_post($attachment_id) | check_ajax_referer | $_REQUEST | db query | core/taxonomies.php:1345 |
| `vergeml_rest` | no | vergeml_transport_bridge | — | wp_verify_nonce | $_REQUEST, $_SERVER, php://input | — | core/transport.php:31 |

## admin_post handlers

A form post to `admin-post.php`. These carry no REST permission callback, so the
capability and the nonce are the whole of the gate.

| action | nopriv | callback | capability | nonce | reads | writes | where |
|---|---|---|---|---|---|---|---|
| `vergeml_zip` | no | vergeml_zip_download | upload_files | check_admin_referer | $_GET | delete | core/folder-tools.php:172 |
| `vergeml_help_send` | no | vergeml_help_send | manage_options | check_admin_referer | $_POST | outbound http | core/get-help.php:165 |
| `vergeml_export_csv` | no | vergeml_csv_download | manage_categories | check_admin_referer | $_GET | — | core/import-csv.php:156 |
| `vergeml_do_file_rename` | no | vergeml_journey_do_file_rename | manage_options | check_admin_referer | — | meta/option write, filesystem, post/term write, db query | core/journey.php:397 |
| `vergeml_undo_file_rename` | no | vergeml_journey_undo_file_rename | manage_options | check_admin_referer | — | meta/option write, filesystem, post/term write | core/journey.php:418 |
| `vergeml_do_alt` | no | vergeml_journey_do_alt | upload_files | check_admin_referer | — | meta/option write, db query | core/journey.php:1071 |
| `vergeml_do_rename` | no | vergeml_journey_do_rename | upload_files, edit_post($id) | check_admin_referer | — | post/term write, meta/option write, db query | core/journey.php:1150 |
| `vergeml_licence_save` | no | vergeml_licence_save | manage_options | check_admin_referer | $_POST | meta/option write, outbound http | core/licence-page.php:33 |
| `vergeml_rename` | no | vergeml_rename_handle_one | edit_post($id), edit_post($id) | check_admin_referer | $_GET | post/term write, meta/option write, db query | core/rename.php:337 |
| `vergeml_rename_undo` | no | vergeml_rename_handle_undo | upload_files | check_admin_referer | — | post/term write, meta/option write, db query | core/rename.php:431 |
| `vergeml_watchdog_resume` | no | vergeml_watchdog_resume | activate_plugins | check_admin_referer | — | meta/option write | core/watchdog.php:345 |

## Cron hooks

A cron hook runs with no user. Nothing inside one may depend on a capability, and
anything it spends has to be decided before it is booked.

| hook | booked at | answered by | writes | reads |
|---|---|---|---|---|
| `vergeml_ai_run_tick` | core/ai-background.php:419 | vergeml_ai_run_tick (core/ai-background.php:288) | schedules cron, db query, meta/option write, db write, outbound http | — |
| `vergeml_talk_refile_event` | core/folder-talk.php:1400 | vergeml_talk_refile_event (core/folder-talk.php:1420) | db query, term assign, meta/option write, delete, db write, outbound http, schedules cron | — |
| `vergeml_meaning_convert` | core/search-meaning.php:444 | vergeml_meaning_convert_tick (core/search-meaning.php:451) | db query, db write, schedules cron | — |
| `vergeml_provision_site` | vergelabs-media-library.php:455 | vergeml_provision_site (vergelabs-media-library.php:260) | meta/option write | — |

## Admin screens

The capability in the column is the one the menu was registered with, and it is the
whole of what WordPress checks before the callback runs. A screen that then reads
`$_GET` and acts on it needs its own nonce; a screen that only renders does not.

| slug | registered as | capability | callback | reads | writes | nonce | where |
|---|---|---|---|---|---|---|---|
| `eml-settings` | add_options_page | manage_options | vergeml_print_settings (core/options-pages.php:786) | — | — | — | core/options-pages.php:191 |
| `eml-settings` | add_submenu_page | manage_network_options | vergeml_print_network_settings (core/options-pages.php:995) | $_GET | meta/option write, outbound http | wp_verify_nonce | core/options-pages.php:215 |
| `media` | add_submenu_page | manage_options | vergeml_print_media_settings (core/options-pages.php:385) | — | — | — | core/options-pages.php:124 |
| `media-ai` | add_submenu_page | manage_categories | vergeml_ai_page (core/ai-screen.php:182) | $_GET | — | — | core/ai.php:2111 |
| `media-health` | add_submenu_page | manage_categories | vergeml_health_page (core/health.php:1411) | — | — | — | core/health.php:1247 |
| `media-help` | add_submenu_page | manage_options | vergeml_help_page (core/get-help.php:250) | $_GET | outbound http | — | core/get-help.php:34 |
| `media-import-folders` | add_submenu_page | manage_categories | vergeml_import_screen (core/import-ui.php:309) | — | — | — | core/import-ui.php:22 |
| `media-librarian` | add_submenu_page | manage_categories | vergeml_folders_page (core/guide.php:278) | — | — | — | core/guide.php:57 |
| `media-library` | add_submenu_page | manage_options | vergeml_print_media_library_options (core/options-pages.php:2129) | — | — | — | core/options-pages.php:133 |
| `media-licence` | add_submenu_page | manage_options | vergeml_licence_page (core/licence-page.php:85) | $_GET | meta/option write, outbound http | — | core/licence-page.php:20 |
| `media-taxonomies` | add_submenu_page | manage_options | vergeml_print_taxonomies_options (core/options-pages.php:2645) | — | — | — | core/options-pages.php:142 |
| `mime-types` | add_submenu_page | manage_options | vergeml_print_mimetypes_options (core/options-pages.php:3141) | — | — | — | core/options-pages.php:151 |
| `vergelabs-media` | add_menu_page | manage_categories | vergeml_journey_screen (core/journey.php:1218) | $_GET | meta/option write, outbound http | — | core/admin-menu.php:50 |

## The front end

These run for a visitor who is not logged in, on attributes that came out of post
content. No capability applies and none should. What matters is that an attribute is
cast before it reaches a query, and that a private folder does not become a public
gallery.

| name | kind | render callback | reads | writes | where |
|---|---|---|---|---|---|
| `vergelabs/folder-gallery` | block | vergeml_render_gallery_block (core/gallery-block.php:241) | — | — | core/gallery-block.php:111 |
| `vergeml_gallery` | shortcode | vergeml_gallery_shortcode (core/gallery-widgets.php:119) | — | — | core/gallery-widgets.php:117 |

## Registered settings

Saved by `options.php`, which checks the option group's capability and nonce itself
and then calls the sanitize callback. A setting with no sanitize callback is stored
as it arrived.

| option | group | sanitize callback | where |
|---|---|---|---|
| `vergeml_lib_options` | media-library | 'vergeml_lib_options_validate' | core/options-pages.php:20 |
| `vergeml_taxonomies` | media-taxonomies | 'vergeml_taxonomies_validate' | core/options-pages.php:27 |
| `vergeml_tax_options` | media-taxonomies | 'vergeml_tax_options_validate' | core/options-pages.php:34 |
| `vergeml_mimes` | mime-types | 'vergeml_mimes_validate' | core/options-pages.php:41 |
| `vergeml_network_options` | eml-network-settings | 'vergeml_sanitize_option_array' | core/options-pages.php:50 |
| `vergeml_backup` | vergeml_backup | 'vergeml_sanitize_option_array' | core/options-pages.php:57 |
| `vergeml_notices` | vergeml_notices | 'vergeml_sanitize_option_array' | core/options-pages.php:64 |

## Meta fields core exposes over REST

Registered with `show_in_rest`, which means core accepts writes to them on its own
routes — `/wp/v2/media`, `/wp/v2/media_category` — without any route of ours being
involved. The `auth_callback` is the gate, and its default is `edit_posts`.

| key | on | sanitize | auth callback | capability | where |
|---|---|---|---|---|---|
| `vergeml_color` | term meta | 'vergeml_sanitize_color' | closure | manage_categories | core/rest-tree.php:46 |
| `vergeml_order` | term meta | 'absint' | closure | manage_categories | core/rest-tree.php:54 |

## Filters and actions that act on the request

Not an endpoint, and that is the point: these run on somebody else's request, so the
capability gate belongs to whatever screen they fire on, not to them.

| hook | kind | callback | reads | writes | capability | nonce | where |
|---|---|---|---|---|---|---|---|
| `add_attachment` | action | vergeml_file_upload_into_folder | $_POST | term assign | — | — | core/folder-tools.php:31 |
| `admin_enqueue_scripts` | action | vergeml_admin_enqueue_scripts | $_GET | — | — | — | vergelabs-media-library.php:672 |
| `admin_init` | action | vergeml_admin_menu_redirects | $_GET | — | — | — | core/admin-menu.php:142 |
| `admin_init` | action | vergeml_connect_router | $_GET | meta/option write, outbound http | manage_options | wp_verify_nonce | core/connect.php:66 |
| `admin_init` | action | vergeml_neighbour_dismiss | $_GET | meta/option write | — | wp_verify_nonce | core/neighbours.php:121 |
| `admin_init` | action | vergeml_settings_export | $_POST | — | manage_options, manage_network_options | wp_verify_nonce | core/options-pages.php:1562 |
| `admin_init` | action | vergeml_settings_import | $_POST, $_FILES | meta/option write | manage_options, manage_network_options | wp_verify_nonce | core/options-pages.php:1607 |
| `admin_init` | action | vergeml_settings_restoring | $_POST | meta/option write | manage_options, manage_network_options | wp_verify_nonce | core/options-pages.php:1699 |
| `admin_init` | action | vergeml_uninstall_wipe_save | $_POST | meta/option write | manage_options | wp_verify_nonce | core/options-pages.php:1749 |
| `admin_init` | action | vergeml_settings_cleanup | $_POST | db write, db query, delete, meta/option write | manage_options, manage_network_options | wp_verify_nonce | core/options-pages.php:1773 |
| `admin_menu` | action | vergeml_guide_redirect | $_GET | — | — | — | core/guide.php:40 |
| `admin_menu` | action | vergeml_submenu_order | $_GET | — | — | — | core/options-pages.php:238 |
| `admin_notices` | action | vergeml_bulk_terms_notice | $_GET | — | — | — | core/bulk-terms.php:222 |
| `admin_notices` | action | vergeml_list_move_notice | $_GET | — | — | — | core/media-list.php:614 |
| `admin_notices` | action | vergeml_rename_notice | $_GET | — | — | — | core/rename.php:396 |
| `admin_notices` | action | vergeml_rename_undo_notice | $_GET | — | — | — | core/rename.php:448 |
| `admin_page_access_denied` | action | vergeml_admin_menu_redirects | $_GET | — | — | — | core/admin-menu.php:141 |
| `ajax_query_attachments_args` | filter | vergeml_quarantine_hide_grid | $_POST | — | — | — | core/quarantine.php:229 |
| `ajax_query_attachments_args` | filter | vergeml_smart_grid_query | $_POST | — | — | — | core/smart-folders.php:1142 |
| `ajax_query_attachments_args` | filter | vergeml_ajax_query_attachments_args | $_REQUEST | — | — | — | core/taxonomies.php:333 |
| `attachment_fields_to_edit` | filter | vergeml_why_here_field | $_REQUEST | db query | — | — | core/librarian.php:3164 |
| `handle_bulk_actions-upload` | filter | vergeml_handle_bulk_terms | $_REQUEST | term assign | edit_post($post_id) | — | core/bulk-terms.php:142 |
| `init` | action | vergeml_builder_register | $_GET | — | — | — | core/page-builders.php:178 |
| `network_admin_menu` | action | vergeml_update_network_licence | $_POST | — | manage_network_options | wp_verify_nonce | core/options-pages.php:1374 |
| `network_admin_menu` | action | vergeml_update_network_settings | $_POST | — | manage_network_options | check_admin_referer | core/options-pages.php:1509 |
| `parse_tax_query` | action | vergeml_backend_parse_tax_query | $_REQUEST | — | — | — | core/taxonomies.php:716 |
| `pre_get_posts` | action | vergeml_folder_filter_posts | $_GET | — | — | — | core/post-folders.php:240 |
| `pre_get_posts` | action | vergeml_quarantine_hide_list | $_GET | — | — | — | core/quarantine.php:249 |
| `pre_get_posts` | action | vergeml_meaning_take_over | $_GET | db query, outbound http, db write, schedules cron | — | — | core/search-meaning.php:618 |
| `pre_get_posts` | action | vergeml_smart_list_query | $_GET | — | — | — | core/smart-folders.php:1168 |
| `rest_api_init` | action | vergeml_ai_alt_route | $request | db query, meta/option write | upload_files, manage_options | — | core/ai.php:1783 |
| `rest_api_init` | action | vergeml_file_rename_routes | $request | db query, meta/option write, filesystem, post/term write | manage_options | — | core/rename-file.php:560 |
| `rest_api_init` | action | vergeml_rename_routes | $request | db query, post/term write, meta/option write | upload_files, manage_options, edit_post($id) | — | core/rename.php:481 |
| `restrict_manage_posts` | action | vergeml_list_folder_filter | $_REQUEST | db query | — | — | core/media-list.php:359 |
| `restrict_manage_posts` | action | vergeml_meaning_offer | $_GET | — | — | — | core/search-meaning.php:575 |
| `restrict_manage_posts` | action | vergeml_restrict_manage_posts | $_REQUEST | — | manage_options | — | core/taxonomies.php:492 |

