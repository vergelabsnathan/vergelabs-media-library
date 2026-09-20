---
title: 'Pro 1.0.2 works on 4.0.0'
type: 'feature'
created: '2026-09-20'
status: 'done'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: '5ead7d40dd4fbbfca3a078fba7931234263ebea9'
context:
  - '{project-root}/_bmad-output/implementation-artifacts/epic-1-context.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Pro 1.0.2 was built against the old free plugin; 4.0.0 removed the Rules tab, rebuilt the media list and reshaped routes. If Pro breaks, every Pro customer breaks on update day, and nobody has tried it. What customers run is the served archive (`2a6a7946426f`, the `0825e17` build), not pro HEAD.

**Approach:** A suite in the Pro repo asserts the four touch points (AD-6) and Pro's own hooks, run three ways: the two archives in Playground (the proof), the same archives on the MySQL upgrade fixture `upg` with screenshots of the licence page, the media list column and the attachment provenance, and Pro's existing box suites against pro HEAD with the two keys Nathan issued (supporting evidence, not the proof).

## Boundaries & Constraints

**Always:** the free side is the 4.0.0 archive and the Pro side is `../dist/vergelabs-media-library-pro-1.0.2.zip` (sha256 `2a6a7946426f…`), pinned by hash. The licence used is the one-seat box licence (`VGMLPRO_SEATS_KEY`); every activation is followed by a deactivation that gives the seat back, in the same run. Mock mode on the free side, so a describe costs nothing. Counters in `$GLOBALS`, `N/N passed`.

**Never:** bump Pro; change any of the four touch points in free; run a describe against the service; print a key; touch `/var/www/wp`'s Pro beyond running its own suites there.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Activation | free archive active, Pro archive activated | no fatal; `vgmlpro_base_plugin_active()` true; `vgmlpro_ready()` false until a licence | — |
| Licence | seats key in `vgmlpro_licence_key`, `vgmlpro_refresh('activate')` | `vgmlpro_is_active()` true; features load; deactivate at the end gives the seat back | a refused seat: FAIL with the state's reason |
| Index | `vergeml_index_set()` a row, `vergeml_index_get()` it | keys `alt`, `model`, `locked`, `error`, `described_at` present | — |
| Profile | `vergeml_ai` option | carries `site_profile` (Pro reads it) | — |
| Column | `manage_media_columns` filter and `vergeml_list_our_columns()` | both carry `vgmlpro_source` | — |
| Screens (box) | `upg` with both archives and the licence | licence page 200, media list 200 with the Alt text column, attachment screen 200 | — |
| Pro suites (box, HEAD) | `VGMLPRO_SEATS_KEY`, `VGMLPRO_EXPIRED_KEY` in the environment | `node tools/verify.mjs` in `pro/`: every suite `N/N passed` | a failure is recorded, not fixed, in this story |

</frozen-after-approval>

## Code Map

- `../dist/vergelabs-media-library-pro-1.0.2.zip` — the customer's Pro; its `includes/licence.php` (347 lines): key in plain option `vgmlpro_licence_key`, `vgmlpro_refresh( 'check'|'activate'|'deactivate' )`, `vgmlpro_is_active()`; no `vgmlpro_set_key`/`vgmlpro_seal` (those are HEAD's).
- `pro/vergelabs-media-library-pro.php:105-131` — `vgmlpro_ready()` (base active + licence active), `vgmlpro_load_features()` on `plugins_loaded` 20; callable again after activation to load `describe.php`/`provenance.php` in the same process.
- Touch points: basename `vergelabs-media-library/vergelabs-media-library.php`; `core/ai-index.php:276` `vergeml_index_get()` / `:319` `vergeml_index_set()`; `core/ai.php` `vergeml_ai_settings()['site_profile']`; `core/media-list.php:50-53` `vergeml_list_our_columns()` lists `vgmlpro_source`; `pro/includes/provenance.php:165-173` registers the column.
- `pro/tools/verify.mjs:142-190` — the Playground leg mounts `FREE` (working tree) and `ROOT`; a new suite flag `archives: true` unzips the two `../dist` archives into a temp dir and mounts those instead.
- Box: `tools/box-upgrade-site.sh plugin <zip>` installs any zip on `upg`; `/var/www/wp` carries pro HEAD (13,950-byte `licence.php`, `vgmlpro_seal` present) — that is what Pro's box suites test.
- Screenshots: the `upg-shot.mjs` pattern (Playwright, login as `vgmls22`, 1440×900).

## Tasks & Acceptance

**Execution:**
- [x] `pro/tests/compat-free.php` — the matrix rows Activation, Licence, Index, Profile, Column as checks; activates with the seats key and deactivates at the end; `N/N passed`.
- [x] `pro/tools/verify.mjs` — suite `compat-free` with `where: 'playground', archives: true, needsKey: true`; the archives leg unzips `../dist/vergelabs-media-library-4.0.0.zip` and `…-pro-1.0.2.zip` (hashes asserted) and mounts them.
- [x] Run it: `VGMLPRO_SEATS_KEY=… node tools/verify.mjs compat-free` in `pro/` — the proof.
- [x] `upg`: install the Pro archive, run `compat-free` there through the plugin repo's `verify.mjs` (`wp: /var/www/upg`, `also:` the suite), three screenshots into `docs/superpowers/mocks/shots/2026-09-20-pro-on-4-0-0-*.png`, deactivate the licence; the fixture keeps Pro installed but inactive.
- [x] Pro's own suites on the box with both keys: `node tools/verify.mjs` in `pro/`; every `N/N passed` line recorded as HEAD evidence.

**Acceptance Criteria:**
- Given the two archives in Playground and the seats key, when `compat-free` runs, then it prints `N/N passed` with the four touch points green and the seat given back.
- Given `upg`, when the same suite runs on MySQL, then it is green and the three screens answer 200 with the Alt text column visible.
- Given the keys, when Pro's suites run on the box, then each prints `N/N passed`, or the failing line is recorded.

## Implementation Notes

- **Commits:** pro `c84ecef` (the suite and the archives leg), plugin `3416589` (`compat-free-upg` and the three screenshots), plugin `a1a5a7c` ([tools/upg-pro-shots.mjs](../../tools/upg-pro-shots.mjs), the screenshot step as a repo tool — the matrix's Screens row needs a covering check that can be re-run, and a scratchpad script is not one). After review: pro `81f6fd3`, plugin `45a96a4` (triage rows 1, 4, 7, 12, 13, 14, 17, 20). Nothing spent.
- **After the review patches:** Playground on the archives `20/20 passed` — the new Profile rows: `the saved brief lands in vergeml_ai[site_profile], the key Pro reads -- compat probe profile` and `Pro's describe sends that brief as profile, with this site and its key -- profile "compat probe profile", site http://127.0.0.1:64069, key the seats key` (the service stood in for by `pre_http_request`, nothing spent); `upg` on MySQL `20/20 passed`, `site http://upg.46.225.66.194.nip.io`; `compat-free-upg` with Pro inactive → `SKIPPED — exit 2`, the runner's own skipped line, not a FAIL; `tools/upg-pro-shots.mjs` → `licence 200 ok · media-list 200 ok, 20 of 20 cells say "We wrote" · attachment 200 ok`, `exit 0`, `released 0/1; key cleared`. `upg` after everything: 20 attachments, 0 probe files, 20 alts, `site_profile` empty, key option absent, Pro `inactive 1.0.2`, 0 Pro lines in `debug.log`.
- **The archives leg** ([pro/tools/verify.mjs](../../../pro/tools/verify.mjs)): a suite marked `archives: true` unzips `../dist`'s two zips into the temp dir after asserting their sha256 (`bf0d63b70056`, `2a6a7946426f`) and mounts those instead of the working trees, with `pro/tests` mounted beside them at `/wordpress/wp-content/vgmlpro-tests` because the suite is not inside the archive. `--tree` overrides it (the mutation run, or pro HEAD later). `unzip`, not `tar`: Git Bash's tar reads `C:` as a host and does not read zips.
- **The suite** ([pro/tests/compat-free.php](../../../pro/tests/compat-free.php)) writes the key with `vgmlpro_set_key()` when it exists (HEAD, sealed) and `update_option` otherwise (the archive); with `VGMLPRO_COMPAT_ARCHIVES` set it pins free `4.0.0` / Pro `1.0.2`. It restores the key, the licence state, the `vergeml_ai` option and deletes its probe index row (id `2147480000`) from a shutdown function, and hands the seat back inside the run as its own check.
- **Proof, Playground on the archives:** `17/17 passed`, `deactivating gives the seat back -- sites_used 1 -> 0 (1 taken), valid yes`.
- **Mutation:** `vgmlpro_source` removed from `vergeml_list_our_columns()` (`core/media-list.php:52`), `node tools/verify.mjs compat-free --tree` → `15/16 passed`, `FAIL the free media list names vgmlpro_source as one of ours -- vergeml_used, taxonomy-media_category`; the seat still went back. Reverted with an edit, not a checkout.
- **MySQL (`upg`):** the Pro archive installed with `bash box-upgrade-site.sh plugin /tmp/vgmlpro-102.zip` (sha256 `2a6a7946426f` on the box), `node tools/verify.mjs compat-free-upg` → `17/17 passed`, `sites_used 1 -> 0`. After: `vergeml_ai` back to `site_profile ""`, key empty, state `[]`, probe row `0`, the fixture's 20 alts intact. `debug.log`: 0 lines naming Pro across install, activation, three admin screens, the suite and `wp plugin deactivate`. Pro left `inactive 1.0.2`. Note: the archive deactivated on the box without a fatal — the fatal `seats.php` names is HEAD's on `/var/www/wp`.
- **Screens** (`node tools/upg-pro-shots.mjs`, Playwright as `vgmls22`, 1440×900, licence activated before and released after in a `finally`; run twice, the second time from the repo, `exit 0`): licence page `200` with "Connected" (Single, 1 / 1, 2,000 credits — the key masked by Pro's own `vgmlpro_mask_key`, 9 + 4 characters); media list `200` with the Alt text column visible and Pro's cell on all 20 described pictures ("Mock alt for… / We wrote this…"); attachment screen `200`, alt present. The column is off the default set in 4.0.0 as it was in 3.16.1 (`default_hidden_columns`); the script turned it on through the same user option Screen Options writes and put the option back (`was false`).
- **Pro's suites on the box (HEAD `a57bc77`, `licence.php` 13,950 bytes, sealed key at rest; free there 4.0.0):** api-base **2/3** (below), api-base-https `2/2`, api-base-insecure `1/1`, api-base-localhost `1/1`, licence `5/5`, updates `4/4`, seats `3/3`, seats-staging `1/1`, seats-deactivate `2/2`, compat-free `17/17`.
- **Recorded, not fixed — `api-base` on the box:** `FAIL the base agrees with the free plugin -- http://127.0.0.1:3100/v1`. `/var/www/wp/wp-config.php:101` defines `VERGEML_AI_SERVICE = http://127.0.0.1:3100/v1` (a `next-server` listening on the box), so the tech site's free plugin names a different host than Pro's `https://ai.vergelabs.nl`. The box's configuration, not Pro's code; the check is right to say so.
- **Found, not done:** (1) Pro 1.0.2 has no attachment-screen hook — provenance lives only in the media list's `vgmlpro_source` cell (`provenance.php:165-173`); the epic's "attachment screen shows provenance" describes a screen Pro never had, the matrix's "attachment screen 200" is what holds. (2) The 4.0.0 archive ships `.harness/active.json` (3,986 bytes): `.harness` is not `export-ignore` in `.gitattributes`. The archive is cut and tagged; a line for 4.0.1. (3) `deploy.mjs --check` says the tech site holds another build (`184e21aa924d` vs the tree's `522000d9d997`); not deployed in this story — Pro's box suites do not read the free build beyond the base URL. (4) `AGENTS.md` says 48 suites are registered; it is 49.

## Spec Change Log

## Review Triage Log

Pass 1, 2026-09-20 — blind-hunter 13, edge-case-hunter 19, verification-gap 1 + 3. Rows are `layer · location · claim → verdict · evidence · route`.

| # | Finding | Verdict | Evidence | Route |
|---|---------|---------|----------|-------|
| 1 | BH/VG · `compat-free.php` Profile section · writes `vergeml_ai` itself and reads it back; neither free's writer nor Pro's reader runs | medium | True: `update_option`/`get_option` by the suite, copied from `describe.php:58-59`. A free change to where the brief is stored ships green. Fix inside the suite: write through `vergeml_ai_rest_settings( WP_REST_Request )` (`core/ai.php:2079`, spends nothing) and read through `vgmlpro_describe_attachment()` behind `pre_http_request`, asserting the captured `/v1/describe` body carries the profile — Pro's own read, no spend | patch |
| 2 | BH · a fifth touch point: Pro's `update_post_meta( '_wp_attachment_image_alt' )` (`describe.php:232`) outside `vergeml_index_writing()` makes free's `vergeml_index_watch_alt` lock `alt` | low | Real, but 3.16.1 carries the same `vergeml_index_watch_alt` (`core/ai-index.php:565` in the 3.16.1 archive) — the lock happens on 3.16.1 exactly as on 4.0.0; Pro's column reads its own record first (`vgmlpro_provenance_state`) so it stays "We wrote this". Pre-existing; the lock means "do not paint over", which may be intended | defer |
| 3 | BH/EC-claim · "features stay unloaded" cannot catch a gate that loads `describe.php` on a site licensed at boot | low | True in general; both proof legs boot unlicensed (fresh Playground, `upg` with an empty key), so the check was meaningful where it ran. Fix adds a branch for a case the story never meets | reject (low) |
| 4 | BH/EC/VG · `compat-free-upg` in the free battery exits 1 without the key or with Pro inactive on `upg` — the spec's own end state | medium | True: bare `node tools/verify.mjs` on the free side goes red on this suite. The free runner reads exit 2 as SKIPPED, loudly (`verify.mjs:822-835`). The suite exits 2 for a missing key and for Pro not loaded, saying why | patch |
| 5 | BH · restore is a shutdown function only; a killed process leaves the key/profile/seat | low | True for a kill only — `exit(1)` and a fatal both run it; same shape as `seats.php`, the mirror. A caller-side `finally` is a runner change for a case not met in everyday use | reject (low) |
| 6 | BH · the seat-back check cascades when activation was refused | low | True: `sites_used 0 -> n` prints a second FAIL for one cause. Everyday use never refuses; the fix is a branch | reject (low) |
| 7 | BH/EC-claim · `upg-pro-shots.mjs` exits 0 when the notes say the column is not visible / no Connected / no alt | medium | True: `ok` tracks only `response.status()`. The tool reads as pass/fail and the story is "does Pro break"; `look()` returns its verdict and it folds into `ok`, with the URL still under `/wp-admin/` (EC: a login redirect answers 200) | patch |
| 8 | BH/EC · `described` may be `0` / `uid` may be a warning string | low | True on a fixture without described rows or without `vgmls22`; the fixture has both by construction (story 1.2). Guards for a state the tool never meets | reject (low) |
| 9 | BH · `wpEval` drops the exit code behind the pipe | low | True; a fatal comes back as text, and every caller either compares the text (`activated`) or prints it. Handling the status would make the `finally` itself able to throw | reject (low) |
| 10 | BH/EC/VG · `unpackArchive` throws uncaught when `unzip` is missing | low | True and loud (a stack trace, not a false pass); Git Bash has `unzip`. A guard for a shell the runner is not run from | reject (low) |
| 11 | BH · `vergeml_ai_settings() carries site_profile` is true by construction | false | It reads the defaults array (`core/ai.php:39`); a rename of the key there turns it red, which is what a contract check pins. Against a hash-pinned archive every check is "true by construction" — that is the point | reject (false) |
| 12 | BH/EC · `cf_restore` writes `''` options where none existed | low | True: `get_option( KEY, '' )` then `update_option` creates the row; the `ai` branch three lines down already does it right. A deletion-shaped correction | patch |
| 13 | BH · the mutation line names `core/media-list.php` without the repo | low | True; `../plugin/core/media-list.php` | patch |
| 14 | EC · shots: Pro inactive on `upg` (the spec's end state) → `vgmlpro_refresh` undefined after the key was written; the `finally`'s clear never runs | medium | True: the activate eval writes the option first, the fatal follows, the `finally` eval fatals on the same call before its `update_option`. The test key stays at rest on the fixture. Check `function_exists( 'vgmlpro_refresh' )` before writing anything, and guard the refresh in the `finally` | patch |
| 15 | EC · activation eval throws on an ssh drop outside the `try` | low | True, unlikely; adds a try | reject (low) |
| 16 | EC · `hadHidden` not JSON → restore garbage | low | True if a notice leaks past the filter; not met on the fixture | reject (low) |
| 17 | EC · a failed deactivate leaves `cf_seat` false so the restore does not retry | low | True: the flag is cleared before the request. The stop point is "every activation is followed by a deactivation" — clear the flag only on `valid === true` | patch |
| 18 | EC · `vergeml_ai_settings` / `vergeml_index_table_exists` absent → fatal, not FAIL | low | True on a free build without them; the pinned 4.0.0 has both. Guards | reject (low) |
| 19 | EC · seat ordering: `seats` without `seats-deactivate`, or while `upg` holds the seat → `seat_limit` | low | True; the refusal prints `refused: seat_limit`, and the full run orders `seats-deactivate` before `compat-free` | reject (low) |
| 20 | EC-claim · "the versions are pinned so a working tree cannot pass for the archives" overclaims — HEAD trees carry the same versions; only the Playground hash pins bytes, `compat-free-upg` trusts the install | low | True; the docblock says so plainly now: the hash pins the Playground leg, the box leg trusts `box-upgrade-site.sh plugin <zip>` (sha256 read on the box before install) | patch |
| 21 | VG-other · no pairing for free HEAD against the Pro 1.0.2 archive (the next free release's question) | low | True; the frozen intent pins both sides to the archives. A `--free-tree` mode for the release after 4.0.0 | defer |

## Verification

**Commands:**
- `node tools/verify.mjs compat-free` in `pro/` (keys in the environment) — expected: `N/N passed`, `seat given back`
- `node tools/verify.mjs compat-free-upg` in `plugin/` — expected: `N/N passed` on `/var/www/upg`
- `node tools/verify.mjs` in `pro/` — expected: api-base ×4, licence, updates, seats ×3 each `N/N passed`
- Cost: none (mock describe only; licence checks are free)
