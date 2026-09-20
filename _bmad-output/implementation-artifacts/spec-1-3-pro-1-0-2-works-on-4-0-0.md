---
title: 'Pro 1.0.2 works on 4.0.0'
type: 'feature'
created: '2026-09-20'
status: 'ready-for-dev'
route: 'dispatch'
review_loop_iteration: 0
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
- [ ] `pro/tests/compat-free.php` — the matrix rows Activation, Licence, Index, Profile, Column as checks; activates with the seats key and deactivates at the end; `N/N passed`.
- [ ] `pro/tools/verify.mjs` — suite `compat-free` with `where: 'playground', archives: true, needsKey: true`; the archives leg unzips `../dist/vergelabs-media-library-4.0.0.zip` and `…-pro-1.0.2.zip` (hashes asserted) and mounts them.
- [ ] Run it: `VGMLPRO_SEATS_KEY=… node tools/verify.mjs compat-free` in `pro/` — the proof.
- [ ] `upg`: install the Pro archive, run `compat-free` there through the plugin repo's `verify.mjs` (`wp: /var/www/upg`, `also:` the suite), three screenshots into `docs/superpowers/mocks/shots/2026-09-20-pro-on-4-0-0-*.png`, deactivate the licence; the fixture keeps Pro installed but inactive.
- [ ] Pro's own suites on the box with both keys: `node tools/verify.mjs` in `pro/`; every `N/N passed` line recorded as HEAD evidence.

**Acceptance Criteria:**
- Given the two archives in Playground and the seats key, when `compat-free` runs, then it prints `N/N passed` with the four touch points green and the seat given back.
- Given `upg`, when the same suite runs on MySQL, then it is green and the three screens answer 200 with the Alt text column visible.
- Given the keys, when Pro's suites run on the box, then each prints `N/N passed`, or the failing line is recorded.

## Implementation Notes

## Spec Change Log

## Review Triage Log

## Verification

**Commands:**
- `node tools/verify.mjs compat-free` in `pro/` (keys in the environment) — expected: `N/N passed`, `seat given back`
- `node tools/verify.mjs compat-free-upg` in `plugin/` — expected: `N/N passed` on `/var/www/upg`
- `node tools/verify.mjs` in `pro/` — expected: api-base ×4, licence, updates, seats ×3 each `N/N passed`
- Cost: none (mock describe only; licence checks are free)
