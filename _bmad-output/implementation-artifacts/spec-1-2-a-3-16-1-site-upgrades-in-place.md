---
title: 'A 3.16.1 site upgrades in place and keeps everything'
type: 'feature'
created: '2026-09-19'
status: 'in-progress'
baseline_commit: 'plugin 662569c'
route: 'dispatch'
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** No test walks this plugin from one version to the next. A customer on 3.16.1 with folders, filed pictures and a Folders session will take 4.0.0 through Plugins → Upload → Replace; the librarian schema moves 3 → 4 and nothing has proven that their rows, terms and session survive it on a real database.

**Approach:** A fifth WordPress on the Hetzner box (`upg.46.225.66.194.nip.io`, `/var/www/upg`, MySQL `wpupg`) built by 3.16.1's own code — mock describe, accepted filings, a saved Folders session — snapshotted by literal SQL, upgraded with `wp plugin install ../dist/…4.0.0.zip --force` (the `WP_Upgrader` path), and asserted by a suite that runs there. Playground runs the same walk first as the smoke.

## Boundaries & Constraints

**Always:** the snapshot is literal SQL (terms, term_taxonomy counts, term_relationships, both librarian tables, the three options), taken before the swap and diffed after, never through plugin code. ≥ 5 `moves` rows and ≥ 1 `batches` row exist before the swap, written by 3.16.1's `vergeml_autofile_file()` (the accept-a-suggestion path). Mock mode on, so no credit is spent and no licence is needed. The suite's counters live in `$GLOBALS` and it ends with `N/N passed`. The fixture's admin user is this session's own, `vgmls22`.

**Never:** touch `/var/www/wp`, `/var/www/ms2`, `/var/www/ms`, `/var/www/upd` or their databases, except to read 20 JPEGs out of `/var/www/wp/wp-content/uploads`; swap the plugin by replacing the directory; run a real describe; let Playground stand as the proof.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Version | after the swap and one wp-admin load | `vergeml_version` = `4.0.0`; `vergeml_librarian.schema` = `4` | — |
| Schema | `SHOW COLUMNS` on `vergeml_librarian_moves` | carries `source` and `hit`; the ≥ 5 old rows intact, same `move_id`s and `term_id`s | — |
| Terms | snapshot vs after | 0 differences in terms, `count`s and relationships | — |
| Session | `vergeml_guide_session` written by 3.16.1 (version 2, no `tree` key) | the Folders screen loads; `tree` reads `editing` | — |
| Lock | after the load | `vergeml_upgrading` absent | — |
| Notices | `debug.log` after the load | no line from `vergelabs-media-library/` | — |
| No session | Playground smoke starts with none | 4.0.0 provisions and opens fresh | — |
| Fixture after | the walk done | `upg` stays up as the standing upgrade fixture (Nathan, 2026-09-19); the next schema bump walks it again from 4.0.0 | — |
| Smoke | Playground: 3.16.1 zip → fixture → 4.0.0 zip via `wp plugin install --force` | the same assertions green | fail → stop before the box |

</frozen-after-approval>

## Code Map

- `../dist/vergelabs-media-library-3.16.1.zip` (`7f2a4fe9bee9`) and `…-4.0.0.zip` (`bf0d63b70056`) -- the two builds; copied to the box's `/tmp` for `wp plugin install`.
- 3.16.1 at plugin commit `348c841`: `core/auto-file.php:391` `vergeml_autofile_file( $id, $term_id, 'accepted', $reason )` writes a `suggested` batch and a move and sets the term; `core/ai.php:1027` `vergeml_ai_mock_describe`, `:362` connected() is true in mock; `core/librarian.php:50` `VERGEML_LIBRARIAN_VERSION = 3`; `core/guide.php` `vergeml_guide_fresh()` / `vergeml_guide_save()` (session version 2).
- 4.0.0: `core/librarian.php:50` version 4 (`source`, `hit`); `vergelabs-media-library.php:427-443` the `plugins_loaded` upgrade under `vergeml_upgrading`; `core/guide.php:559` merges a version-2 session with the fresh shape → `tree = editing`.
- Box pattern: `/etc/nginx/sites-enabled/upd` (server_name `upd.46.225.66.194.nip.io`, root `/var/www/upd`, php8.5-fpm, `client_max_body_size 64m`); `wp-config.php` there for `DB_USER wp` and the password; databases `wp wpms wpms2 wpupd`; wp-cli 2.12 as root with `--allow-root`.
- `tools/verify.mjs:413,562` -- box suites always `cd ${ BOX.wp }`; add a per-suite `wp:` path so this one runs in `/var/www/upg`.
- Mirrors: `tools/box-shop-untree.php` (freeze by literal SQL to a JSON file, restore, prove the restore first); `tests/librarian/gate7-schema.php` (schema checks A–D, `$GLOBALS` counters); `tests/compat/upgrade.js` + `upgrade-helper.php` (Playground blueprint: install zip → runPHP fixture → swap → assert, output through a mounted directory); `tools/box-batch-23.php` (option schema vs code constant).
- `tests/tree/post-folders.php:58-66` -- `vergeml_taxonomies` option shape; `media_category` is registered by default, so folders need no setting for attachments.
- Pictures: 20 JPEGs copied from `/var/www/wp/wp-content/uploads/` (read-only there) and `wp media import`ed.

## Tasks & Acceptance

**Execution:**
- [x] `tools/box-upgrade-site.sh` -- new: create `/var/www/upg` (`wp core download/config/install`, `wpupg`, admin `vgmls22`, nginx block from `upd`'s, reload), install a given zip with `wp plugin install <zip> --force --activate`; idempotent; prints the site URL -- the fixture.
- [x] `tests/compat/upgrade-3161-fixture.php` -- new, runs under 3.16.1: mock on, import the 20 pictures, three folders, `vergeml_ai_describe` (mock) for all, `vergeml_autofile_file(…, 'accepted')` for six, `vergeml_guide_save( vergeml_guide_fresh() )`; prints the counts -- the state a customer has.
- [x] `tests/compat/upgrade-3161-snapshot.php` -- new: literal SQL → `/tmp/vgml-upg-<stamp>.json` (terms, term_taxonomy, term_relationships, librarian batches and moves, the three options); `--compare` mode diffs the live tables against the file and prints `N differences` -- mirror `box-shop-untree.php`.
- [x] `tests/compat/upgrade-3161.php` -- new: the matrix rows as checks (version, schema, columns, old rows, terms diff 0, session `tree`, no lock, no notice), `$GLOBALS` counters, `N/N passed` -- mirror `gate7-schema.php`.
- [x] `tools/verify.mjs` -- register `upgrade-3161` with `env: 'box', php: true, wp: '/var/www/upg'`; honour `suite.wp` at line 562.
- [x] Playground smoke -- `tests/compat/upgrade-3161.spec.mjs` (new, mirror `upgrade.js`): blueprint installs the 3.16.1 zip, runs the fixture, `wp plugin install` the 4.0.0 zip `--force`, loads `/wp-admin/`, runs the suite; output read back from a mounted directory.
- [x] The box walk, in order: site with 3.16.1 → fixture → snapshot → `wp plugin install …4.0.0.zip --force` → `/wp-admin/` loaded as `vgmls22` → suite → snapshot `--compare` → a screenshot of the Folders screen.

**Acceptance Criteria:**
- Given the fixture under 3.16.1, when the snapshot is taken, then it holds ≥ 5 moves, ≥ 1 batch, 3 folders and 20 relationships, and `vergeml_librarian.schema` = 3.
- Given the swap and one admin load, when `node tools/verify.mjs upgrade-3161` runs, then it prints its own `N/N passed` and the compare prints `0 differences`.
- Given the Playground smoke, when it runs first, then the same suite is green there.

## Implementation Notes

- 2026-09-19 14:10–14:45Z. Fixture `upg.46.225.66.194.nip.io` created by `tools/box-upgrade-site.sh create` (`/var/www/upg`, `wpupg`, admin `vgmls22`, password in `/root/.upg-admin-pass` on the box, nginx block from `upd`'s). Kept up (Nathan).
- Playground smoke first: `SMOKE GREEN — 22/22 passed; 0 differences` (98 s). Two smoke-only limits: the index has no rows there (SQLite refuses the packed embedding) and the loopback admin request cannot run; both checks are box-only under `VGML_SMOKE`. The 3.16.1 plugin is installed through the `installPlugin` step, not mounted — `Plugin_Upgrader` cannot replace a mount.
- Box, 3.16.1 by its own code: `pictures 20 · folders 2,3,4 · described 20 · filed 6 · session v2 no tree key · batches 1 · moves 6 · schema 3`; the index held 20 rows on MySQL; `moves` had the 13 schema-3 columns. Snapshot frozen to `/tmp/vgml-upg.json`.
- `wp plugin install /tmp/vgml-400.zip --force`: "Removing the old version of the plugin… Plugin updated successfully", active 4.0.0; login 200, `upload.php` 200; `vergeml_librarian` `{schema:4,pruned:…}`.
- Compare: `0 differences`. `node tools/verify.mjs upgrade-3161`: **23/24 passed** — the one FAIL is 4.0.0 itself on PHP 8.5: `PHP Deprecated: vergeml_ai_rest_status(): Implicitly marking parameter $request as nullable` (`core/ai.php:1995`), the only such site in the plugin. Fixed in the tree (`?WP_REST_Request`), proven with PHP 8.5's linter: shipped file 2 deprecation lines, fixed file 0. The fixture runs the 4.0.0 archive, so the suite stays 23/24 there until 4.0.1 is cut — the sequencing note's cut gains a third item.
- Screenshot `docs/superpowers/mocks/shots/2026-09-19-upgrade-3161-folders.png`: Folders on 4.0.0 over the 3.16.1 site, 20 · 20 · 3, Tree step open, *This is my tree*.
- Seen, not this story: in mock mode 4.0.0's Folders conversation says "needs a licence" where 3.16.1's mock counted as ready.
- Cost: none. Nothing on ms2, wp, ms or upd beyond reading 20 JPEGs from wp's uploads.

## Spec Change Log

## Review Triage Log

## Verification

**Commands:**
- `node tests/compat/upgrade-3161.spec.mjs` (or via `verify.mjs upgrade-3161-smoke`) -- expected: `N/N passed` from Playground
- `node tools/verify.mjs upgrade-3161` -- expected: `N/N passed` on `/var/www/upg`, and `0 differences`
- Screenshot `docs/superpowers/mocks/shots/2026-09-19-upgrade-3161-folders.png` -- the Folders screen after the swap, tree step, session intact
- Cost: none (mock mode; no describe against the service)
